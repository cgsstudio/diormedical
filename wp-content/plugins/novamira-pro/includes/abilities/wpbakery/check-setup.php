<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Check WPBakery setup and report environment status.
 */

wp_register_ability('novamira/wpbakery-check-setup', [
    'label' => __('Check WPBakery Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the WPBakery Page Builder environment: version and minimum-version status, registered element count and categories, Grid Builder availability, post types where WPBakery is enabled, and the current user\'s management permission. Call BEFORE any other WPBakery ability so the agent can plan around what is and is not available on this site.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
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
            'min_satisfied' => ['type' => 'boolean'],
            'elements_count' => ['type' => 'integer'],
            'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
            'grid_builder_active' => ['type' => 'boolean'],
            'supported_post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'current_user_can_manage' => ['type' => 'boolean'],
            'issues' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'List of detected configuration issues.',
            ],
        ],
        'required' => [
            'version',
            'min_satisfied',
            'elements_count',
            'categories',
            'grid_builder_active',
            'supported_post_types',
            'current_user_can_manage',
            'issues',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run this before any other WPBakery ability. Each flag gates a specific class of operation: min_satisfied=false means the install is below the supported floor and the write abilities will refuse; grid_builder_active=false means vc_basic_grid / vc_masonry_grid loop widgets are not registered and the page cannot do CMS loops via WPBakery; supported_post_types is the list of post types where WPBakery can edit — creating a WPBakery page on a type outside this list saves the shortcodes but the "Edit with WPBakery" link does not appear and rendering is theme-dependent; current_user_can_manage=false signals the agent is acting under a non-admin user with reduced permission. Read the issues array for human-formatted summaries of the same signals.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     version: string,
 *     min_satisfied: bool,
 *     elements_count: int,
 *     categories: list<string>,
 *     grid_builder_active: bool,
 *     supported_post_types: list<string>,
 *     current_user_can_manage: bool,
 *     issues: list<string>,
 * }
 */
function wpbakery_check_setup(): array
{
    $all = \WPBMap::getAllShortCodes();
    $categories = wpb_element_categories();
    $version = wpb_version();

    $min_satisfied = $version !== '' && version_compare($version, NOVAMIRA_PRO_WPBAKERY_MIN_VERSION, operator: '>=');

    // Grid Builder bundles vc_basic_grid / vc_masonry_grid / vc_grid into the
    // WPBMap registry. Use vc_basic_grid as the canonical signal — it is the
    // simplest of the loop widgets and the one the build-page skill recommends.
    $grid_builder_active = array_key_exists('vc_basic_grid', $all);

    $supported_post_types = wpb_supported_post_types();

    $issues = [];

    if ($all === []) {
        $issues[] = 'No WPBakery elements are registered. The plugin may not be fully initialised.';
    }

    if (!$min_satisfied) {
        $issues[] = sprintf(
            /* translators: 1: installed WPBakery version, 2: minimum supported version. */
            __(
                'WPBakery %1$s is below the minimum supported version (%2$s). Write abilities will refuse to run.',
                domain: 'novamira-pro',
            ),
            $version === '' ? 'unknown' : $version,
            NOVAMIRA_PRO_WPBAKERY_MIN_VERSION,
        );
    }

    if (!$grid_builder_active) {
        $issues[] = 'Grid Builder is not active on this site. vc_basic_grid / vc_masonry_grid loop widgets are unavailable; CMS loops are not possible through WPBakery on this install.';
    }

    return [
        'version' => $version,
        'min_satisfied' => $min_satisfied,
        'elements_count' => count($all),
        'categories' => $categories,
        'grid_builder_active' => $grid_builder_active,
        'supported_post_types' => $supported_post_types,
        'current_user_can_manage' => current_user_can('manage_options'),
        'issues' => $issues,
    ];
}

/**
 * Resolves the post types where WPBakery is enabled as the editor.
 *
 * `wpb_js_post_types_to_use` is the option WPBakery stores in the
 * Role Manager. When the option is missing or false, WPBakery falls
 * back to its default set (`post` and `page`).
 *
 * @return list<string>
 */
function wpb_supported_post_types(): array
{
    /** @var mixed $stored */
    $stored = get_option('wpb_js_post_types_to_use');
    if (is_array($stored) && $stored !== []) {
        return array_values(array_map('strval', $stored));
    }
    return ['post', 'page'];
}
