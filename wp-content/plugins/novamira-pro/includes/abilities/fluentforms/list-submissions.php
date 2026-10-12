<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use FluentForm\Framework\Database\Query\Builder;
use stdClass;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-list-submissions', [
    'label' => __('List Fluent Forms Submissions', domain: 'novamira-pro'),
    'description' => __(
        "List Fluent Forms submissions (entries) with optional filters. Returns compact rows (id, form_id, status, user_id, payment_status, payment_method, currency, payment_total, source_url, ip, browser, device, country, is_favourite, created_at, updated_at) plus pagination metadata (page, per_page, total, total_pages). The submission's full `response` JSON is NOT included — fetch it with novamira/fluentforms-get-submission when needed (progressive disclosure). Payment fields are null on submissions without a payment + null on free FF installs. Filters: form_id, status (unread|read|spam|trashed — omit to list all non-trashed entries, matching FF's own entries list; pass status=trashed to see the trash), payment_status (paid|pending|failed|refunded), is_favourite (bool), search (LIKE on source_url + ip + browser), from / to (created_at ISO date range), user_id (the WP user who submitted, when logged in). Default order is created_at DESC; pagination is integer page-based, per_page capped at 100.",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'status' => ['type' => 'string', 'enum' => ['', 'unread', 'read', 'spam', 'trashed']],
            'payment_status' => ['type' => 'string'],
            'is_favourite' => ['type' => 'boolean'],
            'search' => ['type' => 'string', 'default' => ''],
            'from' => ['type' => 'string', 'description' => 'ISO date (Y-m-d) lower bound on created_at.'],
            'to' => ['type' => 'string', 'description' => 'ISO date (Y-m-d) upper bound on created_at.'],
            'user_id' => ['type' => 'integer', 'minimum' => 0],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'submissions' => ['type' => 'array', 'items' => ['type' => 'object']],
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
        'required' => ['submissions', 'pagination'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_list_submissions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact list — never embeds the response JSON. Call novamira/fluentforms-get-submission with the row id to inspect full responses. Payment fields surface only when the submission has payment data AND Pro is loaded. Capability: fluentform_entries_viewer.',
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
 *     submissions: list<array<string, mixed>>,
 *     pagination: array{page:int, per_page:int, total:int, total_pages:int},
 * }|WP_Error
 */
function fluentforms_list_submissions(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $filters = ff_list_submissions_parse_input($input);
    // When a form_id is supplied, confirm it exists so a nonexistent id
    // returns fluentforms_form_not_found instead of an empty success payload
    // indistinguishable from "form exists, zero submissions".
    if ($filters['form_id'] !== null) {
        $form = ff_form_or_error($filters['form_id']);
        if ($form instanceof WP_Error) {
            return $form;
        }
    }
    // Per-form capability check when form_id is supplied — team-mode users
    // get correctly gated. When form_id is omitted (global listing) the
    // check stays global, which is the only safe behaviour: any narrower
    // gate would silently truncate the result set. Round-2 audit CRITICAL.
    $cap = ff_capability_or_error('fluentform_entries_viewer', form_id: $filters['form_id']);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    [$rows, $total] = ff_list_submissions_query($filters);

    return [
        'submissions' => ff_list_submissions_rows($rows),
        'pagination' => [
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total' => $total,
            'total_pages' => (int) ceil($total / $filters['per_page']),
        ],
    ];
}

/**
 * Normalise caller input into a typed filter shape.
 *
 * @param array<string, mixed> $input
 *
 * @return array{form_id:?int,status:string,payment_status:string,is_favourite:?bool,search:string,from:string,to:string,user_id:?int,page:int,per_page:int}
 */
function ff_list_submissions_parse_input(array $input): array
{
    return [
        'form_id' => ff_input_pos_int($input, key: 'form_id'),
        'status' => ff_input_str($input, key: 'status'),
        'payment_status' => ff_input_str($input, key: 'payment_status'),
        'is_favourite' => ff_input_nullable_bool($input, key: 'is_favourite'),
        'search' => trim(ff_input_str($input, key: 'search')),
        'from' => ff_input_str($input, key: 'from'),
        'to' => ff_input_str($input, key: 'to'),
        'user_id' => ff_input_non_neg_int($input, key: 'user_id'),
        'page' => ff_input_int_or($input, key: 'page', default: 1, min: 1),
        'per_page' => max(1, min(100, ff_input_int_or($input, key: 'per_page', default: 25))),
    ];
}

/**
 * Run the COUNT + SELECT pair against fluentform_submissions.
 *
 * @param array{form_id:?int,status:string,payment_status:string,is_favourite:?bool,search:string,from:string,to:string,user_id:?int,page:int,per_page:int} $filters
 *
 * @return array{0: list<stdClass>, 1: int}
 */
function ff_list_submissions_query(array $filters): array
{
    if (!function_exists('wpFluent')) {
        return [[], 0];
    }
    $base = ff_list_submissions_apply_filters(wpFluent()->table('fluentform_submissions'), $filters);
    $total = (clone $base)->count();
    $rows = ff_rows_to_array(
        $base
            // Project only the compact columns. The `response` column (the full
            // submission JSON, which can be hundreds of KB with file uploads /
            // repeaters / long text) is NEVER part of the compact row, but a
            // SELECT * hydrated it for every one of up to per_page=100 rows and
            // then discarded it — OOM at a realistic web memory_limit. Reading
            // only what we return keeps peak memory flat regardless of payload.
            ->select(...FF_SUBMISSION_COMPACT_COLUMNS)
            ->orderBy('created_at', direction: 'desc')
            ->skip(($filters['page'] - 1) * $filters['per_page'])
            ->take($filters['per_page'])
            ->get(),
    );
    return [$rows, $total];
}

/**
 * Apply the filter shape as a chain of where() calls.
 *
 * @param array{form_id:?int,status:string,payment_status:string,is_favourite:?bool,search:string,from:string,to:string,user_id:?int,page:int,per_page:int} $filters
 */
function ff_list_submissions_apply_filters(Builder $q, array $filters): Builder
{
    if ($filters['form_id'] !== null) {
        $q = $q->where('form_id', $filters['form_id']);
    }
    // Match FF's own entry list: trashed submissions are hidden unless the
    // caller explicitly asks for status=trashed.
    $q = $filters['status'] !== '' ? $q->where('status', $filters['status']) : $q->where('status', '!=', 'trashed');
    if ($filters['payment_status'] !== '') {
        $q = $q->where('payment_status', $filters['payment_status']);
    }
    if ($filters['is_favourite'] !== null) {
        $q = $q->where('is_favourite', $filters['is_favourite'] ? 1 : 0);
    }
    if ($filters['user_id'] !== null) {
        $q = $q->where('user_id', $filters['user_id']);
    }
    if ($filters['from'] !== '') {
        $q = $q->where('created_at', '>=', $filters['from'] . ' 00:00:00');
    }
    if ($filters['to'] !== '') {
        $q = $q->where('created_at', '<=', $filters['to'] . ' 23:59:59');
    }
    if ($filters['search'] !== '') {
        global $wpdb;
        // esc_like so a search containing % or _ is matched literally instead
        // of acting as a LIKE wildcard. Group the OR so it does not widen an
        // outer AND filter (form_id, status, …).
        $escaped = $wpdb instanceof \wpdb ? $wpdb->esc_like($filters['search']) : $filters['search'];
        $like = '%' . $escaped . '%';
        $q = $q->where(static function (Builder $sub) use ($like): void {
            $sub->where('source_url', 'LIKE', $like)->orWhere('ip', 'LIKE', $like)->orWhere('browser', 'LIKE', $like);
        });
    }
    return $q;
}

/**
 * Project the SELECT rows to compact rows. Hydrates each stdClass
 * directly into a Submission via ff_submission_from_row — round-2 audit
 * CRITICAL flagged the previous Submission::find($id) per-row pattern as
 * an N+1 query (27 queries for per_page=25). The stdClass already carries
 * every column we project, so no extra DB hit is needed.
 *
 * @param list<stdClass> $rows
 *
 * @return list<array<string, mixed>>
 */
function ff_list_submissions_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (ff_row_id($row) <= 0) {
            continue;
        }
        $out[] = ff_submission_row(ff_submission_from_row($row));
    }
    return $out;
}
