<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-list-product-types', [
    'label' => __('List Product Types', domain: 'novamira-pro'),
    'description' => __(
        'Returns the WC product types available on the site. Six core types are always present (simple, variable, grouped, external, plus the virtual / downloadable flags). Custom types registered by third-party plugins are not enumerated in this release.',
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
            'types' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                    'required' => ['slug', 'label'],
                ],
            ],
        ],
        'required' => ['types'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_product_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use the returned slug values when filtering list-products by type, or when picking the type for a create-product call in later sprints.',
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
function woocommerce_list_product_types(array $input): array
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return ['types' => []];
    }
    return [
        'types' => [
            ['slug' => 'simple', 'label' => __('Simple product', domain: 'novamira-pro')],
            ['slug' => 'variable', 'label' => __('Variable product', domain: 'novamira-pro')],
            ['slug' => 'grouped', 'label' => __('Grouped product', domain: 'novamira-pro')],
            ['slug' => 'external', 'label' => __('External / affiliate product', domain: 'novamira-pro')],
            ['slug' => 'virtual', 'label' => __('Virtual (flag)', domain: 'novamira-pro')],
            ['slug' => 'downloadable', 'label' => __('Downloadable (flag)', domain: 'novamira-pro')],
        ],
    ];
}
