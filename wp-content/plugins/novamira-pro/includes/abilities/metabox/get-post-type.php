<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-get-post-type', [
    'label' => __('Get Meta Box Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full settings payload for a single MB Custom Post Type entry identified by slug. The payload mirrors the MB CPT internal format (slug, labels, public, hierarchical, supports, taxonomies, menu_icon, etc.) and can be used as a reference for metabox-edit-post-type.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The post type slug.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'source' => ['type' => 'string', 'const' => 'db'],
            'db_post_id' => ['type' => 'integer'],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['slug', 'title', 'source', 'db_post_id', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_get_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetch the full settings for one MB Custom Post Type entry. The settings object is the raw MB CPT payload (slug, labels, public, hierarchical, supports, taxonomies, menu_icon, rewrite, etc.). Use as a reference or feed it into metabox-edit-post-type.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slug: string} $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_get_post_type(array $args): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $args['slug'];
    if ($slug === '') {
        return new WP_Error('metabox_invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    $post = mb_find_cpt_post($slug, post_type: 'mb-post-type');
    if ($post === null) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: post type slug. */
            __('Post type "%s" not found.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    $settings = mb_decode_cpt_post_settings($post);

    return [
        'slug' => $slug,
        'title' => $post->post_title,
        'source' => 'db',
        'db_post_id' => $post->ID,
        'settings' => $settings ?? [],
    ];
}

/**
 * Find a mb-post-type or mb-taxonomy CPT post by slug from post_content.
 */
function mb_find_cpt_post(string $slug, string $post_type): ?\WP_Post
{
    // Try post_name match first (slug stored in post_name for new entries).
    /** @var mixed $posts */
    $posts = get_posts([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'name' => $slug,
        'numberposts' => 1,
    ]);
    if (is_array($posts) && count($posts) > 0 && $posts[0] instanceof \WP_Post) {
        return $posts[0];
    }

    // Fallback: scan post_content JSON for the slug field.
    /** @var mixed $all_posts_raw */
    $all_posts_raw = get_posts([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'numberposts' => -1,
    ]);
    if (!is_array($all_posts_raw)) {
        return null;
    }
    /** @var array<int, mixed> $all_posts */
    $all_posts = $all_posts_raw;
    /** @var mixed $post */
    foreach ($all_posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        $settings = mb_decode_cpt_post_settings($post);
        if ($settings === null) {
            continue;
        }
        /** @var mixed $slug_raw */
        $slug_raw = $settings['slug'] ?? null;
        if (is_string($slug_raw) && $slug_raw === $slug) {
            return $post;
        }
    }

    return null;
}
