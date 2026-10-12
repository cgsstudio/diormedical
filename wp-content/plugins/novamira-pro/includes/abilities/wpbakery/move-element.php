<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Move an existing WPBakery element to a different parent and/or
 * position in one atomic call.
 *
 * Equivalent to wpbakery-delete-element + wpbakery-add-element but without
 * the _idx-renumbering footgun in between (the deletion would shift every
 * downstream _idx, so a naive delete-then-add must re-fetch get-content
 * before the second call). Move resolves both source and target inside a
 * single tree snapshot and is therefore safe for chaining.
 */

wp_register_ability('novamira/wpbakery-move-element', [
    'label' => __('Move WPBakery Element', domain: 'novamira-pro'),
    'description' => __(
        'Detaches the element identified by element_idx and reinserts it under parent_idx at position. WITHIN a single move call source and target are resolved in one tree snapshot, so the intermediate _idx renumbering that would happen with a manual delete-then-add does not bite. BETWEEN separate move calls _idx still renumbers — call wpbakery-get-content again before issuing the next move. Use null parent_idx to move to the root level; use position=-1 to append at the end of the target container. Input names mirror wpbakery-add-element — parent_idx and position refer to the TARGET (where the element ends up), element_idx refers to the SOURCE. Non-WPBakery shortcodes elsewhere in post_content are preserved and listed in `preserved_foreign_shortcodes`.',
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
                'description' => 'The _idx of the element to move (the SOURCE, from wpbakery-get-content).',
            ],
            'parent_idx' => [
                'type' => ['integer', 'null'],
                'description' => 'The _idx of the TARGET parent container, or null to move to the root level. Same semantics as on wpbakery-add-element.',
                'default' => null,
            ],
            'position' => [
                'type' => 'integer',
                'description' => 'Zero-based position inside the target parent\'s children array. -1 appends at the end. Same semantics as on wpbakery-add-element.',
                'default' => -1,
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
                'description' => 'The post-write tree with fresh _idx values — same shape wpbakery-get-content returns. Indices renumber after the move, so use this to chain the next move without an extra get-content round-trip.',
            ],
            'preserved_foreign_shortcodes' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_move_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Atomic move within a single tree snapshot — no need to re-fetch get-content between the implicit delete and the implicit insert. Refuses to move an element into its own descendant.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     post_id?: int,
 *     element_idx?: int,
 *     parent_idx?: int|null,
 *     position?: int,
 * } $input
 * @return array<string, mixed>
 */
function wpbakery_move_element(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $element_idx = (int) ($input['element_idx'] ?? -1);
    $new_parent_idx = isset($input['parent_idx']) ? (int) $input['parent_idx'] : null;
    $new_position = (int) ($input['position'] ?? -1);

    $writable_error = wpb_post_writable_or_error($post_id);
    if ($writable_error !== null) {
        return $writable_error;
    }

    $raw = wpb_get_post_content($post_id) ?? '';
    $tree = wpb_parse_indexed_tree($raw);

    // Snapshot the source. PHP arrays are value types, so this is a deep copy.
    $source_copy = wpb_find_node_by_idx($tree, $element_idx);
    if ($source_copy === null) {
        return ['success' => false, 'error' => sprintf('Source element with _idx %d not found.', $element_idx)];
    }

    // Refuse moves into the source's own descendant — would create a cycle
    // and corrupt the tree.
    if ($new_parent_idx !== null && wpb_subtree_contains_idx($source_copy, $new_parent_idx)) {
        return [
            'success' => false,
            'error' => sprintf(
                'Cannot move element _idx %d into _idx %d: target is a descendant of the source.',
                $element_idx,
                $new_parent_idx,
            ),
        ];
    }

    if (!wpb_delete_node($tree, $element_idx)) {
        return ['success' => false, 'error' => 'Failed to detach source element.'];
    }

    if (!wpb_insert_node($tree, $source_copy, $new_parent_idx, $new_position)) {
        return [
            'success' => false,
            'error' => sprintf('Target parent with _idx %d not found.', $new_parent_idx ?? -1),
        ];
    }

    // A move can land a tag inside another of its own kind, which WordPress
    // cannot render. Nothing has been written yet, so refusing here leaves
    // the post as it was.
    $nesting_error = wpb_self_nesting_error($tree);
    if ($nesting_error !== null) {
        return $nesting_error;
    }

    return wpb_finalize_write($post_id, $tree, $raw);
}

/**
 * Returns true when $node or any of its (transitive) children carry the
 * given `_idx`. Used to detect "move into own descendant" before performing
 * the detach.
 *
 * @param array<string, mixed> $node
 */
function wpb_subtree_contains_idx(array $node, int $idx): bool
{
    if (($node['_idx'] ?? -1) === $idx) {
        return true;
    }
    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['children'] ?? null) ? $node['children'] : [];
    foreach ($children as $child) {
        if (wpb_subtree_contains_idx($child, $idx)) {
            return true;
        }
    }
    return false;
}
