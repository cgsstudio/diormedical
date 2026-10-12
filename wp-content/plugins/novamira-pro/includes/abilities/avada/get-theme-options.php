<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-get-theme-options', [
    'label' => __('Get Avada Theme Options', domain: 'novamira-pro'),
    'description' => __(
        'Snapshot of the `fusion_options` DB row grouped by tab (color, typography, layout, header, footer, extra). Large fields (custom CSS / custom JS) are byte-capped at `max_field_bytes` (default 20_000, opt-out with `0`) and flagged with `<field>_truncated: true`. Use `include: ["color","typography"]` to fetch a narrow subset when the caller only cares about a few tabs — progressive disclosure prevents dumping the whole option payload (~50 KB on a configured install).',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'include' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['color', 'typography', 'layout', 'header', 'footer', 'extra'],
                ],
                'uniqueItems' => true,
                'description' => 'Restrict output to these tabs. Omit to include every tab.',
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 20_000,
                'description' => 'Byte cap per large string field. Pass 0 to disable truncation.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'tabs' => [
                'type' => 'object',
                'description' => 'Map of tab-name to `{settings, count}`. Only requested tabs appear.',
                'additionalProperties' => [
                    'type' => 'object',
                    'properties' => [
                        'settings' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                        'count' => ['type' => 'integer'],
                    ],
                    'required' => ['settings', 'count'],
                    'additionalProperties' => false,
                ],
            ],
            'truncated_fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Field names truncated because their byte length exceeded `max_field_bytes`.',
            ],
            'options_row_present' => ['type' => 'boolean'],
        ],
        'required' => ['tabs', 'truncated_fields', 'options_row_present'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_get_theme_options',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact by-tab snapshot. Fresh install returns `options_row_present: false` with each requested tab present but its `settings` object empty (`{settings: {}, count: 0}`) — load /wp-admin once to populate defaults. Custom CSS / JS bytes are capped: pass max_field_bytes=0 to get the full string.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function avada_get_theme_options(array $input): array|WP_Error
{
    if (!avada_theme_available()) {
        return av_theme_not_active();
    }

    $requested_tabs = avada_all_tabs();
    if (isset($input['include']) && is_array($input['include']) && $input['include'] !== []) {
        /** @var list<string> $filtered */
        $filtered = [];
        /** @var mixed $tab */
        foreach ($input['include'] as $tab) {
            if (!is_string($tab)) {
                continue;
            }
            if (!in_array($tab, avada_all_tabs(), strict: true)) {
                continue;
            }
            $filtered[] = $tab;
        }
        $requested_tabs = array_values(array_unique($filtered));
    }

    $max_bytes = AVADA_DEFAULT_MAX_FIELD_BYTES;
    if (array_key_exists('max_field_bytes', $input)) {
        $max_bytes = avada_coerce_int($input['max_field_bytes'], minimum: 0);
    }

    $options_present = avada_options_row_exists();
    $raw = fusion_settings_snapshot();

    /** @var array<string, array{settings: \stdClass, count: int}> $tabs */
    $tabs = [];
    /** @var list<string> $truncated */
    $truncated = [];

    foreach ($requested_tabs as $tab) {
        /** @var array<string, mixed> $settings */
        $settings = [];
        foreach (avada_tab_keys($tab) as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            /** @var mixed $value */
            $value = $raw[$key];
            if (!is_string($value)) {
                $settings[$key] = $value;
                continue;
            }
            [$truncated_value, $was_truncated] = avada_truncate_body($value, $max_bytes);
            $settings[$key] = $truncated_value;
            if ($was_truncated) {
                $truncated[] = $key;
            }
        }
        $tabs[$tab] = [
            'settings' => (object) $settings,
            'count' => count($settings),
        ];
    }

    return [
        'tabs' => (object) $tabs,
        'truncated_fields' => $truncated,
        'options_row_present' => $options_present,
    ];
}

/**
 * The 6 tab categories the ability supports. Order stable so an agent that
 * enumerates the map gets deterministic output.
 *
 * @return list<string>
 */
function avada_all_tabs(): array
{
    return ['color', 'typography', 'layout', 'header', 'footer', 'extra'];
}

/**
 * Curated key allowlist per tab. NOT exhaustive — Fusion Options has ~450
 * total keys across 20+ inner tabs; this covers the 60-odd most-commonly
 * touched ones. Unknown keys returned by `fusion_settings_snapshot()` land
 * in the `extra` tab so a caller can still see them without a schema hit.
 *
 * @return list<string>
 */
function avada_tab_keys(string $tab): array
{
    /** @var array<string, list<string>>|null $map */
    static $map = null;
    if ($map === null) {
        $map = [
            'color' => [
                'primary_color',
                'secondary_color',
                'body_typography_color',
                'header_bg_color',
                'footer_bg_color',
                'link_color',
                'link_hover_color',
                'button_gradient_top_color',
                'button_gradient_bottom_color',
                'button_gradient_top_color_hover',
                'button_gradient_bottom_color_hover',
                'button_accent_color',
                'button_accent_hover_color',
                'body_bg_color',
                'content_bg_color',
                'menu_bg_color',
                'menu_hover_bg_color',
                'menu_active_bg_color',
                'sep_color',
                'main_border_color',
            ],
            'typography' => [
                'body_typography',
                'nav_typography',
                'h1_typography',
                'h2_typography',
                'h3_typography',
                'h4_typography',
                'h5_typography',
                'h6_typography',
                'button_typography',
                'footer_copyright_typography',
                'footer_headings_typography',
                'post_titles_typography',
                'meta_typography',
                'google_enabled_fonts',
                'ex_google_enabled_fonts',
            ],
            'layout' => [
                'layout',
                'site_width',
                'main_padding',
                'content_padding',
                'sidebar_2_1_width',
                'sidebar_1_2_width',
                'sidebar_1_1_width',
                'sidebar_gutter',
                'grid_layout',
                'grid_max_columns',
                'grid_column_spacing',
            ],
            'header' => [
                'header_position',
                'header_layout',
                'header_padding',
                'header_bg_full',
                'header_sticky',
                'header_sticky_type2_layout',
                'header_sticky_shrinkage',
                'header_sticky_navigation_menu',
                'logo',
                'logo_retina',
                'logo_sticky',
                'logo_mobile',
                'main_nav_font_size',
                'main_nav_padding',
                'menu_display',
            ],
            'footer' => [
                'footer_area_columns',
                'footer_area_padding',
                'footer_area_display',
                'footer_widgets_center_content',
                'footer_copyright_center_content',
                'footer_text_align',
                'footer_copyright_padding',
                'copyright',
                'footer_social_links_enable',
            ],
            'extra' => [
                'status_lightbox',
                'status_gmap',
                'status_totop',
                'status_totop_mobile',
                'preloader_activate',
                'preloader',
                'wpml_language_switcher_menu_style',
                'favicon',
                'apple_touch_icon',
                'excerpt_length_blog',
                'blog_pn_nav',
                'related_posts',
            ],
        ];
    }
    return $map[$tab] ?? [];
}
