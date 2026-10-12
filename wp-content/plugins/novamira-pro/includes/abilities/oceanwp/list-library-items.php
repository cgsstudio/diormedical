<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-list-library-items', [
    'label' => __('List OceanWP Library Items', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Ocean Extra `oceanwp_library` CPT posts — the reusable header / footer / topbar / 404 templates the agent can mount via `oceanwp-apply-library-template`. Returns compact entries (id, title, slug, status, modified). REQUIRES the Ocean Extra companion plugin to be active at the supported version; returns `oceanwp_requires_ocean_extra` otherwise. Use the `limit` param to cap the page; defaults to 100, max 500.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 100,
                'description' => 'Maximum library items to return. Clamped server-side to OW_LIST_HARD_MAX (500).',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft', 'any'],
                'default' => 'publish',
                'description' => 'Filter by post_status. `any` includes both publish + draft.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => [
                'type' => 'integer',
                'description' => 'Total library items matching the filter (before limit).',
            ],
            'returned' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean', 'description' => 'True when `total > returned`.'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'modified' => [
                            'type' => 'string',
                            'description' => 'GMT ISO-8601 timestamp (e.g. 2026-06-30T18:00:00Z). Empty string when the post was never modified after creation.',
                        ],
                    ],
                    'required' => ['id', 'title', 'slug', 'status', 'modified'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'returned', 'truncated', 'items'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_list_library_items',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Ocean-Extra-gated. Call `oceanwp-check-setup` first and verify `capabilities.library === true` before invoking.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{limit?: int, status?: string} $input
 * @return array{
 *   total: int,
 *   returned: int,
 *   truncated: bool,
 *   items: list<array{id: int, title: string, slug: string, status: string, modified: string}>
 * }|WP_Error
 */
function oceanwp_list_library_items(array $input): array|WP_Error
{
    $gate = ow_assert_ocean_extra_available();
    if ($gate !== null) {
        return $gate;
    }
    $limit = min(ow_coerce_int_or_null($input['limit'] ?? null) ?? 100, OW_LIST_HARD_MAX);
    if ($limit < 1) {
        $limit = 100;
    }
    $status_input = ow_coerce_string_or_null($input['status'] ?? null) ?? 'publish';
    $status = $status_input === 'any' ? ['publish', 'draft'] : [$status_input];

    $query_args = [
        'post_type' => 'oceanwp_library',
        'post_status' => $status,
        'numberposts' => $limit,
        'orderby' => 'modified',
        'order' => 'DESC',
        'suppress_filters' => false,
    ];
    /** @var \WP_Post[] $posts */
    $posts = get_posts($query_args);

    // Total = unfiltered count so caller knows whether to widen `limit`.
    $count_query = new \WP_Query([
        'post_type' => 'oceanwp_library',
        'post_status' => $status,
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);
    $total = (int) $count_query->found_posts;

    $items = [];
    foreach ($posts as $p) {
        // Convert MySQL datetime → ISO-8601 GMT to honour the schema
        // description. Audit round 2 M#8. Empty string for the
        // '0000-00-00 00:00:00' sentinel (un-modified post).
        $mod_mysql = $p->post_modified_gmt;
        $modified = '';
        if ($mod_mysql !== '' && $mod_mysql !== '0000-00-00 00:00:00') {
            $ts = strtotime($mod_mysql . ' UTC');
            if ($ts !== false) {
                $modified = gmdate('Y-m-d\\TH:i:s\\Z', $ts);
            }
        }
        $items[] = [
            'id' => $p->ID,
            'title' => $p->post_title,
            'slug' => $p->post_name,
            'status' => $p->post_status,
            'modified' => $modified,
        ];
    }

    return [
        'total' => $total,
        'returned' => count($items),
        'truncated' => $total > count($items),
        'items' => $items,
    ];
}
