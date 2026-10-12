<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;
use WP_Post;

/**
 * Abilities: save reusable layouts to the Divi Library and insert them into
 * pages.
 *
 * A library item is an `et_pb_layout` post whose `post_content` holds a Divi
 * subtree (a section, row, or module — or a whole layout). Save carves a subtree
 * out of an existing page; apply inserts a copy into a target page (validated
 * against the structural skeleton). Built on the shared tree helpers.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The Divi Library post type.
 */
function divi_library_post_type(): string
{
    return 'et_pb_layout';
}

/**
 * A WP_Error when the Divi Library post type is unavailable, else null.
 */
function divi_library_available_or_error(): ?WP_Error
{
    if (!post_type_exists(divi_library_post_type())) {
        return divi_error(
            'unavailable',
            message: 'The Divi Library (et_pb_layout) is unavailable on this site.',
            status: 422,
        );
    }
    return null;
}

/**
 * The Divi Library `layout_type` slug for a subtree root node.
 *
 * @param array<string, mixed> $node
 */
function divi_library_type_for_node(array $node): string
{
    $name = is_string($node['name'] ?? null) ? $node['name'] : '';
    if (in_array($name, ['divi/section', 'divi/fullwidth-section', 'divi/specialty-section'], strict: true)) {
        return 'section';
    }
    if (in_array($name, ['divi/row', 'divi/row-inner'], strict: true)) {
        return 'row';
    }
    return 'module';
}

/**
 * Resolve and validate a library item post by id.
 *
 * @return WP_Post|WP_Error
 */
function divi_library_item_or_error(?int $id)
{
    if ($id === null || $id <= 0) {
        return divi_error(
            'invalid_input',
            message: 'A numeric "library_id" is required (from novamira/divi-list-library-items).',
        );
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post || $post->post_type !== divi_library_post_type()) {
        return divi_error('not_found', message: sprintf('No Divi Library item with id %d.', $id), status: 404);
    }
    return $post;
}

/**
 * Resolve the nodes + library type to save: the subtree at `$address`, or the
 * whole page when `$address` is empty. Returns `[nodes, type]` or a WP_Error.
 *
 * @param list<array<string, mixed>> $tree
 * @return array{0: list<array<string, mixed>>, 1: string}|WP_Error
 */
function divi_library_source_nodes(array $tree, string $address)
{
    if ($address !== '') {
        $node = divi_tree_get($tree, $address);
        if ($node === null) {
            return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
        }
        return [[$node], divi_library_type_for_node($node)];
    }
    if ($tree === []) {
        return divi_error('invalid_input', message: 'The source page has no Divi content to save.');
    }
    return [$tree, count($tree) === 1 ? divi_library_type_for_node($tree[0]) : 'layout'];
}

/**
 * Whether a library item is Global (scope = global) — inserted as a live-linked
 * reference rather than a copy.
 */
function divi_library_is_global(int $library_id): bool
{
    $terms = get_the_terms($library_id, taxonomy: 'scope');
    if (!is_array($terms)) {
        return false;
    }
    foreach ($terms as $term) {
        if ($term->slug === 'global') {
            return true;
        }
    }
    return false;
}

