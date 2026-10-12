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

wp_register_ability('novamira/formidable-delete-form', [
    'label' => __('Delete Formidable Form', domain: 'novamira-pro'),
    'description' => __(
        "Deletes a Formidable form. Default `force=false` runs a SOFT delete via FrmForm::trash (the row stays in `wp_frm_forms` with `status='trash'` and a `trash_time` timestamp written into its options blob — restoring is just edit-form{status: 'published'}). Pass `force=true` to HARD delete via FrmForm::destroy — this also cascades on `parent_form_id` so any Repeater child sub-forms are removed, and entries + form_actions tied to the form are removed too. Calling soft-delete twice on the same form is a no-op (deleted=false in the response). Calling force-delete on an already-hard-deleted id returns formidable_form_not_found (404). Use force=true ONLY when you are sure the form's entries are no longer needed — there is no undo.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Numeric form id to delete.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When `true`, hard-deletes via FrmForm::destroy (cascades child forms / entries / form_actions). When `false` (default), soft-deletes via FrmForm::trash.',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'deleted' => [
                'type' => 'boolean',
                'description' => 'True when this call mutated state. False on an idempotent re-call (form was already in the requested state — e.g. soft-deleting an already-trashed form).',
            ],
            'force' => ['type' => 'boolean'],
            'status_after' => [
                'type' => ['string', 'null'],
                'description' => "Form status AFTER the operation. `'trash'` after soft-delete. `null` after force-delete (the row is gone).",
            ],
        ],
        'required' => ['form_id', 'deleted', 'force', 'status_after'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_delete_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Soft-deletes a form by default (status → trash; restorable via edit-form). Pass `force=true` for irreversible hard-delete that cascades child Repeater sub-forms, entries, and form_actions. Re-call is idempotent: soft-deleting an already-trashed form returns deleted=false (no-op); force-deleting a missing id returns formidable_form_not_found (404). Annotated destructive=true — the soft variant is reversible but still mutates state.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, force?: bool} $input
 * @return array{form_id: int, deleted: bool, force: bool, status_after: string|null}|WP_Error
 */
function formidable_delete_form(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form = frm_trashed_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $form_id = (int) $form->id;
    $force = isset($input['force']) && (bool) (int) $input['force'];
    $current_status = (string) ($form->status ?? '');

    if ($force) {
        $deleted_rows = FrmForm::destroy($form_id);
        if (!is_int($deleted_rows) || $deleted_rows <= 0) {
            return frm_error(
                code: 'formidable_delete_failed',
                message: "FrmForm::destroy returned a non-positive row count for form {$form_id}.",
            );
        }
        return [
            'form_id' => $form_id,
            'deleted' => true,
            'force' => true,
            'status_after' => null,
        ];
    }

    if ($current_status === 'trash') {
        // Soft-deleting an already-trashed form is a no-op. We surface this
        // honestly rather than silently re-stamping `trash_time` (which would
        // race with any other concurrent writer trying to read the original
        // trash timestamp).
        return [
            'form_id' => $form_id,
            'deleted' => false,
            'force' => false,
            'status_after' => 'trash',
        ];
    }

    FrmForm::trash($form_id);

    return [
        'form_id' => $form_id,
        'deleted' => true,
        'force' => false,
        'status_after' => 'trash',
    ];
}
