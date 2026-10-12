<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: edit a Voxel post type's settings (partial). Does NOT touch search
 * filters or indexing (those require a search-index rebuild — Voxel admin only).
 */

wp_register_ability('novamira/voxel-edit-post-type', [
    'label' => __('Edit Voxel Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Update a Voxel post type\'s settings. Provide singular / plural labels, an icon, and/or a settings object for other settings keys. Nested settings objects are deep-merged, so sending {submissions:{enabled:false}} changes only that flag and leaves the rest intact; lists and scalars replace wholesale. Search-filter and indexing settings are NOT editable here (they require rebuilding the search index — use the Voxel admin). Changes take effect on the next request.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string', 'description' => 'The post type key to edit.'],
            'singular' => ['type' => 'string', 'description' => 'New singular label.'],
            'plural' => ['type' => 'string', 'description' => 'New plural label.'],
            'icon' => ['type' => 'string', 'description' => 'Icon string.'],
            'settings' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Other top-level settings to replace (e.g. submissions, options, permalinks). "indexing" and "search" are not allowed here.',
            ],
        ],
        'required' => ['post_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'settings' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => ['key', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_edit_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial settings update; nested settings objects are deep-merged (a partial nested object keeps untouched siblings), lists/scalars replace. Read current settings with novamira/voxel-get-post-type include=["settings"] first. Rejects "indexing"/"search" changes. Takes effect next request.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_edit_post_type(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';

    $blank = vx_reject_blank_labels($input, ['singular', 'plural']);
    if ($blank instanceof WP_Error) {
        return $blank;
    }

    return vx_with_lock('post_types', static function () use ($input, $key): array|WP_Error {
        $all = vx_read_post_types_config();
        $config = vx_post_type_config_or_error($all, $key);
        if ($config instanceof WP_Error) {
            return $config;
        }

        $base = isset($config['settings']) && is_array($config['settings']) ? $config['settings'] : [];
        $settings = vx_merge_post_type_settings($base, $input, $key);
        if ($settings instanceof WP_Error) {
            return $settings;
        }

        $config['settings'] = $settings;
        $all[$key] = $config;
        vx_write_post_types_config($all);

        return ['key' => $key, 'settings' => $settings];
    });
}

/**
 * Merge a partial edit into a post type's stored settings. The key is immutable.
 * Rejects any attempt to change indexing/search settings (those require a full
 * search-index rebuild and belong in the Voxel admin).
 *
 * @param array<array-key, mixed> $settings
 * @param array<array-key, mixed> $input
 * @return array<array-key, mixed>|WP_Error
 */
function vx_merge_post_type_settings(array $settings, array $input, string $key): array|WP_Error
{
    if (isset($input['singular']) && is_string($input['singular'])) {
        $settings['singular'] = sanitize_text_field($input['singular']);
    }
    if (isset($input['plural']) && is_string($input['plural'])) {
        $settings['plural'] = sanitize_text_field($input['plural']);
    }
    if (isset($input['icon']) && is_string($input['icon'])) {
        $settings['icon'] = sanitize_text_field($input['icon']);
    }
    if (isset($input['settings']) && is_array($input['settings'])) {
        $provided = $input['settings'];
        // Case-insensitive so 'Indexing'/'SEARCH' cannot slip a junk key past the
        // guard. Voxel only rebuilds its index from these settings.
        foreach (array_keys($provided) as $pk) {
            if (is_string($pk) && in_array(strtolower($pk), ['indexing', 'search'], strict: true)) {
                return vx_invalid(__(
                    'Indexing and search-filter settings must be changed in the Voxel admin — they require rebuilding the search index.',
                    domain: 'novamira-pro',
                ));
            }
        }
        unset($provided['key']); // the key is immutable
        // Deep-merge: a partial nested object (e.g. {submissions:{enabled:false}})
        // preserves the caller's untouched sibling settings instead of wiping them.
        $settings = vx_deep_merge_settings($settings, $provided);
    }
    $settings['key'] = $key;

    return $settings;
}
