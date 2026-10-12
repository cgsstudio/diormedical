<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shortcode string → nested tree (parser) and WPBakery-specific attribute
 * decoders. Decoding lives here because the parser invokes `wpb_decode_atts`
 * inline as it walks each match.
 */

/**
 * Parses a WPBakery shortcode string into a nested tree of nodes.
 *
 * Each node:
 *   tag      → shortcode tag name
 *   atts     → decoded attributes (vc_link decoded to array, param_group decoded to array)
 *   children → child nodes (for containers)
 *   _idx     → depth-first integer index (assigned by wpb_index_tree())
 *
 * @return list<array<string, mixed>>
 */
function wpb_parse_content(string $content, int $depth = 0): array
{
    if ($depth > 15 || trim($content) === '') {
        return [];
    }

    $tags = wpb_registered_tags();
    if ($tags === []) {
        return [];
    }

    $pattern = get_shortcode_regex($tags);
    $matches = [];
    preg_match_all("/{$pattern}/", $content, $matches, PREG_SET_ORDER);

    $nodes = [];
    foreach ($matches as $match) {
        // Groups per WordPress shortcode regex:
        // [1] extra [  [2] tag  [3] atts string  [4] self-closing /  [5] inner content  [6] closing tag
        $tag = $match[2] ?? '';
        $atts_raw = $match[3] ?? '';
        $inner = $match[5] ?? '';

        if ($tag === '') {
            continue;
        }

        /** @var array<string, mixed> $atts */
        $atts = shortcode_parse_atts(trim($atts_raw));

        // Decode special param types for better AI readability.
        $atts = wpb_decode_atts($tag, $atts);

        $children = $inner !== '' ? wpb_parse_content($inner, $depth + 1) : [];

        $node = [
            'tag' => $tag,
            'atts' => $atts,
            'children' => $children,
        ];

        // Preserve raw HTML inner content for elements like vc_column_text
        // where the inner content is not a WPBakery shortcode.
        if ($inner !== '' && $children === []) {
            $node['_content'] = $inner;
        }

        $nodes[] = $node;
    }

    return $nodes;
}

/**
 * Returns a memoised list of all WPBakery shortcode tags the parser should
 * recognise.
 *
 * Two registries combine into one list:
 *  - `WPBMap::getAllShortCodes()` — the page-level WPBakery shortcodes (vc_row,
 *    vc_column, vc_btn, vc_basic_grid, etc.).
 *  - A static `vc_gitem_*` set — Grid Item shortcodes used inside
 *    `vc_grid_item` template posts. Grid Builder registers these only
 *    conditionally (inside the grid render pipeline), so they are usually
 *    absent from `WPBMap` when the parser runs against the saved
 *    `post_content` of a `vc_grid_item` post. Including them statically lets
 *    `wpbakery-get-content` round-trip those templates correctly.
 *
 * The grid-item list is stable across WPBakery 6.5–8.x; expand it if
 * WPBakery introduces new `vc_gitem_*` tags in a future release.
 *
 * @return list<string>
 */
function wpb_registered_tags(): array
{
    static $tags = null;
    if ($tags === null) {
        $core_tags = array_map('strval', array_keys(\WPBMap::getAllShortCodes()));
        $grid_item_tags = [
            'vc_gitem_row',
            'vc_gitem_col',
            'vc_gitem_post_title',
            'vc_gitem_post_excerpt',
            'vc_gitem_post_date',
            'vc_gitem_post_content',
            'vc_gitem_post_data',
            'vc_gitem_post_data_source',
            'vc_gitem_image',
            'vc_gitem_post_meta',
            'vc_gitem_post_categories',
            'vc_gitem_post_link',
            'vc_gitem_zone',
            'vc_gitem_zone_a',
            'vc_gitem_zone_b',
            'vc_gitem_zone_c',
            'vc_gitem_animated_block',
            'vc_gitem_template',
        ];
        $tags = array_values(array_unique(array_merge($core_tags, $grid_item_tags)));
    }
    return $tags;
}

/**
 * Decodes special WPBakery attribute formats (vc_link, param_group) into
 * structured values so agents don't need to know the internal encoding.
 *
 * @param array<string, mixed> $atts
 * @return array<string, mixed>
 */
