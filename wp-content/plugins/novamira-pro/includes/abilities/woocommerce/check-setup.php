<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-check-setup', [
    'label' => __('Check WooCommerce Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the WooCommerce environment: whether WC is active, version, whether it meets the minimum (9.0), whether HPOS (High-Performance Order Storage) is the canonical order storage, whether the Block Checkout page is in use, store currency, default country, total published products (products_count counts published products only; to enumerate ALL products including drafts and pending, use list-products with no status filter), and known WC extensions detected on the site (subscriptions, bookings, brands, memberships, product-add-ons, advanced-shipping, stripe-gateway, woopayments, paypal-payments). Run this before any other WC ability to confirm the runtime is ready.',
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
            'active' => ['type' => 'boolean'],
            'version' => ['type' => ['string', 'null']],
            'meets_minimum' => ['type' => 'boolean'],
            'hpos_enabled' => ['type' => 'boolean'],
            'block_checkout_enabled' => ['type' => 'boolean'],
            'currency' => ['type' => ['string', 'null']],
            'default_country' => ['type' => ['string', 'null']],
            'products_count' => ['type' => 'integer'],
            'registered_extensions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'version' => ['type' => ['string', 'null']],
                        'active' => ['type' => 'boolean'],
                    ],
                    'required' => ['slug', 'name', 'version', 'active'],
                ],
            ],
        ],
        'required' => [
            'active',
            'version',
            'meets_minimum',
            'hpos_enabled',
            'block_checkout_enabled',
            'currency',
            'default_country',
            'products_count',
            'registered_extensions',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run this first. If `meets_minimum` is false, no other WC ability will work; instruct the user to update WooCommerce.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function woocommerce_check_setup(array $input): array
{
    $active = class_exists('WooCommerce');
    if (!$active) {
        return [
            'active' => false,
            'version' => null,
            'meets_minimum' => false,
            'hpos_enabled' => false,
            'block_checkout_enabled' => false,
            'currency' => null,
            'default_country' => null,
            'products_count' => 0,
            'registered_extensions' => wc_detect_extensions(),
        ];
    }
    $version = defined('WC_VERSION') ? (string) constant('WC_VERSION') : null;
    $meets_min = $version !== null && version_compare($version, NOVAMIRA_PRO_WOOCOMMERCE_MIN_VERSION, operator: '>=');

    $hpos_enabled = false;
    if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
        $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    $block_checkout_enabled = false;
    $checkout_page_id = (int) get_option('woocommerce_checkout_page_id');
    if ($checkout_page_id > 0 && function_exists('has_block')) {
        /** @var \WP_Post|null $page */
        $page = get_post($checkout_page_id);
        if ($page instanceof \WP_Post && has_block('woocommerce/checkout', $page)) {
            $block_checkout_enabled = true;
        }
    }

    $counts = wp_count_posts('product');
    $products_count = (int) ($counts->publish ?? 0);

    $base = function_exists('wc_get_base_location') ? \wc_get_base_location() : ['country' => null];

    return [
        'active' => true,
        'version' => $version,
        'meets_minimum' => $meets_min,
        'hpos_enabled' => $hpos_enabled,
        'block_checkout_enabled' => $block_checkout_enabled,
        'currency' => function_exists('get_woocommerce_currency') ? \get_woocommerce_currency() : null,
        'default_country' => is_string($base['country'] ?? null) ? $base['country'] : null,
        'products_count' => $products_count,
        'registered_extensions' => wc_detect_extensions(),
    ];
}
