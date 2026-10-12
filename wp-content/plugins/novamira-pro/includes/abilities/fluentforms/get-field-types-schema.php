<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-get-field-types-schema', [
    'label' => __('List Fluent Forms Field Types', domain: 'novamira-pro'),
    'description' => __(
        'Return the catalog of every Fluent Forms field type the integration can build via add-field / create-form. Output is compact: per entry {type, label, category, pro_only}. Use this FIRST when constructing a form, then call novamira/fluentforms-get-field-type-schema for the per-type setting whitelist + example_input. When Pro is absent, entries with pro_only=true are filtered out — the agent never sees a type it cannot use. Categories: general (12 standard inputs + choices), advanced (composite + decorative), pro_advanced (file/image/repeater/NPS/phone/chained-select/post-selection/rich-text/…), pro_container (multi-step form_step), pro_payment (payment_method/coupon/subscription/…).',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'types' => ['type' => 'array', 'items' => ['type' => 'object']],
            'count' => ['type' => 'integer'],
            'pro_active' => ['type' => 'boolean'],
        ],
        'required' => ['types', 'count', 'pro_active'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_field_types_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Cache this for the session — the catalog is stable. Branch on each entry\'s pro_only flag to know what would unlock with Pro. For detailed settings whitelist + example_input per type, call get-field-type-schema (singular) with the chosen type.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{types: list<array{type:string,label:string,category:string,pro_only:bool}>, count:int, pro_active:bool}
 */
function fluentforms_get_field_types_schema(): array
{
    $setup = ff_detect_setup();
    $catalog = ff_field_catalog();
    $types = [];
    foreach ($catalog as $entry) {
        if ($entry['pro_only'] && !$setup['pro']) {
            continue;
        }
        $types[] = [
            'type' => $entry['type'],
            'label' => $entry['label'],
            'category' => $entry['category'],
            'pro_only' => $entry['pro_only'],
        ];
    }
    return [
        'types' => $types,
        'count' => count($types),
        'pro_active' => $setup['pro'],
    ];
}

wp_register_ability('novamira/fluentforms-get-field-type-schema', [
    'label' => __('Get a Fluent Forms Field Type Detail', domain: 'novamira-pro'),
    'description' => __(
        'Detailed schema for ONE Fluent Forms field type. Returns {type, label, description, category, pro_only, supported_settings[] — the WHITELIST of keys add-field/edit-field accept under settings.*, default_attributes (FF-native), default_settings, example_input — a ready-to-pass {type, label, required, settings:{…}} payload}. Returns fluentforms_invalid_input (400) when the type is absent from the catalog. The catalog ALWAYS includes Pro types — even on a free install — so the agent can learn what would unlock with Pro; add-field still rejects Pro-only types on a free install.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => ['type' => 'string', 'description' => 'Field type slug (e.g. "input_text").'],
        ],
        'required' => ['type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'pro_only' => ['type' => 'boolean'],
            'supported_settings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'default_attributes' => ['type' => 'object'],
            'example_input' => ['type' => 'object'],
        ],
        'required' => [
            'type',
            'label',
            'description',
            'category',
            'pro_only',
            'supported_settings',
            'default_attributes',
            'example_input',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_field_type_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'supported_settings is the AUTHORITATIVE whitelist for the settings.* shape add-field / edit-field accept — keys outside are silently dropped. example_input is copy-pasteable into add-field. Use this before constructing a non-trivial field so you know which keys land vs which get filtered out.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array<string, mixed>|WP_Error
 */
function fluentforms_get_field_type_schema(array $input): array|WP_Error
{
    $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : '';
    if ($type === '') {
        return ff_invalid('type is required and must be a non-empty string.');
    }
    $catalog = ff_field_catalog();
    if (!isset($catalog[$type])) {
        return ff_invalid(sprintf("Unknown field type '%s'. Call get-field-types-schema for the catalog.", $type));
    }
    $entry = $catalog[$type];
    return [
        'type' => $entry['type'],
        'label' => $entry['label'],
        'description' => $entry['description'],
        'category' => $entry['category'],
        'pro_only' => $entry['pro_only'],
        'supported_settings' => $entry['supported_settings'],
        'default_attributes' => ff_default_attributes($type),
        'example_input' => ff_example_input_for_type($type),
    ];
}

/**
 * Build a copy-pasteable example payload for the given field type. The
 * example always lands in the add-field input shape so the agent can
 * literally paste it into the next call.
 *
 * @return array<string, mixed>
 */
function ff_example_input_for_type(string $type): array
{
    $label = match ($type) {
        'input_email' => 'Email address',
        'input_number' => 'Quantity',
        'input_url' => 'Website',
        'input_date' => 'Date of birth',
        'input_file' => 'Attach a file',
        'input_image' => 'Profile image',
        'phone_field' => 'Phone',
        'address' => 'Mailing address',
        'input_name' => 'Full name',
        'ratings' => 'How would you rate us?',
        'payment_method' => 'Payment method',
        default => 'Your input',
    };
    $settings = match ($type) {
        'input_text', 'input_email', 'input_url', 'textarea' => ['placeholder' => 'Type here…'],
        'input_number' => ['min' => 1, 'max' => 100, 'step' => 1],
        'input_date' => ['date_format' => 'Y-m-d'],
        'select', 'multi_select', 'input_checkbox', 'input_radio' => [
            'advanced_options' => [
                ['label' => 'Option 1', 'value' => 'option_1'],
                ['label' => 'Option 2', 'value' => 'option_2'],
            ],
        ],
        'ratings' => ['number_of_stars' => 5],
        'input_file' => ['max_file_count' => 3, 'max_file_size' => 5_242_880],
        default => [],
    };
    return [
        'type' => $type,
        'label' => $label,
        'required' => true,
        'settings' => $settings,
    ];
}
