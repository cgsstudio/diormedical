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

wp_register_ability('novamira/woocommerce-list-product-attribute-terms', [
    'label' => __('List Product Attribute Terms', domain: 'novamira-pro'),
    'description' => __(
        'Returns the terms (options) registered under a global product attribute. '
        . 'Required: exactly one of `attribute_id` (int) or `taxonomy_slug` (string, e.g. "pa_size" or "size"). '
        . 'Optional: `limit` (default 50, max 200), `offset`, `hide_empty` (bool, default false), '
        . '`orderby` (menu_order|name|name_num|id; default menu_order), `order` (asc|desc; default asc). '
        . 'Each row: {id, name, slug, count, description, attribute_id, taxonomy_slug}. '
        . 'Possible errors: wc_inactive, wc_invalid_input (mutual exclusion), wc_attribute_not_found.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'attribute_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Parent attribute id. Mutual exclusion with taxonomy_slug.',
            ],
            'taxonomy_slug' => [
                'type' => ['string', 'null'],
                'description' => 'Parent attribute taxonomy slug, e.g. "pa_size" or "size". Mutual exclusion with attribute_id.',
            ],
            'limit' => ['type' => 'integer', 'description' => 'Max terms to return. Default 50, max 200.'],
            'offset' => ['type' => 'integer', 'description' => 'Number of terms to skip for pagination.'],
            'hide_empty' => ['type' => 'boolean', 'description' => 'Exclude terms with no products. Default false.'],
            'orderby' => [
                'type' => 'string',
                'description' => 'Sort field. Allowed: menu_order, name, name_num, id. Default menu_order.',
            ],
            'order' => ['type' => 'string', 'description' => 'Sort direction. Allowed: asc, desc. Default asc.'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'terms' => [
                'type' => 'array',
                'description' => 'Array of term objects for this attribute.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Term id.'],
                        'name' => ['type' => 'string', 'description' => 'Term display label, e.g. "Small".'],
                        'slug' => ['type' => 'string', 'description' => 'URL slug (lowercased), e.g. "small".'],
                        'count' => ['type' => 'integer', 'description' => 'Number of products assigned this term.'],
                        'description' => ['type' => 'string', 'description' => 'Term description.'],
                        'attribute_id' => ['type' => 'integer', 'description' => 'Parent attribute id.'],
                        'taxonomy_slug' => [
                            'type' => 'string',
                            'description' => 'Parent attribute taxonomy name, e.g. "pa_size".',
                        ],
                    ],
                    'required' => ['id', 'name', 'slug', 'count', 'description', 'attribute_id', 'taxonomy_slug'],
                ],
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'Total number of terms in this attribute (ignores limit/offset).',
            ],
            'returned' => ['type' => 'integer', 'description' => 'Number of terms returned in this response.'],
        ],
        'required' => ['terms', 'total', 'returned'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_product_attribute_terms',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Provide attribute_id or taxonomy_slug (not both) to list the options registered for that attribute.',
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
function woocommerce_list_product_attribute_terms(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $attr_id = wc_resolve_attribute_id($input);
    if ($attr_id instanceof WP_Error) {
        return $attr_id;
    }

    $attr = \wc_get_attribute($attr_id);
    if (!is_object($attr)) {
        return wc_error('wc_attribute_not_found', sprintf(
            /* translators: attribute id */
            __('Attribute with id %d not found.', domain: 'novamira-pro'),
            $attr_id,
        ));
    }

    $taxonomy_slug = wc_attr_object_to_taxonomy_slug($attr);

    return wc_list_attribute_terms_page($taxonomy_slug, $attr_id, $input);
}

/**
 * Execute the paginated term query for a given attribute taxonomy.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function wc_list_attribute_terms_page(string $taxonomy_slug, int $attr_id, array $input): array|WP_Error
{
    $bool_err = wc_strict_bool_check($input, ['hide_empty']);
    if ($bool_err !== null) {
        return $bool_err;
    }

    $limit_raw = isset($input['limit']) ? (int) $input['limit'] : 50;
    $limit = max(1, min(200, $limit_raw));
    $offset = isset($input['offset']) ? max(0, (int) $input['offset']) : 0;
    $hide_empty = isset($input['hide_empty']) && ($input['hide_empty'] === true || $input['hide_empty'] === 1);

    $allowed_orderby = ['menu_order', 'name', 'name_num', 'id'];
    $orderby_raw = isset($input['orderby']) ? (string) $input['orderby'] : 'menu_order';
    $orderby = in_array($orderby_raw, $allowed_orderby, strict: true) ? $orderby_raw : 'menu_order';

    $order_raw = isset($input['order']) ? strtolower((string) $input['order']) : 'asc';
    $order = in_array($order_raw, ['asc', 'desc'], strict: true) ? strtoupper($order_raw) : 'ASC';

    // Ensure the taxonomy is registered in this PHP process (WC only registers pa_* taxonomies
    // during init; attributes created mid-request won't be registered yet).
    wc_ensure_attribute_taxonomy_registered($taxonomy_slug);

    /** @var int|string|\WP_Error $total_raw */
    $total_raw = wp_count_terms(['taxonomy' => $taxonomy_slug, 'hide_empty' => $hide_empty]);
    $total = wc_wp_count_to_int($total_raw);

    /** @var list<WP_Term>|\WP_Error $terms_raw */
    $terms_raw = get_terms([
        'taxonomy' => $taxonomy_slug,
        'hide_empty' => $hide_empty,
        'number' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
    ]);

    $terms = [];
    foreach ($terms_raw instanceof \WP_Error ? [] : $terms_raw as $t) {
        $terms[] = wc_attribute_term_to_array($t, $attr_id, $taxonomy_slug);
    }

    return ['terms' => $terms, 'total' => $total, 'returned' => count($terms)];
}
