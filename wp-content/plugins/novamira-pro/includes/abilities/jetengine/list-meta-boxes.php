<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-list-meta-boxes', [
    'label' => __('List JetEngine Meta Boxes', domain: 'novamira-pro'),
    'description' => __(
        'List every JetEngine meta box on the site with a compact discovery shape. Each item includes id, display name, target object_type (post|tax|user|options), fields_count, an editable flag, and a source flag (db|php). PHP-registered meta boxes are read-only at runtime: source=php and editable=false.',
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
                        'object_type' => ['type' => 'string'],
                        'fields_count' => ['type' => 'integer'],
                        'editable' => ['type' => 'boolean'],
                        'source' => ['type' => 'string', 'enum' => ['db', 'php']],
                    ],
                    'required' => ['id', 'name', 'object_type', 'fields_count', 'editable', 'source'],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['items', 'total'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_list_meta_boxes',
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
 * @return array{items: list<array{id: string, name: string, object_type: string, fields_count: int, editable: bool, source: 'db'|'php'}>, total: int}|WP_Error
 */
function jetengine_list_meta_boxes(): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $data = jet_engine()->meta_boxes->data ?? null;
    if ($data === null) {
        return ['items' => [], 'total' => 0];
    }

    $items = [];
    foreach ($data->get_items() as $row) {
        $id = (string) ($row['id'] ?? '');
        if ($id === '') {
            continue;
        }

        $args = is_array($row['args'] ?? null) ? $row['args'] : [];
        $object_type = (string) ($args['object_type'] ?? 'post');
        $meta_fields = is_array($row['meta_fields'] ?? null) ? $row['meta_fields'] : [];

        $items[] = [
            'id' => $id,
            'name' => (string) ($args['name'] ?? $id),
            'object_type' => $object_type,
            'fields_count' => count($meta_fields),
            'editable' => true,
            'source' => 'db',
        ];
    }

    return ['items' => $items, 'total' => count($items)];
}
