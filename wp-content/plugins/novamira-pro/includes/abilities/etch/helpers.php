<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Hard caps on the page element tree, to keep ability output bounded and reject
 * pathological input. A real Etch page is far smaller than these.
 */
const ETCH_MAX_TREE_NODES = 5000;

const ETCH_MAX_TREE_DEPTH = 50;

/**
 * Per-node payload caps. attrs are JSON-encoded into the block delimiter, where
 * a nesting depth at/over the 512 json_encode/json_decode limit silently drops
 * the data; a 64-level cap stays well clear. Byte caps bound a single ability
 * call's memory so one node cannot OOM the request.
 */
const ETCH_MAX_ATTRS_DEPTH = 64;

const ETCH_MAX_ATTRS_BYTES = 131_072;

const ETCH_MAX_HTML_BYTES = 262_144;

/** Total serialized post_content cap, a backstop against an OOM from many large nodes. */
const ETCH_MAX_MARKUP_BYTES = 4_194_304;

/**
 * Whether Etch is active on this site.
 *
 * Etch defines ETCH_PLUGIN_FILE in its main file and autoloads the `Etch\`
 * namespace, so the two signals together are a reliable activation check.
 */
function etch_active(): bool
{
    return defined('ETCH_PLUGIN_FILE') && class_exists('Etch\\Plugin');
}

/**
 * Etch plugin version, or 'unknown' when Etch is not loaded.
 *
 * Etch exposes no version constant; the version lives in the plugin header and
 * is read back through Etch\Plugin::get_plugin_version().
 */
function etch_version(): string
{
    if (!class_exists('Etch\\Plugin')) {
        return 'unknown';
    }

    $version = \Etch\Plugin::get_plugin_version();

    return $version !== '' ? $version : 'unknown';
}

/**
 * Whether the installed Etch supports the per-post custom field VALUES API
 * (the etch-api/cms/post/<id>/field-values routes), added in Etch 1.5.0. The
 * value abilities are gated on this floor. A pure feature FLOOR — never a
 * ceiling: any Etch at or above NOVAMIRA_PRO_ETCH_CF_VALUES_MIN_VERSION
 * qualifies, so newer releases keep working.
 */
function etch_supports_field_values(): bool
{
    return etch_active() && version_compare(etch_version(), \NOVAMIRA_PRO_ETCH_CF_VALUES_MIN_VERSION, operator: '>=');
}

/**
 * Standard error returned by Etch abilities when Etch is not active.
 */
function etch_not_active_error(): WP_Error
{
    return new WP_Error('etch_not_active', __(
        'Etch is not active on this site. Install and activate the Etch plugin (https://etchwp.com) to use these abilities.',
        domain: 'novamira-pro',
    ));
}

/**
 * Read an Etch option that stores a keyed library (components, styles, loops,
 * queries, …) and normalize it to an array. Non-array stored values become [].
 *
 * @return array<array-key, mixed>
 */
function etch_option_array(string $name): array
{
    /** @var mixed $value */
    $value = get_option($name, default_value: []);

    return is_array($value) ? $value : [];
}

/**
 * Build a namespaced WP_Error with the etch_ code prefix.
 */
function etch_error(string $code, string $message): WP_Error
{
    return new WP_Error($code, $message);
}

/**
 * Sanitize a short human label/title for storage. Like sanitize_text_field but
 * also strips NUL and other C0/DEL control bytes (which sanitize_text_field
 * lets through and which silently corrupt a stored post_title / label).
 */
function etch_clean_text(string $text): string
{
    $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', replacement: '', subject: $text);

    return sanitize_text_field(is_string($stripped) ? $stripped : $text);
}

/**
 * Count the array-valued top-level entries of a keyed Etch option (components,
 * styles, loops, …). This matches what the list abilities enumerate (they skip
 * non-array junk), so check-setup's counts stay in parity even on corrupt data.
 */
