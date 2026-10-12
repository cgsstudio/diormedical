<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Create a brand-new Pods-managed Custom Post Type.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-create-cpt', [
    'label' => __('Create Pods CPT', domain: 'novamira-pro'),
    'description' => __(
        'Creates a brand-new Pods-managed Custom Post Type. To attach Pods fields to an existing post type (post, page, attachment, third-party CPT), use `pods-extend-builtin` instead. After creation use `pods-create-field` / `pods-create-group` to model the schema.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Slug / internal name. Lowercase letters, numbers, underscores. Becomes the post-type key (max 20 chars per WP core).',
            ],
            'label_singular' => ['type' => 'string', 'description' => 'Singular display label (e.g. "Book").'],
            'label_plural' => [
                'type' => 'string',
                'description' => 'Plural display label (e.g. "Books"). Defaults to label_singular.',
            ],
            'description' => ['type' => 'string'],
            'public' => ['type' => 'boolean', 'default' => true],
            'publicly_queryable' => ['type' => 'boolean', 'default' => true],
            'hierarchical' => ['type' => 'boolean', 'default' => false],
            'has_archive' => ['type' => 'boolean', 'default' => false],
            'rest_enable' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Expose this CPT in the WP REST API (also gates Gutenberg / Elementor / Bricks editing).',
            ],
            'menu_icon' => [
                'type' => 'string',
                'description' => 'Dashicon name (`dashicons-book`) or full URL to a 20x20 SVG/PNG.',
            ],
            'menu_position' => ['type' => 'integer'],
            'supports' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => NOVAMIRA_PODS_SUPPORTS_FLAGS],
                'description' => 'WP post-type `supports` flags. Defaults to ["title","editor"].',
            ],
            'storage' => [
                'type' => 'string',
                'enum' => ['meta', 'table'],
                'default' => 'meta',
                'description' => 'Where Pods stores extra fields. `meta` (default) uses postmeta; `table` creates a custom table (faster reads, requires the ACT component for table ops on post-type pods).',
            ],
            'options' => [
                'type' => 'object',
                'description' => 'Free-form pod options merged on top of the rest. Use this for any flag the explicit properties do not cover (e.g. `rewrite_with_front`, `query_var`, `show_in_menu`, `capability_type`).',
                'additionalProperties' => true,
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_create_cpt',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Slug rules: 1–20 chars, lowercase letters/digits/underscores, must not collide with WP reserved keys (post, page, attachment, …) or with an existing post type. Run `pods-list-pods type=post_type` first to confirm the slug is free. To later attach fields, call `pods-create-field pod=<name> ...`.',
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
function pods_create_cpt(array $input): array
{
    $name = pods_input_string($input, key: 'name');
    if ($name === null) {
        return ['success' => false, 'error' => '`name` is required.'];
    }

    if (function_exists('get_post_type_object') && get_post_type_object($name) !== null) {
        return [
            'success' => false,
            'error' => sprintf(
                'A post type named "%s" already exists — use `pods-extend-builtin` to attach Pods fields to it instead.',
                $name,
            ),
        ];
    }

    $params = pods_build_cpt_params($name, $input);
    $result = pods_handle_api_result(static fn(): mixed => pods_api()->save_pod($params));
    return pods_format_save_response($result, $name);
}

/**
 * Assemble the `save_pod()` params for a new CPT — pulled out so the callback
 * stays under mago's complexity threshold.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_cpt_params(string $name, array $input): array
{
    $labels = pods_normalize_labels($input, $name);

    $params = [
        'name' => $name,
        'type' => 'post_type',
        'storage' => pods_input_string($input, key: 'storage') ?? 'meta',
        'label' => $labels['plural'],
        'label_singular' => $labels['singular'],
        'description' => pods_input_string($input, key: 'description') ?? '',
        'show_ui' => 1,
    ];

    $params = pods_apply_bool_flags($params, $input, [
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => false,
        'has_archive' => false,
        'rest_enable' => true,
    ]);

    $menu_icon = pods_input_string($input, key: 'menu_icon');
    if ($menu_icon !== null) {
        $params['menu_icon'] = $menu_icon;
    }
    $menu_position = pods_input_int($input, key: 'menu_position');
    if ($menu_position !== null) {
        $params['menu_position'] = $menu_position;
    }

    $params = array_merge($params, pods_expand_supports(pods_resolve_supports_input($input)));
    return array_merge($params, pods_input_options($input));
}

/**
 * Decode the `supports` input array (or fall back to title+editor when the
 * agent did not pass it), returning a clean list of string flags.
 *
 * @param array<string, mixed> $input
 * @return list<string>
 */
function pods_resolve_supports_input(array $input): array
{
    /** @var mixed $raw */
    $raw = $input['supports'] ?? null;
    if (!is_array($raw)) {
        return ['title', 'editor'];
    }
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_string($entry)) {
            continue;
        }

        $out[] = $entry;
    }
    return $out;
}
