<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/yoast-get-term-seo', [
    'label' => __('Get Term SEO (Yoast)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Yoast SEO metadata for a single taxonomy term (a category, tag, or custom-taxonomy term) in one friendly shape: focus_keyphrase, seo_title, meta_description, canonical, breadcrumb_title, cornerstone, robots (index: default|index|noindex — terms carry only the indexing toggle, no nofollow or advanced robots), and the opengraph / twitter social previews. Identify the term by `term_id` and its `taxonomy`. Term SEO is stored separately from post SEO (in the wpseo_taxonomy_meta option), which this ability reads for you. Never-set fields come back as their Yoast default.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
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
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_get_term_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a term\'s Yoast SEO before editing it. robots.index uses friendly labels (default|index|noindex); "default" follows the taxonomy indexing setting. Term SEO has no nofollow / advanced-robots / schema fields — only the indexing toggle. A term that has never had SEO set returns Yoast defaults, not missing fields.',
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
function yoast_get_term_seo(array $args): array|WP_Error
{
    if (!yoast_detect_setup()['active']) {
        return yoast_not_active();
    }

    $term = yoast_resolve_term($args['term_id'] ?? null, $args['taxonomy'] ?? null);
    if ($term instanceof WP_Error) {
        return $term;
    }

    return [
        'term_id' => $term->term_id,
        'taxonomy' => $term->taxonomy,
        'seo' => yoast_term_seo($term),
    ];
}
