<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WC_Product_Variable;
use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-delete-product', [
    'label' => __('Delete Product', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a WooCommerce product. Requires `id` OR `slug`. Default behaviour is soft delete (product moves to trash; `status` returns `trash` in the response). Pass `force: true` for permanent deletion (returns `deleted_id` instead of the product object). Soft-deleting an already-trashed product is an idempotent no-op. Force-deleting an already-trashed product permanently removes it. For variable products, `force: true` also permanently deletes all child variations before removing the parent. On a variable product with many variations, soft-delete may take several seconds while WooCommerce recomputes aggregate fields on the parent. Force-delete uses suspended cache invalidation to stay fast. Possible errors: wc_invalid_input, wc_not_found, wc_post_type_mismatch, wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Product id. Provide id OR slug.'],
            'slug' => ['type' => 'string', 'description' => 'Product slug. Provide id OR slug.'],
            'force' => [
                'type' => 'boolean',
                'description' => 'If true, permanently deletes the product. Defaults to false (soft delete / trash).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'product' => [
                'type' => 'object',
                'description' => 'Product object after soft delete (status=trash). Present only on soft delete.',
            ],
            'deleted_id' => [
                'type' => 'integer',
                'description' => 'Id of the permanently deleted product. Present only when force=true.',
            ],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_delete_product',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Resolve a product post allowing trashed status (for delete operations).
 * Returns WP_Error when not found or not a product post.
 */
function wc_resolve_post_for_delete(int|string $key): WP_Post|WP_Error
{
    if (is_int($key) || ctype_digit($key)) {
        /** @var WP_Post|WP_Post[]|null $raw */
        $raw = get_post((int) $key);
        $post = $raw instanceof WP_Post ? $raw : null;
        if ($post instanceof WP_Post) {
            return $post;
        }
        return wc_error('wc_not_found', sprintf(
            __('Product not found for id %d.', domain: 'novamira-pro'),
            (int) $key,
        ));
    }

    // Slug lookup: trashed products may have __trashed suffix — try both.
    $slug = $key;
    /** @var WP_Post|null $post */
    $post = get_page_by_path(page_path: $slug, post_type: 'product') ?? get_page_by_path(
        page_path: $slug . '__trashed',
        post_type: 'product',
    );

    if (!$post instanceof WP_Post) {
        return wc_error('wc_not_found', sprintf(__('Product not found for slug `%s`.', domain: 'novamira-pro'), $slug));
    }
    return $post;
}

/**
 * Force-delete all variation children of a variable product before deleting the parent.
 * WC's own trash path is not reliable across versions; explicit cascade is safer.
 * Uses wp_suspend_cache_invalidation to batch the per-post cache flushes into a single
 * clean_post_cache pass, avoiding O(N x cache-invalidation) overhead on large variation sets.
 */
function wc_force_delete_variations(\WC_Product $product): void
{
    if (!$product instanceof WC_Product_Variable) {
        return;
    }
    /** @var array<array-key, \WP_Post> $children */
    $children = get_posts([
        'post_parent' => $product->get_id(),
        'post_type' => 'product_variation',
        'numberposts' => -1,
        'post_status' => 'any',
    ]);
    $variation_ids = array_map(static fn(\WP_Post $c): int => $c->ID, $children);
    $prev = \wp_suspend_cache_invalidation(true);
    try {
        foreach ($variation_ids as $vid) {
            \wp_delete_post(post_id: $vid, force_delete: true);
        }
    } finally {
        \wp_suspend_cache_invalidation($prev);
        clean_post_cache($product->get_id());
        foreach ($variation_ids as $vid) {
            clean_post_cache($vid);
        }
    }
}

/**
 * Permanently (force) delete a product and return the API response.
 *
 * @return array<string, mixed>|WP_Error
 */
function wc_force_delete_product(\WC_Product $product): array|WP_Error
{
    $product_id = $product->get_id();
    wc_force_delete_variations($product);
    try {
        $product->delete(true);
    } catch (\Throwable) {
        return wc_error('wc_internal_error', __('Product deletion failed unexpectedly.', domain: 'novamira-pro'));
    }
    clean_post_cache($product_id);
    return ['success' => true, 'deleted_id' => $product_id];
}

/**
 * Soft-delete a product (move to trash) and return the API response.
 *
 * @return array<string, mixed>|WP_Error
 */
function wc_soft_delete_product(\WC_Product $product): array|WP_Error
{
    $product_id = $product->get_id();
    try {
        $ok = $product->delete(false);
    } catch (\Throwable) {
        return wc_error('wc_internal_error', __('Product deletion failed unexpectedly.', domain: 'novamira-pro'));
    }
    if (!$ok) {
        return wc_error('wc_internal_error', __('Soft delete failed.', domain: 'novamira-pro'));
    }
    clean_post_cache($product_id);
    $product_after = \wc_get_product($product_id);
    if (!$product_after instanceof \WC_Product) {
        // Post was fully deleted even without force=true; treat as success.
        return ['success' => true, 'deleted_id' => $product_id];
    }
    return ['success' => true, 'product' => wc_full_product($product_after)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_delete_product(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $lookup = wc_resolve_product_key($input);
    if ($lookup instanceof WP_Error) {
        return $lookup;
    }

    $post = wc_resolve_post_for_delete($lookup);
    if ($post instanceof WP_Error) {
        return $post;
    }

    if ($post->post_type !== 'product') {
        return wc_error('wc_not_found', __('Product not found.', domain: 'novamira-pro'));
    }

    $product = \wc_get_product($post->ID);
    if (!$product instanceof \WC_Product) {
        return wc_error('wc_not_found', __('Product not found.', domain: 'novamira-pro'));
    }

    $force = isset($input['force']) && ($input['force'] === true || $input['force'] === 1);

    // Idempotent: soft delete on already-trashed product returns current state.
    if (!$force && $post->post_status === 'trash') {
        return ['success' => true, 'product' => wc_full_product($product)];
    }

    if ($force) {
        return wc_force_delete_product($product);
    }
    return wc_soft_delete_product($product);
}
