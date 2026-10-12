<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ─── add-field ────────────────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-add-field', [
    'label' => __('Add Gravity Forms Field', domain: 'novamira-pro'),
    'description' => __(
        'Append a field to a Gravity Forms form. Required: form_id, type, label. Type must be in check-setup.available_field_types (an unknown type is rejected). The new field gets the next sequential id from GFFormsModel::get_next_field_id and is appended to the fields[] array. Pass options for type-specific knobs (choices for select/checkbox/radio, inputMask for phone, etc.).',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Target form id.'],
            'type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Field type slug.'],
            'label' => ['type' => 'string', 'description' => 'Human-readable label.'],
            'description' => ['type' => 'string', 'description' => 'Optional helper text.'],
            'required' => ['type' => 'boolean', 'default' => false],
            'cssClass' => ['type' => 'string', 'description' => 'Extra CSS classes on the field wrapper.'],
            'adminLabel' => ['type' => 'string', 'description' => 'Internal label shown in entries / admin UI.'],
            'options' => [
                'type' => 'object',
                'description' => 'Type-specific options merged into the field (e.g. {"choices": [{"text": "Yes", "value": "yes"}], "inputMask": true}).',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['form_id', 'type', 'label'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'field_id' => ['type' => 'integer'],
            'position' => ['type' => 'integer'],
            'field' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'field_id', 'position', 'field'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_add_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'New field is appended after the last existing one with the next sequential id from GFFormsModel::get_next_field_id. For choice-based types (select/checkbox/radio) pass options.choices as a list of {text, value, isSelected?} objects — GF\'s native shape.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     type: string,
 *     label: string,
 *     description?: string,
 *     required?: bool,
 *     cssClass?: string,
 *     adminLabel?: string,
 *     options?: array<string, mixed>,
 * } $input
 * @return array{form_id: int, field_id: int, position: int, field: array<array-key, mixed>}|WP_Error
 */
function gravityforms_add_field(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $type = trim($input['type']);
    if (!gravityforms_field_type_known($type)) {
        return new WP_Error(
            'gravityforms_unknown_type',
            "Field type '{$type}' is not available on this install. See check-setup.available_field_types.",
            ['status' => 400],
        );
    }

    $fields = gravityforms_fields_from_form($form);
    // GFFormsModel::get_next_field_id expects GF_Field instances (it calls
    // $field->id and $field->fields), but our helper returns arrays — call
    // our own array-aware computation instead.
    $next_id = gravityforms_next_field_id($fields);
    $field = gravityforms_build_field($next_id, $type, $input);
    $fields[] = $field;
    $form['fields'] = $fields;

    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'field_id' => $next_id,
        'position' => count($fields) - 1,
        'field' => $field,
    ];
}

