<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WPSEO_Options;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/yoast-check-setup', [
    'label' => __('Check Yoast SEO Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Yoast SEO environment: whether Yoast is active, edition (free or premium), version, whether redirects are available (Premium only), whether the XML sitemap is enabled, the configured brand identity (company or person), and the public post types and taxonomies whose SEO can be managed. Call this first before any other Yoast ability to confirm the runtime is ready and to learn which post types and taxonomies exist as targets.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => ['type' => 'boolean'],
            'edition' => ['type' => ['string', 'null'], 'enum' => ['free', 'premium', null]],
            'version' => ['type' => ['string', 'null']],
            'supports_redirects' => ['type' => 'boolean'],
            'xml_sitemap_enabled' => ['type' => 'boolean'],
            'seo_identity' => ['type' => 'string'],
            'public_post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'public_taxonomies' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => [
            'active',
            'edition',
            'version',
            'supports_redirects',
            'xml_sitemap_enabled',
            'seo_identity',
            'public_post_types',
            'public_taxonomies',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first before any other Yoast ability. (Note: if this ability is missing from your tool list, Yoast SEO is not active — there is nothing for the agent to do.) Reports edition (free/premium — redirects need Premium), version, whether the XML sitemap is on, the brand identity, and the public post types / taxonomies that can be targeted by get-post-seo / get-term-seo and configured via get-settings.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     edition: 'free'|'premium'|null,
 *     version: string|null,
 *     supports_redirects: bool,
 *     xml_sitemap_enabled: bool,
 *     seo_identity: string,
 *     public_post_types: list<string>,
 *     public_taxonomies: list<string>,
 * }
 */
function yoast_check_setup(): array
{
    $detect = yoast_detect_setup();

    if (!$detect['active']) {
        return [
            'active' => false,
            'edition' => null,
            'version' => null,
            'supports_redirects' => false,
            'xml_sitemap_enabled' => false,
            'seo_identity' => '',
            'public_post_types' => [],
            'public_taxonomies' => [],
        ];
    }

    return [
        'active' => true,
        'edition' => $detect['edition'],
        'version' => $detect['version'],
        'supports_redirects' => $detect['premium'],
        'xml_sitemap_enabled' => yoast_meta_bool(WPSEO_Options::get(
            'enable_xml_sitemap',
            default_value: true,
            option_groups: ['wpseo'],
        )),
        'seo_identity' => yoast_str(WPSEO_Options::get(
            'company_or_person',
            default_value: '',
            option_groups: ['wpseo_titles'],
        )),
        'public_post_types' => yoast_public_post_type_names(),
        'public_taxonomies' => yoast_public_taxonomy_names(),
    ];
}
