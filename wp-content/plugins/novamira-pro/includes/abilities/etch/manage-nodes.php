<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;
use WP_Post;

/**
 * Node-scoped content abilities: add / edit / delete / move a single node in an
 * Etch page tree, addressed by its index path (the same compact tree
 * novamira/etch-get-content returns) — so an agent can make a targeted edit
 * without re-sending (or even holding) the whole tree.
 *
 * These operate on the compact tree and re-serialize through the same path as
 * set-content; like set-content they refuse a lossy page (freeform HTML between
 * child blocks) unless force:true, since re-serializing would drop it.
 */

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// Shared helpers (file-local)
// ---------------------------------------------------------------------------

/**
 * Parse an address like "0/1/2" into a list of integer indices. "" / "root"
 * → [] (the page root). Returns null when malformed.
 *
 * @return list<int>|null
 */
function etch_node_address_parts(string $address): ?array
{
    $address = trim($address);
    if ($address === '' || $address === 'root') {
        return [];
    }
    $parts = [];
    foreach (explode('/', $address) as $seg) {
        $seg = trim($seg);
        if ($seg === '' || !ctype_digit($seg)) {
            return null;
        }
        $parts[] = (int) $seg;
    }
    return $parts;
}

/**
 * Whether a node exists at the given index path.
 *
 * @param array<array-key, mixed> $tree
 * @param list<int>               $parts
 */
function etch_node_exists(array $tree, array $parts): bool
{
    if ($parts === []) {
        return false;
    }
    $cursor = $tree;
    foreach ($parts as $i) {
        if (!isset($cursor[$i]) || !is_array($cursor[$i])) {
            return false;
        }
        /** @var mixed $children */
        $children = $cursor[$i]['children'] ?? [];
        $cursor = is_array($children) ? $children : [];
    }
    return true;
}

/**
 * Return the node (a copy) at the given index path, or null when it does not
 * resolve.
 *
 * @param array<array-key, mixed> $tree
 * @param list<int>               $parts
 * @return array<string, mixed>|null
 */
function etch_node_get(array $tree, array $parts): ?array
{
    if ($parts === []) {
        return null;
    }
    $found = null;
    $cursor = $tree;
    foreach ($parts as $i) {
        if (!isset($cursor[$i]) || !is_array($cursor[$i])) {
            return null;
        }
        $found = $cursor[$i];
        /** @var mixed $children */
        $children = $found['children'] ?? [];
        $cursor = is_array($children) ? $children : [];
    }
    /** @var array<string, mixed>|null $found */
    return $found;
}

/**
 * Clamp an insert position into [0, $count].
 */
function etch_node_clamp(?int $pos, int $count): int
{
    if ($pos === null || $pos > $count) {
        return $count;
    }
    if ($pos < 0) {
        return 0;
    }
    return $pos;
}

/**
 * Insert $node into a (re-indexed) children list at $pos (null = append).
 *
 * @param array<array-key, mixed>  $children
 * @param array<string, mixed>     $node
 * @return list<mixed>
 */
function etch_node_splice_in(array $children, array $node, ?int $pos): array
{
    $list = array_values($children);
    $at = etch_node_clamp($pos, count($list));
    array_splice($list, offset: $at, length: 0, replacement: [$node]);
    return $list;
}

/**
 * Remove the node at $parts, returning the rebuilt tree (or null on a bad path).
 *
 * @param array<array-key, mixed> $tree
 * @param list<int>               $parts
 * @return list<mixed>|null
 */
function etch_node_remove(array $tree, array $parts): ?array
{
    if ($parts === []) {
        return null;
    }
    $i = $parts[0];
    if (!array_key_exists($i, $tree)) {
        return null;
    }
    if (count($parts) === 1) {
        $list = array_values($tree);
        array_splice($list, offset: $i, length: 1);
        return $list;
    }
    /** @var mixed $node */
    $node = $tree[$i];
    if (!is_array($node)) {
        return null;
    }
    /** @var mixed $children */
    $children = $node['children'] ?? [];
    $new = etch_node_remove(is_array($children) ? $children : [], array_slice($parts, offset: 1));
    if ($new === null) {
        return null;
    }
    $node['children'] = $new;
    if ($new === []) {
        unset($node['children']);
    }
    $tree[$i] = $node;
    return array_values($tree);
}