// ─── edit-field ───────────────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-edit-field', [
    'label' => __('Edit Gravity Forms Field', domain: 'novamira-pro'),
    'description' => __(
        'Edit a field on a Gravity Forms form by id. Pass any of: label, description, required, cssClass, adminLabel, options (type-specific keys merged flat). The field type cannot change here — delete + add for a type change. The returned field is the result after the patch is applied.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'field_id' => ['type' => 'integer', 'minimum' => 1],
            'label' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'required' => ['type' => 'boolean'],
            'cssClass' => ['type' => 'string'],
            'adminLabel' => ['type' => 'string'],
            'options' => [
                'type' => 'object',
                'description' => 'Type-specific keys merged flat into the field (e.g. {"choices": [...], "inputMask": true}). Pass only what you want to change.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['form_id', 'field_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'field_id' => ['type' => 'integer'],
            'field' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['form_id', 'field_id', 'field', 'changed'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_edit_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns gravityforms_field_not_found when field_id is not on the form. Idempotent: a patch that produces no real change returns changed=[] and does NOT trigger a database write.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     field_id: int,
 *     label?: string,
 *     description?: string,
 *     required?: bool,
 *     cssClass?: string,
 *     adminLabel?: string,
 *     options?: array<string, mixed>,
 * } $input
 * @return array{form_id: int, field_id: int, field: array<array-key, mixed>, changed: list<string>}|WP_Error
 */
function gravityforms_edit_field(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $field_id = (int) $input['field_id'];
    $fields = gravityforms_fields_from_form($form);
    $index = gravityforms_find_field_index($fields, $field_id);
    if ($index === null) {
        return new WP_Error(
            'gravityforms_field_not_found',
            "Field id {$field_id} not found on form " . (int) $form['id'],
            ['status' => 404],
        );
    }

    [$updated_field, $changed] = gravityforms_apply_field_patch($fields[$index], $input);

    if ($changed === []) {
        return [
            'form_id' => (int) $form['id'],
            'field_id' => $field_id,
            'field' => $updated_field,
            'changed' => [],
        ];
    }

    $fields[$index] = $updated_field;
    $form['fields'] = $fields;
    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'field_id' => $field_id,
        'field' => $updated_field,
        'changed' => $changed,
    ];
}

// ─── delete-field ─────────────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-delete-field', [
    'label' => __('Delete Gravity Forms Field', domain: 'novamira-pro'),
    'description' => __(
        'Remove a field from a Gravity Forms form by id. The remaining fields keep their positions and ids (gaps are NOT compacted — entries already submitted reference field ids directly, so renumbering would corrupt them). Returns the removed field for confirmation.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'field_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id', 'field_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'field_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
            'removed_field' => ['type' => ['object', 'null']],
            'remaining_count' => ['type' => 'integer'],
        ],
        'required' => ['form_id', 'field_id', 'deleted', 'removed_field', 'remaining_count'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_delete_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Deletes the field from the fields[] array. Field ids are NOT compacted because existing entries reference them by id. removed_field is null when the id was already missing (treated as idempotent success).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, field_id: int} $input
 * @return array{form_id: int, field_id: int, deleted: bool, removed_field: array<array-key, mixed>|null, remaining_count: int}|WP_Error
 */
function gravityforms_delete_field(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $field_id = (int) $input['field_id'];
    $fields = gravityforms_fields_from_form($form);
    $index = gravityforms_find_field_index($fields, $field_id);
    if ($index === null) {
        // Idempotent: missing field is a no-op success.
        return [
            'form_id' => (int) $form['id'],
            'field_id' => $field_id,
            'deleted' => true,
            'removed_field' => null,
            'remaining_count' => count($fields),
        ];
    }

    $removed = $fields[$index];
    array_splice($fields, $index, length: 1);
    $form['fields'] = $fields;
    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'field_id' => $field_id,
        'deleted' => true,
        'removed_field' => $removed,
        'remaining_count' => count($fields),
    ];
}

// ─── reorder-fields ───────────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-reorder-fields', [
    'label' => __('Reorder Gravity Forms Fields', domain: 'novamira-pro'),
    'description' => __(
        'Reorder the fields[] array of a Gravity Forms form. Pass field_ids as the new positional list — every existing field id MUST appear exactly once, missing or extra ids are rejected. Field ids are NOT renumbered; only the array order changes.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'field_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'minimum' => 1],
                'minItems' => 1,
                'description' => 'New positional order. Must be a permutation of the form\'s current field ids.',
            ],
        ],
        'required' => ['form_id', 'field_ids'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'order' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'reordered' => ['type' => 'boolean'],
        ],
        'required' => ['form_id', 'order', 'reordered'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_reorder_fields',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass the exact list of current field ids in the new order. Returns gravityforms_invalid_input when the list is not a permutation (missing or extra ids). Idempotent: re-ordering to the existing order returns reordered=false without a DB write.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, field_ids: list<int>} $input
 * @return array{form_id: int, order: list<int>, reordered: bool}|WP_Error
 */
function gravityforms_reorder_fields(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $fields = gravityforms_fields_from_form($form);
    $current_order = array_values(array_map(static fn(array $f): int => (int) ($f['id'] ?? 0), $fields));
    $new_order = array_values(array_map(static fn($id): int => (int) $id, $input['field_ids']));

    sort($current_order);
    $check_new = $new_order;
    sort($check_new);
    if ($current_order !== $check_new) {
        return gravityforms_invalid_input(
            'field_ids must be a permutation of the form\'s current field ids — missing or extra ids are rejected.',
        );
    }

    // Idempotent shortcut when the requested order matches the existing one.
    $existing_order = array_values(array_map(static fn(array $f): int => (int) ($f['id'] ?? 0), $fields));
    if ($existing_order === $new_order) {
        return [
            'form_id' => (int) $form['id'],
            'order' => $new_order,
            'reordered' => false,
        ];
    }

    $by_id = [];
    foreach ($fields as $field) {
        $by_id[(int) ($field['id'] ?? 0)] = $field;
    }
    $reordered = [];
    foreach ($new_order as $id) {
        $reordered[] = $by_id[$id];
    }

    $form['fields'] = $reordered;
    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'order' => $new_order,
        'reordered' => true,
    ];
}

// ─── shared helpers (field-only) ──────────────────────────────────────────

/**
 * Extract the fields[] array from a GF form. Defends against the field array
 * being object-wrapped or missing entirely; non-array entries are skipped so
 * the downstream helpers never see mixed.
 *
 * @param array<string, mixed> $form
 * @return list<array<array-key, mixed>>
 */
