<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * External-CSS-file support for the Bricks writers.
 *
 * With "CSS loading method" set to external files, Bricks serves each post's
 * generated CSS from uploads/bricks/css/post-<id>.min.css and only rewrites
 * that file from its `save_post` listener — which a bare post-meta write
 * never triggers (the builder's own save calls wp_update_post for exactly
 * that reason). Bricks even creates an EMPTY per-post file when the post is
 * first inserted, so an API-built page renders through a stale, empty file:
 * markup present, styles absent. Every content write therefore regenerates
 * the affected post's file through Bricks' own generator, and every global
 * store write makes sure the CSS directory exists before Bricks' option
 * listeners try to write into it.
 */

/**
 * True when Bricks serves CSS from external files.
 */
/**
 * The CSS Bricks compiles for the global classes used by an element tree.
 *
 * Global-class rules are NOT part of a post's CSS file — Bricks emits them
 * inline on the front end — so reading the post file alone shows a page
 * styled by classes without any of the class rules in it, and a search for a
 * class name comes back empty on a page that plainly uses it.
 *
 * The generator reads the classes from the boot snapshot, which does not
 * carry anything written earlier in this same request, so the snapshot is
 * refreshed from the option first.
 *
 * @param list<array<string, mixed>> $elements
 */
function bricks_global_classes_css(array $elements): string
{
    if (!class_exists(\Bricks\Assets::class) || !method_exists(\Bricks\Assets::class, 'generate_global_classes')) {
        return '';
    }
    if (class_exists(\Bricks\Database::class)) {
        /** @var mixed $stored */
        $stored = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
        \Bricks\Database::$global_data['globalClasses'] = is_array($stored) ? $stored : [];
    }
    bricks_reset_css_generation_state();
    \Bricks\Assets::$inline_css['global_classes'] = '';
    \Bricks\Assets::generate_css_from_elements($elements, 'content');
    \Bricks\Assets::generate_global_classes();

    return \Bricks\Assets::$inline_css['global_classes'];
}

function bricks_css_loading_is_file(): bool
{
    if (!class_exists(\Bricks\Database::class)) {
        return false;
    }

    return \Bricks\Database::get_setting('cssLoading') === 'file';
}

/**
 * Absolute path of Bricks' CSS file directory (uploads/bricks/css), or ''
 * when Bricks is not active.
 */
function bricks_css_dir(): string
{
    if (!class_exists(\Bricks\Assets::class)) {
        return '';
    }

    $dir = \Bricks\Assets::$css_dir;
    if ($dir !== '') {
        return $dir;
    }

    $uploads = wp_upload_dir(create_dir: false);

    return $uploads['basedir'] . '/bricks/css';
}

/**
 * Makes sure the CSS file directory exists and is writable. Returns '' when
 * it is, otherwise the reason it cannot be used.
 */
function bricks_ensure_css_dir(): string
{
    $dir = bricks_css_dir();
    if ($dir === '') {
        return 'Bricks assets directory unavailable';
    }
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
        return sprintf('cannot create %s', $dir);
    }
    if (!wp_is_writable($dir)) {
        return sprintf('%s is not writable', $dir);
    }

    return '';
}

/**
 * The area name Bricks' own generator expects for a post: the template type
 * for Bricks templates (header, footer, section, popup, …), "content" for
 * everything else.
 */
function bricks_css_content_type(int $post_id): string
{
    if (get_post_type($post_id) !== BRICKS_DB_TEMPLATE_SLUG) {
        return 'content';
    }

    return bricks_template_type($post_id);
}

/**
 * Regenerates the per-post CSS file for a post after its elements changed,
 * the way Bricks' save listener does. Returns a compact status:
 *  - "regenerated"  — the file was written (or removed because the tree
 *                     produces no CSS);
 *  - "inline"       — Bricks serves CSS inline, nothing to regenerate;
 *  - "skipped(…)"   — file mode is on but the file could not be written; the
 *                     reason names the fix.
 *
 * @param list<array<string, mixed>> $elements
 */
