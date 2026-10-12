<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-check-setup', [
    'label' => __('Check Beaver Builder Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Beaver Builder environment: plugin active/version/minimum status, Beaver Themer presence, supported post types, registered and enabled module counts, template mode, builder-enabled layout count, and current user management permission. Run this before building or editing Beaver Builder layouts.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
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
            'version' => ['type' => 'string'],
            'min_satisfied' => ['type' => 'boolean'],
            'theme_builder_active' => ['type' => 'boolean'],
            'theme_builder_version' => ['type' => 'string'],
            'global_styles_available' => ['type' => 'boolean'],
            'node_templates_available' => ['type' => 'boolean'],
            'lite' => ['type' => 'boolean'],
            'supported_post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'registered_modules_count' => ['type' => 'integer'],
            'enabled_modules_count' => ['type' => 'integer'],
            'enabled_templates' => ['type' => 'string'],
            'builder_enabled_layouts_count' => ['type' => 'integer'],
            'current_user_can_manage' => ['type' => 'boolean'],
            'issues' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run before using Beaver Builder abilities. Key fields: min_satisfied, supported_post_types (must contain the target post type), registered_modules_count, enabled_modules_count, and issues.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function beaver_builder_check_setup(): array
{
    $active = bb_plugin_active();
    $version = bb_version();
    $min_satisfied = $active && version_compare($version, NOVAMIRA_PRO_BEAVER_BUILDER_MIN_VERSION, operator: '>=');
    $modules = $active ? bb_module_catalog(scope: 'all') : [];
    $enabled_modules = $active ? bb_enabled_modules() : [];
    $post_types = $active ? bb_supported_post_types() : [];

    return [
        'active' => $active,
        'version' => $version === '' ? 'unknown' : $version,
        'min_satisfied' => $min_satisfied,
        'theme_builder_active' => bb_theme_builder_active(),
        'theme_builder_version' => bb_theme_builder_version(),
        'global_styles_available' => bb_global_styles_available(),
        'node_templates_available' => bb_node_templates_available(),
        'lite' => defined('FL_BUILDER_LITE') && constant('FL_BUILDER_LITE') === true,
        'supported_post_types' => $post_types,
        'registered_modules_count' => count($modules),
        'enabled_modules_count' => count($enabled_modules),
        'enabled_templates' => $active && class_exists('FLBuilderModel')
            ? \FLBuilderModel::get_enabled_templates()
            : '',
        'builder_enabled_layouts_count' => bb_count_builder_enabled_layouts($post_types),
        'current_user_can_manage' => current_user_can('manage_options'),
        'issues' => bb_check_setup_issues(
            active_status: $active ? 'active' : 'inactive',
            min_status: $min_satisfied ? 'satisfied' : 'below_minimum',
            version: $version,
            modules: $modules,
            post_types: $post_types,
        ),
    ];
}

/**
 * @param list<array<string, mixed>> $modules
 * @param list<string> $post_types
 * @return list<string>
 */
function bb_check_setup_issues(
    string $active_status,
    string $min_status,
    string $version,
    array $modules,
    array $post_types,
): array {
    if ($active_status !== 'active') {
        return ['Beaver Builder is not active. Install and activate the Beaver Builder plugin to use these abilities.'];
    }

    $issues = [];
    if ($min_status !== 'satisfied') {
        $issues[] = sprintf(
            /* translators: 1: installed Beaver Builder version, 2: minimum supported version. */
            __('Beaver Builder %1$s is below the minimum supported version (%2$s).', domain: 'novamira-pro'),
            $version === '' ? 'unknown' : $version,
            NOVAMIRA_PRO_BEAVER_BUILDER_MIN_VERSION,
        );
    }

    if ($modules === []) {
        $issues[] = 'No Beaver Builder modules are registered. The plugin may not have completed its init bootstrap.';
    }

    if ($post_types !== [] && !in_array('page', $post_types, strict: true)) {
        $issues[] = '"page" is not in Beaver Builder supported post types; normal page-building workflows may not be available.';
    }

    return $issues;
}

/**
 * @param list<string> $post_types
 */
function bb_count_builder_enabled_layouts(array $post_types): int
{
    if ($post_types === []) {
        return 0;
    }

    $query = new \WP_Query([
        'fields' => 'ids',
        'post_type' => $post_types,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => '_fl_builder_enabled',
                'value' => '1',
            ],
        ],
    ]);

    return (int) $query->found_posts;
}
