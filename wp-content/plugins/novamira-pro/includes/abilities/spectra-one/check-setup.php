<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Spectra One theme presence + capability snapshot.
 *
 * Discovery entry-point for the specialization. Every other
 * `novamira/spectra-one-*` ability early-returns `spectra_one_not_active`
 * when the theme is missing or under-min, so agents should call this once
 * at the start of a session and gate the rest of the workflow on the
 * `theme.min_satisfied` flag.
 */

wp_register_ability('novamira/spectra-one-check-setup', [
    'label' => __('Check Spectra One Setup', domain: 'novamira-pro'),
    'description' => __(
        'Diagnostic snapshot of the Spectra One theme install: theme active flag + version + minimum supported, whether Spectra One is the currently active WordPress stylesheet (survives mid-request switch_theme), companion Spectra (UAGB) plugin status + version + minimum supported (the plugin is optional — most spectra-one abilities work without it), companion Custom Fonts plugin presence, whether the WordPress Abilities API is available (WP 6.9+, required by the theme-native spectra-one/* surface), and current-user capability. Lean by design: does NOT echo settings / templates / patterns content here — call the corresponding `get-*` / `list-*` abilities for that.',
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
            'theme' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'is_stylesheet' => [
                        'type' => 'boolean',
                        'description' => 'Whether Spectra One is the currently active WP stylesheet or parent template (survives a mid-request switch_theme() call).',
                    ],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'is_stylesheet', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'plugin' => [
                'type' => 'object',
                'description' => 'Companion Spectra (UAGB) plugin state. The plugin is OPTIONAL — most spectra-one abilities work with the theme alone. Only `novamira/spectra-one-list-spectra-blocks` requires it.',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'enum' => ['activated', 'installed', 'not-installed'],
                    ],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['status', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'custom_fonts' => [
                'type' => 'object',
                'description' => 'Brainstorm Force Custom Fonts plugin state. Required by novamira/spectra-one-add-font-family to install a user-supplied font family. Unused otherwise.',
                'properties' => ['active' => ['type' => 'boolean']],
                'required' => ['active'],
                'additionalProperties' => false,
            ],
            'abilities_api' => [
                'type' => 'object',
                'description' => 'WordPress Abilities API availability. The theme-native `spectra-one/*` abilities require WP 6.9+; the `novamira/spectra-one-*` wrappers only require the base Novamira registration surface.',
                'properties' => [
                    'available' => ['type' => 'boolean'],
                    'wp_version' => ['type' => 'string'],
                ],
                'required' => ['available', 'wp_version'],
                'additionalProperties' => false,
            ],
            'current_user_can_manage' => ['type' => 'boolean'],
        ],
        'required' => [
            'theme',
            'plugin',
            'custom_fonts',
            'abilities_api',
            'current_user_can_manage',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once at the start of any Spectra-One-touching session. If `theme.min_satisfied` is false, stop and surface the gap — every other novamira/spectra-one-* ability returns spectra_one_not_active in that state. If `plugin.min_satisfied` is false, skip only `list-spectra-blocks`; every other ability still works. If `custom_fonts.active` is false, `add-font-family` will refuse the write. Use `abilities_api.available` to decide whether to fall back to a `novamira/execute-php` snippet for the small handful of theme-native `spectra-one/*` abilities Novamira does not re-wrap.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     theme: array{active: bool, is_stylesheet: bool, version: string, min_supported: string, min_satisfied: bool},
 *     plugin: array{status: string, version: string, min_supported: string, min_satisfied: bool},
 *     custom_fonts: array{active: bool},
 *     abilities_api: array{available: bool, wp_version: string},
 *     current_user_can_manage: bool,
 * }
 */
function spectra_one_check_setup(): array
{
    $is_stylesheet = spectra_one_is_active_theme();
    $theme_version = spectra_one_theme_version();
    $plugin_version = spectra_plugin_version();
    $plugin_status = so_resolve_plugin_status($plugin_version);

    $wp_version = get_bloginfo('version');
    $abilities_api_available = function_exists('wp_register_ability');

    return [
        'theme' => [
            'active' => $theme_version !== '' && $is_stylesheet,
            'is_stylesheet' => $is_stylesheet,
            'version' => $theme_version,
            'min_supported' => NOVAMIRA_PRO_SPECTRA_ONE_THEME_MIN_VERSION,
            'min_satisfied' => spectra_one_theme_available() && $is_stylesheet,
        ],
        'plugin' => [
            'status' => $plugin_status,
            'version' => $plugin_version,
            'min_supported' => NOVAMIRA_PRO_SPECTRA_ONE_PLUGIN_MIN_VERSION,
            'min_satisfied' => spectra_plugin_available(),
        ],
        'custom_fonts' => [
            'active' =>
                function_exists('Swt\\Utilities\\is_custom_fonts_plugin') && \Swt\Utilities\is_custom_fonts_plugin(),
        ],
        'abilities_api' => [
            'available' => $abilities_api_available,
            'wp_version' => $wp_version,
        ],
        'current_user_can_manage' => current_user_can('edit_theme_options'),
    ];
}
