<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const FRM_PAYMENTS_TABLE = 'frm_payments';

const FRM_SUBSCRIPTIONS_TABLE = 'frm_subscriptions';

const FRM_PAYMENTS_DEFAULT_PER_PAGE = 50;

const FRM_PAYMENTS_MAX_PER_PAGE = 500;

const FRM_PAYMENTS_HARD_CAP = 5000;

// ---------------------------------------------------------------------------
// list-transactions
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-list-transactions', [
    'label' => __('List Formidable Transactions', domain: 'novamira-pro'),
    'description' => __(
        "Lists rows from `wp_frm_payments` (the Stripe Lite + Pro payment ledger). Available on free Formidable installs that have Stripe Lite configured AND on Pro. Filters: `form_id` (joins via the action's menu_order), `status` (e.g. `complete`, `failed`, `pending`, `cancelled`), `paysys` (e.g. `stripe`, `paypal`, `manual`), `entry_id` (item_id). Paginated.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'status' => ['type' => 'string'],
            'paysys' => ['type' => 'string'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 50],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'transactions' => ['type' => 'array'],
            'pagination' => ['type' => 'object'],
        ],
        'required' => ['transactions', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_list_transactions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists rows from wp_frm_payments. Requires payments_supported=true in check-setup.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, entry_id?: int|string, status?: string, paysys?: string, page?: int, per_page?: int} $input
 * @return array{transactions: list<array<string, mixed>>, pagination: array{page: int, per_page: int, total: int, total_pages: int, truncated: bool}}|WP_Error
 */
function formidable_list_transactions(array $input): array|WP_Error
{
    if (!frm_payments_supported()) {
        return frm_payments_not_supported('list-transactions');
    }
    $cap = frm_capability_or_error('frm_view_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $page = max(1, frm_to_int($input['page'] ?? 1));
    $per_page = min(FRM_PAYMENTS_MAX_PER_PAGE, max(1, frm_to_int($input['per_page'] ?? FRM_PAYMENTS_DEFAULT_PER_PAGE)));

    $rows = frm_query_payment_rows(FRM_PAYMENTS_TABLE, $input);
    $total = count($rows);
    $slice = array_slice($rows, ($page - 1) * $per_page, $per_page);

    return [
        'transactions' => array_map(static fn(\stdClass $r): array => frm_payment_to_row($r), $slice),
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => (int) ceil($total / $per_page),
            'truncated' => $total === FRM_PAYMENTS_HARD_CAP,
        ],
    ];
}