function bricks_regenerate_post_css(int $post_id, array $elements): string
{
    if (!bricks_css_loading_is_file()) {
        return 'inline';
    }

    if (
        !class_exists(\Bricks\Assets_Files::class)
        || !method_exists(\Bricks\Assets_Files::class, 'generate_post_css_file')
    ) {
        return 'skipped(the installed Bricks build exposes no per-post CSS generator — regenerate the CSS files from Bricks > Settings > Performance)';
    }

    $dir_problem = bricks_ensure_css_dir();
    if ($dir_problem !== '') {
        return sprintf(
            'skipped(%s — fix the uploads permissions, then regenerate the CSS files from Bricks > Settings > Performance)',
            $dir_problem,
        );
    }

    bricks_reset_css_generation_state();
    try {
        /** @var mixed $written */
        $written = \Bricks\Assets_Files::generate_post_css_file($post_id, bricks_css_content_type($post_id), $elements);
    } catch (\Throwable $e) {
        return sprintf('skipped(Bricks CSS generator failed: %s)', $e->getMessage());
    }

    $path = sprintf('%s/post-%d.min.css', bricks_css_dir(), $post_id);
    if (is_string($written) && $written !== '' && file_exists($path)) {
        return 'regenerated';
    }
    if (!file_exists($path)) {
        // The tree produced no CSS: Bricks deletes the file, which is the
        // correct state for the frontend loader.
        return 'regenerated';
    }

    return sprintf(
        'skipped(%s was not rewritten — regenerate the CSS files from Bricks > Settings > Performance)',
        $path,
    );
}

/**
 * Appends the `return_css` payload to a content-write result: in external
 * file mode the content of the post's regenerated CSS file — what the front
 * end actually loads, covering every area of the post — otherwise the inline
 * CSS Bricks generates for the written area. `css_source` names which one
 * was used, and orphan custom-CSS declarations are reported as warnings.
 *
 * @param array<string, mixed>       $result   write result carrying post_id and css_file
 * @param list<array<string, mixed>> $elements the stored tree of the area
 * @param list<string>               $warnings
 * @return array{0: array<string, mixed>, 1: list<string>}
 */
function bricks_append_return_css(array $result, array $elements, string $area, array $warnings): array
{
    $post_id = is_int($result['post_id'] ?? null) ? $result['post_id'] : 0;
    $file_status = is_string($result['css_file'] ?? null) ? $result['css_file'] : '';

    $from_file = $post_id > 0 && bricks_css_loading_is_file() && $file_status === 'regenerated';
    ['css' => $css, 'truncated' => $truncated, 'size' => $size] = $from_file
        ? bricks_post_css_file_content($post_id)
        : ['css' => bricks_render_area_css($elements, $area), 'truncated' => false, 'size' => 0];

    $result['css'] = $css;
    $result['css_source'] = $from_file ? 'file' : 'inline';
    if ($truncated) {
        $result['css_truncated'] = true;
        $warnings[] = sprintf(
            'css holds the first %d KiB of post-%d.min.css (%d bytes): the file is larger than the return_css limit. Read the file itself for the rest.',
            (int) (NOVAMIRA_BRICKS_RETURN_CSS_LIMIT / 1024),
            $post_id,
            $size,
        );
    }
    foreach (bricks_detect_orphan_css($css) as $orphan_warning) {
        $warnings[] = $orphan_warning;
    }

    return [$result, $warnings];
}

/**
 * Upper bound, in bytes, of the CSS a write returns from the post's file.
 */
const NOVAMIRA_BRICKS_RETURN_CSS_LIMIT = 262_144;

/**
 * Content of the post's external CSS file (up to the return_css limit),
 * whether it was cut, and the file's full size; empty when there is none.
 *
 * @return array{css: string, truncated: bool, size: int}
 */
