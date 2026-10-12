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
 * Ability: enumerate Spectra One style variations (compact, list-shape).
 *
 * Each variation is a JSON file under the theme's `styles/` directory. The
 * bundled set as of 1.2.2 has 8 variations (aquamarine, dark, easter-green,
 * mango, pink, rain-forest, sweet-corn, ultra-marine). This ability returns
 * a compact row per variation (slug + title + palette-summary counters +
 * dominant color guess) — full JSON payloads are NOT part of the current
 * ability surface; fetch a specific variation with an `execute-php` snippet
 * over `so_read_json_object(get_template_directory() . '/styles/' . $slug . '.json')`
 * when needed. Applying a variation live is
 * `novamira/spectra-one-apply-style-variation` (Sprint 3).
 */

wp_register_ability('novamira/spectra-one-list-style-variations', [
    'label' => __('List Spectra One Style Variations', domain: 'novamira-pro'),
    'description' => __(
        'Returns a compact list of bundled Spectra One style variations (pre-configured wp_global_styles snapshots under styles/*.json). Each row: slug, title, source file path (relative to theme root), palette color count, and up-to-three sample palette hexes for a lightweight preview. Progressive disclosure — full JSON is NOT included; for the full payload load the returned `file` path via an `execute-php` snippet. Apply a variation via `novamira/spectra-one-apply-style-variation`.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => new \stdClass(),
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'variations' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'file' => [
                            'type' => 'string',
                            'description' => 'Path relative to the theme root (e.g. `styles/dark.json`).',
                        ],
                        'palette_count' => [
                            'type' => 'integer',
                            'minimum' => 0,
                            'description' => 'Number of color slots declared in `settings.color.palette`.',
                        ],
                        'palette_preview' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Up to 3 sample palette hex values for a lightweight preview.',
                        ],
                    ],
                    'required' => ['slug', 'title', 'file', 'palette_count', 'palette_preview'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer', 'minimum' => 0],
        ],
        'required' => ['variations', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_list_style_variations',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact catalogue. To apply a variation on this install use `novamira/spectra-one-apply-style-variation`. To inspect the full JSON of a single variation before applying, load the returned `file` path via an `execute-php` snippet.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{variations: list<array<string, mixed>>, total: int}|WP_Error
 */
function spectra_one_list_style_variations(): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $template_dir = get_template_directory();
    $files = so_glob_files($template_dir . '/styles/*.json');

    $variations = [];
    foreach ($files as $path) {
        $slug = basename($path, suffix: '.json');
        $data = so_read_json_object($path);

        $title = so_variation_title($data, $slug);
        [$palette_count, $palette_preview] = so_variation_palette_summary($data);

        $variations[] = [
            'slug' => $slug,
            'title' => $title,
            'file' => 'styles/' . basename($path),
            'palette_count' => $palette_count,
            'palette_preview' => $palette_preview,
        ];
    }

    return [
        'variations' => $variations,
        'total' => count($variations),
    ];
}

/**
 * @param array<string, mixed> $data
 */
function so_variation_title(array $data, string $fallback_slug): string
{
    /** @var mixed $title */
    $title = $data['title'] ?? null;
    if (is_string($title) && $title !== '') {
        return $title;
    }
    // Guard against pathological filenames (e.g. `.json`) that would leave
    // `basename($path, '.json')` empty and — after `ucfirst(str_replace(...))`
    // — surface an empty title through a `required` schema field.
    $slug = $fallback_slug !== '' ? $fallback_slug : 'untitled';
    return ucfirst(str_replace(search: '-', replace: ' ', subject: $slug));
}

/**
 * @param array<string, mixed> $data
 * @return array{0: int, 1: list<string>}
 */
function so_variation_palette_summary(array $data): array
{
    /** @var mixed $settings */
    $settings = $data['settings'] ?? [];
    /** @var mixed $color */
    $color = is_array($settings) ? $settings['color'] ?? [] : [];
    /** @var mixed $palette */
    $palette = is_array($color) ? $color['palette'] ?? [] : [];
    if (!is_array($palette)) {
        return [0, []];
    }

    $preview = [];
    /** @var mixed $entry */
    foreach ($palette as $entry) {
        if (count($preview) >= 3) {
            break;
        }
        if (is_array($entry) && isset($entry['color']) && is_string($entry['color']) && $entry['color'] !== '') {
            $preview[] = $entry['color'];
        }
    }

    return [count($palette), $preview];
}