// ---------------------------------------------------------------------------
// get-transaction
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-get-transaction', [
    'label' => __('Get Formidable Transaction', domain: 'novamira-pro'),
    'description' => __(
        'Reads one row from `wp_frm_payments` by id. Returns id, receipt_id, invoice_id, sub_id, item_id, amount, status, action_id, paysys, created_at, begin_date, expire_date, meta (decoded blob), test.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'transaction_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['transaction_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'transaction' => ['type' => 'object'],
        ],
        'required' => ['transaction'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_transaction',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns one transaction row.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{transaction_id?: int|string} $input
 * @return array{transaction: array<string, mixed>}|WP_Error
 */
function formidable_get_transaction(array $input): array|WP_Error
{
    if (!frm_payments_supported()) {
        return frm_payments_not_supported('get-transaction');
    }
    $cap = frm_capability_or_error('frm_view_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $id = frm_to_int($input['transaction_id'] ?? 0);
    if ($id <= 0) {
        return frm_invalid_input('`transaction_id` must be a positive integer.');
    }
    $row = frm_get_payment_row(FRM_PAYMENTS_TABLE, $id);
    if (!$row instanceof \stdClass) {
        return new WP_Error('formidable_transaction_not_found', "Transaction not found: {$id}", ['status' => 404]);
    }
    return ['transaction' => frm_payment_to_row($row)];
}

// ---------------------------------------------------------------------------
// list-subscriptions
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-list-subscriptions', [
    'label' => __('List Formidable Subscriptions', domain: 'novamira-pro'),
    'description' => __(
        'Lists rows from `wp_frm_subscriptions` (Stripe Lite + Pro recurring billing ledger). Same gating as transactions — available whenever payments_supported=true.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'status' => ['type' => 'string'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 50],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'subscriptions' => ['type' => 'array'],
            'pagination' => ['type' => 'object'],
        ],
        'required' => ['subscriptions', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_list_subscriptions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists subscription rows.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, entry_id?: int|string, status?: string, page?: int, per_page?: int} $input
 * @return array{subscriptions: list<array<string, mixed>>, pagination: array{page: int, per_page: int, total: int, total_pages: int, truncated: bool}}|WP_Error
 */
function formidable_list_subscriptions(array $input): array|WP_Error
{
    if (!frm_payments_supported()) {
        return frm_payments_not_supported('list-subscriptions');
    }
    $cap = frm_capability_or_error('frm_view_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $page = max(1, frm_to_int($input['page'] ?? 1));
    $per_page = min(FRM_PAYMENTS_MAX_PER_PAGE, max(1, frm_to_int($input['per_page'] ?? FRM_PAYMENTS_DEFAULT_PER_PAGE)));
    $rows = frm_query_payment_rows(FRM_SUBSCRIPTIONS_TABLE, $input);
    $total = count($rows);
    $slice = array_slice($rows, ($page - 1) * $per_page, $per_page);
    return [
        'subscriptions' => array_map(static fn(\stdClass $r): array => frm_payment_to_row($r), $slice),
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => (int) ceil($total / $per_page),
            'truncated' => $total === FRM_PAYMENTS_HARD_CAP,
        ],
    ];
}

// ---------------------------------------------------------------------------
// get-subscription
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-get-subscription', [
    'label' => __('Get Formidable Subscription', domain: 'novamira-pro'),
    'description' => __('Reads one row from wp_frm_subscriptions by id.', domain: 'novamira-pro'),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'subscription_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['subscription_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'subscription' => ['type' => 'object'],
        ],
        'required' => ['subscription'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_subscription',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns one subscription row.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{subscription_id?: int|string} $input
 * @return array{subscription: array<string, mixed>}|WP_Error
 */
function formidable_get_subscription(array $input): array|WP_Error
{
    if (!frm_payments_supported()) {
        return frm_payments_not_supported('get-subscription');
    }
    $cap = frm_capability_or_error('frm_view_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $id = frm_to_int($input['subscription_id'] ?? 0);
    if ($id <= 0) {
        return frm_invalid_input('`subscription_id` must be a positive integer.');
    }
    $row = frm_get_payment_row(FRM_SUBSCRIPTIONS_TABLE, $id);
    if (!$row instanceof \stdClass) {
        return new WP_Error('formidable_subscription_not_found', "Subscription not found: {$id}", ['status' => 404]);
    }
    return ['subscription' => frm_payment_to_row($row)];
}

// ---------------------------------------------------------------------------
// cancel-subscription (Pro only)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-cancel-subscription', [
    'label' => __('Cancel Formidable Subscription', domain: 'novamira-pro'),
    'description' => __(
        'Cancels a recurring billing subscription (`wp_frm_subscriptions` row) by setting its `status` column to `cancelled`. NOTE: this changes the local ledger only — the upstream gateway (Stripe / PayPal / Square) is NOT contacted; that lifecycle is owned by the Pro gateway controllers. Use this when the upstream cancel has already happened externally and the ledger needs to catch up. Pro-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'subscription_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['subscription_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'subscription_id' => ['type' => 'integer'],
            'cancelled' => ['type' => 'boolean'],
            'previous_status' => ['type' => 'string'],
        ],
        'required' => ['subscription_id', 'cancelled', 'previous_status'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_cancel_subscription',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Sets subscription status to 'cancelled'. Local ledger only — not a gateway API call. Pro-only.",
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{subscription_id?: int|string} $input
 * @return array{subscription_id: int, cancelled: bool, previous_status: string}|WP_Error
 */
function formidable_cancel_subscription(array $input): array|WP_Error
{
    if (!frm_pro_active()) {
        return frm_pro_required('cancel-subscription');
    }
    if (!frm_payments_supported()) {
        return frm_payments_not_supported('cancel-subscription');
    }
    $cap = frm_capability_or_error('frm_change_settings');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $id = frm_to_int($input['subscription_id'] ?? 0);
    if ($id <= 0) {
        return frm_invalid_input('`subscription_id` must be a positive integer.');
    }
    $row = frm_get_payment_row(FRM_SUBSCRIPTIONS_TABLE, $id);
    if (!$row instanceof \stdClass) {
        return new WP_Error('formidable_subscription_not_found', "Subscription not found: {$id}", ['status' => 404]);
    }
    $previous = (string) ($row->status ?? '');
    if ($previous === 'cancelled') {
        return ['subscription_id' => $id, 'cancelled' => false, 'previous_status' => 'cancelled'];
    }

    global $wpdb;
    /** @var \wpdb $wpdb */
    $wpdb->update($wpdb->prefix . FRM_SUBSCRIPTIONS_TABLE, ['status' => 'cancelled'], ['id' => $id], ['%s'], ['%d']);
    return ['subscription_id' => $id, 'cancelled' => true, 'previous_status' => $previous];
}

// ===========================================================================
// Internal helpers
// ===========================================================================

function frm_payments_not_supported(string $surface): WP_Error
{
    return new WP_Error(
        'formidable_payments_not_supported',
        "{$surface} requires the Stripe Lite payment surface (wp_frm_payments) which is not provisioned on this install.",
        ['status' => 400],
    );
}

/**
 * Generic payments-table query (works for both wp_frm_payments and
 * wp_frm_subscriptions).
 *
 * @param array<string, mixed> $filters
 * @return list<\stdClass>
 */
function frm_query_payment_rows(string $table_suffix, array $filters): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    [$where_sql, $params] = frm_build_payment_where($filters);
    $table = $wpdb->prefix . $table_suffix;
    $cap = (int) FRM_PAYMENTS_HARD_CAP;
    // @mago-expect analyzer:possibly-invalid-argument
    $prepared = $wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT {$cap}", ...$params);
    $query = is_string($prepared) ? $prepared : '';
    $rows = $wpdb->get_results($query);
    if (!is_array($rows)) {
        return [];
    }
    /** @var list<\stdClass> $out */
    $out = [];
    foreach ($rows as $row) {
        if (!$row instanceof \stdClass) {
            continue;
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Build the WHERE clause for a payments/subscriptions query.
 *
 * @param array<string, mixed> $filters
 * @return array{0: string, 1: list<mixed>}
 */
function frm_build_payment_where(array $filters): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $clauses = ['1=1'];
    /** @var list<mixed> $params */
    $params = [];
    if (isset($filters['form_id'])) {
        // wp_frm_payments and wp_frm_subscriptions have no direct form_id
        // column; the link is item_id → wp_frm_items.id → wp_frm_items.form_id.
        // Use a sub-select to scope rows to a single form server-side
        // (silently dropping the filter would let an agent believe the
        // result set is scoped when it isn't).
        $items_table = $wpdb->prefix . 'frm_items';
        $clauses[] = 'item_id IN (SELECT id FROM ' . $items_table . ' WHERE form_id = %d)';
        $params[] = frm_to_int($filters['form_id']);
    }
    if (isset($filters['entry_id'])) {
        $clauses[] = 'item_id = %d';
        $params[] = frm_to_int($filters['entry_id']);
    }
    /** @var mixed $status */
    $status = $filters['status'] ?? '';
    if (is_string($status) && $status !== '') {
        $clauses[] = 'status = %s';
        $params[] = $status;
    }
    /** @var mixed $paysys */
    $paysys = $filters['paysys'] ?? '';
    if (is_string($paysys) && $paysys !== '') {
        $clauses[] = 'paysys = %s';
        $params[] = $paysys;
    }
    return [implode(' AND ', $clauses), $params];
}

function frm_get_payment_row(string $table_suffix, int $id): ?\stdClass
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = $wpdb->prefix . $table_suffix;
    // @mago-expect analyzer:possibly-invalid-argument
    $prepared = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);
    $query = is_string($prepared) ? $prepared : '';
    /** @var \stdClass|null $row */
    $row = $wpdb->get_row($query);
    return $row instanceof \stdClass ? $row : null;
}

/**
 * Reduce a wp_frm_payments / wp_frm_subscriptions row to the compact
 * response shape. The `meta` blob is deserialized in place; everything
 * else passes through verbatim.
 *
 * @return array<string, mixed>
 */
function frm_payment_to_row(\stdClass $row): array
{
    $arr = (array) $row;
    if (isset($arr['meta_value']) && is_string($arr['meta_value']) && $arr['meta_value'] !== '') {
        /** @var mixed $meta */
        $meta = maybe_unserialize($arr['meta_value']);
        $arr['meta'] = $meta;
        unset($arr['meta_value']);
        return $arr;
    }
    $arr['meta'] = [];
    unset($arr['meta_value']);
    return $arr;
}
