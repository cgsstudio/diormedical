<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-list-product-tags', [
    'label' => __('List Product Tags', domain: 'novamira-pro'),
    'description' => __(
        'Returns WooCommerce product tags as a flat list; tags have no parent hierarchy (unlike categories). '
        . 'Pagination: `limit` controls max results (default 200, max 500); use `offset` with the `search` arg or paginate with repeated calls. `hide_empty: true` excludes tags not assigned to any published product. '
        . '`search` does a substring match on the name field. '
        . 'Each tag in the response includes id, slug, name, count (products tagged), and description. '
        . 'Possible errors: wc_invalid_input (bad limit value), wc_internal_error (query failure).',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'search' => ['type' => 'string', 'description' => 'Substring match on the name field.'],
            'hide_empty' => [
                'type' => 'boolean',
                'description' => 'Exclude tags with no published products.',
                'default' => false,
            ],
            'limit' => ['type' => 'integer', 'description' => 'Max results. Default 200, max 500.', 'default' => 200],
        ],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'tags' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Tag term ID.'],
                        'slug' => ['type' => 'string', 'description' => 'URL slug.'],
                        'name' => ['type' => 'string', 'description' => 'Display name.'],
                        'count' => ['type' => 'integer', 'description' => 'Number of products tagged.'],
                        'description' => ['type' => 'string', 'description' => 'Tag description.'],
                    ],
                    'required' => ['id', 'slug', 'name', 'count', 'description'],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['tags', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_product_tags',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Tags are flat; no `parent` field. Use as a secondary classification on top of categories.',
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
function woocommerce_list_product_tags(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }
    $hideEmpty = ($input['hide_empty'] ?? false) === true;
    $args = [
        'taxonomy' => 'product_tag',
        'hide_empty' => $hideEmpty,
        'number' => min(500, max(1, (int) ($input['limit'] ?? 200))),
    ];
    if (isset($input['search']) && is_string($input['search']) && trim($input['search']) !== '') {
        $args['search'] = $input['search'];
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
    $tags = array_values(array_map(static fn(\WP_Term $t): array => [
        'id' => (int) $t->term_id,
        'slug' => $t->slug,
        'name' => $t->name,
        'count' => (int) $t->count,
        'description' => $t->description,
    ], $terms));
    return ['tags' => $tags, 'total' => count($tags)];
}
