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
 * Ability: export the active Spectra One design state as a portable JSON
 * snapshot — the `wp_global_styles` user overlay + the theme's own
 * `swt_theme_options` bag + a lightweight `stats` block that summarises
 * what the snapshot contains. Designed as the "rollback point" every
 * write ability's docstring recommends taking before a `set-*` or
 * `apply-style-variation` call — round-trip with `apply-design`.
 *
 * Gap-fill relative to the theme's own surface — Spectra One does not
 * ship an export ability.
 */

wp_register_ability('novamira/spectra-one-export-design', [
    'label' => __('Export Spectra One Design', domain: 'novamira-pro'),
    'description' => __(
        'Returns the active Spectra One design state as a portable JSON snapshot: the wp_global_styles user overlay (palette, typography, styles), the swt_theme_options bag (scroll_top, body/headings settings), the theme identity block (name + version), an ISO 8601 export timestamp, and a `stats` summary (palette_count, patterns_bundled, templates_bundled). Use this as the rollback point before a destructive write — feed the snapshot back through `novamira/spectra-one-apply-design` to restore the pre-write state. Schema version is `1`; future major schema changes will bump this integer. Payload size: `global_styles` + `theme_options` are opaque blobs; typical size is 10–50 KiB but can exceed 100 KiB on installs with an extensive user overlay — stash the snapshot rather than re-fetching it inside a hot path.',
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
            'schema_version' => [
                'type' => 'integer',
                'enum' => [1],
                'description' => 'Snapshot schema version. Future major schema changes will bump this — `apply-design` refuses to load an unknown version.',
            ],
            'exported_at' => [
                'type' => 'string',
                'maxLength' => 40,
                'description' => 'ISO 8601 UTC timestamp of the export.',
            ],
            'theme' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'maxLength' => 60],
                    'version' => ['type' => 'string', 'maxLength' => 40],
                ],
                'required' => ['slug', 'version'],
                'additionalProperties' => false,
            ],
            'global_styles' => [
                'type' => 'object',
                'description' => 'The wp_global_styles user overlay payload (settings + styles + WP_Theme_JSON markers). Empty object when no user overlay exists yet.',
                'additionalProperties' => true,
            ],
            'theme_options' => [
                'type' => 'object',
                'description' => 'The swt_theme_options bag the theme Customizer writes to. Empty object when the option row has never been touched.',
                'additionalProperties' => true,
            ],
            'stats' => [
                'type' => 'object',
                'properties' => [
                    'palette_count' => ['type' => 'integer', 'minimum' => 0],
                    'has_typography' => ['type' => 'boolean'],
                    'has_styles' => ['type' => 'boolean'],
                    'patterns_bundled' => ['type' => 'integer', 'minimum' => 0],
                    'templates_bundled' => ['type' => 'integer', 'minimum' => 0],
                ],
                'required' => [
                    'palette_count',
                    'has_typography',
                    'has_styles',
                    'patterns_bundled',
                    'templates_bundled',
                ],
                'additionalProperties' => false,
            ],
        ],
        'required' => [
            'schema_version',
            'exported_at',
            'theme',
            'global_styles',
            'theme_options',
            'stats',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_export_design',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Take a snapshot BEFORE any destructive write (set-color-palette, set-typography, set-theme-settings, apply-style-variation). Feed the payload back into novamira/spectra-one-apply-design to restore.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     schema_version: int,
 *     exported_at: string,
 *     theme: array{slug: string, version: string},
 *     global_styles: array<string, mixed>|\stdClass,
 *     theme_options: array<string, mixed>|\stdClass,
 *     stats: array{
 *         palette_count: int,
 *         has_typography: bool,
 *         has_styles: bool,
 *         patterns_bundled: int,
 *         templates_bundled: int,
 *     },
 * }|WP_Error
 */
function spectra_one_export_design(): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $context = so_load_global_styles();
    $global_styles = $context !== null ? $context['styles'] : [];

    /** @var mixed $raw_options */
    $raw_options = get_option('swt_theme_options', default_value: []);
    $theme_options = is_array($raw_options) ? so_shape_string_keyed($raw_options) : [];

    $template_dir = get_template_directory();
    $palette = so_normalise_color_entries(so_traverse_array($global_styles, ['settings', 'color', 'palette']));
    /** @var mixed $typography_node */
    $typography_node = so_traverse_array($global_styles, ['settings', 'typography']);
    $has_typography = is_array($typography_node) && $typography_node !== [];
    /** @var mixed $styles_node */
    $styles_node = $global_styles['styles'] ?? null;
    $has_styles = is_array($styles_node) && $styles_node !== [];

    return [
        'schema_version' => 1,
        'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'theme' => [
            'slug' => 'spectra-one',
            'version' => spectra_one_theme_version(),
        ],
        // Empty associative arrays must serialise as `{}`, not `[]`, so
        // strict validators (and the round-trip through apply-design's
        // `type: object` input schema) accept the snapshot on installs
        // with no user overlay / no touched Customizer options.
        'global_styles' => $global_styles === [] ? new \stdClass() : $global_styles,
        'theme_options' => $theme_options === [] ? new \stdClass() : $theme_options,
        'stats' => [
            'palette_count' => count($palette),
            'has_typography' => $has_typography,
            'has_styles' => $has_styles,
            'patterns_bundled' => so_count_glob($template_dir . '/patterns/*.php'),
            'templates_bundled' => so_count_glob($template_dir . '/templates/*.html'),
        ],
    ];
}
