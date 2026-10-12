<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: list posts of a Voxel post type as compact rows (no field values).
 */

wp_register_ability('novamira/voxel-list-posts', [
    'label' => __('List Voxel Posts', domain: 'novamira-pro'),
    'description' => __(
        'List posts (listings) of one Voxel post type as compact rows: id, title, status, author id, and creation date. Filter by status, a search term, or author. Results are paginated and the page size is capped. Compact by design: to read a post\'s field values call novamira/voxel-get-post.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'description' => 'The Voxel post type key, e.g. "places".',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['any', 'publish', 'draft', 'pending', 'private', 'future', 'trash'],
                'default' => 'publish',
                'description' => 'Post status filter. "any" excludes trash.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Optional search term matched against title and content.',
            ],
            'author' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Optional author user id filter.',
            ],
            'per_page' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 25,
                'description' => 'Page size. Requests above the 200 cap are clamped to 200.',
            ],
            'page' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 1,
                'description' => '1-based page number.',
            ],
        ],
        'required' => ['post_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'posts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'author_id' => ['type' => 'integer'],
                        'date_gmt' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'title', 'status', 'author_id', 'date_gmt'],
                    'additionalProperties' => false,
                ],
            ],
            'pagination' => [
                'type' => 'object',
                'properties' => [
                    'total' => ['type' => 'integer'],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total_pages' => ['type' => 'integer'],
                ],
                'required' => ['total', 'page', 'per_page', 'total_pages'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['posts', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_posts',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact list — never embeds field values. Use id with novamira/voxel-get-post to read values. per_page is capped at 200.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{posts: list<array{id: int, title: string, status: string, author_id: int, date_gmt: string}>, pagination: array{total: int, page: int, per_page: int, total_pages: int}}|WP_Error
 */
function voxel_list_posts(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $post_type = vx_post_type_or_error($key);
    if ($post_type instanceof WP_Error) {
        return $post_type;
    }

    $per_page = vx_clamp_int($input['per_page'] ?? null, min: 1, max: 200, default: 25);
    $page = vx_clamp_int($input['page'] ?? null, min: 1, max: PHP_INT_MAX, default: 1);

    $query = new WP_Query(vx_build_posts_query_args($input, $key, $per_page, $page));

    $rows = [];
    foreach ($query->posts as $wp_post) {
        if (!$wp_post instanceof \WP_Post) {
            continue;
        }
        $rows[] = vx_post_row($wp_post);
    }

    $total = (int) $query->found_posts;
    // WP_Query reports found_posts = 0 when `paged` overshoots the last page, which
    // would falsely tell the agent the collection is empty. Recount at page 1.
    if ($rows === [] && $page > 1) {
        $count_query = new WP_Query(vx_build_posts_query_args($input, $key, per_page: 1, page: 1));
        $total = (int) $count_query->found_posts;
    }
    $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;

    return [
        'posts' => $rows,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        ],
    ];
}

/**
 * Build the WP_Query args for a Voxel post listing.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function vx_build_posts_query_args(array $input, string $key, int $per_page, int $page): array
{
    $status = is_string($input['status'] ?? null) ? $input['status'] : 'publish';
    $allowed_status = ['any', 'publish', 'draft', 'pending', 'private', 'future', 'trash'];
    if (!in_array($status, $allowed_status, strict: true)) {
        $status = 'publish';
    }

    $args = [
        'post_type' => $key,
        'post_status' => $status,
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC',
        'ignore_sticky_posts' => true,
        'no_found_rows' => false,
        'suppress_filters' => false,
    ];

    if (isset($input['search']) && is_string($input['search']) && $input['search'] !== '') {
        $args['s'] = sanitize_text_field($input['search']);
    }
    if (isset($input['author']) && is_int($input['author']) && $input['author'] > 0) {
        $args['author'] = $input['author'];
    }

    return $args;
}
