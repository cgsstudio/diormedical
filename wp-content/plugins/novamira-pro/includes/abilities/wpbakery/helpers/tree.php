<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * `_idx` assignment and tree navigation primitives used by every
 * add / edit / replace / delete / move ability.
 */

/**
 * Assigns a depth-first integer `_idx` to every node in the tree.
 * Returns the tree with _idx injected; the counter starts at 0.
 *
 * @param list<array<string, mixed>> $nodes
 * @return list<array<string, mixed>>
 */
function wpb_index_tree(array $nodes, int &$counter = 0): array
{
    foreach ($nodes as &$node) {
        $node['_idx'] = $counter++;
        if (($node['children'] ?? []) !== []) {
            /** @var list<array<string, mixed>> $children */
            $children = $node['children'];
            $node['children'] = wpb_index_tree($children, $counter);
        }
    }
    return $nodes;
}

/**
 * Finds and returns a reference to the node with the given _idx.
 * Returns null if not found.
 *
 * @param list<array<string, mixed>> $nodes
 * @return array<string, mixed>|null
 */
function wpb_find_node_by_idx(array &$nodes, int $idx): ?array
{
    foreach ($nodes as &$node) {
        if (($node['_idx'] ?? -1) === $idx) {
            return $node;
        }
        if (($node['children'] ?? []) !== []) {
            /** @var list<array<string, mixed>> $children */
            $children = &$node['children'];
            $found = wpb_find_node_by_idx($children, $idx);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

/**
 * Inserts $new_node into $nodes as a child of the node with $parent_idx,
 * at the given $position (0-based; -1 appends). If $parent_idx is null,
 * inserts at the root level.
 *
 * Returns true on success, false if the parent was not found.
 *
 * @param list<array<string, mixed>> $nodes
 * @param array<string, mixed> $new_node
 */
function wpb_insert_node(array &$nodes, array $new_node, ?int $parent_idx, int $position): bool
{
    if ($parent_idx === null) {
        // Insert at root level.
        if ($position >= 0 && $position < count($nodes)) {
            array_splice($nodes, offset: $position, length: 0, replacement: [$new_node]);
            return true;
        }
        $nodes[] = $new_node;
        return true;
    }

    foreach ($nodes as &$node) {
        if (($node['_idx'] ?? -1) === $parent_idx) {
            if (!is_array($node['children'])) {
                $node['children'] = [];
            }
            if ($position >= 0 && $position < count($node['children'])) {
                array_splice($node['children'], offset: $position, length: 0, replacement: [$new_node]);
                return true;
            }
            $node['children'][] = $new_node;
            return true;
        }

        if (($node['children'] ?? []) !== []) {
            /** @var list<array<string, mixed>> $children */
            $children = &$node['children'];
            if (wpb_insert_node($children, $new_node, $parent_idx, $position)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Merges $new_atts into the atts of the node with the given _idx.
 * If $new_content is not null, updates _content on the node (inner HTML for
 * elements like vc_column_text that use a textarea_html param).
 * Returns true on success, false if the node was not found.
 *
 * @param list<array<string, mixed>> $nodes
 * @param array<string, mixed> $new_atts
 */
function wpb_edit_node(array &$nodes, int $idx, array $new_atts, ?string $new_content = null): bool
{
    foreach ($nodes as &$node) {
        if (($node['_idx'] ?? -1) === $idx) {
            /** @var array<string, mixed> $current_atts */
            $current_atts = is_array($node['atts'] ?? null) ? $node['atts'] : [];
            $node['atts'] = array_merge($current_atts, $new_atts);
            if ($new_content !== null) {
                $node['_content'] = $new_content;
            }
            return true;
        }
        if (($node['children'] ?? []) !== []) {
            /** @var list<array<string, mixed>> $children */
            $children = &$node['children'];
            if (wpb_edit_node($children, $idx, $new_atts, $new_content)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Replaces a node's atts wholesale (and optionally its _content), finding the
 * node by its `_idx`. Walks into child trees recursively. Returns true when
 * the node was located, false otherwise.
 *
 * @param list<array<string, mixed>> $nodes Tree mutated in place.
 * @param array<string, mixed> $new_atts The new complete atts; existing atts
 *                                       on the matched node are discarded.
 */
function wpb_replace_node(array &$nodes, int $idx, array $new_atts, ?string $new_content = null): bool
{
    foreach ($nodes as &$node) {
        if (($node['_idx'] ?? -1) === $idx) {
            $node['atts'] = $new_atts;
            if ($new_content !== null) {
                $node['_content'] = $new_content;
            }
            return true;
        }
        if (($node['children'] ?? []) !== []) {
            /** @var list<array<string, mixed>> $children */
            $children = &$node['children'];
            if (wpb_replace_node($children, $idx, $new_atts, $new_content)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Removes the node with the given _idx from the tree.
 * Returns true on success, false if not found.
 *
 * @param list<array<string, mixed>> $nodes
 */
function wpb_delete_node(array &$nodes, int $idx): bool
{
    foreach ($nodes as $i => $node) {
        if (($node['_idx'] ?? -1) === $idx) {
            array_splice($nodes, offset: $i, length: 1);
            return true;
        }
        if (($nodes[$i]['children'] ?? []) !== []) {
            /** @var list<array<string, mixed>> $children */
            $children = &$nodes[$i]['children'];
            if (wpb_delete_node($children, $idx)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Finds nodes nested inside another node carrying the same tag.
 *
 * WordPress's shortcode parser cannot handle a shortcode nested inside
 * itself: `[x]a[x]b[/x]c[/x]` matches the FIRST closing tag, so the outer
 * one is left behind and printed to the visitor as literal text. The page
 * is broken by construction, whatever the atts say.
 *
 * WPBakery designed around this — it ships `vc_row_inner` and
 * `vc_column_inner` precisely so a row can sit inside a row. The trap is
 * `vc_tta_section`, which an agent reaches for naturally when it puts an
 * accordion inside a tab, since both containers take sections.
 *
 * Returns one entry per offending node, outermost first.
 *
 * @param list<array<string, mixed>> $nodes
 * @param list<string> $ancestors Tags of the nodes above this level.
 * @return list<array{tag: string, path: list<string>}>
 */
function wpb_self_nested_nodes(array $nodes, array $ancestors = []): array
{
    $found = [];

    foreach ($nodes as $node) {
        $tag = is_string($node['tag'] ?? null) ? $node['tag'] : '';
        if ($tag === '') {
            continue;
        }

        if (in_array($tag, $ancestors, strict: true)) {
            $found[] = ['tag' => $tag, 'path' => [...$ancestors, $tag]];
        }

        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($children !== []) {
            foreach (wpb_self_nested_nodes($children, [...$ancestors, $tag]) as $deeper) {
                $found[] = $deeper;
            }
        }
    }

    return $found;
}

/**
 * Builds the error payload for a tree that nests a tag inside itself, or
 * null when the tree is fine.
 *
 * @param list<array<string, mixed>> $nodes
 * @return array<string, mixed>|null
 */
function wpb_self_nesting_error(array $nodes): ?array
{
    $found = wpb_self_nested_nodes($nodes);
    if ($found === []) {
        return null;
    }

    $tags = array_values(array_unique(array_column($found, 'tag')));
    $alternatives = [
        'vc_row' => 'vc_row_inner',
        'vc_column' => 'vc_column_inner',
        'vc_section' => 'vc_row',
    ];

    $hints = [];
    foreach ($tags as $tag) {
        $hints[] = isset($alternatives[$tag])
            ? sprintf('use %s for the inner one', $alternatives[$tag])
            : sprintf('%s has no inner variant — give the nested container its own row instead', $tag);
    }

    return [
        'success' => false,
        'error' => sprintf(
            'These tags are nested inside themselves: %s. WordPress cannot parse a shortcode nested inside itself — the closing tag of the outer one is printed to the visitor as literal text — so the page would not render. Fix: %s.',
            implode(', ', $tags),
            implode('; ', $hints),
        ),
        'code' => 'wpbakery_self_nested_tag',
        'self_nested' => array_map(static fn(array $f): string => implode(' > ', $f['path']), $found),
    ];
}
