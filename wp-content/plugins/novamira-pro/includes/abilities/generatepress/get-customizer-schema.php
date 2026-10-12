<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-get-customizer-schema', [
    'label' => __('Get Customizer Schema (GeneratePress)', domain: 'novamira-pro'),
    'description' => __(
        'Returns the GeneratePress + GP Premium sections + controls registered in the WordPress Customizer. Each section is `{id, title, panel, priority}` and each control is `{id, setting, label, type, choices, section, priority}`. Reference for generatepress-set-settings — the `setting` field of each control is the key you would pass inside the `settings` patch (controls bound to `generate_settings[<key>]` map to the simple `<key>` form set-settings accepts). Use `scope=gp_only` (default) to keep the response to GeneratePress-owned entries (theme + Premium), `scope=all` to include core WP and other plugins.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'scope' => [
                'type' => 'string',
                'enum' => ['gp_only', 'all'],
                'description' => '`gp_only` (default) keeps sections/controls whose id starts with `generate_` / `gp_`, or whose setting binds to `generate_settings[…]`. `all` returns every registered section/control.',
                'default' => 'gp_only',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'Optional section id filter (e.g. `generate_colors_section`). Returns only the matching section + its controls.',
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
        'required' => ['sections', 'controls'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_get_customizer_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this before generatepress-set-settings whenever you need the exact key name for a Customizer option (e.g. "what is the setting key for Header Padding Top?"). Pair with generatepress-get-defaults for the default values. Output can be large under `scope=all`; prefer `section=...` to narrow.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{scope?: string, section?: string} $input
 * @return array<string, mixed>
 */
function generatepress_get_customizer_schema(array $input): array
{
    $scope = $input['scope'] ?? 'gp_only';
    $section_filter = isset($input['section']) ? trim($input['section']) : '';

    $manager = gp_bootstrap_customize_manager();
    if ($manager === null) {
        return ['sections' => [], 'controls' => [], 'sections_total' => 0, 'controls_total' => 0];
    }

    $sections = gp_collect_customizer_sections($manager, $scope, $section_filter);
    $controls = gp_collect_customizer_controls($manager, $scope, $section_filter);

    return [
        'sections' => $sections,
        'controls' => $controls,
        'sections_total' => count($sections),
        'controls_total' => count($controls),
    ];
}
