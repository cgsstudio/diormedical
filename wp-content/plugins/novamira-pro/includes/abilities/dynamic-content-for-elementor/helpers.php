<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicContentForElementor;

use DynamicContentForElementor\PdfHtmlTemplates;
use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The post type Dynamic Content for Elementor stores its PDF layouts in. Registered by the
 * plugin as non-public with a UI, so it never answers to a front-end request and
 * `get_post_type_object()` is the only reliable probe that it exists.
 *
 * Taken from the plugin rather than spelled out here. A literal copy would keep
 * addressing the old name after a rename, silently and without an error; reading
 * the plugin's own constant turns the same rename into a failure at load.
 */
const DCE_TEMPLATE_POST_TYPE = PdfHtmlTemplates::CPT;

/**
 * The single post meta key that holds a template's entire configuration, as one
 * array. It happens to be spelled the same as the post type; they are unrelated.
 */
const DCE_TEMPLATE_META_KEY = PdfHtmlTemplates::TEMPLATE_META_KEY;

/**
 * Every sub-key of that array, in the order the plugin writes them.
 *
 * They live inside one meta value, and `update_post_meta()` replaces a value
 * whole, so writing a subset of them would drop the rest. Every write in this
 * specialization merges onto what is stored, and this list is what "the whole
 * array" means. Upstream happens to rewrite all seven on each of its own saves
 * too, which is why the two never disagree about the shape.
 *
 * @var array<string, string>
 */
const DCE_TEMPLATE_FIELDS = [
    'code' => PdfHtmlTemplates::FIELD_CODE,
    'is_elementor_template' => PdfHtmlTemplates::FIELD_IS_TEMPLATE,
    'elementor_template_id' => PdfHtmlTemplates::FIELD_TEMPLATE_ID,
    'format' => PdfHtmlTemplates::FIELD_FORMAT,
    'orientation' => PdfHtmlTemplates::FIELD_ORIENTATION,
    'preview_form_data' => PdfHtmlTemplates::FIELD_PREVIEW_FORM_DATA,
    'preview_post' => PdfHtmlTemplates::FIELD_PREVIEW_POST,
];

/**
 * The same seven, keyed by the stored sub-key, so a write can check membership
 * without scanning.
 *
 * @var array<string, string>
 */
const DCE_TEMPLATE_FIELDS_BY_KEY = [
    PdfHtmlTemplates::FIELD_CODE => 'code',
    PdfHtmlTemplates::FIELD_IS_TEMPLATE => 'is_elementor_template',
    PdfHtmlTemplates::FIELD_TEMPLATE_ID => 'elementor_template_id',
    PdfHtmlTemplates::FIELD_FORMAT => 'format',
    PdfHtmlTemplates::FIELD_ORIENTATION => 'orientation',
    PdfHtmlTemplates::FIELD_PREVIEW_FORM_DATA => 'preview_form_data',
    PdfHtmlTemplates::FIELD_PREVIEW_POST => 'preview_post',
];

/**
 * Canonical HTTP status for every DCE error code. Unknown codes are treated as
 * unexpected server failures by dce_error(), so adding a new call site cannot
 * accidentally label an unclassified failure as caller-correctable.
 *
 * @return array<string, int>
 */
function dce_error_map(): array
{
    return [
        'ambiguous_render_mode' => 400,
        'delete_failed' => 500,
        'elementor_template_not_found' => 404,
        'invalid_cursor' => 400,
        'invalid_format' => 400,
        'invalid_input' => 400,
        'invalid_orientation' => 400,
        'lock_conflict' => 409,
        'meta_write_failed' => 500,
        'not_a_template' => 400,
        'page_setup_missing' => 400,
        // Partial means the server-side rollback failed, not a stale caller write.
        'partial_write' => 500,
        'post_create_failed' => 500,
        'post_type_missing' => 412,
        'post_update_failed' => 500,
        'template_not_editable' => 400,
        'template_not_found' => 404,
    ];
}

