<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-delete-element', [
    'label' => __('Delete Breakdance Element', domain: 'novamira-pro'),
    'description' => __(
        'Removes an element from the Breakdance element tree, along with its entire subtree (every descendant). Deletion is immediate, there is no trash for canvas nodes. The root (id `1`) cannot be deleted; to wipe a canvas use create-template or empty `_breakdance_data` via direct API instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
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
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_delete_element',
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
function breakdance_delete_element(array $input): array|WP_Error
{
    $post_id = bd_coerce_int($input['post_id'] ?? 0);
    $element_id = bd_coerce_int($input['element_id'] ?? 0);
    if ($post_id <= 0 || $element_id <= 0) {
        return bd_error('bd_invalid_input', __(
            'post_id and element_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    if ($element_id === 1) {
        return bd_error('bd_invalid_input', __('The root element cannot be deleted.', domain: 'novamira-pro'));
    }
    $post = bd_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    /** @var array<string, mixed>|WP_Error $result */
    return bd_with_tree_lock($post_id, static fn(): array|WP_Error => bd_delete_element_locked($post_id, $element_id));
}

/**
 * @return array<string, mixed>|WP_Error
 */
function bd_delete_element_locked(int $post_id, int $element_id): array|WP_Error
{
    $tree = bd_read_tree($post_id);
    $hit = bd_find_node($tree, $element_id);
    if ($hit === null) {
        return bd_error('bd_not_found', sprintf(
            __('Element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $element_id,
            $post_id,
        ));
    }

    $parent_id = $hit['path'] === [] ? null : (int) $hit['path'][count($hit['path']) - 1];
    if ($parent_id === null) {
        return bd_error('bd_invalid_input', __('Element has no parent, refusing to delete.', domain: 'novamira-pro'));
    }

    $parent_hit = bd_find_node($tree, $parent_id);
    /** @var list<array<string, mixed>> $siblings */
    $siblings = isset($parent_hit['node']['children']) && is_array($parent_hit['node']['children'])
        ? $parent_hit['node']['children']
        : [];
    $filtered = array_values(array_filter($siblings, static fn(array $n): bool => (int) $n['id'] !== $element_id));

    bd_set_children($tree, $parent_id, $filtered);
    bd_rebuild_lookup($tree);
    $css_warning = null;
    $write = bd_write_tree($post_id, $tree, $css_warning);
    if ($write instanceof WP_Error) {
        return $write;
    }

    $result = ['success' => true, 'deleted_id' => $element_id, 'parent_id' => $parent_id];
    if ($css_warning !== null) {
        $result['warnings'] = [$css_warning];
    }
    return $result;
}
