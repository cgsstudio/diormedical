<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-create-settings-page', [
    'label' => __('Create Meta Box Settings Page', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new settings page via MB Settings Page (stored in the mb-settings-page CPT). Required: id (unique page identifier used as slug and option_name unless overridden), menu_title. Optional: page_title, option_name, parent (parent page id), capability (default edit_theme_options), icon_url, position, style (boxes|no-boxes), columns (1 or 2). The settings page values are readable via metabox-read-values with target.type=settings and target.id=<id>.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Unique page id (slug). Used as option_name unless overridden.',
            ],
            'menu_title' => ['type' => 'string', 'description' => 'Menu item text.'],
            'page_title' => ['type' => 'string', 'description' => 'Browser tab title. Defaults to menu_title.'],
            'option_name' => [
                'type' => 'string',
                'description' => 'WP option key where values are stored. Defaults to id.',
            ],
            'parent' => ['type' => 'string', 'description' => 'Parent page id. Empty for top-level.'],
            'capability' => ['type' => 'string', 'description' => 'Required capability. Default edit_theme_options.'],
            'icon_url' => ['type' => 'string', 'description' => 'Dashicons slug or URL.'],
            'position' => ['type' => 'number', 'description' => 'Menu position.'],
            'style' => ['type' => 'string', 'enum' => ['boxes', 'no-boxes'], 'description' => 'Layout style.'],
            'columns' => ['type' => 'integer', 'enum' => [1, 2], 'description' => 'Number of columns. Default 2.'],
        ],
        'required' => ['id', 'menu_title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'menu_title' => ['type' => 'string'],
            'source' => ['type' => 'string', 'const' => 'db'],
            'db_post_id' => ['type' => 'integer'],
        ],
        'required' => ['id', 'menu_title', 'source', 'db_post_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_create_settings_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a new MB Settings Page entry. The id becomes the URL slug and the WP option key (unless option_name is explicitly set). Read field values via metabox-read-values with target.type=settings and target.id=<id>.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/metabox-edit-settings-page', [
    'label' => __('Edit Meta Box Settings Page', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing DB-stored MB Settings Page. Required: id to identify the record. All other fields follow PATCH semantics. PHP-registered settings pages return metabox_php_registered.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Settings page id to edit.'],
            'menu_title' => ['type' => 'string'],
            'page_title' => ['type' => 'string'],
            'option_name' => ['type' => 'string'],
            'parent' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'icon_url' => ['type' => 'string'],
            'position' => ['type' => 'number'],
            'style' => ['type' => 'string', 'enum' => ['boxes', 'no-boxes']],
            'columns' => ['type' => 'integer', 'enum' => [1, 2]],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'menu_title' => ['type' => 'string'],
            'source' => ['type' => 'string', 'const' => 'db'],
            'db_post_id' => ['type' => 'integer'],
        ],
        'required' => ['id', 'menu_title', 'source', 'db_post_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_edit_settings_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PATCH semantics: omitted keys are preserved. PHP-registered settings pages return metabox_php_registered error.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/metabox-delete-settings-page', [
    'label' => __('Delete Meta Box Settings Page', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes a DB-stored MB Settings Page. The WP option storing field values is NOT deleted — it remains in wp_options as an orphan. PHP-registered pages return metabox_php_registered.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Settings page id to delete.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'id' => ['type' => 'string'],
            'orphaned_option_warning' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_delete_settings_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanently deletes a DB-stored settings page definition. The WP option (option_name) containing stored values is NOT deleted automatically. PHP-registered pages return metabox_php_registered.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{id: string, menu_title: string, source: 'db', db_post_id: int}|WP_Error
 */
function metabox_create_settings_page(array $args): array|WP_Error
{
    $guard = mb_settings_page_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $id_raw */
    $id_raw = $args['id'] ?? null;
    if (!is_string($id_raw) || $id_raw === '') {
        return new WP_Error('metabox_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $id = sanitize_key($id_raw);

    $existing = mb_find_settings_page_post($id);
    if ($existing !== null) {
        return new WP_Error('metabox_id_exists', sprintf(
            /* translators: %s: settings page id. */
            __('A settings page with id "%s" already exists.', domain: 'novamira-pro'),
            $id,
        ));
    }

    /** @var mixed $menu_title_raw */
    $menu_title_raw = $args['menu_title'] ?? '';
    $menu_title = is_string($menu_title_raw) ? $menu_title_raw : $id;

    $settings_page = mb_build_settings_page_settings($id, $menu_title, $args);

    $post_id = wp_insert_post([
        'post_type' => 'mb-settings-page',
        'post_title' => $menu_title,
        'post_status' => 'publish',
        'post_name' => $id,
    ], wp_error: true);

    if ($post_id instanceof WP_Error) {
        return $post_id;
    }

    update_post_meta($post_id, meta_key: 'settings_page', meta_value: $settings_page);

    return ['id' => $id, 'menu_title' => $menu_title, 'source' => 'db', 'db_post_id' => $post_id];
}

/**
 * @param array<string, mixed> $args
 * @return array{id: string, menu_title: string, source: 'db', db_post_id: int}|WP_Error
 */
function metabox_edit_settings_page(array $args): array|WP_Error
{
    $guard = mb_settings_page_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $id_raw */
    $id_raw = $args['id'] ?? null;
    if (!is_string($id_raw) || $id_raw === '') {
        return new WP_Error('metabox_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $id = $id_raw;

    $source = mb_resolve_settings_page_source($id);
    $assert = mb_assert_db_source($id, $source);
    if ($assert instanceof WP_Error) {
        return $assert;
    }

    $post = mb_find_settings_page_post($id);
    if ($post === null) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: settings page id. */
            __('Settings page "%s" not found in DB.', domain: 'novamira-pro'),
            $id,
        ));
    }

    /** @var array<string, mixed> $existing */
    $existing = (static function () use ($post): array {
        /** @var mixed $raw */
        $raw = get_post_meta($post->ID, key: 'settings_page', single: true);
        return is_array($raw) ? $raw : [];
    })();

    // PATCH: merge supplied keys.
    $patch_keys = [
        'menu_title',
        'page_title',
        'option_name',
        'parent',
        'capability',
        'icon_url',
        'position',
        'style',
        'columns',
    ];
    foreach ($patch_keys as $k) {
        if (!isset($args[$k])) {
            continue;
        }
        $existing[$k] = $args[$k];
    }

    /** @var mixed $menu_title_raw */
    $menu_title_raw = $existing['menu_title'] ?? $post->post_title;
    $menu_title = is_string($menu_title_raw) ? $menu_title_raw : $post->post_title;

    update_post_meta($post->ID, meta_key: 'settings_page', meta_value: $existing);

    if (isset($args['menu_title']) && is_string($args['menu_title'])) {
        wp_update_post(['ID' => $post->ID, 'post_title' => $args['menu_title']]);
        $menu_title = $args['menu_title'];
    }

    return ['id' => $id, 'menu_title' => $menu_title, 'source' => 'db', 'db_post_id' => $post->ID];
}

/**
 * @param array{id: string} $args
 * @return array{deleted: bool, id: string, orphaned_option_warning?: string}|WP_Error
 */
function metabox_delete_settings_page(array $args): array|WP_Error
{
    $guard = mb_settings_page_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $args['id'];
    if ($id === '') {
        return new WP_Error('metabox_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $source = mb_resolve_settings_page_source($id);
    $assert = mb_assert_db_source($id, $source);
    if ($assert instanceof WP_Error) {
        return $assert;
    }

    $post = mb_find_settings_page_post($id);
    if ($post === null) {
        return ['deleted' => false, 'id' => $id];
    }

    $deleted = wp_delete_post($post->ID, force_delete: true);

    return [
        'deleted' => $deleted !== false && $deleted !== null,
        'id' => $id,
        'orphaned_option_warning' => 'The WP option storing field values was not deleted; remove it manually via delete_option() if needed.',
    ];
}

/**
 * Build the settings_page postmeta array for a new settings page.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_build_settings_page_settings(string $id, string $menu_title, array $args): array
{
    $settings = mb_build_settings_page_core($id, $menu_title, $args);
    mb_apply_settings_page_optional($settings, $args);
    return $settings;
}

/**
 * Build the required/defaulted portion of the settings_page array.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_build_settings_page_core(string $id, string $menu_title, array $args): array
{
    return [
        'id' => $id,
        'menu_title' => $menu_title,
        'page_title' => isset($args['page_title']) && is_string($args['page_title'])
            ? $args['page_title']
            : $menu_title,
        'option_name' => isset($args['option_name']) && is_string($args['option_name']) ? $args['option_name'] : $id,
        'capability' => isset($args['capability']) && is_string($args['capability'])
            ? $args['capability']
            : 'edit_theme_options',
        'style' => isset($args['style']) && is_string($args['style']) ? $args['style'] : 'boxes',
        'columns' => isset($args['columns']) && is_int($args['columns']) ? $args['columns'] : 2,
    ];
}

/**
 * Append optional fields (parent, icon_url, position) to a settings_page array.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $args
 */
function mb_apply_settings_page_optional(array &$settings, array $args): void
{
    if (isset($args['parent']) && is_string($args['parent']) && $args['parent'] !== '') {
        $settings['parent'] = $args['parent'];
    }
    if (isset($args['icon_url']) && is_string($args['icon_url']) && $args['icon_url'] !== '') {
        $settings['icon_url'] = $args['icon_url'];
    }
    if (isset($args['position'])) {
        $settings['position'] = $args['position'];
    }
}
