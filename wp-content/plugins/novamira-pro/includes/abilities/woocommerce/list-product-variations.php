<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WC_Product_Variation;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-list-product-variations', [
    'label' => __('List Product Variations', domain: 'novamira-pro'),
    'description' => __(
        'Returns a paginated compact list of variations for a variable product. Required: `parent_id`. Each row: id, parent_id, sku, attributes_summary (e.g. "M / Red"), regular_price, sale_price, stock_status, stock_quantity, is_default. Optional `fields: ["full"]` to include all variation fields per row. Pagination: `limit` (default 50, max 200), `offset`. Ordering: `orderby` (id|menu_order|date), `order` (asc|desc). The default compact shape is ~200 bytes per variation. Passing `fields: ["full"]` returns ~800 bytes per variation. Plan limit accordingly when expanding. Possible errors: wc_invalid_input, wc_not_found (parent does not exist), wc_not_variable_parent (parent is not a variable product, including WooCommerce Subscriptions `variable-subscription`). On multilingual sites (WPML, Polylang), translated copies of a variation may appear as separate rows.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'parent_id' => ['type' => 'integer', 'description' => 'Id of the parent variable product (required).'],
            'limit' => [
                'type' => 'integer',
                'default' => 50,
                'description' => 'Maximum rows to return. Default 50, max 200.',
            ],
            'offset' => ['type' => 'integer', 'default' => 0, 'description' => 'Pagination offset.'],
            'orderby' => [
                'type' => 'string',
                'enum' => ['id', 'menu_order', 'date'],
                'default' => 'menu_order',
                'description' => 'Sort column.',
            ],
            'order' => [
                'type' => 'string',
                'enum' => ['asc', 'desc'],
                'default' => 'asc',
                'description' => 'Sort direction.',
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['full']],
                'description' => 'Opt-in extra fields per row. Pass ["full"] to include all variation fields.',
            ],
        ],
        'required' => ['parent_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'variations' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'Array of variation rows (compact shape by default; full shape if fields=["full"] is passed).',
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'Total count of variations on the parent (before limit/offset).',
            ],
            'returned' => [
                'type' => 'integer',
                'description' => 'Number of rows returned in this response (after limit/offset).',
            ],
        ],
        'required' => ['variations', 'total', 'returned'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_product_variations',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use parent_id to scope the list. Omit fields for the compact shape; add ["full"] only when you need all variation fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Validate pagination and sorting inputs for list-product-variations.
 * Returns WP_Error on the first invalid field, null when all are acceptable.
 *
 * @param array<string, mixed> $input
 */
function wc_validate_list_variations_input(array $input): ?WP_Error
{
    foreach (['limit', 'offset'] as $int_field) {
        /** @var mixed $raw */
        $raw = $input[$int_field] ?? ($int_field === 'limit' ? 50 : 0);
        if (!is_int($raw) && !is_numeric($raw)) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: field name, 2: actual type */
                __('`%1$s` must be an integer (got %2$s).', domain: 'novamira-pro'),
                $int_field,
                gettype($raw),
            ));
        }
    }

    return (
        wc_validate_enum_field(
            $input,
            field: 'orderby',
            allowed: ['id', 'menu_order', 'date'],
        ) ?? wc_validate_enum_field($input, field: 'order', allowed: ['asc', 'desc'])
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_list_product_variations(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $input_err = wc_validate_list_variations_input($input);
    if ($input_err !== null) {
        return $input_err;
    }

    if (!isset($input['parent_id']) || !is_numeric($input['parent_id'])) {
        return wc_error('wc_invalid_input', __('`parent_id` is required.', domain: 'novamira-pro'));
    }
    $parent_id = (int) $input['parent_id'];

    $parent = wc_validate_variable_parent($parent_id);
    if ($parent instanceof WP_Error) {
        return $parent;
    }

    $limit = max(1, min(200, (int) ($input['limit'] ?? 50)));
    $offset = max(0, (int) ($input['offset'] ?? 0));
    $orderby = (string) ($input['orderby'] ?? 'menu_order');
    $order = strtoupper((string) ($input['order'] ?? 'asc'));

    $fields = isset($input['fields']) && is_array($input['fields'])
        ? array_values(array_map(static fn(mixed $v): string => (string) $v, $input['fields']))
        : [];

    $args = [
        'type' => 'variation',
        'parent' => $parent_id,
        'limit' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
        'return' => 'objects',
        'paginate' => false,
        'status' => ['publish', 'private', 'draft'],
    ];

    try {
        /** @var list<WC_Product_Variation>|WP_Error $results */
        $results = \wc_get_products($args);
    } catch (\Throwable) {
        return wc_error('wc_internal_error', __('WooCommerce query failed unexpectedly.', domain: 'novamira-pro'));
    }
    if ($results instanceof WP_Error) {
        return wc_error('wc_internal_error', $results->get_error_message());
    }

    $rows = array_values(array_map(static fn(WC_Product_Variation $v): array => wc_variation_to_array(
        $v,
        $fields,
    ), $results));

    // Total count (children list is cheap for typical variation counts).
    $all_children = $parent->get_children();
    $total = count($all_children);

    return [
        'variations' => $rows,
        'total' => $total,
        'returned' => count($rows),
    ];
}
