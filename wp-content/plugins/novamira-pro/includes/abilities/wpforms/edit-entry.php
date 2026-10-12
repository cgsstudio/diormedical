<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-edit-entry', [
    'label' => __('Edit WPForms Entry', domain: 'novamira-pro'),
    'description' => __(
        'Update one or more field values on an existing submission (entry). Required: entry_id, fields (a map of field_id → new value). Only field types in check-setup → entry_handler.get_editable_field_types() can be edited — passing a non-editable type returns wpforms_field_not_editable with the offending list. Pro only.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'fields' => [
                'type' => 'object',
                'description' => 'Map of field_id (string-int key) to the new value. e.g. {"3": "new@example.com"}.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['entry_id', 'fields'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer'],
            'updated_fields' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'entry' => ['type' => 'object'],
        ],
        'required' => ['entry_id', 'updated_fields', 'entry'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_edit_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Editable types are a subset of all field types (no payment, signature, captcha, layout, etc.). A field id missing from the current entry → wpforms_field_not_found. A non-editable type → wpforms_field_not_editable. The updated_fields list reports which fields were actually changed.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id: int, fields: array<array-key, mixed>} $input
 * @return array{entry_id: int, updated_fields: list<int>, entry: array<string, mixed>}|WP_Error
 */
function wpforms_edit_entry(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    if (!wpforms_pro_active()) {
        return wpforms_pro_required('entries');
    }

    $entry = wpforms_entry_or_error($input['entry_id']);
    if ($entry instanceof WP_Error) {
        return $entry;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_entries', (int) ($entry->entry_id ?? 0));
    if ($cap_error !== null) {
        return $cap_error;
    }
    $entry_id = (int) ($entry->entry_id ?? 0);
    $current = wpforms_entry_decode_object_blob($entry, property: 'fields');

    $editable = wpforms_editable_entry_field_types();
    /** @var list<int> $updated_field_ids */
    $updated_field_ids = [];

    /** @var mixed $new_value */
    foreach ($input['fields'] as $field_id => $new_value) {
        $error = wpforms_apply_entry_field_edit($current, $field_id, $new_value, $editable, $updated_field_ids);
        if ($error instanceof WP_Error) {
            return $error;
        }
    }

    if ($updated_field_ids === []) {
        return [
            'entry_id' => $entry_id,
            'updated_fields' => [],
            'entry' => wpforms_entry_row($entry),
        ];
    }

    $handler = wpforms_entry_handler();
    if ($handler === null) {
        return wpforms_pro_required('entries');
    }
    // Validate the encoded payload BEFORE persist — wp_json_encode() returns
    // false on invalid UTF-8 or recursive references; the legacy handler->update()
    // path silently wrote the literal `false` (cast to '') and wiped the entry's
    // entire fields blob (audit batch 3 medium).
    $fields_json = wp_json_encode($current);
    if (!is_string($fields_json)) {
        return new WP_Error(
            'wpforms_edit_entry_encode_failed',
            sprintf('Failed to encode WPForms entry %d fields — refusing to persist an empty blob.', $entry_id),
            ['status' => 500],
        );
    }
    // CAS-guarded persist. $entry->date_modified was captured at read time;
    // the UPDATE matches only when nothing has advanced it. Concurrent edits
    // by another agent / form submission / admin save now surface as 409
    // instead of silently clobbering each other (audit batch 3 HIGH).
    $expected_modified = (string) ($entry->date_modified ?? '');
    $persisted = wpforms_persist_entry_with_cas($entry_id, $fields_json, $expected_modified);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    /** @var mixed $reloaded */
    $reloaded = $handler->get($entry_id);
    $row = is_object($reloaded) ? wpforms_entry_row($reloaded) : wpforms_entry_row($entry);

    return [
        'entry_id' => $entry_id,
        'updated_fields' => $updated_field_ids,
        'entry' => $row,
    ];
}

/**
 * Apply one field edit, validating both existence and editable type. Mutates
 * $current and $updated_field_ids in place; returns WP_Error on any rule
 * violation, null on success.
 *
 * @param array<array-key, mixed> $current
 * @param list<string>            $editable
 * @param list<int>               $updated_field_ids
 */
function wpforms_apply_entry_field_edit(
    array &$current,
    mixed $field_id,
    mixed $new_value,
    array $editable,
    array &$updated_field_ids,
): ?WP_Error {
    if (!is_numeric($field_id)) {
        return wpforms_invalid_input('fields keys must be numeric field ids.');
    }
    $key = (string) (int) $field_id;
    $numeric_id = (int) $field_id;

    if (!array_key_exists($key, $current) || !is_array($current[$key])) {
        return new WP_Error('wpforms_field_not_found', "Field {$numeric_id} does not exist on this entry.", [
            'status' => 404,
        ]);
    }
    /** @var array<string, mixed> $field */
    $field = $current[$key];
    $type = (string) ($field['type'] ?? '');
    if (!in_array($type, $editable, strict: true)) {
        return new WP_Error(
            'wpforms_field_not_editable',
            "Field {$numeric_id} (type '{$type}') is not in the entry-editable type list.",
            ['status' => 400],
        );
    }

    $next = is_array($new_value) || is_object($new_value) ? wp_json_encode($new_value) : (string) $new_value;
    if ($next === false) {
        return wpforms_invalid_input("Could not serialise the new value for field {$numeric_id}.");
    }
    if (($field['value'] ?? null) === $next) {
        return null;
    }
    $field['value'] = $next;
    $current[$key] = $field;
    $updated_field_ids[] = $numeric_id;
    return null;
}
