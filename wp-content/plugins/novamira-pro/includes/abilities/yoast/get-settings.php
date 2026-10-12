<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WP_Error;
use WPSEO_Options;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/yoast-get-settings', [
    'label' => __('Get Yoast SEO Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads a curated snapshot of the site-wide Yoast SEO settings. `scope` selects which block(s) to return (default "all"): "general" = brand identity (company or person, names); "titles" = the title separator, homepage title/description templates, per-post-type and per-taxonomy title & meta-description templates and indexing (noindex) toggles, author/date-archive indexing, and whether breadcrumbs are enabled; "social" = OpenGraph/X (Twitter) toggles, default social image, the X card type, and the configured social profile URLs. Template strings use Yoast variables like %%title%%, %%sep%%, %%sitename%%. The separator is returned as Yoast\'s code (e.g. sc-dash). To CHANGE a setting, use novamira/execute-php with WPSEO_Options::set($key, $value, $group); this ability reveals the keys and current values.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        // Top-level default so a null/omitted input (the core REST /run path)
        // normalises to {} and yields the default "all" snapshot instead of an
        // ability_invalid_input error.
        'default' => [],
        'properties' => [
            'scope' => [
                'type' => 'string',
                'enum' => ['all', 'general', 'titles', 'social'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inspect the site-wide Yoast configuration. Use scope to keep the response focused — "titles" can be large on sites with many post types. Per-type noindex true means that type is excluded from search results. Template strings contain Yoast replacement variables (%%title%%, %%sep%%, %%sitename%%); leave them intact unless deliberately changing the pattern. Writes are not an ability: use execute-php with WPSEO_Options::set.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Read a wpseo_titles setting as a string.
 */
function gs_titles_str(string $key): string
{
    return yoast_str(WPSEO_Options::get($key, default_value: '', option_groups: ['wpseo_titles']));
}

/**
 * Read a wpseo_titles setting as a boolean.
 */
function gs_titles_bool(string $key): bool
{
    return yoast_meta_bool(WPSEO_Options::get($key, default_value: false, option_groups: ['wpseo_titles']));
}

/**
 * Read a wpseo_social setting as a string.
 */
function gs_social_str(string $key): string
{
    return yoast_str(WPSEO_Options::get($key, default_value: '', option_groups: ['wpseo_social']));
}

/**
 * Read a wpseo_social setting as a boolean.
 */
function gs_social_bool(string $key): bool
{
    return yoast_meta_bool(WPSEO_Options::get($key, default_value: false, option_groups: ['wpseo_social']));
}

/**
 * Read a wpseo_social setting as an integer (e.g. an attachment id).
 */
function gs_social_int(string $key): int
{
    return yoast_int(WPSEO_Options::get($key, default_value: 0, option_groups: ['wpseo_social']));
}

/**
 * Brand identity block (stored in the wpseo_titles group).
 *
 * @return array{seo_identity: string, company_name: string, person_name: string}
 */
function gs_general(): array
{
    return [
        'seo_identity' => gs_titles_str('company_or_person'),
        'company_name' => gs_titles_str('company_name'),
        'person_name' => gs_titles_str('person_name'),
    ];
}

/**
 * Title & meta templates plus indexing toggles for every public post type.
 *
 * @return array<string, array{title_template: string, metadesc_template: string, noindex: bool}>
 */
function gs_post_type_titles(): array
{
    $out = [];
    foreach (yoast_public_post_type_names() as $name) {
        $out[$name] = [
            'title_template' => gs_titles_str("title-{$name}"),
            'metadesc_template' => gs_titles_str("metadesc-{$name}"),
            'noindex' => gs_titles_bool("noindex-{$name}"),
        ];
    }
    return $out;
}

/**
 * Title & meta templates plus indexing toggles for every public taxonomy.
 *
 * @return array<string, array{title_template: string, metadesc_template: string, noindex: bool}>
 */
function gs_taxonomy_titles(): array
{
    $out = [];
    foreach (yoast_public_taxonomy_names() as $name) {
        $out[$name] = [
            'title_template' => gs_titles_str("title-tax-{$name}"),
            'metadesc_template' => gs_titles_str("metadesc-tax-{$name}"),
            'noindex' => gs_titles_bool("noindex-tax-{$name}"),
        ];
    }
    return $out;
}

/**
 * Title separator, homepage templates, per-type/taxonomy templates, archive
 * indexing, and breadcrumbs state.
 *
 * @return array<string, mixed>
 */
function gs_titles(): array
{
    return [
        'separator' => gs_titles_str('separator'),
        'breadcrumbs_enabled' => gs_titles_bool('breadcrumbs-enable'),
        'homepage' => [
            'title_template' => gs_titles_str('title-home-wpseo'),
            'metadesc_template' => gs_titles_str('metadesc-home-wpseo'),
        ],
        'author_archives_noindex' => gs_titles_bool('noindex-author-wpseo'),
        'date_archives_noindex' => gs_titles_bool('noindex-archive-wpseo'),
        'post_types' => gs_post_type_titles(),
        'taxonomies' => gs_taxonomy_titles(),
    ];
}

/**
 * OpenGraph / X toggles, default social image, card type, and profile URLs.
 *
 * @return array<string, mixed>
 */
function gs_social(): array
{
    return [
        'opengraph_enabled' => gs_social_bool('opengraph'),
        'twitter_enabled' => gs_social_bool('twitter'),
        'twitter_card_type' => gs_social_str('twitter_card_type'),
        'default_image' => gs_social_str('og_default_image'),
        'default_image_id' => gs_social_int('og_default_image_id'),
        'profiles' => [
            'facebook_url' => gs_social_str('facebook_site'),
            'twitter_username' => gs_social_str('twitter_site'),
            'instagram_url' => gs_social_str('instagram_url'),
            'linkedin_url' => gs_social_str('linkedin_url'),
            'youtube_url' => gs_social_str('youtube_url'),
            'pinterest_url' => gs_social_str('pinterest_url'),
        ],
    ];
}

/**
 * @param array{scope?: string} $args
 * @return array{scope: string, settings: array<string, mixed>}|WP_Error
 */
function yoast_get_settings(array $args): array|WP_Error
{
    if (!yoast_detect_setup()['active']) {
        return yoast_not_active();
    }

    $scope = $args['scope'] ?? 'all';
    if (!in_array($scope, ['all', 'general', 'titles', 'social'], strict: true)) {
        return new WP_Error('yoast_invalid_scope', 'scope must be one of: all, general, titles, social', [
            'status' => 400,
        ]);
    }

    $settings = [];
    if ($scope === 'all' || $scope === 'general') {
        $settings['general'] = gs_general();
    }
    if ($scope === 'all' || $scope === 'titles') {
        $settings['titles'] = gs_titles();
    }
    if ($scope === 'all' || $scope === 'social') {
        $settings['social'] = gs_social();
    }

    return ['scope' => $scope, 'settings' => $settings];
}
