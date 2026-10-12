<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Read the WPBakery element tree for a post.
 */

wp_register_ability('novamira/wpbakery-get-content', [
    'label' => __('Get WPBakery Content', domain: 'novamira-pro'),
    'description' => __(
        'Returns the WPBakery element tree for a post as a nested JSON structure under the `tree` field. Each node has tag, atts (decoded), children, and _idx (depth-first index used by add/edit/delete operations). vc_link values are decoded to {url, title, target, rel} objects; param_group values are decoded to arrays. Container elements that hold raw HTML (e.g. vc_column_text) expose a _content field with the inner HTML. The returned `tree` can be passed verbatim to wpbakery-set-content for a round-trip.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to read.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'is_wpbakery_page' => ['type' => 'boolean'],
            'tree' => [
                'type' => 'array',
                'description' => 'The WPBakery element tree (array of root nodes). Same shape accepted by wpbakery-set-content (`tree` field).',
            ],
            'edit_url' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\WPBakery\wpbakery_get_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to read the current WPBakery layout before modifying it. The _idx on each node is the identifier for add/edit/delete operations — it is depth-first and changes after any write, so always call get-content again after a write to get fresh indices.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function wpbakery_get_content(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);

    $raw = wpb_get_post_content($post_id);
    if ($raw === null) {
        return ['success' => false, 'error' => sprintf('Post %d not found.', $post_id)];
    }

    $tree = wpb_parse_content($raw);
    $counter = 0;
    $tree = wpb_index_tree($tree, $counter);

    return [
        'success' => true,
        'post_id' => $post_id,
        'is_wpbakery_page' => wpb_is_wpbakery_post($post_id),
        'tree' => $tree,
        'edit_url' => (string) get_edit_post_link($post_id, context: 'raw'),
    ];
}
