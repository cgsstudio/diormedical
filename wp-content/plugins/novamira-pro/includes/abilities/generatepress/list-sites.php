<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-list-sites', [
    'label' => __('List GeneratePress Site Library Templates', domain: 'novamira-pro'),
    'description' => __(
        'Returns the catalogue of starter templates the GP Premium Site Library can install. The list is cached in the `generatepress_sites` option (1-day TTL); pass `refresh=true` to force a fresh fetch from gpsites.co (network call). Each row is a compact summary — `name`, `category`, `description`, `page_builder`, `preview_url`, `min_theme_version`, `plugins` map, `directory` (the remote asset root used by generatepress-apply-site). Use generatepress-get-site to inspect a specific template before applying.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'refresh' => [
                'type' => 'boolean',
                'description' => 'When true, bypass the cached option and re-fetch the remote catalogue. Defaults to false.',
                'default' => false,
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Optional category filter (e.g. "agency", "ecommerce"). Matched case-insensitively against `category`.',
            ],
            'page_builder' => [
                'type' => 'string',
                'description' => 'Optional builder filter — only return templates built for the given page builder (e.g. "generateblocks", "elementor").',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of rows to return after filtering. The full upstream catalogue can hit ~200 entries / 130 KB — use a smaller limit when scanning for a specific category to keep the agent context lean. Default 100.',
                'default' => 100,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'sites' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
            'cached' => ['type' => 'boolean'],
            'expires_at' => ['type' => 'integer'],
            'refresh_failed' => [
                'type' => 'boolean',
                'description' => 'True when `refresh=true` was passed but the upstream fetch left the catalogue empty (network error, upstream 4xx/5xx, or no entries). Lets the agent distinguish a legitimately-empty catalogue from a broken refresh.',
            ],
        ],
        'required' => ['sites', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_list_sites',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Start here whenever the user mentions "starter template" / "demo site" / "import a layout". Use `refresh=true` only when the cached catalogue is empty or stale (default cache is 1 day). The `name` field is the key used by generatepress-get-site and generatepress-apply-site.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{refresh?: bool, category?: string, page_builder?: string, limit?: int} $input
 * @return array<string, mixed>
 */
function generatepress_list_sites(array $input): array
{
    $refresh = gp_coerce_bool($input['refresh'] ?? false);
    $refresh_failed = false;
    if ($refresh) {
        // gp_force_refresh_sites_catalogue dispatches the upstream REST
        // endpoint and writes the option as a side effect. A network
        // failure leaves the option empty / set to the literal string
        // "no results" — surface that distinction so an agent can tell
        // a legitimately-empty catalogue from a fetch that broke.
        gp_force_refresh_sites_catalogue();
        /** @var mixed $post_refresh */
        $post_refresh = get_option('generatepress_sites', []);
        if (!is_array($post_refresh) || $post_refresh === []) {
            $refresh_failed = true;
        }
    }

    /** @var mixed $raw */
    $raw = get_option('generatepress_sites', []);
    $cached = is_array($raw);
    /** @var array<string, mixed> $sites */
    $sites = $cached ? $raw : [];

    /** @var mixed $expires_raw */
    $expires_raw = get_option(option: 'generatepress_sites_expiration', default_value: 0);
    $expires_at = is_numeric($expires_raw) ? (int) $expires_raw : 0;

    $category = isset($input['category']) ? strtolower($input['category']) : null;
    $builder = isset($input['page_builder']) ? strtolower($input['page_builder']) : null;
    /** @var mixed $raw_limit */
    $raw_limit = $input['limit'] ?? 100;
    $limit = max(1, gp_coerce_int($raw_limit));

    $rows = gp_filter_sites_catalogue($sites, $category, $builder);
    $total = count($rows);
    if (count($rows) > $limit) {
        $rows = array_slice(array: $rows, offset: 0, length: $limit);
    }

    return [
        'sites' => $rows,
        'total' => $total,
        'cached' => !$refresh && $cached && $sites !== [],
        'expires_at' => $expires_at,
        'refresh_failed' => $refresh_failed,
    ];
}
