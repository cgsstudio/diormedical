<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-edit-product', [
    'label' => __('Edit Product', domain: 'novamira-pro'),
    'description' => __(
        'Partially updates (PATCH semantics) a WooCommerce product. Only fields present in the input are changed; absent fields stay as-is. Requires `id` OR `slug` to identify the product. WP-native field aliases are accepted: `post_title` = `name`, `post_name` = `slug`, `post_status` = `status`, `post_content` = `description`, `post_excerpt` = `short_description`. Changing product type is not supported; create a new product with the desired type instead. SKU must remain unique; collisions return wc_sku_exists (HTTP 409). `status` must be one of publish, draft, pending, private. Prices must be non-negative numeric strings; pass null to clear sale_price. Pass `featured_image_id: 0` to clear the featured image. Trashed attachments are rejected. Trashed products cannot be restored via edit-product (the `wc_not_found` guard rejects trashed posts). To re-publish a trashed product, force-delete it first and re-create, or use the WP admin "Restore" link. Untrash via ability arrives in a future sprint. WordPress strips single backslash characters from `description` on save; use double backslashes if you need a literal backslash. Brand assignment is read-only via this ability; agents can read but not write the `brands` field. Direct WP admin or wp_set_object_terms can assign brands. Passing `categories: []` removes all explicit category assignments; WooCommerce then moves the product into the Uncategorized default category, so agents cannot intentionally leave a product with zero categories. Possible errors: wc_invalid_input, wc_not_found, wc_sku_exists, wc_attachment_not_found, wc_post_type_mismatch, wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Product id. Provide id OR slug.'],
            'slug' => ['type' => 'string', 'description' => 'Product slug. Provide id OR slug.'],
            'name' => ['type' => 'string', 'description' => 'Product name.'],
            'post_title' => ['type' => 'string', 'description' => 'WordPress-native alias for `name`.'],
            'post_name' => ['type' => 'string', 'description' => 'WordPress-native alias for `slug`.'],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft', 'pending', 'private'],
                'description' => 'Product status.',
            ],
            'post_status' => ['type' => 'string', 'description' => 'WordPress-native alias for `status`.'],
            'description' => ['type' => 'string', 'description' => 'Full product description.'],
            'post_content' => ['type' => 'string', 'description' => 'WordPress-native alias for `description`.'],
            'short_description' => ['type' => 'string', 'description' => 'Short product description.'],
            'post_excerpt' => ['type' => 'string', 'description' => 'WordPress-native alias for `short_description`.'],
            'type' => ['type' => 'string', 'description' => 'Product type (cannot be changed via edit-product).'],
            'sku' => ['type' => 'string', 'description' => 'Stock-keeping unit. Must be unique.'],
            'regular_price' => [
                'type' => ['string', 'null'],
                'description' => 'Regular price (numeric string). Must be non-negative. Pass null to clear.',
            ],
            'sale_price' => [
                'type' => ['string', 'null'],
                'description' => 'Sale price (numeric string). Pass null or empty string to clear. Must be non-negative.',
            ],
            'featured' => ['type' => 'boolean', 'description' => 'Whether the product is featured.'],
            'manage_stock' => ['type' => 'boolean', 'description' => 'Enable per-product stock management.'],
            'stock_quantity' => ['type' => 'integer', 'description' => 'Stock quantity.'],
            'stock_status' => [
                'type' => 'string',
                'enum' => ['instock', 'outofstock', 'onbackorder'],
                'description' => 'Stock status.',
            ],
            'virtual' => ['type' => 'boolean', 'description' => 'Whether the product is virtual.'],
            'downloadable' => ['type' => 'boolean', 'description' => 'Whether the product is downloadable.'],
            'length' => ['type' => 'string', 'description' => 'Product length.'],
            'width' => ['type' => 'string', 'description' => 'Product width.'],
            'height' => ['type' => 'string', 'description' => 'Product height.'],
            'weight' => ['type' => 'string', 'description' => 'Product weight.'],
            'categories' => [
                'type' => 'array',
                'items' => ['oneOf' => [['type' => 'string'], ['type' => 'integer']]],
                'description' => 'Category slugs or ids.',
            ],
            'tags' => [
                'type' => 'array',
                'items' => ['oneOf' => [['type' => 'string'], ['type' => 'integer']]],
                'description' => 'Tag slugs or ids.',
            ],
            'featured_image_id' => [
                'type' => 'integer',
                'description' => 'Attachment id for the featured image. Pass 0 to clear the featured image.',
            ],
            'gallery_image_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => 'Attachment ids for gallery images.',
            ],
            'attributes' => ['type' => 'array', 'description' => 'Product attributes. Replaces existing attributes.'],
            'shipping_class' => [
                'type' => 'string',
                'description' => 'Shipping class slug, e.g. "heavy-items". Assign by slug or use shipping_class_id. Pass empty string to clear.',
            ],
            'shipping_class_id' => [
                'type' => 'integer',
                'description' => 'Shipping class term id. Alternative to shipping_class (slug). Pass 0 to clear.',
            ],
            'meta_data' => [
                'type' => 'array',
                'description' => 'Custom meta entries: [{key, value}]. Underscore-prefixed keys are rejected.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'product' => ['type' => 'object', 'description' => 'Full product object after the update.'],
        ],
        'required' => ['success', 'product'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_edit_product',
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
 * Guards specific to editing a variable product parent: stock fields, price fields,
 * and attribute edits that would orphan existing variations.
 *
 * @param array<string, mixed> $input
 */
function wc_guard_variable_edit_input(\WC_Product $product, array $input): ?WP_Error
{
    // D5: reject stock fields on variable parents.
    foreach (['manage_stock', 'stock_quantity', 'stock_status'] as $sf) {
        if (array_key_exists($sf, $input)) {
            return wc_error('wc_invalid_input', __(
                'Stock is managed per variation on variable products. Do not pass manage_stock, stock_quantity, or stock_status on the parent.',
                domain: 'novamira-pro',
            ));
        }
    }
    // F9: reject price fields on variable parents.
    foreach (['regular_price', 'sale_price'] as $pf) {
        if (array_key_exists($pf, $input)) {
            return wc_error('wc_invalid_input', __(
                'Prices are managed per variation on variable products. Set prices on each variation, not on the parent.',
                domain: 'novamira-pro',
            ));
        }
    }
    // F5: guard attribute edits that would orphan existing variations.
    if (!isset($input['attributes']) || !is_array($input['attributes'])) {
        return null;
    }
    /** @var array<array-key, array<string, mixed>> $attrs_input */
    $attrs_input = array_values(array_filter($input['attributes'], static fn(mixed $v): bool => is_array($v)));
    return wc_guard_variable_attribute_edit($product, $attrs_input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_edit_product(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $key = wc_resolve_product_key($input);
    if ($key instanceof WP_Error) {
        return $key;
    }

    $product = wc_product_or_error($key);
    if ($product instanceof WP_Error) {
        return $product;
    }

    unset($input['id'], $input['slug']);
    $input = wc_normalize_product_input($input);

    // Reject type changes.
    if (isset($input['type']) && $input['type'] !== $product->get_type()) {
        return wc_error('wc_invalid_input', __(
            'Changing product type is not supported in edit-product. Create a new product with the desired type.',
            domain: 'novamira-pro',
        ));
    }
    unset($input['type']);

    if ($product->get_type() === 'variable') {
        $err = wc_guard_variable_edit_input($product, $input);
        if ($err !== null) {
            return $err;
        }
    }

    $err = wc_apply_product_input_for_edit($product, $input);
    if ($err !== null) {
        return $err;
    }

    try {
        $id = $product->save();
        if (!$id) {
            return wc_error('wc_internal_error', __('WooCommerce failed to save the product.', domain: 'novamira-pro'));
        }
        clean_post_cache($id);
        $saved = \wc_get_product($id);
        if (!$saved instanceof \WC_Product) {
            return wc_error('wc_internal_error', __('Product saved but could not be re-read.', domain: 'novamira-pro'));
        }
        return ['success' => true, 'product' => wc_full_product($saved)];
    } catch (\Throwable $e) {
        // Race window: remap to 409 if a concurrent request just claimed the SKU.
        return wc_sku_race_or_internal($input, $product->get_id());
    }
}
