<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-get-element-tree', [
    'label' => __('Get Mosaic Element Tree', domain: 'novamira-pro'),
    'description' => __(
        'Returns every published node of a Mosaic document (template / component / master / styleGuide) as a flat list AND as a hierarchical tree. Each node carries id, type (the element-widget identifier such as `section`, `div`, `text`, `button`), theme_id, document_type, document_id, parent_type (the literal `node` for inner nodes or the document_type for root nodes), parent_id (a node id, or the document_id for root nodes), ordering (a fractional-index string), status, and the decoded `data` blob (element settings). Size discipline: `max_nodes` caps the row count (default 500, hard cap 5000) — rows are fetched in BFS order from the document root, so truncation always trims the deepest layer first and the hierarchical `roots` view stays coherent; `max_field_bytes` caps per-node `data` size (default 4096, blobs above the cap become `{_truncated: true, _bytes: <orig>}`); `max_depth` caps hierarchical recursion in `roots` (default 64, descendants past the cap drop and the parent gets `children_truncated: true`). When the row count cap fires, `truncated = true` and the deepest descendants are silently omitted from both views.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'document_type' => [
                'type' => 'string',
                'enum' => ['template', 'master', 'styleGuide', 'component'],
                'description' => 'Which kind of canvas the tree belongs to.',
            ],
            'document_id' => [
                'type' => 'string',
                'description' => 'The document UUID — a template id (from mosaic-list-templates), a master/component id (Sprint 4), or a styleguide id.',
            ],
            'max_nodes' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5000,
                'default' => 500,
                'description' => 'Hard cap on returned nodes. Default 500 keeps a typical response under 1 MB even on styled trees (~2 KB per node). Raise for shallow trees, lower for design-system-heavy ones.',
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 65_536,
                'default' => 4096,
                'description' => 'Per-node `data` blob truncation in bytes. The blob is replaced with `{"_truncated": true, "_bytes": <original_size>}` when it exceeds this cap. 0 disables truncation. Default 4096 (4 KB) — enough for a styled-section but not for an entire design token tree.',
            ],
            'max_depth' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 256,
                'default' => 64,
                'description' => 'Hard cap on hierarchical depth in the `roots` view. Children past this depth are dropped (the parent gets `children_truncated: true`). Protects against unbounded recursion at JSON-encode time (PHP\'s default depth is 512).',
            ],
        ],
        'required' => ['document_type', 'document_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'document_type' => ['type' => 'string'],
            'document_id' => ['type' => 'string'],
            'theme_id' => ['type' => 'string'],
            'total' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'nodes' => ['type' => 'array'],
            'roots' => ['type' => 'array'],
        ],
        'required' => ['document_type', 'document_id', 'theme_id', 'total', 'truncated', 'nodes', 'roots'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_element_tree',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to inspect the canvas of a template (or any other document type) before editing. The flat `nodes` array is sorted by parent + ordering for stable diffing; `roots` is the hierarchical view (each node carries a `children` array of its direct descendants).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_get_element_tree(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $document_type = is_string($input['document_type'] ?? null) ? $input['document_type'] : '';
    $document_id = mo_norm_uuid($input['document_id'] ?? null);
    if ($document_id === '' || mo_root_parent_type($document_type) === null) {
        return mo_error('mo_invalid_input', __('document_type + document_id are required.', domain: 'novamira-pro'));
    }
    $caps = mo_element_tree_caps($input);
    $max_nodes = $caps['max_nodes'];
    $max_field_bytes = $caps['max_field_bytes'];
    $max_depth = $caps['max_depth'];

    $rows = mo_fetch_document_nodes_bfs($document_type, $document_id, $max_nodes + 1);
    $truncated = count($rows) > $max_nodes;
    if ($truncated) {
        $rows = array_slice($rows, offset: 0, length: $max_nodes);
    }
    $theme_id = $rows !== [] ? mo_str($rows[0], key: 'themeID') : '';

    return [
        'document_type' => $document_type,
        'document_id' => $document_id,
        'theme_id' => $theme_id,
        'total' => count($rows),
        'truncated' => $truncated,
        'nodes' => array_map(static fn(array $r): array => mo_present_node_with_caps($r, $max_field_bytes), $rows),
        'roots' => mo_build_tree($rows, $document_type, $document_id, $max_field_bytes, $max_depth),
    ];
}

/**
 * Fetch up to `$cap` nodes of a Mosaic document in BFS order — canvas /
 * root first, then each successive depth level by `ordering`. Replaces a
 * single global SELECT that ordered by `parentID` (round 6 F5): on trees
 * past 500 nodes the lex-sort put leaf widgets before their ancestors,
 * so the LIMIT cut off the canvas + sections entirely and the
 * hierarchical `roots` view collapsed to empty even with a perfectly
 * valid flat list. BFS guarantees ancestors precede descendants in the
 * keep-set, so truncation always trims from the deepest layer up — the
 * tree stays coherent.
 *
 * Implementation: level-by-level WHERE parentID IN (...). Stops when a
 * level returns no rows OR the cap is reached. Each level inherits the
 * BINARY ordering sort the single-pass query used.
 *
 * @return list<array<string, mixed>>
 */
