<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use ErrorException;
use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Write-pipeline helpers for the Voxel content CRUD abilities (Sprint 2).
 *
 * Mirrors Voxel's own submission controller: sanitize every provided field,
 * check_validity (enforces required unless a draft), collect per-field errors,
 * and only then persist through each field's own update(). The title field is
 * handled separately (its update() is a no-op; the value lives in post_title).
 * File/image uploads are refused (this surface has no multipart body) so their
 * update() never fatals mid-write. Sanitize/validate run under a warning-to-
 * exception guard so a malformed value (e.g. a partial location object) becomes
 * a clean field error instead of a leaked PHP warning.
 */

/**
 * Post statuses this surface may set (excludes Voxel-internal rejected/expired/unpublished).
 *
 * @return list<string>
 */
function vx_allowed_write_statuses(): array
{
    return ['draft', 'pending', 'publish', 'private'];
}

/** Field types backed by media uploads — only existing attachment ids are accepted. */
function vx_is_upload_field_type(string $type): bool
{
    return in_array($type, ['file', 'image', 'profile-avatar'], strict: true);
}

/**
 * Validate an upload field value: it must be omitted/empty or an array of
 * existing attachment ids. Rejects Voxel's "uploaded_file" sentinel and any
 * non-id item, which would otherwise pass validation and fatal in update().
 *
 * @param mixed $value
 */
function vx_upload_value_error(mixed $value): ?string
{
    if ($value === null || $value === '' || $value === []) {
        return null;
    }
    if (!is_array($value)) {
        return __('Provide an array of existing attachment ids.', domain: 'novamira-pro');
    }
    /** @var mixed $item */
    foreach ($value as $item) {
        if (is_int($item)) {
            continue;
        }
        if (is_string($item) && ctype_digit($item)) {
            continue;
        }
        return __('This ability cannot upload new files; provide existing attachment ids.', domain: 'novamira-pro');
    }
    return null;
}

/**
 * Sanitize + validate one field value under a guard that turns any PHP warning /
 * notice / deprecation into a throwable, so a malformed value never leaks a
 * warning. Returns the sanitized value or throws (caught by the caller).
 *
 * @param \Voxel\Post_Types\Fields\Base_Post_Field $field
 * @param mixed $raw
 * @return mixed
 */
function vx_field_sanitize_validate(
    object $field,
    mixed $raw,
    // @mago-expect lint:no-boolean-flag-parameter -- draft vs full validation
    bool $bypass_required,
): mixed {
    set_error_handler(
        static function (int $errno, string $errstr): bool {
            throw new ErrorException($errstr, 0, $errno);
        },
        E_WARNING | E_NOTICE | E_DEPRECATED,
    );
    try {
        /** @var mixed $value */
        $value = $field->sanitize($raw);
        $bypass_required ? $field->check_validity_bypass_required($value) : $field->check_validity($value);
        return $value;
    } finally {
        restore_error_handler();
    }
}

/**
 * Accept a plain date string where Voxel's date field wants an object.
 *
 * Date_Field::sanitize() reads `$value['date']`, so a bare "2026-09-18" — the
 * obvious thing to send, and the shape every other simple field uses — yields
 * null. Worse, the field then reports itself as *missing*, so the caller is told
 * "Start date is required" about a value it did supply, and no PHP warning is
 * raised to hint otherwise (`??` has isset() semantics, and isset() on a string
 * offset named 'date' is simply false).
 *
 * The `{date, time}` shape is the Voxel front-end form's internal contract and is
 * documented nowhere the caller can reach, so this normalizes rather than
 * rejects. The object form keeps working unchanged.
 *
 * @param \Voxel\Post_Types\Fields\Base_Post_Field $field
 */
function vx_normalize_field_input(object $field, mixed $raw): mixed
{
    if ($field->get_type() !== 'date' || !is_string($raw)) {
        return $raw;
    }
    $trimmed = trim($raw);
    return $trimmed === '' ? $raw : ['date' => $trimmed];
}

/**
 * Field types whose sanitize() casts every input to a fixed shape, so "provided
 * something, stored nothing" is normal for them rather than a loss.
 *
 * `switcher` is the only one: its sanitize() is `return !! $value`, so switching
 * a toggle off by sending `0` gives a raw that Voxel counts as non-empty (its
 * is_empty() whitelists 0, '0' and 0.0) and a sanitized `false` that it counts as
 * empty. Flagging that would report a correct write as a silent loss.
 *
 * @return list<string>
 */
