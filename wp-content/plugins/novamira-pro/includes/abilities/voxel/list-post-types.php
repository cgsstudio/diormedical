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
 * Ability: list the Voxel-managed post types as compact rows (no field bodies).
 */

wp_register_ability('novamira/voxel-list-post-types', [
    'label' => __('List Voxel Post Types', domain: 'novamira-pro'),
    'description' => __(
        'List the post types managed by Voxel as compact rows: key, singular and plural labels, whether the post type is public, and how many fields and search filters it has. Native WordPress post types and other plugins\' post types are excluded. Compact by design: to see a post type\'s fields, filters, order, templates or settings call novamira/voxel-get-post-type.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'public_only' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When true, only public post types are returned.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_types' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'singular' => ['type' => 'string'],
                        'plural' => ['type' => 'string'],
                        'public' => ['type' => 'boolean'],
                        'fields_count' => ['type' => 'integer'],
                        'filters_count' => ['type' => 'integer'],
                    ],
                    'required' => ['key', 'label', 'singular', 'plural', 'public', 'fields_count', 'filters_count'],
                    'additionalProperties' => false,
                ],
            ],
            'count' => ['type' => 'integer'],
        ],
        'required' => ['post_types', 'count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_post_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact list — never embeds field or filter bodies. Use key with novamira/voxel-get-post-type to inspect structure, or with novamira/voxel-list-posts to list content.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{post_types: list<array{key: string, label: string, singular: string, plural: string, public: bool, fields_count: int, filters_count: int}>, count: int}|WP_Error
 */
function voxel_list_post_types(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $public_only = ($input['public_only'] ?? false) === true;

    $rows = [];
    foreach (\Voxel\Post_Type::get_voxel_types() as $post_type) {
        $row = vx_post_type_row($post_type);
        if ($public_only && !$row['public']) {
            continue;
        }
        $rows[] = $row;
    }

    return [
        'post_types' => $rows,
        'count' => count($rows),
    ];
}
