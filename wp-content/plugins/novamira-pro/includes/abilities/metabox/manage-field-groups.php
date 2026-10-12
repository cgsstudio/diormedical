<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-create-field-group', [
    'label' => __('Create Meta Box Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Meta Box field group stored in the database (MB Builder). Requires MB Builder to be active (metabox-check-setup builder_active=true). Pass key (used as post_name and as the id in the field group config), title, post_types[], and fields[]. Each field must have at minimum an id and a type. Returns the new DB post ID and key. Source will always be "db".',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'Unique slug for the field group. Used as the post_name and the id in the Meta Box config. Auto-generated if omitted.',
            ],
            'title' => ['type' => 'string', 'description' => 'Human-readable field group title.'],
            'post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Post types the field group applies to. Omit when binding to settings_pages or taxonomies instead.',
            ],
            'settings_pages' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Settings page ids (as registered via metabox-create-settings-page) this field group attaches to. Requires MB Settings Page.',
            ],
            'taxonomies' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Taxonomy slugs this field group attaches to. Requires MB Term Meta.',
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'Array of field definitions. Each must have id (string) and type (string).',
            ],
            'context' => [
                'type' => 'string',
                'enum' => ['normal', 'side', 'advanced'],
                'description' => 'Where the meta box appears on the edit screen. Default "normal".',
            ],
            'priority' => [
                'type' => 'string',
                'enum' => ['high', 'core', 'default', 'low'],
                'description' => 'Meta box priority. Default "high".',
            ],
        ],
        'required' => ['title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'db_post_id' => ['type' => 'integer'],
            'source' => ['type' => 'string', 'const' => 'db'],
        ],
        'required' => ['key', 'db_post_id', 'source'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_create_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a new Meta Box field group in the database. Requires MB Builder. Call metabox-check-setup first to confirm builder_active=true. Use metabox-get-field-type-schema to understand field type options before building the fields[] array. Keys must be lowercase alphanumeric with hyphens/underscores; they become the meta key prefix.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/metabox-edit-field-group', [
    'label' => __('Edit Meta Box Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing DB-stored Meta Box field group. Only DB-source field groups can be edited (PHP-registered groups return metabox_php_registered). PATCH semantics: top-level keys you omit are preserved; the fields[] array follows REPLACE semantics — any field not in the payload is removed. Requires MB Builder to be active.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'Field group key to edit. Required.',
            ],
            'title' => ['type' => 'string'],
            'post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'settings_pages' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Settings page ids to attach this field group to.',
            ],
            'taxonomies' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Taxonomy slugs to attach this field group to.',
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'REPLACE semantics: the entire fields array is replaced. Fetch current state with metabox-get-field-group-schema include_fields=true first.',
            ],
            'context' => [
                'type' => 'string',
                'enum' => ['normal', 'side', 'advanced'],
            ],
            'priority' => [
                'type' => 'string',
                'enum' => ['high', 'core', 'default', 'low'],
            ],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'db_post_id' => ['type' => 'integer'],
            'source' => ['type' => 'string', 'const' => 'db'],
        ],
        'required' => ['key', 'db_post_id', 'source'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_edit_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Edits a DB-stored field group. PATCH semantics for top-level keys; REPLACE semantics for fields[] (entire array is replaced). To safely update, first call metabox-get-field-group-schema with include_fields=true, mutate the returned data, then pass it back here. PHP-registered groups are rejected with metabox_php_registered.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/metabox-delete-field-group', [
    'label' => __('Delete Meta Box Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes a DB-stored Meta Box field group. PHP-registered groups cannot be deleted (returns metabox_php_registered). Values previously stored under fields of this group remain in postmeta but become orphaned. Requires MB Builder.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'Field group key to delete.',
            ],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'key' => ['type' => 'string'],
            'orphaned_meta_warning' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'key'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_delete_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use only with explicit user confirmation. Deletion is permanent — the field group CPT post is hard-deleted. Stored values in postmeta are not removed and become orphans. PHP-registered groups return metabox_php_registered error.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{key: string, db_post_id: int, source: 'db'}|WP_Error
 */
function metabox_create_field_group(array $args): array|WP_Error
{
    $guard = mb_builder_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $key_raw */
    $key_raw = $args['key'] ?? null;
    $key = is_string($key_raw) && $key_raw !== '' ? sanitize_key($key_raw) : mb_generate_unique_fg_key();

    $existing = mb_get_field_group_post($key);
    if ($existing !== null) {
        return new WP_Error('metabox_key_exists', sprintf(
            /* translators: %s: field group key. */
            __('A field group with key "%s" already exists.', domain: 'novamira-pro'),
            $key,
        ));
    }

    [$title, $settings, $fields] = mb_build_fg_create_parts($key, $args);

    $ext_guard = mb_field_group_extension_guard($settings, $fields);
    if ($ext_guard !== null) {
        return $ext_guard;
    }

    $post_id = wp_insert_post([
        'post_type' => 'meta-box',
        'post_title' => $title,
        'post_status' => 'publish',
        'post_name' => $key,
    ], wp_error: true);

    if ($post_id instanceof WP_Error) {
        return $post_id;
    }

    update_post_meta($post_id, meta_key: 'settings', meta_value: $settings);
    update_post_meta($post_id, meta_key: 'fields', meta_value: mb_unparse_fields_for_builder_ui($fields));
    mb_write_meta_box_postmeta($post_id, $title, $key, $settings, $fields);

    return ['key' => $key, 'db_post_id' => $post_id, 'source' => 'db'];
}

/**
 * Build the title, settings array, and fields array for a new field group.
 *
 * @param array<string, mixed> $args
 * @return array{0: string, 1: array<string, mixed>, 2: list<mixed>}
 */
function mb_build_fg_create_parts(string $key, array $args): array
{
    /** @var mixed $title_raw */
    $title_raw = $args['title'] ?? '';
    $title = is_string($title_raw) ? $title_raw : '';

    /** @var mixed $fields_raw */
    $fields_raw = $args['fields'] ?? [];
    $fields = is_array($fields_raw) ? mb_translate_fields_to_storage($fields_raw) : [];

    $settings = mb_build_fg_settings($key, $args);

    return [$title, $settings, $fields];
}

/**
 * Build the field-group settings array (the postmeta 'settings' key) from
 * the create payload. Kept separate from mb_build_fg_create_parts so the
 * payload-shape branching doesn't push the parent over mago's cyclomatic
 * complexity limit.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_build_fg_settings(string $key, array $args): array
{
    $post_types = mb_filter_string_list($args['post_types'] ?? []);
    $settings_pages = mb_filter_string_list($args['settings_pages'] ?? []);
    $taxonomies = mb_filter_string_list($args['taxonomies'] ?? []);

    /** @var mixed $context_raw */
    $context_raw = $args['context'] ?? 'normal';
    /** @var mixed $priority_raw */
    $priority_raw = $args['priority'] ?? 'high';

    $settings = [
        'id' => $key,
        'post_types' => $post_types,
        'context' => is_string($context_raw) ? $context_raw : 'normal',
        'priority' => is_string($priority_raw) ? $priority_raw : 'high',
    ];
    if (count($settings_pages) > 0) {
        $settings['settings_pages'] = $settings_pages;
    }
    if (count($taxonomies) > 0) {
        $settings['taxonomies'] = $taxonomies;
    }
    return $settings;
}

