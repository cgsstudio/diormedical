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
 * Ability: enumerate Spectra One FSE templates and template parts.
 *
 * Compact list — one row per HTML file under `templates/` (page, single,
 * archive, 404, ...) or `parts/` (header, footer, sidebar). Content is NOT
 * echoed here; it belongs to the base Novamira `read-file` / builder
 * abilities. Optional `type` filter narrows to `templates` or `parts`.
 */

wp_register_ability('novamira/spectra-one-list-templates', [
    'label' => __('List Spectra One Templates', domain: 'novamira-pro'),
    'description' => __(
        'Returns the FSE templates and template parts bundled with Spectra One. Each row: slug (file basename), type (`template` or `part`), area (`header`/`footer`/`sidebar` for parts; empty for templates), and file path relative to the theme root. Optional `type` input narrows the response: `all` (default), `templates`, or `parts`. Content is NOT echoed — the file paths are surfaced so a follow-up read (via the base Novamira `read-file` ability, or an execute-php snippet) can pull the HTML on demand.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'enum' => ['all', 'templates', 'parts'],
                'default' => 'all',
                'description' => 'Filter by kind. `all` (default) returns templates and parts together.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'templates' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['template', 'part']],
                        'area' => [
                            'type' => 'string',
                            'description' => 'Template-part area (`header`, `footer`, `sidebar`) or empty for full templates.',
                        ],
                        'file' => [
                            'type' => 'string',
                            'description' => 'Path relative to the theme root (e.g. `templates/single.html`).',
                        ],
                    ],
                    'required' => ['slug', 'type', 'area', 'file'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer', 'minimum' => 0],
        ],
        'required' => ['templates', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact catalogue. Content is intentionally NOT echoed; use the base Novamira `read-file` ability or an execute-php snippet reading the returned `file` path to load the HTML when a specific template needs inspection.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type?: string} $input
 * @return array{templates: list<array<string, string>>, total: int}|WP_Error
 */
function spectra_one_list_templates(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $kind_filter = $input['type'] ?? 'all';
    if (!in_array($kind_filter, ['all', 'templates', 'parts'], strict: true)) {
        return so_invalid_input(sprintf(
            /* translators: %s: invalid input value */
            __('Unknown template kind filter: %s', domain: 'novamira-pro'),
            $kind_filter,
        ));
    }

    $template_dir = get_template_directory();
    $rows = [];

    if ($kind_filter === 'all' || $kind_filter === 'templates') {
        foreach (so_glob_files($template_dir . '/templates/*.html') as $path) {
            $rows[] = [
                'slug' => basename($path, suffix: '.html'),
                'type' => 'template',
                'area' => '',
                'file' => 'templates/' . basename($path),
            ];
        }
    }

    if ($kind_filter === 'all' || $kind_filter === 'parts') {
        foreach (so_glob_files($template_dir . '/parts/*.html') as $path) {
            $slug = basename($path, suffix: '.html');
            $rows[] = [
                'slug' => $slug,
                'type' => 'part',
                'area' => so_part_area($slug),
                'file' => 'parts/' . basename($path),
            ];
        }
    }

    return [
        'templates' => $rows,
        'total' => count($rows),
    ];
}

/**
 * Best-effort mapping of a template-part slug to a canonical FSE area.
 * Spectra One 1.2.2 ships three parts (`header`, `footer`, `sidebar`);
 * any unknown slug falls back to the empty string so downstream tools
 * can decide their own default.
 */
function so_part_area(string $slug): string
{
    return match ($slug) {
        'header' => 'header',
        'footer' => 'footer',
        'sidebar' => 'sidebar',
        default => '',
    };
}
