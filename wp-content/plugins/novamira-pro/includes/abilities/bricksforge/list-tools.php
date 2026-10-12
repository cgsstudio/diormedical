<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricksforge-list-tools', [
    'label' => __('List Bricksforge Tools', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Bricksforge tools (feature modules such as Popups, Maintenance Mode, Mega Menu, Email Designer, Form Submissions) with their numeric ID, slug, name, active state, availability, and whether the tool carries a settings blob in brf_tool_settings. Bricksforge stores activation as raw numeric IDs; this catalog is the ID-to-name map for the IDs this integration knows. available=false means the installed Bricksforge does not provide that tool, so it cannot be enabled. Slug global-classes is the Global Classes Backups tool; global-classes-manager is the Global Classes management tool. Where animation-panel and node-editor are available, the panel tool only runs Events: GSAP timelines need animation-panel and node flows need node-editor. Active IDs not in the catalog are reported in unknown_active_ids.',
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
                'description' => 'Return only the tools that are currently activated.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'tools' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'available' => [
                            'type' => 'boolean',
                            'description' => 'Whether the installed Bricksforge provides this tool.',
                        ],
                        'has_settings' => ['type' => 'boolean'],
                    ],
                ],
            ],
            'unknown_active_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_list_tools',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to discover which Bricksforge feature modules are on. Tool IDs and custom-element IDs are SEPARATE numbering spaces (tool 5 = Popups, element 5 = Pro Forms — Pro Forms is an element, not a tool). Only enable tools with available=true.',
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
function bricksforge_list_tools(array $input): array
{
    $only_active = brf_bool($input['only_active'] ?? false);
    $settings_ids = brf_tool_ids_with_settings();

    $tools = [];
    foreach (brf_tool_map() as $id => $tool) {
        // Loose active check, mirroring upstream's in_array on the raw option.
        $active = brf_tool_active($id);
        if ($only_active && !$active) {
            continue;
        }
        $tools[] = [
            'id' => $id,
            'slug' => $tool['slug'],
            'name' => $tool['name'],
            'active' => $active,
            'available' => brf_tool_available($id),
            'has_settings' => in_array($id, $settings_ids, strict: true),
        ];
    }

    return [
        'tools' => $tools,
        'unknown_active_ids' => array_values(array_diff(brf_activated_tool_ids(), array_keys(brf_tool_map()))),
    ];
}
