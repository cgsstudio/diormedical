<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-set-content', [
    'label' => __('Set Breakdance Content', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the entire Breakdance element tree of a post in one atomic write. A tree containing raw `EssentialElements\\CodeBlock` elements is gated and rejected unless the user explicitly confirms it and the re-call sets `allow_code_elements:true`; a tree whose content consists only of Code Blocks is always rejected even with that flag. `elements` is a NESTED list of the page\'s top-level elements (the children of the root); each node is `{type, properties?, children?}`. Node ids are allocated automatically from the tree\'s monotonic counter — do not supply them. Every `type` is resolved against Breakdance\'s live element registry: a wrong-cased slug is normalised to the canonical registered slug and reported in `warnings`, while a genuinely unknown type aborts the whole write with a suggestion. Storage is handled correctly, so prefer this over hand-writing postmeta. Use this to lay down or fully replace a page; use add-element / edit-element / move-element / delete-element for small incremental edits. Passing `elements: []` clears the canvas.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'elements' => [
                'type' => 'array',
                'description' => 'Nested top-level elements (children of the root). Each node: {type: string, properties?: object, children?: array}. `EssentialElements\\CodeBlock` requires the allow_code_elements handshake; a Code-Block-only tree is forbidden. Replaces the whole tree; pass [] to clear.',
                'items' => ['type' => 'object'],
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Set true ONLY after the user has explicitly confirmed they want raw Breakdance Code Block elements; never set it on your own initiative. A Code-Block-only tree remains forbidden.',
            ],
        ],
        'required' => ['post_id', 'elements'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'element_count' => [
                'type' => 'integer',
                'description' => 'Total nodes written (excluding the root).',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Non-fatal notes, e.g. an element type whose casing was normalised.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_set_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Replaces the full element tree atomically. Provide nested `elements` ({type, properties?, children?}); ids are auto-allocated, never pass them. Type slugs are case-sensitive PascalCase (EssentialElements\\Section, \\Heading, \\Image2 — Image2 is the modern image, the bare Image is the deprecated "Image V1"); a wrong-cased slug is normalised and reported in `warnings`, an unknown type aborts the write with a suggestion (call novamira/breakdance-list-element-types to discover slugs). Any EssentialElements\\CodeBlock is rejected with breakdance_code_element_needs_confirmation. Ask the user; only after explicit confirmation may you re-call with allow_code_elements:true, and never set that flag on your own initiative. A Code-Block-only tree is always rejected. Successful mixed-tree opt-ins include an audit warning. For small incremental changes prefer add/edit/move/delete-element.',
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
function breakdance_set_content(array $input): array|WP_Error
{
    $post_id = bd_coerce_int($input['post_id'] ?? 0);
    if ($post_id <= 0) {
        return bd_error('bd_invalid_input', __('post_id must be a positive integer.', domain: 'novamira-pro'));
    }
    if (!isset($input['elements']) || !is_array($input['elements'])) {
        return bd_error('bd_invalid_input', __(
            '`elements` is required and must be a JSON array of nodes.',
            domain: 'novamira-pro',
        ));
    }
    if ($input['elements'] !== [] && !array_is_list($input['elements'])) {
        return bd_error('bd_invalid_input', __(
            '`elements` must be a JSON array (list) of nodes, not an object.',
            domain: 'novamira-pro',
        ));
    }
    $post = bd_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $elements = array_values($input['elements']);

    return bd_with_tree_lock($post_id, static fn(): array|WP_Error => bd_set_content_locked(
        $post_id,
        $elements,
        $input,
    ));
}

/**
 * Tree replacement run under the post's tree lock so concurrent REST calls
 * cannot stomp each other.
 *
 * @param list<mixed> $elements
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bd_set_content_locked(int $post_id, array $elements, array $input): array|WP_Error
{
    $tree = bd_read_tree($post_id);
    $next_id = isset($tree['_nextNodeId']) && is_int($tree['_nextNodeId']) ? $tree['_nextNodeId'] : 100;

    /** @var array{next_id: int, count: int, errors: list<string>, warnings: list<string>} $state */
    $state = ['next_id' => $next_id, 'count' => 0, 'errors' => [], 'warnings' => []];
    $built = bd_sc_build_nodes($elements, $state, path: 'elements');

    if ($state['errors'] !== []) {
        return bd_error('bd_invalid_input', implode(' ', $state['errors']));
    }
    $code_only_gate = bd_code_only_tree_gate($built);
    if ($code_only_gate !== null) {
        return $code_only_gate;
    }
    $code_gate = bd_code_element_gate($built, $input);
    if ($code_gate !== null) {
        return $code_gate;
    }
    $audit_warning = bd_code_element_audit_warning($built);
    if ($audit_warning !== null) {
        $state['warnings'][] = $audit_warning;
    }

    $root = bd_assert_node($tree['root'] ?? null);
    $root_id = (int) ($root['id'] ?? 1);
    if (!bd_set_children($tree, $root_id, $built)) {
        return bd_error('bd_invalid_input', __(
            'Could not write the element tree (root node missing).',
            domain: 'novamira-pro',
        ));
    }
    $tree['_nextNodeId'] = $state['next_id'];
    bd_rebuild_lookup($tree);

    $css_warning = null;
    $write = bd_write_tree($post_id, $tree, $css_warning);
    if ($write instanceof WP_Error) {
        return $write;
    }
    if ($css_warning !== null) {
        $state['warnings'][] = $css_warning;
    }

    $result = ['success' => true, 'post_id' => $post_id, 'element_count' => $state['count']];
    if ($state['warnings'] !== []) {
        $result['warnings'] = $state['warnings'];
    }
    return $result;
}

/**
 * Recursively validate and build canonical tree nodes from the nested input,
 * allocating monotonic ids and resolving every type against the registry.
 * `$state` carries the shared id counter plus accumulated errors (unknown
 * types, bad shapes) and warnings (casing normalisations) so the whole payload
 * is reported in one pass; the caller writes nothing when any error is present.
 *
 * @param list<mixed>                                                            $nodes
 * @param array{next_id: int, count: int, errors: list<string>, warnings: list<string>} $state (by ref)
 * @return list<array<string, mixed>>
 */
function bd_sc_build_nodes(array $nodes, array &$state, string $path): array
{
    $built = [];
    /** @var mixed $node */
    foreach ($nodes as $i => $node) {
        $node_path = $path . '[' . $i . ']';
        $normalized = bd_sc_normalize_node($node, $node_path);
        if (is_string($normalized)) {
            $state['errors'][] = $normalized;
            continue;
        }
        if ($normalized['warning'] !== '') {
            $state['warnings'][] = $normalized['warning'];
        }
        $id = $state['next_id'];
        $state['next_id']++;
        $state['count']++;
        $built[] = [
            'id' => $id,
            'data' => ['type' => $normalized['type'], 'properties' => $normalized['properties']],
            'children' => bd_sc_build_nodes($normalized['children'], $state, $node_path . '.children'),
        ];
    }
    return $built;
}

/**
 * Validate a single input node. Returns the normalised parts
 * `{type, properties, children, warning}` on success, or an error string
 * describing what is wrong (with the node path) on failure.
 *
 * @return array{type: string, properties: array<array-key, mixed>, children: list<mixed>, warning: string}|string
 */
function bd_sc_normalize_node(mixed $node, string $path): array|string
{
    if (!is_array($node) || array_is_list($node)) {
        return sprintf('Node at %s must be a JSON object.', $path);
    }
    $raw_type = isset($node['type']) && is_string($node['type']) ? trim($node['type']) : '';
    if ($raw_type === '') {
        return sprintf('Node at %s is missing a non-empty `type` string.', $path);
    }
    $resolved = bd_resolve_element_type($raw_type);
    if ($resolved instanceof WP_Error) {
        return sprintf('Node at %s: %s', $path, $resolved->get_error_message());
    }
    $warning = '';
    if ($resolved !== $raw_type) {
        $warning = sprintf('Node at %s: type `%s` normalised to `%s`.', $path, $raw_type, $resolved);
    }

    $properties = isset($node['properties']) && is_array($node['properties']) ? $node['properties'] : [];
    if ($properties !== [] && array_is_list($properties)) {
        return sprintf('Node at %s: `properties` must be a JSON object (key/value map), not a list.', $path);
    }

    $children_raw = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
    if ($children_raw !== [] && !array_is_list($children_raw)) {
        return sprintf('Node at %s: `children` must be a JSON array of nodes.', $path);
    }

    return [
        'type' => $resolved,
        'properties' => $properties,
        'children' => array_values($children_raw),
        'warning' => $warning,
    ];
}