/**
 * Normalize a raw value to a list of non-empty strings (defensive helper for
 * the multiple list-valued options on a field group).
 *
 * @return list<string>
 */
function mb_filter_string_list(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $item */
    foreach ($raw as $item) {
        if (!is_string($item)) {
            continue;
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * @param array<string, mixed> $args
 * @return array{key: string, db_post_id: int, source: 'db'}|WP_Error
 */
function metabox_edit_field_group(array $args): array|WP_Error
{
    $guard = mb_builder_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $key_raw */
    $key_raw = $args['key'] ?? null;
    if (!is_string($key_raw) || $key_raw === '') {
        return new WP_Error('metabox_invalid_input', __('key is required.', domain: 'novamira-pro'));
    }
    $key = $key_raw;

    $source = mb_resolve_field_group_source($key);
    $assert = mb_assert_db_source($key, $source);
    if ($assert instanceof WP_Error) {
        return $assert;
    }

    $post = mb_get_field_group_post($key);
    if ($post === null) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: field group key. */
            __('Field group "%s" not found in DB.', domain: 'novamira-pro'),
            $key,
        ));
    }

    $ext_guard = mb_edit_extension_guard($args);
    if ($ext_guard !== null) {
        return $ext_guard;
    }

    mb_apply_fg_patches($args, $post);

    return ['key' => $key, 'db_post_id' => $post->ID, 'source' => 'db'];
}