wp_register_ability('novamira/divi-list-library-items', [
    'label' => __('List Divi Library Items', domain: 'novamira-pro'),
    'description' => __(
        'Lists the saved Divi Library items (reusable layouts/sections/rows/modules), as rows: id, title, type ("section", "row", "module", or "layout"), and global (true = a live-linked Global item; editing it updates every page using it). Insert one into a page with novamira/divi-apply-library-item. A library item id is an ordinary Divi post — edit its content with the normal content/module abilities by passing the id as the post.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'items' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_library_items',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover reusable Divi Library items before inserting one with divi-apply-library-item.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-create-library-item', [
    'label' => __('Create Divi Library Item', domain: 'novamira-pro'),
    'description' => __(
        'Saves a reusable layout to the Divi Library and returns its library_id. Pass "post" (the source page) and an optional "address" to save just that subtree (e.g. "0" for the first section); omit "address" to save the whole page. Give it a "title". Set "global": true to save it as a Global item — instances stay linked to it, so a later edit of the saved item updates every page using it (default is a plain copy on insert). Reuse it with novamira/divi-apply-library-item. Accepts the source post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Source post id to save from.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => [
                'type' => 'string',
                'description' => 'Subtree address to save (e.g. "0"); omit to save the whole page.',
            ],
            'title' => ['type' => 'string', 'description' => 'Library item name.'],
            'global' => [
                'type' => 'boolean',
                'description' => 'Save as a Global item — instances stay linked (edit once, updates everywhere). Default false (a copy on insert).',
            ],
        ],
        'required' => ['title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'library_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'global' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_create_library_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Save a section/row/module (by address) or a whole page to the Library so it can be reused with divi-apply-library-item.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/divi-apply-library-item', [
    'label' => __('Apply Divi Library Item', domain: 'novamira-pro'),
    'description' => __(
        'Inserts a saved Divi Library item (by "library_id") into the page at "post", under the "parent" address (omit "parent" for the top level — a saved section), at an optional "position". A regular item is inserted as a copy (nesting validated: a section at the top level, a row under a column, …); a Global item is inserted once as a live-linked reference, so editing the library item later updates this page too. Returns the new module address(es). Accepts the target post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'library_id' => ['type' => 'integer', 'description' => 'Library item id (from divi-list-library-items).'],
            'post' => ['type' => 'integer', 'description' => 'Target post id to insert into.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'parent' => ['type' => 'string', 'description' => 'Parent address (e.g. "0/0/0"); omit for the top level.'],
            'position' => [
                'type' => 'integer',
                'description' => 'Index among the parent\'s children (default: append).',
            ],
        ],
        'required' => ['library_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'inserted' => ['type' => 'integer'],
            'addresses' => ['type' => 'array', 'items' => ['type' => 'string']],
            'global' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_apply_library_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Insert a reusable Library item into a page. Pick a "parent" the item can nest under (top level for a section). Re-read the tree afterwards — addresses shift.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/divi-delete-library-item', [
    'label' => __('Delete Divi Library Item', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a saved Divi Library item by "library_id" (from novamira/divi-list-library-items). Pages where it was already inserted keep their copy.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'library_id' => ['type' => 'integer', 'description' => 'Library item id to delete.'],
        ],
        'required' => ['library_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'library_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_delete_library_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanently removes a saved Library item. Pages already using it keep their inserted copy.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function divi_list_library_items(array $input): array
{
    $rows = [];
    if (!post_type_exists(divi_library_post_type())) {
        return ['items' => [], 'total' => 0];
    }
    /** @var mixed $posts */
    $posts = get_posts([
        'post_type' => divi_library_post_type(),
        'numberposts' => -1,
        'post_status' => 'any',
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    if (!is_array($posts)) {
        return ['items' => [], 'total' => 0];
    }
    /** @var mixed $post */
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $type = '';
        $terms = get_the_terms($post->ID, taxonomy: 'layout_type');
        if (is_array($terms) && isset($terms[0])) {
            $type = $terms[0]->slug;
        }
        $rows[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'type' => $type,
            'global' => divi_library_is_global($post->ID),
        ];
    }
    return ['items' => $rows, 'total' => count($rows)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_create_library_item(array $input)
{
    $unavailable = divi_library_available_or_error();
    if ($unavailable instanceof WP_Error) {
        return $unavailable;
    }

    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $tree = divi_parse_tree($post->post_content);
    $source = divi_library_source_nodes($tree, trim(divi_string_arg($input, key: 'address')));
    if ($source instanceof WP_Error) {
        return $source;
    }
    [$nodes, $type] = $source;

    $title = trim(divi_string_arg($input, key: 'title'));
    if ($title === '') {
        $title = 'Library Item';
    }

    $library_id = wp_insert_post([
        'post_type' => divi_library_post_type(),
        'post_status' => 'publish',
        'post_title' => $title,
    ]);
    if (0 === $library_id) {
        return divi_error('create_failed', message: 'Could not create the Divi Library item.', status: 500);
    }

    /** @var mixed $library_post */
    $library_post = get_post($library_id);
    if (!$library_post instanceof WP_Post) {
        return divi_error(
            'create_failed',
            message: 'The Divi Library item could not be loaded after creation.',
            status: 500,
        );
    }

    $saved = divi_save_tree($library_post, $nodes);
    if ($saved instanceof WP_Error) {
        return $saved;
    }
    wp_set_object_terms($library_id, $type, taxonomy: 'layout_type');

    $global = ($input['global'] ?? false) === true;
    wp_set_object_terms($library_id, $global ? 'global' : 'not_global', taxonomy: 'scope');

    return ['library_id' => $library_id, 'title' => $title, 'type' => $type, 'global' => $global];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_apply_library_item(array $input)
{
    $unavailable = divi_library_available_or_error();
    if ($unavailable instanceof WP_Error) {
        return $unavailable;
    }

    $library_post = divi_library_item_or_error(divi_int_arg_or_null($input, key: 'library_id'));
    if ($library_post instanceof WP_Error) {
        return $library_post;
    }
    $library_tree = divi_parse_tree($library_post->post_content);
    if ($library_tree === []) {
        return divi_error('invalid_input', message: 'The Divi Library item has no content to insert.');
    }

    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $parent = divi_string_arg($input, key: 'parent');
    $position = divi_int_arg_or_null($input, key: 'position');

    // A Global item is inserted once as a live-linked reference (a
    // divi/global-layout block whose content resolves from the library post at
    // render), not copied — so editing the library item updates every page.
    if (divi_library_is_global($library_post->ID)) {
        $tree = divi_parse_tree($post->post_content);
        $reference = ['name' => 'divi/global-layout', 'attrs' => ['globalModule' => (string) $library_post->ID]];
        $updated = divi_tree_insert($tree, $parent, $position ?? PHP_INT_MAX, $reference);
        if ($updated === null) {
            return divi_error('not_found', message: sprintf('No parent at address "%s".', $parent), status: 404);
        }
        $saved = divi_save_tree($post, $updated);
        if ($saved instanceof WP_Error) {
            return $saved;
        }
        return ['post_id' => $post->ID, 'inserted' => 1, 'addresses' => [], 'global' => true];
    }

    $addresses = [];
    $warnings = [];
    foreach ($library_tree as $node) {
        /** @var mixed $fresh */
        $fresh = get_post($post->ID);
        if (!$fresh instanceof WP_Post) {
            return divi_error('not_found', message: 'The target post could not be reloaded.', status: 404);
        }
        $result = divi_finalize_insert($fresh, divi_parse_tree($fresh->post_content), $parent, $position, $node);
        if ($result instanceof WP_Error) {
            return $result;
        }
        $addresses[] = $result['address'];
        $warnings = [...$warnings, ...$result['warnings']];
    }

    return [
        'post_id' => $post->ID,
        'inserted' => count($addresses),
        'addresses' => $addresses,
        'global' => false,
        'warnings' => divi_cap_messages($warnings),
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_delete_library_item(array $input)
{
    $unavailable = divi_library_available_or_error();
    if ($unavailable instanceof WP_Error) {
        return $unavailable;
    }

    $library_post = divi_library_item_or_error(divi_int_arg_or_null($input, key: 'library_id'));
    if ($library_post instanceof WP_Error) {
        return $library_post;
    }

    wp_delete_post($library_post->ID, force_delete: true);

    return ['library_id' => $library_post->ID, 'deleted' => true];
}
