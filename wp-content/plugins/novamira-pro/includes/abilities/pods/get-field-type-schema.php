<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Return the agent-relevant `options` schema for a Pods field type.
 *
 * Pods fields support dozens of types and each has 5–20 type-specific
 * options (`pick_object`, `file_format_type`, `number_decimals`, …). Listing
 * the full matrix on every `pods-create-field` call wastes context, so we
 * expose it via a separate ability that the agent calls only when it needs
 * the option keys for a specific type.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-get-field-type-schema', [
    'label' => __('Get Pods Field Type Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the agent-relevant `options` keys for a single Pods field type (e.g. `pick`, `file`, `number`). Each entry has `key`, `type`, and a one-line description. Call this before `pods-create-field` when you need to set type-specific options like `pick_object`, `file_format_type`, or `number_decimals`. Field types not listed here still accept any option keys Pods recognises — this is a hint surface, not a strict schema.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'field_type' => [
                'type' => 'string',
                'description' => 'The field type to look up.',
                'enum' => NOVAMIRA_PODS_FIELD_TYPES,
            ],
        ],
        'required' => ['field_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field_type' => ['type' => 'string'],
            'options' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                    ],
                ],
            ],
            'all_field_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Every field type Pods 3.x ships with — useful when you guessed the wrong one.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_get_field_type_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'For relationship fields (`pick`), the most important options are `pick_object` + `pick_val` (target) and `pick_format_type` (single vs multi). For media (`file`, `avatar`), set `file_format_type` to `single` or `multi` depending on the cardinality you want.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_get_field_type_schema(array $input): array
{
    /** @var mixed $field_type */
    $field_type = $input['field_type'] ?? '';
    $field_type = is_string($field_type) ? $field_type : '';

    $options = NOVAMIRA_PODS_FIELD_TYPE_OPTIONS[$field_type] ?? [];

    return [
        'field_type' => $field_type,
        'options' => $options,
        'all_field_types' => NOVAMIRA_PODS_FIELD_TYPES,
    ];
}