/**
 * Build a namespaced WP_Error, so every failure in this surface is recognisable
 * by prefix and carries structured data the agent can act on. The optional
 * status is only for context-dependent cases; normal callers use the map above.
 *
 * @param array<string, mixed> $data
 */
function dce_error(string $code, string $message, array $data = [], ?int $status = null): WP_Error
{
    $status ??= dce_error_map()[$code] ?? 500;
    return new WP_Error('dce_' . $code, $message, ['status' => $status] + $data);
}

/**
 * Wrap a WP_Error returned by wp_insert_post()/wp_update_post(). Known input
 * refusals keep their 4xx semantics; an upstream status is honoured, while an
 * unclassified failure remains a real 500. The original code and data stay
 * available for diagnosis without leaking an unnamespaced error to the surface.
 *
 * @param 'create'|'update' $operation
 */
function dce_wp_post_error(WP_Error $error, string $operation): WP_Error
{
    $wp_code = (string) $error->get_error_code();
    /** @var mixed $wp_data */
    $wp_data = $error->get_error_data();
    $data = ['wp_code' => $wp_code];
    if ($wp_data !== null) {
        $data['wp_data'] = $wp_data;
    }

    return dce_error(
        $operation === 'create' ? 'post_create_failed' : 'post_update_failed',
        sprintf('WordPress refused to %s the HTML Template: %s', $operation, $error->get_error_message()),
        $data,
        status: dce_wp_post_error_status($wp_code, $wp_data),
    );
}

/** Return the REST status appropriate for a WordPress post-write error. */
function dce_wp_post_error_status(string $wp_code, mixed $wp_data): int
{
    if (is_array($wp_data)) {
        /** @var mixed $upstream_status */
        $upstream_status = $wp_data['status'] ?? null;
        if (is_int($upstream_status) && $upstream_status >= 400 && $upstream_status <= 599) {
            return $upstream_status;
        }
    }

    return match ($wp_code) {
        'empty_content', 'invalid_date', 'invalid_page_template', 'invalid_post_type' => 400,
        'invalid_post' => 404,
        default => 500,
    };
}

/**
 * Resolve a post id to a usable HTML Template, or explain precisely why not.
 *
 * Rejects the three shapes `get_post()` accepts but callers never mean: a post of
 * another type, a revision, and anything in the trash or still an auto-draft.
 * Without this an edit would silently write template configuration onto an
 * unrelated post. `$blocked_statuses` is narrowed by the one caller that
 * legitimately addresses a trashed template: a permanent delete.
 *
 * @param list<string> $blocked_statuses
 *
 * @return WP_Post|WP_Error
 */
function dce_template_or_error(int $post_id, array $blocked_statuses = ['trash', 'auto-draft']): WP_Post|WP_Error
{
    if ($post_id <= 0) {
        return dce_error('invalid_input', message: 'template_id must be a positive integer.');
    }

    /** @var WP_Post|array<mixed>|null $post */
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return dce_error('template_not_found', sprintf('No post with id %d exists.', $post_id));
    }

    if ($post->post_type !== DCE_TEMPLATE_POST_TYPE) {
        return dce_error(
            'not_a_template',
            sprintf(
                'Post %d is a "%s", not a Dynamic Content for Elementor HTML Template.',
                $post_id,
                $post->post_type,
            ),
            ['post_type' => $post->post_type],
        );
    }

    if (in_array($post->post_status, $blocked_statuses, strict: true)) {
        return dce_error(
            'template_not_editable',
            sprintf('HTML Template %d is %s. Restore it before reading or editing it.', $post_id, $post->post_status),
            ['post_status' => $post->post_status],
        );
    }

    return $post;
}

/**
 * Read a template's stored configuration as a full seven-key array.
 *
 * The meta is absent until the plugin's own screen saves the template once, and
 * upstream reads a missing sub-key as an empty string, so this fills the gaps the
 * same way rather than returning a partial array that a later merge would treat
 * as an instruction to blank the rest.
 *
 * @return array<string, string>
 */
