<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;

/**
 * Ability: Get the Etch element tree for a post.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/etch-get-content', [
    'label' => __('Get Etch Content', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Etch element tree for a post or template, parsed from its block markup (post_content). Each node is { block, attrs?, children?, html? } where block is the Gutenberg block name (e.g. "etch/element", "etch/text", "etch/component"), attrs is the block\'s attributes (e.g. tag, attributes, styles for etch/element; content for etch/text; ref for etch/component), children is the nested node list, and html holds the inner HTML of leaf blocks that store content outside attrs (core blocks, raw HTML). A node with an empty block string ("") is freeform HTML carried in html. Pure-whitespace between blocks is omitted. Use novamira/etch-list-elements to learn the block vocabulary, and novamira/etch-set-content to write a tree back.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID.',
            ],
            'max_nodes' => [
                'type' => 'integer',
                'description' => 'Maximum number of nodes to return (truncates large trees). Defaults to 5000.',
                'minimum' => 1,
                'maximum' => ETCH_MAX_TREE_NODES,
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'post_title' => ['type' => 'string'],
            'post_type' => [
                'type' => 'string',
                'description' => 'The post type of the edited object (e.g. page, post, wp_template, or a CPT) — so the agent knows what kind of thing it is editing.',
            ],
            'node_count' => [
                'type' => 'integer',
                'description' => 'Number of nodes returned in the tree.',
            ],
            'truncated' => [
                'type' => 'boolean',
                'description' => 'True when the tree was capped at max_nodes and is incomplete.',
            ],
            'tree' => [
                'type' => 'array',
                'description' => 'The top-level Etch nodes. Each node: { block, attrs?, children?, html? }.',
                'items' => ['type' => 'object'],
            ],
            'lossy' => [
                'type' => 'boolean',
                'description' => 'True when the source contains content the tree cannot fully represent (see warnings). Writing the tree back with set-content would not be a faithful round-trip.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Notes about content that was not faithfully represented.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the Etch page structure before editing it. The tree mirrors the Gutenberg block tree: container blocks (etch/element, etch/loop, etch/condition) carry children; leaf blocks (etch/text, etch/component) do not. If truncated is true, raise max_nodes or inspect a subtree. To rewrite the page, edit the returned tree and pass it to novamira/etch-set-content. IMPORTANT: if lossy is true, the page mixes in content this tree cannot fully represent (e.g. raw HTML between child blocks) — do NOT write the tree back wholesale, as that would drop the unrepresented content; edit the page another way or only touch subtrees the user intends. These abilities are faithful for pages built entirely with Etch (etch/*) blocks.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     post_id: int,
 *     post_title: string,
 *     post_type: string,
 *     node_count: int,
 *     truncated: bool,
 *     tree: list<array<string, mixed>>,
 *     lossy: bool,
 *     warnings: list<string>,
 * }|WP_Error
 */
function etch_get_content(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post = etch_post_or_error((int) ($input['post_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $max_nodes = isset($input['max_nodes']) ? (int) $input['max_nodes'] : ETCH_MAX_TREE_NODES;
    $max_nodes = max(1, min($max_nodes, ETCH_MAX_TREE_NODES));

    $blocks = parse_blocks($post->post_content);
    $total = etch_count_block_nodes($blocks);

    $count = 0;
    $tree = etch_blocks_to_tree($blocks, $max_nodes, $count);

    $warnings = [];
    $interleaved = etch_count_interleaved($blocks);
    if ($interleaved > 0) {
        $warnings[] = sprintf(
            /* translators: %d: number of HTML fragments. */
            __(
                '%d freeform HTML fragment(s) between child blocks are not represented and would be lost if this tree is written back.',
                domain: 'novamira-pro',
            ),
            $interleaved,
        );
    }

    return [
        'post_id' => $post->ID,
        'post_title' => $post->post_title,
        'post_type' => $post->post_type,
        'node_count' => $count,
        'truncated' => $total > $count,
        'tree' => $tree,
        'lossy' => $warnings !== [],
        'warnings' => $warnings,
    ];
}
