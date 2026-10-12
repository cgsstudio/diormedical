<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/rank-math-edit-post-seo', [
    'label' => __('Edit Post SEO (Rank Math)', domain: 'novamira-pro'),
    'description' => __(
        'Updates the Rank Math SEO metadata for a single post or page. Send only the fields to change — it is a partial merge; nested blocks (robots, facebook, twitter) merge field-by-field. Fields: focus_keywords (list), seo_title, meta_description (may use Rank Math variables like %title% %sep% %sitename%), canonical, pillar_content (bool), robots {index: default|index|noindex, follow: follow|nofollow, advanced: list of noarchive/noimageindex/nosnippet}, facebook/twitter {title, description, image, image_id}, primary_category (category term id, or null to clear). The friendly robots labels are mapped to Rank Math\'s token array for you (so you never build a contradictory or empty robots set). Identify the post by post_id (alias: id). Returns the full updated SEO. Edit the schema type via the schema abilities.',
        domain: 'novamira-pro',
    ),
    'category' => 'rank-math',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'focus_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
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
            'primary_category' => ['type' => ['integer', 'null']],
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
    'execute_callback' => 'Novamira\Pro\Abilities\RankMath\rank_math_edit_post_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update: only the fields you send change, and robots merges field-by-field (sending robots.index alone keeps the current follow/advanced). robots.index "default" inherits the post-type setting; "index"/"noindex" force it. focus_keywords is a list. primary_category takes a category term id, or null to clear. Read first with rank-math-get-post-seo when unsure.',
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
function rank_math_edit_post_seo(array $args): array|WP_Error
{
    if (!rm_detect_setup()['active']) {
        return rm_not_active();
    }

    $post = rm_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    // Validate everything before writing anything (atomic).
    $invalid = rmep_validate($args);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    rmep_apply($post->ID, $args);

    return ['post_id' => $post->ID, 'seo' => rm_post_seo($post->ID, [])];
}

/**
 * Pre-flight validation. Returns a WP_Error, or null if clean.
 *
 * @param array<string, mixed> $args
 */
function rmep_validate(array $args): ?WP_Error
{
    if (array_key_exists('robots', $args)) {
        $robots_error = rmep_validate_robots($args['robots']);
        if ($robots_error instanceof WP_Error) {
            return $robots_error;
        }
    }

    if (array_key_exists('primary_category', $args)) {
        /** @var mixed $value */
        $value = $args['primary_category'];
        if (
            $value !== null
            && rm_int($value) > 0
            && rm_resolve_term($value, taxonomy: 'category') instanceof WP_Error
        ) {
            return new WP_Error(
                'rank_math_invalid_primary_category',
                'primary_category must be an existing category term id (or null/0 to clear)',
                ['status' => 400],
            );
        }
    }

    return null;
}

/**
 * Validate the robots sub-block without writing.
 */
function rmep_validate_robots(mixed $robots): ?WP_Error
{
    if (!is_array($robots)) {
        return new WP_Error('rank_math_invalid_robots', 'robots must be an object', ['status' => 400]);
    }
    if (
        array_key_exists('index', $robots)
        && (!is_string($robots['index']) || !in_array($robots['index'], ['default', 'index', 'noindex'], strict: true))
    ) {
        return new WP_Error('rank_math_invalid_robots', 'robots.index must be default, index, or noindex', [
            'status' => 400,
        ]);
    }
    if (
        array_key_exists('follow', $robots)
        && (!is_string($robots['follow']) || !in_array($robots['follow'], ['follow', 'nofollow'], strict: true))
    ) {
        return new WP_Error('rank_math_invalid_robots', 'robots.follow must be follow or nofollow', ['status' => 400]);
    }
    if (array_key_exists('advanced', $robots) && !rmep_advanced_valid($robots['advanced'])) {
        return new WP_Error(
            'rank_math_invalid_robots',
            'robots.advanced must be an array of: noarchive, noimageindex, nosnippet',
            ['status' => 400],
        );
    }
    return null;
}

