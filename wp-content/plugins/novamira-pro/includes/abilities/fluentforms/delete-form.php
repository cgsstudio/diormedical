<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-delete-form', [
    'label' => __('Delete a Fluent Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Permanently delete a Fluent Forms form. Required: form_id. Optional: cascade (default false) — when true, also deletes all submissions, submission_meta, entry_details and form_meta rows associated with the form (irreversible). When false (default) the form row is deleted but related data stays orphaned in the FF tables. Returns {success: true, form_id, cascaded: bool, deleted: {submissions, submission_meta, entry_details, form_meta}}. Returns fluentforms_form_not_found (404) if the id is absent. Capability: fluentform_forms_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'cascade' => [
                'type' => 'boolean',
                'description' => 'When true, also delete all submissions, submission_meta, entry_details, and form_meta rows for the form.',
                'default' => false,
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'form_id' => ['type' => 'integer'],
            'cascaded' => ['type' => 'boolean'],
            'deleted' => [
                'type' => 'object',
                'properties' => [
                    'submissions' => ['type' => 'integer'],
                    'submission_meta' => ['type' => 'integer'],
                    'entry_details' => ['type' => 'integer'],
                    'form_meta' => ['type' => 'integer'],
                ],
                'required' => ['submissions', 'submission_meta', 'entry_details', 'form_meta'],
            ],
        ],
        'required' => ['success', 'form_id', 'cascaded', 'deleted'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_delete_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent — there is no trash bin in Fluent Forms. cascade=false (default) leaves submissions in the DB orphaned by form_id; pass cascade=true to wipe related rows in the same call. Capability: fluentform_forms_manager.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{
 *     success: bool,
 *     form_id: int,
 *     cascaded: bool,
 *     deleted: array{submissions:int, submission_meta:int, entry_details:int, form_meta:int},
 * }|WP_Error
 */
function fluentforms_delete_form(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $form = ff_form_or_error($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = ff_capability_or_error('fluentform_forms_manager', (int) $form->id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form_id = (int) $form->id;
    $cascade = isset($input['cascade']) && $input['cascade'] === true;
    $deleted = ['submissions' => 0, 'submission_meta' => 0, 'entry_details' => 0, 'form_meta' => 0];

    if ($cascade && function_exists('wpFluent')) {
        $deleted = ff_delete_form_related($form_id);
    }

    try {
        $delete_result = $form->delete();
    } catch (Throwable $e) {
        return ff_operation_failed('Failed to delete form: ' . $e->getMessage());
    }
    // Eloquent returns int (rows affected) or bool. Treat 0 / false / null
    // as failure — without this guard (audit round 1 CRITICAL #3) the
    // ability would announce success while the form row still exists.
    if (!$delete_result) {
        return ff_operation_failed('Fluent Forms reported zero rows deleted for the form.');
    }

    ff_after_form_save($form_id, event: 'deleted');
    ff_clear_form_cache($form_id);

    return [
        'success' => true,
        'form_id' => $form_id,
        'cascaded' => $cascade,
        'deleted' => $deleted,
    ];
}

/**
 * Cascade-delete every row a form owns across the FF tables (submissions,
 * submission_meta linked through the submission ids, entry_details,
 * form_meta). Returns the per-table delete counts.
 *
 * @return array{submissions:int, submission_meta:int, entry_details:int, form_meta:int}
 */
function ff_delete_form_related(int $form_id): array
{
    // Collect submission ids first — submission_meta is keyed on
    // response_id (= submission id), not directly on form_id.
    $submission_ids = ff_collect_submission_ids($form_id);

    $submission_meta_deleted = 0;
    if ($submission_ids !== []) {
        $submission_meta_deleted = wpFluent()
            ->table('fluentform_submission_meta')
            ->whereIn('response_id', $submission_ids)
            ->delete();
    }
    $submissions_deleted = wpFluent()->table('fluentform_submissions')->where('form_id', $form_id)->delete();
    $entry_details_deleted = wpFluent()->table('fluentform_entry_details')->where('form_id', $form_id)->delete();
    $form_meta_deleted = wpFluent()->table('fluentform_form_meta')->where('form_id', $form_id)->delete();

    return [
        'submissions' => $submissions_deleted,
        'submission_meta' => $submission_meta_deleted,
        'entry_details' => $entry_details_deleted,
        'form_meta' => $form_meta_deleted,
    ];
}

/**
 * @return list<int>
 */
function ff_collect_submission_ids(int $form_id): array
{
    if (!function_exists('wpFluent')) {
        return [];
    }
    $rows = ff_rows_to_array(
        wpFluent()->table('fluentform_submissions')->where('form_id', $form_id)->select('id')->get(),
    );
    $ids = [];
    foreach ($rows as $row) {
        $id = ff_row_id($row);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return $ids;
}
