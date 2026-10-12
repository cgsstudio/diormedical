<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmForm;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Hard cap on the matching row pool the ability is willing to load into
 * memory before slicing into the requested page. Reality: typical Formidable
 * installs have well under 500 forms; the cap exists to keep the response
 * bounded on pathological installs (form-builder farms, leaked test data).
 * When the cap is hit, the response advertises `pagination.truncated=true`
 * so the agent knows the `total` is a floor, not the actual row count.
 */
const FRM_LIST_FORMS_HARD_CAP = 5000;

const FRM_LIST_FORMS_DEFAULT_PER_PAGE = 50;

const FRM_LIST_FORMS_MAX_PER_PAGE = 500;

wp_register_ability('novamira/formidable-list-forms', [
    'label' => __('List Formidable Forms', domain: 'novamira-pro'),
    'description' => __(
        'Lists Formidable forms with a compact summary per row (id, form_key, name, description, status, parent_form_id, is_template, created_at). Supports filtering by status (`published` / `draft` / `trash` / `any` — default `published`), by template flag (`is_template`), by parent_form_id (for repeater child forms), and by a substring match on the form name (`search`). Paginated: default `per_page=50`, max 500. Use this BEFORE get-form to pick a form id; the full settings blob (options, fields[], actions) ships from get-form, never from this surface.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match on the form name. Omit to return every form matching the other filters.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'draft', 'trash', 'any'],
                'description' => 'Form status filter. Default `published`. Use `any` to include drafts and trashed forms together; use `trash` to enumerate the soft-deleted forms (e.g. before hard-deleting them).',
                'default' => 'published',
            ],
            'is_template' => [
                'type' => 'boolean',
                'description' => 'When `true`, returns Formidable form-templates (rows with `is_template=1`); when `false` (default), returns only non-template forms.',
                'default' => false,
            ],
            'parent_form_id' => [
                'type' => 'integer',
                'description' => 'When supplied, returns ONLY forms whose `parent_form_id` matches the value: pass `0` for top-level forms ONLY, or a non-zero parent id to enumerate the Repeater-child sub-forms of that parent. OMIT the parameter to apply NO parent filter — the response then contains both top-level forms AND any Repeater child forms mixed together.',
                'minimum' => 0,
            ],
            'page' => [
                'type' => 'integer',
                'description' => '1-indexed page number. Default 1.',
                'minimum' => 1,
                'default' => 1,
            ],
            'per_page' => [
                'type' => 'integer',
                'description' => 'Rows per page. Default 50, hard maximum 500.',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 50,
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
                        'form_key' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'parent_form_id' => ['type' => 'integer'],
                        'is_template' => ['type' => 'boolean'],
                        'created_at' => ['type' => 'string'],
                    ],
                    'required' => [
                        'id',
                        'form_key',
                        'name',
                        'description',
                        'status',
                        'parent_form_id',
                        'is_template',
                        'created_at',
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
                    'truncated' => ['type' => 'boolean'],
                ],
                'required' => ['page', 'per_page', 'total', 'total_pages', 'truncated'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['forms', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_list_forms',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Returns a compact summary per form (id, form_key, name, description, status, parent_form_id, is_template, created_at). The `form_key` slug is the human-friendly identifier you'll see in Formidable shortcodes; the numeric `id` is what every other ability accepts. Default filters return non-template, published forms at ALL nesting levels (top-level + Repeater child sub-forms). To restrict to top-level forms ONLY, pass `parent_form_id: 0`; pass a non-zero id to enumerate the children of a specific parent. Pagination is 1-indexed (`page` / `per_page`, max 500). When `pagination.truncated=true` the matching pool exceeded the in-memory cap of 5000 — narrow the filters or paginate to reach lower-id forms. NEVER fetch the full fields[] / settings blob via this surface; call get-form for that.",
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     search?: string,
 *     status?: string,
 *     is_template?: bool,
 *     parent_form_id?: int,
 *     page?: int,
 *     per_page?: int,
 * } $input
 * @return array{
 *     forms: list<array{
 *         id: int,
 *         form_key: string,
 *         name: string,
 *         description: string,
 *         status: string,
 *         parent_form_id: int,
 *         is_template: bool,
 *         created_at: string,
 *     }>,
 *     pagination: array{page: int, per_page: int, total: int, total_pages: int, truncated: bool},
 * }|WP_Error
 */
function formidable_list_forms(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_view_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $page = max(1, frm_to_int($input['page'] ?? 1));
    $per_page_raw = frm_to_int($input['per_page'] ?? FRM_LIST_FORMS_DEFAULT_PER_PAGE);
    if ($per_page_raw <= 0) {
        $per_page_raw = FRM_LIST_FORMS_DEFAULT_PER_PAGE;
    }
    $per_page = min(FRM_LIST_FORMS_MAX_PER_PAGE, $per_page_raw);

    $status = $input['status'] ?? 'published';
    if (!in_array($status, ['published', 'draft', 'trash', 'any'], strict: true)) {
        return frm_invalid_input('status must be one of: published, draft, trash, any.');
    }

    $is_template = $input['is_template'] ?? false;
    $search = isset($input['search']) ? trim($input['search']) : '';
    $parent = isset($input['parent_form_id']) ? frm_to_int($input['parent_form_id']) : null;

    /** @var array<string, mixed> $where */
    $where = [];
    if ($status !== 'any') {
        $where['status'] = $status;
    }
    $where['is_template'] = $is_template ? 1 : 0;
    if ($parent !== null) {
        $where['parent_form_id'] = $parent;
    }
    if ($search !== '') {
        // `name LIKE` is the FrmDb idiom for substring matching — the model
        // wraps the value in `%...%` automatically. Form names are never long
        // enough to OOM; the per_page cap below bounds the response size.
        $where['name LIKE'] = $search;
    }

    /** @var list<\stdClass> $all */
    $all = FrmForm::getAll($where, 'id DESC', FRM_LIST_FORMS_HARD_CAP);
    $total = count($all);
    $offset = ($page - 1) * $per_page;
    $page_rows = array_slice($all, $offset, $per_page);

    return [
        'forms' => array_map(static fn(\stdClass $row): array => frm_form_to_compact_row($row), $page_rows),
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => (int) ceil($total / $per_page),
            'truncated' => $total === FRM_LIST_FORMS_HARD_CAP,
        ],
    ];
}
