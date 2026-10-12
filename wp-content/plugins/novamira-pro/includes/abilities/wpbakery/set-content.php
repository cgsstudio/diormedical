<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Write the WPBakery element tree for a post (full replacement).
 */

wp_register_ability('novamira/wpbakery-set-content', [
    'label' => __('Set WPBakery Content', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the entire WPBakery layout of a post with the provided element tree. Each node has shape {tag, atts, children, _content}: `children` is the recursive nesting key, `atts` uses {url,title,target,rel} for vc_link and arrays of objects for param_group, `_content` carries raw inner HTML for elements like vc_column_text. The top-level param is `tree` (the array of root nodes). Pass `tree: []` to wipe the WPBakery layout entirely. The post is marked as a WPBakery page automatically. Pass `custom_css` to set the page-level CSS in the same call (avoids a separate wpbakery-set-settings round-trip). Non-WPBakery shortcodes elsewhere in post_content (e.g. [contact-form-7], [gallery]) are preserved and re-appended after the saved WPBakery tree; the response lists them in `preserved_foreign_shortcodes` when present.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to write.',
            ],
            'tree' => [
                'type' => 'array',
                'description' => 'The full element tree (array of root nodes). Same shape returned by wpbakery-get-content and wpbakery-inspect-page (`tree` field on both).',
            ],
            'custom_css' => [
                'type' => 'string',
                'description' => 'Optional. When provided, the page-level custom CSS (post meta _wpb_post_custom_css) is written in the same atomic transaction as the tree. Saves a round-trip vs calling wpbakery-set-settings separately. Pass an empty string to clear.',
            ],
        ],
        'required' => ['post_id', 'tree'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'shortcode_string' => ['type' => 'string'],
            'edit_url' => ['type' => 'string'],
            'tree' => [
                'type' => 'array',
                'description' => 'The post-write tree with fresh _idx values — same shape wpbakery-get-content returns. Use this to act on the new indices without an extra get-content round-trip.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Soft hints. Only present when the linter flagged a suspicious pattern in the saved tree (e.g. a node using `content` as if it were the recursive children key). The write still succeeded.',
            ],
            'invalid_atts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        '_idx' => [
                            'type' => 'integer',
                            'description' => 'Depth-first index assigned by the write (matches `_idx` returned by wpbakery-get-content after this call).',
                        ],
                        'tag' => ['type' => 'string'],
                        'issue' => ['type' => 'string', 'enum' => ['unknown_key', 'invalid_value', 'deprecated_key']],
                        'key' => ['type' => 'string'],
                        'value' => [
                            // The rejected value, echoed back as written, so it
                            // can be any JSON type. WordPress refuses to
                            // validate a property with no `type` and emits a
                            // _doing_it_wrong notice, so the union is spelled out.
                            'type' => ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'],
                            'description' => 'Only present when issue=invalid_value.',
                        ],
                        'opts' => [
                            'type' => 'array',
                            'description' => 'Allowed enum values. Only present when issue=invalid_value.',
                        ],
                    ],
                ],
                'description' => 'Per-node atts validation issues. Soft — the write still succeeds; WPBakery will fall back to defaults for invalid values. Use the `_idx` to target wpbakery-edit-element for an in-place correction.',
            ],
            'preserved_foreign_shortcodes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'tag' => [
                            'type' => 'string',
                            'description' => 'Shortcode tag name (e.g. "gallery", "wpforms"). Use this when describing the preserved content to the user.',
                        ],
                        'raw' => [
                            'type' => 'string',
                            'description' => 'The full original shortcode string as it appeared in the pre-write post_content.',
                        ],
                    ],
                    'required' => ['tag', 'raw'],
                ],
                'description' => 'List of non-WPBakery shortcodes ([gallery], [wpforms], …) that were present in the pre-write post_content and have been re-appended after the WPBakery tree to avoid silent data loss. Only present when at least one such shortcode was preserved. Position is NOT preserved — every entry now sits after the WPBakery tree.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_set_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Replaces the entire page layout — use add/edit/delete-element for targeted changes. Top-level input is `tree` (array of root nodes). Each node has shape {tag, atts, children, _content}. Example: `{"post_id":42,"tree":[{"tag":"vc_row","atts":{},"children":[{"tag":"vc_column","atts":{"width":"1/1"},"children":[{"tag":"vc_btn","atts":{"title":"Click"},"children":[]}]}]}]}`. Inside each node, the recursive nesting key is `children`, NOT `content` — putting children under `content` would silently produce an empty tree (the soft lint surfaces this in the `warnings` field). vc_link atts as {url,title,target,rel}; param_group atts as arrays of objects. `_content` holds raw inner HTML for vc_column_text-style elements.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

function wpbakery_set_content(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    /** @var list<array<string, mixed>> $tree */
    $tree = is_array($input['tree'] ?? null) ? $input['tree'] : [];

    $writable_error = wpb_post_writable_or_error($post_id);
    if ($writable_error !== null) {
        return $writable_error;
    }

    // Refused rather than warned about: unlike a bad enum value, this one
    // does not produce a page that merely looks wrong — it prints raw
    // shortcode text to visitors. Better to hand the agent a fix than to
    // save something that cannot render.
    $nesting_error = wpb_self_nesting_error($tree);
    if ($nesting_error !== null) {
        return $nesting_error;
    }

    $original_raw = wpb_get_post_content($post_id) ?? '';
    $save = wpb_save_tree_preserving_foreign($post_id, $tree, $original_raw);

    if (!$save['success']) {
        return ['success' => false, 'error' => 'Failed to save post content.'];
    }

    if (array_key_exists('custom_css', $input) && is_string($input['custom_css'])) {
        update_post_meta($post_id, meta_key: WPB_META_CUSTOM_CSS, meta_value: $input['custom_css']);
    }

    $result = [
        'success' => true,
        'post_id' => $post_id,
        'shortcode_string' => $save['shortcode_string'],
        'edit_url' => (string) get_edit_post_link($post_id, context: 'raw'),
        'tree' => wpb_load_post_write_tree($post_id),
    ];

    $warnings = wpb_lint_tree($tree);
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    $invalid_atts = wpb_validate_tree_atts($tree);
    if ($invalid_atts !== []) {
        $result['invalid_atts'] = $invalid_atts;
    }

    if ($save['preserved'] !== []) {
        $result['preserved_foreign_shortcodes'] = $save['preserved'];
    }

    return $result;
}

