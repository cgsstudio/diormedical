<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-check-setup', [
    'label' => __('Check SeoPress Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the SeoPress environment: whether SeoPress is active, its version, whether the Pro add-on is loaded (and, for info only, whether its license is valid), which Pro-gated surfaces are available (redirects and structured-data/schema need Pro), the enabled-module map (titles, social, XML sitemap, rich snippets, redirections, …), and the public post types and taxonomies whose SEO can be managed. Pro-gated abilities check this. Call this first before any other SeoPress ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        // No inputs. Keep properties an empty ARRAY (not an object): with
        // additionalProperties:false WordPress core's validator indexes
        // properties as an array for every extra key, which fatals on a stdClass.
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => ['type' => 'boolean'],
            'version' => ['type' => ['string', 'null']],
            'pro' => ['type' => 'boolean'],
            'pro_version' => ['type' => ['string', 'null']],
            'licensed' => ['type' => 'boolean'],
            'supports_redirects' => ['type' => 'boolean'],
            'supports_schema' => ['type' => 'boolean'],
            'modules' => ['type' => 'object'],
            'public_post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'public_taxonomies' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => [
            'active',
            'version',
            'pro',
            'pro_version',
            'licensed',
            'supports_redirects',
            'supports_schema',
            'modules',
            'public_post_types',
            'public_taxonomies',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first. (If this ability is missing from your tool list, SeoPress is not active.) pro indicates the Pro add-on is loaded; supports_redirects and supports_schema are Pro-only (the redirect and schema abilities need them). licensed is informational only — Pro features work unlicensed. modules shows which SeoPress modules are enabled; a disabled module still stores meta but SeoPress will not render it. public_post_types / public_taxonomies are the targets for get/edit-post-seo and get/edit-term-seo.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     pro: bool,
 *     pro_version: string|null,
 *     licensed: bool,
 *     supports_redirects: bool,
 *     supports_schema: bool,
 *     modules: array<string, bool>,
 *     public_post_types: list<string>,
 *     public_taxonomies: list<string>,
 * }
 */
function seopress_check_setup(): array
{
    $detect = sp_detect_setup();

    if (!$detect['active']) {
        return [
            'active' => false,
            'version' => null,
            'pro' => false,
            'pro_version' => null,
            'licensed' => false,
            'supports_redirects' => false,
            'supports_schema' => false,
            'modules' => [],
            'public_post_types' => [],
            'public_taxonomies' => [],
        ];
    }

    return [
        'active' => true,
        'version' => $detect['version'],
        'pro' => $detect['pro'],
        'pro_version' => $detect['pro_version'],
        'licensed' => $detect['licensed'],
        // Redirects (seopress_404 CPT) and structured data are Pro features.
        // Track the SAME predicate the redirect abilities register/execute behind
        // (Pro + the seopress_404 CPT), not just "Pro loaded" — so the flag is true
        // iff the redirect abilities are actually callable in this request context.
        'supports_redirects' => sp_redirect_supported(),
        'supports_schema' => $detect['pro'],
        'modules' => [
            'titles' => sp_module_enabled('titles'),
            'social' => sp_module_enabled('social'),
            'xml_sitemap' => sp_module_enabled('xml-sitemap'),
            'rich_snippets' => sp_module_enabled('rich-snippets'),
            'redirections' => sp_module_enabled('404'),
            'google_analytics' => sp_module_enabled('google-analytics'),
            'instant_indexing' => sp_module_enabled('instant-indexing'),
            'local_business' => sp_module_enabled('local-business'),
        ],
        'public_post_types' => sp_public_post_type_names(),
        'public_taxonomies' => sp_public_taxonomy_names(),
    ];
}
