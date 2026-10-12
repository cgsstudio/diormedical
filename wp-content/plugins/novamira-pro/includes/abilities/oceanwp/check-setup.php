<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-check-setup', [
    'label' => __('Check OceanWP Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns a structured snapshot of the OceanWP integration state — the theme itself (active, version, minimum supported), the Ocean Extra companion plugin (active, version, minimum supported), the optional third-party integrations OceanWP detects at boot (WooCommerce, Elementor, Beaver Builder, EDD, LearnDash, …), and the set of capability flags the agent can use to decide which sub-ability surfaces are available right now. Call this FIRST before invoking any other oceanwp-* ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
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
                    'active' => [
                        'type' => 'boolean',
                        'description' => 'Whether the OceanWP theme is loaded on this request.',
                    ],
                    'version' => [
                        'type' => 'string',
                        'description' => 'Installed OceanWP version, or "" when inactive.',
                    ],
                    'min_supported' => [
                        'type' => 'string',
                        'description' => 'Minimum OceanWP version Novamira Pro requires.',
                    ],
                    'min_satisfied' => [
                        'type' => 'boolean',
                        'description' => 'Whether the installed version meets the minimum.',
                    ],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'ocean_extra' => [
                'type' => 'object',
                'properties' => [
                    'active' => [
                        'type' => 'boolean',
                        'description' => 'Whether the Ocean Extra companion plugin is active.',
                    ],
                    'version' => [
                        'type' => 'string',
                        'description' => 'Installed Ocean Extra version, or "" when inactive.',
                    ],
                    'min_supported' => [
                        'type' => 'string',
                        'description' => 'Minimum Ocean Extra version Novamira Pro requires.',
                    ],
                    'min_satisfied' => [
                        'type' => 'boolean',
                        'description' => 'Whether the installed version meets the minimum.',
                    ],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'integrations' => [
                'type' => 'object',
                'description' => 'OceanWP-detected optional integrations (mirrors the constants in oceanwp/functions.php).',
                'properties' => [
                    'sticky_header' => ['type' => 'boolean'],
                    'sticky_footer' => ['type' => 'boolean'],
                    'ecommerce' => ['type' => 'boolean'],
                    'elementor' => ['type' => 'boolean'],
                    'beaver_builder' => ['type' => 'boolean'],
                    'woocommerce' => ['type' => 'boolean'],
                    'edd' => ['type' => 'boolean'],
                    'lifterlms' => ['type' => 'boolean'],
                    'auto_load_next_post' => ['type' => 'boolean'],
                    'learndash' => ['type' => 'boolean'],
                ],
                'required' => [
                    'sticky_header',
                    'sticky_footer',
                    'ecommerce',
                    'elementor',
                    'beaver_builder',
                    'woocommerce',
                    'edd',
                    'lifterlms',
                    'auto_load_next_post',
                    'learndash',
                ],
                'additionalProperties' => false,
            ],
            'capabilities' => [
                'type' => 'object',
                'description' => 'Resolved per-surface availability — use this to decide which sub-ability clusters to call.',
                'properties' => [
                    'customizer_writes' => [
                        'type' => 'boolean',
                        'description' => 'Whether Sprint-2 Customizer write abilities can run (theme active + min satisfied).',
                    ],
                    'page_overrides' => [
                        'type' => 'boolean',
                        'description' => 'Whether Sprint-3 per-page override abilities can run.',
                    ],
                    'woo_features' => [
                        'type' => 'boolean',
                        'description' => 'Whether WooCommerce-feature toggles can run (requires WC active in the integrations block above).',
                    ],
                    'library' => [
                        'type' => 'boolean',
                        'description' => 'Whether the Sprint-4 Library template abilities can run (requires Ocean Extra at the supported version).',
                    ],
                    'modules' => [
                        'type' => 'boolean',
                        'description' => 'Whether the Sprint-4 Modules toggle abilities can run (requires Ocean Extra at the supported version).',
                    ],
                ],
                'required' => ['customizer_writes', 'page_overrides', 'woo_features', 'library', 'modules'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['theme', 'ocean_extra', 'integrations', 'capabilities'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always call this first. Read `theme.min_satisfied` before any other ability — when false, none of the other oceanwp-* abilities will accept input. Read `ocean_extra.min_satisfied` before any Sprint-4 ability (Library / Modules). Read `integrations.woocommerce` before calling `oceanwp-set-woo-features`.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *   theme: array{active: bool, version: string, min_supported: string, min_satisfied: bool},
 *   ocean_extra: array{active: bool, version: string, min_supported: string, min_satisfied: bool},
 *   integrations: array{
 *     sticky_header: bool, sticky_footer: bool, ecommerce: bool, elementor: bool,
 *     beaver_builder: bool, woocommerce: bool, edd: bool, lifterlms: bool,
 *     auto_load_next_post: bool, learndash: bool
 *   },
 *   capabilities: array{customizer_writes: bool, page_overrides: bool, woo_features: bool, library: bool, modules: bool}
 * }
 */
function oceanwp_check_setup(): array
{
    $theme = ow_detect_setup();
    $ocean_extra = ow_detect_ocean_extra();
    $integrations = ow_detect_integrations();
    $theme_ready = $theme['active'] && $theme['min_satisfied'];
    $oe_ready = $ocean_extra['active'] && $ocean_extra['min_satisfied'];
    return [
        'theme' => $theme,
        'ocean_extra' => $ocean_extra,
        'integrations' => $integrations,
        'capabilities' => [
            'customizer_writes' => $theme_ready,
            'page_overrides' => $theme_ready,
            'woo_features' => $theme_ready && $integrations['woocommerce'],
            'library' => $theme_ready && $oe_ready,
            'modules' => $theme_ready && $oe_ready,
        ],
    ];
}