/**
 * Walks a node tree depth-first, validates each node's atts against its
 * registered element schema, and collects per-node issues. Used as a soft
 * post-write lint by set-content (and surfaced from add-element when a
 * nested subtree is inserted): the write still succeeds and WPBakery falls
 * back to defaults for invalid enum values, but the agent gets actionable
 * `{_idx, tag, issue, key, value, opts}` entries so it can self-correct
 * with a follow-up edit-element call without a separate discovery round.
 *
 * The depth-first index matches the `_idx` that wpb_index_tree assigns
 * post-write, so an agent reading the response can reference the same
 * indices it would see in a subsequent wpbakery-get-content call.
 *
 * @param list<array<string, mixed>> $nodes
 * @param int $idx Running depth-first counter (mirrors wpb_index_tree).
 * @return list<array<string, mixed>>
 */
function wpb_validate_tree_atts(array $nodes, int &$idx = 0): array
{
    $issues = [];
    foreach ($nodes as $node) {
        $node_idx = $idx;
        $idx++;

        $tag = is_string($node['tag'] ?? null) ? $node['tag'] : '';
        /** @var array<string, mixed> $atts */
        $atts = is_array($node['atts'] ?? null) ? $node['atts'] : [];

        if ($tag !== '' && $atts !== []) {
            $schema = wpb_compact_element($tag);
            if ($schema !== null) {
                $validation = wpb_validate_atts($atts, $schema);
                foreach ($validation['dropped_keys'] as $k) {
                    $issues[] = [
                        '_idx' => $node_idx,
                        'tag' => $tag,
                        'issue' => 'unknown_key',
                        'key' => $k,
                    ];
                }
                foreach ($validation['deprecated_keys'] as $k) {
                    $issues[] = [
                        '_idx' => $node_idx,
                        'tag' => $tag,
                        'issue' => 'deprecated_key',
                        'key' => $k,
                    ];
                }
                foreach ($validation['invalid_values'] as $iv) {
                    /** @var array{key: string, value: mixed, opts: list<mixed>} $iv */
                    $issues[] = [
                        '_idx' => $node_idx,
                        'tag' => $tag,
                        'issue' => 'invalid_value',
                        'key' => $iv['key'],
                        'value' => $iv['value'],
                        'opts' => $iv['opts'],
                    ];
                }
            }
        }

        if (isset($node['children']) && is_array($node['children'])) {
            /** @var list<array<string, mixed>> $children */
            $children = $node['children'];
            $sub = wpb_validate_tree_atts($children, $idx);
            foreach ($sub as $s) {
                $issues[] = $s;
            }
        }
    }
    return $issues;
}

/**
 * Soft pre-write lint over a node tree. Returns a list of human-readable
 * warnings when a suspicious shape is detected. Common false-positive
 * patterns surfaced by AI sub-agent testing:
 *  - node with a `content` key holding an array of node-like objects
 *    (an agent used `content` as the per-node nesting key, which is
 *    actually `children`).
 *
 * The lint never raises an error: every warning is a soft hint included
 * alongside a successful write. The agent that ignores `warnings` keeps
 * the same behaviour as before; the agent that reads them gets an
 * actionable pointer to the offending node by idx and tag.
 *
 * @param list<array<string, mixed>> $nodes
 * @param int $idx_offset Running depth-first index for context in messages.
 * @return list<string>
 */
function wpb_lint_tree(array $nodes, int &$idx_offset = 0): array
{
    $warnings = [];
    foreach ($nodes as $node) {
        $this_idx = $idx_offset;
        $idx_offset++;
        $tag = is_string($node['tag'] ?? null) ? $node['tag'] : '?';

        if (
            array_key_exists('content', $node)
            && is_array($node['content'])
            && wpb_lint_array_looks_like_nodes($node['content'])
        ) {
            $warnings[] = sprintf(
                "node at idx %d (tag=%s) has a 'content' key holding %d node-like objects. "
                . "Did you mean 'children'? The per-node nesting key is 'children' — "
                . 'see the wpbakery-build-page skill for the node shape.',
                $this_idx,
                $tag,
                count($node['content']),
            );
        }

        if (isset($node['children']) && is_array($node['children'])) {
            /** @var list<array<string, mixed>> $children */
            $children = $node['children'];
            $sub = wpb_lint_tree($children, $idx_offset);
            foreach ($sub as $s) {
                $warnings[] = $s;
            }
        }
    }
    return $warnings;
}

/**
 * Heuristic: does this array look like a list of WPBakery node objects?
 * Used by the soft linter to distinguish "agent put children under
 * `content` by mistake" from "node has a `content` att that happens to
 * be an array for legitimate reasons".
 *
 * @param array<array-key, mixed> $arr
 */
function wpb_lint_array_looks_like_nodes(array $arr): bool
{
    if ($arr === []) {
        return false;
    }
    /** @var mixed $item */
    foreach ($arr as $item) {
        if (!is_array($item)) {
            return false;
        }
        if (!isset($item['tag']) || !is_string($item['tag']) || $item['tag'] === '') {
            return false;
        }
    }
    return true;
}
