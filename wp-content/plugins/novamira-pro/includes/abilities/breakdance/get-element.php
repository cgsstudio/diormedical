<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-get-element', [
    'label' => __('Get Breakdance Element', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full payload of a single Breakdance element by id: `type`, the full `properties` blob (every builder control value), `parent_id` (null for root), child summaries, and `child_count`. Call get-element-tree first to find the id you want to read, then pass the returned `properties` (or a subset of its top-level keys) back to edit-element to patch the node.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'Id of the post hosting the Breakdance canvas.'],
            'element_id' => ['type' => 'integer', 'description' => 'Id of the element in the tree.'],
        ],
        'required' => ['post_id', 'element_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_get_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The `properties` blob is element-type-specific. Same blob you pass to edit-element to patch it.',
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
function breakdance_get_element(array $input): array|WP_Error
{
    $post_id = bd_coerce_int($input['post_id'] ?? 0);
    $element_id = bd_coerce_int($input['element_id'] ?? 0);
    if ($post_id <= 0 || $element_id <= 0) {
        return bd_error('bd_invalid_input', __(
            'post_id and element_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    $post = bd_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
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
    return ['success' => true, 'element' => bd_element_to_array($hit['node'], $parent_id)];
}
