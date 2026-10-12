<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-list-options-pages', [
    'label' => __('List JetEngine Options Pages', domain: 'novamira-pro'),
    'description' => __(
        'List every JetEngine options page. Each item: id, name, menu_name, fields_count, editable, source (db|php).',
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
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'menu_name' => ['type' => 'string'],
                        'fields_count' => ['type' => 'integer'],
                        'editable' => ['type' => 'boolean'],
                        'source' => ['type' => 'string', 'enum' => ['db', 'php']],
                    ],
                    'required' => ['id', 'name', 'menu_name', 'fields_count', 'editable', 'source'],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['items', 'total'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_list_options_pages',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

/**
 * @return array{items: list<array{id: string, name: string, menu_name: string, fields_count: int, editable: bool, source: 'db'|'php'}>, total: int}|WP_Error
 */
function jetengine_list_options_pages(): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $data = jet_engine()->options_pages->data ?? null;
    if ($data === null) {
        return ['items' => [], 'total' => 0];
    }

    $items = [];
    foreach ($data->get_items() as $row) {
        // The agent-facing identifier is the slug; the numeric `id` column is
        // an internal DB primary key. Always expose `slug` as the public id.
        $id = (string) ($row['slug'] ?? '');
        if ($id === '') {
            continue;
        }

        /** @var mixed $unserialized_labels */
        $unserialized_labels = maybe_unserialize((string) ($row['labels'] ?? ''));
        $labels = is_array($unserialized_labels) ? $unserialized_labels : [];
        $name = (string) ($labels['name'] ?? $id);
        $menu_name = (string) ($labels['menu_name'] ?? $name);

        /** @var mixed $unserialized_fields */
        $unserialized_fields = maybe_unserialize((string) ($row['meta_fields'] ?? ''));
        $fields_count = is_array($unserialized_fields) ? count($unserialized_fields) : 0;

        $items[] = [
            'id' => $id,
            'name' => $name,
            'menu_name' => $menu_name,
            'fields_count' => $fields_count,
            'editable' => true,
            'source' => 'db',
        ];
    }

    return ['items' => $items, 'total' => count($items)];
}
