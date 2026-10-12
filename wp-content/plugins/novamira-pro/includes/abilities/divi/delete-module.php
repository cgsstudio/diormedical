<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Delete a module (and its subtree) from a post's Divi 5 tree.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-delete-module', [
    'label' => __('Delete Divi Module', domain: 'novamira-pro'),
    'description' => __(
        'Removes a module and all of its descendants from a post\'s Divi 5 tree by address (e.g. "0/1"). Returns how many modules were removed. Addresses of later siblings shift down after a delete — re-read with novamira/divi-get-content before further edits. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Address of the module to delete, e.g. "0/1".'],
        ],
        'required' => ['address'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'removed' => ['type' => 'integer'],
            'node_count' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_delete_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes a module and everything inside it. Sibling addresses after the removed one shift down by one; re-read the tree before the next address-based edit.',
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
function divi_delete_module(array $input)
{
    $post = divi_post_or_error($input['post'] ?? $input['post_id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = is_string($input['address'] ?? null) ? $input['address'] : '';
    if (trim($address) === '') {
        return divi_error('invalid_input', message: 'An "address" is required (e.g. "0/1").');
    }

    $tree = divi_parse_tree($post->post_content);
    $node = divi_tree_get($tree, $address);
    if ($node === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }
    if (!divi_is_divi_node($node)) {
        return divi_error(
            'not_a_module',
            message: sprintf('Address "%s" is a non-Divi block, not a deletable Divi module.', $address),
            status: 422,
        );
    }

    $removed = divi_tree_count([$node]);

    $updated = divi_tree_remove($tree, $address);
    if ($updated === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }

    $count = divi_save_tree($post, $updated);
    if ($count instanceof WP_Error) {
        return $count;
    }

    return ['post_id' => $post->ID, 'removed' => $removed, 'node_count' => $count];
}
