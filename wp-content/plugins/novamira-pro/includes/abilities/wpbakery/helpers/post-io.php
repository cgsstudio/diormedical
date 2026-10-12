<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Post-content read / write, foreign-shortcode preservation, the canonical
 * write finalizer used by every mutating ability, and the WPBakery post-meta
 * key constants. Sits on top of parse / serialize / tree.
 */

// WPBakery post-meta keys. Centralised here so a future WPBakery rename only
// requires one edit, and so the magic strings don't leak across ability files.
const WPB_META_EDITED = '_wpb_vc_js_status';

const WPB_META_CUSTOM_CSS = '_wpb_post_custom_css';

// WPBakery stores the page's shortcode Custom CSS under `_wpb_shortcodes_custom_css`.
const WPB_META_SHORTCODES_CSS = '_wpb_shortcodes_custom_css';

/**
 * Returns the raw post_content for a given post ID, or null if not found.
 */
function wpb_get_post_content(int $post_id): ?string
{
    /** @var \WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post instanceof \WP_Post) {
        return null;
    }
    return $post->post_content;
}

/**
 * Pre-write guard for every mutating ability. Returns null when the target
 * is safe to write to, or a standard {success:false, error:string} array
 * otherwise. Rejects three states that look writeable through `get_post()`
 * but should never be mutated through a builder ability:
 *  - `post_status === 'trash'`: edits would persist after untrash and
 *    surprise the user; the agent should ask to untrash first.
 *  - `post_status === 'auto-draft'`: temporary placeholder WP creates when
 *    the user clicks "Add New"; writing to it bypasses the intended
 *    create-post flow.
 *  - `post_type === 'revision'`: revisions are immutable history snapshots;
 *    mutating one corrupts the audit trail without touching the parent.
 *
 * Also collapses the "post not found" check, so every mutating ability can
 * use a single guard at the top instead of two separate (get_post / raw)
 * null checks.
 *
 * @return array{success: false, error: string}|null
 */
function wpb_post_writable_or_error(int $post_id): ?array
{
    /** @var \WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post instanceof \WP_Post) {
        return ['success' => false, 'error' => sprintf('Post %d not found.', $post_id)];
    }
    if ($post->post_status === 'trash') {
        return [
            'success' => false,
            'error' => sprintf('Post %d is in the trash. Untrash it before writing.', $post_id),
        ];
    }
    if ($post->post_status === 'auto-draft') {
        return [
            'success' => false,
            'error' => sprintf(
                'Post %d is an auto-draft (temporary placeholder WP creates when "Add New" is clicked); '
                . 'create a real post first via novamira/create-post before writing.',
                $post_id,
            ),
        ];
    }
    if ($post->post_type === 'revision') {
        return [
            'success' => false,
            'error' => sprintf(
                'Post %d is a revision (immutable history snapshot). Target the parent post instead.',
                $post_id,
            ),
        ];
    }
    return null;
}

/**
 * Parses a raw post_content string into the depth-first indexed tree shape
 * every mutating ability needs as its starting point. Extracted because the
 * `parse + index counter=0` pair was repeated verbatim across add/edit/
 * delete/move/edit-elements/apply-template.
 *
 * @return list<array<string, mixed>>
 */
function wpb_parse_indexed_tree(string $raw): array
{
    $tree = wpb_parse_content($raw);
    $counter = 0;
    return wpb_index_tree($tree, $counter);
}

/**
 * Saves a shortcode string as post_content and marks the post as a WPBakery
 * page. Returns true on success.
 */
function wpb_save_post_content(int $post_id, string $shortcode_string): bool
{
    // WPBakery listens on save_post and rebuilds its own post meta from the
    // $_POST fields its editor submits. Outside that editor those fields do
    // not exist, so the handler reads nothing and deletes what is stored:
    // the page's Custom CSS and the flag marking the post as WPBakery-built
    // both disappear on any programmatic save. Hold them across the write.
    $preserved = wpb_preserved_meta($post_id);

    $result = wp_update_post([
        'ID' => $post_id,
        'post_content' => $shortcode_string,
    ], wp_error: true);

    if (is_wp_error($result)) {
        return false;
    }

    wpb_restore_preserved_meta($post_id, $preserved);
    update_post_meta($post_id, meta_key: WPB_META_EDITED, meta_value: 'true');
    wpb_rebuild_post_settings($post_id);
    return true;
}

/**
 * Asks WPBakery to rebuild the per-post settings it derives from the
 * content, which is how a Grid Builder element becomes resolvable.
 *
 * A grid's items are fetched over AJAX, and the request quotes an id that
 * WPBakery looks up in `_vc_post_settings`. That map is built by a
 * `save_post` listener reading the fields its editor submits, so after a
 * programmatic write the map is missing and the grid renders empty. `Vc_Post_Admin::setSettings()` rebuilds it from `post_content`
 * alone, but it replaces the whole meta, so the keys it does not derive
 * are put back afterwards.
 */
function wpb_rebuild_post_settings(int $post_id): void
{
    if (!class_exists('Vc_Post_Admin')) {
        return;
    }

    /** @var mixed $before */
    $before = get_post_meta($post_id, key: '_vc_post_settings', single: true);

    (new \Vc_Post_Admin())->setSettings($post_id);

    if (!is_array($before)) {
        return;
    }
    /** @var mixed $after */
    $after = get_post_meta($post_id, key: '_vc_post_settings', single: true);
    $after = is_array($after) ? $after : [];
    /** @var mixed $value */
    foreach ($before as $key => $value) {
        if ($key === 'vc_grid_id' || array_key_exists($key, $after)) {
            continue;
        }
        $after[$key] = $value;
    }
    update_post_meta($post_id, meta_key: '_vc_post_settings', meta_value: $after);
}

/**
 * The WPBakery post meta a save_post listener may clear, captured as it
 * stands. Keys absent from the post are absent from the result, so a
 * restore never invents one.
 *
 * @return array<string, string>
 */
function wpb_preserved_meta(int $post_id): array
{
    $held = [];
    foreach ([WPB_META_CUSTOM_CSS, WPB_META_SHORTCODES_CSS] as $key) {
        /** @var mixed $value */
        $value = get_post_meta($post_id, key: $key, single: true);
        if (is_string($value) && $value !== '') {
            $held[$key] = $value;
        }
    }

    return $held;
}

/**
 * Puts back any of the captured values the save dropped. Values that survived
 * are left alone, so a legitimate change made during the save still stands.
 *
 * @param array<string, string> $preserved
 */
function wpb_restore_preserved_meta(int $post_id, array $preserved): void
{
    foreach ($preserved as $key => $value) {
        /** @var mixed $current */
        $current = get_post_meta($post_id, key: $key, single: true);
        if ($current === '' || $current === false) {
            update_post_meta($post_id, meta_key: $key, meta_value: $value);
        }
    }
}

/**
 * Whether a given post was last edited with WPBakery.
 */
function wpb_is_wpbakery_post(int $post_id): bool
{
    return get_post_meta($post_id, key: WPB_META_EDITED, single: true) === 'true';
}

/**
 * Scans a raw post_content string and returns every shortcode whose tag is
 * NOT in the WPBakery registry. Used by the write path to preserve foreign
 * shortcodes (e.g. `[gallery]`, `[wpforms]`, `[mailpoet_form]`) when the
 * serializer would otherwise drop them.
 *
 * The pattern matches any `[tag …][/tag]` or `[tag …/]` or `[tag]`
 * including nested inner content; it does NOT depend on whether the
 * foreign tag is registered as a WP shortcode at parse time.
 *
 * @return list<array{tag: string, match: string}>
 */
function wpb_extract_foreign_shortcodes(string $raw): array
{
    if (trim($raw) === '') {
        return [];
    }

    $wpb_tags = wpb_registered_tags();
    if ($wpb_tags === []) {
        return [];
    }

    // Generic shortcode regex (any tag), mirrors WP's get_shortcode_regex
    // structure but accepts any [\w-]+ tag name instead of a fixed list.
    $pattern =
        '/'
        . '\['
        . '(\[?)'
        . '([\w-]+)'
        . '(?![\w-])'
        . '('
        . '[^\]\/]*'
        . '(?:'
        . '\/(?!\])'
        . '[^\]\/]*'
        . ')*?'
        . ')'
        . '(?:'
        . '(\/)'
        . '\]'
        . '|'
        . '\]'
        . '(?:'
        . '('
        . '[^\[]*+'
        . '(?:'
        . '\[(?!\/\2\])'
        . '[^\[]*+'
        . ')*+'
        . ')'
        . '\[\/\2\]'
        . ')?'
        . ')'
        . '(\]?)'
        . '/s';

    $matches = [];
    preg_match_all($pattern, $raw, $matches);

    $foreign = [];
    $seen = [];
    /** @var array<int, string> $tag_captures */
    $tag_captures = $matches[2];
    /** @var array<int, string> $full_captures */
    $full_captures = $matches[0];
    foreach ($tag_captures as $i => $tag) {
        if ($tag === '' || in_array($tag, $wpb_tags, strict: true)) {
            continue;
        }
        $raw_match = $full_captures[$i];
        // Dedup by exact match string — if the same shortcode appears twice
        // in post_content (e.g. because a prior preservation pass already
        // appended it once), report it only once.
        if (isset($seen[$raw_match])) {
            continue;
        }
        $seen[$raw_match] = true;
        $foreign[] = ['tag' => $tag, 'match' => $raw_match];
    }
    return $foreign;
}

/**
 * Finalises a mutating ability call: saves the tree (preserving foreign
 * shortcodes), and returns the canonical success-shaped response or an
 * error array. Keeps each ability callback small (extracted to satisfy
 * the mago Halstead-effort threshold and to share the
 * preserved_foreign_shortcodes contract across abilities).
 *
 * @param list<array<string, mixed>> $tree
 * @return array<string, mixed>
 */
function wpb_finalize_write(int $post_id, array $tree, string $raw): array
{
    $save = wpb_save_tree_preserving_foreign($post_id, $tree, $raw);
    if (!$save['success']) {
        return ['success' => false, 'error' => 'Failed to save post content.'];
    }
    $result = [
        'success' => true,
        'post_id' => $post_id,
        'edit_url' => (string) get_edit_post_link($post_id, context: 'raw'),
        'tree' => wpb_load_post_write_tree($post_id),
    ];
    if ($save['preserved'] !== []) {
        $result['preserved_foreign_shortcodes'] = $save['preserved'];
    }
    return $result;
}

/**
 * Re-reads the post content and produces the canonical post-write tree
 * with fresh depth-first `_idx` values — same shape wpbakery-get-content
 * returns. Included in the response of every structural-write ability so
 * the agent can act on the new indices without an extra get-content
 * round-trip (sub-agents B8 / C3 / C4 / C5 independently asked for this).
 *
 * @return list<array<string, mixed>>
 */
function wpb_load_post_write_tree(int $post_id): array
{
    return wpb_parse_indexed_tree(wpb_get_post_content($post_id) ?? '');
}

/**
 * Serialises a WPBakery tree, then re-appends any foreign shortcodes that
 * were present in the pre-write raw content (preserving non-WPBakery
 * shortcodes like `[gallery]` / `[wpforms]` / `[mailpoet_form]` that the
 * serializer would otherwise drop). Saves the combined string as
 * post_content.
 *
 * `preserved` returns each foreign shortcode as `{tag, raw}` so the
 * caller (the response of every mutating ability) can name the
 * shortcode in plain English (via `tag`) without extracting it from
 * the raw string with a regex.
 *
 * @param list<array<string, mixed>> $tree
 * @return array{
 *     success: bool,
 *     shortcode_string: string,
 *     preserved: list<array{tag: string, raw: string}>,
 * }
 */
function wpb_save_tree_preserving_foreign(int $post_id, array $tree, string $original_raw): array
{
    $foreign = wpb_extract_foreign_shortcodes($original_raw);
    $serialized = wpb_serialize_content($tree);

    if ($foreign !== []) {
        $foreign_chunk = implode("\n\n", array_map(static fn(array $f): string => $f['match'], $foreign));
        $serialized = $serialized === '' ? $foreign_chunk : rtrim($serialized) . "\n\n" . $foreign_chunk;
    }

    $saved = wpb_save_post_content($post_id, $serialized);

    return [
        'success' => $saved,
        'shortcode_string' => $serialized,
        'preserved' => array_map(static fn(array $f): array => ['tag' => $f['tag'], 'raw' => $f['match']], $foreign),
    ];
}