function etch_count_option_entries(string $option): int
{
    $entries = etch_option_array($option);
    $count = 0;
    foreach (array_keys($entries) as $key) {
        if (!is_array($entries[$key])) {
            continue;
        }

        $count++;
    }

    return $count;
}

/**
 * Acquire a DB-level advisory lock for a read-modify-write on a shared Etch
 * option, scoped by blog id so multisite installs don't collide. Returns the
 * lock name on success, or null when the lock could not be taken or the database
 * lacks GET_LOCK (e.g. SQLite in dev — the caller then proceeds unlocked, which
 * is acceptable outside production MySQL/MariaDB). Callers MUST re-read the
 * option inside the lock and release it in a finally block.
 */
function etch_option_lock_acquire(string $option): ?string
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return null;
    }

    $name = 'etch_lock_' . get_current_blog_id() . '_' . $option;
    $sql = $wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 5);
    if (!is_string($sql)) {
        return null;
    }

    /** @var mixed $got */
    $got = $wpdb->get_var($sql);

    return $got === '1' ? $name : null;
}

/**
 * Release a lock taken by etch_option_lock_acquire(). No-op when $lock is null.
 */
function etch_option_lock_release(?string $lock): void
{
    if ($lock === null) {
        return;
    }

    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }

    $sql = $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock);
    if (is_string($sql)) {
        $wpdb->get_var($sql);
    }
}

/**
 * Resolve a post id to a usable WP_Post, or a WP_Error. Rejects revisions and
 * auto-drafts so callers never edit a non-canonical object by accident.
 *
 * @return WP_Post|WP_Error
 */
function etch_post_or_error(int $post_id): WP_Post|WP_Error
{
    if ($post_id <= 0) {
        return etch_error('etch_invalid_input', __('A positive post_id is required.', domain: 'novamira-pro'));
    }

    /** @var WP_Post|null $post */
    $post = get_post($post_id);

    if (!$post instanceof WP_Post) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %d: post ID. */
            __('Post %d was not found.', domain: 'novamira-pro'),
            $post_id,
        ));
    }

    if ($post->post_type === 'revision' || $post->post_status === 'auto-draft' || $post->post_status === 'trash') {
        return etch_error('etch_invalid_post', sprintf(
            /* translators: %d: post ID. */
            __('Post %d is a revision, auto-draft, or trashed and cannot be edited.', domain: 'novamira-pro'),
            $post_id,
        ));
    }

    return $post;
}

/**
 * Resolve a component id to its wp_block WP_Post, or a WP_Error. An Etch
 * component is a published wp_block carrying the etch_component_html_key meta
 * (mirrors Etch's own ComponentsRoutes 404/412 checks).
 *
 * @return WP_Post|WP_Error
 */
function etch_component_or_error(int $component_id): WP_Post|WP_Error
{
    if ($component_id <= 0) {
        return etch_error('etch_invalid_input', __('A positive component_id is required.', domain: 'novamira-pro'));
    }

    /** @var WP_Post|null $post */
    $post = get_post($component_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'wp_block') {
        return etch_error('etch_not_found', sprintf(
            /* translators: %d: component ID. */
            __('Component %d was not found.', domain: 'novamira-pro'),
            $component_id,
        ));
    }

    /** @var mixed $key */
    $key = get_post_meta($component_id, key: 'etch_component_html_key', single: true);
    if (!is_string($key) || $key === '') {
        return etch_error('etch_not_component', sprintf(
            /* translators: %d: post ID. */
            __('Post %d is a block pattern but not an Etch component.', domain: 'novamira-pro'),
            $component_id,
        ));
    }

    return $post;
}

/**
 * Count the published Etch components — wp_block posts carrying the
 * etch_component_html_key meta. This is where Etch stores components (the
 * etch_components option is legacy), so it is the count an agent should see.
 */
function etch_count_components(): int
{
    $query = new \WP_Query([
        'post_type' => 'wp_block',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
        'meta_query' => [['key' => 'etch_component_html_key', 'compare' => 'EXISTS']],
    ]);

    return (int) $query->found_posts;
}

