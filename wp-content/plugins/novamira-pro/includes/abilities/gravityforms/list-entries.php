<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const NVP_GRAVITYFORMS_ENTRIES_LIMIT_MAX = 200;

const NVP_GRAVITYFORMS_ENTRIES_LIMIT_DEFAULT = 25;

wp_register_ability('novamira/gravityforms-list-entries', [
    'label' => __('List Gravity Forms Entries', domain: 'novamira-pro'),
    'description' => __(
        'List entries (form submissions) for one form. Returns compact rows (id, form_id, status, date_created, date_updated, is_read, is_starred, ip) — call get-entry to read a single entry\'s field values. Paginated with limit (default 25, max 200) and offset; filterable by status (active | spam | trash | any) and a date range.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Form id to read entries from.'],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NVP_GRAVITYFORMS_ENTRIES_LIMIT_MAX,
                'default' => NVP_GRAVITYFORMS_ENTRIES_LIMIT_DEFAULT,
            ],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            'status' => [
                'type' => 'string',
                'enum' => ['any', 'active', 'spam', 'trash'],
                'default' => 'active',
                'description' => 'Filter by status. "active" excludes spam + trash; "any" returns all statuses.',
            ],
            'date_from' => [
                'type' => 'string',
                'description' => 'Optional ISO-8601 date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS) — entries created on or after this timestamp.',
            ],
            'date_to' => [
                'type' => 'string',
                'description' => 'Optional ISO-8601 date — entries created on or before this timestamp.',
            ],
            'orderby' => [
                'type' => 'string',
                'enum' => ['id', 'date_created', 'date_updated'],
                'default' => 'date_created',
            ],
            'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'total' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'entries' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'form_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string'],
                        'date_created' => ['type' => 'string'],
                        'date_updated' => ['type' => 'string'],
                        'is_read' => ['type' => 'boolean'],
                        'is_starred' => ['type' => 'boolean'],
                        'ip' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'required' => ['form_id', 'total', 'limit', 'offset', 'entries'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_list_entries',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns compact entry rows (no field values — call get-entry for those). status="active" by default excludes spam + trash. Default limit is 25, max 200 — paginate with offset for larger sets.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     limit?: int,
 *     offset?: int,
 *     status?: string,
 *     date_from?: string,
 *     date_to?: string,
 *     orderby?: string,
 *     order?: string,
 * } $input
 * @return array{form_id: int, total: int, limit: int, offset: int, entries: list<array<string, mixed>>}|WP_Error
 */
function gravityforms_list_entries(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_view_entries']);
    if ($cap !== null) {
        return $cap;
    }

    $form_id = (int) $form['id'];
    $limit = isset($input['limit'])
        ? (int) max(1, min(NVP_GRAVITYFORMS_ENTRIES_LIMIT_MAX, (int) $input['limit']))
        : NVP_GRAVITYFORMS_ENTRIES_LIMIT_DEFAULT;
    $offset = isset($input['offset']) ? (int) max(0, (int) $input['offset']) : 0;
    $status = $input['status'] ?? 'active';
    $orderby = $input['orderby'] ?? 'date_created';
    $order = strtoupper($input['order'] ?? 'desc') === 'ASC' ? 'ASC' : 'DESC';

    $search_criteria = ['status' => $status === 'any' ? '' : $status];
    if (isset($input['date_from'])) {
        $search_criteria['start_date'] = $input['date_from'];
    }
    if (isset($input['date_to'])) {
        $search_criteria['end_date'] = $input['date_to'];
    }

    $sorting = ['key' => $orderby, 'direction' => $order];
    $paging = ['offset' => $offset, 'page_size' => $limit];

    $total = 0;
    $entries = \GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging, $total);
    if ($entries instanceof WP_Error) {
        return $entries;
    }

    $rows = [];
    foreach ($entries as $entry) {
        $rows[] = gravityforms_entry_row($entry);
    }

    return [
        'form_id' => $form_id,
        'total' => (int) $total,
        'limit' => $limit,
        'offset' => $offset,
        'entries' => $rows,
    ];
}
