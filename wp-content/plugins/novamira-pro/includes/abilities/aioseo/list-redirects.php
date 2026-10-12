<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-list-redirects', [
    'label' => __('List Redirects (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Lists the All in One SEO redirects (requires AIOSEO Pro). Returns compact rows {id, source_url, target_url, type, regex, enabled}, newest first. Use limit (default 100, max 500), offset to paginate, and search to filter by source or target URL. total is the number of matching rows (it respects search), so you can paginate while offset + returned < total. Use the id with aioseo-edit-redirect / aioseo-delete-redirect.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => ['type' => 'integer', 'description' => 'Max rows to return (1–500, default 100).'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip (pagination).'],
            'search' => ['type' => 'string', 'description' => 'Filter by source or target URL (substring).'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'redirects' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
        ],
        'required' => ['redirects', 'total', 'returned'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_list_redirects',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'List AIOSEO redirects (Pro). Paginate with limit/offset and narrow with search; rows are compact (id, source/target URL, type, regex, enabled). Use the id with edit-redirect / delete-redirect.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{limit?: int, offset?: int, search?: string} $args
 * @return array{redirects: list<array<string, mixed>>, total: int, returned: int}|WP_Error
 */
function aioseo_list_redirects(array $args): array|WP_Error
{
    if (!aio_detect_setup()['active']) {
        return aio_not_active();
    }
    if (!aio_supports_redirects()) {
        return aio_pro_required('Redirects');
    }

    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = $wpdb->prefix . 'aioseo_redirects';

    $limit = aio_int($args['limit'] ?? 0);
    $limit = $limit < 1 ? 100 : min($limit, 500);
    $offset = aio_int($args['offset'] ?? 0);
    $search = is_string($args['search'] ?? null) ? trim($args['search']) : '';

    // The table comes from the trusted prefix; $limit/$offset are coerced ints;
    // the search term is wildcard- and SQL-escaped before interpolation.
    $where = '';
    if ($search !== '') {
        $escaped = esc_sql('%' . $wpdb->esc_like($search) . '%');
        $like = is_string($escaped) ? $escaped : '';
        $where = "WHERE source_url LIKE '{$like}' OR target_url LIKE '{$like}'";
    }

    // total must reflect the SAME filter as the rows so a caller can paginate
    // with offset + returned < total; the $where is already escaped above.
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    /** @var mixed $rows */
    $rows = $wpdb->get_results(
        "SELECT id, source_url, target_url, type, regex, enabled FROM {$table} {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
        'ARRAY_A',
    );

    $redirects = aio_redirect_db_rows(is_array($rows) ? $rows : []);

    return ['redirects' => $redirects, 'total' => $total, 'returned' => count($redirects)];
}
