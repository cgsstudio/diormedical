<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-get-settings', [
    'label' => __('Get Spectra Settings', domain: 'novamira-pro'),
    'description' => __(
        'Read the current Spectra plugin settings — editor UX toggles (`uag_enable_*`), container defaults, Google Fonts hosting mode, and reCAPTCHA site-key presence. Values are returned as a flat map. reCAPTCHA SECRET keys are NEVER echoed; only a `recaptcha.has_secret_v2` / `has_secret_v3` boolean flag is exposed. Whitelist-only — settings outside the map are not read here (use novamira/execute-php for one-off reads of niche keys).',
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
            'editor_toggles' => [
                'type' => 'object',
                'description' => 'Editor UX toggles (`uag_enable_*`). Values are `enabled` / `disabled` strings mirroring the UAGB storage shape.',
            ],
            'container_defaults' => [
                'type' => 'object',
                'description' => 'Container padding, block gap, and button-inherit defaults.',
            ],
            'google_fonts' => [
                'type' => 'object',
                'properties' => [
                    'load_locally' => ['type' => 'string'],
                    'preload_local' => ['type' => 'string'],
                    'load_fse_globally' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'recaptcha' => [
                'type' => 'object',
                'properties' => [
                    'site_key_v2' => ['type' => 'string'],
                    'has_secret_v2' => ['type' => 'boolean'],
                    'site_key_v3' => ['type' => 'string'],
                    'has_secret_v3' => ['type' => 'boolean'],
                ],
                'required' => ['site_key_v2', 'has_secret_v2', 'site_key_v3', 'has_secret_v3'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['editor_toggles', 'container_defaults', 'google_fonts', 'recaptcha'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only inspection of Spectra plugin settings. reCAPTCHA secrets are never returned — only presence flags.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Whitelist of `uag_enable_*` editor toggles surfaced by `get-settings` and
 * mutable via `set-editor-settings` (in Sprint 2). Kept as a module const so
 * the two abilities share one authoritative list — no drift between the
 * reader and the writer's allowlist.
 *
 * @var list<string>
 */
const SPECTRA_EDITOR_TOGGLE_KEYS = [
    'uag_enable_block_condition',
    'uag_enable_block_responsive',
    'uag_enable_masonry_gallery',
    'uag_enable_animations_extension',
    'uag_enable_gbs_extension',
    'uag_enable_dynamic_content',
    'uag_enable_quick_action_sidebar',
    'uag_enable_templates_button',
    'uag_enable_on_page_css_button',
    'uag_collapse_panels',
    'uag_copy_paste',
    'uag_visibility_mode',
    'uag_dynamic_content_mode',
];

/**
 * Container default keys mirrored to output.
 *
 * @var list<string>
 */
const SPECTRA_CONTAINER_DEFAULT_KEYS = [
    'uag_container_global_padding',
    'uag_container_global_elements_gap',
    'uag_blocks_editor_spacing',
    'uag_btn_inherit_from_theme',
];

/**
 * @return array<string, mixed>|WP_Error
 */
function spectra_get_settings(): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $editor_toggles = [];
    foreach (SPECTRA_EDITOR_TOGGLE_KEYS as $key) {
        $editor_toggles[$key] = spectra_option_to_string(spectra_admin_settings_option(
            $key,
            default_value: 'disabled',
        ));
    }

    $container_defaults = [];
    foreach (SPECTRA_CONTAINER_DEFAULT_KEYS as $key) {
        $container_defaults[$key] = spectra_option_to_string(spectra_admin_settings_option($key, default_value: ''));
    }

    return [
        'editor_toggles' => $editor_toggles,
        'container_defaults' => $container_defaults,
        'google_fonts' => [
            'load_locally' => spectra_option_to_string(spectra_admin_settings_option(
                'uag_load_gfonts_locally',
                default_value: 'disabled',
            )),
            'preload_local' => spectra_option_to_string(spectra_admin_settings_option(
                'uag_preload_local_fonts',
                default_value: 'disabled',
            )),
            'load_fse_globally' => spectra_option_to_string(spectra_admin_settings_option(
                'uag_load_fse_font_globally',
                default_value: 'disabled',
            )),
        ],
        'recaptcha' => [
            'site_key_v2' => spectra_option_to_string(get_option('uag_recaptcha_site_key_v2', default_value: '')),
            'has_secret_v2' =>
                spectra_option_to_string(get_option('uag_recaptcha_secret_key_v2', default_value: '')) !== '',
            'site_key_v3' => spectra_option_to_string(get_option('uag_recaptcha_site_key_v3', default_value: '')),
            'has_secret_v3' =>
                spectra_option_to_string(get_option('uag_recaptcha_secret_key_v3', default_value: '')) !== '',
        ],
    ];
}

/**
 * Coerce a mixed option value to its canonical string form so the output
 * shape is stable regardless of how UAGB serialised the storage (native
 * strings, JSON blobs, cast booleans).
 */
function spectra_option_to_string(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }
    if (is_bool($value)) {
        return $value ? 'enabled' : 'disabled';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return '';
}