function dce_read_template_meta(int $post_id): array
{
    /** @var mixed $raw */
    $raw = get_post_meta($post_id, DCE_TEMPLATE_META_KEY, single: true);
    /** @var array<string, mixed> $stored */
    $stored = is_array($raw) ? $raw : [];

    $out = [];
    foreach (DCE_TEMPLATE_FIELDS as $field) {
        /** @var mixed $value */
        $value = $stored[$field] ?? '';
        $out[$field] = is_scalar($value) ? (string) $value : '';
    }

    return $out;
}

/**
 * Present a stored template as the shape this surface returns: friendly key
 * names, a boolean for the mode flag, and the Elementor template id as an
 * integer so the caller can pass it straight to an Elementor ability.
 *
 * @param array<string, string> $meta
 * @return array{renders: string, is_elementor_template: bool, elementor_template_id: int, format: string, orientation: string}
 */
function dce_present_template_meta(array $meta): array
{
    $is_template = dce_flag_is_on($meta[DCE_TEMPLATE_FIELDS['is_elementor_template']] ?? '');

    return [
        'renders' => $is_template ? 'elementor_template' : 'html',
        'is_elementor_template' => $is_template,
        'elementor_template_id' => (int) ($meta[DCE_TEMPLATE_FIELDS['elementor_template_id']] ?? 0),
        'format' => $meta[DCE_TEMPLATE_FIELDS['format']] ?? '',
        'orientation' => $meta[DCE_TEMPLATE_FIELDS['orientation']] ?? '',
    ];
}

/**
 * Whether one of the plugin's stored flags is on.
 *
 * They arrive from a checkbox, so upstream stores whatever the browser posted and
 * then reads it with plain PHP truthiness. This matches that for every value a
 * checkbox can produce, including `'0'`, which is non-empty and still off.
 */
function dce_flag_is_on(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return is_scalar($value) && (string) $value !== '' && (string) $value !== '0';
}

/**
 * Cut a stored field to a byte budget, marking it when cut so a caller never
 * mistakes a truncated template for a short one.
 *
 * @return array{value: string, truncated: bool, total_bytes: int}
 */
function dce_truncate_field(string $value, ?int $max_bytes): array
{
    $total = strlen($value);

    // Null is "no cap"; zero is "the size only", which is a different request.
    if ($max_bytes === null || $total <= $max_bytes) {
        return ['value' => $value, 'truncated' => false, 'total_bytes' => $total];
    }

    if ($max_bytes <= 0) {
        return ['value' => '', 'truncated' => true, 'total_bytes' => $total];
    }

    // Cut on a character boundary. A byte-exact cut through a multi-byte character
    // returns a prefix that is not valid UTF-8, and a caller encoding it with plain
    // json_encode gets false rather than a short string. When the budget is smaller
    // than the first character the honest answer is nothing at all.
    return [
        'value' => mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8'),
        'truncated' => true,
        'total_bytes' => $total,
    ];
}

/**
 * Merge a partial change onto the stored array and write all seven keys back.
 *
 * The two halves are separate functions so that a caller which also writes
 * something else can find out whether this write would be refused *before* it
 * touches anything: `dce_merged_template_meta()` decides, and only
 * `dce_persist_template_meta()` writes.
 *
 * @param array<string, string> $changes keyed by raw sub-key
 * @return array<string, string>|WP_Error the array as written, or an error when the
 *                                        result would carry no page setup
 */
function dce_write_template_meta(int $post_id, array $changes): array|WP_Error
{
    $before = dce_read_template_meta($post_id);

    $merged = dce_merged_template_meta($post_id, $changes);
    if ($merged instanceof WP_Error) {
        return $merged;
    }

    return dce_persist_template_meta($post_id, $merged, $before);
}

