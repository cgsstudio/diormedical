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
 * Ability: apply a bundled Spectra One style variation to the
 * `wp_global_styles` user overlay — a gap-fill relative to the theme's
 * own surface (the theme ships `spectra-one/list-style-variations` but
 * has no `apply-style-variation`). Applying a variation replaces every
 * top-level `settings.*` sub-tree the variation declares (color,
 * typography, and any other sub-tree the variation JSON carries — the
 * bundled variations ship color + typography), then wholesale-replaces
 * the entire `styles` sub-tree of the overlay when the variation
 * declares one — including block styles the variation does not touch
 * (typography rules on paragraphs, spacing overrides on the button
 * block, etc.). Only overlay keys OUTSIDE `settings.<key>` for the
 * declared keys and OUTSIDE `styles` (WP_Theme_JSON markers, custom
 * top-level keys) survive.
 *
 * `dry_run: true` returns the post-application shape without persisting so
 * an agent can preview before committing. The lock protects concurrent
 * writers; the flush guarantees the next request renders the applied
 * variation.
 */

wp_register_ability('novamira/spectra-one-apply-style-variation', [
    'label' => __('Apply Spectra One Style Variation', domain: 'novamira-pro'),
    'description' => __(
        'Applies a bundled Spectra One style variation (one of `aquamarine`, `dark`, `easter-green`, `mango`, `pink`, `rain-forest`, `sweet-corn`, `ultra-marine` as of 1.2.2) to the wp_global_styles overlay. For every top-level `settings.<key>` the variation declares (the bundled variations ship `color` and `typography`), the corresponding sub-tree of the overlay is replaced wholesale; other `settings.*` sub-trees (spacing, layout, custom tokens) are preserved. When the variation declares a `styles` block, the entire `styles` sub-tree of the overlay is wholesale-replaced by the variation\'s — block-level style rules the variation does not mention are dropped. Take a snapshot with novamira/spectra-one-export-design first if you need a rollback point. `dry_run: true` returns the resulting overlay without persisting so the caller can preview. Discover the writable list via novamira/spectra-one-list-style-variations. Destructive: overwrites the color palette, gradients, duotones, typography presets, and the entire styles block; the write is lock-protected and flushes the theme_json cache. Error codes: `spectra_one_variation_not_found` (unknown slug), `spectra_one_variation_malformed` (bundled JSON is corrupt).',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'variation' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 60,
                'description' => 'Slug of the variation to apply — matches the `slug` field returned by novamira/spectra-one-list-style-variations. Case-sensitive.',
            ],
            'dry_run' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When true, return the resulting overlay without persisting. Defaults to false (actual write).',
            ],
        ],
        'required' => ['variation'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'variation' => ['type' => 'string'],
            'global_styles_id' => ['type' => 'integer', 'minimum' => 1],
            'applied' => [
                'type' => 'boolean',
                'description' => 'True on a live write, false on dry_run.',
            ],
            'palette_count' => ['type' => 'integer', 'minimum' => 0],
            'has_typography' => ['type' => 'boolean'],
            'has_styles' => ['type' => 'boolean'],
        ],
        'required' => [
            'variation',
            'global_styles_id',
            'applied',
            'palette_count',
            'has_typography',
            'has_styles',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_apply_style_variation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Wholesale apply of a bundled variation. Call novamira/spectra-one-export-design (Sprint 5) first if you want a rollback point. dry_run: true is the recommended discovery mode before the first real write.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{variation: string, dry_run?: bool} $input
 * @return array{
 *     variation: string,
 *     global_styles_id: int,
 *     applied: bool,
 *     palette_count: int,
 *     has_typography: bool,
 *     has_styles: bool,
 * }|WP_Error
 */
function spectra_one_apply_style_variation(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $variation = $input['variation'];
    $dry_run = $input['dry_run'] ?? false;

    // Path traversal protection: the slug becomes a filename component, so
    // reject anything that isn't the safe kebab-case shape the theme ships.
    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $variation) !== 1) {
        return so_invalid_input(sprintf(
            /* translators: %s: rejected variation slug */
            __('Invalid variation slug: %s', domain: 'novamira-pro'),
            $variation,
        ));
    }

    $path = get_template_directory() . '/styles/' . $variation . '.json';
    if (!is_readable($path)) {
        return new WP_Error(
            'spectra_one_variation_not_found',
            sprintf(
                /* translators: %s: variation slug */
                __(
                    'Style variation not found: %s. Call novamira/spectra-one-list-style-variations for the bundled slugs.',
                    domain: 'novamira-pro',
                ),
                $variation,
            ),
            ['status' => 404],
        );
    }

    $payload = so_read_json_object($path);
    if ($payload === []) {
        return new WP_Error(
            'spectra_one_variation_malformed',
            sprintf(
                /* translators: %s: variation slug */
                __('Style variation %s has a malformed or unreadable JSON payload.', domain: 'novamira-pro'),
                $variation,
            ),
            ['status' => 500],
        );
    }

    $summary = so_summarise_variation($payload);

    return so_with_design_lock(
        static fn(): array|WP_Error => so_apply_variation_to_overlay($variation, $dry_run, $payload, $summary),
    );
}

