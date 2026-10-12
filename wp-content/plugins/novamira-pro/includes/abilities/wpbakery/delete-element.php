<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Delete a WPBakery element (and its children) from a post.
 */

wp_register_ability('novamira/wpbakery-delete-element', [
    'label' => __('Delete WPBakery Element', domain: 'novamira-pro'),
    'description' => __(
        'Permanently removes a WPBakery element and all its children from a post, identified by _idx (from wpbakery-get-content). Deleting a container (vc_row, vc_column) removes all nested content inside it. Non-WPBakery shortcodes elsewhere in post_content (e.g. [contact-form-7], [gallery]) are preserved and re-appended after the saved WPBakery tree; the response lists them in `preserved_foreign_shortcodes` when present. Remaining nodes get re-indexed depth-first after the delete, so call wpbakery-get-content again before targeting another _idx.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to modify.',
            ],
            'element_idx' => [
                'type' => 'integer',
                'description' => 'The _idx of the element to delete (from wpbakery-get-content).',
            ],
        ],
        'required' => ['post_id', 'element_idx'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'edit_url' => ['type' => 'string'],
            'tree' => [
                'type' => 'array',
                'description' => 'The post-write tree with fresh _idx values — same shape wpbakery-get-content returns. Surrounding nodes get re-indexed after the delete, so use this to act on the new indices without an extra get-content round-trip.',
            ],
            'preserved_foreign_shortcodes' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\WPBakery\wpbakery_delete_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call wpbakery-get-content first to find the _idx of the element to delete. Deleting a container removes its entire subtree. _idx values change after every write — always re-fetch after a modification.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

function wpbakery_delete_element(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $element_idx = (int) ($input['element_idx'] ?? -1);

    $writable_error = wpb_post_writable_or_error($post_id);
    if ($writable_error !== null) {
        return $writable_error;
    }

    $raw = wpb_get_post_content($post_id) ?? '';
    $tree = wpb_parse_indexed_tree($raw);

    $deleted = wpb_delete_node($tree, $element_idx);
    if (!$deleted) {
        return ['success' => false, 'error' => sprintf('Element with _idx %d not found.', $element_idx)];
    }

    return wpb_finalize_write($post_id, $tree, $raw);
}
