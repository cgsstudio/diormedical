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

wp_register_ability('novamira/woocommerce-create-product-shipping-class', [
    'label' => __('Create Product Shipping Class', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new WooCommerce shipping class (taxonomy: product_shipping_class). '
        . 'Required: `name`. Optional: `slug` (auto-generated from name), `description`. '
        . 'Returns the full shipping class object on success. '
        . 'Possible errors: wc_inactive, wc_invalid_input (name empty/too long, description too long), wc_term_exists (duplicate), wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Shipping class name (required).'],
            'slug' => ['type' => ['string', 'null'], 'description' => 'URL slug. Auto-generated from name if omitted.'],
            'description' => ['type' => ['string', 'null'], 'description' => 'Shipping class description.'],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => [
                'type' => 'boolean',
                'description' => 'True when the shipping class was created successfully.',
            ],
            'shipping_class' => [
                'type' => 'object',
                'description' => 'The newly created shipping class object.',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Shipping class term id.'],
                    'name' => ['type' => 'string', 'description' => 'Display label, e.g. "Heavy Items".'],
                    'slug' => ['type' => 'string', 'description' => 'URL slug, e.g. "heavy-items".'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_create_product_shipping_class',
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
function woocommerce_create_product_shipping_class(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $name_err = wc_require_term_name($input);
    if ($name_err !== null) {
        return $name_err;
    }

    $taxonomy = 'product_shipping_class';
    $args = wc_apply_term_input(term_id: 0, input: $input, taxonomy: $taxonomy);
    if ($args instanceof WP_Error) {
        return $args;
    }

    try {
        /** @var array{term_id: int, term_taxonomy_id: int}|WP_Error $result */
        $result = \wp_insert_term((string) ($args['name'] ?? $input['name']), $taxonomy, [
            'description' => (string) ($args['description'] ?? ''),
            'slug' => (string) ($args['slug'] ?? ''),
        ]);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Shipping class creation failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        $code = $result->get_error_code();
        if ($code === 'term_exists') {
            return wc_error('wc_term_exists', __(
                'A shipping class with this name already exists.',
                domain: 'novamira-pro',
            ));
        }
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    $term_id = (int) $result['term_id'];
    $term = get_term($term_id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return wc_error('wc_internal_error', __(
            'Failed to reload shipping class after creation.',
            domain: 'novamira-pro',
        ));
    }

    return ['success' => true, 'shipping_class' => wc_term_to_array($term, $taxonomy)];
}
