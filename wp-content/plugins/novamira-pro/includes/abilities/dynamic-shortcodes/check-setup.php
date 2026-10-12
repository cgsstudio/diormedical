<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicShortcodes;

use DynamicShortcodes\Plugin;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-shortcodes-check-setup', [
    'label' => __('Check Dynamic Shortcodes Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Dynamic Shortcodes environment: whether the plugin is active, the version, whether the minimum version is satisfied, the accepted shortcode types on this site, the detected dependency plugins (acf, woocommerce, jet-engine, metabox, pods, toolset, acpt), a count of saved Power Shortcodes, and `enabled_areas` — which content surfaces DSH actually resolves shortcodes in (post content, blocks, Elementor, Bricks, …; ALL default OFF, so a shortcode renders as literal text where its surface is off). Call BEFORE any other dynamic-shortcodes ability — the other abilities require min_satisfied=true to register.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => ['type' => 'boolean'],
            'version' => ['type' => ['string', 'null']],
            'min_version' => ['type' => 'string'],
            'min_satisfied' => ['type' => 'boolean'],
            'accepted_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'available_plugins' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'power_shortcodes_count' => ['type' => 'integer'],
            'enabled_areas' => [
                'type' => 'object',
                'description' => 'Which content surfaces DSH resolves shortcodes in. ALL default false — a shortcode renders as literal text wherever its surface is off (the usual cause of a shortcode "not working", especially inside builders).',
                'properties' => [
                    'post_title' => ['type' => 'boolean'],
                    'post_content' => ['type' => 'boolean'],
                    'post_excerpt' => ['type' => 'boolean'],
                    'gutenberg_blocks' => ['type' => 'boolean'],
                    'text_widget' => ['type' => 'boolean'],
                    'elementor_dynamic_tag' => ['type' => 'boolean'],
                    'elementor_widgets' => ['type' => 'boolean'],
                    'bricks' => ['type' => 'boolean'],
                    'oxygen' => ['type' => 'boolean'],
                    'breakdance' => ['type' => 'boolean'],
                    'wpbakery' => ['type' => 'boolean'],
                ],
            ],
            'issues' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run this first when working with Dynamic Shortcodes. The other abilities (validate-shortcode, evaluate-shortcode, compose-shortcode, get-context, and the Power Shortcodes CRUD) are only registered when min_satisfied=true. Check `enabled_areas` to see where shortcodes will actually resolve — every area defaults to OFF, so an all-false map is why a shortcode renders as literal text (areas are enabled in the DSH dashboard settings).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *   active: bool,
 *   version: string|null,
 *   min_version: string,
 *   min_satisfied: bool,
 *   accepted_types: list<string>,
 *   available_plugins: list<string>,
 *   power_shortcodes_count: int,
 *   enabled_areas: array<string, bool>,
 *   issues: list<string>
 * }
 */
function dynamic_shortcodes_check_setup(): array
{
    $active = defined('DYNAMIC_SHORTCODES_VERSION') && class_exists('DynamicShortcodes\\Plugin');
    $version = $active ? (string) constant('DYNAMIC_SHORTCODES_VERSION') : null;
    $min_satisfied = dsh_min_runtime_available();
    $enabled_areas = dsh_enabled_areas();

    $accepted_types = [];
    $available_plugins = [];
    $power_count = 0;
    $issues = [];

    if (!$active) {
        $issues[] = 'Dynamic Shortcodes plugin is not active. Install/activate it to use these abilities.';

        return [
            'active' => $active,
            'version' => $version,
            'min_version' => NOVAMIRA_PRO_DYNAMIC_SHORTCODES_MIN_VERSION,
            'min_satisfied' => $min_satisfied,
            'accepted_types' => $accepted_types,
            'available_plugins' => $available_plugins,
            'power_shortcodes_count' => $power_count,
            'enabled_areas' => $enabled_areas,
            'issues' => $issues,
        ];
    }

    if (!$min_satisfied) {
        $issues[] = sprintf(
            'Installed Dynamic Shortcodes %s is older than the required minimum %s. Update the plugin.',
            $version ?? 'unknown',
            NOVAMIRA_PRO_DYNAMIC_SHORTCODES_MIN_VERSION,
        );

        return [
            'active' => $active,
            'version' => $version,
            'min_version' => NOVAMIRA_PRO_DYNAMIC_SHORTCODES_MIN_VERSION,
            'min_satisfied' => $min_satisfied,
            'accepted_types' => $accepted_types,
            'available_plugins' => $available_plugins,
            'power_shortcodes_count' => $power_count,
            'enabled_areas' => $enabled_areas,
            'issues' => $issues,
        ];
    }

    $plugin = Plugin::instance();
    $accepted_types = $plugin->demo_manager->get_types_accepted();

    $available_plugins = dsh_detect_dependency_plugins($plugin->shortcodes_manager);

    $power_count = count($plugin->shortcodes_manager->get_power_shortcodes());

    if (!in_array(true, $enabled_areas, strict: true)) {
        $issues[] = 'No content surface is enabled in the Dynamic Shortcodes settings, so shortcodes render as literal text everywhere. Enable the areas you need (post content, blocks, Elementor, Bricks, …) in Dashboard → Dynamic Shortcodes.';
    }

    return [
        'active' => $active,
        'version' => $version,
        'min_version' => NOVAMIRA_PRO_DYNAMIC_SHORTCODES_MIN_VERSION,
        'min_satisfied' => $min_satisfied,
        'accepted_types' => $accepted_types,
        'available_plugins' => $available_plugins,
        'power_shortcodes_count' => $power_count,
        'enabled_areas' => $enabled_areas,
        'issues' => $issues,
    ];
}