/**
 * Guard against editing in a field-group state that requires an extension
 * not currently active. Checks only fields the edit args ADD; preserved
 * legacy bindings on the existing post don't block other edits.
 *
 * @param array<string, mixed> $args
 */
function mb_edit_extension_guard(array $args): ?WP_Error
{
    $settings_subset = [
        'taxonomies' => $args['taxonomies'] ?? null,
        'settings_pages' => $args['settings_pages'] ?? null,
    ];
    /** @var mixed $fields_raw */
    $fields_raw = $args['fields'] ?? null;
    $fields = is_array($fields_raw) ? mb_translate_fields_to_storage($fields_raw) : [];
    return mb_field_group_extension_guard($settings_subset, $fields);
}

/**
 * Merge patch args into the field group's `settings` postmeta. PATCH semantics —
 * only keys present in $args are written, the rest are preserved from postmeta.
 *
 * @param array<string, mixed> $args
 */
function mb_patch_fg_settings(array $args, \WP_Post $post): void
{
    /** @var mixed $raw */
    $raw = get_post_meta($post->ID, key: 'settings', single: true);
    /** @var array<string, mixed> $existing */
    $existing = is_array($raw) ? $raw : [];

    foreach (['post_types', 'settings_pages', 'taxonomies'] as $list_key) {
        if (!array_key_exists($list_key, $args)) {
            continue;
        }
        $existing[$list_key] = mb_filter_string_list($args[$list_key]);
    }
    if (isset($args['context']) && is_string($args['context'])) {
        $existing['context'] = $args['context'];
    }
    if (isset($args['priority']) && is_string($args['priority'])) {
        $existing['priority'] = $args['priority'];
    }

    update_post_meta($post->ID, meta_key: 'settings', meta_value: $existing);
}

/**
 * Apply patch args onto an existing field group post (settings, title, fields).
 *
 * @param array<string, mixed> $args
 */
function mb_apply_fg_patches(array $args, \WP_Post $post): void
{
    mb_patch_fg_settings($args, $post);

    if (array_key_exists('title', $args)) {
        /** @var mixed $title_raw */
        $title_raw = $args['title'];
        wp_update_post(['ID' => $post->ID, 'post_title' => is_string($title_raw) ? $title_raw : $post->post_title]);
    }

    // Resolve runtime-shape fields once, in memory: either freshly translated
    // from the edit args, or read from the existing meta_box postmeta (the
    // canonical runtime-shape source — the `fields` postmeta is the UI shape
    // after this fix and would corrupt the Parser if reused as input).
    $fields_runtime = mb_resolve_edit_runtime_fields($post, $args);

    /** @var mixed $latest_settings_raw */
    $latest_settings_raw = get_post_meta($post->ID, key: 'settings', single: true);
    mb_write_meta_box_postmeta(
        $post->ID,
        $post->post_title,
        $post->post_name,
        is_array($latest_settings_raw) ? $latest_settings_raw : [],
        $fields_runtime,
    );
}

/**
 * For mb_apply_fg_patches: pick the runtime-shape fields list to feed into
 * mb_write_meta_box_postmeta. When the edit payload includes `fields`, we
 * use those (and also persist the UI-shape into the `fields` postmeta);
 * otherwise read the existing meta_box's fields as the canonical source.
 *
 * @param array<string, mixed> $args
 * @return list<array<array-key, mixed>>
 */
