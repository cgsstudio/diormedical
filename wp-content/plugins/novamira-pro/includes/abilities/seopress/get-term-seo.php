<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-get-term-seo', [
    'label' => __('Get Term SEO (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the SeoPress metadata for a single taxonomy term (category, tag, or custom taxonomy term) in the same friendly shape as get-post-seo: title, description, robots (noindex, nofollow, nosnippet, noimageindex, canonical, and an effective sub-block resolving the global / per-taxonomy defaults), facebook and twitter social previews, and redirect. Term SEO is a FREE SeoPress feature and reuses the SAME _seopress_* keys as posts, only stored in term meta. The top-level robots flags are the RAW per-term overrides — an empty string / false means "inherit the per-taxonomy or global default"; robots.effective is the resolved directive. Identify the term by term_id AND taxonomy (both required).',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy the term belongs to (e.g. category).'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_get_term_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a term\'s SeoPress metadata before editing. Both term_id and taxonomy are required. The top-level robots flags are the per-term OVERRIDES: empty string / false = "inherit the per-taxonomy / global default"; robots.effective is the resolved directive. Term SEO is free (no Pro needed).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{term_id?: int|string, taxonomy?: string} $args
 * @return array{term_id: int, taxonomy: string, seo: array<string, mixed>}|WP_Error
 */
function seopress_get_term_seo(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }

    $term = sp_resolve_term($args['term_id'] ?? null, $args['taxonomy'] ?? null);
    if ($term instanceof WP_Error) {
        return $term;
    }

    return [
        'term_id' => $term->term_id,
        'taxonomy' => $term->taxonomy,
        'seo' => sp_term_seo($term),
    ];
}
