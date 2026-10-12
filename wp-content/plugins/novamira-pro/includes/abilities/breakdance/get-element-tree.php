<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-get-element-tree', [
    'label' => __('Get Breakdance Element Tree', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Breakdance element tree (the builder canvas) of any post the builder edits, including templates AND regular pages/posts that have been opened in Breakdance. The response is a nested summary of `{id, type, child_count, children}` rows. Use `depth` to control how deep the walk goes: `1` (default) returns the root and its direct children, `2` includes grandchildren, `-1` walks the entire tree. Pair with `get-element` to fetch the full `properties` blob of a specific node.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'Id of any post that has a Breakdance canvas.'],
            'depth' => [
                'type' => 'integer',
                'description' => 'How many levels below the root to include. 1 = root + direct children (default). 2 = + grandchildren. -1 = entire tree. 0 = only the root summary.',
                'default' => 1,
            ],
            'max_nodes' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Safety cap on the total number of summary rows returned. When the tree has more nodes than `max_nodes`, the response is truncated to the root summary only and `truncated: true` is set so the caller can re-issue with a tighter `depth`. Default 500.',
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
            'truncated' => ['type' => 'boolean'],
            'max_nodes' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_get_element_tree',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default depth (1) keeps the response small even on complex pages. Increase depth to navigate the tree, or call get-element with a specific id once you know what you want to read in full.',
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
function breakdance_get_element_tree(array $input): array|WP_Error
{
    $post_id = bd_coerce_int($input['post_id'] ?? 0);
    if ($post_id <= 0) {
        return bd_error('bd_invalid_input', __('post_id must be a positive integer.', domain: 'novamira-pro'));
    }
    $post = bd_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $depth = bd_coerce_int($input['depth'] ?? 1);
    if ($depth < -1) {
        $depth = -1;
    }
    $max_nodes = max(1, bd_coerce_int($input['max_nodes'] ?? 500));

    $tree = bd_read_tree($post_id);
    /** @var array<string, mixed> $root */
    $root = $tree['root'];

    $total = 0;
    foreach (bd_iter_tree($tree) as $_node) {
        unset($_node);
        $total++;
    }

    $next_node_id = isset($tree['_nextNodeId']) && is_int($tree['_nextNodeId']) ? $tree['_nextNodeId'] : 100;

    // When the tree exceeds the safety cap, refuse the deep walk and
    // hand the caller a root-only summary plus a `truncated: true`
    // marker. They can re-issue with depth=1 or a higher max_nodes.
    if ($total > $max_nodes) {
        return [
            'success' => true,
            'tree' => bd_element_summary($root, depth: 0),
            'next_node_id' => $next_node_id,
            'total_nodes' => $total,
            'truncated' => true,
            'max_nodes' => $max_nodes,
        ];
    }

    return [
        'success' => true,
        'tree' => bd_element_summary($root, $depth),
        'next_node_id' => $next_node_id,
        'total_nodes' => $total,
        'truncated' => false,
        'max_nodes' => $max_nodes,
    ];
}
