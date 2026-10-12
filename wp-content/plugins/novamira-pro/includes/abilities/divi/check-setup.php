<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use ET\Builder\Framework\Breakpoint\Breakpoint;

/**
 * Ability: Check Divi 5 setup and report environment status.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-check-setup', [
    'label' => __('Check Divi Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Divi 5 environment status: builder version, whether the D5 (block-based) builder is enabled, the module API availability, the number of registered modules, the post types the builder is enabled for, and the active responsive breakpoint names (the valid <breakpoint> keys for the responsive attr shape, e.g. desktop, tablet, phone). Run this before building or editing Divi pages to confirm the environment is ready.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'version' => ['type' => 'string'],
            'is_theme' => [
                'type' => 'boolean',
                'description' => 'True when Divi runs as the active theme (vs the Divi Builder plugin).',
            ],
            'd5_enabled' => [
                'type' => 'boolean',
                'description' => 'True when the Divi 5 block-based builder is active. The abilities require this.',
            ],
            'module_api_available' => ['type' => 'boolean'],
            'module_count' => ['type' => 'integer'],
            'builder_post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'breakpoints' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Active responsive breakpoint names — the valid <breakpoint> keys for the <attrName>.<breakpoint>.value shape.',
            ],
            'issues' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Actionable configuration warnings.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run before building Divi pages. Key checks: d5_enabled (required — the abilities only support the Divi 5 block model, not Divi 4 shortcodes), module_api_available, and builder_post_types (must include "page" to edit pages).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function divi_check_setup(): array
{
    $version = \Novamira\Pro\novamira_pro_divi_builder_version();
    if ($version === '') {
        $version = 'unknown';
    }
    $is_theme = defined('ET_BUILDER_THEME') && constant('ET_BUILDER_THEME') === true;
    $d5_enabled = function_exists('et_builder_d5_enabled') && et_builder_d5_enabled();
    $module_api = divi_min_runtime_available();
    $modules = divi_all_modules_metadata();

    /** @var list<string> $post_types */
    $post_types = [];
    if (function_exists('et_builder_get_enabled_builder_post_types')) {
        foreach (et_builder_get_enabled_builder_post_types() as $type) {
            $post_types[] = $type;
        }
    }

    /** @var list<string> $breakpoints */
    $breakpoints = [];
    if (class_exists(Breakpoint::class)) {
        foreach (Breakpoint::get_enabled_breakpoint_names() as $breakpoint_name) {
            $breakpoints[] = $breakpoint_name;
        }
    }

    $issues = [];
    if (!$d5_enabled) {
        $issues[] = 'The Divi 5 block-based builder is not enabled. These abilities only support the Divi 5 module model, not Divi 4 shortcodes.';
    }
    if (!$module_api) {
        $issues[] = 'The Divi 5 module API (ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration) is unavailable. Module discovery and editing will not work.';
    }
    if ($module_api && $modules === []) {
        $issues[] = 'The module registry returned no modules. The Divi install may be incomplete.';
    }
    if ($post_types !== [] && !in_array('page', $post_types, strict: true)) {
        $issues[] = '"page" is not in the builder-enabled post types. Pages cannot be edited with Divi.';
    }

    return [
        'version' => $version,
        'is_theme' => $is_theme,
        'd5_enabled' => $d5_enabled,
        'module_api_available' => $module_api,
        'module_count' => count($modules),
        'builder_post_types' => $post_types,
        'breakpoints' => $breakpoints,
        'issues' => $issues,
    ];
}
