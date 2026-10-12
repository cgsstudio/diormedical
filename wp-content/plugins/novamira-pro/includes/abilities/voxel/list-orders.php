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
 * Ability: list Voxel commerce orders (read-only, compact rows).
 */

wp_register_ability('novamira/voxel-list-orders', [
    'label' => __('List Voxel Orders', domain: 'novamira-pro'),
    'description' => __(
        'List Voxel commerce orders as compact rows (id, customer, vendor, status, shipping status, payment method, total, currency, item count, created date). Filter by customer_id, vendor_id, status, shipping_status or payment_method. Read-only; it never contacts Stripe. Orders are scoped to the site\'s current payments mode (test or live), so only orders from that mode are returned. Use novamira/voxel-get-order for one order\'s full detail.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'customer_id' => ['type' => 'integer', 'description' => 'Only orders placed by this user id.'],
            'vendor_id' => ['type' => 'integer', 'description' => 'Only orders sold by this vendor user id.'],
            'status' => [
                'type' => 'string',
                'description' => 'Only orders with this status (e.g. completed, pending_payment, refunded).',
            ],
            'shipping_status' => [
                'type' => 'string',
                'description' => 'Only orders with this shipping status (processing, shipped, delivered).',
            ],
            'payment_method' => [
                'type' => 'string',
                'description' => 'Only orders paid with this method (e.g. stripe_payment, offline_payment).',
            ],
            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 200).'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip (for paging).'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'orders' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
            'pagination' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer'],
                    'offset' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'test_mode' => ['type' => 'boolean'],
                ],
                'required' => ['limit', 'offset', 'total', 'test_mode'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['orders', 'count', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_orders',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Orders are scoped to the current payments mode (test/live). No Stripe calls.',
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
function voxel_list_orders(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $limit = vx_clamp_int($input['limit'] ?? null, min: 1, max: 200, default: 25);
    $offset = max(0, vx_clamp_int($input['offset'] ?? null, min: 0, max: PHP_INT_MAX, default: 0));

    $filters = vx_order_query_filters($input);

    $orders = \Voxel\Product_Types\Orders\Order::query(array_merge($filters, [
        'limit' => $limit,
        'offset' => $offset,
        'order_by' => 'id',
        'order' => 'desc',
        'with_items' => false,
    ]));
    $total = \Voxel\Product_Types\Orders\Order::count($filters);

    $rows = [];
    foreach ($orders as $order) {
        $rows[] = vx_order_row($order);
    }

    return [
        'orders' => $rows,
        'count' => count($rows),
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total,
            'test_mode' => \Voxel\is_test_mode(),
        ],
    ];
}

/**
 * Build the Order::query filter args from the validated input (only set keys the
 * caller provided, so an omitted filter does not constrain the query).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function vx_order_query_filters(array $input): array
{
    $filters = [];
    $customer_id = vx_coerce_int($input['customer_id'] ?? null);
    if ($customer_id !== null && $customer_id > 0) {
        $filters['customer_id'] = $customer_id;
    }
    $vendor_id = vx_coerce_int($input['vendor_id'] ?? null);
    if ($vendor_id !== null && $vendor_id > 0) {
        $filters['vendor_id'] = $vendor_id;
    }
    if (isset($input['status']) && is_string($input['status']) && $input['status'] !== '') {
        $filters['status'] = $input['status'];
    }
    if (isset($input['shipping_status']) && is_string($input['shipping_status']) && $input['shipping_status'] !== '') {
        $filters['shipping_status'] = $input['shipping_status'];
    }
    if (isset($input['payment_method']) && is_string($input['payment_method']) && $input['payment_method'] !== '') {
        $filters['payment_method'] = $input['payment_method'];
    }
    return $filters;
}
