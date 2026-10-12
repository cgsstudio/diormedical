<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-get-post-seo', [
    'label' => __('Get Post SEO (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the SeoPress metadata for a single post or page in one friendly shape: title and description (the per-post meta-title / meta-description templates, which may contain SeoPress %%dynamic_variables%%), robots (noindex, nofollow, nosnippet, noimageindex as explicit booleans, breadcrumbs_hide which is Pro-written/read-only, canonical URL, and an effective sub-block that resolves the global / per-post-type defaults so a globally-forced directive is not under-reported), target_keywords (the content-analysis keywords as a list), facebook (Open Graph) and twitter social previews (title, description, image, image_id), and redirect (the single-post redirection: enabled, type, value, logged_status). The top-level robots flags are the RAW per-post overrides: an empty string / false means "no override — SeoPress inherits the global or per-post-type default", NOT a stored blank; robots.effective is the resolved directive. Identify the post by post_id (alias: id).',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
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
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_get_post_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a post\'s SeoPress metadata before editing. The top-level robots flags are the per-post OVERRIDES: empty string / false = "inherit the global or per-post-type default", not "off"; noindex=true means a noindex override is set. robots.effective is the resolved directive (e.g. a globally-forced noindex). title/description may contain %%dynamic_variables%% — leave them intact unless changing the pattern.',
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
function seopress_get_post_seo(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }

    $post = sp_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    return [
        'post_id' => $post->ID,
        'seo' => sp_post_seo($post),
    ];
}
