<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-list-ccts', [
    'label' => __('List JetEngine CCT Types', domain: 'novamira-pro'),
    'description' => __(
        'List every JetEngine Custom Content Type (CCT) registered on the site. Each item: id (slug), name, fields_count, records_count (rows in the dedicated table), editable, source (db|php).',
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
                        'fields_count' => ['type' => 'integer'],
                        'records_count' => ['type' => 'integer'],
                        'editable' => ['type' => 'boolean'],
                        'source' => ['type' => 'string', 'enum' => ['db', 'php']],
                    ],
                    'required' => ['id', 'name', 'fields_count', 'records_count', 'editable', 'source'],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['items', 'total'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_list_ccts',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

/**
 * @return array{items: list<array<string, mixed>>, total: int}|WP_Error
 */
function jetengine_list_ccts(): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $module = jetengine_get_cct_module();
    if ($module === null) {
        return ['items' => [], 'total' => 0];
    }

    $raw = $module->manager->get_content_types();
    $instances = is_array($raw) ? $raw : [];

    $items = [];
    foreach ($instances as $factory) {
        $slug = (string) $factory->get_arg('slug');
        if ($slug === '') {
            continue;
        }

        $source = jetengine_cct_source($slug) ?? 'php';

        $items[] = [
            'id' => $slug,
            'name' => (string) $factory->get_arg('name', $slug),
            'fields_count' => count($factory->fields),
            'records_count' => jetengine_cct_records_count($slug),
            'editable' => $source === 'db',
            'source' => $source,
        ];
    }

    return ['items' => $items, 'total' => count($items)];
}
