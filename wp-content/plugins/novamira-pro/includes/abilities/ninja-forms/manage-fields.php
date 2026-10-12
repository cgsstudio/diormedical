<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// add-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ninja-forms-add-field', [
    'label' => __('Add a Ninja Forms Field', domain: 'novamira-pro'),
    'description' => __(
        'Add one field to an existing Ninja Forms form. Required: form_id, type (a slug from ninja-forms-get-field-types-schema — an unknown type fails with ninja_forms_invalid_input). Optional: label, key (auto-derived from the label when omitted, made unique per form — read the returned key back, do not guess), required (bool), order (1-based position; omit to append at the end), settings (object of extra per-type setting keys, e.g. placeholder / default / options for list fields; see ninja-forms-get-field-type-schema for the whitelist). Returns the new field as a compact row {id, type, key, label, required, order, form_id} plus warnings[] (e.g. a key that was uniquified). Does NOT auto-add a submit button — that is a create-form concern. Use ninja-forms-get-form (include=["fields"]) for the full per-field settings afterwards.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'type' => [
                'type' => 'string',
                'description' => 'Field type slug (must appear in ninja-forms-get-field-types-schema).',
            ],
            'label' => ['type' => 'string', 'description' => 'Field label.'],
            'key' => ['type' => 'string', 'description' => 'Field key (auto-derived + uniquified when omitted).'],
            'required' => ['type' => 'boolean', 'default' => false],
            'order' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Position within the form (lowest first). Omit to append at the end.',
            ],
            'settings' => ['type' => 'object', 'description' => 'Extra per-type setting keys.'],
        ],
        'required' => ['form_id', 'type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field' => ['type' => 'object'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['field', 'warnings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_add_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Validate `type` against ninja-forms-get-field-types-schema BEFORE calling — an unknown type returns ninja_forms_invalid_input. `key` is auto-derived from `label` and uniquified per form; read the returned `key` back rather than guessing it. Omit `order` to append. Capability: manage_options.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int,
 *     type?: string,
 *     label?: string,
 *     key?: string,
 *     required?: bool,
 *     order?: int,
 *     settings?: array<string, mixed>,
 * } $input
 *
 * @return array{field: array<string, mixed>, warnings: list<string>}|WP_Error
 */
function ninja_forms_add_field(array $input): array|WP_Error
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

    $type = isset($input['type']) ? trim(ninja_forms_str($input['type'])) : '';
    if ($type === '') {
        return ninja_forms_invalid('type is required and must be a non-empty string.');
    }
    $registry = ninja_forms_field_registry();
    if (!isset($registry[$type])) {
        return ninja_forms_invalid(sprintf(
            "Unknown field type '%s'. Call ninja-forms-get-field-types-schema for the catalog.",
            $type,
        ));
    }

    $used_keys = ninja_forms_existing_field_keys($form_id);
    $order = isset($input['order']) ? ninja_forms_non_neg_int($input['order']) : ninja_forms_next_field_order($form_id);

    /** @var list<string> $warnings */
    $warnings = [];
    // A Ninja form needs exactly one submit; warn (don't block — the caller may
    // be mid-rebuild) when adding a second one.
    if ($type === 'submit' && ninja_forms_form_has_submit($form_id)) {
        $warnings[] =
            'This form already has a submit field — a second submit can break the form. '
            . 'Delete the existing one first if you are replacing it.';
    }
    $descriptor = [
        'type' => $type,
        'label' => $input['label'] ?? '',
        'key' => $input['key'] ?? '',
        'required' => $input['required'] ?? null,
        'settings' => $input['settings'] ?? null,
    ];
    $persisted = ninja_forms_persist_field($form_id, $descriptor, $order, $used_keys, $warnings);
    ninja_forms_bust_cache($form_id);

    return [
        'field' => ninja_forms_field_compact($form_id, $persisted['id']),
        'warnings' => $warnings,
    ];
}

// ---------------------------------------------------------------------------
// edit-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ninja-forms-edit-field', [
    'label' => __('Edit a Ninja Forms Field', domain: 'novamira-pro'),
    'description' => __(
        'Partial-merge edit of one field on a form. Identify the field by `field_id` (numeric) OR `field_key` (slug from get-form) — field_id wins when both are given; the field MUST belong to `form_id` (a stray id/key returns ninja_forms_field_not_found). Mutable: label, key (renamed key is uniquified per form), required (bool), order, and `settings` (an object merged onto the field\'s current settings — only the keys you pass are touched). The `type` is NOT mutable (Ninja re-renders unpredictably on a type swap) — delete the field and add-field a new one instead; a `type` key inside `settings` is ignored. Returns the updated compact field + the list of changed setting keys (idempotent: a no-op re-run returns an empty changed[]).',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'field_id' => ['type' => 'integer', 'minimum' => 1],
            'field_key' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'required' => ['type' => 'boolean'],
            'order' => ['type' => 'integer', 'minimum' => 0],
            'settings' => ['type' => 'object', 'description' => 'Per-field settings to merge.'],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['field', 'changed', 'warnings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_edit_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass `field_id` OR `field_key` (one required). The field must belong to `form_id`. `settings` is a shallow per-key merge — re-running with the same values is a no-op (idempotent). `type` changes are not supported (delete + add-field instead). Renaming `key` uniquifies against the form\'s other fields. Concurrency: no lock, and Ninja rewrites the whole field-settings row on save, so two edits to the SAME field racing in parallel are last-write-wins on the ENTIRE settings blob (the later save can drop the earlier edit on a different key) — serialise concurrent edits. Capability: manage_options.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int,
 *     field_id?: int,
 *     field_key?: string,
 *     label?: string,
 *     key?: string,
 *     required?: bool,
 *     order?: int,
 *     settings?: array<string, mixed>,
 * } $input
 *
 * @return array{field: array<string, mixed>, changed: list<string>, warnings: list<string>}|WP_Error
 */
function ninja_forms_edit_field(array $input): array|WP_Error
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

    $field = ninja_forms_resolve_field($form_id, $input);
    if ($field instanceof WP_Error) {
        return $field;
    }
    $field_id = (int) $field->get_id();

    /** @var list<string> $warnings */
    $warnings = [];
    $patch = ninja_forms_build_field_patch($form_id, $field_id, $input, $warnings);

    $current = $field->get_settings();
    /** @var list<string> $changed */
    $changed = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($patch as $key => $value) {
        if (array_key_exists($key, $current) && ninja_forms_setting_equal($current[$key], $value)) {
            continue;
        }
        $changed[] = $key;
    }

    if ($changed !== []) {
        $field->update_settings($patch);
        $field->save();
        ninja_forms_bust_cache($form_id);
    }

    return [
        'field' => ninja_forms_field_compact($form_id, $field_id),
        'changed' => $changed,
        'warnings' => $warnings,
    ];
}

/**
 * Build the settings patch for edit-field from caller input: the `settings`
 * merge object (string keys, managed keys stripped, `type` dropped so a field
 * type can't be mutated here) plus the top-level label / required / order /
 * key. A renamed `key` is uniquified against the form's OTHER fields.
 *
 * @param array<string, mixed> $input
 * @param list<string>         $warnings
 *
 * @return array<string, mixed>
 */
function ninja_forms_build_field_patch(int $form_id, int $field_id, array $input, array &$warnings): array
{
    $patch = ninja_forms_string_keyed($input['settings'] ?? null);
    // `type` is not mutable through edit-field — drop it from the merge.
    unset($patch['type']);

    if (isset($input['label'])) {
        $patch['label'] = ninja_forms_str($input['label']);
    }
    if (isset($input['required'])) {
        $patch['required'] = ninja_forms_truthy($input['required']) ? 1 : 0;
    }
    if (isset($input['order'])) {
        $patch['order'] = ninja_forms_non_neg_int($input['order']);
    }
    $given_key = ninja_forms_str($input['key'] ?? '');
    if ($given_key !== '') {
        $existing = ninja_forms_existing_field_keys($form_id, $field_id);
        $unique = ninja_forms_unique_key($given_key, $existing);
        if ($unique !== $given_key) {
            $warnings[] = sprintf("Field key '%s' was changed to '%s' to stay unique.", $given_key, $unique);
        }
        $patch['key'] = $unique;
    }
    return $patch;
}

// ---------------------------------------------------------------------------
// delete-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ninja-forms-delete-field', [
    'label' => __('Delete a Ninja Forms Field', domain: 'novamira-pro'),
    'description' => __(
        'Permanently delete one field from a form. Identify it by `field_id` (numeric) OR `field_key` (slug from get-form); the field MUST belong to `form_id`. Idempotent: deleting an already-missing field is a no-op that returns deleted=false (re-running is safe, not a 404). The field row and its `nf3_field_meta` rows are removed by the model layer; there is no soft-delete / trash for fields. Note a Ninja form needs a submit field to be usable — do not delete the only submit unless you are replacing it.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'field_id' => ['type' => 'integer', 'minimum' => 1],
            'field_key' => ['type' => 'string'],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'field_id' => ['type' => ['integer', 'null']],
            'field_key' => ['type' => ['string', 'null']],
            'deleted' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['form_id', 'field_id', 'field_key', 'deleted', 'warnings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_delete_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Deletes a field by `field_id` or `field_key` from `form_id`. Idempotent — re-calling on a missing field returns deleted=false rather than 404. No undo. Do not delete the only submit field. Capability: manage_options.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int, field_id?: int, field_key?: string} $input
 *
 * @return array{form_id: int, field_id: int|null, field_key: string|null, deleted: bool, warnings: list<string>}|WP_Error
 */
function ninja_forms_delete_field(array $input): array|WP_Error
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

    $field = ninja_forms_resolve_field($form_id, $input);
    if ($field instanceof WP_Error) {
        // Idempotent re-call: a missing field (404) is a no-op, not an error.
        // Any OTHER error (invalid input — neither ref given) propagates.
        if ($field->get_error_code() === 'ninja_forms_field_not_found') {
            $ref_id = ninja_forms_pos_int($input['field_id'] ?? null);
            $ref_key = ninja_forms_str($input['field_key'] ?? '');
            return [
                'form_id' => $form_id,
                'field_id' => $ref_id > 0 ? $ref_id : null,
                'field_key' => $ref_key !== '' ? $ref_key : null,
                'deleted' => false,
                'warnings' => [],
            ];
        }
        return $field;
    }

    $field_id = (int) $field->get_id();
    $field_key = ninja_forms_str($field->get_setting('key'));
    // Warn (don't block) when this removes the form's only submit — the form
    // becomes unusable until another submit is added.
    /** @var list<string> $warnings */
    $warnings = [];
    if (
        ninja_forms_str($field->get_setting('type')) === 'submit'
        && !ninja_forms_form_has_submit($form_id, $field_id)
    ) {
        $warnings[] =
            'You deleted the form\'s only submit field — the form cannot be submitted until you add another. '
            . 'Use ninja-forms-add-field with type "submit".';
    }
    $field->delete();
    ninja_forms_bust_cache($form_id);

    return [
        'form_id' => $form_id,
        'field_id' => $field_id,
        'field_key' => $field_key !== '' ? $field_key : null,
        'deleted' => true,
        'warnings' => $warnings,
    ];
}
