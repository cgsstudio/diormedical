<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-get-post-seo', [
    'label' => __('Get Post SEO (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the All in One SEO metadata for a single post or page in one friendly shape: focus_keyphrase, additional_keyphrases (read-only here; reflects values stored by AIOSEO\'s own editor or an importer, and no ability writes it), seo_title, meta_description, canonical, pillar_content (cornerstone), robots (index: default|index|noindex, follow: follow|nofollow, advanced: list of noarchive/noimageindex/nosnippet/noodp/notranslate, plus the max_snippet / max_image_preview / max_video_preview SERP-preview limits on an explicit-robots post), facebook (Open Graph) and twitter previews, and schema (the stored default-graph toggle — "enabled" or "off"; use aioseo-get-post-schema for the full picture). AIOSEO gates per-post robots behind a "use defaults" flag: index "default" means the post inherits the global / post-type robots, NOT "indexed" — this ability maps it for you. Identify the post by post_id (alias: id). Never-set fields come back empty / default.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_get_post_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a post\'s AIOSEO metadata before editing. robots.index uses friendly labels (default|index|noindex) — "default" means "inherit the global / post-type setting", not "indexed". focus_keyphrase is a single string. additional_keyphrases is read-only here: it reflects values stored by AIOSEO\'s own editor or an importer, and no ability writes it. A fresh post returns empty / default values, not missing fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, id?: int} $args
 * @return array{post_id: int, seo: array<string, mixed>}|WP_Error
 */
function aioseo_get_post_seo(array $args): array|WP_Error
{
    if (!aio_detect_setup()['active']) {
        return aio_not_active();
    }

    $post = aio_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    return [
        'post_id' => $post->ID,
        'seo' => aio_post_seo($post->ID),
    ];
}
