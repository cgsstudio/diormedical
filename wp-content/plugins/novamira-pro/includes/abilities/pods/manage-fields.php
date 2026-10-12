<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Pods Field abilities.
 *
 * Hosts five abilities — list-fields, get-field, create-field, edit-field,
 * delete-field — under one file because they share input vocabulary
 * (pod identifier, field identifier).
 */

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------
// list-fields
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-list-fields', [
    'label' => __('List Pods Fields', domain: 'novamira-pro'),
    'description' => __(
        'Lists every field defined on a pod. Output is compact: name, label, type, group_id, weight. Use `pods-get-field` for the full options blob of a single field.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'pod' => [
                'type' => 'string',
                'description' => 'Pod name (slug). Either `pod` or `pod_id` is required.',
            ],
            'pod_id' => ['type' => 'integer'],
            'type' => [
                'type' => 'string',
                'description' => 'Filter by field type (e.g. `pick`, `text`).',
            ],
            'group_id' => [
                'type' => 'integer',
                'description' => 'Filter to fields belonging to a specific group_id. Combine with `pods-list-groups` to paginate field-heavy pods one group at a time.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => ['integer', 'string']],
                        'name' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'group_id' => ['type' => ['integer', 'string', 'null']],
                        'weight' => ['type' => ['integer', 'null']],
                    ],
                ],
            ],
            'total' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_list_fields',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'For sites with hundreds of fields on a single pod, prefer this over `pods-get-pod` (which returns full field bodies). Pair with `pods-get-field` to drill into a specific field.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------
