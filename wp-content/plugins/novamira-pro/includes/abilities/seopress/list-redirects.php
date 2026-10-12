<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-list-redirects', [
    'label' => __('List Redirects (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Lists the SeoPress Pro redirects and monitored 404s (requires SeoPress Pro). Redirects AND 404s share ONE custom post type (seopress_404); the PRESENCE of an HTTP status (type) distinguishes a configured redirect from a bare 404 log entry. Use view to pick which: "redirects" (default — has a type), "404" (logged 404s with no type yet), or "all". Returns compact rows {id, origin, destination, type (int|null — null for a 404), enabled, enabled_regex, logged_status, param, count, last_hit}, newest first. origin is the source path (a relative path like /old-page/ or a regex pattern); destination is the target URL. Filter with enabled (bool), type (int HTTP status), and search (matches the origin). Paginate with limit (default 100, max 500) and offset; total is the matching count (respects the filters) so you can page while offset + returned < total. The compact row omits user-agent and referer telemetry — use get-redirect for the full row including ua, referer and category_ids. Use the id with get-redirect.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'view' => [
                'type' => 'string',
                'enum' => ['redirects', '404', 'all'],
                'description' => 'Which entries to list: redirects (has a type, default), 404 (no type), or all.',
            ],
            'enabled' => ['type' => 'boolean', 'description' => 'Filter by whether the redirect is enabled.'],
            'type' => ['type' => 'integer', 'description' => 'Filter by HTTP status code (e.g. 301, 410).'],
            'search' => ['type' => 'string', 'description' => 'Filter by origin (the source path / pattern).'],
            'limit' => ['type' => 'integer', 'description' => 'Max rows to return (1-500, default 100).'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip (pagination).'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'redirects' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
        ],
        'required' => ['redirects', 'total', 'limit', 'offset'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_list_redirects',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'List SeoPress Pro redirects / monitored 404s (the shared seopress_404 CPT). view=redirects (has a type) vs view=404 (no type) vs view=all. Rows are compact (origin = source path/regex, type null = a 404, no ua/referer); use get-redirect for the full row. Paginate with limit/offset; total respects the filters.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{view?: string, enabled?: bool, type?: int, search?: string, limit?: int, offset?: int} $args
 * @return array{redirects: list<array<string, mixed>>, total: int, limit: int, offset: int}|WP_Error
 */
function seopress_list_redirects(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }
    if (!sp_redirect_supported()) {
        return sp_pro_required('Redirects');
    }

    $limit = sp_int($args['limit'] ?? 0);
    $limit = $limit < 1 ? 100 : min($limit, 500);
    $offset = sp_int($args['offset'] ?? 0);

    $query = new WP_Query(sp_redirect_query_args($args, $limit, $offset));

    $redirects = [];
    /** @var mixed $post */
    foreach ($query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $redirects[] = sp_redirect_row($post);
    }

    return [
        'redirects' => $redirects,
        'total' => $query->found_posts,
        'limit' => $limit,
        'offset' => $offset,
    ];
}

/**
 * Build the WP_Query args for the seopress_404 listing: the post type, paging,
 * any-status, newest-first, plus the view / enabled / type meta_query and the
 * origin search. Extracted so the execute callback stays flat.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function sp_redirect_query_args(array $args, int $limit, int $offset): array
{
    $query_args = [
        'post_type' => 'seopress_404',
        'post_status' => 'any',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'date',
        'order' => 'DESC',
        // This CPT can never be sticky; the flag just stops WP_Query prepending
        // the sticky set, which would break the offset/total pagination contract.
        'ignore_sticky_posts' => true,
    ];

    $meta_query = sp_redirect_meta_query($args);
    if ($meta_query !== []) {
        $query_args['meta_query'] = $meta_query;
    }

    $search = sp_str($args['search'] ?? null);
    if (trim($search) !== '') {
        // WP_Query's `s` matches the post_title for this CPT — i.e. the origin.
        $query_args['s'] = $search;
    }

    return $query_args;
}

/**
 * The meta_query for the view / enabled / type filters. view selects on the
 * presence of _seopress_redirections_type (redirects = EXISTS, 404 = NOT
 * EXISTS, all = no constraint); enabled and type add equality leaves.
 *
 * @param array<string, mixed> $args
 * @return list<array<array-key, mixed>>
 */
function sp_redirect_meta_query(array $args): array
{
    /** @var list<array<array-key, mixed>> $meta_query */
    $meta_query = [];

    $view = is_string($args['view'] ?? null) ? $args['view'] : 'redirects';
    $view_leaf = sp_redirect_view_leaf($view);
    if ($view_leaf !== null) {
        $meta_query[] = $view_leaf;
    }

    if (is_bool($args['enabled'] ?? null)) {
        $meta_query[] = $args['enabled']
            ? ['key' => '_seopress_redirections_enabled', 'value' => 'yes', 'compare' => '=']
            : [
                'relation' => 'OR',
                ['key' => '_seopress_redirections_enabled', 'compare' => 'NOT EXISTS'],
                ['key' => '_seopress_redirections_enabled', 'value' => 'yes', 'compare' => '!='],
            ];
    }

    $type = sp_int($args['type'] ?? 0);
    if ($type > 0) {
        $meta_query[] = ['key' => '_seopress_redirections_type', 'value' => $type, 'compare' => '='];
    }

    return $meta_query;
}

/**
 * The view meta_query leaf: redirects = the type meta EXISTS, 404 = NOT EXISTS,
 * all (or any unknown value) = no constraint (null).
 *
 * @return array{key: string, compare: string}|null
 */
function sp_redirect_view_leaf(string $view): ?array
{
    return match ($view) {
        'redirects' => ['key' => '_seopress_redirections_type', 'compare' => 'EXISTS'],
        '404' => ['key' => '_seopress_redirections_type', 'compare' => 'NOT EXISTS'],
        default => null,
    };
}
