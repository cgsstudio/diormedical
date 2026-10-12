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

if (!ff_pro_runtime_available()) {
    return;
}

wp_register_ability('novamira/fluentforms-list-subscriptions', [
    'label' => __('List Fluent Forms subscriptions', domain: 'novamira-pro'),
    'description' => __(
        'List recurring-billing subscriptions captured by Fluent Forms Pro. Filterable by form_id, submission_id, status (active|trialing|cancelled|expired|past_due), billing_interval (day|week|month|year). Compact rows include item_name, plan_name, recurring_amount, billing_interval, bill_times, bill_count, vendor_subscription_id, status, expiration_at. Per_page capped at 50. Capability: fluentform_view_payments.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'submission_id' => ['type' => 'integer', 'minimum' => 1],
            'status' => ['type' => 'string'],
            'billing_interval' => ['type' => 'string', 'enum' => ['', 'day', 'week', 'month', 'year']],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 25],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'subscriptions' => ['type' => 'array', 'items' => ['type' => 'object']],
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
        'required' => ['subscriptions', 'pagination'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_list_subscriptions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Capability: fluentform_view_payments. To cancel an active subscription call novamira/fluentforms-cancel-subscription.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{subscriptions:list<array<string, mixed>>, pagination:array{page:int,per_page:int,total:int,total_pages:int}}|WP_Error
 */
function fluentforms_list_subscriptions(array $input): array|WP_Error
{
    $pro = ff_pro_required('subscriptions');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $filters = ff_list_subscriptions_parse_input($input);
    $cap = ff_capability_or_error('fluentform_view_payments', form_id: $filters['form_id']);
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    [$rows, $total] = ff_list_subscriptions_query($filters);
    return [
        'subscriptions' => ff_list_subscriptions_rows($rows),
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
 * @return array{form_id:?int,submission_id:?int,status:string,billing_interval:string,page:int,per_page:int}
 */
function ff_list_subscriptions_parse_input(array $input): array
{
    return [
        'form_id' => ff_input_pos_int($input, key: 'form_id'),
        'submission_id' => ff_input_pos_int($input, key: 'submission_id'),
        'status' => ff_input_str($input, key: 'status'),
        'billing_interval' => ff_input_str($input, key: 'billing_interval'),
        'page' => ff_input_int_or($input, key: 'page', default: 1, min: 1),
        'per_page' => max(1, min(50, ff_input_int_or($input, key: 'per_page', default: 25))),
    ];
}

/**
 * @param array{form_id:?int,submission_id:?int,status:string,billing_interval:string,page:int,per_page:int} $filters
 *
 * @return array{0:list<stdClass>, 1:int}
 */
function ff_list_subscriptions_query(array $filters): array
{
    if (!function_exists('wpFluent')) {
        return [[], 0];
    }
    $q = wpFluent()->table('fluentform_subscriptions');
    if ($filters['form_id'] !== null) {
        $q = $q->where('form_id', $filters['form_id']);
    }
    if ($filters['submission_id'] !== null) {
        $q = $q->where('submission_id', $filters['submission_id']);
    }
    if ($filters['status'] !== '') {
        $q = $q->where('status', $filters['status']);
    }
    if ($filters['billing_interval'] !== '') {
        $q = $q->where('billing_interval', $filters['billing_interval']);
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
 * @param list<stdClass> $rows
 *
 * @return list<array<string, mixed>>
 */
function ff_list_subscriptions_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (ff_row_id($row) <= 0) {
            continue;
        }
        $out[] = ff_subscription_row($row);
    }
    return $out;
}
