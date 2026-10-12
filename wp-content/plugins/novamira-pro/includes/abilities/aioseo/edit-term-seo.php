<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use AIOSEO\Plugin\Pro\Models\Term;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-edit-term-seo', [
    'label' => __('Edit Term SEO (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Updates the All in One SEO metadata for a single taxonomy term (category, tag, or custom-taxonomy term). Requires AIOSEO Pro. Send only the fields to change — partial merge; robots/facebook/twitter merge field-by-field. Fields: seo_title, meta_description, canonical, pillar_content (bool), robots {index: default|index|noindex, follow: follow|nofollow, advanced: list of noarchive/noimageindex/nosnippet/noodp/notranslate, plus SERP limits max_snippet (-1 unlimited, 0 none), max_image_preview (none|standard|large), max_video_preview}, facebook/twitter {title, description, image}. As with posts, AIOSEO robots is all-or-nothing — index "default" makes the term inherit ALL robots; use "index"/"noindex" to set explicit robots. Identify the term by term_id + taxonomy. Returns the full updated SEO.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string'],
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
        'required' => ['term_id', 'taxonomy'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string'],
            'seo' => ['type' => 'object'],
        ],
        'required' => ['term_id', 'taxonomy', 'seo'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_edit_term_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update of a term\'s AIOSEO metadata. Requires AIOSEO Pro (aioseo_pro_required otherwise). robots.index "default" inherits (all-or-nothing); use "index"/"noindex" for explicit robots. Identify by term_id + taxonomy. Read first with aioseo-get-term-seo when unsure.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{term_id: int, taxonomy: string, seo: array<string, mixed>}|WP_Error
 */
function aioseo_edit_term_seo(array $args): array|WP_Error
{
    $detect = aio_detect_setup();
    if (!$detect['active']) {
        return aio_not_active();
    }
    if (!$detect['pro']) {
        return aio_pro_required('Term SEO');
    }

    $term = aio_resolve_term($args['term_id'] ?? null, $args['taxonomy'] ?? null);
    if ($term instanceof WP_Error) {
        return $term;
    }

    if (array_key_exists('robots', $args)) {
        $invalid = aio_validate_robots($args['robots']);
        if ($invalid instanceof WP_Error) {
            return $invalid;
        }
    }

    aiet_apply($term->term_id, $args);

    return [
        'term_id' => $term->term_id,
        'taxonomy' => $term->taxonomy,
        'seo' => aio_term_seo($term->term_id),
    ];
}

/**
 * Apply every provided field to the term's AIOSEO model and save once.
 *
 * @param array<string, mixed> $args
 */
function aiet_apply(int $term_id, array $args): void
{
    $model = Term::getTerm($term_id);

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
