<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Create a brand-new Pods Settings Page.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-create-settings', [
    'label' => __('Create Pods Settings Page', domain: 'novamira-pro'),
    'description' => __(
        'Creates a brand-new Pods Settings Page — a custom admin screen whose fields are stored in `wp_options` and queryable via `pods_v` / `pods_field`. Use this for site-wide configuration (e.g. social URLs, footer text, contact info) that does not belong on a single post.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Slug / internal name. Used as the option key prefix (`<name>_<field>`).',
            ],
            'label' => ['type' => 'string', 'description' => 'Title shown at the top of the admin page.'],
            'menu_name' => [
                'type' => 'string',
                'description' => 'Label shown in the WP admin menu. Defaults to label.',
            ],
            'menu_location' => [
                'type' => 'string',
                'enum' => ['default', 'settings', 'appearances', 'objects', 'top', 'submenu'],
                'default' => 'settings',
                'description' => 'Where in the WP admin menu the page appears: `settings` (under Settings — default), `appearances` (under Appearance — note the trailing `s`), `objects` (under Pods Admin), `top` (top-level menu), `submenu` (requires `menu_location_custom` in `options` to point to a parent slug), `default` (Pods\' own routing).',
            ],
            'description' => ['type' => 'string'],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Free-form pod options (e.g. `menu_position`, `menu_icon`, `menu_location_custom`).',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_create_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Settings pods do not have items in the usual sense — read field values with `pods_field("<settings_pod_name>", "<field_name>")` from PHP, or via the Pods dynamic-data tag in Elementor / Bricks. Adding fields to the page is done with `pods-create-field pod=<name>` like any other pod.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_create_settings(array $input): array
{
    $name = pods_input_string($input, key: 'name');
    if ($name === null) {
        return ['success' => false, 'error' => '`name` is required.'];
    }

    $params = pods_build_settings_params($name, $input);
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_pod($params));
    return pods_format_save_response($result, $name);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_settings_params(string $name, array $input): array
{
    $label = pods_input_string($input, key: 'label') ?? ucwords(str_replace(search: '_', replace: ' ', subject: $name));
    $menu_name = pods_input_string($input, key: 'menu_name') ?? $label;

    $params = [
        'name' => $name,
        'type' => 'settings',
        'storage' => 'option',
        'label' => $label,
        'menu_name' => $menu_name,
        'menu_location' => pods_input_string($input, key: 'menu_location') ?? 'settings',
        'description' => pods_input_string($input, key: 'description') ?? '',
    ];
    return array_merge($params, pods_input_options($input));
}
