<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

use WP_Error;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate posts / pages carrying at least one Spectra One
 * per-post display override — a discovery surface for the agent to know
 * "which posts diverge from the site defaults?" before deep-diving into
 * a specific post with `get-post-display-settings`.
 *
 * Compact list-shape (id + title + post_type + which overrides are set,
 * as a list of the boolean keys). Paginated with a max of 100 rows per
 * call.
 */

wp_register_ability('novamira/spectra-one-list-posts-with-display-overrides', [
    'label' => __('List Posts with Spectra One Display Overrides', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates posts and pages that carry at least one Spectra One per-post display override (hide_header / hide_footer / hide_title / sticky_header / transparent_header). Compact rows: post_id, post_title, post_type, and `overrides` — the list of override keys the row has set. Progressive disclosure: full values are NOT echoed; call novamira/spectra-one-get-post-display-settings for a specific post. `limit` (default 25, max 100) + `offset` paginate; optional `post_type` filter narrows to one type (typically `page` or `post`). `orderby` selects sort order (defaults to modified DESC — most recently touched first).',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 25,
            ],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            'post_type' => [
                'type' => 'string',
                'maxLength' => 60,
                'description' => 'Optional post_type slug to narrow the response. Empty (default) returns every public post type.',
            ],
            'orderby' => [
                'type' => 'string',
                'enum' => ['modified', 'date', 'title', 'id'],
                'default' => 'modified',
            ],
            'order' => [
                'type' => 'string',
                'enum' => ['ASC', 'DESC'],
                'default' => 'DESC',
            ],
        ],
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
                        'post_id' => ['type' => 'integer', 'minimum' => 1],
                        'post_title' => ['type' => 'string', 'maxLength' => 200],
                        'post_type' => ['type' => 'string', 'maxLength' => 60],
                        'overrides' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                                'enum' => [
                                    'hide_header',
                                    'hide_footer',
                                    'hide_title',
                                    'sticky_header',
                                    'transparent_header',
                                ],
                            ],
                        ],
                    ],
                    'required' => ['post_id', 'post_title', 'post_type', 'overrides'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer', 'minimum' => 0],
            'returned' => ['type' => 'integer', 'minimum' => 0],
        ],
        'required' => ['posts', 'total', 'returned'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_list_posts_with_display_overrides',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact discovery. Values are NOT echoed — use novamira/spectra-one-get-post-display-settings on a specific post_id when you need the full boolean map.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{limit?: int, offset?: int, post_type?: string, orderby?: string, order?: string} $input
 * @return array{
 *     posts: list<array{post_id: int, post_title: string, post_type: string, overrides: list<string>}>,
 *     total: int,
 *     returned: int,
 * }|WP_Error
 */
function spectra_one_list_posts_with_display_overrides(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $limit = so_clamp_int($input['limit'] ?? 25, min: 1, max: 100);
    $offset = max(0, (int) ($input['offset'] ?? 0));
    $post_type = trim($input['post_type'] ?? '');
    $orderby = $input['orderby'] ?? 'modified';
    $order = $input['order'] ?? 'DESC';

    $meta_keys = so_display_meta_keys();

    $args = [
        'post_type' => $post_type !== '' ? $post_type : 'any',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
        'post_status' => ['publish', 'draft', 'private', 'future', 'pending'],
        'no_found_rows' => false,
        'ignore_sticky_posts' => true,
        'suppress_filters' => false,
        'meta_query' => array_merge([['relation' => 'OR']], array_map(static fn(string $key): array => [
            'key' => $key,
            'compare' => 'EXISTS',
        ], array_values($meta_keys))),
    ];

    $query = new WP_Query($args);
    $rows = [];
    foreach ($query->posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        $post_id = (int) $post->ID;
        $rows[] = [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'overrides' => so_active_display_overrides($post_id),
        ];
    }

    return [
        'posts' => $rows,
        'total' => (int) $query->found_posts,
        'returned' => count($rows),
    ];
}
