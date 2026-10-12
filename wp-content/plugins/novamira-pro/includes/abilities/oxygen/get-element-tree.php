<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-get-element-tree', [
    'label' => __('Get Oxygen Element Tree', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Oxygen element tree (the builder canvas) of any post the builder edits — including templates AND regular pages/posts that have been opened in Oxygen. The response is a nested summary of `{id, type, child_count, children}` rows. Use `depth` to control how deep the walk goes: `1` (default) returns the root and its direct children, `2` includes grandchildren, `-1` walks the entire tree. Pair with get-element to fetch the full `properties` blob of a specific node. Applies progressive disclosure: the cap (`max_nodes`, default 500) is applied to the nodes PROJECTED at the requested `depth`, not to the whole page — so a shallow query of a large page still returns the root and its children. Only when the projection itself exceeds the cap is the walk budgeted (up to `max_nodes` rows) and `truncated: true` set; every returned row keeps its `id` and `child_count`, so continue by re-issuing get-element-tree on a child id. `projected_nodes` reports the projection size and `total_nodes` the whole page.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'Id of any post that has an Oxygen canvas.'],
            'depth' => [
                'type' => 'integer',
                'description' => 'How many levels below the root to include. 1 = root + direct children (default). 2 = + grandchildren. -1 = entire tree. 0 = only the root summary.',
                'default' => 1,
            ],
            'max_nodes' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Safety cap on the number of summary rows returned. Applied to the nodes projected at the requested `depth` (not the whole page): only when that projection exceeds the cap is the walk budgeted and `truncated: true` set. Default 500.',
                'default' => 500,
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'tree' => ['type' => 'object'],
            'next_node_id' => ['type' => 'integer'],
            'total_nodes' => ['type' => 'integer'],
            'projected_nodes' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'max_nodes' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_get_element_tree',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default depth (1) keeps the response small even on complex pages. Increase depth to navigate the tree, or call get-element with a specific id once you know what you want to read in full. On `truncated: true` re-issue with either a tighter `depth` or a higher `max_nodes`.',
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
function oxygen_get_element_tree(array $input): array|WP_Error
{
    $post_id = ox_coerce_int($input['post_id'] ?? 0);
    if ($post_id <= 0) {
        return ox_error('oxygen_invalid_input', __('post_id must be a positive integer.', domain: 'novamira-pro'));
    }
    $post = ox_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $depth = ox_coerce_int($input['depth'] ?? 1);
    if ($depth < -1) {
        $depth = -1;
    }
    $max_nodes = max(1, ox_coerce_int($input['max_nodes'] ?? 500));

    $tree = ox_read_tree($post_id);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    /** @var array<string, mixed> $root */
    $root = $tree['root'];

    $total = ox_count_nodes($tree);
    $next_node_id = isset($tree['_nextNodeId']) && is_int($tree['_nextNodeId']) ? $tree['_nextNodeId'] : 100;

    // Cap on the PROJECTED node count at the requested depth, not the whole
    // canvas: a shallow query (e.g. depth 1) of a 5000-node page must still
    // return the root's immediate children, not collapse to a bare root just
    // because the page is large. Only when the projection itself exceeds the
    // budget do we emit a budgeted walk (up to max_nodes nodes) flagged
    // `truncated` — every emitted row keeps its id/child_count so the caller
    // can continue with get-element-tree on a child id.
    $projected = ox_count_projected_nodes($root, $depth);
    if ($projected <= $max_nodes) {
        return [
            'success' => true,
            'tree' => ox_element_summary($root, $depth),
            'next_node_id' => $next_node_id,
            'total_nodes' => $total,
            'projected_nodes' => $projected,
            'truncated' => false,
            'max_nodes' => $max_nodes,
        ];
    }

    $budget = $max_nodes;
    $summary = ox_element_summary_capped($root, $depth, $budget);
    return [
        'success' => true,
        'tree' => $summary,
        'next_node_id' => $next_node_id,
        'total_nodes' => $total,
        'projected_nodes' => $projected,
        // Reached only because the projection exceeds max_nodes, so the
        // budgeted walk necessarily dropped some rows.
        'truncated' => true,
        'max_nodes' => $max_nodes,
    ];
}
