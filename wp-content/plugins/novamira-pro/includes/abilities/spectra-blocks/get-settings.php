<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraBlocks;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-blocks-get-settings', [
    'label' => __('Get Spectra Blocks Settings', domain: 'novamira-pro'),
    'description' => __(
        'Read the current Spectra Blocks plugin settings (spectra_blocks_* options) as a structured object: editor-experience toggles, extension toggles, font-delivery flags, reCAPTCHA key state, misc flags (CSS cache, content-width source), and a summary of the Global Block Styles store (entry count + block names — never the full style blobs). reCAPTCHA SECRET keys are NEVER echoed; only a `recaptcha.has_secret_v2` / `has_secret_v3` boolean flag is exposed. Whitelist-only — settings outside the map are not read here.',
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
            'editor' => [
                'type' => 'object',
                'description' => 'Editor-experience toggles. Values are stored strings (`enabled` / `disabled` / `yes` / mode names) mirroring the plugin storage shape.',
                'properties' => [
                    'templates_button' => ['type' => 'string'],
                    'on_page_css_button' => ['type' => 'string'],
                    'block_condition' => ['type' => 'string'],
                    'masonry_gallery' => ['type' => 'string'],
                    'quick_action_sidebar' => ['type' => 'string'],
                    'collapse_panels' => ['type' => 'string'],
                    'copy_paste' => ['type' => 'string'],
                    'visibility_mode' => ['type' => 'string'],
                    'blocks_editor_spacing' => ['type' => 'string'],
                    'load_font_awesome_5' => ['type' => 'string'],
                    'auto_block_recovery' => ['type' => 'string'],
                ],
                'required' => [
                    'templates_button',
                    'on_page_css_button',
                    'block_condition',
                    'masonry_gallery',
                    'quick_action_sidebar',
                    'collapse_panels',
                    'copy_paste',
                    'visibility_mode',
                    'blocks_editor_spacing',
                    'load_font_awesome_5',
                    'auto_block_recovery',
                ],
                'additionalProperties' => false,
            ],
            'extensions' => [
                'type' => 'object',
                'description' => 'Block-extension toggles (responsive controls, dynamic content, animations, Global Block Styles, display conditions).',
                'properties' => [
                    'block_responsive' => ['type' => 'string'],
                    'dynamic_content' => ['type' => 'string'],
                    'animations_extension' => ['type' => 'string'],
                    'gbs_extension' => ['type' => 'string'],
                    'display_conditions' => ['type' => 'string'],
                ],
                'required' => [
                    'block_responsive',
                    'dynamic_content',
                    'animations_extension',
                    'gbs_extension',
                    'display_conditions',
                ],
                'additionalProperties' => false,
            ],
            'fonts' => [
                'type' => 'object',
                'properties' => [
                    'load_select_font_globally' => ['type' => 'string'],
                    'load_fse_font_globally' => ['type' => 'string'],
                    'load_gfonts_locally' => ['type' => 'string'],
                    'preload_local_fonts' => ['type' => 'string'],
                    'select_font_globally_count' => [
                        'type' => 'integer',
                        'description' => 'Number of globally-selected font entries (the list itself is editor payload, not echoed).',
                    ],
                ],
                'required' => [
                    'load_select_font_globally',
                    'load_fse_font_globally',
                    'load_gfonts_locally',
                    'preload_local_fonts',
                    'select_font_globally_count',
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
            'misc' => [
                'type' => 'object',
                'properties' => [
                    'disable_css_cache' => ['type' => 'string'],
                    'content_width_set_by' => ['type' => 'string'],
                ],
                'required' => ['disable_css_cache', 'content_width_set_by'],
                'additionalProperties' => false,
            ],
            'global_block_styles' => [
                'type' => 'object',
                'properties' => [
                    'count' => ['type' => 'integer'],
                    'block_names' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Distinct blockName values across the store entries.',
                    ],
                ],
                'required' => ['count', 'block_names'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['editor', 'extensions', 'fonts', 'recaptcha', 'misc', 'global_block_styles'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraBlocks\\spectra_blocks_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only inspection of Spectra Blocks settings. reCAPTCHA secrets are never returned — only presence flags. Global Block Styles come back as a summary, not the style blobs.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Editor-experience toggle keys surfaced in the `editor` bucket, mapped to
 * option-key suffix + storage default. Defaults mirror the plugin's own
 * fallbacks (`Spectra_Blocks_Admin_Helper::get_admin_settings_shareable_data`
 * plus the editor-side keys whitelisted by the plugin's native
 * update-plugin-setting ability).
 *
 * @var array<string, array{0:string, 1:string}>
 */
const SPECTRA_BLOCKS_EDITOR_SETTING_KEYS = [
    'templates_button' => ['enable_templates_button', 'yes'],
    'on_page_css_button' => ['enable_on_page_css_button', 'yes'],
    'block_condition' => ['enable_block_condition', 'disabled'],
    'masonry_gallery' => ['enable_masonry_gallery', 'enabled'],
    'quick_action_sidebar' => ['enable_quick_action_sidebar', 'enabled'],
    'collapse_panels' => ['collapse_panels', 'disabled'],
    'copy_paste' => ['copy_paste', 'enabled'],
    'visibility_mode' => ['visibility_mode', 'disabled'],
    'blocks_editor_spacing' => ['blocks_editor_spacing', '0'],
    'load_font_awesome_5' => ['load_font_awesome_5', 'disabled'],
    'auto_block_recovery' => ['auto_block_recovery', 'enabled'],
];

/**
 * Extension toggle keys surfaced in the `extensions` bucket.
 *
 * @var array<string, array{0:string, 1:string}>
 */
const SPECTRA_BLOCKS_EXTENSION_SETTING_KEYS = [
    'block_responsive' => ['enable_block_responsive', 'enabled'],
    'dynamic_content' => ['enable_dynamic_content', 'enabled'],
    'animations_extension' => ['enable_animations_extension', 'enabled'],
    'gbs_extension' => ['enable_gbs_extension', 'enabled'],
    'display_conditions' => ['enable_display_conditions', 'enabled'],
];

/**
 * @return array<string, mixed>|WP_Error
 */
function spectra_blocks_get_settings(): array|WP_Error
{
    if (!spectra_blocks_plugin_available()) {
        return sb_not_active();
    }

    $editor = [];
    foreach (SPECTRA_BLOCKS_EDITOR_SETTING_KEYS as $out_key => [$suffix, $default]) {
        $editor[$out_key] = sb_option_to_string(sb_setting($suffix, default_value: $default));
    }
    $extensions = [];
    foreach (SPECTRA_BLOCKS_EXTENSION_SETTING_KEYS as $out_key => [$suffix, $default]) {
        $extensions[$out_key] = sb_option_to_string(sb_setting($suffix, default_value: $default));
    }

    /** @var mixed $global_fonts */
    $global_fonts = sb_setting('select_font_globally', default_value: []);

    return [
        'editor' => $editor,
        'extensions' => $extensions,
        'fonts' => [
            'load_select_font_globally' => sb_option_to_string(sb_setting(
                'load_select_font_globally',
                default_value: 'disabled',
            )),
            'load_fse_font_globally' => sb_option_to_string(sb_setting(
                'load_fse_font_globally',
                default_value: 'disabled',
            )),
            'load_gfonts_locally' => sb_option_to_string(sb_setting('load_gfonts_locally', default_value: 'disabled')),
            'preload_local_fonts' => sb_option_to_string(sb_setting('preload_local_fonts', default_value: 'disabled')),
            'select_font_globally_count' => is_array($global_fonts) ? count($global_fonts) : 0,
        ],
        'recaptcha' => [
            'site_key_v2' => sb_option_to_string(sb_setting('recaptcha_site_key_v2', default_value: '')),
            'has_secret_v2' => sb_option_to_string(sb_setting('recaptcha_secret_key_v2', default_value: '')) !== '',
            'site_key_v3' => sb_option_to_string(sb_setting('recaptcha_site_key_v3', default_value: '')),
            'has_secret_v3' => sb_option_to_string(sb_setting('recaptcha_secret_key_v3', default_value: '')) !== '',
        ],
        'misc' => [
            'disable_css_cache' => sb_option_to_string(sb_setting('disable_css_cache', default_value: 'disabled')),
            'content_width_set_by' => sb_option_to_string(sb_setting('content_width_set_by', default_value: 'Spectra')),
        ],
        'global_block_styles' => sb_global_block_styles_summary(),
    ];
}

/**
 * Summarise the `spectra_global_block_styles` option: entry count plus the
 * distinct `blockName` values, never the style payloads themselves — the
 * store carries per-style CSS-in-JS blobs and post-id backrefs that would
 * blow the context budget without helping discovery.
 *
 * @return array{count:int, block_names:list<string>}
 */
function sb_global_block_styles_summary(): array
{
    /** @var mixed $raw */
    $raw = get_option(option: SPECTRA_BLOCKS_GBS_OPTION, default_value: []);
    if (!is_array($raw)) {
        return ['count' => 0, 'block_names' => []];
    }
    $names = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        /** @var mixed $block_name */
        $block_name = $entry['blockName'] ?? null;
        if (is_string($block_name) && $block_name !== '' && !in_array($block_name, $names, strict: true)) {
            $names[] = $block_name;
        }
    }
    return ['count' => count($raw), 'block_names' => $names];
}
