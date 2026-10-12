<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: list the terms of a Voxel taxonomy as compact rows.
 */

wp_register_ability('novamira/voxel-list-terms', [
    'label' => __('List Voxel Terms', domain: 'novamira-pro'),
    'description' => __(
        'List the terms of a Voxel taxonomy as compact rows: id, name, slug, parent id, post count, and order. Filter by parent (0 for top-level) or a search term. Results are paginated. Compact by design: to read a term\'s icon, color, image, area and full data call novamira/voxel-get-term.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy key, e.g. "places_category".'],
            'parent' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Filter to children of this term id (0 = top-level). Omit for all.',
            ],
            'search' => ['type' => 'string', 'description' => 'Optional name/slug search.'],
            'hide_empty' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When true, exclude terms with no posts.',
            ],
            'per_page' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 50,
                'description' => 'Page size. Requests above 200 are clamped.',
            ],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1, 'description' => '1-based page number.'],
        ],
        'required' => ['taxonomy'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'terms' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'parent' => ['type' => 'integer'],
                        'count' => ['type' => 'integer'],
                        'order' => ['type' => 'integer'],
                    ],
                    'required' => ['id', 'name', 'slug', 'parent', 'count', 'order'],
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
        'required' => ['terms', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_terms',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact list; use parent to walk the hierarchy. Read one term in full with novamira/voxel-get-term. per_page is capped at 200.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{terms: list<array<string, mixed>>, pagination: array{total: int, page: int, per_page: int, total_pages: int}}|WP_Error
 */
function voxel_list_terms(array $input): array|WP_Error
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

    $per_page = vx_clamp_int($input['per_page'] ?? null, min: 1, max: 200, default: 50);
    $page = vx_clamp_int($input['page'] ?? null, min: 1, max: PHP_INT_MAX, default: 1);
    $filters = vx_term_query_filters($input, $key);

    /** @var mixed $raw */
    // @mago-expect analysis:possibly-invalid-argument
    $raw = get_terms(array_merge($filters, [
        'number' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'orderby' => 'name',
        'order' => 'ASC',
    ]));

    /** @var list<WP_Term> $terms */
    $terms = [];
    if (is_array($raw)) {
        /** @var mixed $item */
        foreach ($raw as $item) {
            if (!$item instanceof WP_Term) {
                continue;
            }
            $terms[] = $item;
        }
    }

    $ids = [];
    foreach ($terms as $term) {
        $ids[] = (int) $term->term_id;
    }
    $orders = vx_term_orders($ids);

    $rows = [];
    foreach ($terms as $term) {
        $rows[] = vx_term_row($term, $orders[(int) $term->term_id] ?? 0);
    }

    // @mago-expect analysis:possibly-invalid-argument
    $count = wp_count_terms($filters);
    $total = is_numeric($count) ? (int) $count : 0;
    $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;

    return [
        'terms' => $rows,
        'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $per_page, 'total_pages' => $total_pages],
    ];
}

/**
 * Build the shared get_terms / wp_count_terms filter args (no paging).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function vx_term_query_filters(array $input, string $taxonomy_key): array
{
    $filters = ['taxonomy' => $taxonomy_key, 'hide_empty' => ($input['hide_empty'] ?? false) === true];
    if (array_key_exists('parent', $input)) {
        $parent = vx_coerce_int($input['parent']);
        if ($parent !== null && $parent >= 0) {
            $filters['parent'] = $parent;
        }
    }
    if (isset($input['search']) && is_string($input['search']) && $input['search'] !== '') {
        $filters['search'] = sanitize_text_field($input['search']);
    }
    return $filters;
}
