<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Add a single element into a WPBakery page.
 */

wp_register_ability('novamira/wpbakery-add-element', [
    'label' => __('Add WPBakery Element', domain: 'novamira-pro'),
    'description' => __(
        'Inserts a new WPBakery element (or a whole subtree) into a post. The `element` input is a node `{tag, atts, children?, _content?}` — the exact same shape wpbakery-set-content / wpbakery-get-content / wpbakery-inspect-page use for tree nodes, so a subtree captured from one can be passed verbatim here. `parent_idx` is the _idx of the target container (null for root) and `position` is the 0-based offset (-1 to append). On invalid top-level atts the response returns `invalid_values[]` with each bad key\'s value and the full inline `opts` list. Atts inside nested `element.children` get a soft post-write `invalid_atts` array (write still succeeds). Non-WPBakery shortcodes elsewhere in post_content are preserved and listed in `preserved_foreign_shortcodes`. Remaining nodes get re-indexed depth-first after the insert; call wpbakery-get-content again before targeting another _idx.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to modify.',
            ],
            'element' => [
                'type' => 'object',
                'description' => 'The node to insert. Shape `{tag, atts, children?, _content?}` mirrors set-content tree nodes — pass a leaf for a single element, or include nested `children` for a whole subtree (e.g. vc_row > vc_column > vc_btn) in one call. Atts on nested children are NOT schema-validated (only the top-level `element.tag` atts are).',
                'properties' => [
                    'tag' => [
                        'type' => 'string',
                        'description' => 'WPBakery element tag, e.g. "vc_btn", "vc_column", "vc_row".',
                    ],
                    'atts' => [
                        'type' => 'object',
                        'description' => 'Element attributes. vc_link values as {url, title, target, rel}; param_group as array.',
                    ],
                    'children' => ['type' => 'array', 'description' => 'Optional nested subtree (same node shape).'],
                    '_content' => [
                        'type' => ['string', 'null'],
                        'description' => 'Raw inner HTML for elements that use a textarea_html param (e.g. vc_column_text). Mutually exclusive in practice with children.',
                    ],
                ],
                'required' => ['tag'],
            ],
            'parent_idx' => [
                'type' => ['integer', 'null'],
                'description' => 'The _idx of the parent container. Pass null to insert at the root level.',
                'default' => null,
            ],
            'position' => [
                'type' => 'integer',
                'description' => 'Zero-based position inside the parent children array. -1 appends at the end.',
                'default' => -1,
            ],
        ],
        'required' => ['post_id', 'element'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'edit_url' => ['type' => 'string'],
            'tree' => [
                'type' => 'array',
                'description' => 'The post-write tree with fresh _idx values — same shape wpbakery-get-content returns. Use this to find the _idx of the just-inserted node without an extra get-content round-trip.',
            ],
            'tag' => [
                'type' => 'string',
                'description' => 'Echoed tag when validation fails so the agent can pass it to wpbakery-get-element-schema if needed.',
            ],
            'dropped_keys' => ['type' => 'array', 'description' => 'Keys that are not in the element schema.'],
            'deprecated_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Atts WPBakery still accepts but no longer honours. The write went through; the element ignores them at render time.',
            ],
            'invalid_values' => [
                'type' => 'array',
                'description' => 'Bad enum values. Each entry is {key, value, opts}: `opts` already lists every valid choice — pick one and retry, no separate schema call needed.',
            ],
            'hint' => ['type' => 'string', 'description' => 'One-line recovery hint when validation fails.'],
            'invalid_atts' => [
                'type' => 'array',
                'description' => 'Per-node atts issues from a successful insert of a nested subtree (element.children). Same shape as wpbakery-set-content invalid_atts.',
            ],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\WPBakery\wpbakery_add_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always call wpbakery-get-content first to see the current tree and note the _idx of the target parent. Wrap the new node inside `element` — its shape `{tag, atts, children?, _content?}` is identical to a set-content tree node, so a subtree captured from get-content / inspect-page can be passed verbatim. If the response returns `invalid_values[]`, each entry already lists the valid `opts` inline: pick one and retry, no separate discovery call needed. To insert a fresh row + column scaffold in one shot, include nested `element.children` rather than chaining 3 add-element calls. Example: {post_id, parent_idx: null, position: -1, element: {tag: "vc_row", atts: {}, children: [{tag: "vc_column", atts: {width: "1/1"}, children: [{tag: "vc_btn", atts: {title: "X"}, children: []}]}]}}.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wpbakery_add_element(array $input): array
{
    $parsed = wpb_parse_add_element_input($input);
    $tag = $parsed['tag'];

    if ($tag === '') {
        return [
            'success' => false,
            'error' => 'Missing element.tag. Pass `element: {tag, atts, children?, _content?}` — the same node shape that wpbakery-set-content uses.',
        ];
    }

    $schema = wpb_compact_element($tag);
    if ($schema === null) {
        return [
            'success' => false,
            'error' => sprintf(
                'Unknown WPBakery element tag "%s". Use wpbakery-list-elements to browse available elements.',
                $tag,
            ),
        ];
    }

    $validation = wpb_validate_atts($parsed['atts'], $schema);
    if ($validation['dropped_keys'] !== [] || $validation['invalid_values'] !== []) {
        return [
            'success' => false,
            'tag' => $tag,
            'dropped_keys' => $validation['dropped_keys'],
            'invalid_values' => $validation['invalid_values'],
            'hint' => wpb_validation_hint($tag, $validation),
        ];
    }

    $result = wpb_do_add_element(
        $parsed['post_id'],
        [
            'tag' => $tag,
            'atts' => $parsed['atts'],
            '_content' => $parsed['_content'],
            'children' => $parsed['children'],
        ],
        $parsed['parent_idx'],
        $parsed['position'],
    );

    if ($validation['deprecated_keys'] !== []) {
        $result['deprecated_keys'] = $validation['deprecated_keys'];
    }

    return $result;
}

/**
 * @param array<string, mixed> $input
 * @return array{
 *     post_id: int,
 *     tag: string,
 *     atts: array<string, mixed>,
 *     children: list<array<string, mixed>>,
 *     _content: ?string,
 *     parent_idx: ?int,
 *     position: int,
 * }
 */
function wpb_parse_add_element_input(array $input): array
{
    /** @var array<string, mixed> $element */
    $element = is_array($input['element'] ?? null) ? $input['element'] : [];
    /** @var array<string, mixed> $atts */
    $atts = is_array($element['atts'] ?? null) ? $element['atts'] : [];
    /** @var list<array<string, mixed>> $children */
    $children = is_array($element['children'] ?? null) ? $element['children'] : [];

    return [
        'post_id' => (int) ($input['post_id'] ?? 0),
        'tag' => (string) ($element['tag'] ?? ''),
        'atts' => $atts,
        'children' => $children,
        '_content' => array_key_exists('_content', $element) && is_string($element['_content'])
            ? $element['_content']
            : null,
        'parent_idx' => array_key_exists('parent_idx', $input) && is_int($input['parent_idx'])
            ? $input['parent_idx']
            : null,
        'position' => (int) ($input['position'] ?? -1),
    ];
}

/**
 * Loads the post tree, inserts the new node, and saves. Called after all
 * validation passes in wpbakery_add_element.
 *
 * @param array{
 *     tag: string,
 *     atts: array<string, mixed>,
 *     _content: ?string,
 *     children: list<array<string, mixed>>,
 * } $node_def
 * @return array<string, mixed>
 */
function wpb_do_add_element(int $post_id, array $node_def, ?int $parent_idx, int $position): array
{
    $writable_error = wpb_post_writable_or_error($post_id);
    if ($writable_error !== null) {
        return $writable_error;
    }

    $raw = wpb_get_post_content($post_id) ?? '';
    $tree = wpb_parse_indexed_tree($raw);

    $new_node = [
        'tag' => $node_def['tag'],
        'atts' => $node_def['atts'],
        'children' => $node_def['children'],
    ];
    if ($node_def['_content'] !== null) {
        $new_node['_content'] = $node_def['_content'];
    }

    if (!wpb_insert_node($tree, $new_node, $parent_idx, $position)) {
        return [
            'success' => false,
            'error' => sprintf('Parent with _idx %d not found in the tree.', (int) $parent_idx),
        ];
    }

    // Checked on the assembled tree, not on the incoming node: dropping a
    // vc_tta_section into a parent that already sits inside one is just as
    // unrenderable as carrying the nesting in the subtree itself.
    $nesting_error = wpb_self_nesting_error($tree);
    if ($nesting_error !== null) {
        return $nesting_error;
    }

    $result = wpb_finalize_write($post_id, $tree, $raw);

    // Validate atts inside the inserted subtree. wpb_finalize_write already
    // re-read + reparsed the post into $result['tree'], so reuse that instead
    // of issuing another get_post + parse just for the lint pass.
    if ($node_def['children'] !== [] && isset($result['tree']) && is_array($result['tree'])) {
        /** @var list<array<string, mixed>> $saved_tree */
        $saved_tree = $result['tree'];
        $issues = wpb_validate_tree_atts($saved_tree);
        if ($issues !== []) {
            $result['invalid_atts'] = $issues;
        }
    }

    return $result;
}
