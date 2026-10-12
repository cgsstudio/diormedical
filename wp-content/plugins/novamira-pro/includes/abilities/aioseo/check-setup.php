<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-check-setup', [
    'label' => __('Check AIOSEO Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the All in One SEO environment: whether AIOSEO is active, its version, whether it is the Pro edition, which Pro-gated surfaces are available (term SEO and redirects need Pro), whether schema is available, and the public post types and taxonomies whose SEO can be managed. Pro-gated abilities (term SEO, redirects) check this. Call this first before any other AIOSEO ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        // No inputs. Keep properties an empty ARRAY, NOT (object) []: with
        // additionalProperties:false, WordPress core's input validator runs
        // isset($schema['properties'][$key]) for every extra key, which FATALS
        // ("Cannot use object of type stdClass as array") when properties is a
        // stdClass. The array form gives the intended clean WP_Error rejection.
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => ['type' => 'boolean'],
            'version' => ['type' => ['string', 'null']],
            'pro' => ['type' => 'boolean'],
            'supports_term_seo' => ['type' => 'boolean'],
            'supports_redirects' => ['type' => 'boolean'],
            'supports_schema' => ['type' => 'boolean'],
            'public_post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'public_taxonomies' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => [
            'active',
            'version',
            'pro',
            'supports_term_seo',
            'supports_redirects',
            'supports_schema',
            'public_post_types',
            'public_taxonomies',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first. (If this ability is missing from your tool list, AIOSEO is not active.) pro indicates the Pro edition; supports_term_seo and supports_redirects are Pro-only (the term-SEO and redirect abilities need them). public_post_types / public_taxonomies are the targets for get/edit-post-seo and get/edit-term-seo.',
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
 *     supports_term_seo: bool,
 *     supports_redirects: bool,
 *     supports_schema: bool,
 *     public_post_types: list<string>,
 *     public_taxonomies: list<string>,
 * }
 */
function aioseo_check_setup(): array
{
    $detect = aio_detect_setup();

    if (!$detect['active']) {
        return [
            'active' => false,
            'version' => null,
            'pro' => false,
            'supports_term_seo' => false,
            'supports_redirects' => false,
            'supports_schema' => false,
            'public_post_types' => [],
            'public_taxonomies' => [],
        ];
    }

    return [
        'active' => true,
        'version' => $detect['version'],
        'pro' => $detect['pro'],
        // Term SEO is a Pro feature (wp_aioseo_terms / the Term model).
        'supports_term_seo' => $detect['pro'],
        'supports_redirects' => aio_supports_redirects(),
        // Schema (structured data) type is available in the free edition too.
        'supports_schema' => true,
        'public_post_types' => aio_public_post_type_names(),
        'public_taxonomies' => aio_public_taxonomy_names(),
    ];
}
