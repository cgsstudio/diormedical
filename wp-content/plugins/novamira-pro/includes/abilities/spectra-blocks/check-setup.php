<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraBlocks;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-blocks-check-setup', [
    'label' => __('Check Spectra Blocks Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Spectra Blocks runtime state in one call: free plugin active + version + min-supported + min-satisfied; Spectra Blocks Pro add-on state; WordPress and PHP versions; legacy-Spectra presence flags (UAGB and the UAGB spectra-v3 bootstrap that makes Spectra Blocks refuse to initialize); registered block counts (spectra/* and spectra-pro/*); popup counts by status for the spectra-blocks-popup CPT (plus the migration-compat spectra-popup count); and a compact snapshot of key feature toggles. Call this BEFORE any other spectra-blocks ability. The whole spectra-blocks category only registers when the free plugin passed its availability gate — if these abilities are absent from the tool list, that absence itself signals an unavailable or refused install. Composing or writing Spectra block trees goes through the generic `novamira/gutenberg-*` pipeline (gutenberg-add-pending-change → batch finalization), and full block attribute schemas come from `novamira/gutenberg-get-block-type` on base Novamira releases after 1.11.3.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-blocks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plugin' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'pro' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'environment' => [
                'type' => 'object',
                'properties' => [
                    'wp_version' => ['type' => 'string'],
                    'php_version' => ['type' => 'string'],
                ],
                'required' => ['wp_version', 'php_version'],
                'additionalProperties' => false,
            ],
            'legacy' => [
                'type' => 'object',
                'description' => 'Legacy Spectra (UAGB) coexistence flags. spectra_v3_present=true means the UAGB spectra-v3 bootstrap (SPECTRA_3_FILE) is loaded and Spectra Blocks REFUSES to initialize.',
                'properties' => [
                    'spectra_v3_present' => ['type' => 'boolean'],
                    'uagb_present' => ['type' => 'boolean'],
                ],
                'required' => ['spectra_v3_present', 'uagb_present'],
                'additionalProperties' => false,
            ],
            'blocks_registered' => [
                'type' => 'object',
                'properties' => [
                    'free' => ['type' => 'integer'],
                    'pro' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                ],
                'required' => ['free', 'pro', 'total'],
                'additionalProperties' => false,
            ],
            'popups' => [
                'type' => 'object',
                'properties' => [
                    'total' => [
                        'type' => 'integer',
                        'description' => 'spectra-blocks-popup posts across publish/draft/private/pending/future (trash excluded).',
                    ],
                    'by_status' => [
                        'type' => 'object',
                        'description' => 'Fixed status buckets (0 when empty). Trash appears here only — trashed popups are excluded from total and from list-popups.',
                        'properties' => [
                            'publish' => ['type' => 'integer'],
                            'draft' => ['type' => 'integer'],
                            'private' => ['type' => 'integer'],
                            'pending' => ['type' => 'integer'],
                            'future' => ['type' => 'integer'],
                            'trash' => ['type' => 'integer'],
                        ],
                        'required' => ['publish', 'draft', 'private', 'pending', 'future', 'trash'],
                        'additionalProperties' => false,
                    ],
                    'compat_total' => [
                        'type' => 'integer',
                        'description' => 'Posts in the migration-compat spectra-popup CPT (UAGB spectra-v3 beta popups), same status window as total.',
                    ],
                ],
                'required' => ['total', 'by_status', 'compat_total'],
                'additionalProperties' => false,
            ],
            'features' => [
                'type' => 'object',
                'description' => 'Compact snapshot of key spectra_blocks_* feature toggles as stored strings.',
                'properties' => [
                    'templates_button' => ['type' => 'string'],
                    'block_responsive' => ['type' => 'string'],
                    'dynamic_content' => ['type' => 'string'],
                    'animations_extension' => ['type' => 'string'],
                    'gbs_extension' => ['type' => 'string'],
                    'display_conditions' => ['type' => 'string'],
                    'load_gfonts_locally' => ['type' => 'string'],
                    'preload_local_fonts' => ['type' => 'string'],
                    'disable_css_cache' => ['type' => 'string'],
                ],
                'required' => [
                    'templates_button',
                    'block_responsive',
                    'dynamic_content',
                    'animations_extension',
                    'gbs_extension',
                    'display_conditions',
                    'load_gfonts_locally',
                    'preload_local_fonts',
                    'disable_css_cache',
                ],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['plugin', 'pro', 'environment', 'legacy', 'blocks_registered', 'popups', 'features'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraBlocks\\spectra_blocks_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always the first spectra-blocks call. Report the Pro state and the legacy coexistence flags to the user; if this ability is missing from the tool list entirely, the Spectra Blocks surface gate refused (plugin missing, below floor, or blocked by the legacy spectra-v3 conflict).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Feature-toggle keys surfaced in the check-setup snapshot, mapped to the
 * plugin's own storage defaults (`Spectra_Blocks_Admin_Helper` /
 * `ExtensionManager` fallbacks). Output key => [option key suffix, default].
 *
 * @var array<string, array{0:string, 1:string}>
 */
const SPECTRA_BLOCKS_FEATURE_SNAPSHOT_KEYS = [
    'templates_button' => ['enable_templates_button', 'yes'],
    'block_responsive' => ['enable_block_responsive', 'enabled'],
    'dynamic_content' => ['enable_dynamic_content', 'enabled'],
    'animations_extension' => ['enable_animations_extension', 'enabled'],
    'gbs_extension' => ['enable_gbs_extension', 'enabled'],
    'display_conditions' => ['enable_display_conditions', 'enabled'],
    'load_gfonts_locally' => ['load_gfonts_locally', 'disabled'],
    'preload_local_fonts' => ['preload_local_fonts', 'disabled'],
    'disable_css_cache' => ['disable_css_cache', 'disabled'],
];

/**
 * Post statuses that count toward the popup `total` — the same window the
 * list ability defaults to plus scheduled/pending rows, with trash excluded
 * so a bulk-trashed install reads as empty.
 *
 * @var list<string>
 */
const SPECTRA_BLOCKS_POPUP_COUNT_STATUSES = ['publish', 'draft', 'private', 'pending', 'future'];

/**
 * @return array<string, mixed>
 */
function spectra_blocks_check_setup(): array
{
    $blocks = sb_registry_index();
    $free_count = 0;
    $pro_count = 0;
    foreach (array_keys($blocks) as $name) {
        if (sb_block_family($name) === 'pro') {
            $pro_count++;
            continue;
        }
        $free_count++;
    }

    $features = [];
    foreach (SPECTRA_BLOCKS_FEATURE_SNAPSHOT_KEYS as $out_key => [$suffix, $default]) {
        $features[$out_key] = sb_option_to_string(sb_setting($suffix, default_value: $default));
    }

    return [
        'plugin' => [
            'active' => spectra_blocks_version() !== '',
            'version' => spectra_blocks_version(),
            'min_supported' => NOVAMIRA_PRO_SPECTRA_BLOCKS_MIN_VERSION,
            'min_satisfied' => spectra_blocks_plugin_available(),
        ],
        'pro' => [
            'active' => spectra_blocks_pro_version() !== '',
            'version' => spectra_blocks_pro_version(),
            'min_supported' => NOVAMIRA_PRO_SPECTRA_BLOCKS_PRO_MIN_VERSION,
            'min_satisfied' => spectra_blocks_pro_active(),
        ],
        'environment' => [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
        ],
        'legacy' => [
            'spectra_v3_present' => defined('SPECTRA_3_FILE'),
            'uagb_present' => defined('UAGB_VER'),
        ],
        'blocks_registered' => [
            'free' => $free_count,
            'pro' => $pro_count,
            'total' => $free_count + $pro_count,
        ],
        'popups' => [
            'total' => sb_count_popups(SPECTRA_BLOCKS_POPUP_POST_TYPE)['total'],
            'by_status' => sb_count_popups(SPECTRA_BLOCKS_POPUP_POST_TYPE)['by_status'],
            'compat_total' => sb_count_popups(SPECTRA_BLOCKS_COMPAT_POPUP_POST_TYPE)['total'],
        ],
        'features' => $features,
    ];
}

/**
 * Statuses reported in the fixed `by_status` output shape — the countable
 * window plus trash. Trash contributes to its own bucket only, never to
 * `total`, so soft-deleted popups are visible without inflating the live
 * inventory.
 *
 * @var list<string>
 */
const SPECTRA_BLOCKS_POPUP_REPORTED_STATUSES = ['publish', 'draft', 'private', 'pending', 'future', 'trash'];

/**
 * Count posts of a popup CPT, bucketed by status. Returns the all-zero fixed
 * shape when the post type is not registered (e.g. the compat CPT on
 * installs where another plugin already claimed it and then went away).
 * `by_status` always carries the six supported buckets — a closed shape the
 * output schema enumerates — and statuses outside them (auto-draft, inherit)
 * are ignored. No local memoisation: `wp_count_posts` caches its own query
 * per post type, so the second read inside the callback is already free.
 *
 * @return array{total:int, by_status:array<string, int>}
 */
function sb_count_popups(string $post_type): array
{
    $by_status = array_fill_keys(SPECTRA_BLOCKS_POPUP_REPORTED_STATUSES, value: 0);
    $total = 0;
    if (post_type_exists($post_type)) {
        $counts = get_object_vars(wp_count_posts($post_type));
        foreach (SPECTRA_BLOCKS_POPUP_REPORTED_STATUSES as $status) {
            /** @var mixed $value */
            $value = $counts[$status] ?? 0;
            if (!is_numeric($value)) {
                continue;
            }
            $by_status[$status] = (int) $value;
            if (in_array($status, SPECTRA_BLOCKS_POPUP_COUNT_STATUSES, strict: true)) {
                $total += (int) $value;
            }
        }
    }
    return ['total' => $total, 'by_status' => $by_status];
}
