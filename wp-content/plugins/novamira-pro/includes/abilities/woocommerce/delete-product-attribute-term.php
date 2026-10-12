<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-delete-product-attribute-term', [
    'label' => __('Delete Product Attribute Term', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a single term (option) from a global product attribute. Required: `id` (term id). Optional: `force` (bool, default false). '
        . 'Without `force`: if any product or product variation references this term, returns wc_attribute_term_in_use (HTTP 409) with {products_using} in the error data. '
        . 'Pass `force: true` to delete anyway. Variations that referenced the deleted term silently break at the storefront level (the variation postmeta is not cleaned up). '
        . 'Possible errors: wc_inactive, wc_attribute_term_not_found, wc_attribute_term_in_use (without force), wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Term id (required).'],
            'force' => [
                'type' => 'boolean',
                'description' => 'If true, deletes even when products reference this term. Variation references break silently. Default false.',
                'default' => false,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean', 'description' => 'True when the term was deleted successfully.'],
            'deleted_id' => ['type' => 'integer', 'description' => 'The id of the deleted term.'],
            'variations_affected' => [
                'type' => 'integer',
                'description' => 'Number of products/variations that referenced this term (variation postmeta is not cleaned up after force-delete).',
            ],
        ],
        'required' => ['success', 'deleted_id', 'variations_affected'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_delete_product_attribute_term',
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
function woocommerce_delete_product_attribute_term(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $bool_err = wc_strict_bool_check($input, ['force']);
    if ($bool_err !== null) {
        return $bool_err;
    }

    $term_id = (int) $input['id'];
    $force = isset($input['force']) && ($input['force'] === true || $input['force'] === 1);

    $term = wc_post_to_attribute_term($term_id);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $taxonomy_slug = $term->taxonomy;
    $products_using = wc_count_products_using_attribute_term($term_id, $taxonomy_slug);

    if ($products_using > 0 && !$force) {
        return new WP_Error(
            'wc_attribute_term_in_use',
            sprintf(
                /* translators: products count */
                __(
                    '%d product(s) or variation(s) reference this term. Pass `force: true` to delete anyway (variation references break silently).',
                    domain: 'novamira-pro',
                ),
                $products_using,
            ),
            ['status' => 409, 'products_using' => $products_using],
        );
    }

    try {
        $result = \wp_delete_term($term_id, $taxonomy_slug);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Term deletion failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    if ($result === false) {
        return wc_error('wc_internal_error', __('Attribute term deletion failed.', domain: 'novamira-pro'));
    }

    return [
        'success' => true,
        'deleted_id' => $term_id,
        'variations_affected' => $products_using,
    ];
}