/**
 * What the stored array would become, or the reason it may not become that.
 *
 * Decides and returns; it writes nothing. The seven keys share one meta value,
 * and `update_post_meta()` replaces a value whole, so writing a subset would drop
 * the keys the caller did not mention — the markup of a template being edited
 * only to change its page format, for instance.
 *
 * @param array<string, string> $changes keyed by raw sub-key
 * @return array<string, mixed>|WP_Error the array to write, or the refusal
 */
function dce_merged_template_meta(int $post_id, array $changes): array|WP_Error
{
    // Merge onto what is actually stored, not onto the seven keys this file knows
    // about: a sub-key a later plugin release adds would otherwise be dropped by
    // every edit, silently and with nothing to show for it.
    // Read the base from the database, not from the object cache. Without a
    // persistent cache each process keeps its own copy, and `wp_cache_delete()` in
    // the process that wrote cannot reach it — so a merge started from that copy
    // reverts whatever anyone else stored, while reporting only the field it was
    // asked to change. The template lock its callers hold serialises writers; this
    // is what makes them agree on what they are writing onto.
    wp_cache_delete($post_id, group: 'post_meta');

    /** @var mixed $stored */
    $stored = get_post_meta($post_id, DCE_TEMPLATE_META_KEY, single: true);
    /** @var array<string, mixed> $merged */
    $merged = is_array($stored) ? $stored : [];

    foreach (DCE_TEMPLATE_FIELDS as $field) {
        if (array_key_exists($field, $merged)) {
            continue;
        }
        $merged[$field] = '';
    }

    foreach ($changes as $field => $value) {
        if (!array_key_exists($field, DCE_TEMPLATE_FIELDS_BY_KEY)) {
            continue;
        }
        $merged[$field] = $value;
    }

    // Never persist an empty page setup. The renderer reads both sub-keys with no
    // fallback of its own and joins them as "<format>-<orientation>", so an empty
    // half reaches mPDF as "-P" and throws. The plugin defaults them only where it
    // draws its settings box, and inventing the same defaults here would be
    // guessing on the caller's behalf: a template arriving without a page setup is
    // asked for one instead.
    $missing = [];
    foreach (['format', 'orientation'] as $part) {
        if ($merged[DCE_TEMPLATE_FIELDS[$part]] !== '') {
            continue;
        }
        $missing[] = $part;
    }
    if ($missing !== []) {
        return dce_error(
            'page_setup_missing',
            sprintf(
                'This template has no %s stored, and nothing can render it until it has one. Pass %s in the same call.',
                implode(' and no ', $missing),
                implode(' and ', $missing),
            ),
            ['missing' => $missing],
        );
    }

    return $merged;
}

/**
 * Write an already-merged, already-validated array back, and answer with what is
 * now stored — or an error, when nothing was stored at all. This is the only
 * place in the specialization that writes the meta.
 *
 * The write is checked by reading the row back rather than by its return value.
 * `update_post_meta()` answers `false` both for a write the database refused and
 * for a value that was already exactly what was asked for, and nothing in the
 * return distinguishes the two.
 *
 * What the read-back can find is three things, and they are three different
 * answers rather than two:
 *
 *   - the row holds what was asked for — the write landed;
 *   - the row holds something else — a sanitiser rewrote the value on its way in,
 *     so the write also landed, and the caller is answered with what is really
 *     stored rather than with what was requested;
 *   - the row still holds exactly what it held before — nothing landed, and this
 *     is the only case reported as a failure, which is what lets that error say
 *     that nothing was changed.
 *
 * @param array<string, mixed> $merged as returned by `dce_merged_template_meta()`
 * @param array<string, string> $before the managed fields as they were before this write
 * @return array<string, string>|WP_Error
 */
