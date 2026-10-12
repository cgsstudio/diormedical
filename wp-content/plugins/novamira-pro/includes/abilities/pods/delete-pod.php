<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Delete a Pod definition.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-delete-pod', [
    'label' => __('Delete Pod', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Pod definition. By default the underlying WP objects (posts, terms, ACT rows) are preserved — pass `delete_items: true` to wipe them as well. Irreversible.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'name' => ['type' => 'string'],
            'id' => ['type' => 'integer'],
            'delete_items' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Also delete every item belonging to this pod (posts, terms, ACT rows, settings values).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'name' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_delete_pod',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Confirm with the user before calling this — `delete_items: true` is destructive and cannot be undone. For an extended built-in (e.g. `post`, `category`), deleting only the pod removes the Pods fields but leaves the WP object intact. Deleting items on extended built-ins WILL delete actual posts / terms — be sure that is what the user wants.',
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
function pods_delete_pod(array $input): array
{
    [$lookup, $err] = pods_resolve_top_level_pod($input);
    if ($err !== null) {
        return ['success' => false, 'error' => $err];
    }

    /** @var object|false|null $pod */
    $pod = pods_api()->load_pod($lookup);
    if ($pod === false || $pod === null) {
        return ['success' => false, 'error' => 'Pod not found.'];
    }
    $existing = pods_pod_to_array($pod);
    $resolved_name = is_string($existing['name'] ?? null) ? $existing['name'] : '';

    $delete_items = pods_input_bool($input, key: 'delete_items') ?? false;
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->delete_pod(
        params: $lookup,
        strict: false,
        delete_all: $delete_items,
    ));
    return pods_format_delete_response($result, $resolved_name);
}
