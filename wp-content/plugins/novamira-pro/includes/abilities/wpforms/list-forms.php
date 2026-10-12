<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

const NVP_WPFORMS_LIST_LIMIT_MAX = 500;

const NVP_WPFORMS_LIST_LIMIT_DEFAULT = 50;

wp_register_ability('novamira/wpforms-list-forms', [
    'label' => __('List WPForms Forms', domain: 'novamira-pro'),
    'description' => __(
        'List WPForms forms with a compact row per form (id, title, slug, status, dates, entry count when WPForms Pro is active). Paginated with limit (default 50, max 500) and offset; filterable by status. Use get-form to read a single form\'s full definition (fields, settings, notifications, confirmations).',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NVP_WPFORMS_LIST_LIMIT_MAX,
                'default' => NVP_WPFORMS_LIST_LIMIT_DEFAULT,
                'description' => 'Maximum rows to return (1-500).',
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
                'description' => 'Number of rows to skip (for pagination).',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['any', 'publish', 'draft', 'pending', 'private', 'trash'],
                'default' => 'any',
                'description' => 'Filter by post_status. "any" excludes trash.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Optional title substring filter.',
            ],
            'orderby' => [
                'type' => 'string',
                'enum' => ['id', 'title', 'date', 'modified'],
                'default' => 'modified',
                'description' => 'Sort key.',
            ],
            'order' => [
                'type' => 'string',
                'enum' => ['asc', 'desc'],
                'default' => 'desc',
                'description' => 'Sort direction.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'forms' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'date_created' => ['type' => 'string'],
                        'date_modified' => ['type' => 'string'],
                        'entry_count' => ['type' => ['integer', 'null']],
                    ],
                    'required' => ['id', 'title', 'slug', 'status', 'date_created', 'date_modified', 'entry_count'],
                ],
            ],
        ],
        'required' => ['total', 'limit', 'offset', 'forms'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_list_forms',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns a compact row per form. Use get-form for the full definition. entry_count is null on WPForms Lite (no entries table). Default limit is 50, max 500 — paginate via offset for larger sets.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     limit?: int,
 *     offset?: int,
 *     status?: string,
 *     search?: string,
 *     orderby?: string,
 *     order?: string,
 * } $input
 * @return array{
 *     total: int,
 *     limit: int,
 *     offset: int,
 *     forms: list<array{id: int, title: string, slug: string, status: string, date_created: string, date_modified: string, entry_count: int|null}>,
 * }|WP_Error
 */
function wpforms_list_forms(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }

    $limit = isset($input['limit'])
        ? max(1, min(NVP_WPFORMS_LIST_LIMIT_MAX, (int) $input['limit']))
        : NVP_WPFORMS_LIST_LIMIT_DEFAULT;
    $offset = isset($input['offset']) ? max(0, (int) $input['offset']) : 0;
    $status = $input['status'] ?? 'any';
    if ($status === 'any') {
        $status = ['publish', 'draft', 'pending', 'private'];
    }
    $orderby_map = ['id' => 'ID', 'title' => 'title', 'date' => 'date', 'modified' => 'modified'];
    $orderby = $orderby_map[$input['orderby'] ?? 'modified'] ?? 'modified';
    $order = strtoupper($input['order'] ?? 'desc') === 'ASC' ? 'ASC' : 'DESC';

    $args = [
        'post_type' => 'wpforms',
        'post_status' => $status,
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
        'no_found_rows' => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'suppress_filters' => true,
    ];
    if (isset($input['search']) && $input['search'] !== '') {
        $args['s'] = $input['search'];
    }

    $query = new WP_Query($args);
    $posts = array_values(array_filter($query->posts, static fn($p): bool => $p instanceof \WP_Post));

    // Batch the entry_count lookup into a single GROUP BY instead of one COUNT
    // per row (audit batch 1 HIGH — N+1 that scaled with limit, up to 500x).
    // counts is null on Lite or missing-table; wpforms_form_row's null default
    // then preserves the legacy entry_count=null contract for those installs.
    // Round-1 audit CRITICAL: explicit `is_array` guard so the array access
    // on the next line doesn't TypeError ("Cannot access offset of type null")
    // when wpforms_form_entry_counts_batch returns null on Lite.
    $counts = wpforms_form_entry_counts_batch(array_map(static fn($p) => (int) $p->ID, $posts));
    $counts_map = is_array($counts) ? $counts : [];

    $forms = [];
    foreach ($posts as $post) {
        $forms[] = wpforms_form_row($post, $counts_map[(int) $post->ID] ?? null);
    }

    return [
        'total' => (int) $query->found_posts,
        'limit' => $limit,
        'offset' => $offset,
        'forms' => $forms,
    ];
}
