<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-delete-product-shipping-class', [
    'label' => __('Delete Product Shipping Class', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a WooCommerce shipping class. Required: `id`. Optional: `force` (bool, default false). '
        . 'Without `force`: if any published product is assigned this class, returns wc_shipping_class_in_use (HTTP 409) with {products_using} in the error data. '
        . 'Pass `force: true` to delete anyway; WP removes the class from all assigned products (standard taxonomy delete cascade). '
        . 'Possible errors: wc_inactive, wc_term_not_found, wc_shipping_class_in_use (without force), wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Shipping class id (required).'],
            'force' => [
                'type' => 'boolean',
                'description' => 'If true, deletes even when products are assigned. Class is removed from all products. Default false.',
                'default' => false,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => [
                'type' => 'boolean',
                'description' => 'True when the shipping class was deleted successfully.',
            ],
            'deleted_id' => ['type' => 'integer', 'description' => 'The id of the deleted shipping class term.'],
            'products_affected' => [
                'type' => 'integer',
                'description' => 'Number of products that had this shipping class assigned at the time of deletion.',
            ],
        ],
        'required' => ['success', 'deleted_id', 'products_affected'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_delete_product_shipping_class',
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
function woocommerce_delete_product_shipping_class(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $bool_err = wc_strict_bool_check($input, ['force']);
    if ($bool_err !== null) {
        return $bool_err;
    }

    $taxonomy = 'product_shipping_class';
    $term_id = (int) $input['id'];
    $force = isset($input['force']) && ($input['force'] === true || $input['force'] === 1);

    $term = wc_post_to_term($term_id, $taxonomy);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $products_using = wc_count_products_in_term($term_id, $taxonomy);

    if ($products_using > 0 && !$force) {
        return new WP_Error(
            'wc_shipping_class_in_use',
            sprintf(
                /* translators: products count */
                __(
                    '%d product(s) are assigned to this shipping class. Pass `force: true` to delete anyway (class removed from all products).',
                    domain: 'novamira-pro',
                ),
                $products_using,
            ),
            ['status' => 409, 'products_using' => $products_using],
        );
    }

    try {
        $result = \wp_delete_term($term_id, $taxonomy);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Shipping class deletion failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    if ($result === false) {
        return wc_error('wc_internal_error', __('Shipping class deletion failed.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'deleted_id' => $term_id, 'products_affected' => $products_using];
}