function dce_persist_template_meta(int $post_id, array $merged, array $before): array|WP_Error
{
    $wanted = dce_managed_template_fields($merged);

    update_post_meta($post_id, DCE_TEMPLATE_META_KEY, wp_slash($merged));

    wp_cache_delete($post_id, group: 'post_meta');
    $stored = dce_read_template_meta($post_id);

    // Compared over the seven sub-keys this surface manages, in their order, and
    // not over the whole array: a sub-key a later plugin release adds is written
    // through untouched but is also whatever that release's own sanitiser makes
    // of it, and a comparison that insisted on those would answer about fields
    // this surface does not speak for.
    if ($stored !== $wanted && $stored === $before) {
        return dce_error('meta_write_failed', message: sprintf(
            'The configuration of HTML Template %d could not be stored: the row still holds what it held before, and nothing was changed.',
            $post_id,
        ));
    }

    return $stored;
}

/**
 * Put a template's stored configuration back exactly as it was, and say whether
 * that worked.
 *
 * Takes the meta as `get_post_meta()` with `single: false` hands it over — an
 * empty list for "there was no row", a one-element list for "there was, and this
 * was in it" — because those are the two things that have to be told apart and
 * a single read cannot: an absent row and a row holding an empty string come
 * back identically. Restoring the raw value rather than the seven normalised
 * sub-keys matters for the same reason the write merges: a sub-key a later
 * plugin release added, or a value that was never the array this surface expects,
 * is what was there, and putting back something tidier is not an undo.
 *
 * @param list<mixed> $before
 */
function dce_restore_template_meta(int $post_id, array $before): bool
{
    if ($before === []) {
        delete_post_meta($post_id, DCE_TEMPLATE_META_KEY);
        wp_cache_delete($post_id, group: 'post_meta');

        return get_post_meta($post_id, DCE_TEMPLATE_META_KEY, single: false) === [];
    }

    /** @var array<string, mixed>|string|null $value */
    $value = is_array($before[0]) || is_string($before[0]) ? $before[0] : null;
    /** @var mixed $raw */
    $raw = $before[0];

    update_post_meta($post_id, DCE_TEMPLATE_META_KEY, $value === null ? $raw : wp_slash($value));
    wp_cache_delete($post_id, group: 'post_meta');

    /** @var list<mixed> $now */
    $now = get_post_meta($post_id, DCE_TEMPLATE_META_KEY, single: false);
    if (count($now) !== 1) {
        return false;
    }

    /** @var mixed $restored */
    $restored = $now[0];

    // Compared whole, unknown sub-keys included. The write is checked over the
    // seven this surface manages because those are the ones it is answering
    // about; an undo is answering a different question — is the row as it was —
    // and a sub-key a later plugin release added is part of how it was. Ignoring
    // those would let a sanitiser leave one of them altered while this reported a
    // clean undo, and the caller would be handed the ordinary refusal as though
    // nothing had happened to it.
    return dce_same_shape($restored, $raw);
}

/**
 * Whether two values are the same all the way down, types included.
 *
 * `==` alone would call `'0'` and `0` the same thing, and a sanitiser that
 * retypes a value has changed what is stored even when the two still compare
 * loosely equal. `===` alone would call two arrays different for holding their
 * keys in a different order, which is not a change to what is stored. This is
 * both: same keys, and each value identical to its counterpart.
 */
function dce_same_shape(mixed $a, mixed $b): bool
{
    if (is_array($a) !== is_array($b)) {
        return false;
    }

    if (!is_array($a) || !is_array($b)) {
        return $a === $b;
    }

    if (count($a) !== count($b)) {
        return false;
    }

    foreach (array_keys($a) as $key) {
        if (!array_key_exists($key, $b) || !dce_same_shape($a[$key], $b[$key])) {
            return false;
        }
    }

    return true;
}

/**
 * The seven sub-keys this surface answers for, normalised to strings and in their
 * canonical order, read out of an array already in memory. The counterpart of
 * `dce_read_template_meta()`, which does the same to what the database holds, so
 * that a write can be compared with what was asked for without the two sides
 * normalising differently.
 *
 * @param array<string, mixed> $meta
 * @return array<string, string>
 */
