<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Create a brand-new Advanced Content Type (Pods custom-table pod).
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-create-act', [
    'label' => __('Create Pods Advanced Content Type', domain: 'novamira-pro'),
    'description' => __(
        'Creates a brand-new Advanced Content Type — a Pods-managed content collection backed by a dedicated database table (`wp_pods_<name>`) instead of postmeta. Use this when you need scale, fast queries, and data that should not pollute the WP posts table. Requires the Advanced Content Types component to be active (`pods-check-setup` reports `act_component_active`).',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Slug / internal name. Becomes the table suffix (`wp_pods_<name>`).',
            ],
            'label_singular' => ['type' => 'string'],
            'label_plural' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'storage' => [
                'type' => 'string',
                'enum' => NOVAMIRA_PODS_ACT_STORAGE,
                'default' => 'table',
                'description' => '`table` (default, the whole point of an ACT) → dedicated table; `meta` keeps it as a CPT under the hood; `none` → no field storage.',
            ],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Free-form pod options.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_create_act',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'ACTs are *not* WP post types — they do not appear in the admin posts UI, are not editable in Gutenberg / Elementor / Bricks, and do not participate in the standard `WP_Query` loop. Read items via `pods-list-items` / `pods-get-item`. If you actually need a post-type-shaped collection that other plugins integrate with, use `pods-create-cpt`.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_create_act(array $input): array
{
    if (!pods_act_runtime_available()) {
        return [
            'success' => false,
            'error' => 'The Pods Advanced Content Types component is not active — enable it under Pods → Settings → Components and try again.',
        ];
    }
    $name = pods_input_string($input, key: 'name');
    if ($name === null) {
        return ['success' => false, 'error' => '`name` is required.'];
    }

    $params = pods_build_act_params($name, $input);
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_pod($params));
    return pods_format_save_response($result, $name);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_act_params(string $name, array $input): array
{
    $labels = pods_normalize_labels($input, $name);
    $params = [
        'name' => $name,
        'type' => 'pod',
        'storage' => pods_input_string($input, key: 'storage') ?? 'table',
        'label' => $labels['plural'],
        'label_singular' => $labels['singular'],
        'description' => pods_input_string($input, key: 'description') ?? '',
    ];
    return array_merge($params, pods_input_options($input));
}