/**
 * Insert $node into the children list of the container at $parts ([] = root),
 * returning the rebuilt tree (or null on a bad path).
 *
 * @param array<array-key, mixed> $tree
 * @param list<int>               $parts
 * @param array<string, mixed>    $node
 * @return list<mixed>|null
 */
function etch_node_insert(array $tree, array $parts, array $node, ?int $pos): ?array
{
    if ($parts === []) {
        return etch_node_splice_in($tree, $node, $pos);
    }
    $i = $parts[0];
    if (!array_key_exists($i, $tree)) {
        return null;
    }
    /** @var mixed $parent */
    $parent = $tree[$i];
    if (!is_array($parent)) {
        return null;
    }
    /** @var mixed $children */
    $children = $parent['children'] ?? [];
    $children = is_array($children) ? $children : [];
    if (count($parts) === 1) {
        $parent['children'] = etch_node_splice_in($children, $node, $pos);
        $tree[$i] = $parent;
        return array_values($tree);
    }
    $new = etch_node_insert($children, array_slice($parts, offset: 1), $node, $pos);
    if ($new === null) {
        return null;
    }
    $parent['children'] = $new;
    $tree[$i] = $parent;
    return array_values($tree);
}

/**
 * Merge an attribute patch over a node's existing attrs: top-level keys
 * shallow-merge (a key set to null is dropped); the HTML `attributes` sub-object
 * deep-merges, so attributes the patch omits (notably `class`) are preserved.
 *
 * @param array<string, mixed> $node
 * @param array<string, mixed> $patch
 * @return array<string, mixed>
 */
function etch_node_merge_attrs(array $node, array $patch): array
{
    /** @var mixed $existing */
    $existing = $node['attrs'] ?? [];
    $existing = is_array($existing) ? $existing : [];
    // Drop keys whose patch value is null; merge the rest over the existing set.
    foreach (array_keys(array_filter($patch, static fn($v): bool => $v === null)) as $drop) {
        unset($existing[$drop]);
    }
    $additions = array_filter($patch, static fn($v): bool => $v !== null);
    // Deep-merge the HTML `attributes` sub-object so a partial edit keeps the
    // attributes it did not resend — notably `class`, which binds the element to
    // its Etch style records. A nested value of null removes that one attribute.
    /** @var mixed $patch_attributes */
    $patch_attributes = $additions['attributes'] ?? null;
    /** @var mixed $existing_attributes */
    $existing_attributes = $existing['attributes'] ?? null;
    if (is_array($patch_attributes) && is_array($existing_attributes)) {
        $additions['attributes'] = etch_merge_html_attributes($existing_attributes, $patch_attributes);
    }
    $current = array_merge($existing, $additions);
    $node['attrs'] = $current;
    if ($current === []) {
        unset($node['attrs']);
    }
    return $node;
}

/**
 * Deep-merge an HTML-attributes patch over the existing set: a patched key wins,
 * a null value removes that attribute, and attributes the patch omits (notably
 * `class`) are kept. Split out so a partial edit-node attribute change does not
 * drop the class that binds an element to its Etch style records.
 *
 * @param array<array-key, mixed> $existing
 * @param array<array-key, mixed> $patch
 * @return array<array-key, mixed>
 */
function etch_merge_html_attributes(array $existing, array $patch): array
{
    foreach (array_keys($patch) as $key) {
        /** @var mixed $value */
        $value = $patch[$key];
        if ($value === null) {
            unset($existing[$key]);

            continue;
        }
        $existing[$key] = $value;
    }

    return $existing;
}

