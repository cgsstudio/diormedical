<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Delete a pod item.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-delete-item', [
    'label' => __('Delete Pods Item', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a single pod item. For post-type and taxonomy pods the underlying WP post / term is also removed (posts go to trash, terms are deleted permanently). Pass `delete_wp_object: false` to keep the WP object and only wipe the Pods-managed data — useful when extending built-ins where the post is shared with other code. ACT items are always permanently removed (custom-table pods have no trash).',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pod' => ['type' => 'string'],
            'id' => ['type' => 'integer'],
            'delete_wp_object' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'When true (default), also remove the underlying WP post / term. When false, only Pods data and relationships are cleaned up.',
            ],
        ],
        'required' => ['pod', 'id'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_delete_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Confirm with the user before calling. `delete_wp_object: false` is the safer choice when extending a built-in post type whose posts may be referenced from other plugins — only the Pods fields are cleared, the WP post itself stays.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_delete_item(array $input): array
{
    $pod_name = pods_input_string($input, key: 'pod');
    if ($pod_name === null) {
        return ['success' => false, 'error' => '`pod` is required.'];
    }
    $id = pods_input_int($input, key: 'id');
    if ($id === null) {
        return ['success' => false, 'error' => '`id` is required.'];
    }

    $params = ['pod' => $pod_name, 'id' => $id];
    $delete_wp_object = pods_input_bool($input, key: 'delete_wp_object') ?? true;

    $result = pods_handle_api_result(static fn(): mixed => pods_api()->delete_pod_item($params, wp: $delete_wp_object));
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error'] ?? 'Unknown error.'];
    }

    return ['success' => true, 'id' => $id];
}
