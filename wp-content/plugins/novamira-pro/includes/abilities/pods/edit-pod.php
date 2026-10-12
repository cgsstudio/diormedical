<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Edit an existing Pod definition.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-edit-pod', [
    'label' => __('Edit Pod', domain: 'novamira-pro'),
    'description' => __(
        'Updates an existing Pod definition — labels, description, public flags, supports, menu, REST settings, custom options. Pass only the keys you want to change; existing options are preserved. To rename the pod, set `new_name` (renames the post type / taxonomy / table accordingly).',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Pod name (slug). Either `name` or `id` is required.'],
            'id' => ['type' => 'integer', 'description' => 'Pod ID (alternative to `name`).'],
            'new_name' => [
                'type' => 'string',
                'description' => 'Rename the pod. Pods will rename the underlying post type / taxonomy / table.',
            ],
            'label' => ['type' => 'string', 'description' => 'Plural display label.'],
            'label_singular' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'public' => ['type' => 'boolean'],
            'publicly_queryable' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'has_archive' => ['type' => 'boolean'],
            'rest_enable' => ['type' => 'boolean'],
            'show_ui' => ['type' => 'boolean'],
            'menu_icon' => ['type' => 'string'],
            'menu_position' => ['type' => 'integer'],
            'supports' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => NOVAMIRA_PODS_SUPPORTS_FLAGS],
                'description' => 'Replace the `supports_*` flags. Pass an empty array to disable all.',
            ],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Free-form pod options to merge / overwrite.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_edit_pod',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Renames are heavy operations — they touch the post-type registration and rewrite rules. Avoid renaming a pod with thousands of items unless you really need to. After editing, run `pods-get-pod name=<pod>` to verify the change persisted.',
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
function pods_edit_pod(array $input): array
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
    $existing_id = is_int($existing['id'] ?? null) ? (int) $existing['id'] : 0;
    $existing_name = is_string($existing['name'] ?? null) ? $existing['name'] : '';

    $params = pods_build_edit_pod_params($existing_id, $existing_name, $input);
    $resolved_name = is_string($params['name'] ?? null) ? $params['name'] : $existing_name;

    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_pod($params));
    return pods_format_save_response($result, $resolved_name);
}

/**
 * Build the partial-update params for `save_pod()` from the input fields the
 * agent actually passed. Keeping this separate from the callback keeps both
 * functions under mago's complexity threshold and lets us reuse the
 * supports-replacement logic without inlining it.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_edit_pod_params(int $existing_id, string $existing_name, array $input): array
{
    $params = ['id' => $existing_id, 'name' => $existing_name];

    $new_name = pods_input_string($input, key: 'new_name');
    if ($new_name !== null && $new_name !== $existing_name) {
        $params['name'] = $new_name;
        $params['old_name'] = $existing_name;
    }

    foreach (['label', 'label_singular', 'description', 'menu_icon'] as $string_key) {
        $value = pods_input_string($input, $string_key);
        if ($value !== null) {
            $params[$string_key] = $value;
        }
    }

    foreach (['public', 'publicly_queryable', 'hierarchical', 'has_archive', 'rest_enable', 'show_ui'] as $bool_key) {
        $value = pods_input_bool($input, $bool_key);
        if ($value !== null) {
            $params[$bool_key] = $value ? 1 : 0;
        }
    }

    $menu_position = pods_input_int($input, key: 'menu_position');
    if ($menu_position !== null) {
        $params['menu_position'] = $menu_position;
    }

    $params = array_merge($params, pods_apply_supports_override($input));
    return array_merge($params, pods_input_options($input));
}

/**
 * Build the `supports_*` map for an edit-pod call. When the agent passes a
 * `supports` array we treat it as a replacement set — every known flag is
 * reset to 0 first, then the requested ones are flipped to 1.
 *
 * @param array<string, mixed> $input
 * @return array<string, int>
 */
function pods_apply_supports_override(array $input): array
{
    /** @var mixed $raw */
    $raw = $input['supports'] ?? null;
    if (!is_array($raw)) {
        return [];
    }
    $params = [];
    foreach (NOVAMIRA_PODS_SUPPORTS_FLAGS as $flag) {
        $params['supports_' . str_replace(search: '-', replace: '_', subject: $flag)] = 0;
    }
    $list = array_values(array_filter($raw, callback: 'is_string'));
    return array_merge($params, pods_expand_supports($list));
}
