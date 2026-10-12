<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-list-hooks', [
    'label' => __('List Avada Hooks', domain: 'novamira-pro'),
    'description' => __(
        'Curated catalogue of the ~20 most commonly used `fusion_*` and `avada_*` filter hooks the Avada theme exposes for customisation. Each entry reports the hook name, its category (dynamic-css, options-injection, layout, search, related-posts, other), a short description, and the number of parameters the callback receives. This is a static catalogue kept in sync with the theme source — NOT an exhaustive enumeration of every hook.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'enum' => ['all', 'dynamic-css', 'options-injection', 'layout', 'search', 'related-posts', 'other'],
                'default' => 'all',
                'description' => 'Narrow the result to a single hook category.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'hooks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['filter', 'action']],
                        'category' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'params' => ['type' => 'integer'],
                    ],
                    'required' => ['name', 'type', 'category', 'description', 'params'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'hooks'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_list_hooks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Curated catalogue, not exhaustive. Filter by category to narrow the output.',
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
function avada_list_hooks(array $input): array|WP_Error
{
    if (!avada_theme_available()) {
        return av_theme_not_active();
    }

    $requested_category = 'all';
    /** @var list<string> $accepted_categories */
    $accepted_categories = ['all', 'dynamic-css', 'options-injection', 'layout', 'search', 'related-posts', 'other'];
    if (
        isset($input['category'])
        && is_string($input['category'])
        && in_array($input['category'], $accepted_categories, strict: true)
    ) {
        $requested_category = $input['category'];
    }

    $catalogue = avada_hook_catalogue();
    if ($requested_category !== 'all') {
        $catalogue = array_values(array_filter(
            $catalogue,
            static fn(array $hook): bool => $hook['category'] === $requested_category,
        ));
    }

    return [
        'total' => count($catalogue),
        'hooks' => $catalogue,
    ];
}

/**
 * Curated catalogue of the ~20 most-used Avada / Fusion Library filter
 * hooks exposed by the theme (independent of Fusion Builder plugin). Kept
 * static here rather than reading `$wp_filter` because most useful hooks
 * are only defined by the time the callback is invoked, so a runtime
 * enumeration would miss them. Split across two helper arrays so no
 * single function exceeds the halstead maintainability threshold.
 *
 * @return list<array{name: string, type: string, category: string, description: string, params: int}>
 */
function avada_hook_catalogue(): array
{
    return array_merge(avada_hook_catalogue_filters(), avada_hook_catalogue_actions());
}

/**
 * Filter-hook entries in the curated catalogue.
 *
 * @return list<array{name: string, type: string, category: string, description: string, params: int}>
 */
function avada_hook_catalogue_filters(): array
{
    return [
        [
            'name' => 'fusion_admin_options_injection',
            'type' => 'filter',
            'category' => 'options-injection',
            'description' => 'Inject or override tabs in the Fusion Theme Options admin UI.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_google_fonts',
            'type' => 'filter',
            'category' => 'other',
            'description' => 'Modify the list of Google Fonts the theme registers before enqueue.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_privacy_embeds',
            'type' => 'filter',
            'category' => 'other',
            'description' => 'Adjust the third-party embed placeholders the privacy layer swaps in.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_builder_link_icon_target',
            'type' => 'filter',
            'category' => 'other',
            'description' => 'Override the target attribute rule for link icons in builder output.',
            'params' => 2,
        ],
        [
            'name' => 'fusion_builder_post_links_target',
            'type' => 'filter',
            'category' => 'other',
            'description' => 'Override the default target on rendered post links.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_related_posts_args',
            'type' => 'filter',
            'category' => 'related-posts',
            'description' => 'Adjust WP_Query args used for the related-posts block.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_get_plugin_info',
            'type' => 'filter',
            'category' => 'other',
            'description' => 'Modify the bundled-plugin info payload the theme reports through its `Avada::get_bundled_plugins()` accessor.',
            'params' => 1,
        ],
        [
            'name' => 'avada_search_results_post_types',
            'type' => 'filter',
            'category' => 'search',
            'description' => 'Add or remove post types eligible for the theme search-results template.',
            'params' => 1,
        ],
        [
            'name' => 'avada_menu_options',
            'type' => 'filter',
            'category' => 'layout',
            'description' => 'Override menu-rendering options at the theme-menu-args layer.',
            'params' => 2,
        ],
        [
            'name' => 'avada_dynamic_css_array',
            'type' => 'filter',
            'category' => 'dynamic-css',
            'description' => 'Modify the compiled dynamic-CSS declarations array before it is serialised.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_dynamic_css_final',
            'type' => 'filter',
            'category' => 'dynamic-css',
            'description' => 'Modify the final compiled dynamic CSS string before it is written to disk / echoed inline.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_dynamic_css_cache_enabled',
            'type' => 'filter',
            'category' => 'dynamic-css',
            'description' => 'Enable or disable the dynamic-CSS file cache at runtime.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_attr',
            'type' => 'filter',
            'category' => 'layout',
            'description' => 'Modify the attributes on any theme-rendered element identified by its slug key.',
            'params' => 2,
        ],
        [
            'name' => 'fusion_get_typography_font_families',
            'type' => 'filter',
            'category' => 'other',
            'description' => 'Modify the list of font families exposed in the typography picker.',
            'params' => 1,
        ],
        [
            'name' => 'avada_prevent_layout_options_scripts',
            'type' => 'filter',
            'category' => 'layout',
            'description' => 'Short-circuit the layout-options JS enqueue on specific screens.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_settings_defaults',
            'type' => 'filter',
            'category' => 'options-injection',
            'description' => 'Modify the default values Fusion Settings falls back to for unset options.',
            'params' => 1,
        ],
    ];
}

/**
 * Action-hook entries in the curated catalogue.
 *
 * @return list<array{name: string, type: string, category: string, description: string, params: int}>
 */
function avada_hook_catalogue_actions(): array
{
    return [
        [
            'name' => 'fusion_theme_after_setup',
            'type' => 'action',
            'category' => 'other',
            'description' => 'Fires after the theme completes its setup phase.',
            'params' => 0,
        ],
        [
            'name' => 'awb_global_colors_before_save',
            'type' => 'action',
            'category' => 'other',
            'description' => 'Fires immediately before AWB Global Colors persist to the DB.',
            'params' => 1,
        ],
        [
            'name' => 'awb_global_typography_before_save',
            'type' => 'action',
            'category' => 'other',
            'description' => 'Fires immediately before AWB Global Typography persists to the DB.',
            'params' => 1,
        ],
        [
            'name' => 'fusion_cache_clear',
            'type' => 'action',
            'category' => 'other',
            'description' => 'Fires when the Fusion Cache facade resets its internal caches.',
            'params' => 0,
        ],
    ];
}