function wpb_decode_atts(string $tag, array $atts): array
{
    $settings = \WPBMap::getShortCode($tag);
    if ($settings === null) {
        return $atts;
    }

    /** @var list<array<string, mixed>> $params */
    $params = is_array($settings['params'] ?? null) ? $settings['params'] : [];

    foreach ($params as $param) {
        $name = (string) ($param['param_name'] ?? '');
        $type = (string) ($param['type'] ?? '');

        if ($name === '' || !array_key_exists($name, $atts)) {
            continue;
        }

        /** @var mixed $value */
        $value = $atts[$name];
        if (!is_string($value)) {
            continue;
        }

        if (wpb_is_link_param_type($type)) {
            // Only a value actually written in the pipe format becomes an
            // object. Inside a Grid Item template the very same att holds a
            // magic string ("post_link", "image_lightbox", …) and WPBakery's
            // href back-compat allows a bare URL: decoding either of those
            // would hand back an empty object and a read-modify-write would
            // then erase the link.
            if (wpb_looks_like_vc_link($value)) {
                $atts[$name] = wpb_decode_vc_link($value);
            }
            continue;
        }
        if ($type === 'param_group') {
            $decoded = wpb_decode_param_group($value);
            if ($decoded !== null) {
                $atts[$name] = $decoded;
            }
        }
    }

    return $atts;
}

/**
 * The marker WPBakery requires in front of a Grid Builder element's id.
 * Declared in Vc_Grid_Item's `$grid_id_unique_name`; the lookup regex that
 * resolves a grid is built from it.
 */
const WPB_GRID_ID_PREFIX = 'vc_gid';

/**
 * Encodes special WPBakery attribute formats back to their storage strings
 * before serialising to shortcode format.
 *
 * @param array<string, mixed> $atts
 * @return array<string, mixed>
 */
function wpb_encode_atts(string $tag, array $atts): array
{
    $settings = \WPBMap::getShortCode($tag);
    if ($settings === null) {
        return $atts;
    }

    /** @var list<array<string, mixed>> $params */
    $params = is_array($settings['params'] ?? null) ? $settings['params'] : [];

    foreach ($params as $param) {
        $name = (string) ($param['param_name'] ?? '');
        $type = (string) ($param['type'] ?? '');

        if ($name === '' || !array_key_exists($name, $atts)) {
            continue;
        }

        /** @var mixed $value */
        $value = $atts[$name];

        if (wpb_is_link_param_type($type) && is_array($value)) {
            /** @var array<string, mixed> $value */
            $atts[$name] = wpb_encode_vc_link($value);
            continue;
        }
        if ($type === 'param_group' && is_array($value)) {
            /** @var list<array<string, mixed>> $value */
            $atts[$name] = wpb_encode_param_group($value);
        }
    }

    return wpb_apply_grid_id($params, $atts);
}

/**
 * Gives a Grid Builder element the identifier WPBakery can resolve.
 *
 * A grid does not render its own items: the published page asks for them
 * over AJAX, quoting this att, and WPBakery looks it up in the map it keeps
 * under the post's `_vc_post_settings`. The lookup only matches a value of
 * the form `vc_gid:<id>` — anything else resolves to `failed_to_get_id` and
 * the grid stays empty for good. WPBakery's own editor generates the value,
 * so nothing outside it ever states the rule.
 *
 * An agent's own label is kept and prefixed, which keeps the att stable
 * across reads and writes; an element that arrives without one is given a
 * generated id rather than a broken grid.
 *
 * @param list<array<string, mixed>> $params
 * @param array<string, mixed> $atts
 * @return array<string, mixed>
 */
function wpb_apply_grid_id(array $params, array $atts): array
{
    $name = '';
    foreach ($params as $param) {
        if ((string) ($param['type'] ?? '') !== 'vc_grid_id') {
            continue;
        }
        $name = (string) ($param['param_name'] ?? '');
        break;
    }
    if ($name === '') {
        return $atts;
    }

    /** @var mixed $current */
    $current = $atts[$name] ?? '';
    $current = is_scalar($current) ? (string) $current : '';

    if (preg_match('/' . WPB_GRID_ID_PREFIX . ':([\w\-]+)/', $current) === 1) {
        return $atts;
    }

    $label = (string) preg_replace('/[^\w\-]/', replacement: '-', subject: $current);
    $label = trim($label, characters: '-');
    if ($label === '') {
        $label = uniqid();
    }
    $atts[$name] = WPB_GRID_ID_PREFIX . ':' . $label;

    return $atts;
}

