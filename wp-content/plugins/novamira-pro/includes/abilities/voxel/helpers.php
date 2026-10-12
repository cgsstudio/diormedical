<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Voxel theme integration.
 *
 * Provides:
 *  - Error factories with HTTP status codes (vx_error + named constructors)
 *  - Setup detection snapshot (vx_detect_setup, cached per request)
 *  - Progressive-disclosure truncation (vx_truncate_field + byte-cap constants)
 *  - Input coercion / clamping (vx_clamp_int, vx_coerce_string_list, vx_coerce_bool)
 *  - Entity gates (vx_post_type_or_error, vx_post_or_error, vx_field_or_error,
 *    vx_custom_table_or_error)
 *  - Compact row projections (vx_post_type_row, vx_field_row, vx_post_row)
 */

// ---------------------------------------------------------------------------
// Error factories
// ---------------------------------------------------------------------------

/**
 * Uniform WP_Error factory. The HTTP status is embedded in `$data['status']` so
 * the Abilities API returns the right 4xx/5xx over REST instead of a blanket 500.
 *
 * Code-to-status map:
 *   voxel_invalid_input=400, voxel_forbidden=403, voxel_not_found=404,
 *   voxel_not_active=412, voxel_version_unsupported=412, voxel_locked=409,
 *   voxel_operation_failed=500
 */
function vx_error(string $code, string $message): WP_Error
{
    $status_map = [
        'voxel_invalid_input' => 400,
        'voxel_forbidden' => 403,
        'voxel_not_found' => 404,
        'voxel_not_active' => 412,
        'voxel_version_unsupported' => 412,
        // Lock contention on a config blob is transient — signal a retryable
        // conflict (409) so the caller retries instead of rewriting its input.
        'voxel_locked' => 409,
        'voxel_operation_failed' => 500,
    ];
    $status = $status_map[$code] ?? 400;
    return new WP_Error($code, $message, ['status' => $status]);
}

function vx_not_active(): WP_Error
{
    return vx_error('voxel_not_active', __('The Voxel theme is not active on this site.', domain: 'novamira-pro'));
}

function vx_version_unsupported(): WP_Error
{
    return vx_error('voxel_version_unsupported', sprintf(
        /* translators: %1$s installed version, %2$s minimum version. */
        __('The Voxel theme is version %1$s, but these abilities require %2$s or newer.', domain: 'novamira-pro'),
        voxel_theme_version() !== '' ? voxel_theme_version() : __('unknown', domain: 'novamira-pro'),
        vx_min_version(),
    ));
}

function vx_invalid(string $message): WP_Error
{
    return vx_error('voxel_invalid_input', $message);
}

function vx_not_found(string $entity, string $id): WP_Error
{
    return vx_error('voxel_not_found', sprintf(
        /* translators: %1$s entity kind, %2$s identifier. */
        __('No %1$s found for "%2$s".', domain: 'novamira-pro'),
        $entity,
        $id,
    ));
}

function vx_forbidden(string $message): WP_Error
{
    return vx_error('voxel_forbidden', $message);
}

function vx_operation_failed(string $message): WP_Error
{
    return vx_error('voxel_operation_failed', $message);
}

/**
 * The last database error, read through a mixed boundary: wpdb types the
 * property with its initial '' literal, so a direct !== '' check reads as
 * statically impossible to the analyzer even though it is the canonical
 * "did the query fail" signal at runtime.
 */
function vx_db_last_error(): string
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    /** @var mixed $error */
    $error = $wpdb->last_error;
    return is_string($error) ? $error : '';
}

// ---------------------------------------------------------------------------
// Setup detection
// ---------------------------------------------------------------------------

/**
 * Snapshot of the Voxel setup state. Deliberately NOT cached: it reads the
 * detected version live (through the novamira/voxel/detected_version filter) so
 * check-setup and the per-callback guard vx_require_min() never disagree within
 * a request. The underlying theme-header read is already cached by WordPress.
 *
 * @return array{present: bool, active: bool, version: string, min_required: string, min_satisfied: bool, php_version: string, php_required: string, php_satisfied: bool}
 */
