<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-list-taxonomies', [
    'label' => __('List ACF Taxonomies', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight overview of all ACF-managed taxonomies: key, slug, title, active flag, source (db/json/php), and the object_type[] (post types this taxonomy is attached to). Use to discover what is available before fetching a full payload via acf-get-taxonomy.',
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
            'taxonomies' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'source' => ['type' => 'string', 'enum' => ['db', 'json', 'php']],
                        'object_type' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ],
        'required' => ['taxonomies'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_list_taxonomies',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to discover ACF-managed taxonomies. The output is intentionally minimal; use novamira/acf-get-taxonomy with the returned key (or slug) to read the full payload. source=php marks records registered via PHP code, which cannot be edited at runtime.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{include_inactive?: bool} $args
 * @return array{taxonomies: list<array<string, mixed>>}|WP_Error
 */
function acf_list_taxonomies(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $include_inactive = $args['include_inactive'] ?? false;

    $records = acf_get_internal_post_type_posts('acf-taxonomy');
    /** @var list<array<string, mixed>> $out */
    $out = [];
    foreach ($records as $record) {
        /** @var mixed $active */
        $active = $record['active'] ?? true;
        if (!$include_inactive && !filter_var($active, FILTER_VALIDATE_BOOLEAN)) {
            continue;
        }
        $out[] = acf_internal_post_type_to_output($record, kind: 'taxonomy');
    }

    return ['taxonomies' => $out];
}
