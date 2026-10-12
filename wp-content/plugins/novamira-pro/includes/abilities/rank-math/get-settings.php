<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

use RankMath\Helper;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/rank-math-get-settings', [
    'label' => __('Get Rank Math Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads a curated snapshot of the site-wide Rank Math settings. `scope` selects the block(s) (default "all"): "general" = title separator and the knowledge-graph identity (organization or person, name); "titles" = homepage title/description templates plus per-post-type and per-taxonomy title & description templates and the EFFECTIVE default robots (index/follow/advanced) for each, with a custom_robots flag (false = the type inherits the site-wide default, which is what robots then reflects; true = it has its own per-type override). Template strings use Rank Math variables like %title%, %sep%, %sitename%. To CHANGE a setting use novamira/execute-php (read the shared rank-math-options-* option, merge, update_option) — this ability reveals the keys and current values.',
        domain: 'novamira-pro',
    ),
    'category' => 'rank-math',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'scope' => [
                'type' => 'string',
                'enum' => ['all', 'general', 'titles'],
                'default' => 'all',
                'description' => 'Which settings block to return.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'scope' => ['type' => 'string'],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['scope', 'settings'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\RankMath\rank_math_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inspect the site-wide Rank Math configuration. Use scope to stay focused — "titles" can be large with many post types. Per-type robots is the EFFECTIVE default a post inherits when it has no override; custom_robots tells you whether that value is a per-type override (true) or the inherited site-wide default (false). Template strings contain Rank Math variables (%title%, %sep%, %sitename%); leave them intact unless changing the pattern. Writes are not an ability — use execute-php.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * The effective default robots block for a settings path holding a token array.
 * Unlike a per-post read, a site-level default always resolves to a concrete
 * directive: Rank Math appends 'index' whenever no 'noindex' is present
 * (Paper::validate_robots / get_robots_defaults), so we report 'index' rather
 * than the per-post-only 'default' (= inherit) label.
 *
 * @return array{index: string, follow: string, advanced: list<string>}
 */
function rmgs_robots(string $path): array
{
    $block = rm_robots_block(rm_robots_array(Helper::get_settings($path)));
    if ($block['index'] === 'default') {
        $block['index'] = 'index';
    }
    return $block;
}

/**
 * @return array{separator: string, knowledgegraph_type: string, knowledgegraph_name: string}
 */
function rmgs_general(): array
{
    return [
        'separator' => rm_setting_str('titles.title_separator'),
        'knowledgegraph_type' => rm_setting_str('titles.knowledgegraph_type'),
        'knowledgegraph_name' => rm_setting_str('titles.knowledgegraph_name'),
    ];
}

/**
 * Title/description templates + the EFFECTIVE default robots for every public
 * post type. When custom_robots is off, Rank Math ignores the per-type seed and
 * inherits the site-wide default (titles.robots_global), so we report that and
 * expose custom_robots so the value is interpretable.
 *
 * @return array<string, array{title_template: string, description_template: string, custom_robots: bool, robots: array<string, mixed>}>
 */
function rmgs_post_types(): array
{
    $out = [];
    foreach (rm_public_post_type_names() as $name) {
        $custom = rm_bool(Helper::get_settings("titles.pt_{$name}_custom_robots"));
        $out[$name] = [
            'title_template' => rm_setting_str("titles.pt_{$name}_title"),
            'description_template' => rm_setting_str("titles.pt_{$name}_description"),
            'custom_robots' => $custom,
            'robots' => rmgs_robots($custom ? "titles.pt_{$name}_robots" : 'titles.robots_global'),
        ];
    }
    return $out;
}

/**
 * Title/description templates + the EFFECTIVE default robots for every public
 * taxonomy. Same custom_robots gating as post types (titles.tax_{tax}_custom_robots
 * governs whether the per-taxonomy seed applies or the site-wide default inherits).
 *
 * @return array<string, array{title_template: string, description_template: string, custom_robots: bool, robots: array<string, mixed>}>
 */
function rmgs_taxonomies(): array
{
    $out = [];
    foreach (rm_public_taxonomy_names() as $name) {
        $custom = rm_bool(Helper::get_settings("titles.tax_{$name}_custom_robots"));
        $out[$name] = [
            'title_template' => rm_setting_str("titles.tax_{$name}_title"),
            'description_template' => rm_setting_str("titles.tax_{$name}_description"),
            'custom_robots' => $custom,
            'robots' => rmgs_robots($custom ? "titles.tax_{$name}_robots" : 'titles.robots_global'),
        ];
    }
    return $out;
}

/**
 * @return array<string, mixed>
 */
function rmgs_titles(): array
{
    return [
        'homepage' => [
            'title_template' => rm_setting_str('titles.homepage_title'),
            'description_template' => rm_setting_str('titles.homepage_description'),
        ],
        'post_types' => rmgs_post_types(),
        'taxonomies' => rmgs_taxonomies(),
    ];
}

/**
 * @param array{scope?: string} $args
 * @return array{scope: string, settings: array<string, mixed>}|WP_Error
 */
function rank_math_get_settings(array $args): array|WP_Error
{
    if (!rm_detect_setup()['active']) {
        return rm_not_active();
    }

    // Reflect any same-request update_option to the shared rank-math-options-*
    // option (the documented execute-php write path) instead of a stale cache.
    rm_refresh_settings();

    $scope = $args['scope'] ?? 'all';
    if (!in_array($scope, ['all', 'general', 'titles'], strict: true)) {
        return new WP_Error('rank_math_invalid_scope', 'scope must be one of: all, general, titles', ['status' => 400]);
    }

    $settings = [];
    if ($scope === 'all' || $scope === 'general') {
        $settings['general'] = rmgs_general();
    }
    if ($scope === 'all' || $scope === 'titles') {
        $settings['titles'] = rmgs_titles();
    }

    return ['scope' => $scope, 'settings' => $settings];
}
