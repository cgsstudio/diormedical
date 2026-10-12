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
 * Ability: read one Voxel product type (read-only, opt-in detail).
 */

wp_register_ability('novamira/voxel-get-product-type', [
    'label' => __('Get Voxel Product Type', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel product type by key. Returns the summary (key, label, product mode, marketplace support); pass include with "fields" for its product-field list and/or "settings" for its settings block. Read-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'product_type' => ['type' => 'string', 'description' => 'The product type key (e.g. "simple-product").'],
            'include' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['fields', 'settings']],
                'description' => 'Optional sections to embed.',
            ],
        ],
        'required' => ['product_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => ['string', 'null']],
            'label' => ['type' => ['string', 'null']],
            'product_mode' => ['type' => ['string', 'null']],
            'fields_count' => ['type' => 'integer'],
            'supports_marketplace' => ['type' => 'boolean'],
            'fields' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'settings' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => ['key', 'label', 'product_mode', 'fields_count', 'supports_marketplace'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_product_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. include: fields, settings.',
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
function voxel_get_product_type(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['product_type'] ?? null) ? $input['product_type'] : '';
    $product_type = \Voxel\Product_Type::get($key);
    if ($product_type === null) {
        return vx_not_found('product type', $key);
    }

    $row = vx_product_type_row($product_type);
    $include = vx_coerce_string_list($input['include'] ?? []);
    $config = $product_type->get_config();

    if (in_array('fields', $include, strict: true)) {
        $row['fields'] = isset($config['fields']) && is_array($config['fields']) ? array_values($config['fields']) : [];
    }
    if (in_array('settings', $include, strict: true)) {
        $row['settings'] = isset($config['settings']) && is_array($config['settings']) ? $config['settings'] : [];
    }

    return $row;
}
