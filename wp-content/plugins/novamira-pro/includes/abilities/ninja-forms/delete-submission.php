<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-delete-submission', [
    'label' => __('Delete a Ninja Forms Submission', domain: 'novamira-pro'),
    'description' => __(
        'Delete one submission of a form. Required: form_id, submission_id (must belong to form_id). By default the submission is TRASHED (reversible — restore it from the WordPress admin or via novamira/execute-php wp_untrash_post). Pass force=true to PERMANENTLY delete it (the `nf_sub` post + all its `_field_*` postmeta, no undo). Idempotent: deleting an already-missing submission, or re-trashing an already-trashed one, is a no-op that returns deleted=false. Returns {form_id, submission_id, deleted, permanent}.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'submission_id' => ['type' => 'integer', 'minimum' => 1],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'true = permanent delete; false (default) = trash (reversible).',
            ],
        ],
        'required' => ['form_id', 'submission_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'submission_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
            'permanent' => ['type' => 'boolean'],
        ],
        'required' => ['form_id', 'submission_id', 'deleted', 'permanent'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_delete_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default trashes (reversible); force=true permanently deletes (no undo). Idempotent — a missing/already-trashed submission returns deleted=false. Capability: manage_options.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int, submission_id?: int, force?: bool} $input
 *
 * @return array{form_id: int, submission_id: int, deleted: bool, permanent: bool}|WP_Error
 */
function ninja_forms_delete_submission(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form = ninja_forms_form_or_error($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $form_id = (int) $form->get_id();
    $submission_id = ninja_forms_pos_int($input['submission_id'] ?? null);
    $force = ninja_forms_truthy($input['force'] ?? null);

    $submission = ninja_forms_submission_or_error($submission_id, $form_id);
    if ($submission instanceof WP_Error) {
        // Idempotent re-call: a missing submission is a no-op. Invalid input
        // (bad id) still propagates.
        if ($submission->get_error_code() === 'ninja_forms_submission_not_found') {
            return [
                'form_id' => $form_id,
                'submission_id' => $submission_id,
                'deleted' => false,
                'permanent' => $force,
            ];
        }
        return $submission;
    }

    if ($force) {
        $result = wp_delete_post($submission_id, force_delete: true);
        return [
            'form_id' => $form_id,
            'submission_id' => $submission_id,
            'deleted' => $result instanceof \WP_Post,
            'permanent' => true,
        ];
    }

    // Trash (reversible). wp_trash_post returns false when the post is already
    // trashed — reported as a no-op (deleted=false), keeping the call idempotent.
    $result = wp_trash_post($submission_id);
    return [
        'form_id' => $form_id,
        'submission_id' => $submission_id,
        'deleted' => $result instanceof \WP_Post,
        'permanent' => false,
    ];
}
