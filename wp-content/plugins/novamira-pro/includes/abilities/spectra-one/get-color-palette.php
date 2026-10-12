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
 * Ability: read the active Spectra One color palette (theme.json defaults
 * overlaid with the user's `wp_global_styles` post) plus bundled gradients
 * and duotones. Sprint 2 read wrapper on top of the theme-native
 * `spectra-one/get-color-palette` — same source of truth, tighter output
 * schema, and the extra gradients / duotones surface the theme-native
 * ability does not expose today.
 */

wp_register_ability('novamira/spectra-one-get-color-palette', [
    'label' => __('Get Spectra One Color Palette', domain: 'novamira-pro'),
    'description' => __(
        'Returns the active Spectra One color palette: 14 named color slugs (primary, secondary, heading, body, background, tertiary, quaternary, surface, foreground, outline, neutral, transparent, currentColor, inherit) with their current hex values (user overrides on top of theme.json defaults). Also returns the 1 bundled gradient + 3 duotones the theme ships. `source` reports whether the active palette comes from the user overlay (`user`), the theme.json baseline (`theme`), or a mix of the two (`mixed`). Progressive disclosure: swatches only; write via `novamira/spectra-one-set-color-palette` (Sprint 3).',
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
            'source' => [
                'type' => 'string',
                'enum' => ['user', 'theme', 'mixed'],
                'description' => '`user` when every rendered slot comes from the wp_global_styles overlay; `theme` when the overlay is empty and everything falls back to theme.json; `mixed` when only some slots are overridden.',
            ],
            'colors' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'color' => ['type' => 'string'],
                        'origin' => ['type' => 'string', 'enum' => ['user', 'theme']],
                    ],
                    'required' => ['slug', 'name', 'color', 'origin'],
                    'additionalProperties' => false,
                ],
            ],
            'gradients' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'gradient' => ['type' => 'string'],
                    ],
                    'required' => ['slug', 'name', 'gradient'],
                    'additionalProperties' => false,
                ],
            ],
            'duotones' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'colors' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['slug', 'name', 'colors'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of palette color entries returned.',
            ],
        ],
        'required' => ['source', 'colors', 'gradients', 'duotones', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_get_color_palette',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only snapshot. To change the palette use novamira/spectra-one-set-color-palette (Sprint 3). To apply a bundled variation wholesale use novamira/spectra-one-apply-style-variation (Sprint 3). Slot slugs are the writable allowlist — anything outside this list is rejected by the write path.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     source: string,
 *     colors: list<array{slug: string, name: string, color: string, origin: string}>,
 *     gradients: list<array{slug: string, name: string, gradient: string}>,
 *     duotones: list<array{slug: string, name: string, colors: list<string>}>,
 *     total: int,
 * }|WP_Error
 */
function spectra_one_get_color_palette(): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $theme_json = function_exists('Swt\\get_theme_json') ? \Swt\get_theme_json() : [];
    $db_styles = function_exists('Swt\\get_theme_custom_styles') ? \Swt\get_theme_custom_styles() : [];

    $theme_palette = so_normalise_color_entries(so_traverse_array($theme_json, ['settings', 'color', 'palette']));
    $user_palette = so_normalise_color_entries(so_traverse_array($db_styles, [
        'post_content',
        'settings',
        'color',
        'palette',
    ]));

    // Overlay user slots onto the theme baseline, tagging each entry with its
    // resolved origin so the agent can see which slots the user has actually
    // customised versus which are still riding the theme.json baseline.
    /** @var array<string, array{slug: string, name: string, color: string, origin: string}> $by_slug */
    $by_slug = [];
    foreach ($theme_palette as $entry) {
        $by_slug[$entry['slug']] = [
            'slug' => $entry['slug'],
            'name' => $entry['name'],
            'color' => $entry['color'],
            'origin' => 'theme',
        ];
    }
    foreach ($user_palette as $entry) {
        $by_slug[$entry['slug']] = [
            'slug' => $entry['slug'],
            'name' => $entry['name'],
            'color' => $entry['color'],
            'origin' => 'user',
        ];
    }

    // Preserve the theme.json declaration order so agent output is stable
    // across environments regardless of the DB post's array ordering.
    $ordered = [];
    foreach ($theme_palette as $entry) {
        if (!isset($by_slug[$entry['slug']])) {
            continue;
        }
        $ordered[] = $by_slug[$entry['slug']];
        unset($by_slug[$entry['slug']]);
    }
    // Any slot present only in the user overlay (a slug the theme.json does
    // not declare) is appended at the tail so it stays visible.
    foreach ($by_slug as $entry) {
        $ordered[] = $entry;
    }

    $origins = array_column($ordered, 'origin');
    $unique_origins = array_unique($origins);
    $source = match (true) {
        $unique_origins === ['user'] => 'user',
        in_array('user', $unique_origins, strict: true) => 'mixed',
        default => 'theme',
    };

    return [
        'source' => $source,
        'colors' => $ordered,
        'gradients' => so_normalise_gradients(so_traverse_array($theme_json, ['settings', 'color', 'gradients'])),
        'duotones' => so_normalise_duotones(so_traverse_array($theme_json, ['settings', 'color', 'duotone'])),
        'total' => count($ordered),
    ];
}
