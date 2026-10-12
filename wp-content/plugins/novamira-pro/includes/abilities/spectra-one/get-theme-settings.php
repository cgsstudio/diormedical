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
 * Ability: read the active Spectra One theme settings — layout widths, the
 * `swt_theme_options` bag, spacing scale, and block-style declarations.
 *
 * Wraps the theme-native `spectra-one/get-theme-settings` with a tighter
 * output shape and additional surfaces the theme-native ability does not
 * report (layout widths, spacing scale, block-style names, custom templates
 * inventory).
 */

wp_register_ability('novamira/spectra-one-get-theme-settings', [
    'label' => __('Get Spectra One Theme Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the active Spectra One theme configuration: layout widths (contentSize + wideSize), the swt_theme_options bag (scroll_top toggle + related global flags), the 8 spacing scale sizes, the list of block styles declared by the theme (targeting core/site-title, core/navigation, etc.), and the theme.json customTemplates inventory. Progressive disclosure: block-style rows are compact (block + name + label); pattern content and template HTML are NOT echoed — use novamira/spectra-one-list-templates + novamira/read-file or an execute-php snippet to inspect a single template.',
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
            'options' => [
                'type' => 'object',
                'description' => 'The theme\'s Customizer-managed swt_theme_options bag.',
                'properties' => [
                    'scroll_top' => ['type' => 'boolean'],
                ],
                'required' => ['scroll_top'],
                'additionalProperties' => false,
            ],
            'layout' => [
                'type' => 'object',
                'properties' => [
                    'content_size' => ['type' => 'string'],
                    'wide_size' => ['type' => 'string'],
                ],
                'required' => ['content_size', 'wide_size'],
                'additionalProperties' => false,
            ],
            'spacing_sizes' => [
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
            'block_styles' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'block' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                    'required' => ['block', 'name', 'label'],
                    'additionalProperties' => false,
                ],
            ],
            'custom_templates' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['name', 'title', 'post_types'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['options', 'layout', 'spacing_sizes', 'block_styles', 'custom_templates'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_get_theme_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Layout widths + spacing scale + block-style names are the writable surface for Sprint 3 novamira/spectra-one-set-theme-settings; every slug reported here is on the allowlist.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     options: array{scroll_top: bool},
 *     layout: array{content_size: string, wide_size: string},
 *     spacing_sizes: list<array<string, string>>,
 *     block_styles: list<array<string, string>>,
 *     custom_templates: list<array{name: string, title: string, post_types: list<string>}>,
 * }|WP_Error
 */
function spectra_one_get_theme_settings(): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    /** @var mixed $raw_options */
    $raw_options = get_option('swt_theme_options', default_value: []);
    $options = is_array($raw_options) ? $raw_options : [];
    $theme_json = function_exists('Swt\\get_theme_json') ? \Swt\get_theme_json() : [];

    return [
        'options' => [
            'scroll_top' => filter_var($options['scroll_top'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ],
        'layout' => [
            'content_size' => so_string_at($theme_json, ['settings', 'layout', 'contentSize']),
            'wide_size' => so_string_at($theme_json, ['settings', 'layout', 'wideSize']),
        ],
        'spacing_sizes' => so_normalise_preset_triples(
            so_traverse_array($theme_json, ['settings', 'spacing', 'spacingSizes']),
            value_key: 'size',
        ),
        'block_styles' => so_normalise_block_styles(so_traverse_array($theme_json, ['styles', 'blocks'])),
        'custom_templates' => so_normalise_custom_templates(so_traverse_array($theme_json, ['customTemplates'])),
    ];
}