/**
 * PHP 8.0-safe array_is_list(). The project targets PHP 8.0, where the native
 * function (8.1+) is unavailable.
 *
 * @param array<array-key, mixed> $value
 */
function etch_is_list(array $value): bool
{
    $expected = 0;
    foreach (array_keys($value) as $key) {
        if ($key !== $expected) {
            return false;
        }

        $expected++;
    }

    return true;
}

/**
 * Convert WordPress parsed blocks into the compact Etch node tree the abilities
 * expose: each node is { block, attrs?, children?, html? }. A leaf block whose
 * content lives in innerHTML (core blocks, freeform HTML) keeps it in `html` so
 * a get -> set round-trip is lossless. Freeform blocks (null blockName) become
 * empty-string `block` nodes; their pure-whitespace separators are skipped.
 * The walk stops once $max_nodes is reached; compare with etch_count_block_nodes
 * to detect truncation.
 *
 * @param array<array-key, mixed> $blocks
 * @return list<array<string, mixed>>
 */
function etch_blocks_to_tree(array $blocks, int $max_nodes, int &$count): array
{
    $tree = [];

    // Iterate keys (a typed list) rather than binding the value directly, so the
    // genuinely-mixed block values never trip the analyzer's mixed-assignment.
    foreach (array_keys($blocks) as $block_key) {
        if ($count >= $max_nodes) {
            break;
        }

        $node = etch_block_to_node($blocks[$block_key], $max_nodes, $count);
        if ($node !== null) {
            $tree[] = $node;
        }
    }

    return $tree;
}

/**
 * Convert one parsed block into a compact node, or null to skip it (non-array,
 * or a whitespace-only freeform separator). Recurses into inner blocks.
 *
 * @return array<string, mixed>|null
 */
function etch_block_to_node(mixed $block, int $max_nodes, int &$count): ?array
{
    if (!is_array($block)) {
        return null;
    }

    /** @var mixed $name */
    $name = $block['blockName'] ?? null;
    $is_freeform = !is_string($name) || $name === '';

    /** @var mixed $inner_html */
    $inner_html = $block['innerHTML'] ?? '';
    $inner_html = is_string($inner_html) ? $inner_html : '';

    if ($is_freeform && trim($inner_html) === '') {
        return null;
    }

    $count++;

    $node = ['block' => $is_freeform ? '' : $name];

    /** @var mixed $attrs */
    $attrs = $block['attrs'] ?? [];
    if (is_array($attrs) && $attrs !== []) {
        $node['attrs'] = $attrs;
    }

    return etch_attach_children($node, $block['innerBlocks'] ?? [], $inner_html, $max_nodes, $count);
}

/**
 * Attach a block's inner blocks (as children) or its leaf innerHTML (as html) to
 * a node. A node with real children never also carries html.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function etch_attach_children(array $node, mixed $inner, string $inner_html, int $max_nodes, int &$count): array
{
    $children = is_array($inner) ? etch_blocks_to_tree($inner, $max_nodes, $count) : [];
    if ($children !== []) {
        $node['children'] = $children;

        return $node;
    }

    // A leaf block whose content lives in innerHTML (core block, freeform HTML)
    // keeps it so set-content can write it back.
    if (trim($inner_html) !== '') {
        $node['html'] = $inner_html;
    }

    return $node;
}

/**
 * Count the nodes etch_blocks_to_tree would emit from a parsed block list,
 * ignoring the node cap. Used to report truncation without a stateful flag.
 *
 * @param array<array-key, mixed> $blocks
 */
