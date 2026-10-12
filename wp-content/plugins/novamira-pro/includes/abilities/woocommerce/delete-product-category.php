<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-delete-product-category', [
    'label' => __('Delete Product Category', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a WooCommerce product category. Requires `id`. '
        . 'Without `force` (default false): if any published products are assigned to this category, returns wc_term_has_products (HTTP 409) with `attached_count` in the response. Pass `force: true` to delete anyway; WooCommerce moves orphaned products to the Uncategorized default category. '
        . 'Child categories are promoted to the deleted category\'s parent (WP default hierarchy cascade). '
        . 'The default Uncategorized category (stored in WC option default_product_cat) cannot be deleted; returns wc_invalid_input. '
        . 'Possible errors: wc_inactive, wc_term_not_found, wc_invalid_input (default category), wc_term_has_products (without force), wc_internal_error. '
        . 'Response reflects the actual stored state at the time of deletion. WP hooks (`delete_term`, etc.) added by third-party plugins may perform additional side-effects.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Category id (required).'],
            'force' => [
                'type' => 'boolean',
                'description' => 'If true, deletes even when products are assigned. Orphaned products move to Uncategorized. Default false.',
                'default' => false,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'deleted_id' => ['type' => 'integer', 'description' => 'The id of the deleted term.'],
        ],
        'required' => ['success', 'deleted_id'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_delete_product_category',
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
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_delete_product_category(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $err = wc_strict_bool_check($input, ['force']);
    if ($err !== null) {
        return $err;
    }

    $taxonomy = 'product_cat';
    $term_id = (int) $input['id'];
    $force = isset($input['force']) && ($input['force'] === true || $input['force'] === 1);

    // Verify term exists
    $term = wc_post_to_term($term_id, $taxonomy);
    if ($term instanceof WP_Error) {
        return $term;
    }

    // D3: block default category
    $default_guard = wc_validate_default_category($term_id);
    if ($default_guard !== null) {
        return $default_guard;
    }

    // Check attached products
    $attached = wc_count_products_in_term($term_id, $taxonomy);
    if ($attached > 0 && !$force) {
        return new WP_Error(
            'wc_term_has_products',
            sprintf(
                /* translators: 1: attached product count */
                __(
                    '%1$d published product(s) are assigned to this category. Pass `force: true` to delete anyway (products will move to Uncategorized).',
                    domain: 'novamira-pro',
                ),
                $attached,
            ),
            ['status' => 409, 'attached_count' => $attached],
        );
    }

    // Re-validate right before mutation (closes TOCTOU window where default_product_cat
    // might have changed between the first check and the actual deletion).
    $default_guard = wc_validate_default_category($term_id);
    if ($default_guard !== null) {
        return $default_guard;
    }

    // Migrate orphaned products to the default product category before deletion.
    // default_product_cat stores the term_id of the "Uncategorized" category.
    // Passing `default` + `force_default: true` ensures WP reassigns products even
    // when they belong to other categories too (WP skips reassignment otherwise).
    // Edge case: if default_product_cat is 0 (unconfigured install), `default` is
    // omitted and products may end up with zero categories.
    $default_cat = (int) get_option(option: 'default_product_cat', default_value: 0);
    /** @var array{'default'?: int, 'force_default'?: bool} $delete_args */
    $delete_args = ['force_default' => true];
    if ($default_cat > 0) {
        $delete_args['default'] = $default_cat;
    }
    try {
        $result = \wp_delete_term($term_id, $taxonomy, $delete_args);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Category deletion failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    if ($result === false) {
        return wc_error('wc_internal_error', __('Category deletion failed.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'deleted_id' => $term_id];
}
