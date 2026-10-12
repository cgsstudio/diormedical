<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate the Astra Customizer sections and controls.
 */

wp_register_ability('novamira/astra-list-customizer-settings', [
    'label' => __('List Astra Customizer Settings', domain: 'novamira-pro'),
    'description' => __(
        'Bootstraps the WordPress Customizer and returns the Astra-owned sections + controls registered in it. Each section: {id, title, panel, priority}. Each control: {id, setting, settings, label, type, choices, section, priority}. The `setting` id maps to the key in `astra-settings` (or a theme_mod). Use `scope=astra_only` (default) to keep the response to Astra-prefixed sections (`astra-*`, `ast-*`, `astra_*`). Pass `section` to drill into a single section.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'scope' => [
                'type' => 'string',
                'enum' => ['astra_only', 'all'],
                'description' => '`astra_only` (default) returns only sections/controls whose id starts with `astra-`, `astra_`, `ast-`, or `ast_`. `all` returns every registered section/control.',
                'default' => 'astra_only',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'Optional section id filter. Returns only the matching section and its controls.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'sections' => ['type' => 'array'],
            'controls' => ['type' => 'array'],
            'sections_total' => ['type' => 'integer'],
            'controls_total' => ['type' => 'integer'],
        ],
        'required' => ['sections', 'controls', 'sections_total', 'controls_total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_list_customizer_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use before astra-edit-settings to discover the exact key name for a Customizer control (e.g. "what is the setting key for Header Padding Top?"). The `setting` field of each control is the key that maps to `astra-settings`. Pair with astra-get-defaults for default values. Pass `section=<id>` to narrow the response significantly.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{scope?: string, section?: string} $input
 * @return array{sections: list<array<string, mixed>>, controls: list<array<string, mixed>>, sections_total: int, controls_total: int}
 */
function astra_list_customizer_settings(array $input): array
{
    $scope = $input['scope'] ?? 'astra_only';
    $section_filter = isset($input['section']) ? astra_strip_nul_bytes(trim($input['section'])) : '';

    $manager = ast_bootstrap_customize_manager();
    if ($manager === null) {
        return ['sections' => [], 'controls' => [], 'sections_total' => 0, 'controls_total' => 0];
    }

    $sections = astra_lcs_collect_sections($manager, $scope, $section_filter);
    $controls = astra_lcs_collect_controls($manager, $scope, $section_filter);

    return [
        'sections' => $sections,
        'controls' => $controls,
        'sections_total' => count($sections),
        'controls_total' => count($controls),
    ];
}

/**
 * Walk the Customizer sections and build the public rows list.
 *
 * @return list<array{id: string, title: string, panel: string, priority: int}>
 */
function astra_lcs_collect_sections(\WP_Customize_Manager $manager, string $scope, string $section_filter): array
{
    $out = [];
    /** @var \WP_Customize_Section $section */
    foreach ($manager->sections() as $section) {
        if ($section_filter !== '' && $section->id !== $section_filter) {
            continue;
        }
        if ($scope === 'astra_only' && !ast_is_astra_section($section->id)) {
            continue;
        }
        $out[] = [
            'id' => $section->id,
            'title' => $section->title,
            'panel' => $section->panel,
            'priority' => (int) $section->priority,
        ];
    }
    return $out;
}

/**
 * Walk the Customizer controls and build the public rows list.
 *
 * @return list<array<string, mixed>>
 */
function astra_lcs_collect_controls(\WP_Customize_Manager $manager, string $scope, string $section_filter): array
{
    $astra_sections = ast_known_astra_section_ids($manager);
    $out = [];

    /** @var \WP_Customize_Control $control */
    foreach ($manager->controls() as $control) {
        if ($section_filter !== '' && $control->section !== $section_filter) {
            continue;
        }
        $setting_id = ast_resolve_customizer_setting_id($control->setting);
        $settings_map = ast_resolve_customizer_settings_map($control->settings);

        // When the primary setting id is blank but the multi-settings map has
        // entries, use the first entry so the agent gets a usable key.
        if ($setting_id === '' && $settings_map !== []) {
            $setting_id = $settings_map['default'] ?? $settings_map['desktop'] ?? array_values($settings_map)[0];
        }

        if ($scope === 'astra_only') {
            $belongs =
                ast_is_astra_control($control->id, $setting_id)
                || in_array($control->section, $astra_sections, strict: true);
            if (!$belongs) {
                continue;
            }
        }

        $out[] = [
            'id' => $control->id,
            'setting' => $setting_id,
            'settings' => $settings_map,
            'label' => $control->label,
            'type' => $control->type,
            'choices' => $control->choices,
            'section' => $control->section,
            'priority' => (int) $control->priority,
        ];
    }

    return $out;
}