function etch_count_block_nodes(array $blocks): int
{
    $count = 0;

    foreach (array_keys($blocks) as $block_key) {
        /** @var mixed $block */
        $block = $blocks[$block_key];
        if (!is_array($block)) {
            continue;
        }

        /** @var mixed $name */
        $name = $block['blockName'] ?? null;
        /** @var mixed $inner_html */
        $inner_html = $block['innerHTML'] ?? '';
        $is_freeform = !is_string($name) || $name === '';

        if ($is_freeform && trim(is_string($inner_html) ? $inner_html : '') === '') {
            continue;
        }

        $count++;

        /** @var mixed $inner */
        $inner = $block['innerBlocks'] ?? [];
        if (is_array($inner)) {
            $count += etch_count_block_nodes($inner);
        }
    }

    return $count;
}

/**
 * Count the freeform HTML fragments interleaved BETWEEN a container's child
 * blocks. The compact tree only captures innerBlocks, so such fragments (which
 * WordPress stores as literal strings in a container's innerContent, not as
 * blocks) are not represented and would be lost on a get -> set round-trip. This
 * lets get-content report `lossy` honestly instead of dropping them silently.
 *
 * @param array<array-key, mixed> $blocks
 */
function etch_count_interleaved(array $blocks): int
{
    $count = 0;

    foreach (array_keys($blocks) as $block_key) {
        /** @var mixed $block */
        $block = $blocks[$block_key];
        if (!is_array($block)) {
            continue;
        }

        /** @var mixed $inner */
        $inner = $block['innerBlocks'] ?? [];
        if (!is_array($inner) || $inner === []) {
            // Only container blocks interleave text around their children; a
            // leaf's innerHTML is captured into the node's `html`.
            continue;
        }

        /** @var mixed $content */
        $content = $block['innerContent'] ?? [];
        if (is_array($content)) {
            foreach (array_keys($content) as $chunk_key) {
                /** @var mixed $chunk */
                $chunk = $content[$chunk_key];
                if (is_string($chunk) && trim($chunk) !== '') {
                    $count++;
                }
            }
        }

        $count += etch_count_interleaved($inner);
    }

    return $count;
}

/**
 * Convert a (validated) compact Etch node tree back into WordPress block arrays
 * suitable for serialize_blocks(). Containers carry one null innerContent slot
 * per child; a leaf with `html` serializes that as its inner content; an empty
 * `block` becomes a freeform (null blockName) HTML node; bare leaves are
 * self-closing.
 *
 * @param array<array-key, mixed> $tree
 * @return list<array<string, mixed>>
 */
function etch_tree_to_blocks(array $tree): array
{
    $blocks = [];

    foreach (array_keys($tree) as $node_key) {
        /** @var mixed $node */
        $node = $tree[$node_key];
        if (!is_array($node)) {
            continue;
        }

        /** @var mixed $name */
        $name = $node['block'] ?? '';
        /** @var mixed $attrs */
        $attrs = $node['attrs'] ?? [];
        /** @var mixed $child_tree */
        $child_tree = $node['children'] ?? [];
        /** @var mixed $html */
        $html = $node['html'] ?? '';

        $children = is_array($child_tree) ? etch_tree_to_blocks($child_tree) : [];
        $html = is_string($html) ? $html : '';
        $block_name = is_string($name) && $name !== '' ? $name : null;

        $inner_html = '';
        $inner_content = [];
        if ($children !== []) {
            $inner_content = array_fill(start_index: 0, count: count($children), value: null);
        }
        if ($children === [] && $html !== '') {
            $inner_html = $html;
            $inner_content = [$html];
        }

        $blocks[] = [
            'blockName' => $block_name,
            'attrs' => is_array($attrs) ? $attrs : [],
            'innerBlocks' => $children,
            'innerHTML' => $inner_html,
            'innerContent' => $inner_content,
        ];
    }

    return $blocks;
}

/**
 * Sum the serialized byte weight of a (validated) tree — attrs JSON + html — so
 * set-content can reject an oversized tree BEFORE serialize_blocks materializes
 * (and could OOM on) the full markup.
 *
 * @param array<array-key, mixed> $tree
 */