/**
 * Precompute the counters + flags an apply-style-variation caller sees in
 * the output. Kept as a separate helper so the ability signature stays
 * lean and the lock closure never re-scans the payload.
 *
 * @param array<string, mixed> $payload
 * @return array{palette_count: int, has_typography: bool, has_styles: bool}
 */
function so_summarise_variation(array $payload): array
{
    /** @var mixed $var_settings */
    $var_settings = $payload['settings'] ?? [];
    /** @var mixed $var_styles */
    $var_styles = $payload['styles'] ?? [];
    $var_settings_arr = is_array($var_settings) ? $var_settings : [];
    $has_typography =
        isset($var_settings_arr['typography'])
        && is_array($var_settings_arr['typography'])
        && $var_settings_arr['typography'] !== [];
    $has_styles = is_array($var_styles) && $var_styles !== [];
    // Normalise through so_normalise_color_entries so a malformed variation
    // that ships palette as a nested `custom` / `theme` / `default` bag
    // (invalid per the theme.json v2 sanitizer) surfaces palette_count: 0
    // instead of the array-key count of the buckets.
    $palette_raw = so_traverse_array($var_settings_arr, ['color', 'palette']);
    $palette = so_normalise_color_entries($palette_raw);
    return [
        'palette_count' => count($palette),
        'has_typography' => $has_typography,
        'has_styles' => $has_styles,
    ];
}

/**
 * Locked read-modify-write for `apply-style-variation`. Extracted from
 * the ability closure to keep the ability under Mago's cyclomatic /
 * Halstead complexity ceilings. `$summary` is the precomputed
 * counters + flags block from `so_summarise_variation`.
 *
 * @param array<string, mixed> $payload
 * @param array{palette_count: int, has_typography: bool, has_styles: bool} $summary
 * @return array{
 *     variation: string,
 *     global_styles_id: int,
 *     applied: bool,
 *     palette_count: int,
 *     has_typography: bool,
 *     has_styles: bool,
 * }|WP_Error
 */
// @mago-expect linter:no-boolean-flag-parameter -- dry_run reflects the ability's dry_run input, not an internal mode switch
function so_apply_variation_to_overlay(string $variation, bool $dry_run, array $payload, array $summary): array|WP_Error
{
    $context = so_load_global_styles();
    if ($context === null) {
        return so_no_global_styles();
    }
    $styles = so_merge_variation_payload($context['styles'], $payload, $summary['has_styles']);

    $response = [
        'variation' => $variation,
        'global_styles_id' => $context['ID'],
        'applied' => !$dry_run,
        'palette_count' => $summary['palette_count'],
        'has_typography' => $summary['has_typography'],
        'has_styles' => $summary['has_styles'],
    ];

    if ($dry_run) {
        return $response;
    }

    $save = so_save_global_styles($context['ID'], $styles);
    if ($save instanceof WP_Error) {
        return $save;
    }
    return $response;
}

/**
 * Overlay a variation payload's `settings` map onto the current overlay
 * settings, then wholesale-replace the `styles` sub-tree when the
 * variation declares one. `$has_styles` is precomputed by
 * `so_summarise_variation` so this helper can stay branchless around
 * the `styles` sub-tree read — the boolean parameter carries the
 * caller's intent, not a mode switch.
 *
 * @param array<string, mixed> $current_overlay
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
// @mago-expect linter:no-boolean-flag-parameter -- has_styles is a precomputed signal, callers never pass a literal
function so_merge_variation_payload(array $current_overlay, array $payload, bool $has_styles): array
{
    /** @var array<string, mixed> $current_settings */
    $current_settings = isset($current_overlay['settings']) && is_array($current_overlay['settings'])
        ? $current_overlay['settings']
        : [];
    /** @var mixed $var_settings_raw */
    $var_settings_raw = $payload['settings'] ?? [];
    if (is_array($var_settings_raw)) {
        /** @var mixed $value */
        foreach ($var_settings_raw as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $current_settings[$key] = $value;
        }
    }
    $current_overlay['settings'] = $current_settings;

    if ($has_styles) {
        $current_overlay['styles'] = $payload['styles'];
    }
    return $current_overlay;
}
