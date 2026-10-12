<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Pods Group abilities — create / edit / delete groups within a pod.
 *
 * Listing groups is intentionally not a separate ability: `pods-get-pod`
 * already returns the group list as part of the pod payload.
 */

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------
// list-groups
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-list-groups', [
    'label' => __('List Pods Field Groups', domain: 'novamira-pro'),
    'description' => __(
        'Lists every group defined on a pod, compact form: id, name, label, weight. Use this instead of `pods-get-pod include_groups=true` when you only need the group list — keeps the response small and avoids loading every field at the same time.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'pod' => ['type' => 'string', 'description' => 'Pod name (slug).'],
            'pod_id' => ['type' => 'integer'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'groups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => ['integer', 'string']],
                        'name' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'weight' => ['type' => ['integer', 'null']],
                    ],
                ],
            ],
            'total' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_list_groups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pair with `pods-list-fields` (filtered by group_id) to page into a specific group without ever loading the full pod payload.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------
// create-group
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-create-group', [
    'label' => __('Create Pods Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Adds a new field group to a pod. Pods 3.x requires every field to belong to a group — call this once per pod before any `pods-create-field`.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pod' => ['type' => 'string'],
            'pod_id' => ['type' => 'integer'],
            'name' => ['type' => 'string', 'description' => 'Group slug.'],
            'label' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'weight' => ['type' => 'integer'],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Free-form group options (e.g. metabox layout flags).',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_create_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'A pod can have many groups (e.g. one per metabox tab). For a simple schema, a single group named `details` is fine.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------
// edit-group
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-edit-group', [
    'label' => __('Edit Pods Field Group', domain: 'novamira-pro'),
    'description' => __('Updates a field group: rename, re-label, reorder, change options.', domain: 'novamira-pro'),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'pod' => ['type' => 'string'],
            'pod_id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'id' => ['type' => 'integer'],
            'new_name' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'weight' => ['type' => 'integer'],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_edit_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------
// delete-group
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-delete-group', [
    'label' => __('Delete Pods Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a field group. By default fields in the group are preserved (re-parented to no group); pass `delete_fields: true` to delete them along with the group.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'pod' => ['type' => 'string'],
            'pod_id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'id' => ['type' => 'integer'],
            'delete_fields' => ['type' => 'boolean', 'default' => false],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_delete_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => '`delete_fields: true` is destructive — every field in the group and every value stored for those fields is wiped. Confirm with the user.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------
// Callbacks
// ---------------------------------------------------------------------

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_list_groups(array $input): array
{
    [$pod_params, $err] = pods_resolve_pod_params($input);
    if ($err !== null) {
        return ['error' => $err];
    }

    /** @var array<int|string, mixed> $loaded */
    $loaded = pods_api()->load_groups($pod_params);

    $groups = [];
    /** @var mixed $g */
    foreach ($loaded as $g) {
        if (!is_object($g) && !is_array($g)) {
            continue;
        }
        $args = pods_pod_to_array($g);
        $groups[] = [
            'id' => $args['id'] ?? null,
            'name' => is_string($args['name'] ?? null) ? $args['name'] : '',
            'label' => is_string($args['label'] ?? null) ? $args['label'] : '',
            'weight' => is_int($args['weight'] ?? null) ? (int) $args['weight'] : null,
        ];
    }
    return ['groups' => $groups, 'total' => count($groups)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_create_group(array $input): array
{
    [$pod_params, $err] = pods_resolve_pod_params($input);
    if ($err !== null) {
        return ['success' => false, 'error' => $err];
    }
    $name = pods_input_string($input, key: 'name');
    if ($name === null) {
        return ['success' => false, 'error' => '`name` is required.'];
    }

    $params = pods_build_create_group_params($pod_params, $name, $input);
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_group($params));
    return pods_format_save_response($result, $name);
}

/**
 * @param array<string, mixed> $pod_params
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_create_group_params(array $pod_params, string $name, array $input): array
{
    $label = pods_input_string($input, key: 'label') ?? ucwords(str_replace(search: '_', replace: ' ', subject: $name));
    $params = array_merge($pod_params, [
        'name' => $name,
        'is_new' => true,
        'label' => $label,
        'description' => pods_input_string($input, key: 'description') ?? '',
    ]);

    $weight = pods_input_int($input, key: 'weight');
    if ($weight !== null) {
        $params['weight'] = $weight;
    }
    return array_merge($params, pods_input_options($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_edit_group(array $input): array
{
    [$pod_params, $err] = pods_resolve_pod_params($input);
    if ($err !== null) {
        return ['success' => false, 'error' => $err];
    }
    [$entity_params, $entity_err] = pods_resolve_entity($input);
    if ($entity_err !== null) {
        return ['success' => false, 'error' => $entity_err];
    }

    /** @var object|false|null $existing */
    $existing = pods_api()->load_group(array_merge($pod_params, $entity_params));
    if ($existing === false || $existing === null) {
        return ['success' => false, 'error' => 'Group not found.'];
    }
    $existing_args = pods_pod_to_array($existing);
    $existing_id = is_int($existing_args['id'] ?? null) ? (int) $existing_args['id'] : 0;
    $existing_name = is_string($existing_args['name'] ?? null) ? $existing_args['name'] : '';

    $params = pods_build_edit_group_params($pod_params, $existing_id, $existing_name, $input);
    $resolved_name = is_string($params['name'] ?? null) ? $params['name'] : $existing_name;

    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_group($params));
    return pods_format_save_response($result, $resolved_name);
}

/**
 * @param array<string, mixed> $pod_params
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_edit_group_params(array $pod_params, int $existing_id, string $existing_name, array $input): array
{
    $params = array_merge($pod_params, ['id' => $existing_id, 'name' => $existing_name]);

    $new_name = pods_input_string($input, key: 'new_name');
    if ($new_name !== null && $new_name !== $existing_name) {
        $params['name'] = $new_name;
        $params['old_name'] = $existing_name;
    }
    foreach (['label', 'description'] as $string_key) {
        $value = pods_input_string($input, $string_key);
        if ($value !== null) {
            $params[$string_key] = $value;
        }
    }
    $weight = pods_input_int($input, key: 'weight');
    if ($weight !== null) {
        $params['weight'] = $weight;
    }
    return array_merge($params, pods_input_options($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_delete_group(array $input): array
{
    [$pod_params, $err] = pods_resolve_pod_params($input);
    if ($err !== null) {
        return ['success' => false, 'error' => $err];
    }
    [$entity_params, $entity_err] = pods_resolve_entity($input);
    if ($entity_err !== null) {
        return ['success' => false, 'error' => $entity_err];
    }
    $params = array_merge($pod_params, $entity_params);
    $delete_fields = pods_input_bool($input, key: 'delete_fields') ?? false;

    /** @var object|false|null $existing */
    $existing = pods_api()->load_group($params);
    if ($existing === false || $existing === null) {
        return ['success' => false, 'error' => 'Group not found.'];
    }
    $existing_args = pods_pod_to_array($existing);
    $resolved_name = is_string($existing_args['name'] ?? null) ? $existing_args['name'] : '';
    $group_id = pods_normalize_int_id($existing_args['id'] ?? null) ?? 0;

    if ($delete_fields && $group_id > 0) {
        pods_cascade_delete_fields_in_group($pod_params, $group_id);
    }

    $result = pods_handle_api_result(static fn(): mixed => pods_api()->delete_group(
        params: $params,
        strict: false,
        delete_all: false,
    ));
    return pods_format_delete_response($result, $resolved_name);
}

/**
 * Manually delete every field assigned to the given group id. Pulled out of
 * `pods_delete_group` because `delete_group(delete_all: true)` iterates the
 * cached `$group['fields']` collection — which Pods 3.x leaves empty until a
 * fresh `load_fields` is issued — so the cascade silently skips the fields.
 * Listing the pod's fields and filtering by `group` (Whatsit stores the
 * group id under that key as a numeric string) guarantees the deletion.
 *
 * @param array<string, mixed> $pod_params
 */
function pods_cascade_delete_fields_in_group(array $pod_params, int $group_id): void
{
    /** @var array<int|string, mixed> $all_fields */
    $all_fields = pods_api()->load_fields($pod_params);
    /** @var mixed $f */
    foreach ($all_fields as $f) {
        if (!is_object($f) && !is_array($f)) {
            continue;
        }
        $fa = pods_field_to_array($f);
        if (pods_normalize_int_id($fa['group'] ?? null) !== $group_id) {
            continue;
        }
        $fid = pods_normalize_int_id($fa['id'] ?? null);
        if ($fid === null) {
            continue;
        }
        pods_api()->delete_field(array_merge($pod_params, ['id' => $fid]));
    }
}