function vx_detect_setup(): array
{
    $present = voxel_theme_present();
    $version = voxel_theme_version();
    $php_required = '8.1';

    return [
        'present' => $present,
        'active' => $present,
        'version' => $version,
        'min_required' => vx_min_version(),
        'min_satisfied' => vx_min_satisfied(),
        'php_version' => PHP_VERSION,
        'php_required' => $php_required,
        'php_satisfied' => version_compare(PHP_VERSION, $php_required, operator: '>='),
    ];
}

// ---------------------------------------------------------------------------
// Progressive disclosure — byte caps
// ---------------------------------------------------------------------------

/** Default per-field byte cap for value reads. */
function vx_default_max_field_bytes(): int
{
    return 4096;
}

/** Hard ceiling for any caller-supplied max_field_bytes. */
function vx_absolute_max_field_bytes(): int
{
    return 65_536;
}

/**
 * Truncate a string to a UTF-8-safe byte length, returning a marker so the size
 * signal survives even when the value is clipped.
 *
 * @return array{value: string, truncated: bool, full_size: int}
 */
function vx_truncate_field(string $value, int $max_bytes): array
{
    $size = strlen($value);
    if ($max_bytes <= 0 || $size <= $max_bytes) {
        return ['value' => $value, 'truncated' => false, 'full_size' => $size];
    }
    return ['value' => vx_utf8_safe_cut($value, $max_bytes), 'truncated' => true, 'full_size' => $size];
}

/**
 * Cut a string to at most $max_bytes without splitting a UTF-8 sequence.
 * Dependency-free on purpose: mbstring is not a plugin requirement, so this
 * must not rely on mb_strcut(). After the byte cut, a trailing lead byte that
 * announces more continuation bytes (0x80-0xBF) than remain is dropped along
 * with anything after it. On VALID UTF-8 the result is byte-identical to
 * mb_strcut(). On malformed input the trim is slightly more aggressive than
 * mb_strcut (a trailing byte that merely LOOKS like a cut-off lead — e.g.
 * "A\xC2" where no continuation ever followed — is dropped too); that stays
 * safe: at most 3 trailing bytes are lost, never added. Trailing bytes that
 * cannot be a cut-off sequence (ASCII, or continuation runs with no lead
 * nearby) keep the plain byte cut.
 */
function vx_utf8_safe_cut(string $value, int $max_bytes): string
{
    $clipped = substr($value, offset: 0, length: $max_bytes);
    $length = strlen($clipped);

    // Find the last non-continuation byte within the final 4 bytes (a UTF-8
    // sequence is at most 4 bytes, so a longer continuation run is invalid).
    $floor = max(0, $length - 4);
    $lead_pos = -1;
    for ($i = $length - 1; $i >= $floor; $i--) {
        if ((ord($clipped[$i]) & 0b1100_0000) === 0b1000_0000) {
            continue;
        }
        $lead_pos = $i;
        break;
    }
    if ($lead_pos === -1) {
        return $clipped;
    }

    $lead = ord($clipped[$lead_pos]);
    if ($lead < 0b1000_0000) {
        // ASCII tail: the cut cannot have split a sequence.
        return $clipped;
    }
    if ($lead >= 0xC0 && $lead <= 0xF7) {
        $sequence_length = match (true) {
            $lead >= 0xF0 => 4,
            $lead >= 0xE0 => 3,
            default => 2,
        };
        if (($lead_pos + $sequence_length) > $length) {
            return substr($clipped, offset: 0, length: $lead_pos);
        }
    }

    return $clipped;
}

/**
 * Resolve a caller-supplied max_field_bytes to a concrete cap in [1, ceiling].
 * Non-integers fall back to the default; integers are clamped so the hard
 * ceiling is always respected and a 0/negative can never disable the cap.
 */
function vx_resolve_max_field_bytes(mixed $raw): int
{
    if (!is_int($raw)) {
        return vx_default_max_field_bytes();
    }
    return max(1, min(vx_absolute_max_field_bytes(), $raw));
}

// ---------------------------------------------------------------------------
// Input coercion / clamping
// ---------------------------------------------------------------------------

/**
 * Coerce a value to an int, accepting a real int, a whole float, or an
 * integer-valued numeric string (e.g. "5"). Returns null for anything else.
 *
 * The Abilities API validates input_schema non-strictly and passes the raw
 * (un-coerced) value to the callback, so a JSON string like "5" reaches an
 * `integer` parameter and must be coerced here rather than dropped.
 */
