<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Edit (partial merge update) an existing WPBakery element.
 */

wp_register_ability('novamira/wpbakery-edit-element', [
    'label' => __('Edit WPBakery Element', domain: 'novamira-pro'),
    'description' => __(
        'Updates an existing WPBakery element identified by its _idx (from wpbakery-get-content). By default merges new atts (omitted keys keep their current value); pass replace=true to discard every existing att and use only the provided ones. To update the inner HTML of elements like vc_column_text, pass `_content` at the top level (sibling of `atts`, mirroring the node shape that set-content / get-content / add-element use). On invalid atts the response returns `invalid_values[]` with each bad key\'s value and the full inline `opts` list — pick a valid choice and retry, no separate get-element-schema call needed.',
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
            'element_idx' => [
                'type' => 'integer',
                'description' => 'The _idx of the element to edit (from wpbakery-get-content).',
            ],
            'atts' => [
                'type' => 'object',
                'description' => 'Atts to apply to the element. Merged with existing atts by default; replaces them when replace=true.',
            ],
            '_content' => [
                'type' => ['string', 'null'],
                'description' => 'Raw inner HTML for elements that store their body as a textarea_html shortcode content (e.g. vc_column_text). Mirrors the node shape used by wpbakery-set-content / wpbakery-get-content. As a convenience for callers that already nest _content inside atts (the older form), passing atts._content is also accepted and takes precedence.',
            ],
            'replace' => [
                'type' => 'boolean',
                'description' => 'When true, discard every existing att on the element and use only the keys provided in atts. Use this to clear stale Design Options or reset an element to a known minimal state. Default false (merge).',
                'default' => false,
            ],
        ],
        'required' => ['post_id', 'element_idx', 'atts'],
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
                'description' => 'The post-write tree — same shape wpbakery-get-content returns. _idx values are stable across pure edit-element calls, but the tree is included for consistency with the other mutating abilities.',
            ],
            'tag' => [
                'type' => 'string',
                'description' => 'Echoed tag when validation fails so the agent can pass it to wpbakery-get-element-schema if needed.',
            ],
            'deprecated_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Atts WPBakery still accepts but no longer honours. The write went through; the element ignores them at render time.',
            ],
            'dropped_keys' => [
                'type' => 'array',
                'description' => 'Keys that are not in the element schema. Removed from the atts before write.',
            ],
            'invalid_values' => [
                'type' => 'array',
                'description' => 'Bad enum values. Each entry is {key, value, opts}: `opts` already lists every valid choice — pick one and retry, no separate schema call needed.',
            ],
            'hint' => ['type' => 'string', 'description' => 'One-line recovery hint when validation fails.'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\WPBakery\wpbakery_edit_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call wpbakery-get-content first to find the _idx of the element to edit. _idx values change after every structural write (add/delete/move/set-content) but NOT after a pure edit-element — you can chain edits without re-fetching. Default semantics is merge: only the att keys you pass are changed, the rest stay. Pass replace=true when you intend to wipe every existing att (typical use case: reset Design Options or remove stale styles before applying a fresh set). For elements like vc_column_text, pass `_content` as a top-level sibling of `atts` (same shape get-content / set-content / add-element use).',
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
function wpbakery_edit_element(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $element_idx = (int) ($input['element_idx'] ?? -1);
    /** @var array<string, mixed> $new_atts */
    $new_atts = is_array($input['atts'] ?? null) ? $input['atts'] : [];
    $replace = ($input['replace'] ?? false) === true;

    // _content accepted either as a top-level sibling of atts (mirrors the
    // node shape used by set-content / get-content / add-element) OR nested
    // inside atts (legacy form). atts._content takes precedence if both are
    // present so callers that already migrated keep working.
    $new_content = wpb_extract_edit_content($input, $new_atts);

    $writable_error = wpb_post_writable_or_error($post_id);
    if ($writable_error !== null) {
        return $writable_error;
    }

    $raw = wpb_get_post_content($post_id) ?? '';
    $tree = wpb_parse_indexed_tree($raw);

    $deprecated_keys = [];
    $schema_error = wpb_validate_edit_schema($tree, $element_idx, $new_atts, $deprecated_keys);
    if ($schema_error !== null) {
        return $schema_error;
    }

    $result = wpb_do_edit_element($tree, $raw, $post_id, [
        'idx' => $element_idx,
        'atts' => $new_atts,
        'content' => $new_content,
        'replace' => $replace,
    ]);

    if ($deprecated_keys !== []) {
        $result['deprecated_keys'] = $deprecated_keys;
    }

    return $result;
}

/**
 * Pulls the inner-HTML override from the edit-element input. Accepts both
 * shapes for backward symmetry with set-content / get-content / add-element:
 *  - top-level `_content` (preferred — mirrors the node shape).
 *  - `atts._content` (legacy form). Strips it from atts before further use
 *    so it doesn't leak into the atts merge.
 * atts._content wins when both are present.
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> &$new_atts
 */
function wpb_extract_edit_content(array $input, array &$new_atts): ?string
{
    if (array_key_exists('_content', $new_atts)) {
        $content = (string) $new_atts['_content'];
        unset($new_atts['_content']);
        return $content;
    }
    if (array_key_exists('_content', $input) && is_string($input['_content'])) {
        return $input['_content'];
    }
    return null;
}

/**
 * Validates the atts against the element's schema. Returns an error array on
 * failure, or null when validation passes (or no schema exists for the tag).
 *
 * @param list<array<string, mixed>> $tree
 * @param array<string, mixed> $new_atts
 * @param list<string> &$deprecated_keys Filled with atts WPBakery still
 *        registers but no longer honours.
 * @return array<string, mixed>|null
 */
function wpb_validate_edit_schema(array $tree, int $element_idx, array $new_atts, array &$deprecated_keys): ?array
{
    $node = wpb_find_node_by_idx($tree, $element_idx);
    if ($node === null) {
        return ['success' => false, 'error' => sprintf('Element with _idx %d not found.', $element_idx)];
    }

    $tag = (string) ($node['tag'] ?? '');
    $schema = wpb_compact_element($tag);
    if ($schema === null) {
        return null;
    }

    $validation = wpb_validate_atts($new_atts, $schema);
    $deprecated_keys = $validation['deprecated_keys'];
    if ($validation['dropped_keys'] !== [] || $validation['invalid_values'] !== []) {
        return [
            'success' => false,
            'tag' => $tag,
            'dropped_keys' => $validation['dropped_keys'],
            'invalid_values' => $validation['invalid_values'],
            'hint' => wpb_validation_hint($tag, $validation),
        ];
    }

    return null;
}

/**
 * Applies the edit to the tree and saves. Called after all validation passes.
 *
 * @param list<array<string, mixed>> $tree
 * @param array{idx: int, atts: array<string, mixed>, content: ?string, replace: bool} $patch
 * @return array<string, mixed>
 */
function wpb_do_edit_element(array $tree, string $raw, int $post_id, array $patch): array
{
    $mutated = $patch['replace']
        ? wpb_replace_node($tree, $patch['idx'], $patch['atts'], $patch['content'])
        : wpb_edit_node($tree, $patch['idx'], $patch['atts'], $patch['content']);
    if (!$mutated) {
        return ['success' => false, 'error' => sprintf('Element with _idx %d not found.', $patch['idx'])];
    }

    return wpb_finalize_write($post_id, $tree, $raw);
}
