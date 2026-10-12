<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-get-field-type-schema', [
    'label' => __('Get Ninja Forms Field Type Schema', domain: 'novamira-pro'),
    'description' => __(
        'Describe one Ninja Forms field type — its human label, its editor section, and the WHITELIST of setting keys add-field / edit-field accept under the field descriptor for that type (e.g. label, label_pos, required, default, placeholder, classes, key, …). Call novamira/ninja-forms-get-field-types-schema first for the catalog of available type slugs, then this ability per chosen type before crafting an add-field payload. Returns ninja_forms_field_type_not_found when the slug is not registered on this install (the add-on that provides it is inactive).',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Field type slug (e.g. "textbox", "email", "listselect"). See get-field-types-schema.',
            ],
        ],
        'required' => ['type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'supported_settings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['type', 'label', 'category', 'supported_settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_get_field_type_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once per field type before crafting an add-field / edit-field payload. `supported_settings` is the authoritative list of setting keys that type accepts — keys outside it are ignored by Ninja Forms. Returns ninja_forms_field_type_not_found for an unregistered slug.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type?: string} $input
 *
 * @return array{type: string, label: string, category: string, supported_settings: list<string>}|WP_Error
 */
function ninja_forms_get_field_type_schema(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $type = isset($input['type']) ? trim($input['type']) : '';
    if ($type === '') {
        return ninja_forms_invalid('type is required and must be a non-empty string.');
    }

    $registry = ninja_forms_field_registry();
    if (!isset($registry[$type])) {
        return ninja_forms_not_found('field_type', $type);
    }
    $field = $registry[$type];

    return [
        'type' => $type,
        'label' => ninja_forms_field_type_label($field, $type),
        'category' => ninja_forms_field_type_category($field),
        'supported_settings' => ninja_forms_field_supported_settings($field),
    ];
}

/**
 * The setting keys a Ninja Forms field type accepts, read from the type's
 * `get_settings()` definition map (keyed by setting name). Returns a sorted
 * list of the keys — the names an add-field / edit-field descriptor may set.
 * The full per-setting definitions are intentionally NOT returned (large,
 * editor-internal); the key list is the actionable whitelist.
 *
 * @return list<string>
 */
function ninja_forms_field_supported_settings(\NF_Abstracts_Field $field): array
{
    $settings = $field->get_settings();
    $keys = [];
    foreach ($settings as $key => $_definition) {
        if ($key === '') {
            continue;
        }
        $keys[] = $key;
    }
    sort($keys);
    return $keys;
}
