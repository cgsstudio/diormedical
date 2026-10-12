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
 * Ability: edit a Voxel taxonomy's settings (partial).
 */

wp_register_ability('novamira/voxel-edit-taxonomy', [
    'label' => __('Edit Voxel Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Update a Voxel taxonomy\'s settings: singular and plural labels, the post types it attaches to, and hierarchical / public flags. Only the values you provide are changed. Changes take effect on the next request. The taxonomy must keep at least one post type.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy key to edit.'],
            'singular' => ['type' => 'string', 'description' => 'New singular label.'],
            'plural' => ['type' => 'string', 'description' => 'New plural label.'],
            'post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Replacement list of post type keys (at least one existing type).',
            ],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Whether terms can have parents.'],
            'publicly_queryable' => [
                'type' => 'boolean',
                'description' => 'Whether the taxonomy has public archive pages.',
            ],
        ],
        'required' => ['taxonomy'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_edit_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update of taxonomy settings. Takes effect next request. Providing an empty/invalid post_types list is refused.',
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
function voxel_edit_taxonomy(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['taxonomy'] ?? null) ? $input['taxonomy'] : '';

    $blank = vx_reject_blank_labels($input, ['singular', 'plural']);
    if ($blank instanceof WP_Error) {
        return $blank;
    }

    return vx_with_lock('taxonomies', static function () use ($input, $key): array|WP_Error {
        $all = vx_read_taxonomies_config();
        $config = vx_taxonomy_config_or_error($all, $key);
        if ($config instanceof WP_Error) {
            return $config;
        }

        $base = isset($config['settings']) && is_array($config['settings']) ? $config['settings'] : [];
        $settings = vx_merge_taxonomy_settings($base, $input, $key);
        if ($settings instanceof WP_Error) {
            return $settings;
        }

        $config['settings'] = $settings;
        $all[$key] = $config;
        vx_write_taxonomies_config($all);

        return ['key' => $key, 'settings' => $settings];
    });
}

/**
 * Merge a partial edit into a taxonomy's stored settings. The key is immutable.
 * Returns a WP_Error when the edit would leave the taxonomy with no post types.
 *
 * @param array<array-key, mixed> $settings
 * @param array<array-key, mixed> $input
 * @return array<array-key, mixed>|WP_Error
 */
function vx_merge_taxonomy_settings(array $settings, array $input, string $key): array|WP_Error
{
    if (isset($input['singular']) && is_string($input['singular'])) {
        $settings['singular'] = sanitize_text_field($input['singular']);
    }
    if (isset($input['plural']) && is_string($input['plural'])) {
        $settings['plural'] = sanitize_text_field($input['plural']);
    }
    if (array_key_exists('post_types', $input)) {
        $post_types = vx_valid_post_type_list($input['post_types']);
        if ($post_types === []) {
            return vx_invalid(__('The taxonomy must keep at least one existing post type.', domain: 'novamira-pro'));
        }
        $settings['post_type'] = $post_types;
    }
    if (array_key_exists('hierarchical', $input)) {
        $settings['hierarchical'] = $input['hierarchical'] === true ? 'yes' : 'no';
    }
    if (array_key_exists('publicly_queryable', $input)) {
        $settings['publicly_queryable'] = $input['publicly_queryable'] === true ? 'yes' : 'no';
    }
    $settings['key'] = $key; // the key is immutable

    return $settings;
}