function bricks_post_css_file_content(int $post_id): array
{
    $path = sprintf('%s/post-%d.min.css', bricks_css_dir(), $post_id);
    clearstatcache(clear_realpath_cache: true, filename: $path);
    if (!file_exists($path)) {
        return ['css' => '', 'truncated' => false, 'size' => 0];
    }
    $size = filesize($path);
    $size = is_int($size) ? $size : 0;
    $content = file_get_contents($path, length: NOVAMIRA_BRICKS_RETURN_CSS_LIMIT);

    return [
        'css' => is_string($content) ? $content : '',
        'truncated' => $size > NOVAMIRA_BRICKS_RETURN_CSS_LIMIT,
        'size' => $size,
    ];
}

/**
 * Post types enabled for Bricks (the `postTypes` global setting), read the
 * way Bricks reads it: the single default type applies only when the key is
 * absent, while an explicitly stored empty list stays empty (no post type
 * is Bricks-enabled).
 *
 * @return list<string>
 */
function bricks_enabled_post_types(): array
{
    /** @var mixed $raw */
    $raw = method_exists(\Bricks\Database::class, 'get_setting')
        ? \Bricks\Database::get_setting('postTypes', ['page'])
        : \Bricks\Database::$global_settings['postTypes'] ?? ['page'];

    return is_array($raw) ? array_values(array_filter($raw, callback: 'is_string')) : [];
}

/**
 * Clears the CSS Bricks remembers having generated during this request.
 * Bricks de-duplicates generated CSS per request (a front-end concern), so a
 * second write to the same post in one process — a batch runner, a smoke —
 * would skip every unchanged element and write an incomplete or empty file.
 * Writes never happen in the middle of a front-end render.
 */
function bricks_reset_css_generation_state(): void
{
    if (property_exists(\Bricks\Assets::class, 'unique_inline_css')) {
        \Bricks\Assets::$unique_inline_css = [];
    }
    // The classes seen while generating accumulate on the Assets singleton.
    // Left in place, a second read in the same process compiles the FIRST
    // page's classes into the second page's CSS — so a verification read says
    // a class landed on a page that never carried it. Worse in a checking
    // tool than in a writer: the agent believes the opposite of the truth.
    if (property_exists(\Bricks\Assets::class, 'global_classes_elements')) {
        \Bricks\Assets::$global_classes_elements = [];
    }
}

/**
 * Status of a global CSS file Bricks maintains through its option listeners
 * (theme styles, global variables, color palettes, global custom CSS) after
 * a store write: "regenerated" when the file is present and fresh,
 * "inline" outside file mode, "skipped(…)" with the reason otherwise. Call
 * {@see bricks_ensure_css_dir()} BEFORE the option write so the listener can
 * write the file; pass the time the write started so a pre-existing stale
 * file is not mistaken for a fresh one.
 */
function bricks_global_css_file_status(string $file_name, int $since): string
{
    if (!bricks_css_loading_is_file()) {
        return 'inline';
    }

    $path = bricks_css_dir() . '/' . $file_name;
    if (!file_exists($path)) {
        return sprintf(
            'skipped(%s was not written — regenerate the CSS files from Bricks > Settings > Performance)',
            $file_name,
        );
    }
    clearstatcache(clear_realpath_cache: true, filename: $path);
    $mtime = filemtime($path);
    if ($mtime !== false && $mtime < ($since - 1)) {
        return sprintf(
            'skipped(%s is stale — regenerate the CSS files from Bricks > Settings > Performance)',
            $file_name,
        );
    }

    return 'regenerated';
}

/**
 * Status after a global store lost its last entry: Bricks' generator, called
 * with the empty store, removes the file, so "regenerated" means the file is
 * gone; "inline" outside file mode; "skipped(…)" when the stale file is still
 * there.
 */