function dce_managed_template_fields(array $meta): array
{
    $out = [];
    foreach (DCE_TEMPLATE_FIELDS as $field) {
        /** @var mixed $value */
        $value = $meta[$field] ?? '';
        $out[$field] = is_scalar($value) ? (string) $value : '';
    }

    return $out;
}

/**
 * Validate the page format, returning the value to store or an error.
 *
 * Only the two things the renderer itself cannot survive are refused: an empty
 * value, and one containing a dash. Which format names exist is mPDF's question,
 * asked by name at render time, and answered there with the name that failed.
 *
 * @return string|WP_Error
 */
function dce_validate_format(string $format): string|WP_Error
{
    $format = trim($format);

    if ($format === '') {
        return dce_error('invalid_format', message: 'A page format is required and cannot be empty.');
    }

    // The renderer joins the pair as "<format>-<orientation>" and splits it back
    // on the dash, so a format carrying one of its own would be read as a
    // different format and an orientation nobody asked for.
    if (str_contains($format, '-')) {
        return dce_error('invalid_format', sprintf(
            '"%s" cannot be a page format: the renderer reads a dash as the start of the orientation.',
            $format,
        ));
    }

    // Which names exist is mPDF's to answer, and it answers by name rather than
    // by list: an unknown one comes back as "Unknown page format X" at render
    // time. A list repeated here would be narrower than the renderer, and would
    // refuse a size the plugin's own screen may start offering.
    return $format;
}

/**
 * Validate the orientation the same way, normalising to the single letter the
 * renderer captures. It reads the character after the dash and accepts P or L,
 * spelled either as the letter or as the whole word, in any case.
 *
 * @return string|WP_Error
 */
function dce_validate_orientation(string $orientation): string|WP_Error
{
    if ($orientation === '') {
        return dce_error('invalid_orientation', message: 'A page orientation is required and cannot be empty.');
    }

    $normalised = match (strtolower($orientation)) {
        'p', 'portrait' => 'P',
        'l', 'landscape' => 'L',
        default => null,
    };

    if ($normalised !== null) {
        return $normalised;
    }

    return dce_error(
        'invalid_orientation',
        sprintf('"%s" is not a page orientation. The renderer reads P or L, portrait or landscape.', $orientation),
        ['accepted' => ['P', 'L', 'portrait', 'landscape']],
    );
}

/**
 * The pair of stored fields that decide how a template renders, for a given
 * Elementor template id. Zero means "renders its own markup", which is expressed
 * by clearing both rather than by any positive value.
 *
 * @return array<string, string>
 */
function dce_render_mode_fields(int $elementor_template_id): array
{
    $on = $elementor_template_id > 0;

    return [
        DCE_TEMPLATE_FIELDS['is_elementor_template'] => $on ? 'yes' : '',
        DCE_TEMPLATE_FIELDS['elementor_template_id'] => $on ? (string) $elementor_template_id : '',
    ];
}

/**
 * Refuse an Elementor template id that points at nothing, so a template is never
 * left rendering a post that does not exist. Zero is allowed and means the
 * template renders its own markup instead.
 */
function dce_elementor_target_error(int $elementor_template_id, int $self_id = 0): ?WP_Error
{
    if ($elementor_template_id <= 0) {
        return null;
    }

    if ($self_id > 0 && $elementor_template_id === $self_id) {
        return dce_error(
            'elementor_template_not_found',
            message: sprintf('HTML Template %d cannot render itself.', $self_id),
            status: 400,
        );
    }

    /** @var WP_Post|array<mixed>|null $target */
    $target = get_post($elementor_template_id);
    if (!$target instanceof WP_Post) {
        return dce_error('elementor_template_not_found', message: sprintf(
            'No post with id %d exists, so it cannot be rendered.',
            $elementor_template_id,
        ));
    }

    // A trashed target renders as nothing and throws inside the plugin, so it is
    // refused here rather than stored and discovered at generation time.
    if (in_array($target->post_status, ['trash', 'auto-draft'], strict: true)) {
        return dce_error(
            'elementor_template_not_found',
            message: sprintf('Post %d is %s, so it cannot be rendered.', $elementor_template_id, $target->post_status),
            data: ['post_status' => $target->post_status],
            status: 400,
        );
    }

    return null;
}

