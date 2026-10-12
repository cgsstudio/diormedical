<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;

/**
 * Ability: Replace the entire Etch element tree of a post.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/etch-set-content', [
    'label' => __('Set Etch Content', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the entire Etch element tree of a post or template with the given tree, serialized to block markup (post_content). The tree is a list of nodes, each { block, attrs?, children?, html? } — the same shape novamira/etch-get-content returns. block is a "namespace/name" Gutenberg block slug (e.g. "etch/element", "etch/text"); attrs is the block attributes object (keyed, not a list); children is the nested node list (container blocks only); html is the inner HTML for leaf blocks that store content outside attrs. A node with an empty block string ("") plus html is freeform HTML. Safety guard: if the post\'s current content is lossy (it holds freeform HTML the tree cannot represent, the same condition get-content reports as lossy:true), the write is refused with etch_lossy_overwrite unless you pass force:true. This overwrites all existing content of the post — read it first with novamira/etch-get-content if you intend to edit rather than replace. Use novamira/etch-list-elements for the block vocabulary. Style binding is automatic: when an element\'s attributes.class names a class that has a per-class Etch style record (novamira/etch-create-style), that record\'s id is added to the node\'s styles for you, so the class stays rendered and editable in Etch without setting styles by hand (create the record first; explicit styles are preserved).',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID to overwrite.',
            ],
            'tree' => [
                'type' => 'array',
                'description' => 'The top-level Etch nodes. Each node: { block (string), attrs (object, optional), children (array of nodes, optional), html (string, optional — inner HTML for leaf/freeform nodes) }.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'block' => ['type' => 'string'],
                        'attrs' => ['type' => 'object'],
                        'children' => ['type' => 'array'],
                        'html' => ['type' => 'string'],
                    ],
                    'required' => ['block'],
                    'additionalProperties' => false,
                ],
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'Set true to overwrite even when the current content is lossy (holds freeform HTML the tree cannot represent). Without it, such a write is refused with etch_lossy_overwrite to avoid silent data loss. Defaults to false.',
            ],
        ],
        'required' => ['post_id', 'tree'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'node_count' => [
                'type' => 'integer',
                'description' => 'Number of nodes written.',
            ],
            'bytes' => [
                'type' => 'integer',
                'description' => 'Byte length of the serialized post_content.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_set_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE: replaces the whole page content. To edit, first call novamira/etch-get-content, mutate the returned tree, then pass it back here. Pass an empty tree ([]) to clear the page. Block names should be Etch blocks (etch/*) from novamira/etch-list-elements; attrs must match the block (e.g. etch/element needs attrs.tag).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{post_id: int, node_count: int, bytes: int}|WP_Error
 */
function etch_set_content(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post = etch_post_or_error((int) ($input['post_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $node_count = 0;
    $markup = etch_tree_to_markup_or_error($input['tree'] ?? null, $node_count);
    if ($markup instanceof WP_Error) {
        return $markup;
    }

    // Refuse to clobber a page whose current content is lossy (freeform HTML
    // interleaved between child blocks, which get-content cannot represent and
    // reports as lossy:true). A blind get->mutate->set on such a page would
    // silently drop that content; require an explicit force:true to proceed.
    if (($input['force'] ?? null) !== true) {
        $interleaved = etch_count_interleaved(parse_blocks($post->post_content));
        if ($interleaved > 0) {
            return etch_error('etch_lossy_overwrite', sprintf(
                /* translators: %d: number of freeform HTML fragments. */
                __(
                    'This post holds %d freeform HTML fragment(s) between child blocks that the Etch tree cannot represent; overwriting would drop them. Re-read it with novamira/etch-get-content (it reports lossy:true), and if you intend to replace the page anyway, call set-content again with force:true.',
                    domain: 'novamira-pro',
                ),
                $interleaved,
            ));
        }
    }

    // wp_update_post() runs wp_unslash() on the data, which would strip the
    // backslashes from the \uXXXX escapes serialize_blocks() emits for < > & "
    // in attribute values — corrupting the block markup. Slash the content first
    // so the round-trip is lossless.
    $result = wp_update_post([
        'ID' => $post->ID,
        'post_content' => wp_slash($markup),
    ], wp_error: true);

    if ($result instanceof WP_Error) {
        return $result;
    }

    // update_post_meta-style structural writes don't always refresh the object
    // cache; force it so a subsequent get-content reads the new tree.
    clean_post_cache($post->ID);

    return [
        'post_id' => $post->ID,
        'node_count' => $node_count,
        'bytes' => strlen($markup),
    ];
}
