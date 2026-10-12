<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const NVP_WPFORMS_ENTRY_LIST_LIMIT_MAX = 500;

const NVP_WPFORMS_ENTRY_LIST_LIMIT_DEFAULT = 50;

wp_register_ability('novamira/wpforms-list-entries', [
    'label' => __('List WPForms Entries', domain: 'novamira-pro'),
    'description' => __(
        'List WPForms form submissions (entries) with compact rows. Filterable by form_id, status (publish | spam | trash | partial), and date range. Paginated with limit (default 50, max 500) and offset. WPForms Pro only — entries do not exist on Lite. Use get-entry to read a single entry\'s full field values.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Required: the form whose entries to list. Per-form capability enforcement requires a concrete form_id; cross-form listing is not exposed (round-1 audit CRITICAL — capability bypass when form_id was 0).',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['any', 'publish', 'spam', 'trash', 'partial'],
                'default' => 'any',
                'description' => 'Entry status filter. "publish" = the empty status string WPForms stores for normal entries.',
            ],
            'date_after' => [
                'type' => 'string',
                'description' => 'ISO-8601 lower bound (inclusive) for entry.date.',
            ],
            'date_before' => [
                'type' => 'string',
                'description' => 'ISO-8601 upper bound (inclusive) for entry.date.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NVP_WPFORMS_ENTRY_LIST_LIMIT_MAX,
                'default' => NVP_WPFORMS_ENTRY_LIST_LIMIT_DEFAULT,
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'entries' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'entry_id' => ['type' => 'integer'],
                        'form_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'viewed' => ['type' => 'boolean'],
                        'starred' => ['type' => 'boolean'],
                        'date' => ['type' => 'string'],
                        'date_modified' => ['type' => 'string'],
                        'user_id' => ['type' => 'integer'],
                    ],
                    'required' => [
                        'entry_id',
                        'form_id',
                        'status',
                        'type',
                        'viewed',
                        'starred',
                        'date',
                        'date_modified',
                        'user_id',
                    ],
                ],
            ],
        ],
        'required' => ['total', 'limit', 'offset', 'entries'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_list_entries',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. status "publish" maps to the empty WPForms status string. The compact row omits the fields blob AND the submitter IP address (PII) — call get-entry per row when you need either. limit caps at 500 — paginate via offset for larger sets.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int,
 *     status?: string,
 *     date_after?: string,
 *     date_before?: string,
 *     limit?: int,
 *     offset?: int,
 * } $input
 * @return array{total: int, limit: int, offset: int, entries: list<array<string, mixed>>}|WP_Error
 */
function wpforms_list_entries(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    if (!wpforms_pro_active()) {
        return wpforms_pro_required('entries');
    }
    // Round-1 audit CRITICAL: form_id is now required (schema-side too) so
    // the per-form `wpforms_user_can` filter chain always fires. Previously
    // a missing form_id collapsed to 0 and the filter was skipped, opening
    // a cross-form entry-disclosure path. Resolve via wpforms_form_or_error
    // up-front so a stale/missing form returns 404 instead of an empty list.
    $form_id = (int) ($input['form_id'] ?? 0);
    if ($form_id <= 0) {
        return wpforms_invalid_input('form_id is required.');
    }
    $form = wpforms_form_or_error($form_id);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_view_entries', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }
    $handler = wpforms_entry_handler();
    if ($handler === null) {
        return wpforms_pro_required('entries');
    }

    $limit = isset($input['limit'])
        ? max(1, min(NVP_WPFORMS_ENTRY_LIST_LIMIT_MAX, (int) $input['limit']))
        : NVP_WPFORMS_ENTRY_LIST_LIMIT_DEFAULT;
    $offset = isset($input['offset']) ? max(0, (int) $input['offset']) : 0;
    $args = wpforms_list_entries_args($input, $limit, $offset);

    /** @var mixed $raw_total */
    $raw_total = $handler->get_entries($args, count: true);
    $total = is_numeric($raw_total) ? (int) $raw_total : 0;

    /** @var mixed $raw_rows */
    $raw_rows = $handler->get_entries($args, count: false);

    return [
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'entries' => wpforms_list_entries_rows(is_array($raw_rows) ? $raw_rows : []),
    ];
}

/**
 * Build the args array passed to WPForms_Entry_Handler::get_entries() from the
 * agent input. Split out so wpforms_list_entries() stays under mago's cyclomatic
 * threshold as new filters accumulate.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wpforms_list_entries_args(array $input, int $limit, int $offset): array
{
    $args = [
        'number' => $limit,
        'offset' => $offset,
    ];
    if (isset($input['form_id'])) {
        $args['form_id'] = (int) $input['form_id'];
    }
    $status = (string) ($input['status'] ?? 'any');
    if ($status !== 'any') {
        // WPForms stores normal entries with an EMPTY status string; "publish"
        // is the public alias we expose to callers.
        $args['status'] = $status === 'publish' ? '' : $status;
    }
    if (isset($input['date_after']) && $input['date_after'] !== '') {
        $args['date_after'] = $input['date_after'];
    }
    if (isset($input['date_before']) && $input['date_before'] !== '') {
        $args['date_before'] = $input['date_before'];
    }
    return $args;
}

/**
 * Map a list of raw entry rows (mixed-typed because the entry handler returns
 * stdClass[]) into the compact list-row shape.
 *
 * @param array<array-key, mixed> $rows
 * @return list<array<string, mixed>>
 */
function wpforms_list_entries_rows(array $rows): array
{
    /** @var list<array<string, mixed>> $entries */
    $entries = [];
    /** @var mixed $row */
    foreach ($rows as $row) {
        if (!is_object($row)) {
            continue;
        }
        $entries[] = wpforms_entry_compact_row($row);
    }
    return $entries;
}
