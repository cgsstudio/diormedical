<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

/**
 * Ability: List the Divi 5 query-loop query types and ordering options.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-list-loop-query-types', [
    'label' => __('List Divi Loop Query Types', domain: 'novamira-pro'),
    'description' => __(
        'Lists the query types a Divi 5 query loop supports, what each one\'s "sub_types" mean, and the ordering options. Use this before novamira/divi-enable-loop to pick a valid query_type and order_by. order_by is passed straight to WP_Query, so any WP_Query orderby works — the list shows the common ones.',
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
            'query_types' => ['type' => 'array', 'items' => ['type' => 'object']],
            'order_by' => ['type' => 'array', 'items' => ['type' => 'string']],
            'order' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_loop_query_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reference for novamira/divi-enable-loop: query_type drives what "sub_types" hold (post-type slugs, taxonomy names, role slugs, or menu ids). order_by is forwarded to WP_Query.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function divi_list_loop_query_types(): array
{
    return [
        'query_types' => [
            [
                'type' => 'post_types',
                'sub_types' => 'post-type slugs (e.g. "post", "page", "project"); empty = any',
                'description' => 'Loop over posts of one or more post types.',
            ],
            [
                'type' => 'post_taxonomies',
                'sub_types' => 'taxonomy names (e.g. "category")',
                'description' => 'Loop over the terms of the queried post\'s taxonomies.',
            ],
            ['type' => 'terms', 'sub_types' => 'taxonomy names', 'description' => 'Loop over taxonomy terms.'],
            ['type' => 'users', 'sub_types' => '(none)', 'description' => 'Loop over users.'],
            [
                'type' => 'user_roles',
                'sub_types' => 'role slugs (e.g. "subscriber")',
                'description' => 'Loop over users of the given roles.',
            ],
            ['type' => 'menus', 'sub_types' => 'menu ids', 'description' => 'Loop over the items of a menu.'],
        ],
        'order_by' => ['date', 'title', 'name', 'modified', 'menu_order', 'comment_count', 'rand', 'ID', 'author'],
        'order' => ['ASC', 'DESC'],
    ];
}
