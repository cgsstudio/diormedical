<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Read a single Pod definition with its fields, groups, and options.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-get-pod', [
    'label' => __('Get Pod', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full schema of a single pod: identifying info (id, name, type, storage, object), the options blob (label, description, public flags, REST settings, …), and the field/group structure. Use this to introspect the schema before editing or to verify what `create-cpt` / `create-taxonomy` / `create-act` actually wrote.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Pod name (slug). Either `name` or `id` is required.',
            ],
            'id' => [
                'type' => 'integer',
                'description' => 'Pod ID (alternative to `name`).',
            ],
            'include_fields' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include every field definition in the response. Default false to keep payload small — pods with 50+ fields blow past 25KB. Use `pods-list-fields` to enumerate, then `pods-get-field` for a specific field.',
            ],
            'include_groups' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include every group definition in the response. Default false. Use `pods-list-groups` instead when you only need the group list.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'pod' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => ['integer', 'string']],
                    'name' => ['type' => 'string'],
                    'label' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'storage' => ['type' => 'string'],
                    'object' => ['type' => 'string'],
                    'options' => ['type' => 'object'],
                    'fields' => [
                        'type' => 'array',
                        'items' => ['type' => 'object'],
                        'description' => 'Field definitions (omitted when `include_fields=false`).',
                    ],
                    'groups' => [
                        'type' => 'array',
                        'items' => ['type' => 'object'],
                        'description' => 'Group definitions (omitted when `include_groups=false`).',
                    ],
                ],
            ],
            'error' => [
                'type' => 'string',
                'description' => 'Present when the pod could not be loaded.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_get_pod',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the pod definition + options blob (label_singular, supports_*, public, rest_enable, …) without fields/groups by default. Use `pods-list-fields pod=<name>` to enumerate fields and `pods-list-groups pod=<name>` for groups. Set `include_fields=true`/`include_groups=true` only when you really need the full hydrated payload — pods with 50+ fields blow past 25KB.',
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
function pods_get_pod(array $input): array
{
    [$lookup, $err] = pods_resolve_top_level_pod($input);
    if ($err !== null) {
        return ['error' => $err];
    }

    /** @var object|false|null $pod */
    $pod = pods_api()->load_pod($lookup);
    if ($pod === false || $pod === null) {
        return ['error' => sprintf('Pod "%s" not found.', pods_format_lookup_label($lookup))];
    }

    $args = pods_pod_to_array($pod);

    // `Pod::$fields` / `$groups` are lazy collections that can be empty in
    // the same request that just created them — Whatsit caches the pod row
    // separately from its child rows. When the agent asks for them, hydrate
    // explicitly via `load_fields` / `load_groups` so the response matches
    // the DB.
    $include_fields = pods_input_bool($input, key: 'include_fields') ?? false;
    if ($include_fields) {
        $args['fields'] = pods_api()->load_fields(['pod' => $args['name'] ?? '']);
    }
    $include_groups = pods_input_bool($input, key: 'include_groups') ?? false;
    if ($include_groups) {
        $args['groups'] = pods_api()->load_groups(['pod' => $args['name'] ?? '']);
    }

    return ['pod' => pods_build_pod_response($args, $input)];
}

/**
 * Pretty-print the lookup args used in the load_pod call so the error
 * message references whichever form (`name=` / `id=`) the agent passed.
 *
 * @param array<string, mixed> $lookup
 */
function pods_format_lookup_label(array $lookup): string
{
    /** @var mixed $maybe_id */
    $maybe_id = $lookup['id'] ?? null;
    if (is_int($maybe_id)) {
        return '#' . $maybe_id;
    }
    /** @var mixed $maybe_name */
    $maybe_name = $lookup['name'] ?? null;
    return is_string($maybe_name) ? $maybe_name : '';
}

/**
 * Translate the loaded pod array into the response shape, optionally
 * including fields/groups based on the input flags.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_pod_response(array $args, array $input): array
{
    $out = pods_extract_pod_string_fields($args);
    $out['id'] = $args['id'] ?? null;
    $out['options'] = (object) pods_extract_pod_options($args);

    if (pods_input_bool($input, key: 'include_fields') ?? false) {
        $fields = is_array($args['fields'] ?? null) ? $args['fields'] : [];
        $out['fields'] = pods_collect_whatsit_array($fields);
    }
    if (pods_input_bool($input, key: 'include_groups') ?? false) {
        $groups = is_array($args['groups'] ?? null) ? $args['groups'] : [];
        $out['groups'] = pods_collect_whatsit_array($groups);
    }
    return $out;
}

/**
 * Pods 3.x stores every option flat at the root of `Whatsit::get_args()`
 * (not under a nested `options` array as the API param shape suggests).
 * Strip the keys we already expose at the top level of the response and
 * everything else (label_singular, public, supports_*, rest_enable, …)
 * lands in the returned `options` blob.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function pods_extract_pod_options(array $args): array
{
    $excluded = [
        'id' => true,
        'name' => true,
        'label' => true,
        'description' => true,
        'type' => true,
        'storage' => true,
        'object' => true,
        'fields' => true,
        'groups' => true,
        'object_type' => true,
        'object_storage_type' => true,
    ];
    return array_diff_key($args, $excluded);
}

/**
 * Pull the canonical string fields off a loaded pod's args array, defaulting
 * each one to '' when missing or non-string. Pulled out so the response
 * builder stays under the cyclomatic-complexity threshold.
 *
 * @param array<string, mixed> $args
 * @return array<string, string>
 */
function pods_extract_pod_string_fields(array $args): array
{
    $out = [];
    foreach (['name', 'label', 'description', 'type', 'storage', 'object'] as $string_key) {
        /** @var mixed $value */
        $value = $args[$string_key] ?? null;
        $out[$string_key] = is_string($value) ? $value : '';
    }
    return $out;
}

/**
 * Convert a list of Whatsit objects (or array entries) into a flat list of
 * arrays. Skips entries that are neither objects nor arrays.
 *
 * @param array<int|string, mixed> $items
 * @return list<array<string, mixed>>
 */
function pods_collect_whatsit_array(array $items): array
{
    $out = [];
    /** @var mixed $item */
    foreach ($items as $item) {
        if (!is_object($item) && !is_array($item)) {
            continue;
        }
        $out[] = pods_pod_to_array($item);
    }
    return $out;
}
