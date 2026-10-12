<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use FluentForm\App\Models\Form;
use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ====================================================================
// add-field — append (or insert at `position`) one new field
// ====================================================================

wp_register_ability('novamira/fluentforms-add-field', [
    'label' => __('Add a Field to a Fluent Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Append a new field to an existing form. Required: form_id, field — the agent-facing descriptor {type, label?, required?, name?, settings?}. The ability resolves the form, decodes form_fields.fields[], appends the new element (or inserts it at the optional `position` index, 0-based), re-encodes and persists. Unknown field types are rejected with fluentforms_invalid_input. Pro-only field types on a free install are rejected with fluentforms_pro_field_not_available. The `name` is auto-assigned (collision-safe within the form) when omitted. Returns {form_id, field — the FF-native element row just inserted, position, dropped_settings — keys filtered by the catalog whitelist}. Capability: fluentform_forms_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'field' => [
                'type' => 'object',
                'description' => 'Agent-facing field descriptor. type is required; label/required/name/settings are optional.',
            ],
            'position' => [
                'type' => 'integer',
                'description' => '0-based index to insert at. Default: append at the end.',
                'minimum' => 0,
            ],
        ],
        'required' => ['form_id', 'field'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'field' => ['type' => 'object'],
            'position' => ['type' => 'integer'],
            'dropped_settings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['form_id', 'field', 'position', 'dropped_settings'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_add_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this for incremental additions — DO NOT call edit-form with fields[] (that REPLACES the whole array). Call get-field-types-schema first to know which types are available; use get-field-type-schema for the settings whitelist of one type. Capability: fluentform_forms_manager.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{form_id:int, field:array<string,mixed>, position:int, dropped_settings:list<string>}|WP_Error
 */
function fluentforms_add_field(array $input): array|WP_Error
{
    $loaded = ff_load_form_for_field_op($input['form_id'] ?? null);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    [$form, $decoded] = $loaded;

    $field_input = isset($input['field']) && is_array($input['field']) ? $input['field'] : null;
    if ($field_input === null) {
        return ff_invalid('field must be an object.');
    }
    $check = ff_check_field_input($field_input);
    if ($check instanceof WP_Error) {
        return $check;
    }

    $type = isset($field_input['type']) && is_string($field_input['type']) ? $field_input['type'] : '';
    $supplied_settings = isset($field_input['settings']) && is_array($field_input['settings'])
        ? $field_input['settings']
        : [];
    $dropped = ff_filter_settings_whitelist($type, $supplied_settings)['dropped'];

    $built = ff_build_element($field_input, $decoded['fields']);
    $position = ff_resolve_insert_position($input, max: count($decoded['fields']));
    // length: 0 INSERTS at $position. Omitting length (audit round 1 CRITICAL
    // #1) makes array_splice DELETE every element from $position onwards
    // before inserting — silent data loss on any non-tail position.
    array_splice($decoded['fields'], $position, length: 0, replacement: [$built]);

    $persist = ff_save_form_fields($form, $decoded);
    if ($persist instanceof WP_Error) {
        return $persist;
    }
    return [
        'form_id' => (int) $form->id,
        'field' => $built,
        'position' => $position,
        'dropped_settings' => $dropped,
    ];
}

// ====================================================================
// edit-field — patch one field by its HTML `name`
// ====================================================================

wp_register_ability('novamira/fluentforms-edit-field', [
    'label' => __('Edit a Fluent Forms Field', domain: 'novamira-pro'),
    'description' => __(
        'Patch one field in an existing form, identified by its `name` (the FF submission-key under attributes.name). Required: form_id, name. Mutable: label, required, settings (partial merge into the existing settings, NOT a replace — pass null on a key to remove it). Returns {form_id, field — updated FF-native row, dropped_settings}. Returns fluentforms_field_not_found (404) when the name is absent. Renaming a field is NOT supported via this ability (would orphan existing submissions); to rename, delete + add. Capability: fluentform_forms_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string', 'description' => 'attributes.name on the target field.'],
            'label' => ['type' => 'string'],
            'required' => ['type' => 'boolean'],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'field' => ['type' => 'object'],
            'dropped_settings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['form_id', 'field', 'dropped_settings'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_edit_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Identifies the target field by attributes.name — get it from get-form?include=[fields]. settings merge is shallow (top-level keys replace, nested objects replace WHOLE not deep-merge); pass null on a key to delete it. Capability: fluentform_forms_manager.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{form_id:int, field:array<string,mixed>, dropped_settings:list<string>}|WP_Error
 */
function fluentforms_edit_field(array $input): array|WP_Error
{
    $loaded = ff_load_form_for_field_op($input['form_id'] ?? null);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    [$form, $decoded] = $loaded;

    $name = isset($input['name']) && is_string($input['name']) ? $input['name'] : '';
    if ($name === '') {
        return ff_invalid('name must be a non-empty string.');
    }
    $target_index = ff_find_field_index($decoded['fields'], $name);
    if ($target_index === null) {
        return new WP_Error('fluentforms_field_not_found', sprintf("No field with name '%s' on this form.", $name), [
            'status' => 404,
        ]);
    }
    /** @var array<string, mixed> $existing */
    $existing = $decoded['fields'][$target_index];
    $patched = ff_patch_field($existing, $input);
    if ($patched instanceof WP_Error) {
        return $patched;
    }
    $decoded['fields'][$target_index] = $patched['field'];

    $persist = ff_save_form_fields($form, $decoded);
    if ($persist instanceof WP_Error) {
        return $persist;
    }
    return [
        'form_id' => (int) $form->id,
        'field' => $patched['field'],
        'dropped_settings' => $patched['dropped'],
    ];
}

// ====================================================================
// delete-field — remove one field by its HTML `name`
// ====================================================================

wp_register_ability('novamira/fluentforms-delete-field', [
    'label' => __('Delete a Fluent Forms Field', domain: 'novamira-pro'),
    'description' => __(
        'Remove a field from a form, identified by its `name`. Required: form_id, name. Returns {form_id, removed_name, fields_remaining: int, warnings?}. When the removed field was the last one, warnings.empty_form flags that the form now has no fields and renders as empty output. Returns fluentforms_field_not_found (404) when the name is absent. NOTE: existing submissions retain the value under the old field name in their response JSON — deleting the field on the form does not retroactively redact entries. Capability: fluentform_forms_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string'],
        ],
        'required' => ['form_id', 'name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'removed_name' => ['type' => 'string'],
            'fields_remaining' => ['type' => 'integer'],
            'warnings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'removed_name', 'fields_remaining'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_delete_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Existing submissions are NOT redacted — entries retain the value under the deleted field name in their response JSON. Use this only when the form definition needs the field gone; do not rely on it to scrub historical data. Capability: fluentform_forms_manager.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{form_id:int, removed_name:string, fields_remaining:int, warnings?:array{empty_form:string}}|WP_Error
 */
function fluentforms_delete_field(array $input): array|WP_Error
{
    $loaded = ff_load_form_for_field_op($input['form_id'] ?? null);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    [$form, $decoded] = $loaded;

    $name = isset($input['name']) && is_string($input['name']) ? $input['name'] : '';
    if ($name === '') {
        return ff_invalid('name must be a non-empty string.');
    }
    $target_index = ff_find_field_index($decoded['fields'], $name);
    if ($target_index === null) {
        return new WP_Error('fluentforms_field_not_found', sprintf("No field with name '%s' on this form.", $name), [
            'status' => 404,
        ]);
    }
    array_splice($decoded['fields'], $target_index, length: 1);

    $persist = ff_save_form_fields($form, $decoded);
    if ($persist instanceof WP_Error) {
        return $persist;
    }
    $remaining = count($decoded['fields']);
    $result = [
        'form_id' => (int) $form->id,
        'removed_name' => $name,
        'fields_remaining' => $remaining,
    ];
    if ($remaining === 0) {
        // A form with no fields renders as empty output on the front end —
        // the same silent-empty failure create-form warns about.
        $result['warnings'] = ['empty_form' => 'The form now has no fields and will render as empty output.'];
    }
    return $result;
}

// ====================================================================
// Helpers shared by add-field / edit-field / delete-field
// ====================================================================

/**
 * Common bootstrap for the three field-mutation abilities. Resolves the
 * form, runs the version + capability guards, and decodes the form_fields
 * blob. Returns [Form, decoded] on success or a WP_Error on the first gate
 * failure.
 *
 * @return array{0:Form, 1:array{fields:list<array<array-key, mixed>>,submitButton:array<array-key, mixed>,...<array-key, mixed>}}|WP_Error
 */
function ff_load_form_for_field_op(mixed $form_id_raw): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $form = ff_form_or_error($form_id_raw);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = ff_capability_or_error('fluentform_forms_manager', (int) $form->id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    return [$form, ff_decode_fields(ff_str($form->form_fields))];
}

/**
 * Resolve the insertion position for add-field. The optional `position`
 * input is clamped to [0, max]; missing / out-of-range falls back to max
 * (append at the end).
 *
 * @param array<string, mixed> $input
 */
function ff_resolve_insert_position(array $input, int $max): int
{
    if (isset($input['position']) && is_int($input['position']) && $input['position'] >= 0) {
        return min($input['position'], $max);
    }
    return $max;
}

/**
 * Find a field's index in the decoded fields[] by its attributes.name. Returns
 * null when no field matches. Returns the FIRST match — names are expected to
 * be unique within a form (ff_assign_field_name enforces this on add).
 *
 * @param list<array<array-key, mixed>> $fields
 */
function ff_find_field_index(array $fields, string $name): ?int
{
    foreach ($fields as $idx => $field) {
        $attrs = isset($field['attributes']) && is_array($field['attributes']) ? $field['attributes'] : [];
        if (isset($attrs['name']) && is_string($attrs['name']) && $attrs['name'] === $name) {
            return (int) $idx;
        }
    }
    return null;
}

/**
 * Apply the edit-field patch onto an existing FF-native field. The settings
 * patch is a SHALLOW merge (top-level keys replace; pass null to delete a
 * key). Validation rules are merged so a caller toggling `required` doesn't
 * blow away custom rules.
 *
 * @param array<string, mixed> $existing
 * @param array<string, mixed> $input
 *
 * @return array{field: array<string, mixed>, dropped: list<string>}|WP_Error
 */
function ff_patch_field(array $existing, array $input): array|WP_Error
{
    $type = isset($existing['element']) && is_string($existing['element']) ? $existing['element'] : '';
    if ($type === '') {
        return ff_invalid('Stored field is missing its `element` type.');
    }
    /** @var array<string, mixed> $settings */
    $settings = isset($existing['settings']) && is_array($existing['settings']) ? $existing['settings'] : [];

    $settings = ff_patch_field_label($settings, $input);
    $settings = ff_patch_field_required($settings, $input);
    [$settings, $dropped] = ff_patch_field_settings($settings, $type, $input);

    // Route the patched settings to their FF-native homes (attributes.* for
    // placeholder/maxlength/value, validation_rules/number_step for number
    // constraints, normalized advanced_options) — the create/add path does
    // this via ff_overlay_element; without it an edited placeholder / number
    // bound / option shape was stored inert or, for a bad option shape,
    // fatal on render.
    /** @var array<string, mixed> $attributes */
    $attributes = isset($existing['attributes']) && is_array($existing['attributes']) ? $existing['attributes'] : [];
    /** @var array<string, mixed> $normalized */
    $normalized = ff_normalize_choice_options($type, $settings);
    [$attributes, $settings] = ff_route_field_settings($type, $attributes, $normalized);

    $existing['attributes'] = $attributes;
    $existing['settings'] = $settings;
    return ['field' => $existing, 'dropped' => $dropped];
}

/**
 * Apply the `label` portion of an edit-field patch onto the settings array.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 *
 * @return array<string, mixed>
 */
function ff_patch_field_label(array $settings, array $input): array
{
    if (array_key_exists('label', $input) && is_string($input['label'])) {
        $settings['label'] = $input['label'];
    }
    return $settings;
}

/**
 * Apply the `required` toggle, merging into the existing validation_rules
 * block so custom rules aren't wiped.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 *
 * @return array<string, mixed>
 */
function ff_patch_field_required(array $settings, array $input): array
{
    if (!array_key_exists('required', $input) || !is_bool($input['required'])) {
        return $settings;
    }
    /** @var array<string, mixed> $rules */
    $rules = isset($settings['validation_rules']) && is_array($settings['validation_rules'])
        ? $settings['validation_rules']
        : [];
    if (!$input['required']) {
        unset($rules['required']);
        $settings['validation_rules'] = $rules;
        return $settings;
    }
    $rules['required'] = ['value' => true, 'message' => 'This field is required.'];
    $settings['validation_rules'] = $rules;
    return $settings;
}

/**
 * Apply the `settings` partial merge — whitelisted keys only, null deletes.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 *
 * @return array{0: array<string, mixed>, 1: list<string>}
 */
function ff_patch_field_settings(array $settings, string $type, array $input): array
{
    if (!array_key_exists('settings', $input) || !is_array($input['settings'])) {
        return [$settings, []];
    }
    // Resolve the `options` alias → advanced_options BEFORE the whitelist, so an
    // edit-field caller may use the same friendly alias create/add accept.
    // Without this the non-whitelisted `options` key is dropped here and the
    // field's choices are left unchanged (create/add normalise pre-whitelist).
    $incoming = ff_normalize_choice_options($type, $input['settings']);
    $filtered = ff_filter_settings_whitelist($type, $incoming);
    /** @var mixed $value */
    foreach ($filtered['settings'] as $key => $value) {
        if ($value === null) {
            unset($settings[$key]);
            continue;
        }
        $settings[$key] = $value;
    }
    return [$settings, $filtered['dropped']];
}

/**
 * Encode + persist the form_fields blob after any field-mutation ability.
 * Fires the lifecycle hook + clears the FF form cache. Returns null on
 * success, a WP_Error on save failure. $decoded must come from
 * ff_decode_fields() so the blob's other top-level keys (stepsWrapper, …)
 * are written back unchanged.
 *
 * @param array{fields:list<array<array-key, mixed>>,submitButton:array<array-key, mixed>,...<array-key, mixed>} $decoded
 */
function ff_save_form_fields(Form $form, array $decoded): ?WP_Error
{
    $encoded = ff_encode_fields($decoded);
    if ($encoded === null) {
        return ff_invalid(
            'Field data could not be encoded (a settings object is nested too deeply for JSON, past the 512-level cap). No changes were saved.',
        );
    }
    $form->form_fields = $encoded;
    try {
        $form->save();
    } catch (Throwable $e) {
        return ff_operation_failed('Failed to save form: ' . $e->getMessage());
    }
    ff_after_form_save((int) $form->id, event: 'updated');
    ff_clear_form_cache((int) $form->id);
    return null;
}