function gravityforms_fields_from_form(array $form): array
{
    if (!isset($form['fields']) || !is_array($form['fields'])) {
        return [];
    }
    $out = [];
    /** @var mixed $entry */
    foreach ($form['fields'] as $entry) {
        if ($entry instanceof \GF_Field) {
            // (array) on a GF_Field prefixes private/protected props with NULL
            // bytes that break json_encode and downstream key access. Use
            // get_object_vars to get the public surface only — what we'd write
            // back to the form blob anyway.
            $out[] = get_object_vars($entry);
            continue;
        }
        if (is_array($entry)) {
            $out[] = $entry;
        }
    }
    return $out;
}

/**
 * Compute the next sequential field id from an array-shaped fields list.
 * Mirrors GFFormsModel::get_next_field_id but expects array entries (not
 * GF_Field objects) — the GF helper accesses $field->id and emits a
 * "read property on array" warning on our normalized array shape.
 *
 * @param list<array<array-key, mixed>> $fields
 */
function gravityforms_next_field_id(array $fields): int
{
    $max = 0;
    foreach ($fields as $field) {
        $id = (int) ($field['id'] ?? 0);
        if ($id > $max) {
            $max = $id;
        }
        if (isset($field['inputs']) && is_array($field['inputs'])) {
            /** @var mixed $input */
            foreach ($field['inputs'] as $input) {
                if (!is_array($input)) {
                    continue;
                }
                $iid = (int) ($input['id'] ?? 0);
                if ($iid > $max) {
                    $max = $iid;
                }
            }
        }
    }
    return $max + 1;
}

/**
 * Whether the field type is registered on this install (Lite + Pro + add-ons).
 */
function gravityforms_field_type_known(string $type): bool
{
    if (!class_exists('GF_Fields')) {
        return false;
    }
    return \GF_Fields::get($type) instanceof \GF_Field;
}

/**
 * Find the positional index of a field by id within the fields[] array.
 * Returns null when not found.
 *
 * @param list<array<array-key, mixed>> $fields
 */
function gravityforms_find_field_index(array $fields, int $field_id): ?int
{
    foreach ($fields as $i => $field) {
        if ((int) ($field['id'] ?? 0) === $field_id) {
            return $i;
        }
    }
    return null;
}

/**
 * Construct a new field entry from the add-field input.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function gravityforms_build_field(int $next_id, string $type, array $input): array
{
    /** @var array<string, mixed> $field */
    $field = [
        'id' => $next_id,
        'type' => $type,
        'label' => (string) ($input['label'] ?? ''),
    ];
    if (isset($input['description'])) {
        $field['description'] = (string) $input['description'];
    }
    if (isset($input['required'])) {
        $field['isRequired'] = $input['required'] === true;
    }
    if (isset($input['cssClass'])) {
        $field['cssClass'] = (string) $input['cssClass'];
    }
    if (isset($input['adminLabel'])) {
        $field['adminLabel'] = (string) $input['adminLabel'];
    }
    if (isset($input['options']) && is_array($input['options'])) {
        /** @var mixed $value */
        foreach ($input['options'] as $key => $value) {
            $field[(string) $key] = $value;
        }
    }
    return $field;
}

/**
 * Apply a partial field-edit patch to a stored field entry. Returns [updated,
 * changed-keys].
 *
 * @param array<array-key, mixed> $field
 * @param array<array-key, mixed> $input
 * @return array{0: array<array-key, mixed>, 1: list<string>}
 */
function gravityforms_apply_field_patch(array $field, array $input): array
{
    /** @var list<string> $changed */
    $changed = [];
    $map = [
        'label' => 'label',
        'description' => 'description',
        'cssClass' => 'cssClass',
        'adminLabel' => 'adminLabel',
    ];
    foreach ($map as $src => $dst) {
        if (!isset($input[$src])) {
            continue;
        }
        $val = (string) $input[$src];
        if (($field[$dst] ?? null) !== $val) {
            $field[$dst] = $val;
            $changed[] = $src;
        }
    }
    if (isset($input['required'])) {
        $val = $input['required'] === true;
        if (($field['isRequired'] ?? null) !== $val) {
            $field['isRequired'] = $val;
            $changed[] = 'required';
        }
    }
    if (isset($input['options']) && is_array($input['options'])) {
        /** @var mixed $value */
        foreach ($input['options'] as $key => $value) {
            $skey = (string) $key;
            if (array_key_exists($skey, $field) && $field[$skey] === $value) {
                continue;
            }
            $field[$skey] = $value;
            $changed[] = "options.{$skey}";
        }
    }
    return [$field, $changed];
}
