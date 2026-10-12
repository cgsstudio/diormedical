<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-move-element', [
    'label' => __('Move Breakdance Element', domain: 'novamira-pro'),
    'description' => __(
        'Relocates an element (and its entire subtree) inside the Breakdance canvas. `new_parent_id` may be the same as the current parent; in that case only `position` (the zero-based index inside the new parent\'s children array) is updated. Omitting `position` appends to the end. Moving an element under one of its own descendants is rejected.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'element_id' => ['type' => 'integer'],
            'new_parent_id' => ['type' => 'integer'],
            'position' => [
                'type' => 'integer',
                'description' => 'Zero-based index in the new parent\'s children. Omit to append.',
            ],
        ],
        'required' => ['post_id', 'element_id', 'new_parent_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element' => ['type' => 'object'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_move_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Same parent + new position = reorder. Different parent = move. Moving a node into its own subtree is rejected to prevent cycles.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_move_element(array $input): array|WP_Error
{
    $post_id = bd_coerce_int($input['post_id'] ?? 0);
    $element_id = bd_coerce_int($input['element_id'] ?? 0);
    $new_parent_id = bd_coerce_int($input['new_parent_id'] ?? 0);
    $bail = bd_move_validate_input($post_id, $element_id, $new_parent_id);
    if ($bail !== null) {
        return $bail;
    }
    /** @var array<string, mixed>|WP_Error $result */
    return bd_with_tree_lock($post_id, static fn(): array|WP_Error => bd_move_element_locked(
        $input,
        $post_id,
        $element_id,
        $new_parent_id,
    ));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bd_move_element_locked(array $input, int $post_id, int $element_id, int $new_parent_id): array|WP_Error
{
    $tree = bd_read_tree($post_id);
    $hit = bd_find_node($tree, $element_id);
    $new_parent_hit = bd_find_node($tree, $new_parent_id);
    $structural = bd_move_validate_structural($hit, $new_parent_hit, $element_id, $new_parent_id, $post_id);
    if ($structural !== null) {
        return $structural;
    }

    /** @var array{node: array<string, mixed>, path: list<int>} $hit */
    $old_parent_id = $hit['path'] === [] ? null : (int) $hit['path'][count($hit['path']) - 1];
    if ($old_parent_id === null) {
        return bd_error('bd_invalid_input', __('Element has no parent, refusing to move.', domain: 'novamira-pro'));
    }

    [$detached, $old_siblings] = bd_detach_from_siblings(bd_siblings_of($tree, $old_parent_id), $element_id);
    if ($detached === null) {
        return bd_error('bd_not_found', __('Element vanished mid-move.', domain: 'novamira-pro'));
    }
    bd_set_children($tree, $old_parent_id, $old_siblings);

    $new_siblings = bd_insert_at(
        bd_siblings_of($tree, $new_parent_id),
        $detached,
        isset($input['position']) ? bd_coerce_int($input['position']) : null,
    );
    bd_set_children($tree, $new_parent_id, $new_siblings);
    bd_rebuild_lookup($tree);
    $css_warning = null;
    $write = bd_write_tree($post_id, $tree, $css_warning);
    if ($write instanceof WP_Error) {
        return $write;
    }

    $result = ['success' => true, 'element' => bd_element_to_array($detached, $new_parent_id)];
    if ($css_warning !== null) {
        $result['warnings'] = [$css_warning];
    }
    return $result;
}

/**
 * Input-only validation: returns a WP_Error to bail out with, or null
 * when the ids look usable.
 */
function bd_move_validate_input(int $post_id, int $element_id, int $new_parent_id): ?WP_Error
{
    if ($post_id <= 0 || $element_id <= 0 || $new_parent_id <= 0) {
        return bd_error('bd_invalid_input', __(
            'post_id, element_id and new_parent_id must all be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    if ($element_id === 1) {
        return bd_error('bd_invalid_input', __('The root element cannot be moved.', domain: 'novamira-pro'));
    }
    if ($element_id === $new_parent_id) {
        return bd_error('bd_invalid_input', __('new_parent_id cannot equal element_id.', domain: 'novamira-pro'));
    }
    $post = bd_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    return null;
}

/**
 * Tree-structural validation: confirms both nodes exist and rejects
 * moving an element under one of its own descendants.
 *
 * @param array{node: array<string, mixed>, path: list<int>}|null $hit
 * @param array{node: array<string, mixed>, path: list<int>}|null $new_parent_hit
 */
function bd_move_validate_structural(
    ?array $hit,
    ?array $new_parent_hit,
    int $element_id,
    int $new_parent_id,
    int $post_id,
): ?WP_Error {
    if ($hit === null) {
        return bd_error('bd_not_found', sprintf(
            __('Element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $element_id,
            $post_id,
        ));
    }
    if ($new_parent_hit === null) {
        return bd_error('bd_not_found', sprintf(
            __('Destination parent %d not found.', domain: 'novamira-pro'),
            $new_parent_id,
        ));
    }
    /** @var list<array<string, mixed>> $subtree_children */
    $subtree_children = isset($hit['node']['children']) && is_array($hit['node']['children'])
        ? $hit['node']['children']
        : [];
    foreach (bd_iter_children($subtree_children, $element_id) as [$descendant]) {
        if ((int) $descendant['id'] === $new_parent_id) {
            return bd_error('bd_invalid_input', __(
                'Cannot move an element under one of its own descendants.',
                domain: 'novamira-pro',
            ));
        }
    }
    return null;
}

/**
 * Remove the element matching `$element_id` from `$siblings`. Returns
 * `[detached_node, remaining_siblings]`; `detached_node` is null when
 * no sibling matches.
 *
 * @param list<array<string, mixed>> $siblings
 * @return array{0: array<string, mixed>|null, 1: list<array<string, mixed>>}
 */
function bd_detach_from_siblings(array $siblings, int $element_id): array
{
    foreach ($siblings as $i => $sib) {
        if ((int) $sib['id'] !== $element_id) {
            continue;
        }
        $detached = $sib;
        array_splice(array: $siblings, offset: $i, length: 1);
        return [$detached, $siblings];
    }
    return [null, $siblings];
}
