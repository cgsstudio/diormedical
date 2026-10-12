<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-list-product-categories', [
    'label' => __('List Product Categories', domain: 'novamira-pro'),
    'description' => __(
        'Returns the WooCommerce product categories. Tree-shaped: each item carries `parent_id` (term id) and `parent_slug` so callers can build the hierarchy. Includes empty categories by default. Use `hide_empty: true` to exclude categories that have zero published products. The `search` filter does substring match on the name field. Default limit is 50; pass `limit` to fetch more. Pass `include_thumbnail: true` to fetch the featured image id and URL per category; omitted by default to reduce payload. The `parent` and `parent_id` filter inputs are interchangeable; provide one or the other, not both.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'search' => ['type' => 'string', 'description' => 'Substring match on the name field.'],
            'parent' => [
                'type' => ['integer', 'null'],
                'description' => 'Filter to direct children of a parent id. 0 = top-level only. Alias: parent_id.',
            ],
            'parent_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Alias for `parent`. Filter to direct children of a parent id. 0 = top-level only. Provide one or the other, not both.',
            ],
            'hide_empty' => [
                'type' => 'boolean',
                'description' => 'Exclude categories with no published products.',
                'default' => false,
            ],
            'limit' => ['type' => 'integer', 'description' => 'Max results. Default 50, max 200.', 'default' => 50],
            'include_thumbnail' => [
                'type' => 'boolean',
                'description' => 'When true, each category row includes `thumbnail_id` and `thumbnail_src`. Omitted by default to reduce payload.',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'categories' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Category term ID.'],
                        'name' => ['type' => 'string', 'description' => 'Display name.'],
                        'slug' => ['type' => 'string', 'description' => 'URL slug.'],
                        'description' => ['type' => 'string', 'description' => 'Category description.'],
                        'count' => ['type' => 'integer', 'description' => 'Number of products directly attached.'],
                        'parent_id' => ['type' => 'integer', 'description' => 'Parent category ID; 0 if root.'],
                        'parent_slug' => [
                            'type' => ['string', 'null'],
                            'description' => 'Parent category slug; null if root.',
                        ],
                        'thumbnail_id' => [
                            'type' => ['integer', 'null'],
                            'description' => 'Featured image attachment ID or null.',
                        ],
                        'thumbnail_src' => [
                            'type' => ['string', 'null'],
                            'description' => 'Featured image URL or null.',
                        ],
                        'display_type' => [
                            'type' => 'string',
                            'description' => 'Archive display: default, products, subcategories, or both.',
                        ],
                        'menu_order' => ['type' => 'integer', 'description' => 'Sort order.'],
                    ],
                    'required' => [
                        'id',
                        'slug',
                        'name',
                        'parent_id',
                        'count',
                        'description',
                        'display_type',
                        'menu_order',
                    ],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['categories', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_product_categories',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use the response to build a category tree by grouping items by `parent_id`. Top-level items have parent_id = 0.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_list_product_categories(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }
    $hideEmpty = ($input['hide_empty'] ?? false) === true;
    $includeThumbnail = ($input['include_thumbnail'] ?? false) === true;
    $args = [
        'taxonomy' => 'product_cat',
        'hide_empty' => $hideEmpty,
        'number' => min(200, max(1, (int) ($input['limit'] ?? 50))),
    ];
    if (isset($input['search']) && is_string($input['search']) && trim($input['search']) !== '') {
        $args['search'] = $input['search'];
    }
    if (isset($input['parent_id'], $input['parent'])) {
        return wc_error('wc_invalid_input', __(
            'Provide either `parent` or `parent_id`, not both.',
            domain: 'novamira-pro',
        ));
    }
    /** @var int|null $raw_parent */
    $raw_parent = $input['parent_id'] ?? $input['parent'] ?? null;
    if ($raw_parent !== null) {
        $args['parent'] = (int) $raw_parent;
    }
    $terms = new WP_Error();
    try {
        /** @var list<\WP_Term>|WP_Error $terms */
        $terms = get_terms($args);
    } catch (\Throwable) {
        return wc_error('wc_internal_error', __('WooCommerce query failed unexpectedly.', domain: 'novamira-pro'));
    }
    if ($terms instanceof WP_Error) {
        return wc_error('wc_invalid_input', $terms->get_error_message());
    }
    $categories = array_values(array_map(static fn(\WP_Term $t): array => $includeThumbnail
        ? wc_term_to_array($t, taxonomy: 'product_cat')
        : wc_term_strip_thumbnail(wc_term_to_array($t, taxonomy: 'product_cat')), $terms));
    return ['categories' => $categories, 'total' => count($categories)];
}
