<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-list-taxonomies', [
    'label' => __('List Meta Box Taxonomies', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight overview of all custom taxonomies registered via MB Custom Post Type: slug, title, source (db), object_type (array of post types the taxonomy applies to), hierarchical flag. Use slug with metabox-get-taxonomy for the full payload.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'taxonomies' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'source' => ['type' => 'string', 'const' => 'db'],
                        'object_type' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'hierarchical' => ['type' => 'boolean'],
                        'db_post_id' => ['type' => 'integer'],
                    ],
                    'required' => ['slug', 'title', 'source', 'object_type', 'hierarchical'],
                ],
            ],
        ],
        'required' => ['taxonomies'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_list_taxonomies',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists custom taxonomies registered via MB Custom Post Type. source is always "db". Use slug with metabox-get-taxonomy for the full settings payload.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{taxonomies: list<array<string, mixed>>}|WP_Error
 */
function metabox_list_taxonomies(): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $posts_raw */
    $posts_raw = get_posts([
        'post_type' => 'mb-taxonomy',
        'post_status' => 'publish',
        'numberposts' => -1,
    ]);
    if (!is_array($posts_raw)) {
        return ['taxonomies' => []];
    }
    /** @var array<int, mixed> $posts */
    $posts = $posts_raw;

    $out = [];
    /** @var mixed $post */
    foreach ($posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        $entry = mb_taxonomy_post_to_overview($post);
        if ($entry !== null) {
            $out[] = $entry;
        }
    }

    return ['taxonomies' => $out];
}

/**
 * @return array<string, mixed>|null
 */
function mb_taxonomy_post_to_overview(\WP_Post $post): ?array
{
    $settings = mb_decode_cpt_post_settings($post);
    if ($settings === null) {
        return null;
    }

    /** @var mixed $slug_raw */
    $slug_raw = $settings['slug'] ?? null;
    $slug = is_string($slug_raw) && $slug_raw !== '' ? $slug_raw : $post->post_name;

    // Source the post-type list from MB's real `types` key (the one
    // TaxonomyRegister registers against); the output field keeps the
    // agent-facing `object_type` name. Reading the legacy `object_type` key
    // here would re-mask taxonomies that register against nothing.
    /** @var mixed $object_type_raw */
    $object_type_raw = $settings['types'] ?? [];
    $object_type = is_array($object_type_raw)
        ? array_values(array_filter($object_type_raw, callback: 'is_string'))
        : [];

    /** @var mixed $hier_raw */
    $hier_raw = $settings['hierarchical'] ?? false;

    return [
        'slug' => $slug,
        'title' => $post->post_title,
        'source' => 'db',
        'object_type' => $object_type,
        'hierarchical' => is_bool($hier_raw) ? $hier_raw : false,
        'db_post_id' => $post->ID,
    ];
}