function vx_total_cast_field_types(): array
{
    return ['switcher'];
}

/**
 * Whether a value the caller provided was silently discarded.
 *
 * Voxel's own submission pipeline treats "the field rejected this" and "the field
 * could not parse this" very differently: the first raises, the second just
 * returns an empty value and check_validity() then skips validation entirely
 * (base-field.php:70-74). The write succeeds and nothing says the value is gone.
 *
 * Emptiness is measured with the field's own is_empty(), never a bare empty():
 * `0` is a legitimate number and `'0'` a legitimate string, and empty() would
 * report both as lost. Raw strings are trimmed first, or clearing a field with a
 * single space would look like a discarded value.
 *
 * Known limit: this compares whole values and list lengths, so it does not see a
 * value that survives with its *contents* stripped — a repeater row whose unknown
 * sub-fields were dropped still arrives as one row. Read the post back when a
 * value matters.
 *
 * @param \Voxel\Post_Types\Fields\Base_Post_Field $field
 */
function vx_field_value_dropped(object $field, mixed $raw, mixed $sanitized): bool
{
    if (in_array($field->get_type(), vx_total_cast_field_types(), strict: true)) {
        return false;
    }
    // Layout-only fields (ui-step, ui-heading, ui-html, ui-image) share a trait
    // whose sanitize() is an empty no-op by design, so anything sent to one comes
    // back empty and would be reported as a loss forever. Asking the field
    // rather than listing the four types keeps this true if Voxel adds another.
    if (method_exists($field, 'is_ui') && $field->is_ui() === true) {
        return false;
    }
    if (is_string($raw)) {
        $raw = trim($raw);
    }
    // Nothing was offered, so nothing was lost — this is how a field is cleared.
    if ($field->is_empty($raw)) {
        return false;
    }
    if ($field->is_empty($sanitized)) {
        return true;
    }
    // A list that came back shorter lost entries: a relation beyond a has_one
    // limit, a term that did not resolve.
    //
    // Only on a list, though. Cardinality means nothing on an associative payload:
    // a location sanitizes to exactly four fixed keys and a product to its schema
    // export, so sending one extra key would look like a loss on a value that was
    // stored perfectly. The gate is on the raw side alone — the key count can grow
    // as well as shrink, and some sanitizers (taxonomy) return a filtered array
    // whose keys are no longer sequential, which is a real truncation worth
    // reporting.
    if (!is_array($raw) || !is_array($sanitized) || !vx_is_list($raw)) {
        return false;
    }
    return count($sanitized) < count($raw);
}

/**
 * Whether an array is a list (sequential integer keys from 0).
 *
 * array_is_list() would say this in one call, but it landed in PHP 8.1 and this
 * plugin supports 8.0.
 */
function vx_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }
    return array_keys($value) === range(0, count($value) - 1);
}

/**
 * Sanitize + validate field values without writing. The title field is skipped
 * (handled via post_title). Collects per-field error messages, and the keys whose
 * value was accepted but silently discarded.
 *
 * @param \Voxel\Post_Type $post_type
 * @param array<string, mixed> $values raw field values keyed by field key
 * @param array{all: bool, bypass_required: bool, post: \Voxel\Post|null} $ctx write context:
 *        all = process every field (create) vs only provided (edit); bypass_required =
 *        skip required enforcement (draft); post = bind fields during sanitize (edit)
 * @return array{sanitized: array<string, mixed>, errors: array<string, string>, dropped: list<string>}
 */