function etch_tree_bytes(array $tree): int
{
    $bytes = 0;

    foreach (array_keys($tree) as $key) {
        /** @var mixed $node */
        $node = $tree[$key];
        if (!is_array($node)) {
            continue;
        }

        /** @var mixed $attrs */
        $attrs = $node['attrs'] ?? null;
        if (is_array($attrs)) {
            $encoded = wp_json_encode($attrs);
            $bytes += is_string($encoded) ? strlen($encoded) : 0;
        }

        /** @var mixed $html */
        $html = $node['html'] ?? null;
        if (is_string($html)) {
            $bytes += strlen($html);
        }

        /** @var mixed $children */
        $children = $node['children'] ?? null;
        if (is_array($children)) {
            $bytes += etch_tree_bytes($children);
        }
    }

    return $bytes;
}

/**
 * Validate a compact node tree, enforce the size caps, and serialize it to Etch
 * block markup (UNSLASHED — callers wp_slash at the persistence boundary). Sets
 * $node_count to the validated node count. Shared by set-content and the
 * component create/edit abilities so they get the same audited tree safety.
 *
 * @return string|WP_Error
 */
function etch_tree_to_markup_or_error(mixed $tree, int &$node_count): string|WP_Error
{
    if (!is_array($tree)) {
        return etch_error('etch_invalid_input', __('"tree" must be an array of nodes.', domain: 'novamira-pro'));
    }

    $node_count = 0;
    $error = etch_validate_tree($tree, ETCH_MAX_TREE_NODES, ETCH_MAX_TREE_DEPTH, depth: 1, count: $node_count);
    if ($error instanceof WP_Error) {
        return $error;
    }

    // Reject an oversized tree before serialize_blocks builds the whole string.
    if (etch_tree_bytes($tree) > ETCH_MAX_MARKUP_BYTES) {
        return etch_error('etch_too_large', sprintf(
            /* translators: %d: maximum serialized size in bytes. */
            __('The content exceeds the maximum of %d bytes.', domain: 'novamira-pro'),
            ETCH_MAX_MARKUP_BYTES,
        ));
    }

    // Auto-bind per-class style records: ensure every element carrying a class
    // that has a single-class etch_styles record also lists that record's id in
    // attrs.styles, so a class set through the abilities stays rendered and
    // editable in Etch (Etch only emits a class record's CSS for elements that
    // reference its id). Mirrors what the editor wires when you add a class.
    $tree = etch_autobind_tree_styles($tree, etch_class_style_index());

    /** @var array<int|string, array{attrs: array<array-key, mixed>, blockName: null|string, innerBlocks: array<array-key, array<array-key, mixed>>, innerContent: array<array-key, mixed>, innerHTML: string}> $blocks */
    $blocks = etch_tree_to_blocks($tree);
    $markup = serialize_blocks($blocks);

    if (strlen($markup) > ETCH_MAX_MARKUP_BYTES) {
        return etch_error('etch_too_large', sprintf(
            /* translators: %d: maximum serialized size in bytes. */
            __('The serialized content exceeds the maximum of %d bytes.', domain: 'novamira-pro'),
            ETCH_MAX_MARKUP_BYTES,
        ));
    }

    return $markup;
}

/**
 * Index single-class style records as { className => [recordId, ...] }. Only
 * records whose selector is exactly one plain class (".name") are indexed;
 * compound, pseudo, id, element and attribute selectors are skipped, because the
 * Etch editor only auto-wires a plain class record to an element's styles.
 *
 * @return array<string, list<string>>
 */
function etch_class_style_index(): array
{
    $index = [];
    $styles = etch_styles_option();
    foreach (array_keys($styles) as $id) {
        if (!is_string($id) || $id === '') {
            continue;
        }
        /** @var mixed $record */
        $record = $styles[$id];
        if (!is_array($record)) {
            continue;
        }
        /** @var mixed $selector */
        $selector = $record['selector'] ?? null;
        $class = is_string($selector) ? etch_single_class_selector($selector) : null;
        if ($class !== null) {
            $index[$class][] = $id;
        }
    }

    return $index;
}

