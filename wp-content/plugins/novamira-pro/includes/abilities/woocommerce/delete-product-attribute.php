<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-delete-product-attribute', [
    'label' => __('Delete Product Attribute', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a global product attribute definition. Required: `id`. Optional: `force` (bool, default false). '
        . 'Without `force`: if the attribute has registered terms OR is used on any product, returns wc_attribute_in_use (HTTP 409) with {term_count, products_using} in the error data. '
        . 'Pass `force: true` to cascade: all terms under this attribute are deleted, the attribute row is removed from the attributes table, and any product that references the attribute taxonomy will have silent orphan postmeta left behind. '
        . 'This is the highest-blast-radius operation in the WooCommerce surface. Verify counts before forcing. '
        . 'Possible errors: wc_inactive, wc_attribute_not_found, wc_attribute_in_use (without force), wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Attribute id (required).'],
            'force' => [
                'type' => 'boolean',
                'description' => 'If true, deletes even when terms or products are attached. Cascade removes all terms. Default false.',
                'default' => false,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean', 'description' => 'True when the attribute was deleted successfully.'],
            'deleted_id' => ['type' => 'integer', 'description' => 'The id of the deleted attribute.'],
            'variations_affected' => [
                'type' => 'integer',
                'description' => 'Number of products that used this attribute as a variation axis (may have orphaned postmeta after force-delete).',
            ],
            'terms_affected' => [
                'type' => 'integer',
                'description' => 'Number of terms that were cascade-deleted along with the attribute.',
            ],
        ],
        'required' => ['success', 'deleted_id', 'variations_affected', 'terms_affected'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_delete_product_attribute',
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
function woocommerce_delete_product_attribute(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $bool_err = wc_strict_bool_check($input, ['force']);
    if ($bool_err !== null) {
        return $bool_err;
    }

    $attr_id = (int) $input['id'];
    $force = isset($input['force']) && ($input['force'] === true || $input['force'] === 1);

    $attr = \wc_get_attribute($attr_id);
    if (!is_object($attr)) {
        return wc_error('wc_attribute_not_found', sprintf(
            /* translators: attribute id */
            __('Attribute with id %d not found.', domain: 'novamira-pro'),
            $attr_id,
        ));
    }

    $taxonomy_slug = wc_attr_object_to_taxonomy_slug($attr);
    wc_ensure_attribute_taxonomy_registered($taxonomy_slug);

    /** @var int|string|\WP_Error $count_raw */
    $count_raw = wp_count_terms(['taxonomy' => $taxonomy_slug, 'hide_empty' => false]);
    $term_count = wc_wp_count_to_int($count_raw);

    // Count products using this attribute
    $products_using = wc_count_products_using_attribute($attr_id);

    if (($term_count > 0 || $products_using > 0) && !$force) {
        return new WP_Error(
            'wc_attribute_in_use',
            sprintf(
                /* translators: 1: term count, 2: products count */
                __(
                    'Attribute has %1$d term(s) and is used by %2$d product(s). Pass `force: true` to cascade-delete.',
                    domain: 'novamira-pro',
                ),
                $term_count,
                $products_using,
            ),
            ['status' => 409, 'term_count' => $term_count, 'products_using' => $products_using],
        );
    }

    try {
        $result = \wc_delete_attribute($attr_id);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Attribute deletion failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    if ($result === false) {
        return wc_error('wc_internal_error', __('Attribute deletion failed.', domain: 'novamira-pro'));
    }

    return [
        'success' => true,
        'deleted_id' => $attr_id,
        'variations_affected' => $products_using,
        'terms_affected' => $term_count,
    ];
}
