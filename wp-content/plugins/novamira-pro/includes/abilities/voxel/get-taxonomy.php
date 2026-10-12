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
 * Ability: read one Voxel taxonomy, optionally with its raw settings.
 */

wp_register_ability('novamira/voxel-get-taxonomy', [
    'label' => __('Get Voxel Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel taxonomy: key, labels, description, public and hierarchical flags, the post types it is attached to, and its term count. Pass include with "settings" to also get the raw Voxel taxonomy settings (permalinks, registration flags).',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy key, e.g. "places_category".'],
            'include' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['settings']],
                'default' => [],
                'description' => 'Pass ["settings"] to embed the raw settings.',
            ],
        ],
        'required' => ['taxonomy'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'terms_count' => ['type' => 'integer'],
            'settings' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => [
            'key',
            'label',
            'singular',
            'plural',
            'description',
            'public',
            'hierarchical',
            'post_types',
            'terms_count',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Summary is always returned; settings are opt-in via include.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_get_taxonomy(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['taxonomy'] ?? null) ? $input['taxonomy'] : '';
    $taxonomy = vx_taxonomy_or_error($key);
    if ($taxonomy instanceof WP_Error) {
        return $taxonomy;
    }

    $out = vx_taxonomy_row($taxonomy);
    $out['description'] = $taxonomy->get_description();

    $include = vx_coerce_string_list($input['include'] ?? []);
    if (in_array('settings', $include, strict: true)) {
        $out['settings'] = $taxonomy->get_config();
    }

    return $out;
}
