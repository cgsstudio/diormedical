<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-delete-element', [
    'label' => __('Delete Oxygen Element', domain: 'novamira-pro'),
    'description' => __(
        'Removes an element from the Oxygen element tree, along with its entire subtree (every descendant). Deletion is immediate — there is no trash for canvas nodes. The root (id `1`) cannot be deleted; to clear a whole canvas call novamira/oxygen-set-content with `elements: []` (the supported operation) rather than deleting the root.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'element_id' => ['type' => 'integer'],
        ],
        'required' => ['post_id', 'element_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'deleted_id' => ['type' => 'integer'],
            'parent_id' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_delete_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Recursive: deletes the entire subtree rooted at element_id. There is no undo. Use get-element-tree first to confirm what will be removed.',
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
function oxygen_delete_element(array $input): array|WP_Error
{
    $post_id = ox_coerce_int($input['post_id'] ?? 0);
    $element_id = ox_coerce_int($input['element_id'] ?? 0);
    if ($post_id <= 0 || $element_id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'post_id and element_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    if ($element_id === 1) {
        return ox_error('oxygen_invalid_input', __('The root element cannot be deleted.', domain: 'novamira-pro'));
    }
    $post = ox_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_tree_lock($post_id, static fn(): array|WP_Error => ox_delete_element_locked($post_id, $element_id));
}

/**
 * @return array<string, mixed>|WP_Error
 */
function ox_delete_element_locked(int $post_id, int $element_id): array|WP_Error
{
    $tree = ox_read_tree($post_id);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    $hit = ox_find_node($tree, $element_id);
    if ($hit === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $element_id,
            $post_id,
        ));
    }

    $parent_id = $hit['path'] === [] ? null : (int) $hit['path'][count($hit['path']) - 1];
    if ($parent_id === null) {
        return ox_error('oxygen_invalid_input', __(
            'Element has no parent, refusing to delete.',
            domain: 'novamira-pro',
        ));
    }

    $parent_hit = ox_find_node($tree, $parent_id);
    /** @var list<array<string, mixed>> $siblings */
    $siblings = isset($parent_hit['node']['children']) && is_array($parent_hit['node']['children'])
        ? $parent_hit['node']['children']
        : [];
    $filtered = array_values(array_filter($siblings, static fn(array $n): bool => (int) $n['id'] !== $element_id));

    ox_set_children($tree, $parent_id, $filtered);
    ox_rebuild_lookup($tree);
    $write = ox_write_tree($post_id, $tree);
    if ($write instanceof WP_Error) {
        return $write;
    }

    return ['success' => true, 'deleted_id' => $element_id, 'parent_id' => $parent_id];
}
