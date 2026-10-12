<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: apply atts updates to multiple WPBakery elements in one call.
 *
 * Sub-agents reported the "style every vc_btn on this page the same way"
 * pattern hurts when handled one element at a time: even with caveat #4
 * (edit-element does not renumber _idx), the agent still has to issue N
 * separate writes. This ability lets the agent declare one or more
 * `{match, atts, replace?}` edit rules in a single call. Each rule's
 * `match` selects nodes by `tag` and/or by an explicit list of
 * `element_idxs`; the rule's `atts` are merged (or replaced when
 * `replace=true`) into every matching node. A single tree-parse +
 * single save covers all updates.
 */

wp_register_ability('novamira/wpbakery-edit-elements', [
    'label' => __('Edit Multiple WPBakery Elements', domain: 'novamira-pro'),
    'category' => 'wpbakery',
    'description' => __(
        'Applies one or more atts updates across multiple WPBakery elements in a single atomic write. Input shape: `{post_id, edits: [{match, atts, replace?}, …]}` — edits is ALWAYS an array, even for a single rule. Each rule\'s `match` selects nodes by `tag` and/or `element_idxs` (AND when both present), and the rule\'s `atts` are merged (or replaced wholesale when `replace=true`). Example: `edits: [{"match":{"tag":"vc_btn"},"atts":{"color":"blue","size":"lg"}}]`. Atts are NOT schema-validated per match (use wpbakery-edit-element on a single node when validation matters). Returns the deduplicated list of element_idxs touched, plus the standard preserved_foreign_shortcodes field when applicable.',
        domain: 'novamira-pro',
    ),
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to modify.',
            ],
            'edits' => [
                'type' => 'array',
                'description' => 'Ordered list of edit rules. Each rule applies to every node matching its `match` predicate. Order matters: later rules see the result of earlier rules.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'match' => [
                            'type' => 'object',
                            'properties' => [
                                'tag' => [
                                    'type' => 'string',
                                    'description' => 'Apply this rule to every node with this tag (e.g. "vc_btn"). Combine with element_idxs to narrow further.',
                                ],
                                'element_idxs' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'integer'],
                                    'description' => 'Apply this rule only to nodes whose _idx is in this list. If `tag` is also set the two predicates AND together.',
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                        'atts' => [
                            'type' => 'object',
                            'description' => 'Atts to apply to every matched node. Merged by default; pass `replace=true` to wipe the node\'s existing atts and use these instead.',
                        ],
                        'replace' => [
                            'type' => 'boolean',
                            'description' => 'When true, the matched node\'s existing atts are discarded and replaced wholesale with `atts`. Default false (merge).',
                            'default' => false,
                        ],
                        'preserve_atts' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Only meaningful when `replace=true`. List of att keys to carry forward from each matched node\'s existing atts before applying the new `atts`. Lets the agent express "wipe everything except these identity keys" in one batch call — e.g. reset every vc_btn to a fixed style while keeping its existing title.',
                        ],
                    ],
                    'required' => ['match', 'atts'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['post_id', 'edits'],
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
                'description' => 'The post-write tree — same shape wpbakery-get-content returns. _idx values are stable across pure edit-elements calls; included for consistency with the other mutating abilities.',
            ],
            'touched' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => 'Sorted unique list of element_idxs that were updated by at least one rule.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'rule_index' => [
                            'type' => 'integer',
                            'description' => 'Position of the offending rule in the input edits[] array.',
                        ],
                        'code' => ['type' => 'string', 'description' => 'Machine-readable code (e.g. "no_match").'],
                        'message' => ['type' => 'string', 'description' => 'Human-readable summary.'],
                    ],
                    'required' => ['rule_index', 'code', 'message'],
                ],
                'description' => 'Soft hints. Present only when at least one rule had a problem worth surfacing (currently: matched zero nodes). The write itself still succeeded.',
            ],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_edit_elements',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this when you need to apply identical atts to N elements (e.g. "style every vc_btn on this page"). Input MUST nest each rule inside an `edits[]` array — top-level `{match, atts}` will fail validation. Concrete example: `{"post_id":42,"edits":[{"match":{"tag":"vc_btn"},"atts":{"size":"lg","color":"primary"}}]}`. For multiple rules with different selectors, add more entries to `edits` (later rules see the result of earlier rules, atts merge unless `replace=true`). For a single-element edit with schema validation prefer wpbakery-edit-element.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     post_id?: int,
 *     edits?: list<array{match: array<string, mixed>, atts: array<string, mixed>, replace?: bool, preserve_atts?: list<string>}>,
 * } $input
 * @return array<string, mixed>
 */
