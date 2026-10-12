<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-list-modules', [
    'label' => __('List Ocean Extra Modules', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Ocean Extra modules an agent can toggle: Sticky Header, Sticky Footer, Side Panel, Popup Login, Footer Callout, Instagram, Cookie Notice, Portfolio, WooCommerce Popup, Full Screen, White Label, Elementor Widgets. Each entry reports the module slug, label, the sentinel PHP class Ocean Extra registers when the module bootstrap has loaded, and the resolved `enabled` flag (true when the sentinel class exists). REQUIRES Ocean Extra.',
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
            'total' => ['type' => 'integer'],
            'modules' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'sentinel_class' => ['type' => 'string'],
                        'enabled' => [
                            'type' => 'boolean',
                            'description' => 'True when the sentinel class is loaded (module bootstrap has run).',
                        ],
                    ],
                    'required' => ['slug', 'label', 'sentinel_class', 'enabled'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'modules'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_list_modules',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Static catalogue of the modules Ocean Extra ships; the `enabled` flag is resolved at call time via class_exists. To toggle a module use `oceanwp-enable-module` / `oceanwp-disable-module`.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *   total: int,
 *   modules: list<array{slug: string, label: string, sentinel_class: string, enabled: bool}>
 * }|WP_Error
 */
function oceanwp_list_modules(): array|WP_Error
{
    $gate = ow_assert_ocean_extra_available();
    if ($gate !== null) {
        return $gate;
    }
    $catalogue = ow_ocean_extra_modules();
    $modules = [];
    foreach ($catalogue as $slug => $entry) {
        $modules[] = [
            'slug' => $slug,
            'label' => $entry['label'],
            'sentinel_class' => $entry['class'],
            'enabled' => class_exists($entry['class']),
        ];
    }
    return [
        'total' => count($modules),
        'modules' => $modules,
    ];
}
