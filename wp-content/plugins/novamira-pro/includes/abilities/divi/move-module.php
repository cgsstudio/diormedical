<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Move (reparent / reorder) a module within a post's Divi 5 tree.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-move-module', [
    'label' => __('Move Divi Module', domain: 'novamira-pro'),
    'description' => __(
        'Moves a module (and its subtree) to a new parent and/or position within a post\'s Divi block tree. "address" is the module to move; omit "new_parent" (or pass "") to move it to the top level; "position" is the index among the destination\'s children (defaults to the end). The destination nesting is validated against Divi\'s structural grammar, and a module cannot be moved into itself or its own descendant. Nesting follows Divi\'s own rule, which reads the parent\'s ATTRIBUTES as well as its name: the root takes divi/section; a regular section takes divi/row > divi/column > content and fullwidth modules, and an ordinary divi/column also takes divi/group or a nested divi/row; a fullwidth section (module.advanced.type = "fullwidth") takes content and fullwidth modules directly, with no row; a column marked module.advanced.specialtyColumns takes divi/row-inner > divi/column-inner; divi/column-inner takes content and fullwidth modules plus divi/group; divi/group takes content and fullwidth modules, another divi/group or a divi/row. A divi/post-filter-item stays inside a divi/post-filter: as its direct child or, where the site\'s Divi accepts it, deeper inside a module nested in the filter that takes content modules (e.g. post-filter > text > post-filter-item); a subtree holding such items cannot be moved out of its filter unless the destination is inside another post filter. A placement Divi rejects is refused, except a shape earlier content may already carry, which is saved with a warning. A SPECIALTY section (module.advanced.type = "specialty") is not supported here: Divi lays it out with direct divi/column children whose rendered widths depend on effective sizing these abilities cannot resolve. Placing a divi/column DIRECTLY in one is refused by set-content, which validates the whole tree you supply, and by add-module, move-module and non-Global apply-library-item, which validate the inserted subtree against its immediate destination. Those checks see the immediate parent only, so no other path guarantees the specialty ancestor is examined: applying a GLOBAL library item skips that validation entirely, and edits to the column itself, deletes, moving it out, the loop / dynamic-content / preset / interaction / display-condition writers and global variable edits may all succeed with no specialty-related warning. What does still fire: an insert is graded against its own immediate parent, so an illegal child there is refused and a tolerated one warns, and an attrs write that changes a section type or a column\'s inner-row flag is still reported. A divi/row in a specialty section saves with a warning. Rule of thumb: a page containing a specialty section is not safe to edit through these abilities — use the Visual Builder. Returns the module\'s new address. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Address of the module to move, e.g. "0/0/1".'],
            'new_parent' => [
                'type' => 'string',
                'description' => 'Address of the destination parent. Omit or "" for the top level.',
            ],
            'position' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Index among the destination\'s children. Defaults to the end.',
            ],
        ],
        'required' => ['address'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string', 'description' => 'The module\'s new address.'],
            'node_count' => ['type' => 'integer'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_move_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reparent or reorder a module. Respect nesting, which depends on the parent\'s attrs as well as its name: root > divi/section > divi/row > divi/column > content and fullwidth modules, and an ordinary column also takes divi/group or a nested divi/row; a fullwidth section (module.advanced.type = "fullwidth") holds content and fullwidth modules directly with no row; a column carrying module.advanced.specialtyColumns holds divi/row-inner > divi/column-inner. A specialty section (type = "specialty") is not supported: set-content, add-module, move-module and non-Global apply-library-item refuse a divi/column placed directly in one, but they check the immediate parent only, so no other path guarantees the specialty ancestor is seen and many may succeed without a specialty-related warning. Rule of thumb: a page containing a specialty section is not safe to edit here — use the Visual Builder. Addresses shift after a move; re-read with novamira/divi-get-content before the next address-based edit.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_move_module(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    if (trim($address) === '') {
        return divi_error('invalid_input', message: 'An "address" is required (e.g. "0/0/1").');
    }
    $new_parent = divi_string_arg($input, key: 'new_parent');

    if (divi_address_within($new_parent, $address)) {
        return divi_error(
            'invalid_tree',
            message: 'A module cannot be moved into itself or its own descendant.',
            status: 422,
        );
    }

    $tree = divi_parse_tree($post->post_content);
    $node = divi_tree_get($tree, $address);
    if ($node === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }

    $placement = divi_check_placement($tree, $new_parent, (string) $node['name']);
    if ($placement instanceof WP_Error) {
        return $placement;
    }

    // Remove from the source, then resolve the destination on the modified tree
    // (sibling indices after the source shift down by one).
    [$src_parent, $src_index] = divi_split_address($address);
    $without = divi_tree_remove($tree, $address);
    if ($without === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }

    $dest_parent = divi_adjust_address_after_removal($new_parent, $src_parent, $src_index);
    $result = divi_finalize_insert($post, $without, $dest_parent, divi_int_arg_or_null($input, key: 'position'), $node);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'post_id' => $post->ID,
        'address' => $result['address'],
        'node_count' => $result['node_count'],
        'warnings' => $result['warnings'],
    ];
}