function vx_prepare_field_values(object $post_type, array $values, array $ctx): array
{
    $all = $ctx['all'];
    $bypass_required = $ctx['bypass_required'];
    $post = $ctx['post'];
    $sanitized = [];
    $errors = [];
    $dropped = [];

    foreach ($post_type->get_fields() as $key => $field) {
        // Title is set through post_title; its field update() is a no-op.
        if ($field->get_type() === 'title') {
            continue;
        }
        if (!array_key_exists($key, $values)) {
            // Never sanitize an unprovided field — taxonomy/file/repeater fatal on
            // null. Enforce "required" directly, only on create for a non-draft.
            if ($all && !$bypass_required && $field->is_required()) {
                $errors[$key] = sprintf(
                    /* translators: %s field label. */
                    __('%s is required.', domain: 'novamira-pro'),
                    $field->get_label(),
                );
            }
            continue;
        }
        if ($post !== null) {
            $field->set_post($post);
        }
        /** @var mixed $raw */
        $raw = vx_normalize_field_input($field, $values[$key]);
        if (vx_is_upload_field_type($field->get_type())) {
            $upload_error = vx_upload_value_error($raw);
            if ($upload_error !== null) {
                $errors[$key] = $upload_error;
                continue;
            }
        }
        try {
            $sanitized[$key] = vx_field_sanitize_validate($field, $raw, $bypass_required);
            if (vx_field_value_dropped($field, $raw, $sanitized[$key])) {
                $dropped[] = $key;
            }
        } catch (Throwable $e) {
            $errors[$key] = $e->getMessage();
        }
    }

    return ['sanitized' => $sanitized, 'errors' => $errors, 'dropped' => $dropped];
}

/**
 * Persist already-sanitized field values via each field's update(). Skips the
 * title field. May throw if a field's update() fatals — callers wrap this and
 * roll back.
 *
 * @param \Voxel\Post $post
 * @param \Voxel\Post_Type $post_type
 * @param array<string, mixed> $sanitized field key => sanitized value
 * @return list<string> the field keys actually written, in field order
 */
function vx_apply_field_writes(object $post, object $post_type, array $sanitized): array
{
    $written = [];
    foreach ($post_type->get_fields() as $key => $field) {
        if ($field->get_type() === 'title' || !array_key_exists($key, $sanitized)) {
            continue;
        }
        $field->set_post($post);
        $field->update($sanitized[$key]);
        $written[] = $key;
    }
    return $written;
}

/**
 * Bring the search index in sync with a post's current status/values. Idempotent.
 *
 * @param \Voxel\Post $post
 */
function vx_reindex_post(object $post): void
{
    $post->should_index() ? $post->index() : $post->unindex();
}

/**
 * WP_Error carrying per-field validation messages (HTTP 400).
 *
 * @param array<string, string> $errors field key => message
 */
function vx_field_validation_error(array $errors): WP_Error
{
    /** @var list<string> $summary */
    $summary = [];
    foreach ($errors as $key => $message) {
        $summary[] = $key . ': ' . $message;
    }
    return new WP_Error(
        'voxel_field_validation',
        sprintf(
            /* translators: %s semicolon-separated "field: message" list. */
            __('One or more field values are invalid: %s', domain: 'novamira-pro'),
            implode('; ', $summary),
        ),
        ['status' => 400, 'fields' => $errors],
    );
}

/**
 * Normalize a caller status against the write allowlist, or a WP_Error.
 *
 * @return string|WP_Error
 */
function vx_normalize_write_status(mixed $raw, string $default): string|WP_Error
{
    if ($raw === null) {
        return $default;
    }
    if (!is_string($raw) || !in_array($raw, vx_allowed_write_statuses(), strict: true)) {
        return vx_invalid(sprintf(
            /* translators: %s comma-separated status list. */
            __('status must be one of: %s', domain: 'novamira-pro'),
            implode(', ', vx_allowed_write_statuses()),
        ));
    }
    return $raw;
}

/**
 * Run the field-value pipeline plus required-title enforcement, returning the
 * sanitized values and merged per-field errors.
 *
 * @param \Voxel\Post_Type $post_type
 * @param array<string, mixed> $values
 * @param array{provided: bool, value: string} $title
 * @param array{all: bool, bypass_required: bool, post: \Voxel\Post|null} $ctx
 * @return array{sanitized: array<string, mixed>, errors: array<string, string>, dropped: list<string>}
 */
function vx_prepare_write(object $post_type, array $values, array $title, array $ctx): array
{
    $prep = vx_prepare_field_values($post_type, $values, $ctx);
    $errors = $prep['errors'];
    $enforce_title = $ctx['bypass_required'] ? false : $ctx['all'] || $title['provided'];
    $title_error = vx_title_required_error($post_type, $title, $enforce_title);
    if ($title_error !== null) {
        $errors['title'] = $title_error;
    }
    return ['sanitized' => $prep['sanitized'], 'errors' => $errors, 'dropped' => $prep['dropped']];
}

