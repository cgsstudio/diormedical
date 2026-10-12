<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: List ASE custom taxonomies with compact shape and pagination.
 */
wp_register_ability('novamira/ase-list-taxonomies', [
    'label' => __('List ASE Taxonomies', domain: 'novamira-pro'),
    'description' => __(
        'Lists ASE Custom Content Types (CCT) taxonomy definitions with compact metadata (key, slug, title, public, hierarchical, post_types_count, source, editable). Supports pagination (limit/offset) and substring search on plural name (post_title). Use novamira/ase-get-taxonomy to fetch full details for a specific taxonomy.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of taxonomies to return. Default 50, max 200.',
                'default' => 50,
                'minimum' => 1,
                'maximum' => 200,
            ],
            'offset' => [
                'type' => 'integer',
                'description' => 'Number of taxonomies to skip before returning results. Default 0.',
                'default' => 0,
                'minimum' => 0,
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Optional. Substring search on the plural name (post_title) of the taxonomy definition.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string', 'description' => '"ase_tax_" + the definition post ID.'],
                        'slug' => [
                            'type' => 'string',
                            'description' => 'The registered taxonomy slug (ctax_key postmeta).',
                        ],
                        'title' => ['type' => 'string', 'description' => 'Plural name (post_title).'],
                        'public' => ['type' => 'boolean'],
                        'hierarchical' => ['type' => 'boolean'],
                        'post_types_count' => [
                            'type' => 'integer',
                            'description' => 'Number of post type slugs this taxonomy attaches to.',
                        ],
                        'source' => ['type' => 'string', 'description' => 'Always "db" for ASE-managed taxonomies.'],
                        'editable' => ['type' => 'boolean', 'description' => 'Always true for ASE-managed taxonomies.'],
                    ],
                ],
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'Total number of taxonomy definitions matching the query.',
            ],
            'has_more' => ['type' => 'boolean', 'description' => 'Whether more results exist beyond the current page.'],
            'next_offset' => [
                'type' => ['integer', 'null'],
                'description' => 'Offset to use for the next page, or null when no more results.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_list_taxonomies',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns compact list items only. Call novamira/ase-get-taxonomy to fetch full details including labels and rewrite settings.',
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
function ase_list_taxonomies(array $input): array
{
    $guard = ase_runtime_guard();
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $guard = ase_pro_guard();
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $guard = ase_module_guard('custom_content_types', __(
        'Enable the "Custom Content Types" module in ASE settings.',
        domain: 'novamira-pro',
    ));
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $limit = ctax_list_resolve_limit($input);
    $offset = ctax_list_resolve_offset($input);
    $search = ctax_list_resolve_search($input);

    $query = ctax_list_run_query($limit, $offset, $search);
    $items = ctax_list_build_items($query->posts);

    $total = (int) $query->found_posts;
    $returned = count($items);
    $has_more = ($offset + $returned) < $total;

    return [
        'items' => $items,
        'total' => $total,
        'has_more' => $has_more,
        'next_offset' => $has_more ? $offset + $returned : null,
    ];
}

/**
 * Resolve and clamp the limit input parameter.
 *
 * @param array<string, mixed> $input
 * @return int
 */
function ctax_list_resolve_limit(array $input): int
{
    /** @var mixed $raw */
    $raw = $input['limit'] ?? 50;

    return is_int($raw) ? min(max($raw, 1), 200) : 50;
}

/**
 * Resolve and clamp the offset input parameter.
 *
 * @param array<string, mixed> $input
 * @return int
 */
function ctax_list_resolve_offset(array $input): int
{
    /** @var mixed $raw */
    $raw = $input['offset'] ?? 0;

    return is_int($raw) ? max($raw, 0) : 0;
}

/**
 * Resolve the search input parameter.
 *
 * @param array<string, mixed> $input
 * @return string
 */
function ctax_list_resolve_search(array $input): string
{
    /** @var mixed $raw */
    $raw = $input['search'] ?? '';

    return is_string($raw) ? trim($raw) : '';
}

/**
 * Run the WP_Query for listing taxonomy definitions.
 *
 * @param int    $limit
 * @param int    $offset
 * @param string $search
 * @return WP_Query
 */
function ctax_list_run_query(int $limit, int $offset, string $search): WP_Query
{
    $args = [
        'post_type' => 'asenha_ctax',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => false,
    ];

    if ($search !== '') {
        $args['s'] = $search;
    }

    return new WP_Query($args);
}

/**
 * Build the list items array from a set of WP_Post objects.
 *
 * @param array<mixed> $posts
 * @return list<array<string, mixed>>
 */
function ctax_list_build_items(array $posts): array
{
    $items = [];

    /** @var mixed $post */
    foreach ($posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }

        $public = '1' === (string) get_post_meta($post->ID, key: 'ctax_public', single: true);
        $hierarchical = '1' === (string) get_post_meta($post->ID, key: 'ctax_hierarchical', single: true);

        /** @var mixed $raw_post_types */
        $raw_post_types = get_post_meta($post->ID, key: 'ctax_post_types', single: true);
        if (is_string($raw_post_types)) {
            /** @var mixed $raw_post_types */
            $raw_post_types = maybe_unserialize($raw_post_types);
        }

        $post_types = is_array($raw_post_types) ? $raw_post_types : [];

        $items[] = [
            'key' => 'ase_tax_' . $post->ID,
            'slug' => (string) get_post_meta($post->ID, key: 'ctax_key', single: true),
            'title' => $post->post_title,
            'public' => $public,
            'hierarchical' => $hierarchical,
            'post_types_count' => count($post_types),
            'source' => 'db',
            'editable' => true,
        ];
    }

    return $items;
}
