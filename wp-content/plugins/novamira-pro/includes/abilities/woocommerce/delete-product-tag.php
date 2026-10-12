<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-delete-product-tag', [
    'label' => __('Delete Product Tag', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a WooCommerce product tag. Requires `id`. '
        . 'Without `force` (default false): if any published products use this tag, returns wc_term_has_products (HTTP 409) with `attached_count`. Pass `force: true` to delete anyway; WooCommerce removes the tag from all products (non-hierarchical). '
        . 'Possible errors: wc_inactive, wc_term_not_found, wc_term_has_products (without force), wc_internal_error. '
        . 'Response reflects the actual stored state at the time of deletion. WP hooks (`delete_term`, etc.) added by third-party plugins may perform additional side-effects.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Tag id (required).'],
            'force' => [
                'type' => 'boolean',
                'description' => 'If true, deletes even when products use this tag. Tag is removed from those products. Default false.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_delete_product_tag',
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
function woocommerce_delete_product_tag(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $err = wc_strict_bool_check($input, ['force']);
    if ($err !== null) {
        return $err;
    }

    $taxonomy = 'product_tag';
    $term_id = (int) $input['id'];
    $force = isset($input['force']) && ($input['force'] === true || $input['force'] === 1);

    $term = wc_post_to_term($term_id, $taxonomy);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $attached = wc_count_products_in_term($term_id, $taxonomy);
    if ($attached > 0 && !$force) {
        return new WP_Error(
            'wc_term_has_products',
            sprintf(
                /* translators: 1: attached product count */
                __(
                    '%1$d published product(s) use this tag. Pass `force: true` to delete anyway (tag will be removed from those products).',
                    domain: 'novamira-pro',
                ),
                $attached,
            ),
            ['status' => 409, 'attached_count' => $attached],
        );
    }

    try {
        $result = \wp_delete_term($term_id, $taxonomy);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Tag deletion failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    if ($result === false) {
        return wc_error('wc_internal_error', __('Tag deletion failed.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'deleted_id' => $term_id];
}
