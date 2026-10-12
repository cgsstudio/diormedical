<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const NINJA_FORMS_LIST_DEFAULT_PER_PAGE = 25;

const NINJA_FORMS_LIST_MAX_PER_PAGE = 200;

wp_register_ability('novamira/ninja-forms-list-forms', [
    'label' => __('List Ninja Forms', domain: 'novamira-pro'),
    'description' => __(
        'List the Ninja Forms on this site, paginated, as compact rows: {id, title, key, fields_count, actions_count, submissions_count, created_at, updated_at}. The field / action / submission counts are accurate live counts (not the cached nf3_forms.subs column). Does NOT return field or action definitions — call ninja-forms-get-form (with include) for one form\'s full shape. Optional `search` filters by title substring. Pagination: `page` (default 1), `per_page` (default 25, max 200). Returns {forms: [...], pagination: {page, per_page, total, total_pages}}.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'search' => ['type' => 'string', 'description' => 'Case-insensitive title substring filter.'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NINJA_FORMS_LIST_MAX_PER_PAGE,
                'default' => NINJA_FORMS_LIST_DEFAULT_PER_PAGE,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'forms' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'key' => ['type' => 'string'],
                        'fields_count' => ['type' => 'integer'],
                        'actions_count' => ['type' => 'integer'],
                        'submissions_count' => ['type' => 'integer'],
                        'created_at' => ['type' => ['string', 'null']],
                        'updated_at' => ['type' => ['string', 'null']],
                    ],
                    'required' => [
                        'id',
                        'title',
                        'key',
                        'fields_count',
                        'actions_count',
                        'submissions_count',
                        'created_at',
                        'updated_at',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'pagination' => [
                'type' => 'object',
                'properties' => [
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'total_pages' => ['type' => 'integer'],
                ],
                'required' => ['page', 'per_page', 'total', 'total_pages'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['forms', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_list_forms',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact overview — no field / action definitions. Use the returned id with ninja-forms-get-form (include=["fields","actions","settings"]) for the full shape. per_page is capped at 200; paginate via page. submissions_count is a live count.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{search?: string, page?: int, per_page?: int} $input
 *
 * @return array{forms: list<array<string, mixed>>, pagination: array{page:int,per_page:int,total:int,total_pages:int}}|WP_Error
 */
function ninja_forms_list_forms(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $page = max(1, ninja_forms_pos_int($input['page'] ?? 1));
    $per_page = ninja_forms_pos_int($input['per_page'] ?? NINJA_FORMS_LIST_DEFAULT_PER_PAGE);
    if ($per_page <= 0) {
        $per_page = NINJA_FORMS_LIST_DEFAULT_PER_PAGE;
    }
    $per_page = min(NINJA_FORMS_LIST_MAX_PER_PAGE, $per_page);
    $search = isset($input['search']) ? ninja_forms_str($input['search']) : '';

    $rows = ninja_forms_list_form_rows($search);
    $total = count($rows);
    $page_rows = array_slice($rows, ($page - 1) * $per_page, $per_page);

    $ids = array_map(static fn(array $r): int => (int) $r['id'], $page_rows);
    $field_counts = ninja_forms_child_count_map('nf3_fields', $ids);
    $action_counts = ninja_forms_child_count_map('nf3_actions', $ids);
    $sub_counts = ninja_forms_submission_count_map($ids);

    $forms = [];
    foreach ($page_rows as $row) {
        $id = (int) $row['id'];
        $forms[] = [
            'id' => $id,
            'title' => $row['title'],
            'key' => $row['key'],
            'fields_count' => $field_counts[$id] ?? 0,
            'actions_count' => $action_counts[$id] ?? 0,
            'submissions_count' => $sub_counts[$id] ?? 0,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    return [
        'forms' => $forms,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
        ],
    ];
}

/**
 * Reads the lightweight form rows (id / title / key / created_at /
 * updated_at) straight from `nf3_forms`, optionally filtered by a
 * case-insensitive title substring. The table name is `$wpdb->prefix` + a
 * trusted literal (no caller input in the query), so the search term is
 * filtered in PHP rather than via a `prepare()`d LIKE — Ninja installs
 * carry few forms, so the full fetch is cheap and keeps the query a pure
 * literal. Ordered by id for stable pagination.
 *
 * @return list<array{id:string,title:string,key:string,created_at:?string,updated_at:?string}>
 */
function ninja_forms_list_form_rows(string $search): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = $wpdb->prefix . 'nf3_forms';
    /** @var list<array<string, mixed>> $results */
    $results = $wpdb->get_results(
        'SELECT id, title, `key`, created_at, updated_at FROM ' . $table . ' ORDER BY id ASC',
        'ARRAY_A',
    );

    $needle = $search !== '' ? mb_strtolower($search) : '';
    $out = [];
    foreach ($results as $r) {
        $title = ninja_forms_str($r['title'] ?? '');
        if ($needle !== '' && !str_contains(mb_strtolower($title), $needle)) {
            continue;
        }
        $out[] = [
            'id' => ninja_forms_str($r['id'] ?? ''),
            'title' => $title,
            'key' => ninja_forms_str($r['key'] ?? ''),
            'created_at' => isset($r['created_at']) && is_string($r['created_at']) ? $r['created_at'] : null,
            'updated_at' => isset($r['updated_at']) && is_string($r['updated_at']) ? $r['updated_at'] : null,
        ];
    }
    return $out;
}
