<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-check-setup', [
    'label' => __('Check GeneratePress Setup', domain: 'novamira-pro'),
    'description' => __(
        'Diagnostic snapshot of the GeneratePress install: theme active flag + version + minimum supported, GP Premium presence + version, the activation flag for every Premium module the integration cares about (elements, site_library, font_library, backgrounds, blog, colors, typography, spacing, copyright, disable_elements, menu_plus, secondary_nav, woocommerce), and the GenerateBlocks (free + Pro) presence + version. Call once at the start of any session that will read or write theme settings, Elements, or build page content. Lean by design — does NOT echo the settings or hook list back; use code-snippets-style drill-down (`generatepress-get-settings`, `generatepress-list-hooks`).',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
            ],
            'gp_premium' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                    'modules' => [
                        'type' => 'object',
                        'description' => 'Map of module slug -> active boolean. Use to gate calls into the Elements / Site Library / Font Library abilities.',
                    ],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied', 'modules'],
            ],
            'generateblocks' => [
                'type' => 'object',
                'description' => 'Presence of the GenerateBlocks block library — the canonical companion plugin used to build visual content on a GeneratePress site. When `active=false`, GP can still set typography / colors / header-footer chrome, but the page body is limited to core Gutenberg blocks; warn the user and propose installing GenerateBlocks free before building rich layouts.',
                'properties' => [
                    'active' => ['type' => 'boolean', 'description' => 'GenerateBlocks (free) plugin active.'],
                    'version' => ['type' => 'string'],
                    'pro_active' => [
                        'type' => 'boolean',
                        'description' => 'GenerateBlocks Pro plugin active. Adds Tabs / Accordion / dynamic-data binding / Local Hero on top of the free blocks.',
                    ],
                    'pro_version' => ['type' => 'string'],
                ],
                'required' => ['active', 'version', 'pro_active', 'pro_version'],
            ],
        ],
        'required' => ['theme', 'gp_premium', 'generateblocks'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this once at the start of a GeneratePress session. If `theme.active=false` or `theme.min_satisfied=false`, stop and surface the gap to the user — none of the other GeneratePress abilities will work. Use `gp_premium.modules.<name>=true` to gate calls into the Elements / Site Library / Font Library ability groups. When `generateblocks.active=false`, the visual composition layer is missing — warn the user before promising a rich page body, and either propose installing GenerateBlocks free from wp.org or constrain the build to header/footer chrome + typography settings. The response is intentionally compact — call get-settings / list-hooks / list-elements when you need actual content.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function generatepress_check_setup(): array
{
    $theme_version = gp_theme_version();
    $premium_version = gp_premium_version();
    $gb_version = gp_generateblocks_version();
    $gb_pro_version = gp_generateblocks_pro_version();
    return [
        'theme' => [
            'active' => $theme_version !== '' && function_exists('generate_get_option'),
            'version' => $theme_version,
            'min_supported' => NOVAMIRA_PRO_GP_THEME_MIN_VERSION,
            'min_satisfied' => gp_theme_available(),
        ],
        'gp_premium' => [
            'active' => $premium_version !== '' && function_exists('generatepress_is_module_active'),
            'version' => $premium_version,
            'min_supported' => NOVAMIRA_PRO_GP_PREMIUM_MIN_VERSION,
            'min_satisfied' => gp_premium_available(),
            'modules' => gp_module_status_map(),
        ],
        'generateblocks' => [
            'active' => $gb_version !== '',
            'version' => $gb_version,
            'pro_active' => $gb_pro_version !== '',
            'pro_version' => $gb_pro_version,
        ],
    ];
}

/**
 * Returns the active flag for every module the GeneratePress integration
 * cares about. Stable across the 2.0–2.5.x line of GP Premium we support.
 *
 * @return array<string, bool>
 */
function gp_module_status_map(): array
{
    return [
        'elements' => gp_elements_active(),
        'site_library' => gp_site_library_active(),
        'font_library' => gp_font_library_active(),
        'backgrounds' => gp_module_active(option: 'generate_package_backgrounds', constant: 'GENERATE_BACKGROUNDS'),
        'blog' => gp_module_active(option: 'generate_package_blog', constant: 'GENERATE_BLOG'),
        'colors' => gp_module_active(option: 'generate_package_colors', constant: 'GENERATE_COLORS'),
        'typography' => gp_module_active(option: 'generate_package_typography', constant: 'GENERATE_TYPOGRAPHY'),
        'spacing' => gp_module_active(option: 'generate_package_spacing', constant: 'GENERATE_SPACING'),
        'copyright' => gp_module_active(option: 'generate_package_copyright', constant: 'GENERATE_COPYRIGHT'),
        'disable_elements' => gp_module_active(
            option: 'generate_package_disable_elements',
            constant: 'GENERATE_DISABLE_ELEMENTS',
        ),
        'menu_plus' => gp_module_active(option: 'generate_package_menu_plus', constant: 'GENERATE_MENU_PLUS'),
        'secondary_nav' => gp_module_active(
            option: 'generate_package_secondary_nav',
            constant: 'GENERATE_SECONDARY_NAV',
        ),
        'woocommerce' => gp_module_active(option: 'generate_package_woocommerce', constant: 'GENERATE_WOOCOMMERCE'),
    ];
}
