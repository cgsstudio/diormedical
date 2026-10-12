<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Kadence;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read the WP block-editor theme.json contract Kadence exposes.
 */

wp_register_ability('novamira/kadence-get-theme-json', [
    'label' => __('Get Kadence theme.json', domain: 'novamira-pro'),
    'description' => __(
        'Returns the resolved WP theme.json view of the Kadence theme — the contract the block editor reads to populate its color, typography, spacing, layout, and shadow controls. Unlike kadence-get-settings (which surfaces Kadence-specific Customizer keys), this ability surfaces the WP-native global styles + settings tree that core Gutenberg and any block-aware integration consume. Three slices are returned: `settings` (the per-feature configuration: appearanceTools, color palette, typography, spacing, layout, shadow), `styles` (the resolved styles: per-block, per-element, root spacing), and `presets` (a flat catalogue of the named color / gradient / font-size / spacing presets the agent can reference via slug). Read-only — does not write theme.json. To override theme.json values at the user-style layer, use the wp_update_post path on the active wp_global_styles post (out of scope for this ability).',
        domain: 'novamira-pro',
    ),
    'category' => 'kadence',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'slice' => [
                'type' => 'string',
                'enum' => ['all', 'settings', 'styles', 'presets'],
                'description' => 'Restrict the response to one slice. `all` (default) returns every slice. Use `presets` when you just need the named color / font-size / spacing slugs to reference in block markup.',
                'default' => 'all',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'The WP global_settings map (appearanceTools, color, typography, spacing, layout, shadow, blocks, …). Present unless `slice` was set to a single non-`settings` slice.',
            ],
            'styles' => [
                'type' => 'object',
                'description' => 'The WP global_styles map (root + per-element + per-block styles). Present unless `slice` was set to a single non-`styles` slice.',
            ],
            'presets' => [
                'type' => 'object',
                'description' => 'Flat presets catalogue derived from settings: `colors`, `gradients`, `fontSizes`, `spacingSizes`. Each is a list of `{name, slug, value, origin?}` records the agent can reference in block markup via the slug (e.g. `var(--wp--preset--color--<slug>)`). When the theme.json source was origin-keyed (`default` / `theme` / `custom` map), each record carries an `origin` field so the agent can disambiguate slug collisions; WP\'s render-time cascade is `custom > theme > default`. Present unless `slice` was set to a single non-`presets` slice.',
                'properties' => [
                    'colors' => ['type' => 'array'],
                    'gradients' => ['type' => 'array'],
                    'fontSizes' => ['type' => 'array'],
                    'spacingSizes' => ['type' => 'array'],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Kadence\\kadence_get_theme_json',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this when composing block content that should reuse the theme\'s tokens (palette colors, font sizes, spacing sizes) rather than hardcoded values. The `presets` slice is the most actionable — it lists the slug + value pairs the block markup can reference via WP\'s `var(--wp--preset--*)` CSS variables. The full `styles` slice is heavy; prefer `slice=presets` or `slice=settings` when you do not need the resolved style cascade.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slice?: string} $input
 * @return array<string, mixed>
 */
function kadence_get_theme_json(array $input): array
{
    $slice = $input['slice'] ?? 'all';

    // `wp_get_global_settings/styles()` resolve against whichever theme is
    // currently active, NOT specifically Kadence. The loader gate
    // already screens out the "Kadence inactive" request flow at ability
    // registration time, but a switch_theme() race mid-request could
    // leave this callback running on another theme's data. Guard once
    // more here for consistency with the rest of the surface.
    if (!kadence_theme_available()) {
        return ['settings' => [], 'styles' => [], 'presets' => kd_empty_presets()];
    }

    /** @var array<string, mixed> $settings */
    $settings = wp_get_global_settings();
    /** @var array<string, mixed> $styles */
    $styles = wp_get_global_styles();

    $result = [];
    if ($slice === 'all' || $slice === 'settings') {
        $result['settings'] = $settings;
    }
    if ($slice === 'all' || $slice === 'styles') {
        $result['styles'] = $styles;
    }
    if ($slice === 'all' || $slice === 'presets') {
        $result['presets'] = kd_extract_presets($settings);
    }

    return $result;
}

/**
 * Derives a flat presets catalogue from the global settings tree. WP
 * exposes palette / gradient / font-size / spacing presets nested under
 * `color.palette`, `color.gradients`, `typography.fontSizes`,
 * `spacing.spacingSizes` — each as a list of `{name, slug,
 * (color|gradient|size)}` records. We unify the value field to `value`
 * so the agent can iterate every list uniformly.
 *
 * @param array<string, mixed> $settings
 * @return array{colors: list<array<string, mixed>>, gradients: list<array<string, mixed>>, fontSizes: list<array<string, mixed>>, spacingSizes: list<array<string, mixed>>}
 */
function kd_extract_presets(array $settings): array
{
    return [
        'colors' => kd_normalise_presets($settings, ['color', 'palette'], value_key: 'color'),
        'gradients' => kd_normalise_presets($settings, ['color', 'gradients'], value_key: 'gradient'),
        'fontSizes' => kd_normalise_presets($settings, ['typography', 'fontSizes'], value_key: 'size'),
        'spacingSizes' => kd_normalise_presets($settings, ['spacing', 'spacingSizes'], value_key: 'size'),
    ];
}

/**
 * Empty presets shell returned when the ability is invoked but Kadence
 * isn't the active theme — keeps the response shape consistent so
 * callers can branch on `presets.colors === []` instead of having to
 * handle a missing top-level key.
 *
 * @return array{colors: list<array<string, mixed>>, gradients: list<array<string, mixed>>, fontSizes: list<array<string, mixed>>, spacingSizes: list<array<string, mixed>>}
 */
function kd_empty_presets(): array
{
    return ['colors' => [], 'gradients' => [], 'fontSizes' => [], 'spacingSizes' => []];
}

/**
 * Walks down the settings tree along `$path` and returns the leaf value
 * (mixed). Returns null when any segment is missing or not an array.
 *
 * @param array<string, mixed> $settings
 * @param list<string> $path
 */
function kd_walk_settings_path(array $settings, array $path): mixed
{
    if ($path === []) {
        return $settings;
    }
    $segment = array_shift($path);
    if (!array_key_exists($segment, $settings)) {
        return null;
    }
    /** @var mixed $next */
    $next = $settings[$segment];
    if (!is_array($next)) {
        return $path === [] ? $next : null;
    }
    /** @var array<string, mixed> $next */
    return kd_walk_settings_path($next, $path);
}

/**
 * Walks the settings tree down `$path` and normalises the per-preset
 * records: name + slug copied verbatim, the value pulled from the
 * `$value_key` (color / gradient / size) into a uniform `value` field.
 *
 * @param array<string, mixed> $settings
 * @param list<string> $path
 * @return list<array<string, mixed>>
 */
function kd_normalise_presets(array $settings, array $path, string $value_key): array
{
    /** @var mixed $node */
    $node = kd_walk_settings_path($settings, $path);
    if (!is_array($node)) {
        return [];
    }

    // The theme.json shape allows either a flat list or a `{default, theme,
    // custom}` origin-keyed map. We collect every list we find at any
    // origin so the agent gets a unified view; the origin is tagged on
    // each emitted record when the source was origin-keyed (so the agent
    // can disambiguate slug collisions across origins — WP's cascade is
    // `custom > theme > default`).
    $is_list = kd_array_is_list($node);
    $origin_lists = $is_list ? [$node] : array_values($node);
    $origin_names = $is_list ? [''] : array_keys($node);

    $out = [];
    /** @var mixed $list */
    foreach ($origin_lists as $idx => $list) {
        if (!is_array($list)) {
            continue;
        }
        $origin = is_string($origin_names[$idx] ?? null) ? $origin_names[$idx] : '';
        foreach (kd_records_from_list($list, $value_key, $origin) as $record) {
            $out[] = $record;
        }
    }
    return $out;
}

/**
 * Emits the `{name, slug, value, origin?}` records for a single list of
 * presets (one origin worth, or the whole thing when the source was a
 * flat list). Extracted so the caller stays under the cyclomatic
 * complexity threshold.
 *
 * @param array<array-key, mixed> $list
 * @return list<array<string, mixed>>
 */
function kd_records_from_list(array $list, string $value_key, string $origin): array
{
    $out = [];
    /** @var mixed $entry */
    foreach ($list as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $slug = is_string($entry['slug'] ?? null) ? $entry['slug'] : '';
        if ($slug === '') {
            continue;
        }
        $name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
        $record = ['name' => $name, 'slug' => $slug, 'value' => $entry[$value_key] ?? null];
        if ($origin !== '') {
            $record['origin'] = $origin;
        }
        $out[] = $record;
    }
    return $out;
}