/**
 * The class name of a single-class selector (".hero" -> "hero"), or null when the
 * selector is not exactly one plain class.
 */
function etch_single_class_selector(string $selector): ?string
{
    $matches = [];
    if (preg_match('/^\.([-_A-Za-z0-9]+)$/', trim($selector), $matches) === 1) {
        return $matches[1];
    }

    return null;
}

/**
 * Auto-bind per-class style records to the elements that carry the matching
 * class. For every style-supporting node, the id of each single-class style
 * record whose class appears in attrs.attributes.class is ensured to be present
 * in attrs.styles. Existing ids and their order are preserved; only missing ids
 * are appended, and classes with no matching record are left alone. Idempotent.
 *
 * @param array<array-key, mixed> $tree
 * @param array<string, list<string>> $index className => style record ids
 * @return array<array-key, mixed>
 */
function etch_autobind_tree_styles(array $tree, array $index): array
{
    foreach (array_keys($tree) as $key) {
        /** @var mixed $node */
        $node = $tree[$key];
        if (is_array($node)) {
            $tree[$key] = etch_autobind_node_styles($node, $index);
        }
    }

    return $tree;
}

/**
 * Auto-bind one node (recursing into its children first). See
 * etch_autobind_tree_styles().
 *
 * @param array<array-key, mixed> $node
 * @param array<string, list<string>> $index
 * @return array<array-key, mixed>
 */
function etch_autobind_node_styles(array $node, array $index): array
{
    /** @var mixed $children */
    $children = $node['children'] ?? null;
    if (is_array($children)) {
        $node['children'] = etch_autobind_tree_styles($children, $index);
    }

    /** @var mixed $block */
    $block = $node['block'] ?? null;
    /** @var mixed $attrs */
    $attrs = $node['attrs'] ?? null;
    if (
        !is_string($block)
        || !in_array($block, ['etch/element', 'etch/dynamic-element', 'etch/dynamic-image', 'etch/svg'], strict: true)
        || !is_array($attrs)
    ) {
        return $node;
    }

    $wanted = etch_styles_for_classes(etch_node_class_list($attrs), $index);
    $merged = etch_merge_style_ids($attrs['styles'] ?? null, $wanted);
    if ($merged === null) {
        return $node;
    }

    $attrs['styles'] = $merged;
    $node['attrs'] = $attrs;

    return $node;
}

/**
 * The class names on a node, parsed from attrs.attributes.class.
 *
 * @param array<array-key, mixed> $attrs
 * @return list<string>
 */
function etch_node_class_list(array $attrs): array
{
    /** @var mixed $attributes */
    $attributes = $attrs['attributes'] ?? null;
    if (!is_array($attributes)) {
        return [];
    }
    /** @var mixed $class */
    $class = $attributes['class'] ?? null;
    if (!is_string($class) || trim($class) === '') {
        return [];
    }
    $parts = preg_split('/\s+/', trim($class));

    return is_array($parts) ? array_values(array_filter($parts, static fn(string $c): bool => $c !== '')) : [];
}

/**
 * Resolve the style record ids wanted by a node's classes.
 *
 * @param list<string> $classes
 * @param array<string, list<string>> $index
 * @return list<string>
 */
function etch_styles_for_classes(array $classes, array $index): array
{
    $ids = [];
    foreach ($classes as $class) {
        foreach ($index[$class] ?? [] as $id) {
            $ids[] = $id;
        }
    }

    return $ids;
}

/**
 * Merge wanted ids into an existing attrs.styles value, preserving the existing
 * ids and their order. Returns the merged list, or null when nothing new would
 * be added (so the caller can leave the node untouched).
 *
 * @param list<string> $wanted
 * @return list<string>|null
 */
