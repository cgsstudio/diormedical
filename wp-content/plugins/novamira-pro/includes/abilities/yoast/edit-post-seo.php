<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WP_Error;
use WPSEO_Meta;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/yoast-edit-post-seo', [
    'label' => __('Edit Post SEO (Yoast)', domain: 'novamira-pro'),
    'description' => __(
        'Updates the Yoast SEO metadata for a single post or page. Send only the fields you want to change — it is a partial merge; nested blocks (robots, opengraph, twitter, schema) merge field-by-field. Fields: focus_keyphrase, seo_title, meta_description (may use Yoast variables like %%title%% %%sep%% %%sitename%%), canonical, breadcrumb_title, cornerstone (bool), robots {index: default|index|noindex, follow: follow|nofollow, advanced: list of noimageindex/noarchive/nosnippet}, opengraph/twitter {title, description, image, image_id}, schema {page_type, article_type}, primary_category (term id, or null to clear), redirect (Premium). The friendly index labels are mapped to Yoast\'s raw codes for you (so you never risk the 0/2/1 noindex trap). Identify the post by post_id (alias: id). Returns the full updated SEO.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The post or page id.'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'focus_keyphrase' => ['type' => 'string'],
            'seo_title' => ['type' => 'string'],
            'meta_description' => ['type' => 'string'],
            'canonical' => ['type' => 'string'],
            'breadcrumb_title' => ['type' => 'string'],
            'cornerstone' => ['type' => 'boolean'],
            'robots' => [
                'type' => 'object',
                'properties' => [
                    'index' => ['type' => 'string', 'enum' => ['default', 'index', 'noindex']],
                    'follow' => ['type' => 'string', 'enum' => ['follow', 'nofollow']],
                    'advanced' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['noimageindex', 'noarchive', 'nosnippet']],
                    ],
                ],
                'additionalProperties' => false,
            ],
            'opengraph' => [
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
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'page_type' => ['type' => 'string'],
                    'article_type' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'primary_category' => ['type' => ['integer', 'null']],
            'redirect' => ['type' => 'string'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_edit_post_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update: only the fields you send change. robots.index uses friendly labels (default|index|noindex) — "default" follows the post-type setting, "index"/"noindex" force it. Setting a field to its Yoast default removes the stored override (Yoast keeps the DB clean) — a re-read still shows the default, which is correct. primary_category takes a category term id, or null to clear it. Read first with yoast-get-post-seo when unsure of current values.',
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
function yoast_edit_post_seo(array $args): array|WP_Error
{
    if (!yoast_detect_setup()['active']) {
        return yoast_not_active();
    }

    /** @var mixed $raw_id */
    $raw_id = $args['post_id'] ?? $args['id'] ?? null;
    $post = yoast_resolve_post($raw_id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    // Validate everything that can be rejected BEFORE writing anything, so a bad
    // value never leaves a partial write behind.
    $invalid = eps_validate($args);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    $error = eps_apply($post->ID, $args);
    if ($error instanceof WP_Error) {
        return $error;
    }

    return [
        'post_id' => $post->ID,
        'seo' => yoast_post_seo($post->ID, []),
    ];
}

/**
 * Pre-flight validation that runs before any field is written. Catches a robots
 * sub-block that would otherwise be rejected by eps_apply_robots() only AFTER
 * the scalar fields were already committed (e.g. a scalar `advanced` that equals
 * a valid token slips the array schema). Returns a WP_Error, or null if clean.
 *
 * @param array<string, mixed> $args
 */
function eps_validate(array $args): ?WP_Error
{
    if (array_key_exists('robots', $args)) {
        $robots_error = eps_validate_robots($args['robots']);
        if ($robots_error instanceof WP_Error) {
            return $robots_error;
        }
    }

    if (array_key_exists('primary_category', $args)) {
        $pc_error = eps_validate_primary_category($args['primary_category']);
        if ($pc_error instanceof WP_Error) {
            return $pc_error;
        }
    }

    return null;
}

/**
 * Validate the robots sub-block without writing. Returns a WP_Error or null.
 */
function eps_validate_robots(mixed $robots): ?WP_Error
{
    if (!is_array($robots)) {
        return new WP_Error('yoast_invalid_robots', 'robots must be an object', ['status' => 400]);
    }

    if (array_key_exists('index', $robots)) {
        $code = is_string($robots['index']) ? yoast_noindex_to_code($robots['index']) : null;
        if ($code === null) {
            return new WP_Error('yoast_invalid_robots', 'robots.index must be default, index, or noindex', [
                'status' => 400,
            ]);
        }
    }

    if (array_key_exists('follow', $robots)) {
        $code = is_string($robots['follow']) ? yoast_follow_to_code($robots['follow']) : null;
        if ($code === null) {
            return new WP_Error('yoast_invalid_robots', 'robots.follow must be follow or nofollow', ['status' => 400]);
        }
    }

    if (array_key_exists('advanced', $robots)) {
        if (!is_array($robots['advanced'])) {
            return new WP_Error('yoast_invalid_robots', 'robots.advanced must be an array of tokens', [
                'status' => 400,
            ]);
        }
        $advanced = yoast_advanced_to_raw($robots['advanced']);
        if ($advanced instanceof WP_Error) {
            return $advanced;
        }
    }

    return null;
}

/**
 * Validate the primary_category term id. null or a non-positive value clears it
 * (allowed); any other value must be an existing term in the 'category'
 * taxonomy, or Yoast's primary-term storage is corrupted with a bogus id.
 */
function eps_validate_primary_category(mixed $value): ?WP_Error
{
    if ($value === null || yoast_int($value) <= 0) {
        return null;
    }
    if (yoast_resolve_term($value, taxonomy: 'category') instanceof WP_Error) {
        return new WP_Error(
            'yoast_invalid_primary_category',
            'primary_category must be an existing category term id (or null/0 to clear)',
            ['status' => 400],
        );
    }
    return null;
}

/**
 * Apply every provided field to the post. Returns a WP_Error on the first
 * invalid value, null on success.
 *
 * @param array<string, mixed> $args
 */
function eps_apply(int $post_id, array $args): ?WP_Error
{
    eps_apply_scalars($post_id, $args);

    if (array_key_exists('cornerstone', $args)) {
        WPSEO_Meta::set_value('is_cornerstone', yoast_meta_bool($args['cornerstone']) ? '1' : '0', $post_id);
    }

    if (array_key_exists('robots', $args) && is_array($args['robots'])) {
        $robots_error = eps_apply_robots($post_id, $args['robots']);
        if ($robots_error instanceof WP_Error) {
            return $robots_error;
        }
    }

    foreach (['opengraph', 'twitter'] as $network) {
        if (!array_key_exists($network, $args) || !is_array($args[$network])) {
            continue;
        }
        eps_apply_social($post_id, $network, $args[$network]);
    }

    if (array_key_exists('schema', $args) && is_array($args['schema'])) {
        eps_apply_schema($post_id, $args['schema']);
    }

    if (array_key_exists('primary_category', $args)) {
        eps_apply_primary_category($post_id, $args['primary_category']);
    }

    return null;
}

/**
 * Write the plain string fields that map one-to-one to a Yoast meta key.
 *
 * @param array<array-key, mixed> $args
 */
function eps_apply_scalars(int $post_id, array $args): void
{
    $map = [
        'focus_keyphrase' => 'focuskw',
        'seo_title' => 'title',
        'meta_description' => 'metadesc',
        'canonical' => 'canonical',
        'breadcrumb_title' => 'bctitle',
        'redirect' => 'redirect',
    ];
    $url_fields = ['canonical', 'redirect'];
    foreach ($map as $friendly => $yoast_key) {
        if (!array_key_exists($friendly, $args) || !is_string($args[$friendly])) {
            continue;
        }
        $value = in_array($friendly, $url_fields, strict: true)
            ? yoast_clean_url($args[$friendly])
            : yoast_clean_text($args[$friendly]);
        WPSEO_Meta::set_value($yoast_key, $value, $post_id);
    }
}

/**
 * Validate and write the robots sub-block. Returns a WP_Error on a bad value.
 *
 * @param array<array-key, mixed> $robots
 */
function eps_apply_robots(int $post_id, array $robots): ?WP_Error
{
    if (array_key_exists('index', $robots)) {
        $code = is_string($robots['index']) ? yoast_noindex_to_code($robots['index']) : null;
        if ($code === null) {
            return new WP_Error('yoast_invalid_robots', 'robots.index must be default, index, or noindex', [
                'status' => 400,
            ]);
        }
        WPSEO_Meta::set_value('meta-robots-noindex', $code, $post_id);
    }

    if (array_key_exists('follow', $robots)) {
        $code = is_string($robots['follow']) ? yoast_follow_to_code($robots['follow']) : null;
        if ($code === null) {
            return new WP_Error('yoast_invalid_robots', 'robots.follow must be follow or nofollow', ['status' => 400]);
        }
        WPSEO_Meta::set_value('meta-robots-nofollow', $code, $post_id);
    }

    if (array_key_exists('advanced', $robots)) {
        if (!is_array($robots['advanced'])) {
            return new WP_Error('yoast_invalid_robots', 'robots.advanced must be an array of tokens', [
                'status' => 400,
            ]);
        }
        $raw = yoast_advanced_to_raw($robots['advanced']);
        if ($raw instanceof WP_Error) {
            return $raw;
        }
        WPSEO_Meta::set_value('meta-robots-adv', $raw, $post_id);
    }

    return null;
}

/**
 * Write a social-preview block (network is 'opengraph' or 'twitter').
 *
 * @param array<array-key, mixed> $block
 */
function eps_apply_social(int $post_id, string $network, array $block): void
{
    foreach (['title', 'description', 'image'] as $field) {
        if (!array_key_exists($field, $block) || !is_string($block[$field])) {
            continue;
        }
        $value = $field === 'image' ? yoast_clean_url($block[$field]) : yoast_clean_text($block[$field]);
        WPSEO_Meta::set_value("{$network}-{$field}", $value, $post_id);
    }
    if (array_key_exists('image_id', $block)) {
        WPSEO_Meta::set_value("{$network}-image-id", (string) max(0, yoast_int($block['image_id'])), $post_id);
    }
}

/**
 * Write the schema page/article types.
 *
 * @param array<array-key, mixed> $schema
 */
function eps_apply_schema(int $post_id, array $schema): void
{
    $map = ['page_type' => 'schema_page_type', 'article_type' => 'schema_article_type'];
    foreach ($map as $friendly => $yoast_key) {
        if (!array_key_exists($friendly, $schema) || !is_string($schema[$friendly])) {
            continue;
        }
        WPSEO_Meta::set_value($yoast_key, yoast_clean_text($schema[$friendly]), $post_id);
    }
}

/**
 * Set or clear the primary category. A null or non-positive value clears it.
 */
function eps_apply_primary_category(int $post_id, mixed $value): void
{
    $id = yoast_int($value);
    if ($id <= 0) {
        delete_post_meta($post_id, meta_key: '_yoast_wpseo_primary_category');
        return;
    }
    update_post_meta($post_id, meta_key: '_yoast_wpseo_primary_category', meta_value: $id);
}