function mb_resolve_edit_runtime_fields(\WP_Post $post, array $args): array
{
    if (!array_key_exists('fields', $args)) {
        return mb_read_runtime_fields_from_post($post);
    }
    /** @var mixed $fields_raw */
    $fields_raw = $args['fields'];
    $fields_runtime = is_array($fields_raw) ? mb_translate_fields_to_storage($fields_raw) : [];
    update_post_meta($post->ID, meta_key: 'fields', meta_value: mb_unparse_fields_for_builder_ui($fields_runtime));
    return $fields_runtime;
}

/**
 * Write the `meta_box` postmeta MB Builder relies on at runtime.
 *
 * Builder's Register.php skips a field group when `$meta_box['meta_box']` is
 * empty — so we must write this key alongside `settings` and `fields` for
 * the field group to actually register on the front-end / admin.
 *
 * Uses MBBParser when available (canonical path), falling back to a manual
 * merge of settings + fields when the Builder parser class is not loaded
 * (defensive — `mb_builder_guard()` should prevent that).
 *
 * @param array<array-key, mixed> $settings
 * @param array<array-key, mixed> $fields
 */
function mb_write_meta_box_postmeta(
    int $post_id,
    string $post_title,
    string $post_name,
    array $settings,
    array $fields,
): void {
    if (!class_exists('\\MBBParser\\Parsers\\MetaBox')) {
        // Defensive fallback — mb_builder_guard() should prevent this branch in practice.
        $meta_box = array_merge($settings, [
            'title' => $post_title,
            'fields' => $fields,
        ]);
        update_post_meta($post_id, meta_key: 'meta_box', meta_value: $meta_box);
        return;
    }

    $parser = new \MBBParser\Parsers\MetaBox([
        'fields' => $fields,
        'settings' => $settings,
        'post_title' => $post_title,
        'post_name' => $post_name,
    ]);
    $parser->parse();
    $meta_box = $parser->get_settings();

    // The MBBParser drops `settings_pages` and `taxonomies` from the
    // resulting meta_box even though they're required at runtime for the
    // field group to bind to those targets. Restore them from the canonical
    // settings array before persisting.
    foreach (['settings_pages', 'taxonomies'] as $extra_key) {
        if (!array_key_exists($extra_key, $settings)) {
            continue;
        }
        $meta_box[$extra_key] = $settings[$extra_key];
    }
    update_post_meta($post_id, meta_key: 'meta_box', meta_value: $meta_box);
}

/**
 * @param array{key: string} $args
 * @return array{deleted: bool, key: string, orphaned_meta_warning?: string}|WP_Error
 */
function metabox_delete_field_group(array $args): array|WP_Error
{
    $guard = mb_builder_guard();
    if ($guard !== null) {
        return $guard;
    }

    $key = $args['key'];
    if ($key === '') {
        return new WP_Error('metabox_invalid_input', __('key is required.', domain: 'novamira-pro'));
    }

    $source = mb_resolve_field_group_source($key);
    $assert = mb_assert_db_source($key, $source);
    if ($assert instanceof WP_Error) {
        return $assert;
    }

    $post = mb_get_field_group_post($key);
    if ($post === null) {
        return ['deleted' => false, 'key' => $key];
    }

    $result = wp_delete_post($post->ID, force_delete: true);

    return [
        'deleted' => $result !== false && $result !== null,
        'key' => $key,
        'orphaned_meta_warning' => 'values previously stored under this group remain in postmeta as orphans',
    ];
}

/**
 * Generate a unique field group key that does not collide with existing DB groups.
 */
function mb_generate_unique_fg_key(): string
{
    do {
        $entropy = microtime(as_float: true) . wp_generate_password(length: 8, special_chars: false);
        $candidate = 'mb_fg_' . substr(md5($entropy), offset: 0, length: 10);
    } while (mb_get_field_group_post($candidate) !== null);
    return $candidate;
}
