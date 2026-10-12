<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WP_Error;
use WPSEO_Redirect_Manager;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/yoast-list-redirects', [
    'label' => __('List Redirects (Yoast Premium)', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Yoast SEO Premium redirects as compact rows {origin, target, type, format}. Supports paging (limit, default 100, max 500; offset) and a case-insensitive substring search across origin and target. format filters to plain or regex (default all). Returns total (matching count before paging) so you can page through large redirect sets. Requires Yoast SEO Premium.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring matched against origin and target.',
            ],
            'format' => ['type' => 'string', 'enum' => ['all', 'plain', 'regex'], 'default' => 'all'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'redirects' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
        ],
        'required' => ['redirects', 'total', 'returned', 'offset', 'limit'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_list_redirects',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'List redirects with paging + search so large sets never flood context. type is the HTTP code (301/302/307/410/451); format is plain or regex. Use search to find a specific origin before edit/delete. Premium-only — yoast-check-setup reports supports_redirects.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{limit?: int, offset?: int, search?: string, format?: string} $args
 * @return array{redirects: list<array<string, mixed>>, total: int, returned: int, offset: int, limit: int}|WP_Error
 */
function yoast_list_redirects(array $args): array|WP_Error
{
    if (!yoast_detect_setup()['active']) {
        return yoast_not_active();
    }
    if (!yoast_redirect_premium()) {
        return yoast_redirect_not_available();
    }

    // Coerce via yoast_int: a JSON number arrives as a float (100.0) and a
    // numeric string passes the integer schema, both of which would otherwise
    // survive the clamp and fatal array_slice() under strict_types.
    $limit = max(1, min(500, yoast_int($args['limit'] ?? 100)));
    $offset = max(0, yoast_int($args['offset'] ?? 0));
    $search = $args['search'] ?? '';
    $format = $args['format'] ?? 'all';

    $rows = lr_collect_rows($format, $search);
    $total = count($rows);

    return [
        'redirects' => array_slice($rows, $offset, $limit),
        'total' => $total,
        'returned' => count(array_slice($rows, $offset, $limit)),
        'offset' => $offset,
        'limit' => $limit,
    ];
}

/**
 * Collect redirect rows across both formats, filtered by format + search.
 *
 * @return list<array{origin: string, target: string, type: int, format: string}>
 */
function lr_collect_rows(string $format, string $search): array
{
    $rows = [];
    foreach ((new WPSEO_Redirect_Manager())->get_all_redirects() as $redirect) {
        $row = yoast_redirect_to_row($redirect);
        if ($format !== 'all' && $row['format'] !== $format) {
            continue;
        }
        if (
            $search !== ''
            && mb_stripos($row['origin'], $search, encoding: 'UTF-8') === false
            && mb_stripos($row['target'], $search, encoding: 'UTF-8') === false
        ) {
            continue;
        }
        $rows[] = $row;
    }
    return $rows;
}
