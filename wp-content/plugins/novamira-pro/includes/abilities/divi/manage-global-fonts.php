<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Abilities: read and set Divi 5 global fonts (the site-wide typography
 * defaults).
 *
 * Divi exposes a fixed set of global font slots — the heading and body font
 * families, their weights, and the base body size / line-height — stored as
 * Customizer options (`heading_font`, `body_font`, …) and emitted on the front
 * end as the `--et_global_heading_font` / `--et_global_body_font` CSS custom
 * properties. They are fixed slots, so this is read + write (get/set), not a
 * create/delete collection.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The Divi global-font option keys this surface reads and writes.
 *
 * @return list<string>
 */
function divi_global_font_keys(): array
{
    return [
        'heading_font',
        'body_font',
        'heading_font_weight',
        'body_font_weight',
        'body_font_size',
        'body_font_height',
        'tablet_body_font_size',
        'phone_body_font_size',
    ];
}

wp_register_ability('novamira/divi-get-global-fonts', [
    'label' => __('Get Divi Global Fonts', domain: 'novamira-pro'),
    'description' => __(
        'Returns Divi\'s site-wide global font settings: the heading and body font families (heading_font, body_font), their weights (heading_font_weight, body_font_weight), and the base body font size / line-height (body_font_size, body_font_height) plus the tablet/phone base sizes. An empty value means the Divi default is in effect. Change them with novamira/divi-set-global-fonts.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'fonts' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_get_global_fonts',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the site\'s global typography before changing it. An empty value means the Divi default applies.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-set-global-fonts', [
    'label' => __('Set Divi Global Fonts', domain: 'novamira-pro'),
    'description' => __(
        'Sets Divi\'s site-wide global fonts. Provide any of: heading_font / body_font (font family names, e.g. "Poppins", "Inter"), heading_font_weight / body_font_weight (e.g. "700"), body_font_size (e.g. "17px"), body_font_height (line-height, e.g. "1.7em"), tablet_body_font_size, phone_body_font_size. Only the fields you provide are changed; an empty string restores the Divi default for that field. Applies site-wide on the front end. For per-module fonts use novamira/divi-edit-module; for reusable color/spacing tokens use the global-color / variable abilities.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'heading_font' => ['type' => 'string', 'description' => 'Heading font family, e.g. "Poppins".'],
            'body_font' => ['type' => 'string', 'description' => 'Body font family, e.g. "Inter".'],
            'heading_font_weight' => ['type' => 'string', 'description' => 'Heading font weight, e.g. "700".'],
            'body_font_weight' => ['type' => 'string', 'description' => 'Body font weight, e.g. "400".'],
            'body_font_size' => ['type' => 'string', 'description' => 'Base body font size, e.g. "17px".'],
            'body_font_height' => ['type' => 'string', 'description' => 'Base body line-height, e.g. "1.7em".'],
            'tablet_body_font_size' => ['type' => 'string', 'description' => 'Base body font size on tablet.'],
            'phone_body_font_size' => ['type' => 'string', 'description' => 'Base body font size on phone.'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'changed' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_set_global_fonts',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Set the site\'s heading/body fonts once for consistent typography. Only the provided fields change.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function divi_get_global_fonts(array $input): array
{
    $fonts = [];
    foreach (divi_global_font_keys() as $key) {
        /** @var mixed $value */
        $value = function_exists('et_get_option') ? et_get_option($key, default_value: '') : '';
        $fonts[$key] = is_scalar($value) ? (string) $value : '';
    }
    return ['fonts' => $fonts];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_set_global_fonts(array $input)
{
    if (!function_exists('et_update_option')) {
        return divi_error('unavailable', message: 'The Divi option API is unavailable on this site.', status: 422);
    }

    $changed = [];
    foreach (divi_global_font_keys() as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $value = divi_string_arg($input, $key);
        et_update_option($key, $value);
        $changed[$key] = $value;
    }

    if ($changed === []) {
        return divi_error(
            'invalid_input',
            message: 'Provide at least one font field to set, e.g. "heading_font", "body_font", "body_font_size".',
        );
    }

    return ['changed' => $changed];
}
