<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WC_Product_Simple;
use WC_Product_Variable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-create-product', [
    'label' => __('Create Product', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new WooCommerce product. Supported types: `simple` and `variable`. Grouped and external types arrive in a future release. Returns the full product object on success. WP-native field aliases are accepted: `post_title` = `name`, `post_name` = `slug`, `post_status` = `status`, `post_content` = `description`, `post_excerpt` = `short_description`. Required: `name` (or `post_title`). For `type=variable`: at least one attribute must have `variation: true`; stock fields (`manage_stock`, `stock_quantity`, `stock_status`) are rejected (stock lives on variations). SKU must be unique across the catalog; duplicate returns wc_sku_exists (HTTP 409). Image fields (`featured_image_id`, `gallery_image_ids`) accept media-library attachment ids only. `status` must be one of publish, draft, pending, private. Prices must be non-negative numeric strings. When `manage_stock: true` on a simple product, `stock_quantity` is required. WordPress strips single backslash characters from `description` on save; use double backslashes if you need a literal backslash. Note: if a third-party plugin hooks `save_post_product` and throws after the product is inserted, the ability returns an error but the product may remain in the database. Brands are read-only; the input schema rejects a `brands` field. Possible errors: wc_invalid_input, wc_sku_exists, wc_not_found (for missing category/tag/brand slug), wc_attachment_not_found, wc_post_type_mismatch, wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Product name (required).'],
            'post_title' => ['type' => 'string', 'description' => 'WordPress-native alias for `name`.'],
            'slug' => ['type' => 'string', 'description' => 'URL slug.'],
            'post_name' => ['type' => 'string', 'description' => 'WordPress-native alias for `slug`.'],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft', 'pending', 'private'],
                'description' => 'Product status. Defaults to publish.',
            ],
            'post_status' => ['type' => 'string', 'description' => 'WordPress-native alias for `status`.'],
            'description' => ['type' => 'string', 'description' => 'Full product description.'],
            'post_content' => ['type' => 'string', 'description' => 'WordPress-native alias for `description`.'],
            'short_description' => ['type' => 'string', 'description' => 'Short product description (excerpt).'],
            'post_excerpt' => ['type' => 'string', 'description' => 'WordPress-native alias for `short_description`.'],
            'type' => [
                'type' => 'string',
                'enum' => ['simple', 'variable'],
                'description' => 'Product type. `simple` or `variable`. For `variable`, declare at least one attribute with variation=true and do not pass stock fields (stock lives on variations). Grouped and external types arrive in a future release.',
            ],
            'sku' => ['type' => 'string', 'description' => 'Stock-keeping unit. Must be unique.'],
            'regular_price' => [
                'type' => ['string', 'null'],
                'description' => 'Regular price (numeric string, e.g. "19.99"). Must be non-negative. Pass null to clear.',
            ],
            'sale_price' => [
                'type' => ['string', 'null'],
                'description' => 'Sale price (numeric string). Pass null or empty string to clear. Must be non-negative.',
            ],
            'featured' => ['type' => 'boolean', 'description' => 'Whether the product is featured.'],
            'manage_stock' => ['type' => 'boolean', 'description' => 'Enable per-product stock management.'],
            'stock_quantity' => ['type' => 'integer', 'description' => 'Stock quantity (requires manage_stock: true).'],
            'stock_status' => [
                'type' => 'string',
                'enum' => ['instock', 'outofstock', 'onbackorder'],
                'description' => 'Stock status.',
            ],
            'virtual' => ['type' => 'boolean', 'description' => 'Whether the product is virtual (no shipping).'],
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
            'featured_image_id' => ['type' => 'integer', 'description' => 'Attachment id for the featured image.'],
            'gallery_image_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => 'Attachment ids for gallery images.',
            ],
            'attributes' => [
                'type' => 'array',
                'description' => 'Product attributes. Each entry: {name, options[], visible?, variation?} for local attributes or {taxonomy_slug, options[], visible?, variation?} for global (pa_*) attributes. Set variation=true to make the attribute usable for variations. For type=variable, at least one attribute must have variation=true.',
            ],
            'shipping_class' => [
                'type' => 'string',
                'description' => 'Shipping class slug, e.g. "heavy-items". Assign by slug or use shipping_class_id.',
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
            'product' => ['type' => 'object', 'description' => 'Full product object (same shape as get-product).'],
        ],
        'required' => ['success', 'product'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_create_product',
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
 * Guard for variable product creation: D2 (variation-enabled attribute) + D5 (no stock fields).
 *
 * @param array<string, mixed> $input
 */
function wc_guard_variable_create_input(array $input): ?WP_Error
{
    // D5: reject stock fields on variable parent.
    $stock_fields = ['manage_stock', 'stock_quantity', 'stock_status'];
    foreach ($stock_fields as $sf) {
        if (array_key_exists($sf, $input)) {
            return wc_error('wc_invalid_input', __(
                'Stock is managed per variation on variable products. Do not pass manage_stock, stock_quantity, or stock_status on the parent.',
                domain: 'novamira-pro',
            ));
        }
    }
    // F9: reject price fields on variable parent.
    foreach (['regular_price', 'sale_price'] as $pf) {
        if (array_key_exists($pf, $input)) {
            return wc_error('wc_invalid_input', __(
                'Prices are managed per variation on variable products. Set prices on each variation, not on the parent.',
                domain: 'novamira-pro',
            ));
        }
    }
    // D2: must have at least one attribute with variation: true.
    if (!isset($input['attributes']) || !is_array($input['attributes'])) {
        return wc_error('wc_invalid_input', __(
            'Variable products require at least one variation-enabled attribute.',
            domain: 'novamira-pro',
        ));
    }
    /** @var array<array-key, array<string, mixed>> $attrs_list */
    $attrs_list = array_values(array_filter($input['attributes'], static fn(mixed $v): bool => is_array($v)));
    $variation_attrs = array_filter(
        $attrs_list,
        static fn(array $a): bool => isset($a['variation']) && ($a['variation'] === true || $a['variation'] === 1),
    );
    if ($variation_attrs === []) {
        return wc_error('wc_invalid_input', __(
            'Variable products require at least one variation-enabled attribute.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_create_product(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $input = wc_normalize_product_input($input);

    if (!isset($input['name'])) {
        return wc_error('wc_invalid_input', __('`name` is required.', domain: 'novamira-pro'));
    }

    if (!is_string($input['name']) && !is_numeric($input['name'])) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: actual PHP type */
            __('`name` must be a string (got %1$s).', domain: 'novamira-pro'),
            gettype($input['name']),
        ));
    }

    if (trim((string) $input['name']) === '') {
        return wc_error('wc_invalid_input', __('`name` is required.', domain: 'novamira-pro'));
    }

    /** @var mixed $raw_type */
    $raw_type = $input['type'] ?? 'simple';
    $type = (string) $raw_type;
    if (!in_array($type, ['simple', 'variable'], strict: true)) {
        return wc_error('wc_invalid_input', __(
            'Only type=simple and type=variable are supported. Grouped and external product types arrive in a future release.',
            domain: 'novamira-pro',
        ));
    }

    // D2: variable requires at least one variation-enabled attribute.
    if ($type === 'variable') {
        $err = wc_guard_variable_create_input($input);
        if ($err !== null) {
            return $err;
        }
    }

    $product = $type === 'variable' ? new WC_Product_Variable() : new WC_Product_Simple();

    $err = wc_apply_product_input_for_create($product, $input);
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
        return wc_sku_race_or_internal($input, own_id: 0);
    }
}
