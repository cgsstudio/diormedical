<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

if (!ff_pro_runtime_available()) {
    return;
}

wp_register_ability('novamira/fluentforms-get-transaction', [
    'label' => __('Get a Fluent Forms transaction', domain: 'novamira-pro'),
    'description' => __(
        "Fetch one Fluent Forms Pro transaction by id. Returns the compact row PLUS payment_note (vendor notes / raw payment description) and billing_address / shipping_address (full addresses). Returns fluentforms_transaction_not_found (404) for missing ids. Capability: fluentform_view_payments (per-form when the transaction's form_id is known).",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => ['transaction_id' => ['type' => 'integer', 'minimum' => 1]],
        'required' => ['transaction_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'transaction' => ['type' => 'object'],
        ],
        'required' => ['transaction'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_transaction',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Capability: fluentform_view_payments. Returns the compact row + payment_note + billing_address + shipping_address.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{transaction: array<string, mixed>}|WP_Error
 */
function fluentforms_get_transaction(array $input): array|WP_Error
{
    $pro = ff_pro_required('transactions');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $tx = ff_transaction_or_error($input['transaction_id'] ?? null);
    if ($tx instanceof WP_Error) {
        return $tx;
    }
    $cap = ff_capability_or_error('fluentform_view_payments', form_id: ff_row_int($tx, column: 'form_id'));
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $payload = ff_transaction_row($tx);
    $payload['payment_note'] = ff_row_str($tx, column: 'payment_note');
    $payload['billing_address'] = ff_row_str($tx, column: 'billing_address');
    $payload['shipping_address'] = ff_row_str($tx, column: 'shipping_address');
    return ['transaction' => $payload];
}
