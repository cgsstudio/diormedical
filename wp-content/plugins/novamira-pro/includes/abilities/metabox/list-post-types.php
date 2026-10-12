<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-list-post-types', [
    'label' => __('List Meta Box Post Types', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight overview of all post types registered via MB Custom Post Type: slug, title, source (db), public flag, hierarchical flag, menu_icon, and active flag. Use to discover available post types before calling metabox-get-post-type for the full payload.',
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
            'post_types' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'source' => ['type' => 'string', 'const' => 'db'],
                        'public' => ['type' => 'boolean'],
                        'hierarchical' => ['type' => 'boolean'],
                        'menu_icon' => ['type' => 'string'],
                        'db_post_id' => ['type' => 'integer'],
                    ],
                    'required' => ['slug', 'title', 'source', 'public', 'hierarchical'],
                ],
            ],
        ],
        'required' => ['post_types'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_list_post_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists post types registered via MB Custom Post Type. source is always "db" (MB CPT only stores DB-backed post types). Use slug with metabox-get-post-type for the full settings payload.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{post_types: list<array<string, mixed>>}|WP_Error
 */
function metabox_list_post_types(): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $posts_raw */
    $posts_raw = get_posts([
        'post_type' => 'mb-post-type',
        'post_status' => 'publish',
        'numberposts' => -1,
    ]);

    $out = [];
    if (!is_array($posts_raw)) {
        return ['post_types' => $out];
    }
    /** @var array<int, mixed> $posts */
    $posts = $posts_raw;
    /** @var mixed $post */
    foreach ($posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        $entry = mb_post_type_post_to_overview($post);
        if ($entry !== null) {
            $out[] = $entry;
        }
    }

    return ['post_types' => $out];
}

/**
 * Build a lightweight overview row from a mb-post-type CPT post.
 *
 * @return array<string, mixed>|null
 */
function mb_post_type_post_to_overview(\WP_Post $post): ?array
{
    $settings = mb_decode_cpt_post_settings($post);
    if ($settings === null) {
        return null;
    }

    /** @var mixed $slug_raw */
    $slug_raw = $settings['slug'] ?? null;
    $slug = is_string($slug_raw) && $slug_raw !== '' ? $slug_raw : $post->post_name;

    /** @var mixed $public_raw */
    $public_raw = $settings['public'] ?? true;
    /** @var mixed $hierarchical_raw */
    $hierarchical_raw = $settings['hierarchical'] ?? false;
    /** @var mixed $icon_raw */
    $icon_raw = $settings['menu_icon'] ?? 'dashicons-admin-generic';

    return [
        'slug' => $slug,
        'title' => $post->post_title,
        'source' => 'db',
        'public' => is_bool($public_raw) ? $public_raw : true,
        'hierarchical' => is_bool($hierarchical_raw) ? $hierarchical_raw : false,
        'menu_icon' => is_string($icon_raw) ? $icon_raw : 'dashicons-admin-generic',
        'db_post_id' => $post->ID,
    ];
}

/**
 * Decode the settings from a mb-post-type or mb-taxonomy CPT post.
 * Settings are stored as JSON in post_content (new format) or legacy postmeta.
 *
 * @return array<string, mixed>|null
 */
function mb_decode_cpt_post_settings(\WP_Post $post): ?array
{
    if ($post->post_content !== '') {
        /** @var mixed $decoded_raw */
        $decoded_raw = json_decode($post->post_content, associative: true);
        if (is_array($decoded_raw)) {
            /** @var array<string, mixed> $decoded_raw */
            return $decoded_raw;
        }
    }

    // Legacy: individual meta keys prefixed with args_ / label_.
    /** @var mixed $meta_raw */
    $meta_raw = get_post_meta($post->ID);
    if (!is_array($meta_raw) || $meta_raw === []) {
        return null;
    }
    /** @var array<string, mixed> $meta */
    $meta = $meta_raw;

    /** @var array<string, mixed> $settings */
    $settings = ['labels' => []];
    /** @var mixed $meta_val */
    foreach ($meta as $meta_key => $meta_val) {
        /** @var mixed $raw */
        $raw = is_array($meta_val) ? $meta_val[0] : $meta_val;
        if (str_starts_with($meta_key, 'label_')) {
            $label_key = substr($meta_key, offset: 6);
            /** @var array<string, mixed> $labels */
            $labels = $settings['labels'];
            $labels[$label_key] = is_scalar($raw) ? $raw : '';
            $settings['labels'] = $labels;
            continue;
        }

        if (str_starts_with($meta_key, 'args_')) {
            $arg_key = substr($meta_key, offset: 5);
            $settings[$arg_key] = $raw;
        }
    }

    // Normalize post_type → slug if legacy format.
    if (!isset($settings['slug']) && isset($settings['post_type'])) {
        $settings['slug'] = $settings['post_type'];
        unset($settings['post_type']);
    }

    return $settings === ['labels' => []] ? null : $settings;
}
