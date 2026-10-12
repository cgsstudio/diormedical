<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/rank-math-check-setup', [
    'label' => __('Check Rank Math Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Rank Math SEO environment: whether Rank Math is active, its version, which modules are enabled (e.g. rich-snippet/schema, redirections, sitemap, local-seo), and the public post types and taxonomies whose SEO can be managed. Module-gated abilities (like the schema abilities, which need the rich-snippet module) check this. Call this first before any other Rank Math ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'rank-math',
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
            'version' => ['type' => ['string', 'null']],
            'active_modules' => ['type' => 'array', 'items' => ['type' => 'string']],
            'supports_schema' => ['type' => 'boolean'],
            'supports_redirections' => ['type' => 'boolean'],
            'public_post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'public_taxonomies' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => [
            'active',
            'version',
            'active_modules',
            'supports_schema',
            'supports_redirections',
            'public_post_types',
            'public_taxonomies',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\RankMath\rank_math_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first. (If this ability is missing from your tool list, Rank Math is not active.) supports_schema means the rich-snippet module is on (needed by the schema abilities); active_modules lists what is enabled. public_post_types / public_taxonomies are the targets for get/edit-post-seo and get/edit-term-seo.',
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
 *     active_modules: list<string>,
 *     supports_schema: bool,
 *     supports_redirections: bool,
 *     public_post_types: list<string>,
 *     public_taxonomies: list<string>,
 * }
 */
function rank_math_check_setup(): array
{
    $detect = rm_detect_setup();

    if (!$detect['active']) {
        return [
            'active' => false,
            'version' => null,
            'active_modules' => [],
            'supports_schema' => false,
            'supports_redirections' => false,
            'public_post_types' => [],
            'public_taxonomies' => [],
        ];
    }

    return [
        'active' => true,
        'version' => $detect['version'],
        // Runtime view: drops modules whose host plugin is absent (e.g. the
        // woocommerce/acf modules when those plugins are inactive), matching
        // what Rank Math actually treats as enabled.
        'active_modules' => rm_runtime_active_modules(),
        // supports_* mirror the option-based gate that decides whether the
        // dependent abilities register at all (rich-snippet/redirections are
        // never host-gated, so this agrees with the runtime view).
        'supports_schema' => rm_module_active('rich-snippet'),
        'supports_redirections' => rm_module_active('redirections'),
        'public_post_types' => rm_public_post_type_names(),
        'public_taxonomies' => rm_public_taxonomy_names(),
    ];
}
