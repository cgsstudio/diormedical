<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Add the defaults Breakdance's query-control runtime reads
 * unconditionally. Only the canonical element query-control paths are
 * inspected; other element properties are left untouched.
 *
 * @param array<string, mixed> $tree
 * @return array<string, mixed>
 */
function bd_normalize_query_envelopes(array $tree): array
{
    if (!isset($tree['root']) || !is_array($tree['root'])) {
        return $tree;
    }

    /** @var array<string, mixed> $root */
    $root = $tree['root'];
    $tree['root'] = bd_normalize_node_query_envelope($root);
    return $tree;
}

/**
 * Normalize a node's query envelope and recurse through its children.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function bd_normalize_node_query_envelope(array $node): array
{
    /** @var mixed $data_value */
    $data_value = $node['data'] ?? null;
    if (is_array($data_value)) {
        /** @var array<string, mixed> $data */
        $data = $data_value;
        /** @var mixed $properties_value */
        $properties_value = $data['properties'] ?? null;
        if (is_array($properties_value)) {
            /** @var array<string, mixed> $properties */
            $properties = $properties_value;
            bd_normalize_properties_query_envelopes($properties);
            $data['properties'] = $properties;
            $node['data'] = $data;
        }
    }

    /** @var mixed $children_value */
    $children_value = $node['children'] ?? null;
    if (!is_array($children_value)) {
        return $node;
    }

    /** @var mixed $child */
    foreach ($children_value as $index => $child) {
        if (!is_array($child)) {
            continue;
        }
        /** @var array<string, mixed> $child */
        $children_value[$index] = bd_normalize_node_query_envelope($child);
    }
    $node['children'] = $children_value;
    return $node;
}

/**
 * Normalize only Breakdance's two canonical wp_query control paths:
 * `content.query.query` for PostsList/PostsLoop and
 * `content.content.query` for WooProductsList. Arrays elsewhere may
 * also use an `active` key and must not be changed.
 *
 * @param array<string, mixed> $properties
 */
function bd_normalize_properties_query_envelopes(array &$properties): void
{
    /** @var mixed $content_value */
    $content_value = $properties['content'] ?? null;
    if (!is_array($content_value)) {
        return;
    }
    /** @var array<string, mixed> $content */
    $content = $content_value;
    $content = bd_normalize_query_control_group($content, group_key: 'query');
    $content = bd_normalize_query_control_group($content, group_key: 'content');
    $properties['content'] = $content;
}

/**
 * Normalize the wp_query envelope held in one known content group.
 *
 * @param array<string, mixed> $content
 * @return array<string, mixed>
 */
function bd_normalize_query_control_group(array $content, string $group_key): array
{
    /** @var mixed $query_control_value */
    $query_control_value = $content[$group_key] ?? null;
    if (!is_array($query_control_value)) {
        return $content;
    }
    /** @var array<string, mixed> $query_control */
    $query_control = $query_control_value;
    /** @var mixed $envelope_value */
    $envelope_value = $query_control['query'] ?? null;
    if (!is_array($envelope_value) || !array_key_exists('active', $envelope_value)) {
        return $content;
    }
    /** @var array<string, mixed> $envelope */
    $envelope = $envelope_value;
    $query_control['query'] = bd_normalize_query_envelope($envelope);
    $content[$group_key] = $query_control;
    return $content;
}

/**
 * Add the defaults read without guards by Breakdance's query runtime.
 *
 * @param array<string, mixed> $envelope
 * @return array<string, mixed>
 */
function bd_normalize_query_envelope(array $envelope): array
{
    bd_query_string_default($envelope, key: 'text', default: '');
    bd_query_string_default($envelope, key: 'php', default: '');
    if (!isset($envelope['custom']) || !is_array($envelope['custom'])) {
        $envelope['custom'] = [];
    }
    /** @var array<string, mixed> $custom */
    $custom = $envelope['custom'];
    bd_query_bool_default($custom, key: 'ignoreStickyPosts');
    bd_query_string_default($custom, key: 'date', default: 'all');
    bd_query_nullable_string_default($custom, key: 'beforeDate');
    bd_query_nullable_string_default($custom, key: 'afterDate');
    bd_query_bool_default($custom, key: 'ignoreCurrentPost');
    bd_query_string_default($custom, key: 'orderBy', default: 'date');
    bd_query_string_default($custom, key: 'order', default: 'DESC');
    $envelope['custom'] = $custom;
    return $envelope;
}

/**
 * @param array<string, mixed> $values
 */
function bd_query_string_default(array &$values, string $key, string $default): void
{
    if (!isset($values[$key]) || !is_string($values[$key])) {
        $values[$key] = $default;
    }
}

/**
 * @param array<string, mixed> $values
 */
function bd_query_bool_default(array &$values, string $key): void
{
    if (!isset($values[$key]) || !is_bool($values[$key])) {
        $values[$key] = false;
    }
}

/**
 * @param array<string, mixed> $values
 */
function bd_query_nullable_string_default(array &$values, string $key): void
{
    if (!array_key_exists($key, $values) || !is_string($values[$key]) && $values[$key] !== null) {
        $values[$key] = null;
    }
}
