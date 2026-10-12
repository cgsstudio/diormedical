<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-list-google-fonts', [
    'label' => __('List Spectra Google Fonts', domain: 'novamira-pro'),
    'description' => __(
        'Enumerate the Google Fonts Spectra currently loads globally. Merges three UAGB sources: `uag_select_font_globally` (Settings → Global Fonts), `spectra_gbs_google_fonts` (per-Global-Block-Style fonts), and `spectra_global_fse_fonts` (FSE theme.json bridge). Each record reports `source`. Also returns the three delivery-mode flags — `loaded_locally`, `preload_local`, `load_fse_globally` — so an agent can distinguish "not loaded" from "loaded remotely".',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'fonts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'family' => ['type' => 'string'],
                        'source' => [
                            'type' => 'string',
                            'enum' => ['global-settings', 'global-block-style', 'fse'],
                        ],
                        'source_key' => ['type' => 'string'],
                    ],
                    'required' => ['family', 'source', 'source_key'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer'],
            'delivery' => [
                'type' => 'object',
                'properties' => [
                    'loaded_locally' => ['type' => 'boolean'],
                    'preload_local' => ['type' => 'boolean'],
                    'load_fse_globally' => ['type' => 'boolean'],
                ],
                'required' => ['loaded_locally', 'preload_local', 'load_fse_globally'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['fonts', 'total', 'delivery'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_list_google_fonts',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Enumerate every Google Font Spectra loads globally, from all three UAGB sources.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>|WP_Error
 */
function spectra_list_google_fonts(): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $records = [];
    spectra_collect_global_settings_fonts($records);
    spectra_collect_global_block_style_fonts($records);
    spectra_collect_fse_fonts($records);

    return [
        'fonts' => $records,
        'total' => count($records),
        'delivery' => [
            'loaded_locally' =>
                spectra_option_to_string(spectra_admin_settings_option(
                    'uag_load_gfonts_locally',
                    default_value: 'disabled',
                )) === 'enabled',
            'preload_local' =>
                spectra_option_to_string(spectra_admin_settings_option(
                    'uag_preload_local_fonts',
                    default_value: 'disabled',
                )) === 'enabled',
            'load_fse_globally' =>
                spectra_option_to_string(spectra_admin_settings_option(
                    'uag_load_fse_font_globally',
                    default_value: 'disabled',
                )) === 'enabled',
        ],
    ];
}

/**
 * Append records from `uag_select_font_globally` — the "Global Fonts"
 * setting available in Spectra Settings. Stored as a list of family
 * strings when the user has ever touched the setting.
 *
 * @param list<array<string, string>> $records
 */
function spectra_collect_global_settings_fonts(array &$records): void
{
    /** @var mixed $raw */
    $raw = spectra_admin_settings_option('uag_select_font_globally', default_value: []);
    if (!is_array($raw)) {
        return;
    }
    /** @var mixed $family */
    foreach ($raw as $family) {
        if (!is_string($family) || $family === '') {
            continue;
        }
        $records[] = [
            'family' => $family,
            'source' => 'global-settings',
            'source_key' => 'uag_select_font_globally',
        ];
    }
}

/**
 * Append records from `spectra_gbs_google_fonts` — per-Global-Block-Style
 * font arrays. Shape: `{ styleId => [family, ...] }`.
 *
 * @param list<array<string, string>> $records
 */
function spectra_collect_global_block_style_fonts(array &$records): void
{
    /** @var mixed $raw */
    $raw = get_option('spectra_gbs_google_fonts', default_value: []);
    if (!is_array($raw)) {
        return;
    }
    /** @var mixed $families */
    foreach ($raw as $style_id => $families) {
        if (!is_array($families)) {
            continue;
        }
        $style_key = (string) $style_id;
        /** @var mixed $family */
        foreach ($families as $family) {
            if (!is_string($family) || $family === '') {
                continue;
            }
            $records[] = [
                'family' => $family,
                'source' => 'global-block-style',
                'source_key' => 'spectra_gbs_google_fonts:' . $style_key,
            ];
        }
    }
}

/**
 * Append records from `spectra_global_fse_fonts` — FSE theme.json bridge.
 * Stored as a list of family strings by UAGB's FSE compatibility layer.
 *
 * @param list<array<string, string>> $records
 */
function spectra_collect_fse_fonts(array &$records): void
{
    /** @var mixed $raw */
    $raw = get_option('spectra_global_fse_fonts', default_value: []);
    if (!is_array($raw)) {
        return;
    }
    /** @var mixed $family */
    foreach ($raw as $family) {
        if (!is_string($family) || $family === '') {
            continue;
        }
        $records[] = [
            'family' => $family,
            'source' => 'fse',
            'source_key' => 'spectra_global_fse_fonts',
        ];
    }
}
