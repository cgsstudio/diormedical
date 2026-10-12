<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read the Astra global color palette(s).
 */

wp_register_ability('novamira/astra-get-color-palette', [
    'label' => __('Get Astra Color Palette', domain: 'novamira-pro'),
    'description' => __(
        'Reads the `astra-color-palettes` option and returns the active palette id, all registered palettes with their names and 9 color slots, and any preset names set by the user. Pass palette_id to read only a specific palette. Falls back to Astra\'s built-in defaults when the option is not stored.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'palette_id' => [
                'type' => 'string',
                'description' => 'When supplied, only that palette is returned in `palettes`. Omit to get all palettes.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active_id' => [
                'type' => 'string',
                'description' => 'Key of the currently active palette (e.g. palette_1).',
            ],
            'palettes' => [
                'type' => 'object',
                'description' => 'Map of palette_id => {name, colors[9]}.',
                'additionalProperties' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'colors' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['name', 'colors'],
                ],
            ],
        ],
        'required' => ['active_id', 'palettes'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_color_palette',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the active palette and all 4 registered palettes. Pair with astra-set-color-palette to read → modify → write. Colors are hex strings (#rrggbb). Astra stores palette data in the `astra-color-palettes` option, separate from the main `astra-settings` bag.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{palette_id?: string} $input
 * @return array<string, mixed>|WP_Error
 */
function astra_get_color_palette(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    $palette_data = ast_load_palette_option();

    $active_id = is_string($palette_data['currentPalette'] ?? null) ? $palette_data['currentPalette'] : 'palette_1';

    /** @var mixed $palettes_raw_val */
    $palettes_raw_val = $palette_data['palettes'] ?? [];
    $palettes_raw = is_array($palettes_raw_val) ? $palettes_raw_val : [];

    /** @var mixed $preset_names_raw_val */
    $preset_names_raw_val = $palette_data['presetNames'] ?? [];
    $preset_names = is_array($preset_names_raw_val) ? $preset_names_raw_val : [];

    $palettes = ast_build_palettes_map($palettes_raw, $preset_names);

    $filter_id = is_string($input['palette_id'] ?? null) ? astra_strip_nul_bytes($input['palette_id']) : '';

    if ($filter_id !== '' && array_key_exists($filter_id, $palettes)) {
        return ['active_id' => $active_id, 'palettes' => [$filter_id => $palettes[$filter_id]]];
    }

    return ['active_id' => $active_id, 'palettes' => astra_object_or_map($palettes)];
}

/**
 * Build the `palettes` output map from raw option arrays.
 *
 * @param array<array-key, mixed> $palettes_raw   palette_id => list<string>
 * @param array<array-key, mixed> $preset_names   palette_id => name
 * @return array<string, array{name: string, colors: list<string>}>
 */
function ast_build_palettes_map(array $palettes_raw, array $preset_names): array
{
    $default_names = [
        'palette_1' => 'Default',
        'palette_2' => 'Style 2',
        'palette_3' => 'Style 3',
        'palette_4' => 'Dark',
    ];

    $palettes = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($palettes_raw as $pid => $colors_raw) {
        if (!is_string($pid) || !is_array($colors_raw)) {
            continue;
        }

        /** @var mixed $preset_name */
        $preset_name = $preset_names[$pid] ?? $default_names[$pid] ?? $pid;
        $name = is_string($preset_name) ? $preset_name : $pid;

        $colors = [];
        /** @var mixed $c */
        foreach ($colors_raw as $c) {
            if (!is_string($c)) {
                continue;
            }
            $colors[] = $c;
        }

        $palettes[$pid] = ['name' => $name, 'colors' => $colors];
    }

    return $palettes;
}