function etch_merge_style_ids(mixed $existing, array $wanted): ?array
{
    $merged = [];
    if (is_array($existing)) {
        /** @var mixed $sid */
        foreach ($existing as $sid) {
            if (!is_string($sid)) {
                continue;
            }
            $merged[] = $sid;
        }
    }

    $added = false;
    foreach ($wanted as $id) {
        if (in_array($id, $merged, strict: true)) {
            continue;
        }
        $merged[] = $id;
        $added = true;
    }

    return $added ? $merged : null;
}

/**
 * Recursively validate a compact Etch node tree from ability input. Enforces the
 * node-count and depth caps and the { block, attrs?, children? } shape. Returns
 * a WP_Error on the first problem, or null when the tree is well-formed.
 *
 * @param array<array-key, mixed> $tree
 */
function etch_validate_tree(array $tree, int $max_nodes, int $max_depth, int $depth, int &$count): ?WP_Error
{
    if ($depth > $max_depth) {
        return etch_error('etch_too_deep', sprintf(
            /* translators: %d: maximum nesting depth. */
            __('Tree exceeds the maximum nesting depth of %d.', domain: 'novamira-pro'),
            $max_depth,
        ));
    }

    // Iterate keys and pass each value as a function argument (not a variable
    // binding) so the untrusted mixed node values stay clear of the analyzer's
    // mixed-assignment rule.
    foreach (array_keys($tree) as $key) {
        $error = etch_validate_node($tree[$key], $max_nodes, $max_depth, $depth, $count);
        if ($error instanceof WP_Error) {
            return $error;
        }
    }

    return null;
}

/**
 * Validate a single (untrusted) node and recurse into its children. Splitting
 * the per-node check out of etch_validate_tree() lets the node arrive as a typed
 * `mixed` parameter instead of a foreach binding.
 */
function etch_validate_node(mixed $node, int $max_nodes, int $max_depth, int $depth, int &$count): ?WP_Error
{
    if (!is_array($node)) {
        return etch_error('etch_invalid_input', __('Each node must be an object.', domain: 'novamira-pro'));
    }

    /** @var mixed $block */
    $block = $node['block'] ?? null;
    if (!is_string($block)) {
        return etch_error('etch_invalid_input', __('Each node requires a "block" string.', domain: 'novamira-pro'));
    }

    $field_error = etch_validate_node_fields($node, $block);
    if ($field_error instanceof WP_Error) {
        return $field_error;
    }

    $count++;
    if ($count > $max_nodes) {
        return etch_error('etch_too_large', sprintf(
            /* translators: %d: maximum node count. */
            __('Tree exceeds the maximum of %d nodes.', domain: 'novamira-pro'),
            $max_nodes,
        ));
    }

    if (isset($node['children'])) {
        if (!is_array($node['children'])) {
            return etch_error('etch_invalid_input', sprintf(
                /* translators: %s: block name. */
                __('"children" for block "%s" must be an array.', domain: 'novamira-pro'),
                $block,
            ));
        }

        return etch_validate_tree($node['children'], $max_nodes, $max_depth, depth: $depth + 1, count: $count);
    }

    return null;
}

/**
 * Validate a node's block name, attrs, html, and field combination. Split into
 * focused helpers to keep each function under the complexity threshold.
 *
 * @param array<array-key, mixed> $node
 */
function etch_validate_node_fields(array $node, string $block): ?WP_Error
{
    $name_error = etch_validate_block_name($node, $block);
    if ($name_error instanceof WP_Error) {
        return $name_error;
    }

    // A node carries content via children OR html, never both — etch_attach_children
    // drops html when children exist, so reject the ambiguous combination loudly.
    // Only a NON-EMPTY children array conflicts; children:[] degrades to a leaf.
    /** @var mixed $node_children */
    $node_children = $node['children'] ?? null;
    if (isset($node['html']) && is_array($node_children) && $node_children !== []) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: block name. */
            __('A node cannot carry both "html" and a non-empty "children" (block "%s").', domain: 'novamira-pro'),
            $block,
        ));
    }

    $attrs_error = etch_validate_node_attrs($node, $block);
    if ($attrs_error instanceof WP_Error) {
        return $attrs_error;
    }

    return etch_validate_node_html($node, $block);
}

