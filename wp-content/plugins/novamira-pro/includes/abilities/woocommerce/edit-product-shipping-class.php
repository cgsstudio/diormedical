<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-edit-product-shipping-class', [
    'label' => __('Edit Product Shipping Class', domain: 'novamira-pro'),
    'description' => __(
        'Partially updates an existing shipping class (PATCH semantics). Required: `id`. Optional: `name`, `slug`, `description`. '
        . 'Only fields you pass change; omitted fields stay as-is. '
        . 'Returns the full updated shipping class object. '
        . 'Possible errors: wc_inactive, wc_term_not_found, wc_invalid_input, wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Shipping class id (required).'],
            'name' => ['type' => 'string', 'description' => 'New display label.'],
            'slug' => ['type' => ['string', 'null'], 'description' => 'New URL slug.'],
            'description' => ['type' => ['string', 'null'], 'description' => 'New description.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => [
                'type' => 'boolean',
                'description' => 'True when the shipping class was updated successfully.',
            ],
            'shipping_class' => [
                'type' => 'object',
                'description' => 'The updated shipping class object.',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Shipping class term id.'],
                    'name' => ['type' => 'string', 'description' => 'Display label.'],
                    'slug' => ['type' => 'string', 'description' => 'URL slug.'],
                    'description' => ['type' => 'string', 'description' => 'Shipping class description.'],
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of products assigned to this shipping class.',
                    ],
                ],
                'required' => ['id', 'name', 'slug', 'description', 'count'],
            ],
        ],
        'required' => ['success', 'shipping_class'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_edit_product_shipping_class',
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
function woocommerce_edit_product_shipping_class(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $taxonomy = 'product_shipping_class';
    $term_id = (int) $input['id'];

    $term = wc_post_to_term($term_id, $taxonomy);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $term_args = wc_apply_term_input(term_id: $term_id, input: $input, taxonomy: $taxonomy);
    if ($term_args instanceof WP_Error) {
        return $term_args;
    }

    if ($term_args === []) {
        return ['success' => true, 'shipping_class' => wc_term_to_array($term, $taxonomy)];
    }

    $update_args = [];
    if (isset($term_args['name'])) {
        $update_args['name'] = (string) $term_args['name'];
    }
    if (isset($term_args['slug'])) {
        $update_args['slug'] = (string) $term_args['slug'];
    }
    if (isset($term_args['description'])) {
        $update_args['description'] = (string) $term_args['description'];
    }

    try {
        /** @var array{term_id: int, term_taxonomy_id: int}|WP_Error $result */
        // @mago-expect analysis:possibly-invalid-argument
        $result = \wp_update_term($term_id, $taxonomy, $update_args);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Shipping class update failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    $fresh = get_term($term_id, $taxonomy);
    if (!$fresh instanceof WP_Term) {
        return wc_error('wc_internal_error', __(
            'Failed to reload shipping class after update.',
            domain: 'novamira-pro',
        ));
    }

    return ['success' => true, 'shipping_class' => wc_term_to_array($fresh, $taxonomy)];
}