// get-field
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-get-field', [
    'label' => __('Get Pods Field', domain: 'novamira-pro'),
    'description' => __(
        'Returns a single Pods field with every type-specific key Pods stored, flattened into the field object at the top level (Pods\' Whatsit model does not nest options separately). Use this when you need to inspect or copy an existing field\'s configuration.',
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
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_get_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Either (`pod` + `name`) or (`pod_id` + `id`) is required. Type-specific keys (e.g. text_max_length, pick_object, pick_val, pick_format_type, number_decimals, file_format_type, etc.) are returned as top-level keys on the `field` object — NOT nested inside an options sub-object. Use `field[<key>]` directly.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------
// create-field
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-create-field', [
    'label' => __('Create Pods Field', domain: 'novamira-pro'),
    'description' => __(
        'Adds a new field to a pod. **Pods 3.x requires every field to belong to a group** — pass `group` (or `group_id`). If the pod has no group yet, call `pods-create-group pod=<pod> name=details` first. For type-specific options (e.g. `pick_object`, `file_format_type`, `number_decimals`) call `pods-get-field-type-schema field_type=<type>` to discover option keys, then pass them in `options`.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pod' => ['type' => 'string'],
            'pod_id' => ['type' => 'integer'],
            'name' => ['type' => 'string', 'description' => 'Field slug (lowercase letters/digits/underscores).'],
            'label' => ['type' => 'string', 'description' => 'Display label. Defaults to a humanised name.'],
            'type' => [
                'type' => 'string',
                'enum' => NOVAMIRA_PODS_FIELD_TYPES,
                'description' => 'Field type. Use `pods-get-field-type-schema` to see type-specific option keys.',
            ],
            'description' => ['type' => 'string'],
            'group' => [
                'type' => 'string',
                'description' => 'Group name (slug) to attach the field to. Required on Pods 3.x — use `pods-create-group` first if no group exists yet.',
            ],
            'group_id' => ['type' => 'integer'],
            'weight' => ['type' => 'integer', 'description' => 'Order within the group (lower → first).'],
            'required' => ['type' => 'boolean', 'default' => false],
            'unique' => ['type' => 'boolean', 'default' => false],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Free-form type-specific options. Get the keys via `pods-get-field-type-schema`.',
            ],
        ],
        'required' => ['name', 'type'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_create_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pods 3.x requires every field to belong to a group — pass `group` (or `group_id`). If the pod has no group yet, `pods-create-group pod=<pod> name=details` first. Common errors: "field name is reserved" — Pods rejects field names that collide with WP query vars (`author`, `category`, `post`, `tag`, …). See https://docs.pods.io/fields/settings-reserved-list-of-names/ — pick a non-reserved slug like `book_author`.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------
// edit-field
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-edit-field', [
    'label' => __('Edit Pods Field', domain: 'novamira-pro'),
    'description' => __(
        'Updates an existing Pods field — label, type, weight, options. Pass only the keys you want to change. To rename, set `new_name`.',
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
            'new_name' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => NOVAMIRA_PODS_FIELD_TYPES],
            'description' => ['type' => 'string'],
            'new_group' => ['type' => 'string', 'description' => 'Move the field into this group (by name).'],
            'new_group_id' => ['type' => 'integer'],
            'weight' => ['type' => 'integer'],
            'required' => ['type' => 'boolean'],
            'unique' => ['type' => 'boolean'],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Type-specific option overrides — merged on top of the existing options.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_edit_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Changing `type` on a field with existing data can lose values — Pods will keep what fits and drop the rest. Confirm with the user before changing the type of a populated field.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------
// delete-field
// ---------------------------------------------------------------------

wp_register_ability('novamira/pods-delete-field', [
    'label' => __('Delete Pods Field', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Pods field. Removes the field schema; values stored for this field are wiped from the storage layer (postmeta / table column / option). Irreversible.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_delete_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Confirm with the user before calling — values stored for the field are not recoverable. Either (`pod` + `name`) or (`pod_id` + `id`) is required.',
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
function pods_list_fields(array $input): array
{
    [$pod_params, $err] = pods_resolve_pod_params($input);
    if ($err !== null) {
        return ['error' => $err];
    }

    $type_filter = pods_input_string($input, key: 'type');
    if ($type_filter !== null) {
        $pod_params['type'] = $type_filter;
    }

    $group_filter = pods_normalize_int_id($input['group_id'] ?? null);

    /** @var array<int|string, mixed> $loaded */
    $loaded = pods_api()->load_fields($pod_params);

    $fields = [];
    /** @var mixed $field */
    foreach ($loaded as $field) {
        if (!is_object($field) && !is_array($field)) {
            continue;
        }
        $field_array = pods_field_to_array($field);
        if ($group_filter !== null) {
            $field_group_id = pods_normalize_int_id($field_array['group_id'] ?? null);
            if ($field_group_id !== $group_filter) {
                continue;
            }
        }
        $fields[] = pods_summarize_field($field_array);
    }
    return ['fields' => $fields, 'total' => count($fields)];
}

/**
 * Compact representation of a field for `list-fields`.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function pods_summarize_field(array $args): array
{
    // Pods 3.x stores the parent group id under the `group` key in
    // `Whatsit::get_args()` — input params accept `group_id` but the
    // internal arg is `group` (a numeric string). Coerce to int for a
    // consistent output shape.
    $group_id = pods_normalize_int_id($args['group'] ?? null);

    return [
        'id' => $args['id'] ?? null,
        'name' => is_string($args['name'] ?? null) ? $args['name'] : '',
        'label' => is_string($args['label'] ?? null) ? $args['label'] : '',
        'type' => is_string($args['type'] ?? null) ? $args['type'] : '',
        'group_id' => $group_id,
        'weight' => is_int($args['weight'] ?? null) ? (int) $args['weight'] : null,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_get_field(array $input): array
{
    [$pod_params, $err] = pods_resolve_pod_params($input);
    if ($err !== null) {
        return ['error' => $err];
    }
    [$entity_params, $entity_err] = pods_resolve_entity($input);
    if ($entity_err !== null) {
        return ['error' => $entity_err];
    }

    // `load_field` returns an empty Field object when the pod itself does not
    // exist (instead of returning false). Validate the pod first so the agent
    // gets a clear "pod not found" error rather than `{field: []}`.
    /** @var object|false|null $pod */
    $pod = pods_api()->load_pod($pod_params);
    if ($pod === false || $pod === null) {
        return ['error' => sprintf('Pod "%s" not found.', pods_format_pod_lookup_label($pod_params))];
    }

    $params = array_merge($pod_params, $entity_params);
    /** @var object|false|null $field */
    $field = pods_api()->load_field($params);
    if ($field === false || $field === null) {
        return ['error' => 'Field not found.'];
    }
    $args = pods_field_to_array($field);
    // An empty `name` indicates Pods returned a placeholder Field for an
    // unknown pod/field combination — surface as "not found" too.
    /** @var mixed $name_raw */
    $name_raw = $args['name'] ?? null;
    if (!is_string($name_raw) || $name_raw === '') {
        return ['error' => 'Field not found.'];
    }
    return ['field' => $args];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_create_field(array $input): array
{
    [$pod_params, $err] = pods_resolve_pod_params($input);
    if ($err !== null) {
        return ['success' => false, 'error' => $err];
    }
    $name = pods_input_string($input, key: 'name');
    if ($name === null) {
        return ['success' => false, 'error' => '`name` is required.'];
    }
    $type = pods_input_string($input, key: 'type');
    if ($type === null) {
        return ['success' => false, 'error' => '`type` is required.'];
    }

    $params = pods_build_create_field_params($pod_params, $name, $type, $input);
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_field($params));
    return pods_format_save_response($result, $name);
}

/**
 * Assemble `save_field()` params for a create operation.
 *
 * @param array<string, mixed> $pod_params
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_create_field_params(array $pod_params, string $name, string $type, array $input): array
{
    $label = pods_input_string($input, key: 'label') ?? ucwords(str_replace(search: '_', replace: ' ', subject: $name));
    $params = array_merge($pod_params, [
        'name' => $name,
        'type' => $type,
        'is_new' => true,
        'label' => $label,
        'description' => pods_input_string($input, key: 'description') ?? '',
    ]);

    $group_id = pods_input_int($input, key: 'group_id');
    if ($group_id !== null) {
        $params['group_id'] = $group_id;
    }
    $group = pods_input_string($input, key: 'group');
    if ($group_id === null && $group !== null) {
        $params['group'] = $group;
    }

    $weight = pods_input_int($input, key: 'weight');
    if ($weight !== null) {
        $params['weight'] = $weight;
    }
    $required = pods_input_bool($input, key: 'required');
    if ($required !== null) {
        $params['required'] = $required ? 1 : 0;
    }
    $unique = pods_input_bool($input, key: 'unique');
    if ($unique !== null) {
        $params['unique'] = $unique ? 1 : 0;
    }

    return array_merge($params, pods_input_options($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_edit_field(array $input): array
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
    $existing = pods_api()->load_field(array_merge($pod_params, $entity_params));
    if ($existing === false || $existing === null) {
        return ['success' => false, 'error' => 'Field not found.'];
    }
    $existing_args = pods_field_to_array($existing);
    $existing_id = is_int($existing_args['id'] ?? null) ? (int) $existing_args['id'] : 0;
    $existing_name = is_string($existing_args['name'] ?? null) ? $existing_args['name'] : '';

    $params = pods_build_edit_field_params($pod_params, $existing_id, $existing_name, $input);
    $resolved_name = is_string($params['name'] ?? null) ? $params['name'] : $existing_name;

    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_field($params));
    return pods_format_save_response($result, $resolved_name);
}

/**
 * Build the partial-update params for `save_field()`.
 *
 * @param array<string, mixed> $pod_params
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_edit_field_params(array $pod_params, int $existing_id, string $existing_name, array $input): array
{
    $params = array_merge($pod_params, ['id' => $existing_id, 'name' => $existing_name]);

    $new_name = pods_input_string($input, key: 'new_name');
    if ($new_name !== null && $new_name !== $existing_name) {
        $params['name'] = $new_name;
        $params['old_name'] = $existing_name;
    }

    foreach (['label', 'type', 'description'] as $string_key) {
        $value = pods_input_string($input, $string_key);
        if ($value !== null) {
            $params[$string_key] = $value;
        }
    }

    $new_group_id = pods_input_int($input, key: 'new_group_id');
    if ($new_group_id !== null) {
        $params['new_group_id'] = $new_group_id;
    }
    $new_group = pods_input_string($input, key: 'new_group');
    if ($new_group_id === null && $new_group !== null) {
        $params['new_group'] = $new_group;
    }

    $weight = pods_input_int($input, key: 'weight');
    if ($weight !== null) {
        $params['weight'] = $weight;
    }
    foreach (['required', 'unique'] as $bool_key) {
        $value = pods_input_bool($input, $bool_key);
        if ($value !== null) {
            $params[$bool_key] = $value ? 1 : 0;
        }
    }

    return array_merge($params, pods_input_options($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_delete_field(array $input): array
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

    /** @var object|false|null $existing */
    $existing = pods_api()->load_field($params);
    if ($existing === false || $existing === null) {
        return ['success' => false, 'error' => 'Field not found.'];
    }
    $existing_args = pods_field_to_array($existing);
    $resolved_name = is_string($existing_args['name'] ?? null) ? $existing_args['name'] : '';

    $result = pods_handle_api_result(static fn(): mixed => pods_api()->delete_field($params));
    return pods_format_delete_response($result, $resolved_name);
}
