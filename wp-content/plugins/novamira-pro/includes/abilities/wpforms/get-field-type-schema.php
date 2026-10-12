<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-get-field-type-schema', [
    'label' => __('Get WPForms Field Type Schema', domain: 'novamira-pro'),
    'description' => __(
        'Describe one WPForms field type — the label-text, its edition (lite or pro), the cross-cutting features it supports (label, description, required, css class, conditional logic), and the type-specific options the agent can set (e.g. choices for select / radio / checkbox, format for name / date-time / phone, extensions for file-upload). Call check-setup first for the authoritative list of types this install can use; this ability returns null when the type is unknown or requires Pro on a Lite install.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Field type slug (e.g. "text", "email", "select", "file-upload"). See check-setup.available_field_types.',
            ],
        ],
        'required' => ['type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string'],
            'label_text' => ['type' => 'string'],
            'edition' => ['type' => 'string'],
            'supports' => ['type' => 'array', 'items' => ['type' => 'string']],
            'options' => [
                'type' => 'object',
                'additionalProperties' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                    ],
                    'required' => ['description', 'type'],
                ],
            ],
        ],
        'required' => ['type', 'label_text', 'edition', 'supports', 'options'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_get_field_type_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once per field type before crafting an add-field / edit-field payload. Returns the supported cross-cutting features (always include label) plus the type-specific options (e.g. choices, format, min/max). Returns wpforms_unknown_type for a slug not registered on this install.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type: string} $input
 * @return array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}|WP_Error
 */
function wpforms_get_field_type_schema(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }

    $type = trim($input['type']);
    if ($type === '') {
        return wpforms_invalid_input('type is required.');
    }

    $schema = wpforms_field_type_schema($type);
    if ($schema === null) {
        return new WP_Error(
            'wpforms_unknown_type',
            "Field type '{$type}' is not available on this install. See check-setup.available_field_types.",
            ['status' => 404],
        );
    }

    return $schema;
}
