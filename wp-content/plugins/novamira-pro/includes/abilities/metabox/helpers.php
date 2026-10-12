<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * PHP 8.0-compatible equivalent of array_is_list().
 *
 * @param array<array-key, mixed> $value
 */
function mb_array_is_list(array $value): bool
{
    $expected = 0;
    foreach ($value as $key => $_) {
        if ($key !== $expected) {
            return false;
        }
        $expected++;
    }
    return true;
}

/**
 * Runtime snapshot of the Meta Box installation. Cached per request.
 *
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     min_satisfied: bool,
 *     builder_active: bool,
 *     cpt_active: bool,
 *     settings_page_active: bool,
 *     relationships_active: bool,
 *     term_meta_active: bool,
 *     comment_meta_active: bool,
 *     user_meta_active: bool,
 *     group_active: bool,
 *     custom_table_active: bool,
 *     field_group_counts: array{php: int, db: int},
 * }
 */
function mb_check_setup(): array
{
    /** @var null|array{
     *     active: bool,
     *     version: string|null,
     *     min_satisfied: bool,
     *     builder_active: bool,
     *     cpt_active: bool,
     *     settings_page_active: bool,
     *     relationships_active: bool,
     *     term_meta_active: bool,
     *     comment_meta_active: bool,
     *     user_meta_active: bool,
     *     group_active: bool,
     *     custom_table_active: bool,
     *     field_group_counts: array{php: int, db: int},
     * } $cache
     */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $active = defined('RWMB_VER') && class_exists('RW_Meta_Box');
    $version = defined('RWMB_VER') ? (string) constant('RWMB_VER') : null;
    $min_satisfied = mb_min_runtime_available();

    [$php_count, $db_count] = mb_count_field_groups();

    return $cache = [
        'active' => $active,
        'version' => $version,
        'min_satisfied' => $min_satisfied,
        'builder_active' => mb_builder_available(),
        'cpt_active' => mb_cpt_available(),
        'settings_page_active' => mb_settings_page_available(),
        'relationships_active' => mb_relationships_available(),
        'term_meta_active' => mb_term_meta_available(),
        'comment_meta_active' => mb_comment_meta_available(),
        'user_meta_active' => mb_user_meta_available(),
        'group_active' => mb_group_available(),
        'custom_table_active' => mb_custom_table_available(),
        'field_group_counts' => [
            'php' => $php_count,
            'db' => $db_count,
        ],
    ];
}

/**
 * Count Meta Box field groups split by source (php-registered vs db-stored).
 *
 * DB groups live in the `meta-box` CPT (post status `publish`).
 * PHP groups appear in the `rwmb_meta_boxes` filter output but are NOT in
 * the `meta-box` CPT.
 *
 * @return array{0: int, 1: int} php count, db count
 */
