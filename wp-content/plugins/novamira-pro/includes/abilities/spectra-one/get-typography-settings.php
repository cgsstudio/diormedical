<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read the active Spectra One typography settings.
 *
 * Merges two sources of truth: the theme's own `swt_theme_options` bag (body
 * / headings settings the theme's Customizer UI writes to) and the
 * `theme.json` presets (font families, font sizes, line heights, font
 * weights, border radii). The theme-native `spectra-one/get-typography-settings`
 * only echoes the first half — this wrapper adds the theme.json preset
 * inventory so the agent can plan against the actual allowlist.
 */

wp_register_ability('novamira/spectra-one-get-typography-settings', [
    'label' => __('Get Spectra One Typography Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the active Spectra One typography configuration: body settings (font_family, font_weight, line_height, responsive font_size with desktop / tablet / mobile + unit), headings settings (font_family, font_weight, line_height), plus the theme.json presets — bundled font families with weights, the 8 fluid font-size presets (X-Small … XXXX-Large), 5 line-height presets, 9 font-weight presets, and 8 border-radius presets. Presets are the writable allowlist for `novamira/spectra-one-set-typography` (Sprint 3).',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => new \stdClass(),
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'body' => [
                'type' => 'object',
                'properties' => [
                    'font_family' => ['type' => 'string'],
                    'font_weight' => ['type' => 'string'],
                    'line_height' => ['type' => 'string'],
                    'font_size' => [
                        'type' => 'object',
                        'properties' => [
                            'desktop' => ['type' => 'string'],
                            'tablet' => ['type' => 'string'],
                            'mobile' => ['type' => 'string'],
                            'desktop_unit' => ['type' => 'string'],
                        ],
                        'required' => ['desktop', 'tablet', 'mobile', 'desktop_unit'],
                        'additionalProperties' => false,
                    ],
                ],
                'required' => ['font_family', 'font_weight', 'line_height', 'font_size'],
                'additionalProperties' => false,
            ],
            'headings' => [
                'type' => 'object',
                'properties' => [
                    'font_family' => ['type' => 'string'],
                    'font_weight' => ['type' => 'string'],
                    'line_height' => ['type' => 'string'],
                ],
                'required' => ['font_family', 'font_weight', 'line_height'],
                'additionalProperties' => false,
            ],
            'presets' => [
                'type' => 'object',
                'description' => 'theme.json preset allowlists — the writable surface for Sprint 3 set-typography.',
                'properties' => [
                    'font_families' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'slug' => ['type' => 'string'],
                                'font_family' => [
                                    'type' => 'string',
                                    'maxLength' => 500,
                                    'description' => 'CSS font-family declaration (may include a fallback chain such as `Inter, -apple-system, BlinkMacSystemFont, ...`). Capped at 500 chars for progressive disclosure.',
                                ],
                            ],
                            'required' => ['name', 'slug', 'font_family'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'font_sizes' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'slug' => ['type' => 'string'],
                                'size' => ['type' => 'string'],
                            ],
                            'required' => ['name', 'slug', 'size'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'font_weights' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'slug' => ['type' => 'string'],
                                'weight' => ['type' => 'string'],
                            ],
                            'required' => ['name', 'slug', 'weight'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'line_heights' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'slug' => ['type' => 'string'],
                                'size' => ['type' => 'string'],
                            ],
                            'required' => ['name', 'slug', 'size'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'border_radii' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'slug' => ['type' => 'string'],
                                'size' => ['type' => 'string'],
                            ],
                            'required' => ['name', 'slug', 'size'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['font_families', 'font_sizes', 'font_weights', 'line_heights', 'border_radii'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['body', 'headings', 'presets'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_get_typography_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. The `presets` block is the writable allowlist for Sprint 3 novamira/spectra-one-set-typography — values outside these slugs are rejected. Body font_size is responsive (desktop / tablet / mobile) and reports a `desktop_unit` (px by default).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     body: array{font_family: string, font_weight: string, line_height: string, font_size: array{desktop: string, tablet: string, mobile: string, desktop_unit: string}},
 *     headings: array{font_family: string, font_weight: string, line_height: string},
 *     presets: array{font_families: list<array<string, string>>, font_sizes: list<array<string, string>>, font_weights: list<array<string, string>>, line_heights: list<array<string, string>>, border_radii: list<array<string, string>>},
 * }|WP_Error
 */
function spectra_one_get_typography_settings(): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $settings = function_exists('Swt\\get_spectra_one_settings') ? \Swt\get_spectra_one_settings() : [];
    $theme_json = function_exists('Swt\\get_theme_json') ? \Swt\get_theme_json() : [];

    $font_size = so_traverse_array($settings, ['font-size-body']);

    return [
        'body' => [
            'font_family' => so_string_at($settings, ['body-font-family']),
            'font_weight' => so_string_at($settings, ['body-font-weight']),
            'line_height' => so_string_at($settings, ['body-line-height']),
            'font_size' => [
                'desktop' => so_string_at($font_size, ['desktop']),
                'tablet' => so_string_at($font_size, ['tablet']),
                'mobile' => so_string_at($font_size, ['mobile']),
                'desktop_unit' => so_string_at($font_size, ['desktop-unit'], fallback: 'px'),
            ],
        ],
        'headings' => [
            'font_family' => so_string_at($settings, ['headings-font-family']),
            'font_weight' => so_string_at($settings, ['headings-font-weight']),
            'line_height' => so_string_at($settings, ['headings-line-height']),
        ],
        'presets' => [
            'font_families' => so_normalise_font_families(so_traverse_array($theme_json, [
                'settings',
                'typography',
                'fontFamilies',
            ])),
            'font_sizes' => so_normalise_preset_triples(
                so_traverse_array($theme_json, ['settings', 'typography', 'fontSizes']),
                value_key: 'size',
            ),
            'font_weights' => so_normalise_preset_triples(
                so_traverse_array($theme_json, ['settings', 'custom', 'fontWeight']),
                value_key: 'weight',
            ),
            'line_heights' => so_normalise_preset_triples(
                so_traverse_array($theme_json, ['settings', 'custom', 'lineHeight']),
                value_key: 'size',
            ),
            'border_radii' => so_normalise_preset_triples(
                so_traverse_array($theme_json, ['settings', 'custom', 'borderRadius']),
                value_key: 'size',
            ),
        ],
    ];
}
