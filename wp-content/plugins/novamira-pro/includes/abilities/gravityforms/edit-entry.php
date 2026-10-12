<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-edit-entry', [
    'label' => __('Edit Gravity Forms Entry', domain: 'novamira-pro'),
    'description' => __(
        'Update field values on one Gravity Forms entry. Pass `fields` as a map of input id → new value (e.g. {"1": "New Name", "2.3": "Last"}). Multi-input fields (name, address, checkbox) accept individual sub-input ids ("1.3"). Optional `expected_date_updated` enables optimistic concurrency — the call rejects with gravityforms_concurrent_modification (409) if the entry was touched since the last read.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'fields' => [
                'type' => 'object',
                'description' => 'Map of input id (string) → new value (string|number|boolean|null). Keys can be top-level field ids ("1") or sub-input ids ("1.3" for first name on a name field).',
                'additionalProperties' => true,
            ],
            'expected_date_updated' => [
                'type' => 'string',
                'description' => 'Optional CAS token (entry.date_updated from last read).',
            ],
        ],
        'required' => ['entry_id', 'fields'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
            'updated_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
            'date_updated' => ['type' => 'string'],
        ],
        'required' => ['entry_id', 'form_id', 'updated_fields', 'date_updated'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_edit_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Each field update goes through GFAPI::update_entry_field individually so input-id granularity is preserved. Returns the actual list of keys that took effect.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id: int, fields: array<array-key, mixed>, expected_date_updated?: string} $input
 * @return array{entry_id: int, form_id: int, updated_fields: list<string>, date_updated: string}|WP_Error
 */
function gravityforms_edit_entry(array $input): array|WP_Error
{
    $entry = gravityforms_entry_or_error($input['entry_id']);
    if ($entry instanceof WP_Error) {
        return $entry;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_entries']);
    if ($cap !== null) {
        return $cap;
    }

    $entry_id = (int) $entry['id'];

    if (isset($input['expected_date_updated']) && $input['expected_date_updated'] !== ($entry['date_updated'] ?? '')) {
        return new WP_Error(
            'gravityforms_concurrent_modification',
            "Entry {$entry_id} was modified by another writer between read and persist — re-fetch and retry.",
            ['status' => 409],
        );
    }

    if ($input['fields'] === []) {
        return gravityforms_invalid_input('fields cannot be empty.');
    }

    $updated = [];
    foreach (array_keys($input['fields']) as $input_id) {
        // PHP coerces numeric string keys to int when stored as array keys,
        // so the caller's '"1": "value"' arrives here as $input_id===1 (int).
        // Coerce back to string for the schema-validated output.
        $key = is_int($input_id) ? (string) $input_id : $input_id;
        /** @var mixed $value */
        $value = $input['fields'][$input_id];
        $result = \GFAPI::update_entry_field($entry_id, $key, $value);
        if ($result instanceof WP_Error) {
            return $result;
        }
        if ($result === true) {
            $updated[] = $key;
        }
    }

    $refreshed = \GFAPI::get_entry($entry_id);
    return [
        'entry_id' => $entry_id,
        'form_id' => (int) ($entry['form_id'] ?? 0),
        'updated_fields' => $updated,
        'date_updated' => $refreshed === false ? '' : (string) ($refreshed['date_updated'] ?? ''),
    ];
}
