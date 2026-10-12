<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmField;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// add-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-add-field', [
    'label' => __('Add Formidable Field', domain: 'novamira-pro'),
    'description' => __(
        "Adds a field to an existing Formidable form. The `type` slug MUST come from get-field-types-schema (the catalog filters out Pro-only and add-on-upsell types so this ability never registers a type the install cannot render). Optional `name` (the field label), `description`, `required`, `default_value`, `options` (for choice fields like select / radio / checkbox), `field_options` (per-type config map merged on top of Formidable's defaults), and `field_order` (omit to append at the end). Heads-up: Formidable accepts `dropdown` as an alias of `select` in its INTERNAL update path, but this ability validates against the canonical catalog and requires `select`. Pass `dropdown` and you'll get `formidable_invalid_input` — always use the catalog slugs.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'type' => [
                'type' => 'string',
                'description' => 'Field type slug (must appear in get-field-types-schema).',
            ],
            'name' => ['type' => 'string', 'description' => 'Field label.'],
            'description' => ['type' => 'string'],
            'required' => ['type' => 'boolean', 'default' => false],
            'default_value' => ['type' => 'string'],
            'options' => [
                'type' => 'array',
                'description' => 'Choice list for select / radio / checkbox fields. Each entry is either a string label or an object with `value` and `label` keys (Formidable accepts both shapes).',
            ],
            'field_options' => [
                'type' => 'object',
                'description' => 'Per-type configuration map (e.g. `placeholder`, `maxlength`, `size`, `classes`, `custom_html`). Merged on top of Formidable\'s defaults for the field type.',
            ],
            'field_order' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Position within the form (lowest first). Omit to append at the end of the current field list.',
            ],
        ],
        'required' => ['form_id', 'type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'field_key' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'required' => ['type' => 'boolean'],
                    'field_order' => ['type' => 'integer'],
                    'form_id' => ['type' => 'integer'],
                ],
                'required' => ['id', 'field_key', 'type', 'name', 'description', 'required', 'field_order', 'form_id'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['field'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_add_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Adds a field to `form_id`. Validate `type` against get-field-types-schema BEFORE calling; an unknown type returns formidable_invalid_input. `name` becomes the field label. `options` supplies the choice list for select / radio / checkbox fields (each entry can be a bare string OR an object with `value` + `label`). `field_options` is the per-type config map merged with Formidable defaults. Omit `field_order` to append at the end. The response carries the compact field row including the actual `field_key` Formidable wrote (auto-suffixed on collision — read it back, don't guess).",
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int|string,
 *     type?: string,
 *     name?: string,
 *     description?: string,
 *     required?: bool,
 *     default_value?: string,
 *     options?: array<int|string, mixed>,
 *     field_options?: array<string, mixed>,
 *     field_order?: int,
 * } $input
 * @return array{field: array{id: int, field_key: string, type: string, name: string, description: string, required: bool, field_order: int, form_id: int}}|WP_Error
 */
function formidable_add_field(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $type = isset($input['type']) ? trim($input['type']) : '';
    if ($type === '') {
        return frm_invalid_input('`type` is required and must be a non-empty string.');
    }
    if (!in_array($type, frm_available_field_types(), strict: true)) {
        return frm_invalid_input("Unknown field type: '{$type}'. Call get-field-types-schema for the catalog.");
    }

    $form_id = (int) $form->id;
    $values = frm_build_field_create_values($form_id, $type, $input);

    $field_id = FrmField::create($values);
    if (!is_int($field_id) || $field_id <= 0) {
        return frm_error(code: 'formidable_add_field_failed', message: 'FrmField::create returned a non-positive id.');
    }

    frm_invalidate_form_caches($form_id);

    $row = FrmField::getOne($field_id);
    if (!$row instanceof \stdClass) {
        return frm_error(
            code: 'formidable_add_field_failed',
            message: "Could not re-fetch newly created field {$field_id}.",
            status: 500,
        );
    }

    return ['field' => frm_field_to_basic_row($row)];
}

/**
 * Build the FrmField::create values map from caller input. Centralised so
 * the main add callback stays under the cyclomatic-complexity threshold.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function frm_build_field_create_values(int $form_id, string $type, array $input): array
{
    $values = [
        'form_id' => $form_id,
        'type' => $type,
        'name' => isset($input['name']) ? frm_strip_crlf(sanitize_text_field((string) $input['name'])) : '',
        'description' => isset($input['description']) ? sanitize_textarea_field((string) $input['description']) : '',
        'required' => isset($input['required']) && (bool) (int) $input['required'] ? 1 : 0,
        'default_value' => isset($input['default_value']) ? (string) $input['default_value'] : '',
        'options' => isset($input['options']) ? $input['options'] : [],
        'field_options' => isset($input['field_options']) ? $input['field_options'] : [],
    ];
    if (isset($input['field_order'])) {
        $values['field_order'] = max(0, (int) $input['field_order']);
    }
    return $values;
}

// ---------------------------------------------------------------------------
// edit-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-edit-field', [
    'label' => __('Edit Formidable Field', domain: 'novamira-pro'),
    'description' => __(
        "Partial-merge edit of one field on a form. Identify the field by `field_id` (numeric) OR `field_key` (slug from get-form). The field MUST already belong to `form_id` — cross-form keys are rejected with formidable_field_form_mismatch. Mutable keys: `name` (label, CRLF-stripped), `description`, `required`, `default_value`, `field_order` (use this to reorder a single field), `options` (replaces the whole choice list), `field_options` (REPLACES the whole per-type config map — Formidable's model layer does not partial-merge it, so pass the full intended map). The `type` is intentionally NOT mutable — Formidable's `FrmField::update` accepts a type change but the resulting state on a non-empty form is brittle; recreate the field via delete-field + add-field instead.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'field_id' => ['type' => 'integer', 'minimum' => 1],
            'field_key' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'required' => ['type' => 'boolean'],
            'default_value' => ['type' => 'string'],
            'field_order' => ['type' => 'integer', 'minimum' => 0],
            'options' => ['type' => 'array'],
            'field_options' => ['type' => 'object'],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'field_key' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'required' => ['type' => 'boolean'],
                    'field_order' => ['type' => 'integer'],
                    'form_id' => ['type' => 'integer'],
                ],
                'required' => ['id', 'field_key', 'type', 'name', 'description', 'required', 'field_order', 'form_id'],
                'additionalProperties' => false,
            ],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['field', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_edit_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial-merge edit of a field. Pass `field_id` OR `field_key` (one is required). The field MUST belong to `form_id`. `field_options` REPLACES the whole map — pass the entire intended config, not just the keys you want to change. To reorder a single field, set `field_order`. Type changes are NOT supported here (delete + re-add instead).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int|string,
 *     field_id?: int|string,
 *     field_key?: string,
 *     name?: string,
 *     description?: string,
 *     required?: bool,
 *     default_value?: string,
 *     field_order?: int,
 *     options?: array<int|string, mixed>,
 *     field_options?: array<string, mixed>,
 * } $input
 * @return array{field: array{id: int, field_key: string, type: string, name: string, description: string, required: bool, field_order: int, form_id: int}, changed: list<string>}|WP_Error
 */
function formidable_edit_field(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $ref = $input['field_id'] ?? $input['field_key'] ?? null;
    if ($ref === null || $ref === '' || $ref === 0) {
        return frm_invalid_input('Either `field_id` or `field_key` is required.');
    }

    $field = frm_field_or_error((int) $form->id, $ref);
    if ($field instanceof WP_Error) {
        return $field;
    }

    $values = frm_build_field_edit_values($input);
    if ($values === []) {
        return [
            'field' => frm_field_to_basic_row($field),
            'changed' => [],
        ];
    }

    return frm_persist_field_edit((int) $form->id, (int) $field->id, $values);
}

/**
 * Apply a FrmField::update + re-fetch + cache invalidation for the edit path.
 * Split out so the main edit callback stays under the cyclomatic-complexity
 * threshold.
 *
 * @param array<string, mixed> $values
 * @return array{field: array{id: int, field_key: string, type: string, name: string, description: string, required: bool, field_order: int, form_id: int}, changed: list<string>}|WP_Error
 */
function frm_persist_field_edit(int $form_id, int $field_id, array $values): array|WP_Error
{
    $updated = FrmField::update($field_id, $values);
    if ($updated === false) {
        return frm_error(
            code: 'formidable_edit_field_failed',
            message: "FrmField::update returned false for field {$field_id}.",
        );
    }

    frm_invalidate_form_caches($form_id);

    $fresh = FrmField::getOne($field_id);
    if (!$fresh instanceof \stdClass) {
        return frm_error(
            code: 'formidable_edit_field_failed',
            message: "Could not re-fetch field {$field_id} after update.",
            status: 500,
        );
    }

    return [
        'field' => frm_field_to_basic_row($fresh),
        'changed' => array_values(array_keys($values)),
    ];
}

/**
 * Build the FrmField::update values map from caller input. Centralised so the
 * main edit callback stays under the cyclomatic-complexity threshold.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function frm_build_field_edit_values(array $input): array
{
    /** @var array<string, mixed> $values */
    $values = [];
    if (isset($input['name'])) {
        $values['name'] = frm_strip_crlf(sanitize_text_field((string) $input['name']));
    }
    if (isset($input['description'])) {
        $values['description'] = sanitize_textarea_field((string) $input['description']);
    }
    if (isset($input['required'])) {
        $values['required'] = (bool) (int) $input['required'] ? 1 : 0;
    }
    if (isset($input['default_value'])) {
        $values['default_value'] = (string) $input['default_value'];
    }
    if (isset($input['field_order'])) {
        $values['field_order'] = max(0, (int) $input['field_order']);
    }
    if (isset($input['options']) && is_array($input['options'])) {
        $values['options'] = $input['options'];
    }
    if (isset($input['field_options']) && is_array($input['field_options'])) {
        $values['field_options'] = $input['field_options'];
    }
    return $values;
}

// ---------------------------------------------------------------------------
// delete-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-delete-field', [
    'label' => __('Delete Formidable Field', domain: 'novamira-pro'),
    'description' => __(
        'Hard-deletes a single field from a form. Identify the field by `field_id` (numeric) OR `field_key` (slug from get-form); the field MUST belong to `form_id` — cross-form refs are rejected with formidable_field_form_mismatch (400). Idempotent: deleting an already-missing field is a no-op that returns `deleted=false` — re-running the same call is safe. There is no soft-delete for fields; the field row + its `wp_frm_item_metas` rows are removed atomically by FrmField::destroy. Heads-up for Repeater (Pro) fields: FrmField::destroy does NOT cascade to the auto-generated child sub-form (a `wp_frm_forms` row with `parent_form_id` pointing at the parent). Delete that child form separately via delete-form on its id, OR delete the parent form (which DOES cascade child sub-forms).',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
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
        ],
        'required' => ['form_id', 'field_id', 'field_key', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_delete_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Hard-deletes a field by `field_id` or `field_key` from `form_id`. Idempotent — re-call on a missing field returns deleted=false rather than 404 (the agent can retry safely). Cascades the field's `wp_frm_item_metas` rows. No undo.",
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, field_id?: int|string, field_key?: string} $input
 * @return array{form_id: int, field_id: int|null, field_key: string|null, deleted: bool}|WP_Error
 */
function formidable_delete_field(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $form_id = (int) $form->id;

    $ref = $input['field_id'] ?? $input['field_key'] ?? null;
    if ($ref === null || $ref === '' || $ref === 0) {
        return frm_invalid_input('Either `field_id` or `field_key` is required.');
    }

    $field = frm_field_or_error($form_id, $ref);
    if ($field instanceof WP_Error) {
        // Idempotent re-call: a missing field (404) is reported as a no-op
        // rather than an error. Any OTHER error (form mismatch, runtime gate)
        // still propagates.
        if ($field->get_error_code() === 'formidable_field_not_found') {
            return frm_delete_field_noop($form_id, $ref);
        }
        return $field;
    }

    return frm_persist_field_delete($form_id, $field);
}

/**
 * Build the no-op response for the idempotent re-call path (caller asked to
 * delete a field that's already gone).
 *
 * @return array{form_id: int, field_id: int|null, field_key: string|null, deleted: false}
 */
function frm_delete_field_noop(int $form_id, mixed $ref): array
{
    return [
        'form_id' => $form_id,
        'field_id' => is_numeric($ref) ? (int) $ref : null,
        'field_key' => is_string($ref) && !is_numeric($ref) ? $ref : null,
        'deleted' => false,
    ];
}

/**
 * Hard-destroy a resolved field row + invalidate the form's field caches.
 * Centralised so the main delete callback stays under the cyclomatic-
 * complexity threshold.
 *
 * @return array{form_id: int, field_id: int, field_key: string, deleted: true}|WP_Error
 */
function frm_persist_field_delete(int $form_id, \stdClass $field): array|WP_Error
{
    $field_id = (int) $field->id;
    $field_key = (string) ($field->field_key ?? '');

    $destroyed = FrmField::destroy($field_id);
    if (!is_int($destroyed) || $destroyed <= 0) {
        return frm_error(
            code: 'formidable_delete_field_failed',
            message: "FrmField::destroy returned a non-positive row count for field {$field_id}.",
        );
    }

    frm_invalidate_form_caches($form_id);

    return [
        'form_id' => $form_id,
        'field_id' => $field_id,
        'field_key' => $field_key,
        'deleted' => true,
    ];
}
