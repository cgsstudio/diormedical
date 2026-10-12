<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/rank-math-get-term-seo', [
    'label' => __('Get Term SEO (Rank Math)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Rank Math SEO metadata for a single taxonomy term (category, tag, or custom-taxonomy term) in one friendly shape: focus_keywords, seo_title, meta_description, canonical, robots (index: default|index|noindex, follow: follow|nofollow, advanced), and the facebook / twitter social previews. Identify the term by term_id and taxonomy. Rank Math stores term SEO as ordinary term meta, which this ability reads and maps for you. Never-set fields come back empty / default.',
        domain: 'novamira-pro',
    ),
    'category' => 'rank-math',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer', 'description' => 'The taxonomy term id.'],
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy slug, e.g. category or post_tag.'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\RankMath\rank_math_get_term_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a term\'s Rank Math SEO before editing. robots.index uses friendly labels (default|index|noindex); "default" inherits the taxonomy setting. A term with no SEO set returns empty/default values, not missing fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{term_id?: int, taxonomy?: string} $args
 * @return array{term_id: int, taxonomy: string, seo: array<string, mixed>}|WP_Error
 */
function rank_math_get_term_seo(array $args): array|WP_Error
{
    if (!rm_detect_setup()['active']) {
        return rm_not_active();
    }

    $term = rm_resolve_term($args['term_id'] ?? null, $args['taxonomy'] ?? null);
    if ($term instanceof WP_Error) {
        return $term;
    }

    return [
        'term_id' => $term->term_id,
        'taxonomy' => $term->taxonomy,
        'seo' => rm_term_seo($term),
    ];
}
