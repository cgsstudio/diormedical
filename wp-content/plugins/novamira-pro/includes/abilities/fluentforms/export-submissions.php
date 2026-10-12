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

wp_register_ability('novamira/fluentforms-export-submissions', [
    'label' => __('Export Fluent Forms Submissions', domain: 'novamira-pro'),
    'description' => __(
        "Export up to 100 submissions per call as full rows (compact metadata + mapped response triples + optional payment block). Use the cursor pattern to page through the full dataset: pass the previous response's `next_cursor` value as `cursor` to fetch the next batch. Returns {rows, returned, next_cursor, truncated_at_id?}. Stops early and sets truncated_at_id when the cumulative serialised payload exceeds ~200 KiB to keep the response under MCP envelope limits — caller resumes from that id with cursor=<truncated_at_id>. Required: form_id. Optional: status, payment_status, from / to (ISO dates), fields[] whitelist, max_field_bytes (default 2 KiB for export, lower than get-submission's 4 KiB), limit (default 50, max 100), cursor (submission id from previous batch). Capability: fluentform_entries_viewer.",
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
            'from' => ['type' => 'string'],
            'to' => ['type' => 'string'],
            'fields' => ['type' => 'array', 'items' => ['type' => 'string']],
            'max_field_bytes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 65_536, 'default' => 2048],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'cursor' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Submission id from the previous batch. Pass 0 (or omit) for the first call.',
                'default' => 0,
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'rows' => ['type' => 'array', 'items' => ['type' => 'object']],
            'returned' => ['type' => 'integer'],
            'next_cursor' => ['type' => ['integer', 'null']],
            'truncated_at_id' => ['type' => ['integer', 'null']],
        ],
        'required' => ['rows', 'returned', 'next_cursor', 'truncated_at_id'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_export_submissions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Cursor-based pagination — pass next_cursor from one call as cursor in the next. The first call uses cursor=0 (default) and walks ascending by id. When the response carries truncated_at_id, the batch was cut short to fit MCP limits — resume from that id. Capability: fluentform_entries_viewer.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

const FF_EXPORT_PAYLOAD_BUDGET_BYTES = 200_000;

/**
 * @param array<string, mixed> $input
 *
 * @return array{rows: list<array<string, mixed>>, returned: int, next_cursor: ?int, truncated_at_id: ?int}|WP_Error
 */
function fluentforms_export_submissions(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $form_id = isset($input['form_id']) && is_int($input['form_id']) && $input['form_id'] >= 1 ? $input['form_id'] : 0;
    if ($form_id <= 0) {
        return ff_invalid('form_id is required and must be a positive integer.');
    }
    // Distinguish "form does not exist" from "form exists, zero submissions" —
    // both otherwise returned an empty success payload.
    $form = ff_form_or_error($form_id);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = ff_capability_or_error('fluentform_entries_viewer', $form_id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $filters = ff_export_submissions_parse_input($input, $form_id);
    $ids = ff_export_submissions_query($filters);

    return ff_export_submissions_build($ids, $filters);
}

/**
 * @param array<string, mixed> $input
 *
 * @return array{form_id:int,status:string,payment_status:string,from:string,to:string,fields:?list<string>,max_field_bytes:int,limit:int,cursor:int}
 */
function ff_export_submissions_parse_input(array $input, int $form_id): array
{
    $max_raw = ff_input_int_or($input, key: 'max_field_bytes', default: 2048, min: 1);
    $fields_raw = isset($input['fields']) && is_array($input['fields']) ? array_values($input['fields']) : null;
    return [
        'form_id' => $form_id,
        'status' => ff_input_str($input, key: 'status'),
        'payment_status' => ff_input_str($input, key: 'payment_status'),
        'from' => ff_input_str($input, key: 'from'),
        'to' => ff_input_str($input, key: 'to'),
        'fields' => $fields_raw === null ? null : ff_normalize_field_names($fields_raw),
        'max_field_bytes' => min(ff_absolute_max_field_bytes(), $max_raw),
        'limit' => max(1, min(100, ff_input_int_or($input, key: 'limit', default: 50))),
        'cursor' => ff_input_int_or($input, key: 'cursor', default: 0),
    ];
}

/**
 * Number of full submission rows loaded per batch in the build step. Bounds
 * peak memory to this many `response` blobs at once (the byte-budget usually
 * stops well before the limit), while keeping the query count to
 * ceil(limit / chunk) — a handful, never the per-row N+1 round 2 removed.
 */
const FF_EXPORT_CHUNK_SIZE = 25;

/**
 * Fetch the candidate submission IDS (ascending by id, after the cursor,
 * capped by `limit`). Only ids are selected here; the heavy `response` column
 * is loaded in small batches during the build step so a large-response store
 * cannot OOM the request by loading every `limit` row's full body up front.
 *
 * @param array{form_id:int,status:string,payment_status:string,from:string,to:string,fields:?list<string>,max_field_bytes:int,limit:int,cursor:int} $filters
 *
 * @return list<int>
 */
function ff_export_submissions_query(array $filters): array
{
    if (!function_exists('wpFluent')) {
        return [];
    }
    $q = wpFluent()->table('fluentform_submissions')->where('form_id', $filters['form_id']);
    if ($filters['status'] !== '') {
        $q = $q->where('status', $filters['status']);
    }
    if ($filters['payment_status'] !== '') {
        $q = $q->where('payment_status', $filters['payment_status']);
    }
    if ($filters['from'] !== '') {
        $q = $q->where('created_at', '>=', $filters['from'] . ' 00:00:00');
    }
    if ($filters['to'] !== '') {
        $q = $q->where('created_at', '<=', $filters['to'] . ' 23:59:59');
    }
    if ($filters['cursor'] > 0) {
        $q = $q->where('id', '>', $filters['cursor']);
    }
    $rows = ff_rows_to_array($q->select('id')->orderBy('id', direction: 'asc')->take($filters['limit'])->get());
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
 * Load a batch of full submission rows by id, ascending. Kept small (a chunk)
 * so at most FF_EXPORT_CHUNK_SIZE `response` blobs are resident at once.
 *
 * @param list<int> $ids
 *
 * @return list<stdClass>
 */
function ff_export_submissions_load_chunk(array $ids): array
{
    if ($ids === [] || !function_exists('wpFluent')) {
        return [];
    }
    return ff_rows_to_array(
        wpFluent()->table('fluentform_submissions')->whereIn('id', $ids)->orderBy('id', direction: 'asc')->get(),
    );
}

/**
 * Load the candidate ids in small batches, materialise each via
 * ff_submission_full, accumulate up to the payload-budget cap, and set the
 * next_cursor / truncated_at_id. Loading a chunk at a time (rather than every
 * `limit` row up front) keeps peak memory bounded when submissions carry large
 * `response` bodies; the query count stays at ceil(limit / chunk).
 *
 * @param list<int> $ids
 * @param array{form_id:int,status:string,payment_status:string,from:string,to:string,fields:?list<string>,max_field_bytes:int,limit:int,cursor:int} $filters
 *
 * @return array{rows: list<array<string, mixed>>, returned: int, next_cursor: ?int, truncated_at_id: ?int}
 */
function ff_export_submissions_build(array $ids, array $filters): array
{
    $out = [];
    $running_bytes = 0;
    $last_id = $filters['cursor'];
    $truncated_at = null;

    foreach (array_chunk($ids, FF_EXPORT_CHUNK_SIZE) as $chunk) {
        foreach (ff_export_submissions_load_chunk($chunk) as $row) {
            $id = ff_row_id($row);
            if ($id <= 0) {
                continue;
            }
            $sub = ff_submission_from_row($row);
            $payload = ff_submission_full($sub, $filters['fields'], $filters['max_field_bytes']);
            $encoded = wp_json_encode($payload);
            $size = is_string($encoded) ? strlen($encoded) : 0;
            if ($out !== [] && ($running_bytes + $size) > FF_EXPORT_PAYLOAD_BUDGET_BYTES) {
                $truncated_at = $id;
                break 2;
            }
            $out[] = $payload;
            $running_bytes += $size;
            $last_id = $id;
        }
    }

    // Cursor uses > so we pass the id of the last row that DID fit. Set it
    // when we either hit the byte budget (truncated_at is non-null) OR when
    // we filled the full limit (more rows likely exist). Otherwise null.
    $more_likely = $truncated_at !== null || count($out) === $filters['limit'];
    $next_cursor = $more_likely ? $last_id : null;

    return [
        'rows' => $out,
        'returned' => count($out),
        'next_cursor' => $next_cursor,
        'truncated_at_id' => $truncated_at,
    ];
}
