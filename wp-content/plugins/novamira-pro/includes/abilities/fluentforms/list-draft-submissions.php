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

wp_register_ability('novamira/fluentforms-list-draft-submissions', [
    'label' => __('List Fluent Forms draft submissions', domain: 'novamira-pro'),
    'description' => __(
        'List save-and-resume draft submissions (partial entries the user has not yet finalised). Filterable by form_id, user_id, used (true = the user resumed and submitted; false = abandoned drafts). Compact rows include id, form_id, hash (resume token), type, step_completed, user_id, source_url, browser, device, used, created_at, updated_at. Per_page capped at 100. Capability: fluentform_entries_viewer (per-form when form_id is supplied).',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'user_id' => ['type' => 'integer', 'minimum' => 0],
            'used' => ['type' => 'boolean'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'draft_submissions' => ['type' => 'array', 'items' => ['type' => 'object']],
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
        'required' => ['draft_submissions', 'pagination'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_list_draft_submissions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Capability: fluentform_entries_viewer. To delete a draft use novamira/fluentforms-delete-draft-submission.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{draft_submissions:list<array<string, mixed>>, pagination:array{page:int,per_page:int,total:int,total_pages:int}}|WP_Error
 */
function fluentforms_list_draft_submissions(array $input): array|WP_Error
{
    $pro = ff_pro_required('draft_submissions');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $filters = ff_list_drafts_parse_input($input);
    $cap = ff_capability_or_error('fluentform_entries_viewer', form_id: $filters['form_id']);
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    [$rows, $total] = ff_list_drafts_query($filters);
    return [
        'draft_submissions' => ff_list_drafts_rows($rows),
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
 * @return array{form_id:?int,user_id:?int,used:?bool,page:int,per_page:int}
 */
function ff_list_drafts_parse_input(array $input): array
{
    return [
        'form_id' => ff_input_pos_int($input, key: 'form_id'),
        'user_id' => ff_input_non_neg_int($input, key: 'user_id'),
        'used' => ff_input_nullable_bool($input, key: 'used'),
        'page' => ff_input_int_or($input, key: 'page', default: 1, min: 1),
        'per_page' => max(1, min(100, ff_input_int_or($input, key: 'per_page', default: 25))),
    ];
}

/**
 * @param array{form_id:?int,user_id:?int,used:?bool,page:int,per_page:int} $filters
 *
 * @return array{0:list<stdClass>, 1:int}
 */
function ff_list_drafts_query(array $filters): array
{
    if (!function_exists('wpFluent')) {
        return [[], 0];
    }
    $q = wpFluent()->table('fluentform_draft_submissions');
    if ($filters['form_id'] !== null) {
        $q = $q->where('form_id', $filters['form_id']);
    }
    if ($filters['user_id'] !== null) {
        $q = $q->where('user_id', $filters['user_id']);
    }
    if ($filters['used'] !== null) {
        $q = $q->where('used', $filters['used'] ? 1 : 0);
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
function ff_list_drafts_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (ff_row_id($row) <= 0) {
            continue;
        }
        $out[] = ff_draft_row($row);
    }
    return $out;
}