/**
 * Apply the edit fields from the ability input ($block / $attrs / $html) to a
 * node copy, returning it. Children are untouched.
 *
 * @param array<string, mixed> $node
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function etch_node_apply_edit(array $node, array $input): array
{
    /** @var mixed $block_raw */
    $block_raw = $input['block'] ?? null;
    if (is_string($block_raw) && $block_raw !== '') {
        $node['block'] = $block_raw;
    }
    if (array_key_exists('attrs', $input)) {
        /** @var mixed $attrs_raw */
        $attrs_raw = $input['attrs'] ?? [];
        /** @var array<string, mixed> $attrs_in */
        $attrs_in = is_array($attrs_raw) ? $attrs_raw : [];
        $node = etch_node_merge_attrs($node, $attrs_in);
    }
    if (array_key_exists('html', $input)) {
        /** @var mixed $html_raw */
        $html_raw = $input['html'] ?? null;
        unset($node['html']);
        if (is_string($html_raw) && $html_raw !== '') {
            $node['html'] = $html_raw;
        }
    }
    return $node;
}

/**
 * Replace the node at $parts with $new, returning the rebuilt tree (or null on
 * a bad path).
 *
 * @param array<array-key, mixed> $tree
 * @param list<int>               $parts
 * @param array<string, mixed>    $new
 * @return list<mixed>|null
 */
function etch_node_set_at(array $tree, array $parts, array $new): ?array
{
    if ($parts === []) {
        return null;
    }
    $i = $parts[0];
    if (!array_key_exists($i, $tree)) {
        return null;
    }
    if (count($parts) === 1) {
        $tree[$i] = $new;
        return array_values($tree);
    }
    /** @var mixed $node */
    $node = $tree[$i];
    if (!is_array($node)) {
        return null;
    }
    /** @var mixed $children */
    $children = $node['children'] ?? [];
    $nc = etch_node_set_at(is_array($children) ? $children : [], array_slice($parts, offset: 1), $new);
    if ($nc === null) {
        return null;
    }
    $node['children'] = $nc;
    $tree[$i] = $node;
    return array_values($tree);
}

/**
 * Load a post's content as a full compact tree, enforcing the lossy guard
 * shared with set-content. Returns [post, tree] or a WP_Error.
 *
 * @param array<string, mixed> $input
 * @return array{0: WP_Post, 1: list<mixed>}|WP_Error
 */
function etch_node_load(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }
    $post = etch_post_or_error((int) ($input['post_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }
    $blocks = parse_blocks($post->post_content);
    if (($input['force'] ?? null) !== true) {
        $interleaved = etch_count_interleaved($blocks);
        if ($interleaved > 0) {
            return etch_error('etch_lossy_overwrite', sprintf(
                /* translators: %d: number of freeform HTML fragments. */
                __(
                    'This post holds %d freeform HTML fragment(s) between child blocks that the compact tree cannot represent; a node write re-serializes the page and would drop them. Re-read with novamira/etch-get-content (lossy:true); pass force:true only if you accept losing them.',
                    domain: 'novamira-pro',
                ),
                $interleaved,
            ));
        }
    }
    $count = 0;
    $tree = etch_blocks_to_tree($blocks, ETCH_MAX_TREE_NODES, $count);
    return [$post, $tree];
}

/**
 * Validate + serialize the mutated tree and persist it (mirrors set-content's
 * slash-safe write), returning the ability payload or a WP_Error.
 *
 * @param list<mixed> $tree
 * @return array<string, mixed>|WP_Error
 */
function etch_node_persist(WP_Post $post, array $tree): array|WP_Error
{
    $node_count = 0;
    $markup = etch_tree_to_markup_or_error($tree, $node_count);
    if ($markup instanceof WP_Error) {
        return $markup;
    }
    $result = wp_update_post(['ID' => $post->ID, 'post_content' => wp_slash($markup)], wp_error: true);
    if ($result instanceof WP_Error) {
        return $result;
    }
    clean_post_cache($post->ID);
    return ['post_id' => $post->ID, 'node_count' => $node_count, 'bytes' => strlen($markup)];
}

/**
 * Shared input fragment for the post + force keys.
 *
 * @return array<string, mixed>
 */
function etch_node_common_input(): array
{
    return [
        'post_id' => ['type' => 'integer', 'description' => 'The page/template/component post id.'],
        'force' => [
            'type' => 'boolean',
            'description' => 'Proceed even when the page is lossy (freeform HTML between blocks would be dropped). Default false.',
        ],
    ];
}

/**
 * Shared output schema for a node write.
 *
 * @return array<string, mixed>
 */
function etch_node_output_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'node_count' => ['type' => 'integer'],
            'bytes' => ['type' => 'integer'],
        ],
    ];
}

