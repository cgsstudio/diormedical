<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use stdClass;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// Pro-gated registration — see runtime.php (ff_pro_runtime_available).
if (!ff_pro_runtime_available()) {
    return;
}

wp_register_ability('novamira/fluentforms-list-transactions', [
    'label' => __('List Fluent Forms transactions', domain: 'novamira-pro'),
    'description' => __(
        'List payment transactions captured by Fluent Forms Pro. Filterable by form_id, submission_id, subscription_id, status (paid|pending|failed|refunded|cancelled), payment_method (stripe|paypal|square|razorpay|offline|…), payment_mode (test|live), from / to (ISO date range on created_at). Returns compact rows (no payment_note / billing_address blobs to keep the payload small) plus pagination metadata. Per_page capped at 100. Capability: fluentform_view_payments.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'submission_id' => ['type' => 'integer', 'minimum' => 1],
            'subscription_id' => ['type' => 'integer', 'minimum' => 1],
            'status' => ['type' => 'string'],
            'payment_method' => ['type' => 'string'],
            'payment_mode' => ['type' => 'string', 'enum' => ['', 'test', 'live']],
            'from' => ['type' => 'string'],
            'to' => ['type' => 'string'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'transactions' => ['type' => 'array', 'items' => ['type' => 'object']],
            'pagination' => [
                'type' => 'object',
                'properties' => [
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'total_pages' => ['type' => 'integer'],
                ],
                'required' => ['page', 'per_page', 'total', 'total_pages'],
            ],
        ],
        'required' => ['transactions', 'pagination'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_list_transactions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Capability: fluentform_view_payments (per-form when form_id is supplied). Compact rows — payment_note + billing_address omitted; fetch with get-transaction.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{transactions:list<array<string, mixed>>, pagination:array{page:int,per_page:int,total:int,total_pages:int}}|WP_Error
 */
function fluentforms_list_transactions(array $input): array|WP_Error
{
    $pro = ff_pro_required('transactions');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $filters = ff_list_transactions_parse_input($input);
    $cap = ff_capability_or_error('fluentform_view_payments', form_id: $filters['form_id']);
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    [$rows, $total] = ff_list_transactions_query($filters);
    return [
        'transactions' => ff_list_transactions_rows($rows),
        'pagination' => [
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total' => $total,
            'total_pages' => (int) ceil($total / $filters['per_page']),
        ],
    ];
}

/**
 * @param array<string, mixed> $input
 *
 * @return array{form_id:?int,submission_id:?int,subscription_id:?int,status:string,payment_method:string,payment_mode:string,from:string,to:string,page:int,per_page:int}
 */
function ff_list_transactions_parse_input(array $input): array
{
    return [
        'form_id' => ff_input_pos_int($input, key: 'form_id'),
        'submission_id' => ff_input_pos_int($input, key: 'submission_id'),
        'subscription_id' => ff_input_pos_int($input, key: 'subscription_id'),
        'status' => ff_input_str($input, key: 'status'),
        'payment_method' => ff_input_str($input, key: 'payment_method'),
        'payment_mode' => ff_input_str($input, key: 'payment_mode'),
        'from' => ff_input_str($input, key: 'from'),
        'to' => ff_input_str($input, key: 'to'),
        'page' => ff_input_int_or($input, key: 'page', default: 1, min: 1),
        'per_page' => max(1, min(100, ff_input_int_or($input, key: 'per_page', default: 25))),
    ];
}

/**
 * @param array{form_id:?int,submission_id:?int,subscription_id:?int,status:string,payment_method:string,payment_mode:string,from:string,to:string,page:int,per_page:int} $filters
 *
 * @return array{0:list<stdClass>, 1:int}
 */
function ff_list_transactions_query(array $filters): array
{
    if (!function_exists('wpFluent')) {
        return [[], 0];
    }
    $q = wpFluent()->table('fluentform_transactions');
    if ($filters['form_id'] !== null) {
        $q = $q->where('form_id', $filters['form_id']);
    }
    if ($filters['submission_id'] !== null) {
        $q = $q->where('submission_id', $filters['submission_id']);
    }
    if ($filters['subscription_id'] !== null) {
        $q = $q->where('subscription_id', $filters['subscription_id']);
    }
    if ($filters['status'] !== '') {
        $q = $q->where('status', $filters['status']);
    }
    if ($filters['payment_method'] !== '') {
        $q = $q->where('payment_method', $filters['payment_method']);
    }
    if ($filters['payment_mode'] !== '') {
        $q = $q->where('payment_mode', $filters['payment_mode']);
    }
    if ($filters['from'] !== '') {
        $q = $q->where('created_at', '>=', $filters['from'] . ' 00:00:00');
    }
    if ($filters['to'] !== '') {
        $q = $q->where('created_at', '<=', $filters['to'] . ' 23:59:59');
    }
    $total = (clone $q)->count();
    $rows = ff_rows_to_array(
        $q
            ->orderBy('created_at', direction: 'desc')
            ->skip(($filters['page'] - 1) * $filters['per_page'])
            ->take($filters['per_page'])
            ->get(),
    );
    return [$rows, $total];
}

/**
 * Project stdClass rows to compact rows. The projection reads via
 * ff_row_* helpers so the stdClass shape FF Pro returns from wpFluent is
 * tolerated as-is (no extra DB hit, no Eloquent Model needed).
 *
 * @param list<stdClass> $rows
 *
 * @return list<array<string, mixed>>
 */
function ff_list_transactions_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (ff_row_id($row) <= 0) {
            continue;
        }
        $out[] = ff_transaction_row($row);
    }
    return $out;
}
