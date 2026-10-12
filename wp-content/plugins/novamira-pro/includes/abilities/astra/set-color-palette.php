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
 * Ability: overwrite the color slots of an Astra palette.
 */

wp_register_ability('novamira/astra-set-color-palette', [
    'label' => __('Set Astra Color Palette', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the 9 color slots of a registered Astra palette in the `astra-color-palettes` option. Omit palette_id to target the currently active palette. Accepts hex colors (#rgb or #rrggbb) and rgb()/rgba() strings. Optionally renames the palette via name. The dynamic CSS cache is invalidated after every write. Operation is idempotent — sending the same 9 colors again produces the same state.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'palette_id' => [
                'type' => 'string',
                'description' => 'Palette to overwrite. Defaults to the currently active palette (e.g. palette_1).',
            ],
            'colors' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Exactly 9 color values. Accepted formats: #rgb, #rrggbb, rgb(r,g,b), rgba(r,g,b,a).',
                'minItems' => 9,
                'maxItems' => 9,
            ],
            'name' => [
                'type' => 'string',
                'description' => 'Optional new display name for the palette.',
            ],
        ],
        'required' => ['colors'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'palette_id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'colors' => ['type' => 'array', 'items' => ['type' => 'string']],
            'set_active' => [
                'type' => 'boolean',
                'description' => 'True when the modified palette is also the currently active palette.',
            ],
            'lock_supported' => ['type' => 'boolean'],
        ],
        'required' => ['palette_id', 'name', 'colors', 'set_active', 'lock_supported'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_set_color_palette',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Replaces a full 9-slot Astra palette under the palette lock. Use astra-get-color-palette first to read the current colors and active_id. Every color must be a valid hex (#rgb/#rrggbb) or rgb/rgba string — invalid colors return 400. The CSS cache is invalidated after each write so changes appear immediately on next page load.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{palette_id?: string, colors: list<string>, name?: string} $input
 * @return array<string, mixed>|WP_Error
 */
function astra_set_color_palette(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    $validated = ast_validate_palette_colors($input['colors']);
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    $new_name = is_string($input['name'] ?? null) ? astra_strip_nul_bytes(trim($input['name'])) : null;

    $raw_pid = $input['palette_id'] ?? null;
    $has_explicit_pid = is_string($raw_pid) && $raw_pid !== '';
    $explicit_pid = $has_explicit_pid ? astra_strip_nul_bytes($raw_pid) : '';

    $target_id = '';
    $active_id = '';
    $final_name = '';

    // Read active_id and write the entry inside the same lock so there is no
    // stale-read window between determining the target palette and writing it.
    astra_with_palette_lock(static function () use (
        $explicit_pid,
        $has_explicit_pid,
        $validated,
        $new_name,
        &$target_id,
        &$active_id,
        &$final_name,
    ): void {
        $palette_data = ast_load_palette_option();
        /** @var mixed $current_palette_raw */
        $current_palette_raw = $palette_data['currentPalette'] ?? null;
        $active_id_inner = is_string($current_palette_raw) ? $current_palette_raw : 'palette_1';
        $target_id_inner = $has_explicit_pid ? $explicit_pid : $active_id_inner;
        $final_name = ast_write_palette_entry($target_id_inner, $validated, $new_name);
        $target_id = $target_id_inner;
        $active_id = $active_id_inner;
    });

    astra_invalidate_dynamic_css_cache();

    return [
        'palette_id' => $target_id,
        'name' => $final_name,
        'colors' => $validated,
        'set_active' => $target_id === $active_id,
        'lock_supported' => true,
    ];
}

/**
 * Validate the raw `colors` input — must be an array of exactly 9 valid CSS color strings.
 *
 * @param mixed $raw
 * @return WP_Error|list<string>
 */
function ast_validate_palette_colors(mixed $raw): WP_Error|array
{
    $arr = astra_coerce_array($raw);
    if ($arr === null) {
        return astra_invalid_input_error('colors must be an array.');
    }

    $items = array_values($arr);
    if (count($items) !== 9) {
        return astra_invalid_input_error(sprintf('colors must have exactly 9 elements, %d given.', count($items)));
    }

    /** @var list<string> $out */
    $out = [];
    /** @var mixed $c */
    foreach ($items as $idx => $c) {
        if (!is_string($c)) {
            return astra_invalid_input_error(sprintf('colors[%d] must be a string.', $idx));
        }
        $c = astra_strip_nul_bytes(trim($c));
        if (!ast_is_valid_palette_color($c)) {
            return astra_invalid_input_error(sprintf(
                'colors[%d] "%s" is not a valid hex (#rgb, #rrggbb) or rgb()/rgba() color.',
                $idx,
                $c,
            ));
        }
        $out[] = $c;
    }

    return $out;
}

/**
 * Read-modify-write a single palette entry inside the palette lock.
 * Returns the resolved display name of the updated palette.
 *
 * @param list<string> $colors
 */
function ast_write_palette_entry(string $target_id, array $colors, ?string $new_name): string
{
    $palette_data = ast_load_palette_option();

    // @mago-expect analysis:mixed-assignment
    $palettes_raw = $palette_data['palettes'] ?? null;
    $palette_data['palettes'] = is_array($palettes_raw) ? $palettes_raw : [];
    $palette_data['palettes'][$target_id] = $colors;

    if ($new_name !== null && $new_name !== '') {
        // @mago-expect analysis:mixed-assignment
        $names_raw = $palette_data['presetNames'] ?? null;
        $palette_data['presetNames'] = is_array($names_raw) ? $names_raw : [];
        $palette_data['presetNames'][$target_id] = $new_name;
    }

    /** @var mixed $preset_names_val */
    $preset_names_val = $palette_data['presetNames'] ?? null;
    $preset_names = is_array($preset_names_val) ? $preset_names_val : [];

    $default_names = [
        'palette_1' => 'Default',
        'palette_2' => 'Style 2',
        'palette_3' => 'Style 3',
        'palette_4' => 'Dark',
    ];
    /** @var mixed $resolved */
    $resolved = $preset_names[$target_id] ?? $default_names[$target_id] ?? $target_id;
    $final_name = is_string($resolved) ? $resolved : $target_id;

    update_option('astra-color-palettes', $palette_data);

    return $final_name;
}

/**
 * Whether a string is a valid CSS color for Astra palette slots.
 * Accepts #rgb, #rrggbb (hex) and rgb()/rgba() functional notation.
 */
function ast_is_valid_palette_color(string $c): bool
{
    // Hex 3-char or 6-char.
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $c) === 1) {
        return true;
    }
    // rgb() or rgba() — strict 0-255 range check per channel.
    $byte = '(25[0-5]|2[0-4]\d|1?\d?\d)';
    $alpha = '(0|1|0?\.\d+)';
    $pattern = '/^rgba?\(\s*' . $byte . '\s*,\s*' . $byte . '\s*,\s*' . $byte . '(?:\s*,\s*' . $alpha . ')?\s*\)$/i';
    if (preg_match($pattern, $c) === 1) {
        return true;
    }
    return false;
}