// ---------------------------------------------------------------------------
// add-node
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-add-node', [
    'label' => __('Add Etch Node', domain: 'novamira-pro'),
    'description' => __(
        'Inserts one node into an existing container in an Etch page, without re-sending the whole tree. `parent_address` is the index path of the container to insert into ("" / omitted = the page root, e.g. "0/1" = second child of the first top-level node); `node` is a compact node `{ block, attrs?, children?, html? }` (same shape as set-content); `position` is the 0-based index among the parent\'s children (omitted = append). Addresses match the tree novamira/etch-get-content returns. Re-serializes the page; refuses a lossy page unless force:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => etch_node_common_input()
            + [
                'parent_address' => [
                    'type' => 'string',
                    'description' => 'Index path of the container to insert into. "" or omitted = page root.',
                ],
                'node' => [
                    'type' => 'object',
                    'description' => 'The compact node to insert: { block, attrs?, children?, html? }.',
                ],
                'position' => [
                    'type' => 'integer',
                    'description' => '0-based insert index among the parent\'s children. Omitted = append.',
                ],
            ],
        'required' => ['post_id', 'node'],
        'additionalProperties' => false,
    ],
    'output_schema' => etch_node_output_schema(),
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_add_node',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use after get-content to insert a node without rewriting the page. parent_address addresses a CONTAINER node (or "" for root); position places the new node among that container\'s existing children. The node is validated like set-content. Structural writes renumber index paths — re-read with get-content before the next address-based call.',
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
function etch_add_node(array $input): array|WP_Error
{
    $loaded = etch_node_load($input);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    [$post, $tree] = $loaded;

    /** @var mixed $node */
    $node = $input['node'] ?? null;
    if (!is_array($node) || !isset($node['block'])) {
        return etch_error('etch_invalid_input', __(
            '`node` must be an object with a `block` key.',
            domain: 'novamira-pro',
        ));
    }

    $parts = etch_node_address_parts((string) ($input['parent_address'] ?? ''));
    if ($parts === null) {
        return etch_error('etch_invalid_input', __(
            '`parent_address` must be a slash-separated index path (e.g. "0/1") or "" for the root.',
            domain: 'novamira-pro',
        ));
    }
    if ($parts !== [] && !etch_node_exists($tree, $parts)) {
        return etch_error('etch_not_found', __('No container at the given parent_address.', domain: 'novamira-pro'));
    }

    $position = isset($input['position']) ? (int) $input['position'] : null;
    /** @var array<string, mixed> $node */
    $new_tree = etch_node_insert($tree, $parts, $node, $position);
    if ($new_tree === null) {
        return etch_error('etch_not_found', __('Could not resolve parent_address.', domain: 'novamira-pro'));
    }
    return etch_node_persist($post, $new_tree);
}