function bricks_global_css_file_removed_status(string $file_name): string
{
    if (!bricks_css_loading_is_file()) {
        return 'inline';
    }
    $path = bricks_css_dir() . '/' . $file_name;
    clearstatcache(clear_realpath_cache: true, filename: $path);
    if (!file_exists($path)) {
        return 'regenerated';
    }

    return sprintf(
        'skipped(%s still holds the deleted values — regenerate the CSS files from Bricks > Settings > Performance)',
        $file_name,
    );
}

/**
 * File name of a theme style's CSS file, taken from Bricks' own public
 * generator so the file we regenerate or remove is the one Bricks serves.
 * Returns null when the installed Bricks build exposes no generator: a name
 * guessed on our side could differ from Bricks' and leave an orphan file, so
 * callers skip the file step and report `css_file: null` with a warning.
 *
 * @param array<string, mixed> $entry the stored theme-style entry
 */
function bricks_theme_style_css_file_name(string $style_id, array $entry = []): ?string
{
    if (
        class_exists(\Bricks\Assets_Theme_Styles::class)
        && method_exists(\Bricks\Assets_Theme_Styles::class, 'generate_file_name')
    ) {
        // Bricks hands the stored entry to the generator as well, so a
        // `bricks/theme_style_name` filter that reads it names the same file.
        /** @var mixed $name */
        $name = \Bricks\Assets_Theme_Styles::generate_file_name($style_id, $entry);

        return is_string($name) && $name !== '' ? $name : null;
    }

    return null;
}

/**
 * Warning attached to a theme-style write whose CSS file could not be
 * addressed because Bricks exposes no file-name generator.
 */
function bricks_theme_style_file_unavailable_warning(): string
{
    return 'theme-style file name unavailable on this Bricks build; regenerate from the builder';
}

/**
 * Whether Google Fonts are disabled site-wide. Bricks tests the mere
 * presence of the `disableGoogleFonts` key, so a stored `false` disables
 * them too — this reads the same predicate Bricks does.
 */
function bricks_google_fonts_disabled(): bool
{
    // Same predicate as Bricks' own helper, but read from the option rather
    // than the settings snapshot Bricks takes at boot, so a write made
    // earlier in this request is reflected.
    if (apply_filters('bricks/assets/load_webfonts', value: true) === false) {
        return true;
    }

    /** @var mixed $settings */
    $settings = get_option(BRICKS_DB_GLOBAL_SETTINGS, []);

    return is_array($settings) && array_key_exists('disableGoogleFonts', $settings);
}

/**
 * Counts the Bricks-rendered posts (editor mode "bricks", any non-trashed
 * status) whose per-post CSS file is missing. Bounded to `$limit` posts so a
 * large site does not turn check-setup into a crawl.
 *
 * @return array{checked: int, missing: int, missing_ids: list<int>, truncated: bool}
 */
function bricks_count_missing_post_css_files(int $limit = 500): array
{
    // Only the post types Bricks renders: the enabled post types (Bricks'
    // own default when the setting is unset) plus its templates. Named, not
    // 'any': 'any' skips post types excluded from search, which is how Bricks
    // templates are registered.
    $post_types = array_values(array_unique(array_merge(bricks_enabled_post_types(), [BRICKS_DB_TEMPLATE_SLUG])));

    /** @var list<int> $ids */
    $ids = get_posts([
        'post_type' => $post_types,
        'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
        'posts_per_page' => $limit + 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_key' => BRICKS_DB_EDITOR_MODE,
        'meta_value' => 'bricks',
        'orderby' => 'ID',
        'order' => 'DESC',
        'suppress_filters' => true,
    ]);

    $truncated = count($ids) > $limit;
    $ids = array_slice($ids, offset: 0, length: $limit);
    $dir = bricks_css_dir();
    $missing = [];
    foreach ($ids as $id) {
        if (file_exists(sprintf('%s/post-%d.min.css', $dir, $id))) {
            continue;
        }
        $missing[] = $id;
    }

    return [
        'checked' => count($ids),
        'missing' => count($missing),
        'missing_ids' => array_slice($missing, offset: 0, length: 20),
        'truncated' => $truncated,
    ];
}
