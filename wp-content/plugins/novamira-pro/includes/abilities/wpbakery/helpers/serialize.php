<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Nested tree → shortcode string (serializer) plus the container-detection
 * helper used to decide whether a node renders self-closed or with a closing
 * tag.
 */

/**
 * Returns true when the element should always use a closing shortcode tag.
 *
 * WPBakery marks some containers via `is_container` in WPBMap, but elements
 * like vc_column_text use a closing tag to store inner HTML without setting
 * that flag. We detect them by checking for a `textarea_html` param type,
 * which means the inner shortcode content is their primary payload.
 */
function wpb_element_is_container(string $tag): bool
{
    $settings = \WPBMap::getShortCode($tag);
    if ($settings === null) {
        return false;
    }
    if (($settings['is_container'] ?? false) !== false) {
        return true;
    }
    /** @var list<array<string, mixed>> $params */
    $params = is_array($settings['params'] ?? null) ? $settings['params'] : [];
    foreach ($params as $param) {
        if (($param['type'] ?? '') === 'textarea_html') {
            return true;
        }
    }
    return false;
}

/**
 * Serialises a tree of nodes back to a WPBakery shortcode string.
 *
 * @param list<array<string, mixed>> $nodes
 */
function wpb_serialize_content(array $nodes): string
{
    return implode('', array_map(__NAMESPACE__ . '\\wpb_serialize_node', $nodes));
}

/**
 * Serialises a single node to its shortcode representation.
 *
 * @param array<string, mixed> $node
 */
function wpb_serialize_node(array $node): string
{
    $tag = (string) ($node['tag'] ?? '');
    /** @var array<string, mixed> $atts */
    $atts = is_array($node['atts'] ?? null) ? $node['atts'] : [];
    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['children'] ?? null) ? $node['children'] : [];

    if ($tag === '') {
        return '';
    }

    // Re-encode special types before building the attribute string.
    $atts = wpb_encode_atts($tag, $atts);

    $atts_string = wpb_build_atts_string($atts);

    $is_container = wpb_element_is_container($tag);

    if ($is_container || $children !== [] || array_key_exists('_content', $node)) {
        $inner = $children !== [] ? wpb_serialize_content($children) : (string) ($node['_content'] ?? '');
        return '[' . $tag . $atts_string . ']' . $inner . '[/' . $tag . ']';
    }

    return '[' . $tag . $atts_string . ']';
}

/**
 * Builds a shortcode attribute string from an associative array.
 * Skips _idx (internal) and null/empty values.
 *
 * @param array<string, mixed> $atts
 */
function wpb_build_atts_string(array $atts): string
{
    $parts = [];
    foreach (array_keys($atts) as $key) {
        if ($key === '_idx' || $key === '') {
            continue;
        }
        /** @var mixed $raw */
        $raw = $atts[$key];
        if ($raw === null || $raw === '') {
            continue;
        }
        $str_value = is_array($raw) ? (string) wp_json_encode($raw) : (string) $raw;
        if ($str_value === '') {
            continue;
        }
        // Escape double quotes inside attribute values.
        $parts[] = $key . '="' . str_replace(search: '"', replace: '&quot;', subject: $str_value) . '"';
    }

    return $parts !== [] ? ' ' . implode(' ', $parts) : '';
}
