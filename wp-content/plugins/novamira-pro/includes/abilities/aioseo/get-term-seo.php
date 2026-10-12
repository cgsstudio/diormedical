<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-get-term-seo', [
    'label' => __('Get Term SEO (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the All in One SEO metadata for a single taxonomy term (category, tag, or custom-taxonomy term): seo_title, meta_description, canonical, pillar_content, robots (index: default|index|noindex, follow: follow|nofollow, advanced), and the facebook (Open Graph) and twitter previews. Term SEO requires AIOSEO Pro. index "default" means the term inherits the global / taxonomy default, NOT "indexed". Identify the term by term_id + taxonomy.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_get_term_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a term\'s AIOSEO metadata before editing. Requires AIOSEO Pro (returns aioseo_pro_required otherwise). robots.index "default" means "inherit the taxonomy default", not "indexed". Identify the term by term_id + taxonomy.',
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
function aioseo_get_term_seo(array $args): array|WP_Error
{
    $detect = aio_detect_setup();
    if (!$detect['active']) {
        return aio_not_active();
    }
    if (!$detect['pro']) {
        return aio_pro_required('Term SEO');
    }

    $term = aio_resolve_term($args['term_id'] ?? null, $args['taxonomy'] ?? null);
    if ($term instanceof WP_Error) {
        return $term;
    }

    return [
        'term_id' => $term->term_id,
        'taxonomy' => $term->taxonomy,
        'seo' => aio_term_seo($term->term_id),
    ];
}
