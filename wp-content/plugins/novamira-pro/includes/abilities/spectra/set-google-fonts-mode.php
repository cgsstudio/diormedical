<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-set-google-fonts-mode', [
    'label' => __('Set Spectra Google Fonts Mode', domain: 'novamira-pro'),
    'description' => __(
        'Toggle Spectra Google Fonts delivery mode. At least one of `mode`, `preload_local`, `load_fse_globally` MUST be provided — omitted keys stay untouched. `local` enables the UAGB local-hosting pipeline (fonts served from wp-content/uploads/spectra/), `remote` disables it so the front end links Google\'s CDN. `preload_local` and `load_fse_globally` are secondary flags exposed only for completeness. Same cache-flush semantics as spectra-set-editor-settings — the front end serves refreshed CSS after the write.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'mode' => [
                'type' => 'string',
                'enum' => ['local', 'remote'],
                'description' => 'Primary switch — `local` serves fonts from the WordPress upload dir, `remote` links Google\'s CDN.',
            ],
            'preload_local' => [
                'type' => 'boolean',
                'description' => 'When true, Spectra emits `<link rel="preload">` for the locally-hosted font files.',
            ],
            'load_fse_globally' => [
                'type' => 'boolean',
                'description' => 'Bridge to the FSE theme.json font-family layer.',
            ],
        ],
        // At least one delivery-mode field must be provided; a bare `{}` payload
        // would return the current snapshot without doing anything, and the
        // shift-left rejection tells the caller their intent was empty.
        'minProperties' => 1,
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
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
            'changed' => ['type' => 'boolean'],
        ],
        'required' => ['delivery', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_set_google_fonts_mode',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Toggle Spectra Google Fonts delivery. Idempotent — reports changed=false when the mode matches the stored value.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_set_google_fonts_mode(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    if ($input === []) {
        return sp_invalid_input('At least one of `mode`, `preload_local`, `load_fse_globally` is required.');
    }

    $changed = false;

    if (isset($input['mode'])) {
        if (!in_array($input['mode'], ['local', 'remote'], strict: true)) {
            return sp_invalid_input('mode must be one of: local, remote');
        }
        $target = $input['mode'] === 'local' ? 'enabled' : 'disabled';
        if (spectra_write_editor_setting('uag_load_gfonts_locally', $target) === 'written') {
            $changed = true;
        }
    }

    if (isset($input['preload_local'])) {
        if (!is_bool($input['preload_local'])) {
            return sp_invalid_input('preload_local must be a boolean');
        }
        $target = $input['preload_local'] ? 'enabled' : 'disabled';
        if (spectra_write_editor_setting('uag_preload_local_fonts', $target) === 'written') {
            $changed = true;
        }
    }

    if (isset($input['load_fse_globally'])) {
        if (!is_bool($input['load_fse_globally'])) {
            return sp_invalid_input('load_fse_globally must be a boolean');
        }
        $target = $input['load_fse_globally'] ? 'enabled' : 'disabled';
        if (spectra_write_editor_setting('uag_load_fse_font_globally', $target) === 'written') {
            $changed = true;
        }
    }

    if ($changed) {
        sp_invalidate_spectra_cache();
    }

    return [
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
        'changed' => $changed,
    ];
}