/**
 * Whether the advanced-robots input is an array of recognised tokens only.
 */
function rmep_advanced_valid(mixed $advanced): bool
{
    if (!is_array($advanced)) {
        return false;
    }
    $strings = array_filter($advanced, callback: 'is_string');
    return (
        count($strings) === count($advanced)
        && array_diff($strings, ['noarchive', 'noimageindex', 'nosnippet']) === []
    );
}

/**
 * Apply every provided field to the post.
 *
 * @param array<string, mixed> $args
 */
function rmep_apply(int $post_id, array $args): void
{
    rmep_apply_scalars($post_id, $args);

    if (array_key_exists('focus_keywords', $args) && is_array($args['focus_keywords'])) {
        $kws = array_values(array_filter(
            array_map(static fn(mixed $kw): string => rm_clean_keyword(rm_str($kw)), $args['focus_keywords']),
            static fn(string $kw): bool => $kw !== '',
        ));
        update_post_meta($post_id, meta_key: 'rank_math_focus_keyword', meta_value: implode(', ', $kws));
    }

    if (array_key_exists('pillar_content', $args)) {
        update_post_meta(
            $post_id,
            meta_key: 'rank_math_pillar_content',
            meta_value: rm_bool($args['pillar_content']) ? 'on' : 'off',
        );
    }

    if (array_key_exists('robots', $args) && is_array($args['robots'])) {
        rmep_apply_robots($post_id, $args['robots']);
    }

    foreach (['facebook', 'twitter'] as $network) {
        if (!array_key_exists($network, $args) || !is_array($args[$network])) {
            continue;
        }
        rmep_apply_social($post_id, "rank_math_{$network}", $args[$network]);
    }

    if (array_key_exists('primary_category', $args)) {
        rmep_apply_primary_category($post_id, $args['primary_category']);
    }
}

/**
 * Write the plain string fields (sanitised).
 *
 * @param array<string, mixed> $args
 */
function rmep_apply_scalars(int $post_id, array $args): void
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
        update_post_meta($post_id, meta_key: $meta_key, meta_value: $value);
    }
}

/**
 * Merge the robots sub-block onto the current token array and write it.
 *
 * @param array<array-key, mixed> $robots
 */
function rmep_apply_robots(int $post_id, array $robots): void
{
    $current = rm_robots_array(rm_post_meta('rank_math_robots', $post_id));

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

    update_post_meta($post_id, meta_key: 'rank_math_robots', meta_value: rm_robots_tokens($index, $follow, $advanced));
}

/**
 * Write a social-preview block (prefix 'rank_math_facebook' or 'rank_math_twitter').
 *
 * @param array<array-key, mixed> $block
 */
function rmep_apply_social(int $post_id, string $prefix, array $block): void
{
    foreach (['title', 'description'] as $field) {
        if (!array_key_exists($field, $block) || !is_string($block[$field])) {
            continue;
        }
        update_post_meta($post_id, meta_key: "{$prefix}_{$field}", meta_value: rm_clean_text($block[$field]));
    }
    if (array_key_exists('image', $block) && is_string($block['image'])) {
        update_post_meta($post_id, meta_key: "{$prefix}_image", meta_value: rm_clean_url($block['image']));
    }
    if (array_key_exists('image_id', $block)) {
        update_post_meta($post_id, meta_key: "{$prefix}_image_id", meta_value: (string) rm_int($block['image_id']));
    }
}

/**
 * Set or clear the primary category (null / non-positive clears it).
 */
function rmep_apply_primary_category(int $post_id, mixed $value): void
{
    $id = rm_int($value);
    if ($id <= 0) {
        delete_post_meta($post_id, meta_key: 'rank_math_primary_category');
        return;
    }
    update_post_meta($post_id, meta_key: 'rank_math_primary_category', meta_value: $id);
}
