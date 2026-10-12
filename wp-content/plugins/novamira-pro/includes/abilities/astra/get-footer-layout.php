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
 * Ability: read the current Astra footer builder grid (desktop + mobile) and config.
 */

wp_register_ability('novamira/astra-get-footer-layout', [
    'label' => __('Get Astra Footer Layout', domain: 'novamira-pro'),
    'description' => __(
        'Returns the live Astra Header/Footer Builder desktop and mobile footer grids (`footer-desktop-items`, `footer-mobile-items`), a filtered subset of footer config keys (hba-footer-*, hbb-footer-*, hb-footer-*), and the full element and cell allowlists for use before calling `astra-set-footer-layout`. Rows are: above, primary, below. Footer columns use numeric suffixes (_1 … _5 desktop, _1 … _2 mobile). Elements differ from the header builder — see available_elements.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'desktop' => [
                'type' => 'object',
                'description' => 'Raw footer-desktop-items grid: row keys (above, primary, below) → column keys (above_1…above_5 etc.) → ordered list of element slugs.',
                'additionalProperties' => true,
            ],
            'mobile' => [
                'type' => 'object',
                'description' => 'Raw footer-mobile-items grid (same row structure, _1 and _2 columns only).',
                'additionalProperties' => true,
            ],
            'config' => [
                'type' => 'object',
                'description' => 'Subset of hba-/hbb-/hb-footer-* keys from astra-settings that are explicitly stored.',
                'additionalProperties' => true,
            ],
            'available_elements' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Valid element slugs for the footer builder (copyright, menu, widget-1…4, social-icons-1/2, html-1…3, button-1/2, divider-1…3).',
            ],
            'available_cells' => [
                'type' => 'object',
                'properties' => [
                    'desktop' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'mobile' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'description' => 'Valid column/cell keys for each device variant.',
                'additionalProperties' => false,
            ],
            'desktop_shape_ok' => [
                'type' => 'boolean',
                'description' => 'True when the stored desktop grid has all three rows (above, primary, below).',
            ],
            'mobile_shape_ok' => [
                'type' => 'boolean',
                'description' => 'Same shape check for the mobile grid.',
            ],
        ],
        'required' => [
            'desktop',
            'mobile',
            'config',
            'available_elements',
            'available_cells',
            'desktop_shape_ok',
            'mobile_shape_ok',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_footer_layout',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reads the live footer builder grid and config. Check available_cells and available_elements before calling set-footer-layout — unknown cell keys or element slugs are dropped (or cause a 400 error in strict mode). Note that the footer uses different element slugs than the header (copyright, menu, social-icons-1, etc.).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_get_footer_layout(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    /** @var mixed $desktop_raw */
    $desktop_raw = astra_get_option('footer-desktop-items', []);
    /** @var mixed $mobile_raw */
    $mobile_raw = astra_get_option('footer-mobile-items', []);

    $desktop = is_array($desktop_raw) ? $desktop_raw : [];
    $mobile = is_array($mobile_raw) ? $mobile_raw : [];

    $desktop_shape_ok =
        isset($desktop['above'], $desktop['primary'], $desktop['below'])
        && is_array($desktop['above'])
        && is_array($desktop['primary'])
        && is_array($desktop['below']);

    $mobile_shape_ok =
        isset($mobile['above'], $mobile['primary'], $mobile['below'])
        && is_array($mobile['above'])
        && is_array($mobile['primary'])
        && is_array($mobile['below']);

    /** @var mixed $settings_raw */
    $settings_raw = get_option('astra-settings', []);
    $settings = is_array($settings_raw) ? $settings_raw : [];
    /** @var array<string, mixed> $config */
    $config = [];
    foreach (astra_hfb_footer_config_keys() as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        $config[$key] = $settings[$key];
    }

    return [
        'desktop' => astra_object_or_map($desktop),
        'mobile' => astra_object_or_map($mobile),
        'config' => astra_object_or_map($config),
        'available_elements' => astra_hfb_footer_element_slugs(),
        'available_cells' => [
            'desktop' => astra_hfb_footer_cell_keys_desktop(),
            'mobile' => astra_hfb_footer_cell_keys_mobile(),
        ],
        'desktop_shape_ok' => $desktop_shape_ok,
        'mobile_shape_ok' => $mobile_shape_ok,
    ];
}
