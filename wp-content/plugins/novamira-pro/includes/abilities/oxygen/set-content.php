<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-set-content', [
    'label' => __('Set Oxygen Content', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the entire Oxygen element tree of a post in one atomic write. `elements` is a NESTED list of the page\'s top-level elements (the children of the root); each node is `{type, properties?, children?}`. Node ids are allocated automatically from the tree\'s monotonic counter — do not supply them. Every `type` is resolved against the live element registry and every property branch against that type\'s live control schema; an unknown type/path aborts the whole write before persistence. Build with native elements (Container, Text, RichText, Image, …) — the raw-code element types (`OxygenElements\\PhpCode`, `HtmlCode`, `CssCode`, `JavaScriptCode`, `EssentialElements\\CodeBlock`) AND executable settings payloads (a `custom-php` display condition, a wp_query envelope in PHP mode, a `term_query` envelope on TermLoopBuilder, a `phpreturn` / `process_value` dynamic-data payload — including one hidden in a shortcode\'s `params` JSON, which the engine merges OVER the plain attributes) are gated at ANY depth of the tree: a tree containing one is rejected unless you pass `allow_code_elements: true` after the user explicitly confirms they want executable code. Storage uses the native Oxygen Data API and is read back after its save lifecycle. Use this to lay down or fully replace a page; use add/edit/move/delete-element for small incremental edits. Passing `elements: []` clears the canvas.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'elements' => [
                'type' => 'array',
                'description' => 'Nested top-level elements (children of the root). Each node: {type: string (case-sensitive PascalCase slug), properties?: object, children?: array of nodes}. Replaces the whole tree. Pass [] to clear the canvas.',
                'items' => ['type' => 'object'],
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Confirmation gate for executable payloads: the raw-code element types (PhpCode, HtmlCode, CssCode, JavaScriptCode, CodeBlock) AND executable settings on any node (a `custom-php` display condition, a wp_query envelope in PHP mode, a `term_query` envelope, a `phpreturn` / `process_value` dynamic-data payload, including one carried in a shortcode\'s `params` JSON). The engine executes/injects all of these on every render. By default a tree containing one — at any nesting depth — is rejected with guidance to use native elements and registered conditions/fields. Set true ONLY after the user has explicitly confirmed they want executable code — never set it on your own.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_set_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Replaces the full element tree atomically. Provide nested `elements` ({type, properties?, children?}); ids are auto-allocated, never pass them. Type slugs are case-sensitive PascalCase from the live registry (e.g. `OxygenElements\\Container`, `OxygenElements\\HtmlCode`; `EssentialElements\\Section` only with the "Breakdance Elements for Oxygen" extension) — discover them via novamira/oxygen-list-element-types; a wrong-cased slug is normalised and reported in `warnings`, an unknown type aborts the write with a suggestion. Copy `properties` shapes from an existing element via get-element. For small incremental changes prefer add/edit/move/delete-element.',
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
function oxygen_set_content(array $input): array|WP_Error
{
    $post_id = ox_coerce_int($input['post_id'] ?? 0);
    if ($post_id <= 0) {
        return ox_error('oxygen_invalid_input', __('post_id must be a positive integer.', domain: 'novamira-pro'));
    }
    if (!isset($input['elements']) || !is_array($input['elements'])) {
        return ox_error('oxygen_invalid_input', __(
            '`elements` is required and must be a JSON array of nodes.',
            domain: 'novamira-pro',
        ));
    }
    if ($input['elements'] !== [] && !array_is_list($input['elements'])) {
        return ox_error('oxygen_invalid_input', __(
            '`elements` must be a JSON array (list) of nodes, not an object.',
            domain: 'novamira-pro',
        ));
    }
    $post = ox_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $elements = array_values($input['elements']);
    $allow_code = ox_coerce_bool($input['allow_code_elements'] ?? false);

    return ox_with_tree_lock($post_id, static fn(): array|WP_Error => ox_set_content_locked(
        $post_id,
        $elements,
        $allow_code,
    ));
}

/**
 * Tree replacement run under the post's tree lock so concurrent REST
 * calls cannot stomp each other.
 *
 * @param list<mixed> $elements
 * @return array<string, mixed>|WP_Error
 */
// @mago-expect lint:no-boolean-flag-parameter
function ox_set_content_locked(int $post_id, array $elements, bool $allow_code = false): array|WP_Error
{
    $tree = ox_read_tree($post_id);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    $next_id = isset($tree['_nextNodeId']) && is_int($tree['_nextNodeId']) ? $tree['_nextNodeId'] : 100;

    /** @var array{next_id: int, count: int, errors: list<string>, warnings: list<string>} $state */
    $state = ['next_id' => $next_id, 'count' => 0, 'errors' => [], 'warnings' => []];
    $built = ox_sc_build_nodes($elements, $state, path: 'elements');

    if ($state['errors'] !== []) {
        return ox_error('oxygen_invalid_input', implode(' ', $state['errors']));
    }

    // Raw-code elements AND executable settings payloads require the explicit
    // allow_code_elements opt-in. Collected from the BUILT tree (post
    // type-resolution, all depths) so a gated type or a `custom-php` condition
    // nested in a subtree, or spelled in a case-alias, cannot slip through.
    $code_labels = ox_collect_tree_executable_payloads($built, path: 'elements');
    if ($code_labels !== [] && !$allow_code) {
        return ox_code_payload_block($code_labels);
    }

    $root = ox_assert_node($tree['root'] ?? null);
    $root_id = (int) ($root['id'] ?? 1);
    if (!ox_set_children($tree, $root_id, $built)) {
        return ox_error('oxygen_invalid_input', __(
            'Could not write the element tree (root node missing).',
            domain: 'novamira-pro',
        ));
    }
    $tree['_nextNodeId'] = $state['next_id'];
    ox_rebuild_lookup($tree);

    $write = ox_write_tree($post_id, $tree);
    if ($write instanceof WP_Error) {
        return $write;
    }

    $result = ['success' => true, 'post_id' => $post_id, 'element_count' => $state['count']];
    if ($state['warnings'] !== []) {
        $result['warnings'] = $state['warnings'];
    }
    return $result;
}

/**
 * Recursively validate and build canonical tree nodes from the nested
 * input, allocating monotonic ids and resolving every type against the
 * registry. `$state` carries the shared id counter plus accumulated
 * errors and warnings so the whole payload is reported in one pass; the
 * caller writes nothing when any error is present.
 *
 * @param list<mixed>                                                                     $nodes
 * @param array{next_id: int, count: int, errors: list<string>, warnings: list<string>} $state (by ref)
 * @return list<array<string, mixed>>
 */
function ox_sc_build_nodes(array $nodes, array &$state, string $path): array
{
    $built = [];
    /** @var mixed $node */
    foreach ($nodes as $i => $node) {
        $node_path = $path . '[' . $i . ']';
        $normalized = ox_sc_normalize_node($node, $node_path);
        if (is_string($normalized)) {
            $state['errors'][] = $normalized;
            continue;
        }
        if ($normalized['warning'] !== '') {
            $state['warnings'][] = $normalized['warning'];
        }
        // Nesting rule (parity with add-element): a leaf/loop element
        // (nestingRule `final`) renders no child slot, so children under it
        // would persist but never render. Reject the whole write.
        if ($normalized['children'] !== [] && !ox_element_accepts_children($normalized['type'])) {
            $hint = ox_element_is_loop($normalized['type'])
                ? __(
                    'It is a query loop: build the repeated item in an oxygen_block and configure it with oxygen-set-loop, not as children.',
                    domain: 'novamira-pro',
                )
                : __('It is a leaf element and renders no children.', domain: 'novamira-pro');
            $state['errors'][] = sprintf(
                /* translators: 1: node path, 2: element type, 3: hint */
                __('Node at %1$s (`%2$s`) cannot contain children. %3$s', domain: 'novamira-pro'),
                $node_path,
                $normalized['type'],
                $hint,
            );
            continue;
        }
        $id = $state['next_id'];
        $state['next_id']++;
        $state['count']++;
        $built[] = [
            'id' => $id,
            'data' => ['type' => $normalized['type'], 'properties' => $normalized['properties']],
            'children' => ox_sc_build_nodes($normalized['children'], $state, $node_path . '.children'),
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
function ox_sc_normalize_node(mixed $node, string $path): array|string
{
    if (!is_array($node) || array_is_list($node)) {
        return sprintf('Node at %s must be a JSON object.', $path);
    }
    $raw_type = isset($node['type']) && is_string($node['type']) ? trim($node['type']) : '';
    if ($raw_type === '') {
        return sprintf('Node at %s is missing a non-empty `type` string.', $path);
    }
    $resolved = ox_resolve_element_type($raw_type);
    if ($resolved instanceof WP_Error) {
        return sprintf('Node at %s: %s', $path, $resolved->get_error_message());
    }
    $warning = '';
    if ($resolved !== $raw_type) {
        $warning = sprintf('Node at %s: type `%s` normalised to `%s`.', $path, $raw_type, $resolved);
    }

    $properties = ox_sc_node_properties($node, $path, $resolved);
    if (is_string($properties)) {
        return $properties;
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

/**
 * Extract and validate a node's `properties` object: it must be a key/value
 * map (not a list) and use only Oxygen's real top-level control tabs. Returns
 * the validated properties array, or an error string (with the node path).
 *
 * @param array<array-key, mixed> $node
 * @return array<array-key, mixed>|string
 */
function ox_sc_node_properties(array $node, string $path, string $type): array|string
{
    $properties = isset($node['properties']) && is_array($node['properties']) ? $node['properties'] : [];
    if ($properties !== [] && array_is_list($properties)) {
        return sprintf('Node at %s: `properties` must be a JSON object (key/value map), not a list.', $path);
    }
    $properties = ox_normalize_advanced_css($properties);
    $prop_error = ox_invalid_properties($properties, $type);
    if ($prop_error !== null) {
        return sprintf('Node at %s: %s', $path, $prop_error);
    }
    return $properties;
}
