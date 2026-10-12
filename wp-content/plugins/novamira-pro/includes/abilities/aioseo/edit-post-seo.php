<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use AIOSEO\Plugin\Common\Models\Post;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-edit-post-seo', [
    'label' => __('Edit Post SEO (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Updates the All in One SEO metadata for a single post or page. Send only the fields to change — it is a partial merge; nested blocks (robots, facebook, twitter) merge field-by-field. Fields: focus_keyphrase, seo_title, meta_description (may use AIOSEO smart tags like #post_title #separator_sa #site_title), canonical, pillar_content (bool, cornerstone), robots {index: default|index|noindex, follow: follow|nofollow, advanced: list of noarchive/noimageindex/nosnippet/noodp/notranslate, plus SERP limits max_snippet (-1 unlimited, 0 none), max_image_preview (none|standard|large), max_video_preview}, facebook/twitter {title, description, image}. IMPORTANT: AIOSEO\'s robots is all-or-nothing — index "default" makes the post inherit ALL robots (the global / post-type setting); to set follow or advanced you must set index to "index" or "noindex". The friendly labels are mapped to AIOSEO\'s robots_default + per-flag columns for you. Identify the post by post_id (alias: id). Returns the full updated SEO.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'focus_keyphrase' => ['type' => 'string'],
            'seo_title' => ['type' => 'string'],
            'meta_description' => ['type' => 'string'],
            'canonical' => ['type' => 'string'],
            'pillar_content' => ['type' => 'boolean'],
            'robots' => [
                'type' => 'object',
                'properties' => [
                    'index' => ['type' => 'string', 'enum' => ['default', 'index', 'noindex']],
                    'follow' => ['type' => 'string', 'enum' => ['follow', 'nofollow']],
                    'advanced' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => ['noarchive', 'noimageindex', 'nosnippet', 'noodp', 'notranslate'],
                        ],
                    ],
                    'max_snippet' => [
                        'type' => 'integer',
                        'description' => 'SERP snippet length; -1 = unlimited, 0 = none.',
                    ],
                    'max_image_preview' => ['type' => 'string', 'enum' => ['none', 'standard', 'large']],
                    'max_video_preview' => [
                        'type' => 'integer',
                        'description' => 'SERP video preview seconds; -1 = unlimited, 0 = none.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'facebook' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image' => ['type' => 'string'],
                    'object_type' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'twitter' => [
                'type' => 'object',
                'properties' => [
                    'use_og' => ['type' => 'boolean'],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image' => ['type' => 'string'],
                    'card' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'seo' => ['type' => 'object'],
        ],
        'required' => ['post_id', 'seo'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_edit_post_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update: only the fields you send change, and robots merges field-by-field. robots.index "default" inherits the post-type setting (and resets follow/advanced — it is all-or-nothing); use "index"/"noindex" to set explicit robots and to enable follow/advanced. Read first with aioseo-get-post-seo when unsure.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{post_id: int, seo: array<string, mixed>}|WP_Error
 */
function aioseo_edit_post_seo(array $args): array|WP_Error
{
    if (!aio_detect_setup()['active']) {
        return aio_not_active();
    }

    $post = aio_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    if (array_key_exists('robots', $args)) {
        $invalid = aio_validate_robots($args['robots']);
        if ($invalid instanceof WP_Error) {
            return $invalid;
        }
    }

    aiep_apply($post->ID, $args);

    return ['post_id' => $post->ID, 'seo' => aio_post_seo($post->ID)];
}

/**
 * Apply every provided field to the post's AIOSEO model and save once.
 *
 * @param array<string, mixed> $args
 */
function aiep_apply(int $post_id, array $args): void
{
    $model = Post::getPost($post_id);

    if (array_key_exists('focus_keyphrase', $args) && is_string($args['focus_keyphrase'])) {
        aio_set_focus_keyphrase($model, aio_clean_text($args['focus_keyphrase']));
    }
    if (array_key_exists('seo_title', $args) && is_string($args['seo_title'])) {
        $model->title = aio_clean_text($args['seo_title']);
    }
    if (array_key_exists('meta_description', $args) && is_string($args['meta_description'])) {
        $model->description = aio_clean_text($args['meta_description']);
    }
    if (array_key_exists('canonical', $args) && is_string($args['canonical'])) {
        $model->canonical_url = aio_clean_url($args['canonical']);
    }
    if (array_key_exists('pillar_content', $args)) {
        $model->pillar_content = aio_bool($args['pillar_content']);
    }
    if (array_key_exists('robots', $args) && is_array($args['robots'])) {
        aio_apply_robots_merge($model, $args['robots']);
    }
    if (array_key_exists('facebook', $args) && is_array($args['facebook'])) {
        aio_apply_facebook($model, $args['facebook']);
    }
    if (array_key_exists('twitter', $args) && is_array($args['twitter'])) {
        aio_apply_twitter($model, $args['twitter']);
    }

    $model->save();
}
