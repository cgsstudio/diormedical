<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-list-portfolio-items', [
    'label' => __('List Avada Portfolio Items', domain: 'novamira-pro'),
    'description' => __(
        'Compact enumeration of `avada_portfolio` CPT posts. Returns rows with id, title, slug, status, modified, and the first assigned `portfolio_category` term. Paginated: default `limit=25`, hard cap 100. Use `status` to filter (default: any). This is inventory only — use `novamira/read-post` or an execute-php recipe to fetch the full block/shortcode content of a specific item.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
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
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['any', 'publish', 'draft', 'private', 'pending', 'future'],
                'default' => 'any',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'modified' => ['type' => 'string'],
                        'first_category' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['id', 'title', 'slug', 'status', 'modified', 'first_category'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'returned', 'limit', 'offset', 'truncated', 'items'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_list_portfolio_items',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inventory only. `truncated: true` means more items than `limit` matched — page with `offset`. Use novamira/read-post on an id for full content.',
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
function avada_list_portfolio_items(array $input): array|WP_Error
{
    if (!avada_theme_available()) {
        return av_theme_not_active();
    }

    $limit = avada_coerce_int($input['limit'] ?? 25, minimum: 1, maximum: 100);
    $offset = avada_coerce_int($input['offset'] ?? 0, minimum: 0);
    $status = 'any';
    if (
        isset($input['status'])
        && is_string($input['status'])
        && in_array($input['status'], av_accepted_statuses(), strict: true)
    ) {
        $status = $input['status'];
    }

    $query_args = [
        'post_type' => AVADA_CPT_PORTFOLIO,
        'post_status' => $status,
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'modified',
        'order' => 'DESC',
        'suppress_filters' => false,
        'no_found_rows' => false,
    ];

    /** @var array<int, WP_Post> $posts */
    $posts = get_posts($query_args);
    $total = av_query_total(AVADA_CPT_PORTFOLIO, $status);

    /** @var list<array<string, mixed>> $items */
    $items = [];
    foreach ($posts as $post) {
        $terms = get_the_terms($post->ID, taxonomy: 'portfolio_category');
        $first_category = null;
        if (is_array($terms) && $terms !== []) {
            $first_term = $terms[0];
            $first_category = $first_term->name;
        }
        $items[] = [
            'id' => (int) $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'modified' => $post->post_modified_gmt,
            'first_category' => $first_category,
        ];
    }

    return [
        'total' => $total,
        'returned' => count($items),
        'limit' => $limit,
        'offset' => $offset,
        'truncated' => $total > ($offset + count($items)),
        'items' => $items,
    ];
}
