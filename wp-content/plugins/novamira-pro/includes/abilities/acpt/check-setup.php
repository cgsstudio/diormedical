<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-check-setup', [
    'label' => __('Check ACPT Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the ACPT (Advanced Custom Post Types) environment: whether the plugin is active, the version, whether the minimum version is satisfied, whether the Pro license is currently active, and counts of CPTs, taxonomies, meta groups, and option pages defined through ACPT. Call BEFORE any other ACPT ability to verify the runtime is ready.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
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
            'min_satisfied' => ['type' => 'boolean'],
            'license_active' => ['type' => 'boolean'],
            'option_pages_enabled' => ['type' => 'boolean'],
            'option_pages_will_render' => ['type' => 'boolean'],
            'counts' => [
                'type' => 'object',
                'properties' => [
                    'custom_post_types' => ['type' => 'integer'],
                    'taxonomies' => ['type' => 'integer'],
                    'meta_groups' => ['type' => 'integer'],
                    'option_pages' => ['type' => 'integer'],
                ],
                'required' => ['custom_post_types', 'taxonomies', 'meta_groups', 'option_pages'],
                'additionalProperties' => false,
            ],
            'current_user_can_manage' => ['type' => 'boolean'],
        ],
        'required' => [
            'active',
            'version',
            'min_satisfied',
            'license_active',
            'option_pages_enabled',
            'option_pages_will_render',
            'counts',
            'current_user_can_manage',
        ],
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_check_setup_ability',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call first before any other ACPT ability. (Note: if this ability is missing from your tool list, ACPT is not active or below the supported minimum — there is nothing for the agent to do.) CPTs, taxonomies, and meta-group abilities work regardless of license_active. option_pages_will_render is true only when the Pro license is active AND ACPT_ENABLE_PAGES is on; when false, option pages created via abilities will persist in ACPT\'s DB but the menu page will NOT appear in wp-admin until the license is activated. Forms, dynamic blocks, datasets, and WooCommerce integration are out of v1 scope.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     min_satisfied: bool,
 *     license_active: bool,
 *     option_pages_enabled: bool,
 *     option_pages_will_render: bool,
 *     counts: array{custom_post_types: int, taxonomies: int, meta_groups: int, option_pages: int},
 *     current_user_can_manage: bool,
 * }
 */
function acpt_check_setup_ability(): array
{
    $setup = acpt_check_setup();
    return [
        'active' => $setup['active'],
        'version' => $setup['version'],
        'min_satisfied' => $setup['min_satisfied'],
        'license_active' => $setup['license_active'],
        'option_pages_enabled' => $setup['option_pages_enabled'],
        'option_pages_will_render' => $setup['option_pages_will_render'],
        'counts' => $setup['counts'],
        'current_user_can_manage' => current_user_can('manage_options'),
    ];
}
