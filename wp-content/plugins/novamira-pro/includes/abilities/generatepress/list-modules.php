<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-list-modules', [
    'label' => __('List GP Premium Modules', domain: 'novamira-pro'),
    'description' => __(
        'Reports every GP Premium module known to the integration (Backgrounds, Blog, Colors, Copyright, Disable Elements, Elements, Hooks, Menu Plus, Page Header, Secondary Nav, Sections, Spacing, Typography, WooCommerce, Site Library, Font Library — 16 total in GP Premium 2.5.x). Each row contains `{slug, label, active, source}` — `source` explains WHY the module is active: `option` (the `generate_package_<name>` option equals "activated"), `constant` (the `GENERATE_<NAME>` constant is defined in wp-config or mu-plugin), or `none` (inactive). Use this when generatepress-check-setup\'s `gp_premium.modules` map is too terse — list-modules gives the per-module signal source.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'active_only' => [
                'type' => 'boolean',
                'description' => 'When true, return only the modules currently active. Default false (return the full catalogue with the per-module active flag).',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'modules' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
            'active' => ['type' => 'integer'],
        ],
        'required' => ['modules', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_list_modules',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this when troubleshooting why a feature-gated ability is missing (e.g. set-element-conditions absent). The `source` field identifies whether the module is activated via the admin UI (`option`), force-enabled in wp-config (`constant`), or off (`none`).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{active_only?: bool} $input
 * @return array<string, mixed>
 */
function generatepress_list_modules(array $input): array
{
    $active_only = gp_coerce_bool($input['active_only'] ?? false);
    $catalogue = gp_modules_catalogue();
    $rows = [];
    $active_count = 0;
    /** @var array{option: string, constant: string, label: string} $row */
    foreach ($catalogue as $slug => $row) {
        $by_constant = defined($row['constant']);
        $by_option = false;
        if (!$by_constant) {
            /** @var mixed $option_value */
            $option_value = get_option(option: $row['option'], default_value: false);
            $by_option = $option_value === 'activated';
        }
        $active = $by_constant || $by_option;
        if ($active) {
            $active_count++;
        }
        if ($active_only && !$active) {
            continue;
        }
        $source = match (true) {
            $by_constant => 'constant',
            $by_option => 'option',
            default => 'none',
        };
        $rows[] = [
            'slug' => $slug,
            'label' => $row['label'],
            'option' => $row['option'],
            'constant' => $row['constant'],
            'active' => $active,
            'source' => $source,
        ];
    }
    return [
        'modules' => $rows,
        'total' => count($rows),
        'active' => $active_count,
    ];
}
