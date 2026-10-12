<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/astra-list-page-headers', [
    'label' => __('List Astra Page Headers', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates astra_adv_header posts as compact summaries — id, title, status, layout, enabled, location_summary. Content body and design array are omitted; call astra-get-page-header for a specific id. Supports pagination (limit/offset) and filtering by status. Requires the Astra Pro Addon with the advanced-headers module active.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of page headers to return. Default 20, max 100.',
                'default' => 20,
            ],
            'offset' => [
                'type' => 'integer',
                'description' => 'Number of page headers to skip for pagination. Default 0.',
                'default' => 0,
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['any', 'publish', 'draft'],
                'description' => 'Post status filter. Defaults to any (publish + draft).',
                'default' => 'any',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'items' => ['type' => 'array', 'description' => 'Compact page header summaries.'],
            'has_more' => [
                'type' => 'boolean',
                'description' => 'True when additional pages exist beyond this window.',
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'Total matching page headers (ignoring limit/offset).',
            ],
        ],
        'required' => ['items', 'has_more', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_list_page_headers',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists astra_adv_header posts as compact summaries. Call astra-get-page-header with a specific id for the full design, content body, and targeting arrays.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_list_page_headers(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }
    if (!astra_addon_available()) {
        return astra_addon_required_error('advanced-headers');
    }
    if (!astra_module_active('advanced-headers')) {
        return astra_module_disabled_error('advanced-headers');
    }
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'manage_options capability required.', ['status' => 403]);
    }

    $limit = min(100, max(1, astra_coerce_int($input['limit'] ?? 20)));
    $offset = max(0, astra_coerce_int($input['offset'] ?? 0));

    $raw_status = is_string($input['status'] ?? null) ? $input['status'] : 'any';
    $status = in_array($raw_status, ['any', 'publish', 'draft'], strict: true) ? $raw_status : 'any';

    $args = [
        'post_type' => 'astra_adv_header',
        'post_status' => $status,
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'DESC',
        'no_found_rows' => false,
    ];

    $query = new WP_Query($args);

    $items = [];
    /** @var list<WP_Post> $posts */
    $posts = $query->posts;
    foreach ($posts as $post) {
        $items[] = astra_page_header_summary($post);
    }

    return [
        'items' => $items,
        'has_more' => ($offset + $limit) < (int) $query->found_posts,
        'total' => (int) $query->found_posts,
    ];
}
