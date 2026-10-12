<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-check-setup', [
    'label' => __('Check ACF Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the ACF environment: whether ACF is active, edition (free or pro), version, whether the runtime supports managing post types, taxonomies, and options pages via abilities (Pro 6.5+), and how many ACF-managed post types, taxonomies, and options pages already exist. Use before any ACF operation to verify the runtime is ready.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
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
            'edition' => ['type' => ['string', 'null'], 'enum' => ['free', 'pro', null]],
            'version' => ['type' => ['string', 'null']],
            'supports_internal_post_types' => ['type' => 'boolean'],
            'supports_internal_options_pages' => ['type' => 'boolean'],
            'registered_acf_post_types_count' => ['type' => 'integer'],
            'registered_acf_taxonomies_count' => ['type' => 'integer'],
            'registered_acf_options_pages_count' => ['type' => 'integer'],
        ],
        'required' => [
            'active',
            'edition',
            'version',
            'supports_internal_post_types',
            'supports_internal_options_pages',
            'registered_acf_post_types_count',
            'registered_acf_taxonomies_count',
            'registered_acf_options_pages_count',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first before any other ACF ability. (Note: if this ability is missing from your tool list, ACF is not active — there is nothing for the agent to do.) Reports edition (free/pro), version, whether internal-post-type and options-page management abilities are available (both require ACF Pro 6.5+), and counts of registered CPTs / taxonomies / options pages. The counts give immediate orientation: zero means starting fresh.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     edition: 'free'|'pro'|null,
 *     version: string|null,
 *     supports_internal_post_types: bool,
 *     supports_internal_options_pages: bool,
 *     registered_acf_post_types_count: int,
 *     registered_acf_taxonomies_count: int,
 *     registered_acf_options_pages_count: int,
 * }
 */
function acf_check_setup(): array
{
    $base = acf_detect_setup();

    $supports = acf_internal_post_type_guard() === null;
    $cpt_count = 0;
    $tax_count = 0;
    $options_pages_count = 0;

    if ($supports) {
        $cpt = acf_get_internal_post_type_posts('acf-post-type');
        $cpt_count = count($cpt);
        $tax = acf_get_internal_post_type_posts('acf-taxonomy');
        $tax_count = count($tax);
        $options = acf_get_internal_post_type_posts('acf-ui-options-page');
        $options_pages_count = count($options);
    }

    return [
        'active' => $base['active'],
        'edition' => $base['edition'],
        'version' => $base['version'],
        'supports_internal_post_types' => $supports,
        'supports_internal_options_pages' => $supports,
        'registered_acf_post_types_count' => $cpt_count,
        'registered_acf_taxonomies_count' => $tax_count,
        'registered_acf_options_pages_count' => $options_pages_count,
    ];
}
