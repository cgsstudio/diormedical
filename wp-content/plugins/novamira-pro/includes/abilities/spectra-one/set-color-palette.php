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
 * Ability: replace one or more slots in the Spectra One color palette by
 * writing to the `wp_global_styles` user overlay. Wrapper on top of the
 * theme-native `spectra-one/update-color-palette`: 14-slot allowlist
 * enforced, explicit slug → color map, no derivation of secondary slots
 * from a primary, stable `WP_Error` codes, lock + cache-flush around the
 * read-modify-write.
 *
 * The theme-native version wants 4 brand hexes and derives supporting
 * slots itself; this wrapper accepts a partial map so the agent can
 * update `{primary: '#..', background: '#..'}` in one call without
 * touching the rest of the palette.
 */

wp_register_ability('novamira/spectra-one-set-color-palette', [
    'label' => __('Set Spectra One Color Palette', domain: 'novamira-pro'),
    'description' => __(
        'Replaces one or more slots in the Spectra One color palette by writing to the wp_global_styles user overlay. Accepts a `colors` list of { slug, color, name? } entries — slug must be one of the 14 allowed palette slugs (primary, secondary, heading, body, background, tertiary, quaternary, surface, foreground, outline, neutral, transparent, currentColor, inherit), color must be a 6-digit or 8-digit hex (`#RRGGBB` or `#RRGGBBAA`), name is optional and defaults to the current name (or a Title-Case of the slug on first-write). Slots absent from the input are left untouched — this is a partial patch, not a wholesale replace of the palette. Destructive: overwrites existing overlay values for the passed slots. The write is lock-protected against parallel writers and flushes the theme_json cache so the next request renders the new palette.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'colors' => [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 14,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => [
                            'type' => 'string',
                            'enum' => [
                                'primary',
                                'secondary',
                                'heading',
                                'body',
                                'background',
                                'tertiary',
                                'quaternary',
                                'surface',
                                'foreground',
                                'outline',
                                'neutral',
                                'transparent',
                                'currentColor',
                                'inherit',
                            ],
                        ],
                        'color' => [
                            'type' => 'string',
                            'pattern' => '^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$',
                            'description' => 'Hex color — 6-digit `#RRGGBB` or 8-digit `#RRGGBBAA` (alpha).',
                        ],
                        'name' => [
                            'type' => 'string',
                            'maxLength' => 60,
                            'description' => 'Optional human-readable slot name. Defaults to the current name (or Title-Cased slug on first write).',
                        ],
                    ],
                    'required' => ['slug', 'color'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['colors'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'global_styles_id' => ['type' => 'integer', 'minimum' => 1],
            'updated' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of slots the write actually mutated (may be less than input length if a slot was already at the requested value).',
            ],
            'palette_count' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Total number of slots in the palette after the write (for a normal 14-slot palette this is 14, plus any user-only slugs that were on the overlay already).',
            ],
            'updated_slots' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'color' => ['type' => 'string'],
                    ],
                    'required' => ['slug', 'name', 'color'],
                    'additionalProperties' => false,
                ],
                'description' => 'ONLY the slots that were actually mutated by this call. Progressive disclosure — call `novamira/spectra-one-get-color-palette` for the full palette snapshot.',
            ],
        ],
        'required' => ['global_styles_id', 'updated', 'palette_count', 'updated_slots'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_set_color_palette',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial patch — only the slugs you pass are written. To read the current palette before deciding call novamira/spectra-one-get-color-palette. To apply a bundled variation wholesale (all 14 slots at once + gradients + typography) use novamira/spectra-one-apply-style-variation instead.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{colors: list<array{slug: string, color: string, name?: string}>} $input
 * @return array{
 *     global_styles_id: int,
 *     updated: int,
 *     palette_count: int,
 *     updated_slots: list<array{slug: string, name: string, color: string}>,
 * }|WP_Error
 */
function spectra_one_set_color_palette(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $colors = $input['colors'];
    // Belt-and-braces validation on the color format — the schema already
    // enforces the pattern, but this guarantees the callback contract even
    // when a Novamira MCP client bypasses schema validation.
    foreach ($colors as $entry) {
        if (!so_is_hex_color($entry['color'])) {
            return so_invalid_input(sprintf(
                /* translators: %s: rejected color value */
                __('Invalid hex color: %s', domain: 'novamira-pro'),
                $entry['color'],
            ));
        }
        if (!so_is_palette_slug($entry['slug'])) {
            return so_unknown_setting($entry['slug']);
        }
    }

    return so_with_design_lock(static fn(): array|WP_Error => so_write_color_palette($colors));
}

/**
 * Locked read-modify-write for `set-color-palette`. Extracted from the
 * ability closure to keep the ability under Mago's cyclomatic / Halstead
 * complexity ceilings.
 *
 * @param list<array{slug: string, color: string, name?: string}> $colors
 * @return array{
 *     global_styles_id: int,
 *     updated: int,
 *     palette_count: int,
 *     updated_slots: list<array{slug: string, name: string, color: string}>,
 * }|WP_Error
 */
function so_write_color_palette(array $colors): array|WP_Error
{
    $context = so_load_global_styles();
    if ($context === null) {
        return so_no_global_styles();
    }
    $styles = $context['styles'];
    $existing_palette = so_traverse_array($styles, ['settings', 'color', 'palette']);
    $current = so_normalise_color_entries($existing_palette);

    /** @var array<string, array{slug: string, name: string, color: string}> $by_slug */
    $by_slug = [];
    foreach ($current as $entry) {
        $by_slug[$entry['slug']] = $entry;
    }
    /** @var list<array{slug: string, name: string, color: string}> $mutated */
    $mutated = [];
    $updated = so_merge_palette_patch($by_slug, $colors, $mutated);

    // theme.json canonical shape is a flat sequential array at
    // settings.color.palette — the sanitizer drops nested `custom` /
    // `theme` / `default` bags.
    $new_palette = array_values($by_slug);
    $styles = so_set_palette_in_styles($styles, $new_palette);

    $save = so_save_global_styles($context['ID'], $styles);
    if ($save instanceof WP_Error) {
        return $save;
    }

    return [
        'global_styles_id' => $context['ID'],
        'updated' => $updated,
        'palette_count' => count($new_palette),
        'updated_slots' => $mutated,
    ];
}

/**
 * Apply the caller-supplied palette patch onto `$by_slug` in place.
 * Returns the number of slots that actually changed value AND accumulates
 * the resolved rows for the slots that changed into `$mutated` — the
 * "updated_slots" progressive-disclosure output.
 *
 * @param array<string, array{slug: string, name: string, color: string}> $by_slug
 * @param list<array{slug: string, color: string, name?: string}> $colors
 * @param list<array{slug: string, name: string, color: string}> $mutated
 */
function so_merge_palette_patch(array &$by_slug, array $colors, array &$mutated): int
{
    $updated = 0;
    foreach ($colors as $entry) {
        $slug = $entry['slug'];
        $color = $entry['color'];
        $name = $entry['name'] ?? $by_slug[$slug]['name'] ?? ucfirst($slug);
        $prior = $by_slug[$slug]['color'] ?? null;
        $row = ['slug' => $slug, 'name' => $name, 'color' => $color];
        $by_slug[$slug] = $row;
        if ($prior !== $color) {
            $updated++;
            $mutated[] = $row;
        }
    }
    return $updated;
}

/**
 * Nest `$new_palette` into `$styles['settings']['color']['palette']`,
 * creating the intermediate arrays when the overlay hasn't seen a color
 * write before.
 *
 * @param array<string, mixed> $styles
 * @param list<array{slug: string, name: string, color: string}> $new_palette
 * @return array<string, mixed>
 */
function so_set_palette_in_styles(array $styles, array $new_palette): array
{
    if (!isset($styles['settings']) || !is_array($styles['settings'])) {
        $styles['settings'] = [];
    }
    /** @var array<string, mixed> $settings */
    $settings = $styles['settings'];
    if (!isset($settings['color']) || !is_array($settings['color'])) {
        $settings['color'] = [];
    }
    /** @var array<string, mixed> $color_settings */
    $color_settings = $settings['color'];
    $color_settings['palette'] = $new_palette;
    $settings['color'] = $color_settings;
    $styles['settings'] = $settings;
    return $styles;
}
