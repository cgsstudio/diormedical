<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-get-product-variation', [
    'label' => __('Get Product Variation', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full variation object for a single product variation, identified by `id`. Includes: id, parent_id, name (auto-derived from parent; use `attributes_summary` e.g. "M / Red" to identify the specific variation), sku, status, attributes (structured + summary), regular_price, sale_price, price, manage_stock, stock_quantity, stock_status, weight, dimensions, image_id, image_src, description, description_truncated, virtual, downloadable, shipping_class, is_default, date_modified. `description_truncated` is true when the stored description exceeded 64 KB and was truncated in this response. Price fields reflect WooCommerce\'s filter chain (same value the storefront sees). Plugins that filter price at runtime will affect this output. Possible errors: wc_invalid_input, wc_not_found (variation does not exist).',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Variation post id (required).'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Variation post ID.'],
            'parent_id' => ['type' => 'integer', 'description' => 'Parent variable product post ID.'],
            'name' => [
                'type' => 'string',
                'description' => 'Auto-derived display name (parent name + attribute combo).',
            ],
            'sku' => ['type' => 'string', 'description' => 'Stock-keeping unit for this variation.'],
            'status' => ['type' => 'string', 'description' => 'Post status (publish, private, draft, trash).'],
            'attributes' => [
                'type' => 'array',
                'description' => 'Structured attribute list: [{taxonomy_slug|name, option}].',
            ],
            'attributes_summary' => [
                'type' => 'string',
                'description' => 'Human-readable attribute combination string (e.g. "M / Red"). Use this to identify the specific variation.',
            ],
            'regular_price' => ['type' => 'string', 'description' => 'Regular price as a numeric string.'],
            'sale_price' => [
                'type' => ['string', 'null'],
                'description' => 'Sale price as a numeric string, or null when no sale is active.',
            ],
            'price' => [
                'type' => 'string',
                'description' => 'Effective current price as seen by the storefront (after WC filter chain).',
            ],
            'manage_stock' => [
                'type' => 'boolean',
                'description' => 'Whether per-variation stock management is enabled.',
            ],
            'stock_quantity' => [
                'type' => ['integer', 'null'],
                'description' => 'Current stock quantity, or null when manage_stock is false.',
            ],
            'stock_status' => [
                'type' => 'string',
                'description' => 'Stock status: instock, outofstock, or onbackorder.',
            ],
            'weight' => ['type' => 'string', 'description' => 'Variation weight in store units.'],
            'dimensions' => [
                'type' => 'object',
                'description' => 'Physical dimensions: {length, width, height} in store units.',
            ],
            'image_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Attachment ID of the variation image, or null if unset.',
            ],
            'image_src' => [
                'type' => ['string', 'null'],
                'description' => 'URL of the variation image, or null if unset.',
            ],
            'description' => ['type' => 'string', 'description' => 'Variation-level short description.'],
            'description_truncated' => [
                'type' => 'boolean',
                'description' => 'True when the stored description exceeded 64 KB and was truncated in this response.',
            ],
            'virtual' => [
                'type' => 'boolean',
                'description' => 'Whether the variation is virtual (no shipping required).',
            ],
            'downloadable' => ['type' => 'boolean', 'description' => 'Whether the variation is downloadable.'],
            'shipping_class' => [
                'type' => 'string',
                'description' => 'Shipping class slug assigned to this variation.',
            ],
            'is_default' => [
                'type' => 'boolean',
                'description' => 'Whether this variation is the storefront default for the parent.',
            ],
            'date_modified' => [
                'type' => ['string', 'null'],
                'description' => 'ISO 8601 timestamp of the last modification, or null.',
            ],
        ],
        'required' => ['id', 'parent_id', 'sku', 'attributes_summary', 'is_default'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_get_product_variation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use list-product-variations first to discover variation ids; use this for the full object of a single variation.',
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
function woocommerce_get_product_variation(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    if (!isset($input['id']) || !is_numeric($input['id'])) {
        return wc_error('wc_invalid_input', __('`id` is required.', domain: 'novamira-pro'));
    }

    $v = wc_post_to_variation((int) $input['id']);
    if ($v instanceof WP_Error) {
        return $v;
    }

    return wc_variation_full_to_array($v);
}