function mb_count_field_groups(): array
{
    if (!mb_min_runtime_available()) {
        return [0, 0];
    }

    $db_count = 0;
    if (mb_builder_available()) {
        /** @var mixed $rows_raw */
        $rows_raw = get_posts([
            'post_type' => 'meta-box',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);
        $db_count = is_array($rows_raw) ? count($rows_raw) : 0;
    }

    /** @var mixed $registered_raw */
    $registered_raw = apply_filters('rwmb_meta_boxes', []);
    /** @var array<int, mixed> $registered */
    $registered = is_array($registered_raw) ? $registered_raw : [];

    // Total minus db = php-only.
    $total = count($registered);
    $php_count = max(0, $total - $db_count);

    return [$php_count, $db_count];
}

/**
 * Standard runtime guard returned from every Meta Box ability callback's first line.
 */
function mb_runtime_guard(): ?WP_Error
{
    if (!defined('RWMB_VER') || !class_exists('RW_Meta_Box')) {
        return new WP_Error('metabox_not_active', __('Meta Box is not active.', domain: 'novamira-pro'));
    }
    if (!mb_min_runtime_available()) {
        return new WP_Error('metabox_version_unsupported', sprintf(
            /* translators: 1: installed Meta Box version, 2: minimum required version. */
            __('Meta Box %1$s is below the minimum supported version %2$s.', domain: 'novamira-pro'),
            (string) constant('RWMB_VER'),
            NOVAMIRA_PRO_METABOX_MIN_VERSION,
        ));
    }
    return null;
}

/**
 * Builder-specific guard (used by write-side field-group abilities).
 */
function mb_builder_guard(): ?WP_Error
{
    $base = mb_runtime_guard();
    if ($base !== null) {
        return $base;
    }
    if (!mb_builder_available()) {
        return new WP_Error('metabox_builder_not_active', __(
            'MB Builder is not active. Install MB Builder to create, edit, or delete field groups via the API. PHP-defined field groups remain readable via list-field-groups / get-field-group-schema.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * CPT-extension guard (used by post-type + taxonomy abilities).
 */
function mb_cpt_guard(): ?WP_Error
{
    $base = mb_runtime_guard();
    if ($base !== null) {
        return $base;
    }
    if (!mb_cpt_available()) {
        return new WP_Error('metabox_cpt_not_active', __(
            'MB Custom Post Type is not active. Install MB Custom Post Type to manage post types and custom taxonomies.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Settings-page guard.
 */
function mb_settings_page_guard(): ?WP_Error
{
    $base = mb_runtime_guard();
    if ($base !== null) {
        return $base;
    }
    if (!mb_settings_page_available()) {
        return new WP_Error('metabox_settings_page_not_active', __(
            'MB Settings Page is not active. Install MB Settings Page to manage settings pages.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Relationships guard.
 */
function mb_relationships_guard(): ?WP_Error
{
    $base = mb_runtime_guard();
    if ($base !== null) {
        return $base;
    }
    if (!mb_relationships_available()) {
        return new WP_Error('metabox_relationships_not_active', __(
            'MB Relationships is not active. Install MB Relationships to manage object associations.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Term-meta guard — required when a field group binds to taxonomies, or
 * when read-/write-values is called against a term target.
 */
function mb_term_meta_guard(): ?WP_Error
{
    $base = mb_runtime_guard();
    if ($base !== null) {
        return $base;
    }
    if (!mb_term_meta_available()) {
        return new WP_Error('metabox_term_meta_not_active', __(
            'MB Term Meta is not active. Attaching a field group to taxonomies (or reading/writing field values on a term object) requires this extension. Bundled in MB Core (paid) and in Meta Box AIO; not available standalone in any free Meta Box distribution.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Comment-meta guard — required for field groups bound to comments, or
 * for read-/write-values against a comment target.
 */
function mb_comment_meta_guard(): ?WP_Error
{
    $base = mb_runtime_guard();
    if ($base !== null) {
        return $base;
    }
    if (!mb_comment_meta_available()) {
        return new WP_Error('metabox_comment_meta_not_active', __(
            'MB Comment Meta is not active. Required for field groups bound to comments and for read-/write-values on a comment target. Bundled in MB Core (paid) and in Meta Box Lite (free); also available as a standalone paid extension on metabox.io.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * User-meta guard — required for field groups bound to users, or for
 * read-/write-values against a user target. MB User Meta is a standalone
 * paid extension; it is not bundled in MB Core or Meta Box Lite.
 */
function mb_user_meta_guard(): ?WP_Error
{
    $base = mb_runtime_guard();
    if ($base !== null) {
        return $base;
    }
    if (!mb_user_meta_available()) {
        return new WP_Error('metabox_user_meta_not_active', __(
            'MB User Meta is not active. Required for field groups bound to users and for read-/write-values on a user target. Standalone paid extension on metabox.io; not currently included in any free Meta Box distribution.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Group-field guard — required when a field definition uses `type: group`.
 */
function mb_group_guard(): ?WP_Error
{
    $base = mb_runtime_guard();
    if ($base !== null) {
        return $base;
    }
    if (!mb_group_available()) {
        return new WP_Error('metabox_group_not_active', __(
            'MB Group is not active. The `group` field type registers without the extension but its nested fields are dropped at runtime, so the saved data is incomplete. Bundled in MB Core (paid); also available as a standalone paid extension on metabox.io. Not currently included in any free Meta Box distribution.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Pick the per-target extension guard for the read/write-values dispatch.
 * Posts and settings targets have no extra extension requirement beyond the
 * core runtime; term/user/comment each require their own meta extension to
 * persist values through the Meta Box pipeline rather than the raw WP meta
 * table (which would skip per-field-group post-processing).
 */
function mb_target_extension_guard(string $object_type): ?WP_Error
{
    return match ($object_type) {
        'term' => mb_term_meta_guard(),
        'user' => mb_user_meta_guard(),
        'comment' => mb_comment_meta_guard(),
        default => null,
    };
}

/**
 * Pick the field-group extension guard. Returns the first failing guard
 * encountered when:
 *  - `settings.taxonomies[]` is non-empty (requires MB Term Meta);
 *  - `settings.settings_pages[]` is non-empty (requires MB Settings Page);
 *  - any field — including nested fields under a `group` — has `type: group`
 *    (requires MB Group).
 *
 * @param array<array-key, mixed> $settings
 * @param array<array-key, mixed> $fields
 */
function mb_field_group_extension_guard(array $settings, array $fields): ?WP_Error
{
    /** @var mixed $taxonomies */
    $taxonomies = $settings['taxonomies'] ?? null;
    if (is_array($taxonomies) && count($taxonomies) > 0) {
        $g = mb_term_meta_guard();
        if ($g !== null) {
            return $g;
        }
    }
    /** @var mixed $settings_pages */
    $settings_pages = $settings['settings_pages'] ?? null;
    if (is_array($settings_pages) && count($settings_pages) > 0) {
        $g = mb_settings_page_guard();
        if ($g !== null) {
            return $g;
        }
    }
    if (mb_field_group_has_group_field($fields)) {
        $g = mb_group_guard();
        if ($g !== null) {
            return $g;
        }
    }
    return null;
}

/**
 * Recursive check: does any field in the tree have `type: group`?
 *
 * @param array<array-key, mixed> $fields
 */
function mb_field_group_has_group_field(array $fields): bool
{
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        if (($field['type'] ?? null) === 'group') {
            return true;
        }
        if (isset($field['fields']) && is_array($field['fields']) && mb_field_group_has_group_field($field['fields'])) {
            return true;
        }
    }
    return false;
}

/**
 * Convert a structured target {type, id} into Meta Box's `$object_id` +
 * `$object_type` pair used by `rwmb_meta()` / `rwmb_set_meta()` / etc.
 *
 * @param array<string, mixed> $target
 * @return array{object_id: int|string, object_type: string}|WP_Error
 */
function mb_normalize_target(array $target): array|WP_Error
{
    /** @var mixed $type_raw */
    $type_raw = $target['type'] ?? null;
    /** @var mixed $id_raw */
    $id_raw = $target['id'] ?? null;

    if (!is_string($type_raw) || $type_raw === '') {
        return new WP_Error('metabox_target_invalid', __('target.type is required.', domain: 'novamira-pro'));
    }
    if ($id_raw === null || $id_raw === '') {
        return new WP_Error('metabox_target_invalid', __('target.id is required.', domain: 'novamira-pro'));
    }

    $type = strtolower($type_raw);
    return match ($type) {
        'post' => ['object_id' => (int) $id_raw, 'object_type' => 'post'],
        'term' => ['object_id' => (int) $id_raw, 'object_type' => 'term'],
        'user' => ['object_id' => (int) $id_raw, 'object_type' => 'user'],
        'comment' => ['object_id' => (int) $id_raw, 'object_type' => 'comment'],
        'settings' => ['object_id' => (string) $id_raw, 'object_type' => 'setting'],
        default => new WP_Error('metabox_target_invalid', sprintf(
            /* translators: %s: unknown target type. */
            __('Unknown target.type "%s". Use post|term|user|comment|settings.', domain: 'novamira-pro'),
            $type,
        )),
    };
}

/**
 * Resolve where a field group lives: 'db' (meta-box CPT post), 'php' (registered
 * via the rwmb_meta_boxes filter), or null (not found at all).
 *
 * @return 'db'|'php'|null
 */
function mb_resolve_field_group_source(string $key): ?string
{
    if (!mb_min_runtime_available()) {
        return null;
    }

    // DB lookup by post_name.
    if (mb_builder_available()) {
        /** @var mixed $posts_raw */
        $posts_raw = get_posts([
            'post_type' => 'meta-box',
            'name' => $key,
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);
        if (is_array($posts_raw) && count($posts_raw) > 0) {
            return 'db';
        }
    }

    /** @var mixed $registered_raw */
    $registered_raw = apply_filters('rwmb_meta_boxes', []);
    if (!is_array($registered_raw)) {
        return null;
    }
    /** @var array<int, mixed> $registered */
    $registered = $registered_raw;
    /** @var mixed $row */
    foreach ($registered as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (string) ($row['id'] ?? $row['key'] ?? '');
        if ($id === $key) {
            // db lookup above returned null → assume php source.
            return 'php';
        }
    }

    return null;
}

/**
 * Find the underlying `meta-box` CPT post for a field group key. Returns null
 * if the field group is not in DB (or not found at all).
 */
function mb_get_field_group_post(string $key): ?\WP_Post
{
    if (!mb_builder_available()) {
        return null;
    }
    /** @var mixed $posts_raw */
    $posts_raw = get_posts([
        'post_type' => 'meta-box',
        'name' => $key,
        'post_status' => 'publish',
        'numberposts' => 1,
    ]);
    if (!is_array($posts_raw) || count($posts_raw) === 0) {
        return null;
    }
    /** @var mixed $first */
    $first = $posts_raw[0];
    return $first instanceof \WP_Post ? $first : null;
}

/**
 * Resolve a field group definition by key (preferred), id, or slug.
 *
 * Returns the same shape MB Builder writes to its postmeta + the
 * `rwmb_meta_boxes` filter rows use, plus a `source` field and an
 * agent-facing `key` field.
 *
 * @return array<array-key, mixed>|null
 */
function mb_get_field_group(string $key): ?array
{
    $source = mb_resolve_field_group_source($key);
    if ($source === null) {
        return null;
    }

    if ($source === 'db') {
        $post = mb_get_field_group_post($key);
        if ($post === null) {
            return null;
        }
        /** @var array<string, mixed> $settings */
        $settings = (static function () use ($post): array {
            /** @var mixed $raw */
            $raw = get_post_meta($post->ID, key: 'settings', single: true);
            return is_array($raw) ? $raw : [];
        })();
        // Read from `meta_box` postmeta (runtime shape, structured options) —
        // the `fields` postmeta now stores the React-UI shape.
        $fields = mb_read_runtime_fields_from_post($post);

        return array_merge($settings, [
            'id' => (string) ($settings['id'] ?? $post->post_name),
            'key' => (string) ($settings['id'] ?? $post->post_name),
            'title' => $post->post_title,
            'fields' => mb_translate_fields_to_output($fields),
            'source' => 'db',
            'db_post_id' => $post->ID,
        ]);
    }

    /** @var mixed $registered_raw */
    $registered_raw = apply_filters('rwmb_meta_boxes', []);
    if (!is_array($registered_raw)) {
        return null;
    }
    /** @var array<int, mixed> $registered */
    $registered = $registered_raw;
    /** @var mixed $row */
    foreach ($registered as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (string) ($row['id'] ?? $row['key'] ?? '');
        if ($id !== $key) {
            continue;
        }
        /** @var mixed $fields_raw */
        $fields_raw = $row['fields'] ?? [];
        return array_merge($row, [
            'id' => $id,
            'key' => $id,
            'fields' => is_array($fields_raw) ? mb_translate_fields_to_output($fields_raw) : [],
            'source' => 'php',
        ]);
    }
    return null;
}

/**
 * Try/catch wrapper that normalises a write/save call to a consistent shape.
 *
 * @param callable(): mixed $fn
 * @return array{success: bool, id?: int|string, error?: string}
 */
function mb_handle_api_result(callable $fn): array
{
    try {
        /** @var mixed $result */
        $result = $fn();
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    if ($result instanceof WP_Error) {
        return ['success' => false, 'error' => $result->get_error_message()];
    }
    if ($result === false || $result === null) {
        return ['success' => false, 'error' => 'unknown write failure'];
    }
    if (is_int($result) && $result > 0) {
        return ['success' => true, 'id' => $result];
    }
    if (is_string($result) && $result !== '') {
        return ['success' => true, 'id' => $result];
    }
    return ['success' => true];
}

/**
 * Reserved CPT slugs the agent must not redefine via metabox-create-post-type.
 *
 * @return list<string>
 */
function mb_reserved_post_type_slugs(): array
{
    return [
        'meta-box',
        'meta-box-template',
        'mb-post-type',
        'mb-taxonomy',
        'mb-settings-page',
        'mb-relationship',
        'post',
        'page',
        'attachment',
        'revision',
        'nav_menu_item',
    ];
}

/**
 * Refuse to edit/delete a PHP-registered field group / CPT / taxonomy.
 */
function mb_assert_db_source(string $key, ?string $source): ?WP_Error
{
    if ($source === 'db') {
        return null;
    }
    if ($source === 'php') {
        return new WP_Error('metabox_php_registered', sprintf(
            /* translators: %s: the key of the resource. */
            __(
                '"%s" is registered via PHP and is read-only at runtime. Edit the registering PHP file, or create a new DB-backed copy via the create-* ability to take ownership.',
                domain: 'novamira-pro',
            ),
            $key,
        ));
    }
    return new WP_Error('metabox_not_found', sprintf(
        /* translators: %s: the key of the resource. */
        __('"%s" not found.', domain: 'novamira-pro'),
        $key,
    ));
}

/**
 * Translate agent-facing field shape (`name` = meta-key slug, `label` =
 * human label) into Meta Box / MB Builder storage shape (`id` = meta-key
 * slug, `name` = human label). Recurses into nested `fields` arrays for
 * `group` / `clone` types.
 *
 * The agent-facing convention matches ACF / Pods / JE / ASE for cross-
 * integration consistency. Meta Box internally always uses `id` + `name`,
 * so this translation runs at the boundary of every field-group write.
 *
 * `name` keys that fail to resolve to a non-empty string are skipped — the
 * caller's validation layer should reject empty-named fields up front, but
 * the helper is defensive.
 *
 * @param array<int|string, mixed> $fields
 * @return list<array<array-key, mixed>>
 */
function mb_translate_fields_to_storage(array $fields): array
{
    $out = [];
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $row = $field;
        if (array_key_exists('name', $row) && !array_key_exists('id', $row)) {
            $row['id'] = $row['name'];
        }
        if (array_key_exists('label', $row)) {
            // Agent's label becomes Meta Box's name. The slug-style `name` we
            // moved to `id` above is overwritten here with the human label.
            $row['name'] = $row['label'];
            unset($row['label']);
        }
        $row = mb_normalize_choice_field_options($row);
        $row = mb_normalize_datalist_shape($row);
        if (isset($row['fields']) && is_array($row['fields'])) {
            $row['fields'] = mb_translate_fields_to_storage($row['fields']);
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Normalize a field's `datalist` to the runtime shape Meta Box uses:
 * `{id: string, options: list<string>}`. Agents (and our field-type schema)
 * express it as a flat list of suggestion strings; mbb-parser 2.6.0's
 * `Unparsers\Field::unparse_datalist()` reads `$datalist['options']`
 * unconditionally and throws on the flat form (older parsers tolerated it),
 * which turned create/edit-field-group into an ability_callback_exception.
 *
 * @param  array<array-key, mixed> $field
 * @return array<array-key, mixed>
 */
function mb_normalize_datalist_shape(array $field): array
{
    /** @var mixed $datalist */
    $datalist = $field['datalist'] ?? null;
    if (!is_array($datalist) || $datalist === []) {
        return $field;
    }
    if (isset($datalist['options']) && is_array($datalist['options'])) {
        // Already the runtime shape — ensure the id key exists.
        if (!isset($datalist['id']) || !is_string($datalist['id']) || $datalist['id'] === '') {
            $datalist['id'] = uniqid();
            $field['datalist'] = $datalist;
        }
        return $field;
    }
    if (!mb_array_is_list($datalist)) {
        return $field;
    }
    $options = [];
    /** @var mixed $option */
    foreach ($datalist as $option) {
        if (!is_scalar($option)) {
            continue;
        }
        $options[] = (string) $option;
    }
    $field['datalist'] = ['id' => uniqid(), 'options' => $options];
    return $field;
}

/**
 * Inverse of mb_translate_fields_to_storage: storage `{id, name}` back to
 * agent-facing `{name, label}`. Used by list-field-groups / get-field-group-schema.
 *
 * @param array<int|string, mixed> $fields
 * @return list<array<array-key, mixed>>
 */
function mb_translate_fields_to_output(array $fields): array
{
    $out = [];
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $row = $field;
        if (array_key_exists('name', $row)) {
            // Storage `name` is the human label.
            $row['label'] = $row['name'];
        }
        if (array_key_exists('id', $row)) {
            // Storage `id` is the meta-key slug — agent reads it as `name`.
            $row['name'] = $row['id'];
        }
        unset($row['id']);
        if (isset($row['fields']) && is_array($row['fields'])) {
            $row['fields'] = mb_translate_fields_to_output($row['fields']);
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Field types whose `options` setting is a choice list that Meta Box stores as
 * an associative `{value: label}` map at runtime. `get-field-type-schema`
 * documents both an assoc map AND an indexed list of `{value, label}` objects
 * as acceptable input shapes for the agent, so the indexed-list form has to
 * be normalized to the assoc form here before reaching either MBBParser
 * (which corrupts the indexed-list form into `{"": <last-label>}`) or the
 * `fields` postmeta where MB Builder's React UI reads it.
 *
 * @return list<string>
 */
function mb_choice_field_types_with_options(): array
{
    return ['select', 'radio', 'checkbox_list', 'select_advanced', 'autocomplete'];
}

/**
 * Normalize the `options` setting of a single field to the assoc-array form
 * Meta Box uses at runtime, when the agent passed an indexed list of
 * `{value, label}` objects (the alternate shape documented in
 * get-field-type-schema). Leaves the field untouched when:
 *  - the field type is not a choice type with options,
 *  - `options` is not an array,
 *  - `options` is already an assoc map (anything that is not a list).
 *
 * @param array<array-key, mixed> $field
 * @return array<array-key, mixed>
 */
function mb_normalize_choice_field_options(array $field): array
{
    $type = is_string($field['type'] ?? null) ? $field['type'] : '';
    if (!in_array(needle: $type, haystack: mb_choice_field_types_with_options(), strict: true)) {
        return $field;
    }
    /** @var mixed $options */
    $options = $field['options'] ?? null;
    if (!is_array($options) || !mb_array_is_list($options)) {
        return $field;
    }
    $assoc = [];
    /** @var mixed $entry */
    foreach ($options as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        /** @var mixed $value_raw */
        $value_raw = $entry['value'] ?? null;
        if ($value_raw === null) {
            continue;
        }
        $value = (string) $value_raw;
        /** @var mixed $label_raw */
        $label_raw = $entry['label'] ?? null;
        $assoc[$value] = is_string($label_raw) ? $label_raw : $value;
    }
    $field['options'] = $assoc;
    return $field;
}

/**
 * Convert a list of runtime-shape fields (assoc `options`, no `_id`, plain
 * attribute arrays) into the shape MB Builder's React admin app expects in
 * the `fields` postmeta: choice options as a newline-delimited `value:Label`
 * string, `_id` injected per field, and the various default values / boolean
 * / numeric / nested-array conversions the React form relies on.
 *
 * The conversion is exactly `MBBParser\Unparsers\Field::unparse()` — the same
 * code path MB Builder runs when importing field-group JSON. Recurses into
 * nested `fields` arrays so `group`-type fields propagate the conversion to
 * their children.
 *
 * Safe-fallback: returns the input unchanged when the Unparser class is not
 * loaded (i.e. MB Builder is not active — `mb_builder_guard()` should already
 * have stopped callers, but the helper does not assume that).
 *
 * @param array<int|string, mixed> $fields
 * @return list<array<array-key, mixed>>
 */
function mb_unparse_fields_for_builder_ui(array $fields): array
{
    if (!class_exists('\\MBBParser\\Unparsers\\Field')) {
        return mb_filter_runtime_fields_list($fields);
    }
    $out = [];
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        if (isset($field['fields']) && is_array($field['fields'])) {
            $field['fields'] = mb_unparse_fields_for_builder_ui($field['fields']);
        }
        $unparser = new \MBBParser\Unparsers\Field($field);
        $unparser->unparse();
        $out[] = $unparser->get_settings();
    }
    return $out;
}

/**
 * Read the runtime-shape fields list for an MB Builder field-group post.
 *
 * After the builder-UI conversion lands, the `fields` postmeta stores the
 * React-UI shape (string choice options, etc.) — list/get readers must NOT
 * read from there or the agent gets a payload whose `options` is a
 * `"value:Label\r\n..."` string instead of a structured map. The
 * `meta_box` postmeta is written by `MBBParser\Parsers\MetaBox` and contains
 * the runtime-shape fields under its `fields` key; read from there as the
 * canonical source and fall back to the legacy `fields` postmeta only when
 * `meta_box` is absent (e.g. very old DB rows that pre-date the meta_box
 * write path).
 *
 * @return list<array<array-key, mixed>>
 */
function mb_read_runtime_fields_from_post(\WP_Post $post): array
{
    /** @var mixed $meta_box_raw */
    $meta_box_raw = get_post_meta($post->ID, key: 'meta_box', single: true);
    if (is_array($meta_box_raw) && isset($meta_box_raw['fields']) && is_array($meta_box_raw['fields'])) {
        return mb_filter_runtime_fields_list($meta_box_raw['fields']);
    }
    /** @var mixed $fields_raw */
    $fields_raw = get_post_meta($post->ID, key: 'fields', single: true);
    return is_array($fields_raw) ? mb_filter_runtime_fields_list($fields_raw) : [];
}

/**
 * Drop non-array entries from a fields list so the type stays a clean
 * list<array<array-key, mixed>>.
 *
 * @param array<int|string, mixed> $fields
 * @return list<array<array-key, mixed>>
 */
function mb_filter_runtime_fields_list(array $fields): array
{
    $out = [];
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $out[] = $field;
    }
    return $out;
}

/**
 * Soft-flush rewrite rules after a CPT / taxonomy definition change.
 *
 * MB registers the new definition on the NEXT request's `init` (we only
 * store an `mb-post-type` / `mb-taxonomy` post here), so calling
 * `flush_rewrite_rules()` in THIS request would regenerate the rules
 * without the new type and its URLs would still 404. Deleting the option
 * instead makes WordPress rebuild the rules lazily on the next request,
 * after the type is registered — same net effect as re-saving Permalinks.
 */
function mb_soft_flush_rewrite_rules(): void
{
    delete_option('rewrite_rules');
}