/**
 * Whether the post an Elementor reference points at is still renderable.
 *
 * Existence alone is not enough: the plugin fetches the target's builder content,
 * which comes back empty for anything trashed, and reports a template it cannot
 * fetch. This is the field an agent reads to explain an empty PDF, so it answers
 * on renderability rather than on the row still being in the table.
 */
function dce_elementor_target_is_renderable(int $elementor_template_id): bool
{
    if ($elementor_template_id <= 0) {
        return false;
    }

    /** @var WP_Post|array<mixed>|null $target */
    $target = get_post($elementor_template_id);

    return $target instanceof WP_Post && !in_array($target->post_status, ['trash', 'auto-draft'], strict: true);
}

/**
 * Serialise the read-modify-write on a template's meta.
 *
 * The seven sub-keys share one meta value, so two edits that overlap in time each
 * merge their own delta onto the copy they read and the later write silently drops
 * the other's change. Scoped by blog id so two sites on one database do not block
 * each other.
 *
 * @template T
 * @param callable(): (T|WP_Error) $callback
 * @return T|WP_Error
 */
function dce_with_template_lock(int $post_id, callable $callback, int $timeout = 5): mixed
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $lock = dce_template_lock_name($post_id);

    // @mago-expect analysis:possibly-invalid-argument
    $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, $timeout));
    if ((string) $got !== '1') {
        return dce_error('lock_conflict', message: sprintf(
            'Another write to HTML Template %d is in progress; nothing was changed.',
            $post_id,
        ));
    }

    try {
        // @mago-expect lint:inline-variable-return
        $result = $callback();
        return $result;
    } finally {
        // @mago-expect analysis:possibly-invalid-argument
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

/**
 * The name of the advisory lock that serialises writes to one template. Scoped by
 * blog id so two sites on one database do not block each other, and named here
 * rather than inline so anything that needs to address the same lock — a test
 * proving a conflict is refused, for one — asks for it instead of rebuilding the
 * recipe and drifting from it.
 */
function dce_template_lock_name(int $post_id): string
{
    $blog = (int) get_current_blog_id();

    return 'novamira-dce:' . sha1("blog={$blog}|template={$post_id}");
}

/**
 * Whether a value is a plain whole number, and not one of the exotic numeric
 * forms the schema's `minimum` accepts and a cast then flattens to zero.
 */
function dce_is_plain_id(mixed $value): bool
{
    if (is_int($value)) {
        return $value >= 0;
    }

    return is_string($value) && preg_match('/^\d+$/', $value) === 1;
}

/**
 * Refuse the one input combination that has no single correct outcome: non-empty
 * markup and an Elementor template reference together. An empty `code` beside a
 * reference is not that combination and is accepted.
 */
function dce_ambiguous_mode_error(): WP_Error
{
    return dce_error(
        'ambiguous_render_mode',
        message: 'A template renders either its own markup or an Elementor template, never both. Pass non-empty code or elementor_template_id, not both. An empty code alongside a reference is accepted and means the template keeps no markup of its own.',
    );
}

/**
 * Validate page format and orientation together, returning the two stored fields
 * or the first error. Keeps the two callbacks free of duplicated branching.
 *
 * @return array<string, string>|WP_Error
 */
function dce_page_setup_fields(string $format, string $orientation): array|WP_Error
{
    $validated_format = dce_validate_format($format);
    if ($validated_format instanceof WP_Error) {
        return $validated_format;
    }

    $validated_orientation = dce_validate_orientation($orientation);
    if ($validated_orientation instanceof WP_Error) {
        return $validated_orientation;
    }

    return [
        DCE_TEMPLATE_FIELDS['format'] => $validated_format,
        DCE_TEMPLATE_FIELDS['orientation'] => $validated_orientation,
    ];
}
