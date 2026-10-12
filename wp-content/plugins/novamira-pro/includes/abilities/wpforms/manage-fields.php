<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ─── add-field ────────────────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-add-field', [
    'label' => __('Add WPForms Field', domain: 'novamira-pro'),
    'description' => __(
        'Append a field to a WPForms form. Required: form_id, type, label. Type must be in check-setup.available_field_types (a Pro type on a Lite install is rejected). The new field gets the next sequential id; the form\'s field_id counter is bumped. Pass options for type-specific knobs (choices for select/checkbox/radio, format for name/date-time/phone, extensions for file-upload, etc.) — see get-field-type-schema for the per-type catalog.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Target form id.',
            ],
            'type' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Field type slug (see check-setup.available_field_types).',
            ],
            'label' => [
                'type' => 'string',
                'description' => 'Human-readable label rendered next to the field.',
            ],
            'description' => [
                'type' => 'string',
                'description' => 'Optional helper text rendered below the field.',
            ],
            'required' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Whether the field must be filled to submit the form.',
            ],
            'size' => [
                'type' => 'string',
                'enum' => ['small', 'medium', 'large'],
                'default' => 'medium',
                'description' => 'Visual size of the input.',
            ],
            'css' => [
                'type' => 'string',
                'description' => 'Extra CSS classes to add to the field wrapper.',
            ],
            'conditional_logic' => [
                'type' => 'boolean',
                'description' => 'Enable conditional logic for this field (the conditionals[] payload is configured later).',
            ],
            'options' => [
                'type' => 'object',
                'description' => 'Type-specific options (e.g. {"choices": [{"label": "Yes"}, {"label": "No"}], "multiple": false}). Keys are merged flat into the stored field entry.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_add_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'New field is appended after the last existing one. Its id is the form\'s next sequential field id (the field_id counter). For choice-based types (select/checkbox/radio) pass options.choices as an array of {label, value?} objects.',
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
 *     size?: string,
 *     css?: string,
 *     conditional_logic?: bool,
 *     options?: array<string, mixed>,
 * } $input
 * @return array{form_id: int, field_id: int, position: int, field: array<string, mixed>}|WP_Error
 */
function wpforms_add_field(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $type = trim($input['type']);
    if (wpforms_field_type_schema($type) === null) {
        return new WP_Error(
            'wpforms_unknown_type',
            "Field type '{$type}' is not available on this install. See check-setup.available_field_types.",
            ['status' => 400],
        );
    }

    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $fields = wpforms_section($blob, key: 'fields');
    $next_id = wpforms_next_field_id($blob);
    $field = wpforms_build_field($next_id, $type, $input);

    $fields[(string) $next_id] = $field;
    $blob['fields'] = $fields;
    $blob['field_id'] = $next_id;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'field_id' => $next_id,
        'position' => count($fields) - 1,
        'field' => $field,
    ];
}

// ─── edit-field ───────────────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-edit-field', [
    'label' => __('Edit WPForms Field', domain: 'novamira-pro'),
    'description' => __(
        'Partially update an existing field. Required: form_id, field_id. Optional: label, description, required, size, css, conditional_logic, options (type-specific keys are merged flat into the stored field entry). The field type cannot be changed — delete and re-add to change type.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Target form id.'],
            'field_id' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Id of the field to update (numeric — see get-form.fields keys).',
            ],
            'label' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'required' => ['type' => 'boolean'],
            'size' => ['type' => 'string', 'enum' => ['small', 'medium', 'large']],
            'css' => ['type' => 'string'],
            'conditional_logic' => ['type' => 'boolean'],
            'options' => [
                'type' => 'object',
                'description' => 'Partial type-specific options merge (e.g. {"choices": [...]}). Keys are merged flat into the field entry.',
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
            'updated' => ['type' => 'array', 'items' => ['type' => 'string']],
            'field' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'field_id', 'updated', 'field'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_edit_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge update — keys not in the payload preserve their current value. Type cannot be changed (delete-field + add-field for that). The updated[] list reports which keys actually changed; an empty list means the call was a no-op.',
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
 *     size?: string,
 *     css?: string,
 *     conditional_logic?: bool,
 *     options?: array<string, mixed>,
 * } $input
 * @return array{form_id: int, field_id: int, updated: list<string>, field: array<string, mixed>}|WP_Error
 */
