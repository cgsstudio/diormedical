<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-get-redirect', [
    'label' => __('Get Redirect (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Reads one SeoPress Pro redirect / monitored 404 by id (requires SeoPress Pro). Redirects AND 404s share ONE custom post type (seopress_404); the presence of an HTTP status (type) distinguishes a configured redirect from a bare 404 log entry. Returns the FULL row: id, origin (the source path / regex, stored as the post_title), destination (target URL), type (int HTTP status, or null for a 404), enabled, enabled_regex (origin is a regex), logged_status (both / only_logged_in / only_not_logged_in), param (exact_match / without_param / with_ignored_param), count (hit count), last_hit (unix timestamp or null), ua (last user agent), referer (last referer), and category_ids (the seopress_404_cat term ids). Identify the entry by id (the seopress_404 post id — get it from list-redirects).',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'The seopress_404 post id (from list-redirects).'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'redirect' => ['type' => 'object'],
        ],
        'required' => ['redirect'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_get_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read one SeoPress Pro redirect / 404 (the shared seopress_404 CPT) by id. origin is the source path/regex (the post_title); type null means it is a bare 404, not a redirect. The full row adds ua, referer and category_ids over the compact list-redirects row.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id?: int} $args
 * @return array{redirect: array<string, mixed>}|WP_Error
 */
function seopress_get_redirect(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }
    if (!sp_redirect_supported()) {
        return sp_pro_required('Redirects');
    }

    $post = sp_resolve_redirect($args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $id = $post->ID;
    $row = sp_redirect_row($post);
    $row['ua'] = sp_clean_text(sp_str(sp_post_meta('seopress_redirections_ua', $id)));
    $row['referer'] = sp_clean_url(sp_str(sp_post_meta('seopress_redirections_referer', $id)));
    $row['category_ids'] = sp_redirect_category_ids($id);

    return ['redirect' => $row];
}

/**
 * The seopress_404_cat term ids attached to a redirect post. wp_get_post_terms
 * returns a WP_Error or a mixed list; coerce every entry to a positive int.
 *
 * @return list<int>
 */
function sp_redirect_category_ids(int $id): array
{
    /** @var mixed $terms */
    $terms = wp_get_post_terms($id, taxonomy: 'seopress_404_cat', args: ['fields' => 'ids']);
    if (!is_array($terms)) {
        return [];
    }
    $ids = [];
    /** @var mixed $term_id */
    foreach ($terms as $term_id) {
        $int = sp_int($term_id);
        if ($int > 0) {
            $ids[] = $int;
        }
    }
    return $ids;
}
