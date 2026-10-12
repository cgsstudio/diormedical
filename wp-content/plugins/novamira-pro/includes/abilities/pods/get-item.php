<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Read a single pod item with all its field values.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-get-item', [
    'label' => __('Get Pods Item', domain: 'novamira-pro'),
    'description' => __(
        'Returns a single item from a pod with all its field values resolved (relationships expanded into IDs, files into attachment IDs, etc.). Use `fields` to project specific keys when the full payload is too large.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pod' => ['type' => 'string'],
            'id' => ['type' => 'integer', 'description' => 'Item ID. For post-type pods this is the WP post ID.'],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional projection — only return these field names.',
            ],
        ],
        'required' => ['pod', 'id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'item' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'fields' => ['type' => 'object'],
                ],
            ],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_get_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pick fields are returned as related-item IDs (or arrays of IDs for multi pick). Use `pods-get-item pod=<related_pod> id=<id>` to follow a relationship.',
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
function pods_get_item(array $input): array
{
    $pod_name = pods_input_string($input, key: 'pod');
    if ($pod_name === null) {
        return ['error' => '`pod` is required.'];
    }
    $id = pods_input_int($input, key: 'id');
    if ($id === null) {
        return ['error' => '`id` is required.'];
    }

    // Validate the pod exists first — `export_pod_item()` blows up with a
    // PHP fatal on `array_merge(null, ...)` when the pod is unknown.
    /** @var object|false|null $pod_def */
    $pod_def = pods_api()->load_pod(['name' => $pod_name]);
    if ($pod_def === false || $pod_def === null) {
        return ['error' => sprintf('Pod "%s" not found.', $pod_name)];
    }

    // Bust the Pod_Manager instance cache. Without this, a `pods-edit-item`
    // call earlier in the same request leaves the cached row buffer holding
    // pre-edit values, and the subsequent `export_pod_item()` returns stale
    // data. `cache_flush_pods` calls `Pod_Manager::flush($pod)` internally —
    // re-instantiation on the next call gives a fresh row read.
    pods_api()->cache_flush_pods($pod_def, false, false);

    $fields_filter = pods_resolve_fields_filter($input);
    $params = ['pod' => $pod_name, 'id' => $id];
    if ($fields_filter !== null) {
        $params['fields'] = $fields_filter;
    }

    /** @var mixed $exported */
    $exported = pods_api()->export_pod_item($params);
    if ($exported === false || !is_array($exported) || $exported === []) {
        return ['error' => sprintf('Item %d not found in pod "%s".', $id, $pod_name)];
    }
    // export_pod_item returns a "ghost" array of nulls when the item id is
    // unknown but the pod exists — identify by id=0 in the payload.
    /** @var mixed $ghost_id */
    $ghost_id = $exported['id'] ?? null;
    if ($ghost_id === 0 || $ghost_id === '0') {
        return ['error' => sprintf('Item %d not found in pod "%s".', $id, $pod_name)];
    }

    return [
        'item' => [
            'id' => $id,
            'fields' => (object) $exported,
        ],
    ];
}