function wpbakery_edit_elements(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $edits = is_array($input['edits'] ?? null) ? $input['edits'] : [];

    if ($edits === []) {
        return ['success' => false, 'error' => 'No edits provided.'];
    }

    $writable_error = wpb_post_writable_or_error($post_id);
    if ($writable_error !== null) {
        return $writable_error;
    }

    $raw = wpb_get_post_content($post_id) ?? '';
    $tree = wpb_parse_indexed_tree($raw);

    $touched = [];
    $warnings = [];
    foreach ($edits as $rule_index => $edit) {
        $rule_touched = wpb_apply_edit_rule_to_tree($tree, $edit);
        if ($rule_touched === []) {
            $warnings[] = [
                'rule_index' => $rule_index,
                'code' => 'no_match',
                'message' => sprintf(
                    'edits[%d] matched no nodes. Match predicate: %s',
                    $rule_index,
                    json_encode($edit['match']) === false ? '<unprintable>' : (string) json_encode($edit['match']),
                ),
            ];
            continue;
        }
        foreach ($rule_touched as $idx) {
            $touched[] = $idx;
        }
    }

    $touched = array_values(array_unique($touched));
    sort($touched);

    $result = wpb_finalize_write($post_id, $tree, $raw);
    if ($result['success'] === true) {
        $result['touched'] = $touched;
        if ($warnings !== []) {
            $result['warnings'] = $warnings;
        }
    }
    return $result;
}

/**
 * Walks the tree in place and applies a single edit rule to every node
 * the `match` predicate selects. Returns the list of `_idx` values that
 * were touched. Caveat: this mutates `$nodes` by reference.
 *
 * @param list<array<string, mixed>> $nodes
 * @param array{match: array<string, mixed>, atts: array<string, mixed>, replace?: bool, preserve_atts?: list<string>} $edit
 * @return list<int>
 */
function wpb_apply_edit_rule_to_tree(array &$nodes, array $edit): array
{
    $touched = [];
    foreach ($nodes as &$node) {
        if (wpb_node_matches_edit_rule($node, $edit)) {
            $node_idx = is_int($node['_idx'] ?? null) ? $node['_idx'] : -1;
            wpb_apply_edit_rule_atts($node, $edit);
            $touched[] = $node_idx;
        }

        if (isset($node['children']) && is_array($node['children'])) {
            /** @var list<array<string, mixed>> $children */
            $children = &$node['children'];
            $child_touched = wpb_apply_edit_rule_to_tree($children, $edit);
            foreach ($child_touched as $idx) {
                $touched[] = $idx;
            }
        }
    }
    return $touched;
}

/**
 * Returns true when the given tree node satisfies the edit rule's match
 * predicate. `tag` and `element_idxs` AND together when both are provided;
 * an absent filter is always permissive.
 *
 * @param array<string, mixed> $node
 * @param array{match: array<string, mixed>, atts: array<string, mixed>, replace?: bool, preserve_atts?: list<string>} $edit
 */
function wpb_node_matches_edit_rule(array $node, array $edit): bool
{
    $tag_filter = isset($edit['match']['tag']) && is_string($edit['match']['tag']) ? $edit['match']['tag'] : null;
    $idx_filter = isset($edit['match']['element_idxs']) && is_array($edit['match']['element_idxs'])
        ? array_values(array_map('intval', $edit['match']['element_idxs']))
        : null;

    $node_tag = is_string($node['tag'] ?? null) ? $node['tag'] : '';
    if ($tag_filter !== null && $node_tag !== $tag_filter) {
        return false;
    }
    if ($idx_filter !== null) {
        $node_idx = is_int($node['_idx'] ?? null) ? $node['_idx'] : -1;
        if (!in_array($node_idx, $idx_filter, strict: true)) {
            return false;
        }
    }
    return true;
}

/**
 * Applies the rule's atts to the matched node — merge by default,
 * wholesale replace when `replace=true`. With `replace=true` plus
 * `preserve_atts: [keys]`, the listed keys are carried forward from
 * the node's existing atts before the new atts are written.
 *
 * @param array<string, mixed> $node Mutated in place.
 * @param array{
 *     match: array<string, mixed>,
 *     atts: array<string, mixed>,
 *     replace?: bool,
 *     preserve_atts?: list<string>,
 * } $edit
 */
function wpb_apply_edit_rule_atts(array &$node, array $edit): void
{
    $new_atts = $edit['atts'];
    $replace = isset($edit['replace']) && $edit['replace'] === true;
    /** @var array<string, mixed> $current */
    $current = is_array($node['atts'] ?? null) ? $node['atts'] : [];

    if (!$replace) {
        $node['atts'] = array_merge($current, $new_atts);
        return;
    }

    // replace=true: discard existing atts wholesale, but carry forward
    // any keys named in preserve_atts (only meaningful when replace is
    // true — the merge path would carry them forward implicitly).
    $preserve = $edit['preserve_atts'] ?? [];
    $preserved = [];
    foreach ($preserve as $key) {
        if (!array_key_exists($key, $current)) {
            continue;
        }
        $preserved[$key] = $current[$key];
    }
    $node['atts'] = array_merge($preserved, $new_atts);
}
