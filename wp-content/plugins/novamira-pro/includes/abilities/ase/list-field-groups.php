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
 * Ability: List ASE field groups with compact shape and pagination.
 */
wp_register_ability('novamira/ase-list-field-groups', [
    'label' => __('List ASE Field Groups', domain: 'novamira-pro'),
    'description' => __(
        'Lists ASE Custom Field Types (CFT) field groups with compact metadata (key, title, fields_count, target_summary, source, editable). Supports pagination (limit/offset) and substring search on title/key. Use novamira/ase-get-field-group to fetch full location and field details for a specific group.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of field groups to return. Default 50, max 200.',
                'default' => 50,
                'minimum' => 1,
                'maximum' => 200,
            ],
            'offset' => [
                'type' => 'integer',
                'description' => 'Number of field groups to skip before returning results. Default 0.',
                'default' => 0,
                'minimum' => 0,
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Optional. Substring search on field group title and key (post_name).',
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
                        'key' => ['type' => 'string', 'description' => 'Field group slug (post_name).'],
                        'title' => ['type' => 'string', 'description' => 'Field group title.'],
                        'fields_count' => ['type' => 'integer', 'description' => 'Number of fields in this group.'],
                        'target_summary' => [
                            'type' => 'string',
                            'description' => 'Human-readable summary of location rules.',
                        ],
                        'source' => ['type' => 'string', 'description' => 'Always "db" for ASE-managed field groups.'],
                        'editable' => [
                            'type' => 'boolean',
                            'description' => 'Always true for ASE-managed field groups.',
                        ],
                    ],
                ],
            ],
            'total' => ['type' => 'integer', 'description' => 'Total number of field groups matching the query.'],
            'has_more' => ['type' => 'boolean', 'description' => 'Whether more results exist beyond the current page.'],
            'next_offset' => [
                'type' => ['integer', 'null'],
                'description' => 'Offset to use for the next page, or null when no more results.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_list_field_groups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns compact list items only. Call novamira/ase-get-field-group with include_fields: true to expand the full field array for a specific group.',
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
function ase_list_field_groups(array $input): array
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
        'Enable the "Custom Field Types" module in ASE settings.',
        domain: 'novamira-pro',
    ));
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $limit = ase_fg_resolve_limit($input);
    $offset = ase_fg_resolve_offset($input);
    $search = ase_fg_resolve_search($input);

    $query = ase_fg_run_list_query($limit, $offset, $search);
    $items = ase_fg_build_list_items($query->posts);

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
function ase_fg_resolve_limit(array $input): int
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
function ase_fg_resolve_offset(array $input): int
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
function ase_fg_resolve_search(array $input): string
{
    /** @var mixed $raw */
    $raw = $input['search'] ?? '';

    return is_string($raw) ? trim($raw) : '';
}

/**
 * Run the WP_Query for listing field groups.
 *
 * @param int    $limit
 * @param int    $offset
 * @param string $search
 * @return WP_Query
 */
function ase_fg_run_list_query(int $limit, int $offset, string $search): WP_Query
{
    $args = [
        'post_type' => 'asenha_cfgroup',
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
function ase_fg_build_list_items(array $posts): array
{
    $items = [];

    /** @var mixed $post */
    foreach ($posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }

        $fields = ase_fg_read_fields($post->ID);
        $rules = ase_fg_read_rules($post->ID);

        $items[] = [
            'key' => $post->post_name,
            'title' => $post->post_title,
            'fields_count' => count($fields),
            'target_summary' => ase_fg_target_summary($rules),
            'source' => 'db',
            'editable' => true,
        ];
    }

    return $items;
}

/**
 * Build a one-line human-readable summary of cfgroup_rules for list display.
 *
 * The rules array stores each rule type as `['operator' => '...', 'values' => [...]]`.
 * This helper extracts placement and target identifiers into a compact string.
 *
 * @param array<mixed> $rules maybe_unserialize'd cfgroup_rules value.
 * @return string
 */
function ase_fg_target_summary(array $rules): string
{
    if ($rules === []) {
        return 'posts: (all)';
    }

    /** @var mixed $raw_placement */
    $raw_placement = $rules['placement']['values'] ?? '';
    $placement = is_string($raw_placement) ? $raw_placement : '';

    if ($placement === 'taxonomy-terms') {
        return ase_fg_summary_taxonomy($rules);
    }

    if ($placement === 'options-pages') {
        return ase_fg_summary_options_pages($rules);
    }

    return ase_fg_summary_posts($rules);
}

/**
 * Build target summary for taxonomy-terms placement.
 *
 * @param array<mixed> $rules
 * @return string
 */
function ase_fg_summary_taxonomy(array $rules): string
{
    /** @var mixed $raw */
    $raw = $rules['taxonomies']['values'] ?? [];
    $taxonomies = is_array($raw) ? array_filter(array_map('strval', $raw)) : [];

    return $taxonomies !== [] ? 'terms: ' . implode(', ', $taxonomies) : 'terms: (all)';
}

/**
 * Build target summary for options-pages placement.
 *
 * @param array<mixed> $rules
 * @return string
 */
function ase_fg_summary_options_pages(array $rules): string
{
    /** @var mixed $raw */
    $raw = $rules['options_pages']['values'] ?? [];
    $pages = is_array($raw) ? array_filter(array_map('strval', $raw)) : [];

    return $pages !== [] ? 'options-pages: ' . implode(', ', $pages) : 'options-pages: (all)';
}

/**
 * Build target summary for posts placement (default).
 *
 * @param array<mixed> $rules
 * @return string
 */
function ase_fg_summary_posts(array $rules): string
{
    /** @var mixed $raw */
    $raw = $rules['post_types']['values'] ?? [];
    $post_types = is_array($raw) ? array_filter(array_map('strval', $raw)) : [];

    return $post_types !== [] ? 'posts: ' . implode(', ', $post_types) : 'posts: (all)';
}
