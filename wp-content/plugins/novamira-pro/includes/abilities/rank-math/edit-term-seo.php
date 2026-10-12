<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/rank-math-edit-term-seo', [
    'label' => __('Edit Term SEO (Rank Math)', domain: 'novamira-pro'),
    'description' => __(
        'Updates the Rank Math SEO metadata for a single taxonomy term (category, tag, or custom-taxonomy term). Send only the fields to change — it is a partial merge; the robots block merges field-by-field. Fields: focus_keywords (list), seo_title, meta_description, canonical, robots {index: default|index|noindex, follow: follow|nofollow, advanced: list of noarchive/noimageindex/nosnippet}, facebook/twitter {title, description, image, image_id}. Rank Math stores term SEO as ordinary term meta. Identify the term by term_id + taxonomy. Returns the full updated SEO.',
        domain: 'novamira-pro',
    ),
    'category' => 'rank-math',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string'],
            'focus_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
            'seo_title' => ['type' => 'string'],
            'meta_description' => ['type' => 'string'],
            'canonical' => ['type' => 'string'],
            'robots' => [
                'type' => 'object',
                'properties' => [
                    'index' => ['type' => 'string', 'enum' => ['default', 'index', 'noindex']],
                    'follow' => ['type' => 'string', 'enum' => ['follow', 'nofollow']],
                    'advanced' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['noarchive', 'noimageindex', 'nosnippet']],
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
                    'image_id' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
            'twitter' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image' => ['type' => 'string'],
                    'image_id' => ['type' => 'integer'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\RankMath\rank_math_edit_term_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update of a term\'s Rank Math SEO; robots merges field-by-field. robots.index "default" inherits the taxonomy setting. Read first with rank-math-get-term-seo when unsure.',
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
function rank_math_edit_term_seo(array $args): array|WP_Error
{
    if (!rm_detect_setup()['active']) {
        return rm_not_active();
    }

    $term = rm_resolve_term($args['term_id'] ?? null, $args['taxonomy'] ?? null);
    if ($term instanceof WP_Error) {
        return $term;
    }

    if (array_key_exists('robots', $args)) {
        $robots_error = rmep_validate_robots($args['robots']);
        if ($robots_error instanceof WP_Error) {
            return $robots_error;
        }
    }

    rmet_apply($term->term_id, $args);

    return ['term_id' => $term->term_id, 'taxonomy' => $term->taxonomy, 'seo' => rm_term_seo($term)];
}

/**
 * Apply every provided field to the term (real term meta).
 *
 * @param array<string, mixed> $args
 */
function rmet_apply(int $term_id, array $args): void
{
    $map = [
        'seo_title' => 'rank_math_title',
        'meta_description' => 'rank_math_description',
        'canonical' => 'rank_math_canonical_url',
    ];
    foreach ($map as $friendly => $meta_key) {
        if (!array_key_exists($friendly, $args) || !is_string($args[$friendly])) {
            continue;
        }
        $value = $friendly === 'canonical' ? rm_clean_url($args[$friendly]) : rm_clean_text($args[$friendly]);
        update_term_meta($term_id, meta_key: $meta_key, meta_value: $value);
    }

    if (array_key_exists('focus_keywords', $args) && is_array($args['focus_keywords'])) {
        $kws = array_values(array_filter(
            array_map(static fn(mixed $kw): string => rm_clean_keyword(rm_str($kw)), $args['focus_keywords']),
            static fn(string $kw): bool => $kw !== '',
        ));
        update_term_meta($term_id, meta_key: 'rank_math_focus_keyword', meta_value: implode(', ', $kws));
    }

    if (array_key_exists('robots', $args) && is_array($args['robots'])) {
        rmet_apply_robots($term_id, $args['robots']);
    }

    foreach (['facebook', 'twitter'] as $network) {
        if (!array_key_exists($network, $args) || !is_array($args[$network])) {
            continue;
        }
        rmet_apply_social($term_id, "rank_math_{$network}", $args[$network]);
    }
}

/**
 * Merge the robots sub-block onto the term's current token array and write it.
 *
 * @param array<array-key, mixed> $robots
 */
function rmet_apply_robots(int $term_id, array $robots): void
{
    $current = rm_robots_array(rm_term_meta('rank_math_robots', $term_id));

    $index = array_key_exists('index', $robots) && is_string($robots['index'])
        ? $robots['index']
        : rm_index_label($current);
    $follow = array_key_exists('follow', $robots) && is_string($robots['follow'])
        ? $robots['follow']
        : rm_follow_label($current);
    $advanced = array_key_exists('advanced', $robots) && is_array($robots['advanced'])
        ? array_values(array_intersect(
            array_filter($robots['advanced'], callback: 'is_string'),
            ['noarchive', 'noimageindex', 'nosnippet'],
        ))
        : rm_advanced_robots($current);

    update_term_meta($term_id, meta_key: 'rank_math_robots', meta_value: rm_robots_tokens($index, $follow, $advanced));
}

/**
 * Write a social-preview block to term meta.
 *
 * @param array<array-key, mixed> $block
 */
function rmet_apply_social(int $term_id, string $prefix, array $block): void
{
    foreach (['title', 'description'] as $field) {
        if (!array_key_exists($field, $block) || !is_string($block[$field])) {
            continue;
        }
        update_term_meta($term_id, meta_key: "{$prefix}_{$field}", meta_value: rm_clean_text($block[$field]));
    }
    if (array_key_exists('image', $block) && is_string($block['image'])) {
        update_term_meta($term_id, meta_key: "{$prefix}_image", meta_value: rm_clean_url($block['image']));
    }
    if (array_key_exists('image_id', $block)) {
        update_term_meta($term_id, meta_key: "{$prefix}_image_id", meta_value: (string) rm_int($block['image_id']));
    }
}
