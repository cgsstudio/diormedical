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
 * Ability: list the taxonomies managed by Voxel as compact rows.
 */

wp_register_ability('novamira/voxel-list-taxonomies', [
    'label' => __('List Voxel Taxonomies', domain: 'novamira-pro'),
    'description' => __(
        'List the taxonomies managed by Voxel as compact rows: key, singular and plural labels, whether the taxonomy is public, whether it is hierarchical, the post types it is attached to, and how many terms it has. Use the key with novamira/voxel-list-terms to browse its terms.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'taxonomies' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'singular' => ['type' => 'string'],
                        'plural' => ['type' => 'string'],
                        'public' => ['type' => 'boolean'],
                        'hierarchical' => ['type' => 'boolean'],
                        'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'terms_count' => ['type' => 'integer'],
                    ],
                    'required' => [
                        'key',
                        'label',
                        'singular',
                        'plural',
                        'public',
                        'hierarchical',
                        'post_types',
                        'terms_count',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'count' => ['type' => 'integer'],
        ],
        'required' => ['taxonomies', 'count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_taxonomies',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact list. Use key with novamira/voxel-list-terms and novamira/voxel-get-taxonomy.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{taxonomies: list<array<string, mixed>>, count: int}|WP_Error
 */
function voxel_list_taxonomies(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $rows = [];
    foreach (\Voxel\Taxonomy::get_voxel_taxonomies() as $taxonomy) {
        $rows[] = vx_taxonomy_row($taxonomy);
    }

    return ['taxonomies' => $rows, 'count' => count($rows)];
}