/**
 * Validate a node's block name: freeform (empty) requires html; otherwise it
 * must be a clean, length-bounded "namespace/name" slug. The slug check blocks
 * stored-XSS via the block name (e.g. "etch/x --><script>"); the `D` modifier
 * closes the trailing-newline edge of `$`.
 *
 * @param array<array-key, mixed> $node
 */
function etch_validate_block_name(array $node, string $block): ?WP_Error
{
    if ($block === '') {
        if (!isset($node['html'])) {
            return etch_error('etch_invalid_input', __(
                'A node with an empty "block" must provide "html" (freeform HTML content).',
                domain: 'novamira-pro',
            ));
        }

        return null;
    }

    // WordPress block names allow underscores in either segment; match that
    // grammar so get-content output for such blocks can be written back.
    if (strlen($block) > 100 || preg_match('#^[a-z][a-z0-9_-]*/[a-z][a-z0-9_-]*$#D', $block) !== 1) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: the offending block name. */
            __(
                'Block name "%s" is invalid; expected a "namespace/name" slug such as "etch/element".',
                domain: 'novamira-pro',
            ),
            $block,
        ));
    }

    return null;
}

/**
 * Validate a node's attrs: must be a keyed object (not a list), and not so deep
 * or large that JSON encoding silently drops it (the 512-depth cap) or exhausts
 * memory.
 *
 * @param array<array-key, mixed> $node
 */
function etch_validate_node_attrs(array $node, string $block): ?WP_Error
{
    if (!isset($node['attrs'])) {
        return null;
    }

    /** @var mixed $node_attrs */
    $node_attrs = $node['attrs'];
    if (!is_array($node_attrs)) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: block name. */
            __('"attrs" for block "%s" must be an object.', domain: 'novamira-pro'),
            $block,
        ));
    }

    if ($node_attrs !== [] && etch_is_list($node_attrs)) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: block name. */
            __('"attrs" for block "%s" must be a keyed object, not a list.', domain: 'novamira-pro'),
            $block,
        ));
    }

    $encoded = wp_json_encode($node_attrs, flags: 0, depth: ETCH_MAX_ATTRS_DEPTH);
    if (!is_string($encoded) || strlen($encoded) > ETCH_MAX_ATTRS_BYTES) {
        return etch_error('etch_attrs_too_large', sprintf(
            /* translators: %s: block name. */
            __('"attrs" for block "%s" is too large or too deeply nested to store safely.', domain: 'novamira-pro'),
            $block,
        ));
    }

    return null;
}

/**
 * Validate a node's html: a non-empty string within the byte cap, free of block
 * delimiters (which parse_blocks would otherwise re-interpret as real blocks).
 *
 * @param array<array-key, mixed> $node
 */
function etch_validate_node_html(array $node, string $block): ?WP_Error
{
    if (!isset($node['html'])) {
        return null;
    }

    /** @var mixed $html */
    $html = $node['html'];
    // Reject whitespace-only html too: a freeform (empty-block) node with only
    // whitespace serializes to a separator that get-content drops on read, so
    // the node would silently vanish on round-trip.
    if (!is_string($html) || trim($html) === '') {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: block name. */
            __('"html" for block "%s" must be a non-empty (non-whitespace) string.', domain: 'novamira-pro'),
            $block,
        ));
    }

    if (strlen($html) > ETCH_MAX_HTML_BYTES) {
        return etch_error('etch_html_too_large', sprintf(
            /* translators: %s: block name. */
            __('"html" for block "%s" exceeds the maximum size.', domain: 'novamira-pro'),
            $block,
        ));
    }

    if (preg_match('#<!--\s*/?\s*wp:#i', $html) === 1) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: block name. */
            __(
                '"html" for block "%s" must not contain block-comment delimiters (<!-- wp: ... -->).',
                domain: 'novamira-pro',
            ),
            $block,
        ));
    }

    return null;
}
