<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-list-options-pages', [
    'label' => __('List ACF Options Pages', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight overview of all ACF-managed options pages: key, slug (menu_slug), title (page_title), active flag, source (db/json/php), parent_slug, capability, menu_title. Use to discover what is available before fetching a full payload via acf-get-options-page.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'include_inactive' => [
                'type' => 'boolean',
                'description' => 'When true, also returns records with active=false. Default false.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'options_pages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'source' => ['type' => 'string', 'enum' => ['db', 'json', 'php']],
                        'menu_title' => ['type' => 'string'],
                        'parent_slug' => ['type' => 'string'],
                        'capability' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'required' => ['options_pages'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_list_options_pages',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to discover ACF-managed options pages. The output is intentionally minimal; use novamira/acf-get-options-page with the returned key (or slug) to read the full payload. source=php marks records registered via PHP code (e.g. acf_add_options_page() in functions.php), which cannot be edited at runtime. parent_slug is empty for top-level menu items and a parent menu slug (e.g. "options-general.php") for sub-pages.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{include_inactive?: bool} $args
 * @return array{options_pages: list<array<string, mixed>>}|WP_Error
 */
function acf_list_options_pages(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard(kind: 'options-page');
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $include_inactive = $args['include_inactive'] ?? false;

    $records = acf_get_internal_post_type_posts('acf-ui-options-page');
    /** @var list<array<string, mixed>> $out */
    $out = [];
    foreach ($records as $record) {
        /** @var mixed $active */
        $active = $record['active'] ?? true;
        if (!$include_inactive && !filter_var($active, FILTER_VALIDATE_BOOLEAN)) {
            continue;
        }
        $out[] = acf_internal_post_type_to_output($record, kind: 'options-page');
    }

    return ['options_pages' => $out];
}
