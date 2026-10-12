<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-get-defaults', [
    'label' => __('Get GeneratePress Defaults', domain: 'novamira-pro'),
    'description' => __(
        'Returns the four default sets that compose GeneratePress\'s known settings surface: `settings` (layout / structural — generate_get_defaults), `colors` (palette — generate_get_color_defaults), `typography` (font stack — generate_get_default_fonts), `spacing` (padding / margins — generate_spacing_get_defaults). Use as a schema reference before calling generatepress-set-settings — the value type per key follows the default value\'s type (int / string / bool / array).',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'section' => [
                'type' => 'string',
                'enum' => ['settings', 'colors', 'typography', 'spacing', 'all'],
                'description' => 'Limit the response to a single section. Default `all` returns the four together. Use `colors` or `typography` to drill down without context bloat from the unrelated sections.',
                'default' => 'all',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => ['type' => 'object'],
            'colors' => ['type' => 'object'],
            'typography' => ['type' => 'object'],
            'spacing' => ['type' => 'object'],
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_get_defaults',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this as the schema reference for generatepress-set-settings. The default value\'s type for each key signals the type the setter expects (int for container_width, string for layout_setting, etc.). For repeated reads, prefer `section` to scope the response.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{section?: string} $input
 * @return array<string, mixed>
 */
function generatepress_get_defaults(array $input): array
{
    $section = $input['section'] ?? 'all';

    $sets = [
        'settings' => function_exists('generate_get_defaults') ? generate_get_defaults() : [],
        'colors' => function_exists('generate_get_color_defaults') ? generate_get_color_defaults() : [],
        'typography' => function_exists('generate_get_default_fonts') ? generate_get_default_fonts() : [],
        'spacing' => function_exists('generate_spacing_get_defaults') ? generate_spacing_get_defaults() : [],
    ];

    if ($section === 'all') {
        return $sets;
    }
    return isset($sets[$section]) ? [$section => $sets[$section]] : [];
}
