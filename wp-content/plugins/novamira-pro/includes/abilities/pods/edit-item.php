<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Update an existing pod item (or write field values to a settings pod).
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-edit-item', [
    'label' => __('Edit Pods Item', domain: 'novamira-pro'),
    'description' => __(
        'Updates field values on an existing item. Pass `id` for content pods (post_type, taxonomy, ACT). For settings pods the `id` is irrelevant — pass any integer; values are merged into the option storage. Only the keys present in `data` are touched; unset fields keep their existing value.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pod' => ['type' => 'string'],
            'id' => ['type' => 'integer'],
            'data' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['pod', 'id', 'data'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_edit_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Edits are partial — pass only the changed fields. To clear a field, pass it explicitly with `null` or `""` depending on its type.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_edit_item(array $input): array
{
    $pod_name = pods_input_string($input, key: 'pod');
    if ($pod_name === null) {
        return ['success' => false, 'error' => '`pod` is required.'];
    }
    $id = pods_input_int($input, key: 'id');
    if ($id === null) {
        return ['success' => false, 'error' => '`id` is required.'];
    }

    /** @var mixed $data */
    $data = $input['data'] ?? null;
    if (!is_array($data)) {
        return ['success' => false, 'error' => '`data` must be an object of field values.'];
    }

    $params = ['pod' => $pod_name, 'id' => $id, 'data' => $data];
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_pod_item($params));
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error'] ?? 'Unknown error.'];
    }
    return ['success' => true, 'id' => $result['id'] ?? $id];
}