function wpforms_edit_field(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $field_id = (int) $input['field_id'];
    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $fields = wpforms_section($blob, key: 'fields');
    $key = (string) $field_id;

    if (!array_key_exists($key, $fields) || !is_array($fields[$key])) {
        return new WP_Error('wpforms_field_not_found', "Field {$field_id} does not exist on form {$form->ID}.", [
            'status' => 404,
        ]);
    }

    /** @var array<string, mixed> $field */
    $field = $fields[$key];
    $updated = wpforms_apply_field_update($field, $input);

    if ($updated === []) {
        return [
            'form_id' => (int) $form->ID,
            'field_id' => $field_id,
            'updated' => [],
            'field' => $field,
        ];
    }

    $fields[$key] = $field;
    $blob['fields'] = $fields;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'field_id' => $field_id,
        'updated' => $updated,
        'field' => $field,
    ];
}

// ─── delete-field ─────────────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-delete-field', [
    'label' => __('Delete WPForms Field', domain: 'novamira-pro'),
    'description' => __(
        'Permanently remove a field from a form. Required: form_id, field_id. The form\'s field_id counter is NOT decremented — new fields keep getting monotonic ids so a deleted id is never reused.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Target form id.'],
            'field_id' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Id of the field to remove.',
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
            'deleted' => ['type' => 'boolean'],
            'remaining' => ['type' => 'integer'],
        ],
        'required' => ['form_id', 'field_id', 'deleted', 'remaining'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_delete_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanently removes the field from the form definition. Submissions previously collected for the field are NOT touched (they survive in the entries table for historical reference). remaining is the field count after the delete.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, field_id: int} $input
 * @return array{form_id: int, field_id: int, deleted: bool, remaining: int}|WP_Error
 */
function wpforms_delete_field(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $field_id = (int) $input['field_id'];
    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $fields = wpforms_section($blob, key: 'fields');
    $key = (string) $field_id;

    if (!array_key_exists($key, $fields)) {
        // Round-1 audit CRITICAL: delete-field is annotated idempotent=true,
        // so deleting an already-missing field must return success — not 404.
        // No persist needed (state is already what the caller wants).
        return [
            'form_id' => (int) $form->ID,
            'field_id' => $field_id,
            'deleted' => false,
            'remaining' => count($fields),
        ];
    }

    unset($fields[$key]);
    $blob['fields'] = $fields;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'field_id' => $field_id,
        'deleted' => true,
        'remaining' => count($fields),
    ];
}

// ─── reorder-fields ───────────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-reorder-fields', [
    'label' => __('Reorder WPForms Fields', domain: 'novamira-pro'),
    'description' => __(
        'Reorder the fields of a form. Required: form_id, order — an array of field ids in the desired sequence. The order list MUST contain every existing field id exactly once (no additions, no removals, no duplicates); otherwise the call is rejected.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Target form id.'],
            'order' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'minimum' => 0],
                'description' => 'Field ids in the desired top-to-bottom order. Must be a complete permutation of the current field set.',
            ],
        ],
        'required' => ['form_id', 'order'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'order' => ['type' => 'array', 'items' => ['type' => 'integer']],
        ],
        'required' => ['form_id', 'order'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_reorder_fields',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Whole-permutation rewrite — pass every existing field id exactly once. Missing ids, extra ids, or duplicates → wpforms_invalid_order. Use add-field / delete-field for membership changes, then reorder.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, order: list<int>} $input
 * @return array{form_id: int, order: list<int>}|WP_Error
 */
function wpforms_reorder_fields(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $fields = wpforms_section($blob, key: 'fields');

    $current = array_map('intval', array_keys($fields));
    $requested = array_map('intval', $input['order']);

    if (count($requested) !== count(array_unique($requested))) {
        return new WP_Error('wpforms_invalid_order', 'order contains duplicate field ids.', ['status' => 400]);
    }
    sort($current);
    $sorted_request = $requested;
    sort($sorted_request);
    if ($current !== $sorted_request) {
        return new WP_Error('wpforms_invalid_order', 'order must be a complete permutation of the current field ids.', [
            'status' => 400,
        ]);
    }

    $reordered = [];
    foreach ($requested as $field_id) {
        $key = (string) $field_id;
        $reordered[$key] = $fields[$key];
    }
    $blob['fields'] = $reordered;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'order' => $requested,
    ];
}