/**
 * True when a param type stores a link in WPBakery's pipe-separated
 * `url:…|title:…|target:…|rel:…` form.
 *
 * WPBakery 9.0 introduced the unified `link` param type, which
 * "replaces both 'href' (plain URL) and 'vc_link'" (see
 * include/params/link/link.php). The storage format did not change —
 * both types are read back through vc_build_link() →
 * vc_parse_multi_attribute() — so both decode and encode identically.
 * The older `vc_link` type is still registered in 9.0 and is what
 * 6.5–8.x sites use, so both names must be honoured.
 */
function wpb_is_link_param_type(string $type): bool
{
    return $type === 'vc_link' || $type === 'link';
}

/**
 * True when a stored value is in WPBakery's `key:value|key:value` link
 * format. The probe is the one WPBakery itself uses to tell a link apart
 * from a bare value (include/params/link/link.php).
 */
function wpb_looks_like_vc_link(string $value): bool
{
    foreach (['url:', 'title:', 'target:', 'rel:'] as $marker) {
        if (str_contains($value, $marker)) {
            return true;
        }
    }

    return false;
}

/**
 * Decodes a vc_link pipe-separated string into an associative array.
 *
 * Input:  "url:https://example.com|title:Click|target:_blank|rel:nofollow"
 * Output: ["url" => "https://example.com", "title" => "Click", "target" => "_blank", "rel" => "nofollow"]
 *
 * @return array<string, string>
 */
function wpb_decode_vc_link(string $value): array
{
    if ($value === '') {
        return ['url' => '', 'title' => '', 'target' => '', 'rel' => ''];
    }

    $result = ['url' => '', 'title' => '', 'target' => '', 'rel' => ''];
    $parts = explode('|', $value);

    foreach ($parts as $part) {
        $colon = strpos($part, needle: ':');
        if ($colon === false) {
            continue;
        }
        $k = substr($part, offset: 0, length: $colon);
        $v = substr($part, offset: $colon + 1);
        if (array_key_exists($k, $result)) {
            // rawurldecode, not urldecode: WPBakery reads these back with
            // rawurldecode() in vc_parse_multi_attribute(), which leaves a
            // literal "+" alone. Decoding "+" as a space here would make us
            // report a value the site does not actually render.
            $result[$k] = rawurldecode($v);
        }
    }

    return $result;
}

/**
 * Encodes a vc_link array back to WPBakery's pipe-separated string.
 *
 * All four fields are rawurlencoded, the same way WPBakery's own link
 * param writes them (include/params/link/link.php) and the only encoding
 * its reader undoes (rawurldecode in vc_parse_multi_attribute). urlencode
 * would write "+" for a space, which WPBakery does not decode back, so a rel
 * of "noopener nofollow" would reach the front end as "noopener+nofollow".
 *
 * Encoding every field also keeps a literal `|` in `target` or `rel` from
 * splitting into the next field.
 *
 * @param array<string, mixed> $link
 */
function wpb_encode_vc_link(array $link): string
{
    $url = rawurlencode((string) ($link['url'] ?? ''));
    $title = rawurlencode((string) ($link['title'] ?? ''));
    $target = rawurlencode((string) ($link['target'] ?? ''));
    $rel = rawurlencode((string) ($link['rel'] ?? ''));

    return "url:{$url}|title:{$title}|target:{$target}|rel:{$rel}";
}

/**
 * Decodes a param_group URL-encoded JSON string into an array.
 *
 * @return list<array<string, mixed>>|null
 */
function wpb_decode_param_group(string $value): ?array
{
    if ($value === '') {
        return [];
    }

    /** @var mixed $decoded */
    $decoded = json_decode(urldecode($value), associative: true);
    if (!is_array($decoded)) {
        return null;
    }

    /** @var list<array<string, mixed>> $decoded */
    return $decoded;
}

/**
 * Encodes a param_group array back to WPBakery's URL-encoded JSON string.
 *
 * @param list<array<string, mixed>> $items
 */
function wpb_encode_param_group(array $items): string
{
    $json = wp_json_encode($items);
    if ($json === false) {
        return '';
    }
    return rawurlencode($json);
}
