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

wp_register_ability('novamira/woocommerce-edit-product-variation', [
    'label' => __('Edit Product Variation', domain: 'novamira-pro'),
    'description' => __(
        'Partially updates (PATCH semantics) a product variation. Only fields present in the input are changed; absent fields stay as-is. Required: `id`. Variation name is auto-derived and cannot be set (`name` is rejected). When the `attributes` field is present, it fully replaces the variation\'s attribute combination. List every attribute the variation should carry, not just the ones being changed. Price fields must be non-negative numeric strings; pass null to clear `regular_price` or `sale_price`. Note: if you lower `regular_price` below the current `sale_price`, WooCommerce automatically clears `sale_price`. To change both, set them together. Pass `image_id: 0` to clear the variation image. SKU must be unique if provided. `is_default: true` marks this variation as the storefront default (replaces any prior default). `is_default: false` clears the default if this variation is currently default. When you set `manage_stock: false`, WooCommerce clears `stock_quantity`. To re-enable stock management, re-supply `stock_quantity`. WP-native alias: `post_excerpt` = `description`. WordPress strips single backslash characters from `description` on save; use double backslashes if you need a literal backslash. Returns the full variation object after the update. Concurrent edits on the same variation follow last-write-wins; MySQL row locking guarantees no corruption. Possible errors: wc_invalid_input, wc_not_found (variation does not exist), wc_attribute_not_variable, wc_option_not_on_parent, wc_sku_exists, wc_attachment_not_found, wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Variation post id (required).'],
            'attributes' => [
                'type' => 'array',
                'description' => 'When present, fully replaces the variation\'s attribute combination. List every attribute the variation should carry.',
                'items' => ['type' => 'object'],
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
                'description' => 'Variation status.',
            ],
            'is_default' => [
                'type' => 'boolean',
                'description' => 'True to mark as storefront default (replaces any existing default). False to clear if currently default.',
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
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'variation' => ['type' => 'object', 'description' => 'Full variation object after the update.'],
        ],
        'required' => ['success', 'variation'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_edit_product_variation',
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
 * Apply input to a loaded variation, save it, sync parent, and handle is_default.
 * Extracted to keep woocommerce_edit_product_variation within cyclomatic-complexity limits.
 *
 * @param array<string, mixed> $input  Already-stripped of 'id'.
 * @return array<string, mixed>|WP_Error
 */
function wc_apply_save_variation(WC_Product_Variation $v, array $input, int $own_id, int $parent_id): array|WP_Error
{
    $err = wc_apply_variation_input($v, $input, own_id: $own_id);
    if ($err !== null) {
        return $err;
    }

    try {
        $saved_id = $v->save();
        if (!$saved_id) {
            return wc_error('wc_internal_error', __(
                'WooCommerce failed to save the variation.',
                domain: 'novamira-pro',
            ));
        }
    } catch (\Throwable) {
        return wc_sku_race_or_internal_variation($input, own_id: $own_id);
    }

    clean_post_cache($saved_id);
    clean_post_cache($parent_id);
    \WC_Product_Variable::sync($parent_id);

    $saved = new WC_Product_Variation($saved_id);
    wc_handle_is_default($input, $parent_id, $saved);

    return ['success' => true, 'variation' => wc_variation_full_to_array($saved)];
}

/**
 * Set or clear the storefront default variation based on is_default input.
 *
 * @param array<string, mixed> $input
 */
function wc_handle_is_default(array $input, int $parent_id, WC_Product_Variation $saved): void
{
    if (!array_key_exists('is_default', $input)) {
        return;
    }
    if ($input['is_default'] === true || $input['is_default'] === 1) {
        wc_set_default_variation($parent_id, $saved);
        return;
    }
    if (($input['is_default'] === false || $input['is_default'] === 0) && wc_is_default_variation($parent_id, $saved)) {
        wc_clear_default_variation($parent_id);
    }
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
/**
 * Per-process re-entrancy guard for woocommerce_edit_product_variation.
 * Keyed by variation id; set before save, cleared in finally.
 *
 * @var array<int, true>
 */
$NOVAMIRA_EDIT_VARIATION_IN_PROGRESS = [];

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_edit_product_variation(array $input): array|WP_Error
{
    global $NOVAMIRA_EDIT_VARIATION_IN_PROGRESS;

    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    if (!isset($input['id']) || !is_numeric($input['id'])) {
        return wc_error('wc_invalid_input', __('`id` is required.', domain: 'novamira-pro'));
    }

    $id = (int) $input['id'];

    if (isset($NOVAMIRA_EDIT_VARIATION_IN_PROGRESS[$id])) {
        return wc_error('wc_internal_error', __(
            'Recursive variation edit detected; aborting.',
            domain: 'novamira-pro',
        ));
    }

    // D11: reject name / post_title early (before apply).
    if (array_key_exists('name', $input) || array_key_exists('post_title', $input)) {
        return wc_error('wc_invalid_input', __(
            'Variation names are auto-derived from the parent and attributes; do not pass `name`.',
            domain: 'novamira-pro',
        ));
    }

    $v = wc_post_to_variation($id);
    if ($v instanceof WP_Error) {
        return $v;
    }

    $parent_id = $v->get_parent_id();
    unset($input['id']);

    /** @var array<int, true> $NOVAMIRA_EDIT_VARIATION_IN_PROGRESS */
    $NOVAMIRA_EDIT_VARIATION_IN_PROGRESS[$id] = true;
    try {
        return wc_apply_save_variation($v, $input, own_id: $id, parent_id: $parent_id);
    } finally {
        unset($NOVAMIRA_EDIT_VARIATION_IN_PROGRESS[$id]);
    }
}