/**
 * Add content/slug from the ability input to the base insert args and create the
 * WP post row. Maps WordPress' empty-post guard to a 400.
 *
 * @param array{post_type: string, post_status: string, post_title: string, post_author: int} $base
 * @param array<string, mixed> $input raw ability input (for content and slug)
 * @return int|WP_Error
 */
function vx_insert_post_row(array $base, array $input): int|WP_Error
{
    // Seed post_content so WP's empty-post guard passes; the description field
    // re-sanitizes and rewrites it during the field-write phase.
    $content = vx_string_input($input, primary: 'content', alias: 'post_content');
    if ($content !== '') {
        $base['post_content'] = $content;
    }
    $slug = vx_string_input($input, primary: 'slug', alias: 'post_name');
    if ($slug !== '') {
        $base['post_name'] = sanitize_title($slug);
    }
    $post_id = wp_insert_post($base, wp_error: true);
    if ($post_id instanceof WP_Error) {
        if ($post_id->get_error_code() === 'empty_content') {
            return vx_invalid(__('Provide at least a title or content for the new post.', domain: 'novamira-pro'));
        }
        return vx_operation_failed($post_id->get_error_message());
    }
    return (int) $post_id;
}

/**
 * Reload the post (busting caches), reindex it, and build the write-result
 * envelope. Shared tail for create and edit.
 *
 * @param list<string> $written
 * @param list<string> $dropped
 * @return array<string, mixed>|WP_Error
 */
function vx_finalize_write(int $post_id, string $post_type, array $written, array $dropped): array|WP_Error
{
    $post = \Voxel\Post::force_get($post_id);
    if (!is_object($post)) {
        return vx_operation_failed(__('The post was saved but could not be reloaded.', domain: 'novamira-pro'));
    }
    vx_reindex_post($post);
    return vx_write_result($post_id, $post_type, $written, $dropped);
}

/**
 * The custom field-value map for the pipeline: the `fields` object plus content
 * routed to the "description" field. Title is intentionally excluded (it is set
 * via post_title). A `title` inside `fields` is ignored here and promoted to the
 * post title by vx_resolve_title().
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function vx_collect_write_values(array $input): array
{
    $values = [];
    if (isset($input['fields']) && is_array($input['fields'])) {
        /** @var array<string, mixed> $fields */
        $fields = $input['fields'];
        $values = $fields;
    }
    unset($values['title']); // title never flows through the field pipeline
    if (array_key_exists('content', $input) || array_key_exists('post_content', $input)) {
        $values['description'] = vx_string_input($input, primary: 'content', alias: 'post_content');
    }
    return $values;
}

/**
 * Resolve the effective post title from the top-level param, its WP alias, or a
 * `fields.title` value (in that order). `provided` is false only when none of
 * those keys is present.
 *
 * @param array<string, mixed> $input
 * @return array{provided: bool, value: string}
 */
function vx_resolve_title(array $input): array
{
    $provided = array_key_exists('title', $input) || array_key_exists('post_title', $input);
    /** @var mixed $from_fields */
    $from_fields = isset($input['fields']) && is_array($input['fields']) ? $input['fields']['title'] ?? null : null;
    if (!$provided && $from_fields !== null) {
        $provided = true;
    }
    $value = vx_string_input($input, primary: 'title', alias: 'post_title');
    if ($value === '' && is_string($from_fields)) {
        $value = $from_fields;
    }
    return ['provided' => $provided, 'value' => $value];
}

/**
 * Required-title enforcement, returned as a field error or null. The title field
 * is validated here because its own update() is a no-op and it is skipped by the
 * field pipeline.
 *
 * @param \Voxel\Post_Type $post_type
 * @param array{provided: bool, value: string} $title
 */
function vx_title_required_error(
    object $post_type,
    array $title,
    // @mago-expect lint:no-boolean-flag-parameter -- whether required-title enforcement applies
    bool $enforce,
): ?string {
    if (!$enforce) {
        return null;
    }
    foreach ($post_type->get_fields() as $field) {
        if ($field->get_type() !== 'title') {
            continue;
        }
        if (!$field->is_required()) {
            return null;
        }
        if ($title['value'] === '') {
            return sprintf(
                /* translators: %s field label. */
                __('%s is required.', domain: 'novamira-pro'),
                $field->get_label(),
            );
        }
        return null;
    }
    return null;
}