function mo_fetch_document_nodes_bfs(string $document_type, string $document_id, int $cap): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    /** @var list<array<string, mixed>> $kept */
    $kept = [];
    $parent_type = $document_type;
    $parent_ids = [$document_id];
    while ($parent_ids !== [] && count($kept) < $cap) {
        $remaining = $cap - count($kept);
        $placeholders = implode(', ', array_fill(start_index: 0, count: count($parent_ids), value: '%s'));
        // @mago-expect analysis:possibly-invalid-argument
        $sql = (string) $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `parentType` = %s AND `parentID` IN ({$placeholders}) AND `status` = 'publish' ORDER BY `parentID` ASC, BINARY `ordering` ASC LIMIT %d",
            [$document_type, $document_id, $parent_type, ...$parent_ids, $remaining],
        );
        /** @var mixed $raw */
        $raw = $wpdb->get_results($sql, 'ARRAY_A');
        if (!is_array($raw) || $raw === []) {
            break;
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $raw;
        $kept = array_merge($kept, $rows);
        $parent_ids = array_map(static fn(array $r): string => (string) ($r['ID'] ?? ''), $rows);
        $parent_type = 'node';
    }
    return $kept;
}

/**
 * Pull the 3 sizing caps out of the input map with clamps applied.
 * Extracted so mosaic_get_element_tree stays under mago's cyclomatic
 * complexity threshold.
 *
 * @param array<string, mixed> $input
 * @return array{max_nodes:int, max_field_bytes:int, max_depth:int}
 */
function mo_element_tree_caps(array $input): array
{
    return [
        'max_nodes' => max(1, min(5000, is_int($input['max_nodes'] ?? null) ? $input['max_nodes'] : 500)),
        'max_field_bytes' => max(0, min(
            65_536,
            is_int($input['max_field_bytes'] ?? null) ? $input['max_field_bytes'] : 4096,
        )),
        'max_depth' => max(1, min(256, is_int($input['max_depth'] ?? null) ? $input['max_depth'] : 64)),
    ];
}

/**
 * mo_present_node + a per-node `data` byte cap. The blob is replaced
 * with `{"_truncated": true, "_bytes": <orig>}` when it exceeds the cap,
 * keeping the agent's response size predictable on design-system-heavy
 * trees. `max_field_bytes = 0` disables the cap.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function mo_present_node_with_caps(array $row, int $max_field_bytes): array
{
    $node = mo_present_node($row);
    if ($max_field_bytes === 0) {
        return $node;
    }
    /** @var array<string, mixed> $data */
    $data = is_array($node['data'] ?? null) ? $node['data'] : [];
    // Used only for byte-counting, not for storage — either encoder works.
    $encoded = mo_encode_object_blob($data);
    if (strlen($encoded) > $max_field_bytes) {
        $node['data'] = ['_truncated' => true, '_bytes' => strlen($encoded)];
    }
    return $node;
}

/**
 * Build the hierarchical roots[] view: every root carries a `children`
 * array of presented nodes (recursively). Detached subtrees (rows with
 * a parent that didn't survive the publish/truncation filter) are
 * silently skipped — never returned as fake roots — so the tree always
 * resolves cleanly. The recursion is depth-capped at `$max_depth` so
 * pathological trees never hit PHP's json_encode default depth (512).
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function mo_build_tree(
    array $rows,
    string $document_type,
    string $document_id,
    int $max_field_bytes,
    int $max_depth,
): array {
    $by_parent = [];
    foreach ($rows as $row) {
        $pt = mo_str($row, key: 'parentType');
        $pid = mo_str($row, key: 'parentID');
        $by_parent[$pt . ':' . $pid][] = $row;
    }
    $root_key = $document_type . ':' . $document_id;
    return mo_assemble_children($by_parent, $root_key, $max_field_bytes, $max_depth, depth: 1);
}

/**
 * @param array<string, list<array<string, mixed>>> $by_parent
 * @return list<array<string, mixed>>
 */
function mo_assemble_children(
    array $by_parent,
    string $parent_key,
    int $max_field_bytes,
    int $max_depth,
    int $depth,
): array {
    $children = $by_parent[$parent_key] ?? [];
    $out = [];
    foreach ($children as $row) {
        $node = mo_present_node_with_caps($row, $max_field_bytes);
        $node_id = is_string($node['id'] ?? null) ? $node['id'] : '';
        if ($depth >= $max_depth) {
            // Stop recursing — the caller's max_depth has been hit. The
            // node is still returned but its descendants are omitted and
            // explicitly flagged so the agent can re-fetch with a deeper
            // cap or chase the subtree starting from this node's id.
            $node['children'] = [];
            $node['children_truncated'] = true;
            $out[] = $node;
            continue;
        }
        $node['children'] = mo_assemble_children(
            $by_parent,
            'node:' . $node_id,
            $max_field_bytes,
            $max_depth,
            $depth + 1,
        );
        $out[] = $node;
    }
    return $out;
}
