<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-get-field-type-schema', [
    'label' => __('Get Gravity Forms Field Type Schema', domain: 'novamira-pro'),
    'description' => __(
        'Describe one Gravity Forms field type — the human-readable label, the description, and the configuration options the agent may set when adding or editing a field of that type. Call check-setup first for the authoritative list of types this install can use; this ability returns gravityforms_unknown_type for a slug not registered (e.g. a Pro add-on field type when the add-on is inactive).',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Field type slug (e.g. "text", "email", "select", "fileupload"). See check-setup.available_field_types.',
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
            'description' => ['type' => 'string'],
            'class' => ['type' => 'string'],
            'supports' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['type', 'label_text', 'description', 'class', 'supports'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_get_field_type_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once per field type before crafting an add-field / edit-field payload. Returns the field-type label, description, the underlying PHP class, and the cross-cutting feature flags the type supports (label, description, required, css class, choices, conditional logic, etc.). Returns gravityforms_unknown_type for an unregistered slug.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type: string} $input
 * @return array{type: string, label_text: string, description: string, class: string, supports: list<string>}|WP_Error
 */
function gravityforms_get_field_type_schema(array $input): array|WP_Error
{
    if (!gravityforms_detect_setup()['active']) {
        return gravityforms_not_active();
    }

    $type = trim($input['type']);
    if ($type === '') {
        return gravityforms_invalid_input('type is required and cannot be empty.');
    }

    if (!class_exists('GF_Fields')) {
        return gravityforms_operation_failed('GF_Fields registry is unavailable.');
    }

    $field = \GF_Fields::get($type);
    if (!$field instanceof \GF_Field) {
        return new WP_Error(
            'gravityforms_unknown_type',
            "Field type '{$type}' is not registered on this install. Call check-setup for the canonical list.",
            ['status' => 404],
        );
    }

    return [
        'type' => $type,
        'label_text' => $field->get_form_editor_field_title(),
        'description' => $field->get_form_editor_field_description(),
        'class' => get_class($field),
        'supports' => gravityforms_field_supports($type),
    ];
}

/**
 * Cross-cutting feature flags a field type supports. Hard-coded by common
 * groups: every "input" type supports label/description/required/css; only
 * choice-based types support `choices`; only the post-* types support
 * post-creation binding; etc. Avoids reading the full editor-settings list
 * (large, internal-implementation-detail).
 *
 * @return list<string>
 */
function gravityforms_field_supports(string $type): array
{
    $common = ['label', 'description', 'css_class', 'admin_label', 'visibility', 'conditional_logic'];
    $required_capable = [
        'text',
        'textarea',
        'select',
        'multiselect',
        'checkbox',
        'radio',
        'name',
        'email',
        'phone',
        'address',
        'website',
        'number',
        'date',
        'time',
        'fileupload',
        'list',
        'consent',
        'password',
    ];
    $choice_based = ['select', 'multiselect', 'checkbox', 'radio', 'image-choice'];
    $post_field = [
        'post_title',
        'post_content',
        'post_excerpt',
        'post_tags',
        'post_category',
        'post_image',
        'post_custom_field',
    ];

    $supports = $common;
    if (in_array($type, $required_capable, strict: true)) {
        $supports[] = 'required';
    }
    if (in_array($type, $choice_based, strict: true)) {
        $supports[] = 'choices';
    }
    if (in_array($type, $post_field, strict: true)) {
        $supports[] = 'post_binding';
    }
    return $supports;
}
