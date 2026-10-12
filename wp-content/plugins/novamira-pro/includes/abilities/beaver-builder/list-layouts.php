<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-list-layouts', [
    'label' => __('List Beaver Builder Layouts', domain: 'novamira-pro'),
    'description' => __(
        'Lists posts and Beaver Builder templates where the builder is enabled (`_fl_builder_enabled=1`). Returns compact post rows with layout node counts so an agent can pick a target before calling get-layout. Defaults to all Beaver-supported post types and post_status=any; filter by post_type, status, or search.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'description' => 'Optional post type filter. Must be one of Beaver Builder supported post types.',
            ],
            'status' => ['type' => 'string', 'description' => 'WordPress post status filter. Defaults to any.'],
            'search' => ['type' => 'string', 'description' => 'Optional title/content search term.'],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Maximum rows to return. Defaults to 50; hard cap 500.',
            ],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Pagination offset. Defaults to 0.'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'layouts' => ['type' => 'array', 'items' => ['type' => 'object']],
            'supported_post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'total' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_list_layouts',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to discover Beaver Builder pages/templates before reading their node data. For one layout, call beaver-builder-get-layout with the returned id.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function beaver_builder_list_layouts(array $input): array
{
    $supported = bb_supported_post_types();
    $post_types = $supported;
    if (isset($input['post_type']) && is_string($input['post_type']) && $input['post_type'] !== '') {
        $post_types = in_array($input['post_type'], $supported, strict: true) ? [$input['post_type']] : [];
    }

    $limit = bb_int_arg(input: $input, key: 'limit', default: BB_DEFAULT_LIST_LIMIT, min: 1, max: BB_MAX_LIST_LIMIT);
    $offset = bb_int_arg(input: $input, key: 'offset', default: 0, min: 0, max: 1_000_000);
    $status =
        isset($input['status']) && is_string($input['status']) && $input['status'] !== '' ? $input['status'] : 'any';
    $search = isset($input['search']) && is_string($input['search']) ? $input['search'] : '';

    if ($post_types === []) {
        return [
            'layouts' => [],
            'supported_post_types' => $supported,
            'total' => 0,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    $args = [
        'post_type' => $post_types,
        'post_status' => $status,
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'modified',
        'order' => 'DESC',
        'meta_query' => [
            [
                'key' => '_fl_builder_enabled',
                'value' => '1',
            ],
        ],
    ];
    if ($search !== '') {
        $args['s'] = $search;
    }

    $query = new \WP_Query($args);
    $layouts = [];
    foreach ($query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $layouts[] = bb_layout_row($post);
    }

    return [
        'layouts' => $layouts,
        'supported_post_types' => $supported,
        'total' => (int) $query->found_posts,
        'limit' => $limit,
        'offset' => $offset,
    ];
}

/**
 * @return array<string, mixed>
 */
function bb_layout_row(WP_Post $post): array
{
    $data = bb_layout_data(post_id: $post->ID, status: 'published');
    $counts = bb_layout_counts($data);

    return [
        'id' => $post->ID,
        'title' => get_the_title($post),
        'post_type' => $post->post_type,
        'status' => $post->post_status,
        'modified_gmt' => $post->post_modified_gmt,
        'node_counts' => $counts,
        'has_draft_changes' => bb_layout_has_draft_changes($post->ID),
    ];
}

function bb_layout_has_draft_changes(int $post_id): bool
{
    $published = bb_layout_data(post_id: $post_id, status: 'published');
    $draft = bb_layout_data(post_id: $post_id, status: 'draft');
    return serialize($published) !== serialize($draft);
}
