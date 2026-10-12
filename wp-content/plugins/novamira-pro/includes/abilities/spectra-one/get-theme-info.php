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
 * Ability: portable theme metadata for Spectra One.
 *
 * Novamira wrapper on top of the theme-native `spectra-one/get-theme-info`
 * ability. Same underlying data (theme header + companion-plugin status +
 * bundled-asset counts) reshaped: strict
 * `additionalProperties: false` output, no `Response::success()` envelope,
 * `spectra_one_not_active` guard so the failure shape is stable across
 * the whole `novamira/spectra-one-*` surface.
 */

wp_register_ability('novamira/spectra-one-get-theme-info', [
    'label' => __('Get Spectra One Theme Info', domain: 'novamira-pro'),
    'description' => __(
        'Returns Spectra One theme metadata (name, version, slug, text domain, minimum WP + PHP versions) plus the companion Spectra (UAGB) plugin status and the counts of bundled assets: style variations (JSON files under styles/), block patterns (PHP files under patterns/), FSE templates (HTML files under templates/), and template parts (HTML files under parts/). Use this after check-setup to size the surface before exploring individual entities.',
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
            'name' => ['type' => 'string'],
            'version' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'text_domain' => ['type' => 'string'],
            'requires_wp' => ['type' => 'string'],
            'requires_php' => ['type' => 'string'],
            'spectra_plugin_status' => [
                'type' => 'string',
                'enum' => ['activated', 'installed', 'not-installed'],
            ],
            'style_variations' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of JSON files under styles/. Each is a pre-configured wp_global_styles snapshot that can be applied via novamira/spectra-one-apply-style-variation.',
            ],
            'patterns' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of block-pattern PHP files under patterns/. Enumerable via novamira/spectra-one-list-patterns.',
            ],
            'templates' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of FSE templates under templates/ (page, single, archive, 404, ...).',
            ],
            'template_parts' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of template parts under parts/ (header, footer, sidebar).',
            ],
        ],
        'required' => [
            'name',
            'version',
            'slug',
            'text_domain',
            'requires_wp',
            'requires_php',
            'spectra_plugin_status',
            'style_variations',
            'patterns',
            'templates',
            'template_parts',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_get_theme_info',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact metadata + asset counts, not content. Use this before deep-dive `list-*` / `get-*` calls to decide which surfaces are worth exploring on a specific install. The `spectra_plugin_status` field is duplicated from `check-setup.plugin.status` for one-call convenience.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     name: string,
 *     version: string,
 *     slug: string,
 *     text_domain: string,
 *     requires_wp: string,
 *     requires_php: string,
 *     spectra_plugin_status: string,
 *     style_variations: int,
 *     patterns: int,
 *     templates: int,
 *     template_parts: int,
 * }|WP_Error
 */
function spectra_one_get_theme_info(): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $theme = wp_get_theme(stylesheet: 'spectra-one');
    $template_dir = get_template_directory();

    return [
        'name' => so_theme_header_string($theme, header: 'Name'),
        'version' => so_theme_header_string($theme, header: 'Version'),
        'slug' => 'spectra-one',
        'text_domain' => so_theme_header_string($theme, header: 'TextDomain'),
        'requires_wp' => so_theme_header_string($theme, header: 'RequiresWP'),
        'requires_php' => so_theme_header_string($theme, header: 'RequiresPHP'),
        'spectra_plugin_status' => so_resolve_plugin_status(spectra_plugin_version()),
        'style_variations' => so_count_glob($template_dir . '/styles/*.json'),
        'patterns' => so_count_glob($template_dir . '/patterns/*.php'),
        'templates' => so_count_glob($template_dir . '/templates/*.html'),
        'template_parts' => so_count_glob($template_dir . '/parts/*.html'),
    ];
}
