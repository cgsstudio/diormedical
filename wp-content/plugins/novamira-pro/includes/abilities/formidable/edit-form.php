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

wp_register_ability('novamira/formidable-edit-form', [
    'label' => __('Edit Formidable Form', domain: 'novamira-pro'),
    'description' => __(
        "Edits a Formidable form's metadata in place. Accepts BOTH short and WordPress-native aliases for the same field: `title` ↔ `name`, `slug` ↔ `form_key`; the canonical short form wins when both are supplied. Only the keys you pass are touched (partial merge): omit a key to leave it unchanged. Setting `status` to `trash` routes through FrmForm::trash so the soft-delete `trash_time` is stamped correctly; pass `status='published'` or `'draft'` to restore. This ability INTENTIONALLY does not accept a `fields` input — field-level edits (add/edit/delete/reorder) ship from the dedicated add-field / edit-field / delete-field abilities in S3. Returns the compact form row after the write.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Numeric form id (from list-forms).',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'New form name (255-char limit, CRLF-stripped). Equivalent to `name` — pass one OR the other.',
            ],
            'name' => ['type' => 'string', 'description' => 'WordPress-native alias for `title`.'],
            'slug' => [
                'type' => 'string',
                'description' => 'New form_key. Equivalent to `form_key` — pass one OR the other. Auto-suffixed on collision; read back the actual value from the response.',
            ],
            'form_key' => ['type' => 'string', 'description' => 'Formidable-native alias for `slug`.'],
            'description' => ['type' => 'string'],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'draft', 'trash'],
                'description' => 'New form status. Pass `trash` to soft-delete (sets `trash_time` in the options blob) — equivalent to delete-form{force=false}.',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form' => [
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
            'changed' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'List of field names that were sent to the underlying update. Empty when only form_id was supplied (no-op).',
            ],
        ],
        'required' => ['form', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_edit_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Partial-merge edit of the form row identified by `form_id`. Only the keys you pass are written; omit a key to leave it unchanged. Slug-style fields auto-suffix on collision — always read the actual `form_key` back from the response. Trashed forms are accepted as inputs so you can restore them (pass `status='published'` on a trashed form). DO NOT pass a `fields` input — field-level edits (add/edit/delete/reorder) ship from the add-field / edit-field / delete-field abilities. The returned `changed[]` list reports which fields were sent to the underlying update.",
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int|string,
 *     title?: string,
 *     name?: string,
 *     slug?: string,
 *     form_key?: string,
 *     description?: string,
 *     status?: string,
 * } $input
 * @return array{
 *     form: array{
 *         id: int,
 *         form_key: string,
 *         name: string,
 *         description: string,
 *         status: string,
 *         parent_form_id: int,
 *         is_template: bool,
 *         created_at: string,
 *     },
 *     changed: list<string>,
 * }|WP_Error
 */
function formidable_edit_form(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    // Trash-tolerant resolve: this ability accepts trashed forms as inputs so
    // an agent can restore them by passing status='published'.
    $form = frm_trashed_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $form_id = (int) $form->id;

    // Note: a `fields` input is rejected by the schema (additionalProperties:false)
    // BEFORE the callback runs — the framework returns ability_invalid_input.
    // We do not duplicate the check here; the schema is the single source of
    // truth for the accepted key set. See smoke test "edit-form rejects a
    // `fields` input loudly" for the contract.
    $input = frm_normalize_form_input($input);
    $values = frm_build_edit_values($input);

    if ($values === []) {
        // No mutable input — return the current row unchanged.
        return [
            'form' => frm_form_to_compact_row($form),
            'changed' => [],
        ];
    }

    // `status=trash` routes through FrmForm::trash so the soft-delete
    // `trash_time` is stamped correctly. Other status transitions and field
    // mutations go through FrmForm::update.
    if (isset($values['status']) && $values['status'] === 'trash') {
        FrmForm::trash($form_id);
        unset($values['status']);
    }

    if ($values !== []) {
        $updated = FrmForm::update($form_id, $values);
        if ($updated === false) {
            return frm_error(
                code: 'formidable_edit_failed',
                message: "FrmForm::update returned false for form {$form_id}.",
            );
        }
    }

    $row = FrmForm::getOne($form_id);
    if ($row === false) {
        return frm_error(
            code: 'formidable_edit_failed',
            message: "Could not re-fetch form {$form_id} after update.",
            status: 500,
        );
    }

    // `array_keys($values)` returns a sequentially-indexed list. The PHP `+`
    // union operator preserves LEFT-side numeric keys, so the previous
    // `array_keys + ['status']` syntax silently dropped 'status' when $values
    // already contained any other key (audit round 1 finding). Use a typed
    // append instead.
    $changed = array_values(array_keys($values));
    if (isset($input['status']) && $input['status'] === 'trash') {
        $changed[] = 'status';
    }

    return [
        'form' => frm_form_to_compact_row($row),
        'changed' => $changed,
    ];
}

/**
 * Build the FrmForm::update values map from the normalised caller input.
 * Centralises the sanitisation + alias mapping so the main callback stays
 * under the cyclomatic-complexity threshold.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function frm_build_edit_values(array $input): array
{
    /** @var array<string, mixed> $values */
    $values = [];

    if (isset($input['name'])) {
        $values['name'] = frm_strip_crlf(sanitize_text_field((string) $input['name']));
    }
    if (isset($input['form_key'])) {
        $values['form_key'] = sanitize_key((string) $input['form_key']);
    }
    if (isset($input['description'])) {
        $values['description'] = sanitize_textarea_field((string) $input['description']);
    }
    if (isset($input['status'])) {
        $values['status'] = (string) $input['status'];
    }

    return $values;
}
