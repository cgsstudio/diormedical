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
 * Ability: read the current Astra header builder grid (desktop + mobile) and config.
 */

wp_register_ability('novamira/astra-get-header-layout', [
    'label' => __('Get Astra Header Layout', domain: 'novamira-pro'),
    'description' => __(
        'Returns the live Astra Header/Footer Builder desktop and mobile header grids (`header-desktop-items`, `header-mobile-items`), a filtered subset of header config keys (hb-header-*, hba-header-*, hbb-header-*), and the full element and cell allowlists you need before calling `astra-set-header-layout` or `astra-reorder-header-row`. Rows are: above, primary, below, popup. Call astra-check-setup first to confirm the theme is active.',
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
                'description' => 'Raw header-desktop-items grid: row keys (above, primary, below, popup) → cell keys → ordered list of element slugs.',
                'additionalProperties' => true,
            ],
            'mobile' => [
                'type' => 'object',
                'description' => 'Raw header-mobile-items grid (same row structure, 3 columns per row instead of 5).',
                'additionalProperties' => true,
            ],
            'config' => [
                'type' => 'object',
                'description' => 'Subset of hb-/hba-/hbb-header-* keys from astra-settings that are explicitly stored. Keys still at their defaults are omitted.',
                'additionalProperties' => true,
            ],
            'available_elements' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Valid element slugs accepted by the header builder.',
            ],
            'available_cells' => [
                'type' => 'object',
                'properties' => [
                    'desktop' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'mobile' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'description' => 'Valid cell keys for each device variant.',
                'additionalProperties' => false,
            ],
            'desktop_shape_ok' => [
                'type' => 'boolean',
                'description' => 'True when the stored desktop grid has all three main rows (above, primary, below). False on a fresh install before any customisation or after a data anomaly.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_header_layout',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reads the live header builder grid and config. Inspect available_cells and available_elements before calling set-header-layout or reorder-header-row — those abilities refuse unknown cell keys or element slugs. The popup row (key "popup", cell "popup_content") holds the off-canvas menu; leave it alone unless intentionally changing it.',
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
function astra_get_header_layout(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    /** @var mixed $desktop_raw */
    $desktop_raw = astra_get_option('header-desktop-items', []);
    /** @var mixed $mobile_raw */
    $mobile_raw = astra_get_option('header-mobile-items', []);

    $desktop = is_array($desktop_raw) ? $desktop_raw : [];
    $mobile = is_array($mobile_raw) ? $mobile_raw : [];

    // Shape check: a properly initialised grid always has these three rows.
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

    // Read only config keys that have been explicitly saved; defaults-only entries
    // are skipped to keep the response lean.
    /** @var mixed $settings_raw */
    $settings_raw = get_option('astra-settings', []);
    $settings = is_array($settings_raw) ? $settings_raw : [];
    /** @var array<string, mixed> $config */
    $config = [];
    foreach (astra_hfb_header_config_keys() as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        $config[$key] = $settings[$key];
    }

    return [
        'desktop' => astra_object_or_map($desktop),
        'mobile' => astra_object_or_map($mobile),
        'config' => astra_object_or_map($config),
        'available_elements' => astra_hfb_element_slugs(),
        'available_cells' => [
            'desktop' => astra_hfb_cell_keys_desktop(),
            'mobile' => astra_hfb_cell_keys_mobile(),
        ],
        'desktop_shape_ok' => $desktop_shape_ok,
        'mobile_shape_ok' => $mobile_shape_ok,
    ];
}
