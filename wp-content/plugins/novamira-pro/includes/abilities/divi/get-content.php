<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

/**
 * Ability: Read a post's Divi 5 module tree as a compact, addressable list.
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_DIVI_GET_CONTENT_DEFAULT_MAX_NODES = 400;

const NOVAMIRA_DIVI_GET_CONTENT_DEFAULT_FIELD_BYTES = 2048;

wp_register_ability('novamira/divi-get-content', [
    'label' => __('Get Divi Content', domain: 'novamira-pro'),
    'description' => __(
        'Parses a post\'s Divi 5 module tree (the divi/* WordPress blocks in post_content) into a flat, addressable list of nodes. Divi\'s root divi/placeholder envelope is transparent: it consumes no address and is never listed, so top-level nodes keep their ordinary indices ("0", "1", … in document order; on a Divi-only page those are the sections) whether or not the stored content is wrapped. Each node carries its address (a slash path like "0/1/0" that locates it in the tree), module name, parent address, child addresses, and a compact summary (loop enabled, count of dynamic-content bindings, interaction count, raw_html flag). Pass include_attrs=true to also return each node\'s full attributes (byte-capped per field). Accepts the post id as "post" or "post_id". Node count is capped (default 400) with a truncation marker.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post' => [
                'type' => 'integer',
                'description' => 'Post id whose Divi content to read.',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'WordPress-native alias for "post".',
            ],
            'include_attrs' => [
                'type' => 'boolean',
                'description' => 'Include each node\'s full attributes object (default false).',
            ],
            'max_nodes' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 2000,
                'description' => 'Maximum number of nodes to return (default 400).',
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Per-attribute byte cap when include_attrs is true (default 2048; 0 disables).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'is_divi' => [
                'type' => 'boolean',
                'description' => 'True when the post contains at least one divi/* block.',
            ],
            'nodes' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
            ],
            'total_nodes' => ['type' => 'integer'],
            'returned_nodes' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_get_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the current tree before editing. The "address" path is the stable locator for a node (root sections are "0", "1", …; children append "/<index>"). The divi/placeholder envelope Divi stores around the page content consumes no address: top-level nodes keep their ordinary indices in document order (on a Divi-only page those are the sections; a non-Divi block at the top level occupies a slot too). Always address modules by the paths this ability returns. Use include_attrs=true sparingly — it can be large; the default compact view is enough to navigate the tree.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|\WP_Error
 */
function divi_get_content(array $input)
{
    /** @var mixed $id */
    $id = $input['post'] ?? $input['post_id'] ?? null;
    $post = divi_post_or_error($id);
    if ($post instanceof \WP_Error) {
        return $post;
    }

    $include_attrs = ($input['include_attrs'] ?? false) === true;
    $max_nodes = is_int($input['max_nodes'] ?? null)
        ? max(1, min($input['max_nodes'], 2000))
        : NOVAMIRA_DIVI_GET_CONTENT_DEFAULT_MAX_NODES;
    $max_field_bytes = is_int($input['max_field_bytes'] ?? null)
        ? max(0, $input['max_field_bytes'])
        : NOVAMIRA_DIVI_GET_CONTENT_DEFAULT_FIELD_BYTES;

    // Walk the SAME normalized tree the write abilities use (divi_parse_tree),
    // so the addresses reported here resolve identically when passed back to
    // edit/delete/move/etc. A separate raw-block walker drifted from the write
    // model on mixed (non-Divi-wrapped) content and could mis-target writes.
    $tree = divi_parse_tree($post->post_content);

    /** @var list<array<string, mixed>> $nodes */
    $nodes = [];
    $opts = ['include_attrs' => $include_attrs, 'max_field_bytes' => $max_field_bytes];
    divi_walk_tree($tree, parent: null, prefix: '', opts: $opts, nodes: $nodes);

    $total = count($nodes);
    $returned = array_slice($nodes, offset: 0, length: $max_nodes);

    // A page is "Divi" only if it holds a divi/* module — non-Divi passthrough
    // nodes (Gutenberg/classic blocks) also occupy the tree but must not count.
    $is_divi = false;
    foreach ($nodes as $node) {
        if (!is_string($node['name'] ?? null) || !str_starts_with($node['name'], 'divi/')) {
            continue;
        }
        $is_divi = true;
        break;
    }

    return [
        'post_id' => $post->ID,
        'is_divi' => $is_divi,
        'nodes' => $returned,
        'total_nodes' => $total,
        'returned_nodes' => count($returned),
        'truncated' => $total > count($returned),
    ];
}

/**
 * Walk the normalized node tree, emitting one compact node per module. Indices
 * match divi_tree_get's addressing (every node is a divi/* module), so the
 * `address` of each node round-trips to the write abilities.
 *
 * @param list<array<string, mixed>> $tree
 * @param array{include_attrs: bool, max_field_bytes: int} $opts
 * @param list<array<string, mixed>> $nodes
 */
function divi_walk_tree(array $tree, ?string $parent, string $prefix, array $opts, array &$nodes): void
{
    foreach ($tree as $index => $node) {
        $address = $prefix === '' ? (string) $index : $prefix . '/' . $index;
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        $nodes[] = divi_tree_node($node, $address, $parent, count($children), $opts);

        divi_walk_tree($children, $address, $address, $opts, $nodes);
    }
}

/**
 * Build one compact node from a normalized tree node ({name, attrs, children}).
 *
 * @param array<string, mixed> $node
 * @param array{include_attrs: bool, max_field_bytes: int} $opts
 * @return array<string, mixed>
 */
function divi_tree_node(array $node, string $address, ?string $parent, int $child_count, array $opts): array
{
    $name = is_string($node['name'] ?? null) ? $node['name'] : '';
    /** @var array<string, mixed> $attrs */
    $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

    $children = [];
    for ($i = 0; $i < $child_count; $i++) {
        $children[] = $address . '/' . $i;
    }

    $out = [
        'address' => $address,
        'name' => $name,
        'parent' => $parent,
        'children' => $children,
    ];

    $dynamic_count = divi_count_dynamic($attrs);
    if ($dynamic_count > 0) {
        $out['dynamic_bindings'] = $dynamic_count;
    }
    if (divi_attrs_have_loop($attrs)) {
        $out['loop_enabled'] = true;
    }
    $interactions = divi_attrs_interaction_count($attrs);
    if ($interactions > 0) {
        $out['interactions'] = $interactions;
    }
    if (in_array($name, NOVAMIRA_DIVI_RAW_HTML_MODULES, strict: true)) {
        $out['raw_html'] = true;
    }
    if ($opts['include_attrs']) {
        $out['attrs'] = divi_cap_attrs($attrs, $opts['max_field_bytes']);
    }

    return $out;
}

/**
 * Byte-cap the string leaves of an attributes object so include_attrs cannot
 * blow up the response with large rich-text / code fields.
 *
 * @param array<array-key, mixed> $attrs
 * @return array<array-key, mixed>
 */
function divi_cap_attrs(array $attrs, int $max_field_bytes): array
{
    if ($max_field_bytes <= 0) {
        return $attrs;
    }

    /** @var array<array-key, mixed> $capped */
    $capped = [];
    /** @var mixed $value */
    foreach ($attrs as $key => $value) {
        if (is_string($value)) {
            [$short, $truncated] = divi_truncate_bytes($value, $max_field_bytes);
            $capped[$key] = $truncated ? $short . '…[truncated]' : $short;
            continue;
        }
        if (is_array($value)) {
            $capped[$key] = divi_cap_attrs($value, $max_field_bytes);
            continue;
        }
        $capped[$key] = $value;
    }
    return $capped;
}
