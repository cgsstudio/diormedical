<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricksforge-list-custom-elements', [
    'label' => __('List Bricksforge Custom Elements', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Bricksforge custom-element activation catalog: numeric ID, slug, name, active state, and the Bricks element name(s) each ID registers. Element IDs are a SEPARATE numbering space from tool IDs. ID 5 activates the whole Pro Forms bundle (form container, steps, and every field element) in one flag; ID 13 (API Query Builder Load More) auto-activates whenever the API Query Builder tool is active. Once an ID is active its elements are ordinary Bricks elements: fetch control schemas with novamira/bricks-list-elements and compose them with the bricks content abilities.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'only_active' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Return only the element entries that are currently activated.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'elements' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'bricks_elements' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            'unknown_active_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_list_custom_elements',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to see which brf-* elements are available in Bricks. If none are active, Bricksforge registers nothing — enable elements first, then build with the novamira/bricks-* abilities.',
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
function bricksforge_list_custom_elements(array $input): array
{
    $only_active = brf_bool($input['only_active'] ?? false);
    $active_ids = brf_activated_element_ids();
    $load_more_auto_active = brf_tool_active(18);

    $elements = [];
    foreach (brf_element_map() as $id => $element) {
        $active = in_array($id, $active_ids, strict: true) || $id === 13 && $load_more_auto_active;
        if ($only_active && !$active) {
            continue;
        }
        $elements[] = [
            'id' => $id,
            'slug' => $element['slug'],
            'name' => $element['name'],
            'active' => $active,
            'bricks_elements' => $element['bricks_elements'],
        ];
    }

    return [
        'elements' => $elements,
        'unknown_active_ids' => array_values(array_diff($active_ids, array_keys(brf_element_map()))),
    ];
}
