<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Create a brand-new Pods-managed Taxonomy.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-create-taxonomy', [
    'label' => __('Create Pods Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Creates a brand-new Pods-managed Taxonomy. To attach Pods fields to an existing taxonomy (category, post_tag, third-party), use `pods-extend-builtin` instead. After creation, attach the taxonomy to one or more post types via the `built_in_post_types_<post_type>` options.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Slug / internal taxonomy name. Lowercase letters, numbers, underscores. Max 32 chars per WP core.',
            ],
            'label_singular' => ['type' => 'string', 'description' => 'Singular display label (e.g. "Genre").'],
            'label_plural' => ['type' => 'string', 'description' => 'Plural display label (e.g. "Genres").'],
            'description' => ['type' => 'string'],
            'public' => ['type' => 'boolean', 'default' => true],
            'publicly_queryable' => ['type' => 'boolean', 'default' => true],
            'hierarchical' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'true → category-style (parent/child), false → tag-style.',
            ],
            'rest_enable' => ['type' => 'boolean', 'default' => true],
            'show_ui' => ['type' => 'boolean', 'default' => true],
            'show_admin_column' => ['type' => 'boolean', 'default' => false],
            'attached_post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Post type slugs to attach this taxonomy to (e.g. ["book","author"]). Optional — set later via the Pods admin or by editing the pod.',
            ],
            'storage' => [
                'type' => 'string',
                'enum' => ['none', 'meta', 'table'],
                'default' => 'none',
                'description' => '`none` (default) keeps Pods out of term storage; `meta` uses term meta; `table` creates a custom table.',
            ],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Free-form pod options merged on top of the rest.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_create_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'WP taxonomy slugs must not collide with reserved keys (`category`, `post_tag`, `nav_menu`, `link_category`, `post_format`). To attach the taxonomy to an existing post type without re-saving, call `pods-edit-pod name=<tax> options:{built_in_post_types_<cpt>: 1}`.',
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
function pods_create_taxonomy(array $input): array
{
    $name = pods_input_string($input, key: 'name');
    if ($name === null) {
        return ['success' => false, 'error' => '`name` is required.'];
    }

    if (function_exists('get_taxonomy') && get_taxonomy($name) !== false) {
        return [
            'success' => false,
            'error' => sprintf(
                'A taxonomy named "%s" already exists — use `pods-extend-builtin` to attach Pods fields to it instead.',
                $name,
            ),
        ];
    }

    $params = pods_build_taxonomy_params($name, $input);
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_pod($params));
    return pods_format_save_response($result, $name);
}

/**
 * Assemble the `save_pod()` params for a new taxonomy.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_taxonomy_params(string $name, array $input): array
{
    $labels = pods_normalize_labels($input, $name);

    $params = [
        'name' => $name,
        'type' => 'taxonomy',
        'storage' => pods_input_string($input, key: 'storage') ?? 'none',
        'label' => $labels['plural'],
        'label_singular' => $labels['singular'],
        'description' => pods_input_string($input, key: 'description') ?? '',
    ];

    $params = pods_apply_bool_flags($params, $input, [
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => true,
        'rest_enable' => true,
        'show_ui' => true,
        'show_admin_column' => false,
    ]);

    $params = pods_apply_attached_post_types($params, $input);
    return array_merge($params, pods_input_options($input));
}

/**
 * Translate the `attached_post_types` input array into the
 * `built_in_post_types_<cpt> => 1` flags Pods stores in its options blob.
 *
 * @param array<string, mixed> $params
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_apply_attached_post_types(array $params, array $input): array
{
    /** @var mixed $attached */
    $attached = $input['attached_post_types'] ?? null;
    if (!is_array($attached)) {
        return $params;
    }
    /** @var mixed $cpt */
    foreach ($attached as $cpt) {
        if (!is_string($cpt) || $cpt === '') {
            continue;
        }
        $params['built_in_post_types_' . $cpt] = 1;
    }
    return $params;
}
