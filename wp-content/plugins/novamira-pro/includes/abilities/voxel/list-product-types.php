<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: list Voxel product types (read-only, compact rows).
 */

wp_register_ability('novamira/voxel-list-product-types', [
    'label' => __('List Voxel Product Types', domain: 'novamira-pro'),
    'description' => __(
        'List the Voxel product types (the commerce templates that make a post purchasable) as compact rows: key, label, product mode, number of product fields, and whether it supports the marketplace (vendor) flow. Read-only. Use novamira/voxel-get-product-type for one type\'s full configuration.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'product_types' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
        ],
        'required' => ['product_types', 'count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_product_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only list of the site\'s product types.',
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
function voxel_list_product_types(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $rows = [];
    foreach (\Voxel\Product_Type::all() as $product_type) {
        $rows[] = vx_product_type_row($product_type);
    }

    return ['product_types' => $rows, 'count' => count($rows)];
}
