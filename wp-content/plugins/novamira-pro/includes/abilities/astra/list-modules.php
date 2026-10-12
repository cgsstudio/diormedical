<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate every Astra Pro Addon module with its active flag and
 * whether it ships on by default.
 */

wp_register_ability('novamira/astra-list-modules', [
    'label' => __('List Astra Pro Addon Modules', domain: 'novamira-pro'),
    'description' => __(
        'Returns every Astra Pro Addon module known to the integration (20 total): Advanced Hooks, Advanced Headers, Advanced Footer, Blog Pro, Colors & Background, Mobile Header, Header Sections, Site Layouts, Spacing, Sticky Header, Transparent Header, Typography, WooCommerce, EDD, LearnDash, LifterLMS, Nav Menu, Advanced Search, White Label, Scroll To Top. Each row: {slug, label, active, is_default}. Use to check why a module-gated ability is unavailable, or to enumerate what can be toggled via astra-enable-module / astra-disable-module.',
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
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'is_default' => ['type' => 'boolean'],
                    ],
                    'required' => ['slug', 'label', 'active', 'is_default'],
                ],
            ],
            'addon_active' => ['type' => 'boolean'],
        ],
        'required' => ['items', 'addon_active'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_list_modules',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'When addon_active=false, every module row will have active=false — the addon is not installed. Use is_default=true rows to understand which modules are active on a fresh Astra Pro install without any user changes. For a compact boolean map use modules{} from astra-check-setup instead.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     items: list<array{slug: string, label: string, active: bool, is_default: bool}>,
 *     addon_active: bool,
 * }
 */
function astra_list_modules(): array
{
    $labels = astra_module_labels();
    $defaults = astra_default_module_slugs();
    $items = [];

    foreach (astra_module_slugs() as $slug) {
        $items[] = [
            'slug' => $slug,
            'label' => $labels[$slug] ?? $slug,
            'active' => astra_module_active($slug),
            'is_default' => in_array($slug, $defaults, strict: true),
        ];
    }

    return [
        'items' => $items,
        'addon_active' => astra_addon_version() !== '' && class_exists('Astra_Ext_Extension'),
    ];
}
