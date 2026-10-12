<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-list-product-shipping-classes', [
    'label' => __('List Product Shipping Classes', domain: 'novamira-pro'),
    'description' => __(
        'Returns all WooCommerce shipping classes (taxonomy: product_shipping_class). '
        . 'Optional: `limit` (default 100, max 500), `offset`, `hide_empty` (bool, default false), '
        . '`orderby` (name|id|count; default name), `order` (asc|desc; default asc). '
        . 'Each row: {id, name, slug, count, description}. '
        . 'Possible errors: wc_inactive.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'limit' => ['type' => 'integer', 'description' => 'Max classes to return. Default 100, max 500.'],
            'offset' => ['type' => 'integer', 'description' => 'Number of classes to skip for pagination.'],
            'hide_empty' => ['type' => 'boolean', 'description' => 'Exclude classes with no products. Default false.'],
            'orderby' => ['type' => 'string', 'description' => 'Sort field. Allowed: name, id, count. Default name.'],
            'order' => ['type' => 'string', 'description' => 'Sort direction. Allowed: asc, desc. Default asc.'],
        ],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'shipping_classes' => [
                'type' => 'array',
                'description' => 'Array of shipping class objects.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Shipping class term id.'],
                        'name' => ['type' => 'string', 'description' => 'Display label.'],
                        'slug' => ['type' => 'string', 'description' => 'URL slug.'],
                        'description' => ['type' => 'string', 'description' => 'Shipping class description.'],
                        'count' => [
                            'type' => 'integer',
                            'description' => 'Number of products assigned to this shipping class.',
                        ],
                    ],
                    'required' => ['id', 'name', 'slug', 'description', 'count'],
                ],
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'Total number of shipping classes (ignores limit/offset).',
            ],
            'returned' => [
                'type' => 'integer',
                'description' => 'Number of shipping classes returned in this response.',
            ],
        ],
        'required' => ['shipping_classes', 'total', 'returned'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_product_shipping_classes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
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
function woocommerce_list_product_shipping_classes(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $taxonomy = 'product_shipping_class';

    $bool_err = wc_strict_bool_check($input, ['hide_empty']);
    if ($bool_err !== null) {
        return $bool_err;
    }

    $limit_raw = isset($input['limit']) ? (int) $input['limit'] : 100;
    $limit = max(1, min(500, $limit_raw));
    $offset = isset($input['offset']) ? max(0, (int) $input['offset']) : 0;
    $hide_empty = isset($input['hide_empty']) && ($input['hide_empty'] === true || $input['hide_empty'] === 1);

    $allowed_orderby = ['name', 'id', 'count'];
    $orderby_raw = isset($input['orderby']) ? (string) $input['orderby'] : 'name';
    $orderby = in_array($orderby_raw, $allowed_orderby, strict: true) ? $orderby_raw : 'name';

    $order_raw = isset($input['order']) ? strtolower((string) $input['order']) : 'asc';
    $order = in_array($order_raw, ['asc', 'desc'], strict: true) ? strtoupper($order_raw) : 'ASC';

    /** @var int|string $total_raw */
    $total_raw = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => $hide_empty]);
    $total = is_int($total_raw) ? $total_raw : (int) $total_raw;

    /** @var list<WP_Term> $terms_raw */
    $terms_raw = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => $hide_empty,
        'number' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
    ]);

    $classes = [];
    foreach ($terms_raw as $t) {
        $classes[] = [
            'id' => (int) $t->term_id,
            'name' => $t->name,
            'slug' => $t->slug,
            'description' => $t->description,
            'count' => (int) $t->count,
        ];
    }

    return ['shipping_classes' => $classes, 'total' => $total, 'returned' => count($classes)];
}
