<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-get-store-settings', [
    'label' => __('Get WooCommerce Store Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the store-level settings that affect product display and pricing: currency, currency symbol and position, thousand and decimal separators, number of decimals, weight and dimension units, default country, and the store base address. Pulled from a mix of WC helpers and direct option reads (woocommerce_* options).',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'currency' => ['type' => 'string'],
            'currency_symbol' => ['type' => 'string'],
            'currency_position' => ['type' => 'string'],
            'thousand_separator' => ['type' => 'string'],
            'decimal_separator' => ['type' => 'string'],
            'num_decimals' => ['type' => 'integer'],
            'weight_unit' => ['type' => 'string'],
            'dimension_unit' => ['type' => 'string'],
            'default_country' => ['type' => 'string'],
            'store_address' => [
                'type' => 'object',
                'properties' => [
                    'address_1' => ['type' => 'string'],
                    'address_2' => ['type' => 'string'],
                    'city' => ['type' => 'string'],
                    'postcode' => ['type' => 'string'],
                    'state' => ['type' => 'string'],
                    'country' => ['type' => 'string'],
                ],
                'required' => ['address_1', 'address_2', 'city', 'postcode', 'state', 'country'],
            ],
        ],
        'required' => [
            'currency',
            'currency_symbol',
            'currency_position',
            'thousand_separator',
            'decimal_separator',
            'num_decimals',
            'weight_unit',
            'dimension_unit',
            'default_country',
            'store_address',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_get_store_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Treat all string values as opaque. Currency symbol is returned as an HTML entity (e.g., `&#36;`, `&euro;`, `&yen;`). Decode with html_entity_decode() before display. Use num_decimals + separators to format prices, not custom logic.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_get_store_settings(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }
    /** @var array<string, string|null> $base */
    $base = \wc_get_base_location();
    return [
        'currency' => \get_woocommerce_currency(),
        'currency_symbol' => \get_woocommerce_currency_symbol(),
        'currency_position' => (string) get_option(option: 'woocommerce_currency_pos', default_value: 'left'),
        'thousand_separator' => \wc_get_price_thousand_separator(),
        'decimal_separator' => \wc_get_price_decimal_separator(),
        'num_decimals' => (int) \wc_get_price_decimals(),
        'weight_unit' => (string) get_option(option: 'woocommerce_weight_unit', default_value: ''),
        'dimension_unit' => (string) get_option(option: 'woocommerce_dimension_unit', default_value: ''),
        'default_country' => $base['country'] ?? '',
        'store_address' => [
            'address_1' => (string) get_option(option: 'woocommerce_store_address', default_value: ''),
            'address_2' => (string) get_option(option: 'woocommerce_store_address_2', default_value: ''),
            'city' => (string) get_option(option: 'woocommerce_store_city', default_value: ''),
            'postcode' => (string) get_option(option: 'woocommerce_store_postcode', default_value: ''),
            'state' => $base['state'] ?? '',
            'country' => $base['country'] ?? '',
        ],
    ];
}
