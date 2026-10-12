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

wp_register_ability('novamira/fluentforms-delete-submission', [
    'label' => __('Delete a Fluent Forms Submission', domain: 'novamira-pro'),
    'description' => __(
        'Permanently delete one Fluent Forms submission, including its submission_meta + entry_details rows. Required: submission_id. Returns {success: true, submission_id, form_id, deleted: {submission, submission_meta, entry_details}}. There is NO trash bin — use novamira/fluentforms-mark-submission with status=trashed for soft-delete semantics. Returns fluentforms_submission_not_found (404) for missing ids. Capability: fluentform_manage_entries.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'submission_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['submission_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'submission_id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
            'deleted' => [
                'type' => 'object',
                'properties' => [
                    'submission' => ['type' => 'integer'],
                    'submission_meta' => ['type' => 'integer'],
                    'entry_details' => ['type' => 'integer'],
                ],
                'required' => ['submission', 'submission_meta', 'entry_details'],
            ],
        ],
        'required' => ['success', 'submission_id', 'form_id', 'deleted'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_delete_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent — there is no trash bin for submissions. For soft-delete use mark-submission status=trashed instead. Capability: fluentform_manage_entries.',
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
 *     submission_id: int,
 *     form_id: int,
 *     deleted: array{submission:int, submission_meta:int, entry_details:int},
 * }|WP_Error
 */
function fluentforms_delete_submission(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $sub = ff_submission_or_error($input['submission_id'] ?? null);
    if ($sub instanceof WP_Error) {
        return $sub;
    }
    $cap = ff_capability_or_error('fluentform_manage_entries', (int) $sub->form_id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $sub_id = (int) $sub->id;
    $form_id = (int) $sub->form_id;

    // Round-2 audit CRITICAL: wpFluent absent is a hard-fail, not a silent
    // partial. Without it we cannot wipe submission_meta + entry_details,
    // and lying with success=true would leak orphan rows.
    if (!function_exists('wpFluent')) {
        return ff_operation_failed('Fluent Forms query builder is unavailable; cannot cascade-delete child rows.');
    }

    // Round-2 audit CRITICAL: delete the PARENT row FIRST. If parent delete
    // fails, the child rows are still intact (and a retry can succeed).
    // The previous order deleted children first, which created orphans
    // when the parent delete failed.
    try {
        $delete_result = $sub->delete();
    } catch (Throwable $e) {
        return ff_operation_failed('Failed to delete submission: ' . $e->getMessage());
    }
    if (!$delete_result) {
        return ff_operation_failed('Fluent Forms reported zero rows deleted for the submission.');
    }

    $deleted = [
        'submission' => 1,
        'submission_meta' => wpFluent()->table('fluentform_submission_meta')->where('response_id', $sub_id)->delete(),
        'entry_details' => wpFluent()->table('fluentform_entry_details')->where('submission_id', $sub_id)->delete(),
    ];

    return [
        'success' => true,
        'submission_id' => $sub_id,
        'form_id' => $form_id,
        'deleted' => $deleted,
    ];
}
