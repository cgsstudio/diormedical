<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Create a new item in a pod.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-create-item', [
    'label' => __('Create Pods Item', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new item in a pod. `data` is a flat `field_name => value` map. For post-type pods the standard WP fields (`post_title`, `post_status`, `post_content`) work alongside Pods custom fields. For pick (relationship) fields, pass an item ID (single) or an array of IDs (multi).',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pod' => ['type' => 'string'],
            'data' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Field values keyed by field name. Pick fields accept an integer ID, an array of IDs, or a slug; file fields accept attachment IDs.',
            ],
        ],
        'required' => ['pod', 'data'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_create_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'For post-type pods you typically pass at least `post_title` and `post_status` (default `draft`). For settings pods, items do not exist — set field values directly via `pods-edit-item` against the settings pod (id is implicit).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_create_item(array $input): array
{
    $pod_name = pods_input_string($input, key: 'pod');
    if ($pod_name === null) {
        return ['success' => false, 'error' => '`pod` is required.'];
    }

    /** @var mixed $data */
    $data = $input['data'] ?? null;
    if (!is_array($data)) {
        return ['success' => false, 'error' => '`data` must be an object of field values.'];
    }

    $params = ['pod' => $pod_name, 'data' => $data, 'is_new_item' => true];
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_pod_item($params));
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error'] ?? 'Unknown error.'];
    }
    return ['success' => true, 'id' => $result['id'] ?? 0];
}