/**
 * Read a string input by primary key or WP-native alias.
 *
 * @param array<string, mixed> $input
 */
function vx_string_input(array $input, string $primary, string $alias): string
{
    /** @var mixed $value */
    $value = $input[$primary] ?? $input[$alias] ?? '';
    return is_string($value) ? $value : '';
}

/**
 * Resolve the author id from input (must exist) or the current user.
 *
 * @param array<string, mixed> $input
 * @return int|WP_Error
 */
function vx_resolve_author(array $input): int|WP_Error
{
    /** @var mixed $raw */
    $raw = $input['author'] ?? $input['post_author'] ?? null;
    if ($raw === null) {
        return get_current_user_id();
    }
    $author = vx_coerce_int($raw);
    if ($author === null || $author <= 0 || get_userdata($author) === false) {
        return vx_invalid(sprintf(
            /* translators: %s the author id. */
            __('author %s is not an existing user.', domain: 'novamira-pro'),
            is_scalar($raw) ? (string) $raw : gettype($raw),
        ));
    }
    return $author;
}

/**
 * Apply the WP post-column changes (title / status / slug) a write requested.
 * Content is written by the description field. Returns a WP_Error on failure.
 *
 * @param array<string, mixed> $input
 * @param array{provided: bool, value: string} $title
 * @param string|null $status the new status, or null to leave it unchanged
 */
function vx_apply_post_columns(array $input, int $post_id, array $title, ?string $status): ?WP_Error
{
    $postarr = ['ID' => $post_id];
    if ($title['provided']) {
        $postarr['post_title'] = $title['value'];
    }
    if ($status !== null) {
        $postarr['post_status'] = $status;
    }
    $slug = vx_string_input($input, primary: 'slug', alias: 'post_name');
    if ($slug !== '') {
        $postarr['post_name'] = sanitize_title($slug);
    }
    if (count($postarr) <= 1) {
        return null;
    }
    $updated = wp_update_post($postarr, wp_error: true);
    return $updated instanceof WP_Error ? vx_operation_failed($updated->get_error_message()) : null;
}

/**
 * Assemble the standard write-result envelope shared by create/edit. Uses the
 * raw post_title (not get_the_title, which decorates private/protected posts).
 *
 * @param list<string> $written
 * @param list<string> $dropped
 * @return array{post_id: int, post_type: string, status: string, title: string, slug: string, permalink: string, indexed: bool, fields_written: list<string>, fields_dropped: list<string>}
 */
function vx_write_result(int $post_id, string $post_type, array $written, array $dropped): array
{
    $permalink = get_permalink($post_id);
    /** @var mixed $slug */
    $slug = get_post_field('post_name', $post_id);
    /** @var mixed $title */
    $title = get_post_field('post_title', $post_id);

    return [
        // Named post_id, not id: every voxel-* ability that takes a post takes
        // a post_id input, so a write result chains without a key rename.
        'post_id' => $post_id,
        'post_type' => $post_type,
        'status' => (string) get_post_status($post_id),
        'title' => is_string($title) ? $title : '',
        'slug' => is_string($slug) ? $slug : '',
        'permalink' => is_string($permalink) ? $permalink : '',
        'indexed' => vx_post_is_indexed($post_id, $post_type),
        'fields_written' => $written,
        'fields_dropped' => $dropped,
    ];
}

/**
 * Whether the post is actually present in its post type's search index.
 *
 * \Voxel\Post::index() returns nothing and swallows database failures, so the
 * intent to index says nothing about the result: a post type with no index table
 * fails on every call and stays invisible to Voxel's front-end search. Reading
 * the row back is the only honest signal. A missing table simply means no row.
 */
function vx_post_is_indexed(int $post_id, string $post_type): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $safe = sanitize_key($post_type);
    if ($safe === '') {
        return false;
    }
    $table = $wpdb->prefix . 'voxel_index_' . $safe;
    if (!vx_table_exists($table)) {
        return false;
    }
    // wpdb::prepare() only accepts a literal SQL string, and the table name is
    // per-post-type. Both interpolations are injection-safe: the name comes from
    // sanitize_key() and the id is an int — the same approach as
    // vx_drop_index_tables().
    $sql = sprintf('SELECT post_id FROM `%s` WHERE post_id = %d LIMIT 1', $table, $post_id);
    return $wpdb->get_var($sql) !== null;
}
