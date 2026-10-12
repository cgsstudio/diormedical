<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/yoast-get-post-seo', [
    'label' => __('Get Post SEO (Yoast)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Yoast SEO metadata for a single post or page in one friendly shape: focus_keyphrase, seo_title, meta_description, canonical, breadcrumb_title, cornerstone, robots (index: default|index|noindex, follow: follow|nofollow, advanced: list of noimageindex/noarchive/nosnippet), opengraph and twitter social previews, schema types, primary_category, and the Premium redirect. The raw Yoast indexing codes are mapped for you (Yoast stores noindex as 0=default/2=index/1=noindex). Identify the post by `post_id` (alias: `id`). Set `include_scores` to also return the computed read-only SEO / readability / inclusive-language scores. Never-set fields come back as their Yoast default, not as missing.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The post or page id.'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'include_scores' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include the computed read-only SEO / readability / inclusive-language scores.',
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
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_get_post_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a post\'s Yoast SEO before editing it. robots.index uses friendly labels (default|index|noindex) — "default" means "follow the post-type indexing setting", not "indexed". A fresh post returns Yoast defaults (empty strings, index=default, follow), not missing fields. primary_category is the term id of the chosen primary category, or null. redirect is Premium-only.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, id?: int, include_scores?: bool} $args
 * @return array{post_id: int, seo: array<string, mixed>}|WP_Error
 */
function yoast_get_post_seo(array $args): array|WP_Error
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

    // Accept loosely-typed booleans (1, "true", "1") that pass the REST schema
    // but would be dropped by a strict === true check.
    $include_scores = yoast_meta_bool($args['include_scores'] ?? false);

    return [
        'post_id' => $post->ID,
        'seo' => yoast_post_seo($post->ID, ['include_scores' => $include_scores]),
    ];
}
