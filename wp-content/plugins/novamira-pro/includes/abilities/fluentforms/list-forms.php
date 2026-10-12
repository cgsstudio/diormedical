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

wp_register_ability('novamira/fluentforms-list-forms', [
    'label' => __('List Fluent Forms', domain: 'novamira-pro'),
    'description' => __(
        'List Fluent Forms forms with optional filters. Returns compact rows (id, title, status, type, has_payment, has_conversion, submissions_total, submissions_unread, created_at, updated_at) plus pagination metadata (page, per_page, total, total_pages). Fields[] and full settings are NOT included — fetch them with novamira/fluentforms-get-form when needed (progressive disclosure). Filters: search (partial title match), status (published|unpublished), type (form|post), has_payment (bool), page (default 1), per_page (default 25, max 200). Use this before any per-form CRUD ability to discover form ids.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'search' => [
                'type' => 'string',
                'description' => 'Substring match against form title (case-insensitive).',
                'default' => '',
            ],
            'status' => [
                'type' => 'string',
                'description' => 'Filter by form status. Fluent Forms recognises exactly two: published, unpublished (an unpublished form renders as empty output).',
                'enum' => ['', 'published', 'unpublished'],
                'default' => '',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Filter by form type. "form" is the standard FF form; "post" is a post-creation form; conversational forms are identified via the has_conversion flag, not type.',
                'enum' => ['', 'form', 'post'],
                'default' => '',
            ],
            'has_payment' => [
                'type' => 'boolean',
                'description' => 'When true, restrict to forms that collect payments (form.has_payment = 1).',
            ],
            'page' => [
                'type' => 'integer',
                'description' => 'Pagination cursor. 1-based; values <= 0 are coerced to 1.',
                'minimum' => 1,
                'default' => 1,
            ],
            'per_page' => [
                'type' => 'integer',
                'description' => 'Rows per page. Capped at 200; values <= 0 are coerced to the default.',
                'minimum' => 1,
                'maximum' => 200,
                'default' => 25,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'forms' => ['type' => 'array', 'items' => ['type' => 'object']],
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
        'required' => ['forms', 'pagination'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_list_forms',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact list — never embeds form_fields[] or settings. To inspect a form\'s structure call novamira/fluentforms-get-form with include=["fields","settings"]. The submissions_total and submissions_unread counters are computed in a single GROUP BY per call (not N per-row queries). Default order is created_at DESC; pagination is integer page-based (not cursor).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{
 *     forms: list<array<string, mixed>>,
 *     pagination: array{page:int, per_page:int, total:int, total_pages:int},
 * }|WP_Error
 */
function fluentforms_list_forms(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $cap_error = ff_list_forms_capability_check();
    if ($cap_error instanceof WP_Error) {
        return $cap_error;
    }

    $filters = ff_list_forms_parse_input($input);
    [$rows, $total] = ff_list_forms_query($filters);

    $ids = ff_list_forms_extract_ids($rows);
    $counts = ff_list_forms_submission_counts($ids);

    return [
        'forms' => ff_list_forms_rows($rows, $counts),
        'pagination' => [
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total' => $total,
            'total_pages' => (int) ceil($total / $filters['per_page']),
        ],
    ];
}

/**
 * Permission gate for list-forms. Returns null on success; a WP_Error 403
 * otherwise. Tries forms_manager first (the natural cap for a writer); falls
 * back to entries_viewer because read-only admins can still list forms in
 * FF's own UI.
 */
function ff_list_forms_capability_check(): ?WP_Error
{
    $cap = ff_capability_or_error('fluentform_forms_manager');
    if (!$cap instanceof WP_Error) {
        return null;
    }
    $cap_view = ff_capability_or_error('fluentform_entries_viewer');
    return $cap_view instanceof WP_Error ? $cap : null;
}

/**
 * Normalise caller input into a typed filter shape. Defensive — the agent
 * may pass values that don't match the declared schema (the abilities API
 * validates, but a callback should never crash on a wrong-typed cell).
 *
 * @param array<string, mixed> $input
 *
 * @return array{search:string,status:string,type:string,has_payment:?bool,page:int,per_page:int}
 */
function ff_list_forms_parse_input(array $input): array
{
    $page = isset($input['page']) && is_int($input['page']) && $input['page'] >= 1 ? $input['page'] : 1;
    $per_page = max(1, min(200, isset($input['per_page']) && is_int($input['per_page']) ? $input['per_page'] : 25));
    $search = isset($input['search']) && is_string($input['search']) ? trim($input['search']) : '';
    $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : '';
    $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : '';
    $has_payment = isset($input['has_payment']) && is_bool($input['has_payment']) ? $input['has_payment'] : null;

    return [
        'search' => $search,
        'status' => $status,
        'type' => $type,
        'has_payment' => $has_payment,
        'page' => $page,
        'per_page' => $per_page,
    ];
}

/**
 * Execute the forms list query with applied filters + the parallel count
 * query for pagination metadata. Returns the result rows + the total.
 *
 * @param array{search:string,status:string,type:string,has_payment:?bool,page:int,per_page:int} $filters
 *
 * @return array{0: list<stdClass>, 1: int}
 */
function ff_list_forms_query(array $filters): array
{
    if (!function_exists('wpFluent')) {
        return [[], 0];
    }
    $base = ff_list_forms_apply_filters(wpFluent()->table('fluentform_forms'), $filters);
    $total = (clone $base)->count();
    $rows = ff_rows_to_array(
        $base
            ->orderBy('created_at', direction: 'desc')
            ->skip(($filters['page'] - 1) * $filters['per_page'])
            ->take($filters['per_page'])
            ->get(),
    );
    return [$rows, $total];
}

/**
 * Apply the filter shape as a chain of where() calls. Extracted so the
 * filter logic stays in one place; the chain is reused by the COUNT and
 * the SELECT branches.
 *
 * @param array{search:string,status:string,type:string,has_payment:?bool,page:int,per_page:int} $filters
 */
function ff_list_forms_apply_filters(
    \FluentForm\Framework\Database\Query\Builder $base,
    array $filters,
): \FluentForm\Framework\Database\Query\Builder {
    if ($filters['search'] !== '') {
        global $wpdb;
        // esc_like so a % or _ in the search term matches literally rather
        // than acting as a LIKE wildcard.
        $escaped = $wpdb instanceof \wpdb ? $wpdb->esc_like($filters['search']) : $filters['search'];
        $base = $base->where('title', 'LIKE', '%' . $escaped . '%');
    }
    if ($filters['status'] !== '') {
        $base = $base->where('status', $filters['status']);
    }
    if ($filters['type'] !== '') {
        $base = $base->where('type', $filters['type']);
    }
    if ($filters['has_payment'] !== null) {
        $base = $base->where('has_payment', $filters['has_payment'] ? 1 : 0);
    }
    return $base;
}

/**
 * Pull the positive integer ids from a result-row list. Discards rows
 * missing an `id` or whose id isn't a positive integer.
 *
 * @param list<stdClass> $rows
 *
 * @return list<int>
 */
function ff_list_forms_extract_ids(array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        $id = ff_row_id($row);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return $ids;
}

/**
 * Project the result-row list to the compact response shape, merging the
 * pre-computed submission counters in.
 *
 * @param list<stdClass> $rows
 * @param array<int, array{total:int, unread:int}> $counts
 *
 * @return list<array<string, mixed>>
 */
function ff_list_forms_rows(array $rows, array $counts): array
{
    $forms = [];
    foreach ($rows as $row) {
        $id = ff_row_id($row);
        if ($id <= 0) {
            continue;
        }
        $forms[] = [
            'id' => $id,
            'title' => ff_row_str($row, column: 'title'),
            'status' => ff_row_str($row, column: 'status'),
            'type' => ff_row_str($row, column: 'type', default: 'form'),
            'has_payment' => ff_row_int($row, column: 'has_payment') === 1,
            'has_conversion' => ff_row_int($row, column: 'has_conversion') === 1,
            'submissions_total' => $counts[$id]['total'] ?? 0,
            'submissions_unread' => $counts[$id]['unread'] ?? 0,
            'created_at' => ff_row_nullable_str($row, column: 'created_at'),
            'updated_at' => ff_row_nullable_str($row, column: 'updated_at'),
        ];
    }
    return $forms;
}

/**
 * Aggregate submission totals + unread totals per form id, in TWO queries
 * (one GROUP BY each). Returns a map keyed by form id; missing forms read
 * as 0. Keeps list-forms at O(2) queries regardless of page size.
 *
 * @param list<int> $ids
 *
 * @return array<int, array{total:int, unread:int}>
 */
function ff_list_forms_submission_counts(array $ids): array
{
    $out = [];
    if ($ids === [] || !function_exists('wpFluent')) {
        return $out;
    }
    foreach ($ids as $id) {
        $out[$id] = ['total' => 0, 'unread' => 0];
    }

    foreach (ff_count_query($ids, status: null) as $row) {
        $form_id = ff_row_int($row, column: 'form_id');
        if ($form_id > 0 && isset($out[$form_id])) {
            $out[$form_id]['total'] = ff_row_int($row, column: 'total');
        }
    }
    foreach (ff_count_query($ids, status: 'unread') as $row) {
        $form_id = ff_row_int($row, column: 'form_id');
        if ($form_id > 0 && isset($out[$form_id])) {
            $out[$form_id]['unread'] = ff_row_int($row, column: 'total');
        }
    }

    return $out;
}

/**
 * COUNT(*) GROUP BY form_id query against fluentform_submissions, optionally
 * filtered by status. Returns the raw stdClass result rows.
 *
 * @param list<int> $ids
 *
 * @return list<stdClass>
 */
function ff_count_query(array $ids, ?string $status): array
{
    $q = wpFluent()->table('fluentform_submissions')->whereIn('form_id', $ids);
    if ($status !== null) {
        $q = $q->where('status', $status);
    }
    return ff_rows_to_array($q->select('form_id')->selectRaw('COUNT(*) as total')->groupBy('form_id')->get());
}
