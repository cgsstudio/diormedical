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
 * Ability: read one Voxel order (read-only, opt-in detail). Never syncs Stripe.
 */

wp_register_ability('novamira/voxel-get-order', [
    'label' => __('Get Voxel Order', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel commerce order by id. Returns the summary (status, totals, parties, dates, gateway transaction id); pass include with any of "items", "pricing", "shipping", "customer" for those sections. Read-only: it reads the stored order and never contacts Stripe (so it never triggers a payment sync). Orders are scoped to the current payments mode, so an order from the other mode reads as not found. To keep this a minimal read surface, customer/vendor are limited to id and display name (no email), and "shipping" returns the fulfillment status plus a coarse destination (city, state, zip, country) — not the full delivery name, street address or phone.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'order_id' => ['type' => 'integer', 'description' => 'The order id.'],
            'include' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['items', 'pricing', 'shipping', 'customer']],
                'description' => 'Optional detail sections to embed.',
            ],
        ],
        'required' => ['order_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'customer_id' => ['type' => 'integer'],
            'vendor_id' => ['type' => ['integer', 'null']],
            'status' => ['type' => 'string'],
            'status_label' => ['type' => 'string'],
            'shipping_status' => ['type' => ['string', 'null']],
            'payment_method' => ['type' => 'string'],
            'total' => ['type' => ['integer', 'number', 'null']],
            'currency' => ['type' => ['string', 'null']],
            'item_count' => ['type' => 'integer'],
            'test_mode' => ['type' => 'boolean'],
            'created_at' => ['type' => ['string', 'null']],
            'parent_order_id' => ['type' => ['integer', 'null']],
            'transaction_id' => ['type' => ['string', 'null']],
            'items' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'pricing' => ['type' => 'object', 'additionalProperties' => true],
            'customer' => ['type' => 'object', 'additionalProperties' => true],
            'vendor' => ['type' => ['object', 'null'], 'additionalProperties' => true],
            'shipping' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => [
            'id',
            'customer_id',
            'vendor_id',
            'status',
            'status_label',
            'shipping_status',
            'payment_method',
            'total',
            'currency',
            'item_count',
            'test_mode',
            'created_at',
            'parent_order_id',
            'transaction_id',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_order',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only, no Stripe sync. Mode-scoped. include: items/pricing/shipping/customer.',
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
function voxel_get_order(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $order = vx_order_or_error($input['order_id'] ?? null);
    if ($order instanceof WP_Error) {
        return $order;
    }

    $include = vx_coerce_string_list($input['include'] ?? []);

    return vx_order_full($order, $include);
}
