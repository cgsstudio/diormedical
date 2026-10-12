<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-list-product-statuses', [
    'label' => __('List Product Statuses', domain: 'novamira-pro'),
    'description' => __(
        'Returns the post statuses applicable to WC products: publish, draft, pending, private, trash. Use the slugs when filtering list-products by status or when picking a status in create/edit-product in later sprints.',
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
            'statuses' => [
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
        'required' => ['statuses'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_product_statuses',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Status slugs are stable. Use them as the `status` filter on list-products.',
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
function woocommerce_list_product_statuses(array $input): array
{
    return [
        'statuses' => [
            ['slug' => 'publish', 'label' => __('Published', domain: 'novamira-pro')],
            ['slug' => 'draft', 'label' => __('Draft', domain: 'novamira-pro')],
            ['slug' => 'pending', 'label' => __('Pending review', domain: 'novamira-pro')],
            ['slug' => 'private', 'label' => __('Private', domain: 'novamira-pro')],
            ['slug' => 'trash', 'label' => __('Trash', domain: 'novamira-pro')],
        ],
    ];
}