// ---------------------------------------------------------------------------
// edit-node
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-node', [
    'label' => __('Edit Etch Node', domain: 'novamira-pro'),
    'description' => __(
        'Edits one node in place at its index `address` (e.g. "0/1/2"), without re-sending the whole tree. Partial-merge: `attrs` (if given) is merged over the node\'s existing attrs — top-level keys shallow-merge (pass a key as null to drop it), while the HTML `attributes` sub-object deep-merges so attributes you do not resend (notably `class`) are kept; set a nested attribute to null to remove just that one. `html` (if given) replaces a leaf node\'s html; `block` (if given) changes the block type. Children are left untouched. Addresses match novamira/etch-get-content. Re-serializes the page; refuses a lossy page unless force:true. Adding a class that has a per-class Etch style record auto-binds the record\'s id into the node\'s styles, so the class stays editable in Etch.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => etch_node_common_input()
            + [
                'address' => ['type' => 'string', 'description' => 'Index path of the node to edit, e.g. "0/1/2".'],
                'attrs' => [
                    'type' => 'object',
                    'description' => 'Merged over the node\'s existing attrs: top-level keys shallow-merge (a key set to null is removed); the HTML `attributes` sub-object deep-merges, so attributes you omit (e.g. class) are kept and a nested attribute set to null is removed.',
                ],
                'html' => ['type' => 'string', 'description' => 'Replacement html for a leaf node.'],
                'block' => [
                    'type' => 'string',
                    'description' => 'New block type (e.g. "etch/element"). Rarely needed.',
                ],
            ],
        'required' => ['post_id', 'address'],
        'additionalProperties' => false,
    ],
    'output_schema' => etch_node_output_schema(),
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_node',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Targeted in-place edit. Top-level attrs keys merge (null drops a key); the `attributes` sub-object deep-merges, so a partial attribute change keeps untouched attributes like class (set a nested attribute to null to remove it). Does not touch children — use add/delete/move-node for structure.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function etch_edit_node(array $input): array|WP_Error
{
    $loaded = etch_node_load($input);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    [$post, $tree] = $loaded;

    $parts = etch_node_address_parts((string) ($input['address'] ?? ''));
    if ($parts === null || $parts === []) {
        return etch_error('etch_invalid_input', __(
            '`address` must be a non-empty index path (e.g. "0/1/2").',
            domain: 'novamira-pro',
        ));
    }
    if (!etch_node_exists($tree, $parts)) {
        return etch_error('etch_not_found', __('No node at the given address.', domain: 'novamira-pro'));
    }

    $node = etch_node_get($tree, $parts);
    if ($node === null) {
        return etch_error('etch_not_found', __('No node at the given address.', domain: 'novamira-pro'));
    }

    $node = etch_node_apply_edit($node, $input);

    $new_tree = etch_node_set_at($tree, $parts, $node);
    if ($new_tree === null) {
        return etch_error('etch_not_found', __('Could not resolve address.', domain: 'novamira-pro'));
    }
    return etch_node_persist($post, $new_tree);
}

// ---------------------------------------------------------------------------
// delete-node
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-node', [
    'label' => __('Delete Etch Node', domain: 'novamira-pro'),
    'description' => __(
        'Removes one node (and its subtree) at its index `address` (e.g. "0/1/2") without re-sending the whole tree. Addresses match novamira/etch-get-content. Re-serializes the page; refuses a lossy page unless force:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => etch_node_common_input()
            + [
                'address' => ['type' => 'string', 'description' => 'Index path of the node to delete, e.g. "0/1/2".'],
            ],
        'required' => ['post_id', 'address'],
        'additionalProperties' => false,
    ],
    'output_schema' => etch_node_output_schema(),
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_node',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes the addressed node and everything under it. Structural writes renumber index paths — re-read with get-content before the next address-based call.',
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
function etch_delete_node(array $input): array|WP_Error
{
    $loaded = etch_node_load($input);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    [$post, $tree] = $loaded;

    $parts = etch_node_address_parts((string) ($input['address'] ?? ''));
    if ($parts === null || $parts === []) {
        return etch_error('etch_invalid_input', __(
            '`address` must be a non-empty index path (e.g. "0/1/2").',
            domain: 'novamira-pro',
        ));
    }
    if (!etch_node_exists($tree, $parts)) {
        return etch_error('etch_not_found', __('No node at the given address.', domain: 'novamira-pro'));
    }

    $new_tree = etch_node_remove($tree, $parts);
    if ($new_tree === null) {
        return etch_error('etch_not_found', __('Could not resolve address.', domain: 'novamira-pro'));
    }
    return etch_node_persist($post, $new_tree);
}

