<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WC_Product_Variable;
use WC_Product_Variation;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-create-product-variation', [
    'label' => __('Create Product Variation', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new variation for a variable product. Required: `parent_id`, `attributes` (must be non-empty). The `attributes` list must map every variation-enabled attribute on the parent to an option. Each entry: `{"taxonomy_slug": "pa_size", "option": "M"}` for global attributes, or `{"name": "Material", "option": "Cotton"}` for custom attributes. Exactly one of `taxonomy_slug` or `name` per entry. The `option` must be in the parent\'s attribute options list. Variation name is auto-derived (cannot be set via `name`). Pass `is_default: true` to mark this variation as the storefront default. SKU must be unique if provided. Note: if you set `regular_price` below an existing `sale_price`, WooCommerce automatically clears `sale_price`. To set both, supply them together. WP-native alias: `post_excerpt` = `description`. WordPress strips single backslash characters from `description` on save; use double backslashes if you need a literal backslash. Returns the full variation object on success. Possible errors: wc_invalid_input, wc_not_found (parent does not exist), wc_not_variable_parent (also returned for WooCommerce Subscriptions `variable-subscription`), wc_attribute_not_variable, wc_option_not_on_parent, wc_sku_exists, wc_attachment_not_found, wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'parent_id' => ['type' => 'integer', 'description' => 'Id of the parent variable product (required).'],
            'attributes' => [
                'type' => 'array',
                'description' => 'Attribute assignments. Each entry: {taxonomy_slug, option} for global attributes or {name, option} for custom attributes. One of taxonomy_slug|name per entry.',
                'items' => ['type' => 'object'],
                'minItems' => 1,
            ],
            'sku' => ['type' => 'string', 'description' => 'Stock-keeping unit. Must be globally unique if provided.'],
            'regular_price' => [
                'type' => ['string', 'null'],
                'description' => 'Regular price (numeric string). Must be non-negative. Pass null to clear.',
            ],
            'sale_price' => [
                'type' => ['string', 'null'],
                'description' => 'Sale price (numeric string). Pass null to clear.',
            ],
            'manage_stock' => ['type' => 'boolean', 'description' => 'Enable per-variation stock management.'],
            'stock_quantity' => ['type' => 'integer', 'description' => 'Stock quantity.'],
            'stock_status' => [
                'type' => 'string',
                'enum' => ['instock', 'outofstock', 'onbackorder'],
                'description' => 'Stock status.',
            ],
            'weight' => ['type' => 'string', 'description' => 'Variation weight.'],
            'dimensions' => [
                'type' => 'object',
                'description' => 'Variation dimensions: {length?, width?, height?}.',
                'properties' => [
                    'length' => ['type' => 'string'],
                    'width' => ['type' => 'string'],
                    'height' => ['type' => 'string'],
                ],
            ],
            'image_id' => [
                'type' => 'integer',
                'description' => 'Attachment id for the variation image. Pass 0 to clear.',
            ],
            'description' => ['type' => 'string', 'description' => 'Variation-level description.'],
            'post_excerpt' => ['type' => 'string', 'description' => 'WordPress-native alias for `description`.'],
            'virtual' => ['type' => 'boolean', 'description' => 'Whether the variation is virtual.'],
            'downloadable' => ['type' => 'boolean', 'description' => 'Whether the variation is downloadable.'],
            'shipping_class' => ['type' => 'string', 'description' => 'Shipping class slug.'],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'private', 'draft'],
                'description' => 'Variation status. Defaults to publish.',
            ],
            'is_default' => [
                'type' => 'boolean',
                'description' => 'Mark as the storefront default variation. Replaces any existing default.',
            ],
            'name' => [
                'type' => 'string',
                'description' => 'Not accepted. Variation names are auto-derived; passing `name` returns wc_invalid_input.',
            ],
            'post_title' => [
                'type' => 'string',
                'description' => 'Not accepted. WordPress-native alias for `name`; auto-derived on variations.',
            ],
        ],
        'required' => ['parent_id', 'attributes'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'variation' => ['type' => 'object', 'description' => 'Full variation object.'],
        ],
        'required' => ['success', 'variation'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_create_product_variation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_create_product_variation(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    if (!isset($input['parent_id']) || !is_numeric($input['parent_id'])) {
        return wc_error('wc_invalid_input', __('`parent_id` is required.', domain: 'novamira-pro'));
    }
    $parent_id = (int) $input['parent_id'];

    // D11: reject name / post_title early (before apply).
    if (array_key_exists('name', $input) || array_key_exists('post_title', $input)) {
        return wc_error('wc_invalid_input', __(
            'Variation names are auto-derived from the parent and attributes; do not pass `name`.',
            domain: 'novamira-pro',
        ));
    }

    // Validate parent.
    $parent = wc_validate_variable_parent($parent_id);
    if ($parent instanceof WP_Error) {
        return $parent;
    }

    // Validate attributes input.
    if (!isset($input['attributes']) || !is_array($input['attributes'])) {
        return wc_error('wc_invalid_input', __(
            '`attributes` is required and must be an array.',
            domain: 'novamira-pro',
        ));
    }

    $v = new WC_Product_Variation();
    $v->set_parent_id($parent_id);

    // Set default status.
    if (!array_key_exists('status', $input)) {
        $v->set_status('publish');
    }

    $err = wc_apply_variation_input($v, $input, own_id: 0);
    if ($err !== null) {
        return $err;
    }

    try {
        $id = $v->save();
        if (!$id) {
            return wc_error('wc_internal_error', __(
                'WooCommerce failed to save the variation.',
                domain: 'novamira-pro',
            ));
        }
    } catch (\Throwable) {
        return wc_sku_race_or_internal_variation($input, own_id: 0);
    }

    clean_post_cache($id);
    clean_post_cache($parent_id);
    \WC_Product_Variable::sync($parent_id);

    // Re-load after save to get computed fields.
    $saved = new WC_Product_Variation($id);

    // Handle is_default.
    if (isset($input['is_default']) && ($input['is_default'] === true || $input['is_default'] === 1)) {
        wc_set_default_variation($parent_id, $saved);
    }

    return ['success' => true, 'variation' => wc_variation_full_to_array($saved)];
}
