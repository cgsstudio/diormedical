<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/rank-math-get-post-seo', [
    'label' => __('Get Post SEO (Rank Math)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Rank Math SEO metadata for a single post or page in one friendly shape: focus_keywords (list), seo_title, meta_description, canonical, pillar_content (cornerstone), robots (index: default|index|noindex, follow: follow|nofollow, advanced: list of noarchive/noimageindex/nosnippet), facebook and twitter social previews, primary_category, and schema_type. Rank Math stores robots as a token array where index/noindex are mutually exclusive and "default" means inherit the post-type setting — this ability maps it for you. Identify the post by post_id (alias: id). Set include_score to also return the computed read-only SEO score. Never-set fields come back empty / default.',
        domain: 'novamira-pro',
    ),
    'category' => 'rank-math',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The post or page id.'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'include_score' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include the computed read-only SEO score.',
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
    'execute_callback' => 'Novamira\Pro\Abilities\RankMath\rank_math_get_post_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a post\'s Rank Math SEO before editing. robots.index uses friendly labels (default|index|noindex) — "default" means "inherit the post-type setting", not "indexed". focus_keywords is a list (Rank Math stores them comma-separated). schema_type is the rich-snippet type (manage the full schema via the schema abilities). A fresh post returns empty/default values, not missing fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, id?: int, include_score?: bool} $args
 * @return array{post_id: int, seo: array<string, mixed>}|WP_Error
 */
function rank_math_get_post_seo(array $args): array|WP_Error
{
    if (!rm_detect_setup()['active']) {
        return rm_not_active();
    }

    $post = rm_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    return [
        'post_id' => $post->ID,
        // rm_bool, not === true: core's validate-only REST pipeline lets a
        // boolean-schema arg through as 1 / "1" / "true" (which an MCP client may
        // send), and a strict === would silently drop the requested score.
        'seo' => rm_post_seo($post->ID, ['include_score' => rm_bool($args['include_score'] ?? false)]),
    ];
}
