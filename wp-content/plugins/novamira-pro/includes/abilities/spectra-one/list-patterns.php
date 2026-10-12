<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate the block patterns bundled with Spectra One.
 *
 * Gap-fill relative to the theme's own ability surface — Spectra One 1.2.x
 * has NO `spectra-one/list-patterns` ability, so agents currently have to
 * shell out to `execute-php` + `WP_Block_Patterns_Registry` to discover
 * what patterns ship. This ability closes that gap with a paginated,
 * progressive-disclosure catalogue: 85 patterns as of 1.2.2 (headers,
 * footers, hero banners, pricing, contact forms, CTAs, blog layouts,
 * testimonials, features, text blocks, FAQ, sidebars, 404 templates).
 *
 * Pattern headers are parsed directly from the theme's `patterns/*.php`
 * files rather than queried from the registry — this keeps the ability
 * discoverable even on requests where the pattern registry has not yet
 * ingested the files (some early hooks fire before the ingestion pass).
 */

wp_register_ability('novamira/spectra-one-list-patterns', [
    'label' => __('List Spectra One Patterns', domain: 'novamira-pro'),
    'description' => __(
        'Returns the block patterns bundled with the Spectra One theme (85 files under `patterns/*.php` as of 1.2.2). Compact rows — slug, title, categories, keywords, file path (theme-root relative). Content is NOT echoed. `limit` (default 25, max 100) + `offset` paginate; `category` (optional) narrows the response to a single category slug (`spectra-one-headers`, `spectra-one-footers`, `spectra-one-sections`, or any Core / theme category the pattern header declares). Use `novamira/read-file` or a `novamira/execute-php` snippet on the returned `file` path to load the pattern HTML on demand.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 25,
                'description' => 'Maximum number of rows to return. Clamped to [1, 100]. Default 25 for progressive disclosure.',
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
                'description' => 'Number of rows to skip before returning results.',
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Narrow the response to patterns declaring this category slug in their header. Case-sensitive — the value must match the header declaration exactly (Spectra One patterns declare lowercase slugs such as `spectra-one-headers` / `spectra-one-footers` / `spectra-one-sections`). Empty (default) returns every category.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'patterns' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'title' => [
                            'type' => 'string',
                            'maxLength' => 200,
                            'description' => 'Pattern display title (from the file\'s `Title:` header). Capped at 200 chars for progressive disclosure — Spectra One patterns stay well under this ceiling.',
                        ],
                        'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'file' => [
                            'type' => 'string',
                            'description' => 'Path relative to the theme root (e.g. `patterns/hero-banner.php`).',
                        ],
                    ],
                    'required' => ['slug', 'title', 'categories', 'keywords', 'file'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Total number of patterns matching the filters (before pagination).',
            ],
            'returned' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of rows actually returned after limit + offset were applied.',
            ],
        ],
        'required' => ['patterns', 'total', 'returned'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_list_patterns',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Progressive-disclosure catalogue. Content is intentionally NOT echoed — use the returned `file` path with novamira/read-file or an execute-php snippet to inspect a single pattern. Pagination floor is limit=1, ceiling limit=100; caller-supplied values outside the range are clamped rather than rejected.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{limit?: int, offset?: int, category?: string} $input
 * @return array{
 *     patterns: list<array{slug: string, title: string, categories: list<string>, keywords: list<string>, file: string}>,
 *     total: int,
 *     returned: int,
 * }|WP_Error
 */
function spectra_one_list_patterns(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $limit = so_clamp_int($input['limit'] ?? 25, min: 1, max: 100);
    $offset = max(0, (int) ($input['offset'] ?? 0));
    $category_filter = trim($input['category'] ?? '');

    $template_dir = get_template_directory();
    $files = so_glob_files($template_dir . '/patterns/*.php');

    $rows = [];
    foreach ($files as $path) {
        $header = so_read_pattern_header($path);
        if ($header === null) {
            continue;
        }
        if ($category_filter !== '' && !in_array($category_filter, $header['categories'], strict: true)) {
            continue;
        }
        $rows[] = [
            'slug' => $header['slug'],
            'title' => $header['title'],
            'categories' => $header['categories'],
            'keywords' => $header['keywords'],
            'file' => 'patterns/' . basename($path),
        ];
    }

    $total = count($rows);
    $paged = array_slice($rows, $offset, $limit);

    return [
        'patterns' => array_values($paged),
        'total' => $total,
        'returned' => count($paged),
    ];
}
