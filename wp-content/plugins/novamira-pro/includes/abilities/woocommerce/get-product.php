<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-get-product', [
    'label' => __('Get Product', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full WC product object for one product, identified by `id` or `slug` (one is required). Includes augmented sections: images (with id, src, alt, position), categories ({id,slug,name}), tags ({id,slug,name}), attributes (id, name, slug, options[]), variations (id list, full variation objects via get-product-variation), meta_data (caller-visible meta only; underscore-prefixed system keys are filtered out), price_html (rendered HTML price string). Date fields normalized to ISO 8601 strings.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Product id. Provide id OR slug.'],
            'slug' => ['type' => 'string', 'description' => 'Product slug. Provide id OR slug.'],
        ],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'sku' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'permalink' => ['type' => 'string'],
            'images' => ['type' => 'array'],
            'categories' => ['type' => 'array'],
            'tags' => ['type' => 'array'],
            'attributes' => ['type' => 'array'],
            'variations' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'variations_truncated' => [
                'type' => 'boolean',
                'description' => 'True when the parent has more than 200 variations and the list is capped. More variations exist; call list-product-variations to enumerate.',
            ],
            'meta_data' => ['type' => 'array'],
            'description_truncated' => [
                'type' => 'boolean',
                'description' => 'True when the stored description exceeded 64 KB and was truncated in this response.',
            ],
            'price' => ['type' => 'string'],
            'regular_price' => ['type' => 'string'],
            'sale_price' => ['type' => ['string', 'null']],
            'stock_status' => ['type' => 'string'],
            'price_html' => [
                'type' => 'string',
                'description' => 'Rendered HTML price string (e.g. "<span class=\"woocommerce-Price-amount\">…</span>").',
            ],
            'brands' => [
                'type' => 'array',
                'description' => 'Brand taxonomy terms (only present when WooCommerce Brands is active).',
            ],
        ],
        'required' => ['id', 'name', 'slug'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_get_product',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'For lists, prefer list-products with fields[] over multiple get-product calls; far cheaper in tokens.',
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
function woocommerce_get_product(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }
    $hasId = isset($input['id']) && is_numeric($input['id']);
    $hasSlug = isset($input['slug']) && is_string($input['slug']) && trim($input['slug']) !== '';
    if (!$hasId && !$hasSlug) {
        return wc_error('wc_invalid_input', __('Either `id` or `slug` is required.', domain: 'novamira-pro'));
    }
    $key = $hasId ? (int) $input['id'] : (string) $input['slug'];
    $product = wc_product_or_error($key);
    if ($product instanceof WP_Error) {
        return $product;
    }
    $data = wc_full_product($product);
    if (!array_key_exists('type', $data)) {
        $data['type'] = $product->get_type();
    }
    return $data;
}
