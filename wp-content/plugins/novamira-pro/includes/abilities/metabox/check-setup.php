<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-check-setup', [
    'label' => __('Check Meta Box Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Meta Box environment: whether the core is active, the version, whether the minimum version is satisfied, which extensions are loaded (builder, cpt, settings page, relationships, term meta, comment meta, user meta, group field, custom table), and counts of field groups split by source (php-registered vs db-stored). Call BEFORE any other Meta Box ability — each *_active flag gates a specific ability group.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
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
            'builder_active' => ['type' => 'boolean'],
            'cpt_active' => ['type' => 'boolean'],
            'settings_page_active' => ['type' => 'boolean'],
            'relationships_active' => ['type' => 'boolean'],
            'term_meta_active' => ['type' => 'boolean'],
            'comment_meta_active' => ['type' => 'boolean'],
            'user_meta_active' => ['type' => 'boolean'],
            'group_active' => ['type' => 'boolean'],
            'custom_table_active' => ['type' => 'boolean'],
            'field_group_counts' => [
                'type' => 'object',
                'properties' => [
                    'php' => ['type' => 'integer'],
                    'db' => ['type' => 'integer'],
                ],
                'required' => ['php', 'db'],
                'additionalProperties' => false,
            ],
            'current_user_can_manage' => ['type' => 'boolean'],
        ],
        'required' => [
            'active',
            'version',
            'min_satisfied',
            'builder_active',
            'cpt_active',
            'settings_page_active',
            'relationships_active',
            'term_meta_active',
            'comment_meta_active',
            'user_meta_active',
            'group_active',
            'custom_table_active',
            'field_group_counts',
            'current_user_can_manage',
        ],
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first before any other Meta Box ability. Each *_active flag gates a specific ability group: builder_active → write-side field-group abilities (create/edit/delete); cpt_active → manage-post-types + manage-taxonomies (single extension covers both); settings_page_active → manage-settings-pages; relationships_active → manage-relationships + connect/disconnect/list-connected; term_meta_active → field groups with `taxonomies[]` binding + read-/write-values target=term; comment_meta_active → field groups with comment binding + read-/write-values target=comment; user_meta_active → read-/write-values target=user + user-bound field groups; group_active → field definitions with `type: group`; custom_table_active → SIGNAL ONLY, read-/write-values do not currently support custom-table storage (the value will silently miss the data when a field group is flagged for custom-table storage — warn the user explicitly when you see this flag combined with custom-table-bound groups). field_group_counts.php > 0 means some field groups are defined in PHP files and cannot be edited via the API.',
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
 *     builder_active: bool,
 *     cpt_active: bool,
 *     settings_page_active: bool,
 *     relationships_active: bool,
 *     term_meta_active: bool,
 *     comment_meta_active: bool,
 *     user_meta_active: bool,
 *     group_active: bool,
 *     custom_table_active: bool,
 *     field_group_counts: array{php: int, db: int},
 *     current_user_can_manage: bool,
 * }
 */
function metabox_check_setup(): array
{
    $setup = mb_check_setup();
    return [
        'active' => $setup['active'],
        'version' => $setup['version'],
        'min_satisfied' => $setup['min_satisfied'],
        'builder_active' => $setup['builder_active'],
        'cpt_active' => $setup['cpt_active'],
        'settings_page_active' => $setup['settings_page_active'],
        'relationships_active' => $setup['relationships_active'],
        'term_meta_active' => $setup['term_meta_active'],
        'comment_meta_active' => $setup['comment_meta_active'],
        'user_meta_active' => $setup['user_meta_active'],
        'group_active' => $setup['group_active'],
        'custom_table_active' => $setup['custom_table_active'],
        'field_group_counts' => $setup['field_group_counts'],
        'current_user_can_manage' => current_user_can('manage_options'),
    ];
}
