<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const NVP_GRAVITYFORMS_LIST_LIMIT_MAX = 500;

const NVP_GRAVITYFORMS_LIST_LIMIT_DEFAULT = 50;

wp_register_ability('novamira/gravityforms-list-forms', [
    'label' => __('List Gravity Forms Forms', domain: 'novamira-pro'),
    'description' => __(
        'List Gravity Forms forms with a compact row per form (id, title, status, dates, entry count). Paginated with limit (default 50, max 500) and offset; filterable by status (active | inactive | trash | any). Use get-form to read a single form\'s full definition (fields, settings, notifications, confirmations).',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NVP_GRAVITYFORMS_LIST_LIMIT_MAX,
                'default' => NVP_GRAVITYFORMS_LIST_LIMIT_DEFAULT,
                'description' => 'Maximum rows to return (1-500).',
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
                'description' => 'Number of rows to skip (for pagination).',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['any', 'active', 'inactive', 'trash'],
                'default' => 'any',
                'description' => 'Filter by status. "any" returns active + inactive (excludes trash). "trash" returns only trashed forms.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Optional title substring filter (case-insensitive).',
            ],
            'orderby' => [
                'type' => 'string',
                'enum' => ['id', 'title', 'date_created', 'date_updated'],
                'default' => 'date_updated',
                'description' => 'Sort key.',
            ],
            'order' => [
                'type' => 'string',
                'enum' => ['asc', 'desc'],
                'default' => 'desc',
                'description' => 'Sort direction.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'forms' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'date_created' => ['type' => 'string'],
                        'date_updated' => ['type' => 'string'],
                        'entry_count' => ['type' => 'integer'],
                    ],
                    'required' => ['id', 'title', 'status', 'date_created', 'date_updated', 'entry_count'],
                ],
            ],
        ],
        'required' => ['total', 'limit', 'offset', 'forms'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_list_forms',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns a compact row per form. Use get-form for the full definition (fields, notifications, confirmations, settings). Entry counts are batched in one query so list-forms stays O(1) in entry-count cost regardless of page size. Default limit is 50, max 500 — paginate via offset for larger sets.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     limit?: int,
 *     offset?: int,
 *     status?: string,
 *     search?: string,
 *     orderby?: string,
 *     order?: string,
 * } $input
 * @return array{
 *     total: int,
 *     limit: int,
 *     offset: int,
 *     forms: list<array{id: int, title: string, status: string, date_created: string, date_updated: string, entry_count: int}>,
 * }|WP_Error
 */
function gravityforms_list_forms(array $input): array|WP_Error
{
    if (!gravityforms_detect_setup()['active']) {
        return gravityforms_not_active();
    }

    $limit = isset($input['limit'])
        ? max(1, min(NVP_GRAVITYFORMS_LIST_LIMIT_MAX, (int) $input['limit']))
        : NVP_GRAVITYFORMS_LIST_LIMIT_DEFAULT;
    $offset = isset($input['offset']) ? max(0, (int) $input['offset']) : 0;
    $status = $input['status'] ?? 'any';
    $orderby = $input['orderby'] ?? 'date_updated';
    $order = strtoupper($input['order'] ?? 'desc') === 'ASC' ? 'ASC' : 'DESC';
    $search = isset($input['search']) ? trim($input['search']) : '';

    // GFAPI::get_forms() ignores pagination, so we pull the matching slice
    // ourselves: gather the candidate set, filter by status + search, sort,
    // then slice. Trashed forms are pulled separately because GFAPI's two
    // booleans (active, trash) can't express "trashed only" — they're a
    // 2x2 matrix where (true,true) means "active AND trashed" not "trashed".
    $forms = gravityforms_collect_forms_for_status($status);
    if ($search !== '') {
        $needle = mb_strtolower($search);
        $forms = array_values(array_filter($forms, static fn(array $f): bool => str_contains(
            mb_strtolower((string) ($f['title'] ?? '')),
            $needle,
        )));
    }
    gravityforms_sort_forms($forms, $orderby, $order);

    $total = count($forms);
    $slice = array_slice($forms, $offset, $limit);

    $form_ids = array_values(array_map(static fn(array $f): int => (int) $f['id'], $slice));
    $counts = gravityforms_form_entry_counts_batch($form_ids);

    $rows = [];
    foreach ($slice as $form) {
        $form_id = (int) $form['id'];
        $rows[] = gravityforms_form_row($form, $counts[$form_id] ?? 0);
    }

    return [
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'forms' => $rows,
    ];
}

/**
 * Pull the candidate form set matching a status filter. GFAPI does NOT
 * expose a "trashed-only" listing in a single call — get_forms($active,
 * $trash) reads as "is_active = $active AND is_trash = $trash". So:
 *   - any:      active=true AND inactive=true, NOT trashed
 *   - active:   active=true, NOT trashed
 *   - inactive: active=false, NOT trashed
 *   - trash:    all rows where is_trash=1 (both active flags)
 *
 * @return list<array<string, mixed>>
 */
function gravityforms_collect_forms_for_status(string $status): array
{
    return match ($status) {
        'active' => \GFAPI::get_forms(active: true, trash: false),
        'inactive' => \GFAPI::get_forms(active: false, trash: false),
        'trash' => array_merge(
            \GFAPI::get_forms(active: true, trash: true),
            \GFAPI::get_forms(active: false, trash: true),
        ),
        default => array_merge(
            \GFAPI::get_forms(active: true, trash: false),
            \GFAPI::get_forms(active: false, trash: false),
        ),
    };
}

/**
 * Sort the forms array in-place by the supported keys.
 *
 * @param list<array<string, mixed>> $forms
 */
function gravityforms_sort_forms(array &$forms, string $orderby, string $order): void
{
    $direction = $order === 'ASC' ? 1 : -1;
    usort($forms, static function (array $a, array $b) use ($orderby, $direction): int {
        $va = (string) ($a[$orderby] ?? '');
        $vb = (string) ($b[$orderby] ?? '');
        if ($orderby === 'id') {
            return ((int) $va <=> (int) $vb) * $direction;
        }
        return strcmp($va, $vb) * $direction;
    });
}
