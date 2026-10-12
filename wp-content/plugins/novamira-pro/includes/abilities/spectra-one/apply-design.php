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
 * Ability: apply a Spectra One design snapshot produced by
 * `novamira/spectra-one-export-design` to the current install — restore
 * the palette, typography, style overlay AND the swt_theme_options bag
 * wholesale from a previously-exported payload. `dry_run: true` returns
 * the compatibility analysis without persisting.
 *
 * The snapshot is filtered against a top-level Novamira allowlist before
 * write — unknown top-level keys in `global_styles` and unknown keys in
 * `theme_options` are dropped and reported in `filtered.*`. Sub-shapes
 * inside the kept keys (e.g. individual palette entries under
 * `settings.color.palette`) are preserved as-is; the per-slot set-*
 * writers still guard those sub-shapes on their own call surface.
 *
 * Gap-fill relative to the theme's own surface — Spectra One has no
 * apply-design ability today.
 */

wp_register_ability('novamira/spectra-one-apply-design', [
    'label' => __('Apply Spectra One Design Snapshot', domain: 'novamira-pro'),
    'description' => __(
        'Applies a Spectra One design snapshot produced by novamira/spectra-one-export-design to the current install: replaces the wp_global_styles user overlay AND the swt_theme_options bag with the snapshot payload. Snapshot keys are filtered against a top-level Novamira allowlist — wp_global_styles keeps only the trusted top-level shape (`settings`, `styles`, `version`, `isGlobalStylesUserThemeJSON`); swt_theme_options keeps only the keys the per-slot set-typography / set-theme-settings writers touch. Only top-level keys are validated here; sub-shapes inside the kept keys (e.g. individual palette entries under `settings.color.palette`) pass through as-is. Dropped keys are reported in `filtered.global_styles` / `filtered.theme_options`. `snapshot` accepts the exact object shape export-design returns (schema_version, theme, global_styles, theme_options — stats + exported_at are read for compatibility hints but not required). Payload is refused above 1 MiB to keep the write in-memory-safe on a 128 M PHP limit. `dry_run: true` runs schema validation + theme-version comparison + allowlist filtering without persisting, so the caller can preview whether the snapshot is safe and what will be dropped. Destructive: replaces the two write surfaces wholesale (partial patches are not supported). The write is lock-protected (shared with every other design-surface writer) and flushes both the theme_json + swt_theme_options caches. Error codes: `spectra_one_snapshot_unsupported_version` (schema_version not 1), `spectra_one_invalid_input` (snapshot from a different theme), `spectra_one_no_global_styles` (no user global-styles post exists yet), `spectra_one_snapshot_too_large` (snapshot exceeds the 1 MiB ceiling).',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'snapshot' => [
                'type' => 'object',
                'properties' => [
                    'schema_version' => ['type' => 'integer', 'enum' => [1]],
                    'exported_at' => ['type' => 'string', 'maxLength' => 40],
                    'theme' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'maxLength' => 60],
                            'version' => ['type' => 'string', 'maxLength' => 40],
                        ],
                        'required' => ['slug', 'version'],
                        'additionalProperties' => false,
                    ],
                    'global_styles' => ['type' => 'object', 'additionalProperties' => true],
                    'theme_options' => ['type' => 'object', 'additionalProperties' => true],
                    'stats' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                        'description' => 'Read for compatibility hints only — not required.',
                    ],
                ],
                'required' => ['schema_version', 'theme', 'global_styles', 'theme_options'],
                'additionalProperties' => true,
            ],
            'dry_run' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When true, validate + report compatibility + allowlist filtering without persisting.',
            ],
        ],
        'required' => ['snapshot'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'applied' => ['type' => 'boolean'],
            'schema_version' => ['type' => 'integer', 'enum' => [1]],
            'snapshot_theme_version' => ['type' => 'string', 'maxLength' => 40],
            'current_theme_version' => ['type' => 'string', 'maxLength' => 40],
            'version_match' => [
                'type' => 'boolean',
                'description' => 'True when the snapshot was exported by the same theme version currently running. Apply proceeds regardless — the flag is informational.',
            ],
            'palette_count' => ['type' => 'integer', 'minimum' => 0],
            'has_typography' => ['type' => 'boolean'],
            'has_styles' => ['type' => 'boolean'],
            'global_styles_id' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => '0 on dry_run: true or when no user global_styles post exists yet.',
            ],
            'filtered' => [
                'type' => 'object',
                'properties' => [
                    'global_styles' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Top-level keys of the snapshot `global_styles` block dropped because they fall outside the trusted wp_global_styles shape.',
                    ],
                    'theme_options' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Keys of the snapshot `theme_options` block dropped because they fall outside the swt_theme_options allowlist (the union of keys the per-slot set-typography / set-theme-settings writers touch).',
                    ],
                ],
                'required' => ['global_styles', 'theme_options'],
                'additionalProperties' => false,
            ],
        ],
        'required' => [
            'applied',
            'schema_version',
            'snapshot_theme_version',
            'current_theme_version',
            'version_match',
            'palette_count',
            'has_typography',
            'has_styles',
            'global_styles_id',
            'filtered',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_apply_design',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always run with dry_run: true first to inspect the compatibility report (version_match) + the `filtered` block (keys the allowlist will drop) before committing the write. The apply replaces wp_global_styles + swt_theme_options wholesale.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{snapshot: array<string, mixed>, dry_run?: bool} $input
 * @return array{
 *     applied: bool,
 *     schema_version: int,
 *     snapshot_theme_version: string,
 *     current_theme_version: string,
 *     version_match: bool,
 *     palette_count: int,
 *     has_typography: bool,
 *     has_styles: bool,
 *     global_styles_id: int,
 *     filtered: array{global_styles: list<string>, theme_options: list<string>},
 * }|WP_Error
 */
function spectra_one_apply_design(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $snapshot = $input['snapshot'];
    $dry_run = $input['dry_run'] ?? false;

    // Reject snapshots larger than a conservative ceiling before touching
    // wp_json_encode — a 10 MB snapshot round-tripped through json_encode +
    // wp_update_post would double memory usage and OOM on a 128 M limit
    // (audit round 6, Sprint 6 round 2 Performance probe). Real Spectra One
    // exports run 10–50 KiB; 1 MiB is a 20× headroom over the highest
    // observed real payload.
    $snapshot_bytes = strlen((string) wp_json_encode($snapshot));
    if ($snapshot_bytes > 1_048_576) {
        return new WP_Error(
            'spectra_one_snapshot_too_large',
            sprintf(
                /* translators: 1: snapshot size in bytes, 2: hard ceiling */
                __(
                    'Snapshot is %1$d bytes — larger than the %2$d byte ceiling. Export the design under a fresh theme install and retry with a lean payload.',
                    domain: 'novamira-pro',
                ),
                $snapshot_bytes,
                1_048_576,
            ),
            ['status' => 413],
        );
    }

    $schema_version = so_int_at($snapshot, ['schema_version']);
    if ($schema_version !== 1) {
        return new WP_Error(
            'spectra_one_snapshot_unsupported_version',
            sprintf(
                /* translators: %d: unsupported schema version */
                __('Snapshot schema version %d is not supported by this Novamira Pro release.', domain: 'novamira-pro'),
                $schema_version,
            ),
            ['status' => 400],
        );
    }

    $snapshot_slug = so_string_at($snapshot, ['theme', 'slug']);
    if ($snapshot_slug !== '' && $snapshot_slug !== 'spectra-one') {
        return so_invalid_input(sprintf(
            /* translators: %s: snapshot theme slug */
            __(
                'Snapshot was exported by a different theme (`%s`). Only spectra-one snapshots are supported.',
                domain: 'novamira-pro',
            ),
            $snapshot_slug,
        ));
    }

    $filtered_global = so_filter_snapshot_global_styles(so_shape_string_keyed(so_traverse_array($snapshot, [
        'global_styles',
    ])));
    $filtered_options = so_filter_snapshot_theme_options(so_shape_string_keyed(so_traverse_array($snapshot, [
        'theme_options',
    ])));

    $summary = so_summarise_snapshot($snapshot);
    $filtered_block = [
        'filtered' => [
            'global_styles' => $filtered_global['rejected'],
            'theme_options' => $filtered_options['rejected'],
        ],
    ];

    if ($dry_run) {
        return array_merge($summary, ['applied' => false, 'global_styles_id' => 0], $filtered_block);
    }

    // Every design-surface writer now serialises through so_with_design_lock
    // (global_styles → swt_theme_options), so apply-design contends against
    // set-color-palette / apply-style-variation / set-typography /
    // set-theme-settings under the same lock pair — no more split-brain race
    // between the two write stores (audit round 6 Concurrency probe +
    // Sprint 6 review feedback).
    return so_with_design_lock(static fn(): array|WP_Error => so_apply_snapshot(
        $filtered_global['kept'],
        $filtered_options['kept'],
        $summary,
        $filtered_block,
    ));
}

/**
 * Precompute the counters + version-match block that both dry_run and
 * live paths surface. Kept as a separate helper so the ability
 * signature stays lean and the lock closure never re-scans the payload.
 *
 * @param array<string, mixed> $snapshot
 * @return array{
 *     schema_version: int,
 *     snapshot_theme_version: string,
 *     current_theme_version: string,
 *     version_match: bool,
 *     palette_count: int,
 *     has_typography: bool,
 *     has_styles: bool,
 * }
 */
function so_summarise_snapshot(array $snapshot): array
{
    $snapshot_version = so_string_at($snapshot, ['theme', 'version']);
    $current_version = spectra_one_theme_version();

    $global_styles = so_traverse_array($snapshot, ['global_styles']);
    $palette = so_normalise_color_entries(so_traverse_array($global_styles, ['settings', 'color', 'palette']));
    /** @var mixed $typography_node */
    $typography_node = so_traverse_array($global_styles, ['settings', 'typography']);
    $has_typography = is_array($typography_node) && $typography_node !== [];
    /** @var mixed $styles_node */
    $styles_node = $global_styles['styles'] ?? null;
    $has_styles = is_array($styles_node) && $styles_node !== [];

    return [
        'schema_version' => 1,
        'snapshot_theme_version' => $snapshot_version,
        'current_theme_version' => $current_version,
        'version_match' => $snapshot_version !== '' && $snapshot_version === $current_version,
        'palette_count' => count($palette),
        'has_typography' => $has_typography,
        'has_styles' => $has_styles,
    ];
}

/**
 * Locked write path for `apply-design`. Restores the (already-filtered)
 * wp_global_styles user overlay + the (already-filtered) swt_theme_options
 * bag from the snapshot payload.
 *
 * @param array<string, mixed> $global_styles
 * @param array<string, mixed> $theme_options
 * @param array{
 *     schema_version: int,
 *     snapshot_theme_version: string,
 *     current_theme_version: string,
 *     version_match: bool,
 *     palette_count: int,
 *     has_typography: bool,
 *     has_styles: bool,
 * } $summary
 * @param array{filtered: array{global_styles: list<string>, theme_options: list<string>}} $filtered_block
 * @return array{
 *     applied: bool,
 *     schema_version: int,
 *     snapshot_theme_version: string,
 *     current_theme_version: string,
 *     version_match: bool,
 *     palette_count: int,
 *     has_typography: bool,
 *     has_styles: bool,
 *     global_styles_id: int,
 *     filtered: array{global_styles: list<string>, theme_options: list<string>},
 * }|WP_Error
 */
function so_apply_snapshot(
    array $global_styles,
    array $theme_options,
    array $summary,
    array $filtered_block,
): array|WP_Error {
    $context = so_load_global_styles();
    if ($context === null) {
        return so_no_global_styles();
    }
    $save = so_save_global_styles($context['ID'], $global_styles);
    if ($save instanceof WP_Error) {
        return $save;
    }

    update_option('swt_theme_options', $theme_options, autoload: true);
    so_flush_swt_theme_options_cache();

    return array_merge(
        $summary,
        [
            'applied' => true,
            'global_styles_id' => $context['ID'],
        ],
        $filtered_block,
    );
}