// ---------------------------------------------------------------------------
// move-node
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-move-node', [
    'label' => __('Move Etch Node', domain: 'novamira-pro'),
    'description' => __(
        'Moves a node from its current index `address` to a new parent container, without re-sending the whole tree. `new_parent_address` is the destination container ("" = root); `position` is the 0-based index among the destination\'s children (omitted = append). Both addresses are resolved against the CURRENT tree (as get-content returns it); the source is removed and re-inserted, and the destination index is adjusted for the removal automatically. Cannot move a node into its own subtree. Re-serializes the page; refuses a lossy page unless force:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => etch_node_common_input()
            + [
                'address' => ['type' => 'string', 'description' => 'Index path of the node to move, e.g. "0/1/2".'],
                'new_parent_address' => [
                    'type' => 'string',
                    'description' => 'Index path of the destination container. "" = page root.',
                ],
                'position' => [
                    'type' => 'integer',
                    'description' => '0-based index among the destination\'s children. Omitted = append.',
                ],
            ],
        'required' => ['post_id', 'address'],
        'additionalProperties' => false,
    ],
    'output_schema' => etch_node_output_schema(),
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_move_node',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reorder within a parent or relocate to another container. Both addresses are pre-move (as get-content shows). Re-read with get-content afterwards — index paths change.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Adjust a destination parent path for the removal of the source node: if they
 * share the source's parent prefix and the destination index at that level is
 * after the source, it shifted left by one.
 *
 * @param list<int> $src
 * @param list<int> $dst
 * @return list<int>
 */
function etch_node_adjust_dest(array $src, array $dst): array
{
    $depth = count($src) - 1;
    if (count($dst) <= $depth) {
        return $dst;
    }
    if (array_slice($src, offset: 0, length: $depth) !== array_slice($dst, offset: 0, length: $depth)) {
        return $dst;
    }
    if ($dst[$depth] > $src[$depth]) {
        $dst[$depth]--;
    }
    return $dst;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function etch_move_node(array $input): array|WP_Error
{
    $loaded = etch_node_load($input);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    [$post, $tree] = $loaded;

    $src = etch_node_address_parts((string) ($input['address'] ?? ''));
    if ($src === null || $src === []) {
        return etch_error('etch_invalid_input', __(
            '`address` must be a non-empty index path.',
            domain: 'novamira-pro',
        ));
    }
    $dst = etch_node_address_parts((string) ($input['new_parent_address'] ?? ''));
    if ($dst === null) {
        return etch_error('etch_invalid_input', __(
            '`new_parent_address` must be an index path or "" for root.',
            domain: 'novamira-pro',
        ));
    }

    $node = etch_node_get($tree, $src);
    if ($node === null) {
        return etch_error('etch_not_found', __('No node at the source address.', domain: 'novamira-pro'));
    }
    if ($dst !== [] && !etch_node_exists($tree, $dst)) {
        return etch_error('etch_not_found', __('No container at new_parent_address.', domain: 'novamira-pro'));
    }
    if (count($src) <= count($dst) && array_slice($dst, offset: 0, length: count($src)) === $src) {
        return etch_error('etch_invalid_input', __('Cannot move a node into its own subtree.', domain: 'novamira-pro'));
    }

    $position = isset($input['position']) ? (int) $input['position'] : null;

    $without = etch_node_remove($tree, $src);
    if ($without === null) {
        return etch_error('etch_not_found', __('Could not resolve source address.', domain: 'novamira-pro'));
    }

    $dst_adj = etch_node_adjust_dest($src, $dst);
    $new_tree = etch_node_insert($without, $dst_adj, $node, $position);
    if ($new_tree === null) {
        return etch_error('etch_not_found', __(
            'Could not resolve new_parent_address after removal.',
            domain: 'novamira-pro',
        ));
    }
    return etch_node_persist($post, $new_tree);
}
