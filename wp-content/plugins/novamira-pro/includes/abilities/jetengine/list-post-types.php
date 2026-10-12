<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-list-post-types', [
    'label' => __('List JetEngine Post Types', domain: 'novamira-pro'),
    'description' => __(
        'List the Custom Post Types registered through JetEngine (the ones stored in JetEngine\'s own table and re-registered via register_post_type on each load — NOT every WordPress post type). Each item is a compact discovery shape: slug, label, fields_count, plus an editable flag and a source flag (always db here, since these are JetEngine-managed). Fetch the full definition with novamira/jetengine-get-post-type.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'fields_count' => ['type' => 'integer'],
                        'editable' => ['type' => 'boolean'],
                        'source' => ['type' => 'string', 'enum' => ['db', 'php']],
                    ],
                    'required' => ['slug', 'label', 'fields_count', 'editable', 'source'],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['items', 'total'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_list_post_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{items: list<array{slug: string, label: string, fields_count: int, editable: bool, source: 'db'|'php'}>, total: int}|WP_Error
 */
function jetengine_list_post_types(): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $data = jetengine_cpt_data();
    if ($data === null) {
        return ['items' => [], 'total' => 0];
    }

    $items = [];
    foreach ($data->get_items() as $row) {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '') {
            continue;
        }

        $labels = jetengine_cct_coerce_array($row['labels'] ?? null);
        $meta_fields = jetengine_cct_coerce_array($row['meta_fields'] ?? null);

        $label = (string) ($labels['name'] ?? $labels['singular_name'] ?? $slug);

        $items[] = [
            'slug' => $slug,
            'label' => $label !== '' ? $label : $slug,
            'fields_count' => count($meta_fields),
            'editable' => true,
            'source' => 'db',
        ];
    }

    return ['items' => $items, 'total' => count($items)];
}
