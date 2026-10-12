<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: List every pod registered on the site.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-list-pods', [
    'label' => __('List Pods', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Pod (CPT, taxonomy, ACT, settings page, extended built-in) registered through Pods. Output is compact: id, name, label, type, storage, object. Use `pods-get-pod` for the full schema (fields, groups, options) of a single pod.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Filter by pod type (post_type, taxonomy, pod, settings, user, comment, media). Omit for all types.',
                'enum' => NOVAMIRA_PODS_TYPES,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'pods' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => ['integer', 'string']],
                        'name' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'storage' => ['type' => 'string'],
                        'object' => ['type' => 'string'],
                    ],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_list_pods',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run this first when modeling content — it tells you what already exists so you do not create a duplicate post type or taxonomy. Filter by `type` when you only need one kind (e.g. `type: "post_type"` to enumerate the CPTs Pods is managing).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_list_pods(array $input = []): array
{
    /** @var mixed $type_filter */
    $type_filter = $input['type'] ?? null;
    $params = [];
    if (is_string($type_filter) && $type_filter !== '') {
        $params['type'] = $type_filter;
    }

    /** @var array<int|string, mixed> $loaded */
    $loaded = pods_api()->load_pods($params);

    $out = [];
    /** @var mixed $pod */
    foreach ($loaded as $pod) {
        if (!is_object($pod) && !is_array($pod)) {
            continue;
        }
        $out[] = pods_pod_summary($pod);
    }

    return [
        'pods' => $out,
        'total' => count($out),
    ];
}