function vx_coerce_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value)) {
        return floor($value) === $value ? (int) $value : null;
    }
    if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
        return (int) $value;
    }
    return null;
}

/**
 * Clamp an integer input to [min, max], falling back to $default for any
 * value that is not an int / whole float / integer-valued string.
 */
function vx_clamp_int(mixed $raw, int $min, int $max, int $default): int
{
    $int = vx_coerce_int($raw);
    if ($int === null) {
        return $default;
    }
    return max($min, min($max, $int));
}

/**
 * Coerce a value into a list of non-empty strings (drops non-strings and blanks).
 *
 * @return list<string>
 */
function vx_coerce_string_list(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $item */
    foreach ($raw as $item) {
        if (!is_string($item) || $item === '') {
            continue;
        }
        $out[] = $item;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Entity gates
// ---------------------------------------------------------------------------

/**
 * Resolve a Voxel-managed post type by key, or a WP_Error. Rejects non-Voxel WP
 * post types (native `page`, other plugins' CPTs) so callers never operate on a
 * surface Voxel does not model.
 *
 * @return \Voxel\Post_Type|WP_Error
 */
function vx_post_type_or_error(string $key): object
{
    if ($key === '') {
        return vx_invalid(__('A non-empty post_type key is required.', domain: 'novamira-pro'));
    }
    $post_type = \Voxel\Post_Type::get($key);
    if (!is_object($post_type) || !$post_type->is_managed_by_voxel()) {
        return vx_not_found('Voxel post type', $key);
    }
    return $post_type;
}

/**
 * Resolve a Voxel post by id, or a WP_Error. Rejects missing posts, revisions,
 * auto-drafts, trashed posts, and posts whose type Voxel does not manage.
 *
 * @return \Voxel\Post|WP_Error
 */
function vx_post_or_error(int $post_id): object
{
    if ($post_id <= 0) {
        return vx_invalid(__('A positive post id is required.', domain: 'novamira-pro'));
    }
    /** @var mixed $wp_post */
    $wp_post = get_post($post_id);
    if (!$wp_post instanceof WP_Post) {
        return vx_not_found('post', (string) $post_id);
    }
    if ($wp_post->post_type === 'revision') {
        return vx_invalid(__('Revisions cannot be read as Voxel posts.', domain: 'novamira-pro'));
    }
    if (in_array($wp_post->post_status, ['auto-draft', 'trash'], strict: true)) {
        return vx_invalid(sprintf(
            /* translators: %s post status. */
            __('Post is in "%s" state and cannot be read.', domain: 'novamira-pro'),
            $wp_post->post_status,
        ));
    }
    $post = \Voxel\Post::get($post_id);
    if (!is_object($post) || !$post->is_managed_by_voxel()) {
        return vx_invalid(sprintf(
            /* translators: %1$d post id, %2$s WP post type. */
            __('Post %1$d is of type "%2$s", which is not managed by Voxel.', domain: 'novamira-pro'),
            $post_id,
            $wp_post->post_type,
        ));
    }
    return $post;
}

/**
 * Resolve a field on a post type by key, or a WP_Error.
 *
 * @param \Voxel\Post_Type $post_type
 * @return \Voxel\Post_Types\Fields\Base_Post_Field|WP_Error field on hit
 */
function vx_field_or_error(object $post_type, string $key): object
{
    if ($key === '') {
        return vx_invalid(__('A non-empty field key is required.', domain: 'novamira-pro'));
    }
    $field = $post_type->get_field($key);
    if (!is_object($field)) {
        return vx_not_found('field', $key);
    }
    return $field;
}

/**
 * Resolve a Voxel custom table (prefix + suffix) to its full name, or a clean
 * WP_Error when the table does not exist. A missing table means the matching
 * Voxel module has never run its installer — the same signal check-setup
 * reports in its `tables` map — so the caller refuses instead of running SQL
 * against a table that is not there. voxel_not_found is only returned on a
 * CLEAN query miss: a failed prepare or a driver error is its own
 * voxel_operation_failed, never misreported as "table absent".
 *
 * @param string $suffix table name without the site prefix, e.g. 'voxel_relations'
 * @return string|WP_Error the full table name on hit
 */
function vx_custom_table_or_error(string $suffix): string|WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = $wpdb->prefix . $suffix;
    $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
    if (!is_string($sql)) {
        return vx_operation_failed(sprintf(
            /* translators: %s table name without the site prefix. */
            __('Could not build the table-presence check for %s.', domain: 'novamira-pro'),
            $suffix,
        ));
    }
    /** @var mixed $found */
    $found = $wpdb->get_var($sql);
    $db_error = vx_db_last_error();
    if ($db_error !== '') {
        return vx_operation_failed(sprintf(
            /* translators: %1$s table name without the site prefix, %2$s database error summary. */
            __(
                'The table-presence check for %1$s failed (%2$s); cannot tell whether the table exists.',
                domain: 'novamira-pro',
            ),
            $suffix,
            $db_error,
        ));
    }
    if ($found !== $table) {
        return vx_error('voxel_not_found', sprintf(
            /* translators: %s table name without the site prefix. */
            __(
                'The %s table does not exist on this site — the matching Voxel module has never run its installer. See novamira/voxel-check-setup → tables.',
                domain: 'novamira-pro',
            ),
            $suffix,
        ));
    }
    return $table;
}

// ---------------------------------------------------------------------------
// Compact row projections
// ---------------------------------------------------------------------------

/**
 * Compact row for a Voxel post type (no field bodies).
 *
 * @param \Voxel\Post_Type $post_type
 * @return array{key: string, label: string, singular: string, plural: string, public: bool, fields_count: int, filters_count: int}
 */
function vx_post_type_row(object $post_type): array
{
    return [
        'key' => $post_type->get_key(),
        'label' => $post_type->get_label(),
        'singular' => $post_type->get_singular_name(),
        'plural' => $post_type->get_plural_name(),
        'public' => $post_type->wp_post_type->public,
        'fields_count' => count($post_type->get_fields()),
        'filters_count' => count($post_type->get_filters()),
    ];
}

/**
 * Compact row for a field definition (no props body).
 *
 * @param \Voxel\Post_Types\Fields\Base_Post_Field $field
 * @return array{key: string, type: string, label: string, required: bool}
 */
function vx_field_row(object $field): array
{
    return [
        'key' => $field->get_key(),
        'type' => $field->get_type(),
        'label' => $field->get_label(),
        'required' => $field->is_required(),
    ];
}

/**
 * Compact row for a search filter definition.
 *
 * @param \Voxel\Post_Types\Filters\Base_Filter $filter
 * @return array{key: string, type: string, label: string}
 */
function vx_filter_row(object $filter): array
{
    return [
        'key' => $filter->get_key(),
        'type' => $filter->get_type(),
        'label' => $filter->get_label(),
    ];
}

/**
 * Minimal reference for a post another row points at (e.g. a relation edge).
 * A reference whose post no longer exists reads title and post_type as null.
 * Voxel's standard relations table cascades row deletion with the posts, so a
 * dangling reference is not expected there — the null handling is defensive,
 * for non-standard setups (foreign keys missing or disabled) or a deletion
 * racing the read.
 *
 * @return array{id: int, title: string|null, post_type: string|null}
 */
function vx_related_post_ref(int $post_id): array
{
    /** @var mixed $wp_post */
    $wp_post = get_post($post_id);
    if (!$wp_post instanceof WP_Post) {
        return ['id' => $post_id, 'title' => null, 'post_type' => null];
    }
    return [
        'id' => $post_id,
        'title' => $wp_post->post_title,
        'post_type' => $wp_post->post_type,
    ];
}

/**
 * Compact row for a Voxel post (no field values).
 *
 * @param WP_Post $wp_post
 * @return array{id: int, title: string, status: string, author_id: int, date_gmt: string}
 */
function vx_post_row(WP_Post $wp_post): array
{
    return [
        'id' => $wp_post->ID,
        'title' => $wp_post->post_title,
        'status' => $wp_post->post_status,
        'author_id' => (int) $wp_post->post_author,
        'date_gmt' => $wp_post->post_date_gmt,
    ];
}
