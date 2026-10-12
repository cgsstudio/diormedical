<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Attach Pods fields to an existing built-in WP object
 * (post_type, taxonomy, user, comment, media).
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-extend-builtin', [
    'label' => __('Extend Built-in WP Object with Pods', domain: 'novamira-pro'),
    'description' => __(
        'Registers a Pods-managed wrapper around an existing built-in object so you can attach Pods fields to it. Targets: existing post types (incl. `post`, `page`, `attachment`, third-party CPTs), existing taxonomies (`category`, `post_tag`, …), `user`, `comment`, and `media`. After the wrapper is created, attach fields with `pods-create-field`.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'extend_type' => [
                'type' => 'string',
                'enum' => NOVAMIRA_PODS_EXTENDABLE_TYPES,
                'description' => 'What kind of object to extend.',
            ],
            'object' => [
                'type' => 'string',
                'description' => 'Slug of the existing object: post type slug for `post_type`, taxonomy slug for `taxonomy`. Ignored (and may be omitted) for `user`, `comment`, `media`.',
            ],
            'storage' => [
                'type' => 'string',
                'enum' => ['meta', 'table', 'none'],
                'default' => 'meta',
                'description' => '`meta` (default) stores fields in the object\'s native meta table; `table` requires a custom table; `none` is taxonomy-only and stores nothing.',
            ],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Free-form pod options.',
            ],
        ],
        'required' => ['extend_type'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_extend_builtin',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'For `extend_type=user|comment|media`, omit `object` (the wrapper has a fixed slug: `user`, `comment`, `media`). For `post_type|taxonomy`, the `object` value is the existing slug — Pods will refuse if the post type / taxonomy does not exist.',
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
function pods_extend_builtin(array $input): array
{
    $extend_type = pods_input_string($input, key: 'extend_type');
    if (
        $extend_type === null
        || !in_array(needle: $extend_type, haystack: NOVAMIRA_PODS_EXTENDABLE_TYPES, strict: true)
    ) {
        return [
            'success' => false,
            'error' =>
                '`extend_type` must be one of: ' . implode(separator: ', ', array: NOVAMIRA_PODS_EXTENDABLE_TYPES),
        ];
    }

    $resolved = pods_resolve_extend_object($extend_type, pods_input_string($input, key: 'object'));
    if ($resolved['error'] !== '') {
        return ['success' => false, 'error' => $resolved['error']];
    }

    $params = pods_build_extend_params($extend_type, $resolved['name'], $resolved['object'], $input);
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_pod($params));
    return pods_format_save_response($result, $resolved['name']);
}

/**
 * Resolve the (extend_type, object) pair to a concrete pod name + canonical
 * `object` value. Built-in singletons (user/comment/media) have a fixed
 * slug equal to the type. Existing post-type / taxonomy slugs are validated
 * against the live registry.
 *
 * @return array{name: string, object: string, error: string}
 */
function pods_resolve_extend_object(string $extend_type, ?string $object): array
{
    if ($extend_type === 'post_type') {
        if ($object === null) {
            return ['name' => '', 'object' => '', 'error' => '`object` is required when extending a post_type.'];
        }
        if (function_exists('get_post_type_object') && get_post_type_object($object) === null) {
            return [
                'name' => '',
                'object' => '',
                'error' => sprintf('Post type "%s" does not exist on this site.', $object),
            ];
        }
        return ['name' => $object, 'object' => $object, 'error' => ''];
    }
    if ($extend_type === 'taxonomy') {
        if ($object === null) {
            return ['name' => '', 'object' => '', 'error' => '`object` is required when extending a taxonomy.'];
        }
        if (function_exists('get_taxonomy') && get_taxonomy($object) === false) {
            return [
                'name' => '',
                'object' => '',
                'error' => sprintf('Taxonomy "%s" does not exist on this site.', $object),
            ];
        }
        return ['name' => $object, 'object' => $object, 'error' => ''];
    }
    return ['name' => $extend_type, 'object' => $extend_type, 'error' => ''];
}

/**
 * Assemble `save_pod()` params for an extend operation.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_extend_params(string $extend_type, string $name, string $object, array $input): array
{
    $params = [
        'name' => $name,
        'type' => $extend_type,
        'storage' => pods_input_string($input, key: 'storage') ?? 'meta',
        'object' => $object,
        'create_extend' => 'extend',
        'label' => ucwords(str_replace(search: '_', replace: ' ', subject: $name)),
    ];
    return array_merge($params, pods_input_options($input));
}
