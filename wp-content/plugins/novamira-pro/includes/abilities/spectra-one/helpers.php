<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Spectra One integration.
 *
 * Sprint 0 seeds this file with the cross-cutting utilities every later
 * ability needs — error factories, the palette / typography / page-override
 * allowlists, and the lock helper that guards concurrent writes to the
 * single `wp_global_styles` post the theme's write paths mutate. Individual
 * ability files stay thin by delegating validation, error shaping, and
 * cache invalidation here.
 */

/**
 * Error returned when Spectra One is not active at ≥ the minimum supported
 * version. Every non-check-setup ability starts with this guard so the
 * discovery flow (`novamira/spectra-one-check-setup`) is the only surface
 * that reports "install / activate the theme" and the rest report the
 * same shape with a stable code.
 */
function so_not_active(): WP_Error
{
    return new WP_Error(
        'spectra_one_not_active',
        __(
            'Spectra One theme is not active at or above the minimum supported version. Call novamira/spectra-one-check-setup for details.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * Error returned when a Spectra-plugin-only ability is invoked on an
 * install where the Spectra (UAGB) plugin is missing or under-min. Only
 * `novamira/spectra-one-list-spectra-blocks` uses this so the code stays
 * stable across the rest of the surface.
 */
function so_requires_plugin(): WP_Error
{
    return new WP_Error(
        'spectra_one_requires_plugin',
        __(
            'This ability requires the Spectra (UAGB) companion plugin. It is optional — most spectra-one abilities work with the theme alone.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * Uniform "input failed validation" error. `$detail` is the per-callsite
 * reason (missing field, unknown key, out-of-range value). Keep the code
 * stable — tests + auditors assert on it.
 */
function so_invalid_input(string $detail): WP_Error
{
    return new WP_Error('spectra_one_invalid_input', $detail, ['status' => 400]);
}

/**
 * Uniform "unknown / unsupported settings key" error. Emitted by every
 * write path that rejects a payload key outside its allowlist.
 */
function so_unknown_setting(string $key): WP_Error
{
    return new WP_Error(
        'spectra_one_unknown_setting',
        sprintf(
            /* translators: %s: rejected settings key */
            __('Unknown or unsupported Spectra One settings key: %s', domain: 'novamira-pro'),
            $key,
        ),
        ['status' => 400],
    );
}

/**
 * Canonical list of the 14 palette color slugs the theme declares in
 * `theme.json`. Order matches `Swt\get_palette_slugs()` so allowlist checks
 * short-circuit cheaply. Duplicated here (not proxied to the theme helper)
 * so audits pin the allowed surface at rest — a future theme release that
 * silently extends the palette must be re-audited before the new slugs
 * become writeable.
 *
 * @return list<string>
 */
function so_palette_slugs(): array
{
    return [
        'primary',
        'secondary',
        'heading',
        'body',
        'background',
        'tertiary',
        'quaternary',
        'surface',
        'foreground',
        'outline',
        'neutral',
        'transparent',
        'currentColor',
        'inherit',
    ];
}

/**
 * Whether a color slug is part of the theme's writable palette. Used by
 * every write path that accepts palette-scoped input.
 */
function so_is_palette_slug(string $slug): bool
{
    return in_array($slug, so_palette_slugs(), strict: true);
}

/**
 * Count files matching a glob pattern under the theme directory, returning
 * 0 when the directory is missing or glob() fails (some hosts disable it).
 * Used by `get-theme-info` for asset counts. Kept explicit here so audits
 * can pin the "counts never leak error state" contract.
 */
function so_count_glob(string $pattern): int
{
    $files = glob($pattern);
    return is_array($files) ? count($files) : 0;
}

/**
 * Enumerate absolute paths to files matching a glob pattern under the
 * theme directory. Returns an empty list when the directory is missing or
 * glob() fails. Sorted alphabetically so list-* outputs are deterministic
 * across environments regardless of underlying filesystem ordering.
 *
 * @return list<string>
 */
function so_glob_files(string $pattern): array
{
    $files = glob($pattern);
    if (!is_array($files)) {
        return [];
    }
    sort($files);
    return $files;
}

/**
 * Reads a theme JSON file (style variation, pattern header block, …) and
 * decodes it as an associative array. Malformed / missing content yields
 * an empty array so callers never have to handle null. Kept explicit here
 * so audits can pin the "silent failure returns empty" contract shared
 * across every JSON-reading Spectra One ability.
 *
 * @return array<string, mixed>
 */
function so_read_json_object(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($raw, associative: true);
    if (!is_array($decoded)) {
        return [];
    }
    /** @var array<string, mixed> $shaped */
    $shaped = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $shaped[$key] = $value;
    }
    return $shaped;
}

/**
 * Companion Spectra (UAGB) plugin status normalized to the three canonical
 * strings check-setup / get-theme-info emit. Prefers the theme's own
 * `Swt\is_spectra_plugin_status()` when the theme is loaded (it inspects
 * the plugin file on disk too, distinguishing `installed` from
 * `not-installed`); falls back to a version-only heuristic otherwise so
 * the wrapper still resolves on installs where the theme helper is absent.
 *
 * @return 'activated'|'installed'|'not-installed'
 */
function so_resolve_plugin_status(string $plugin_version): string
{
    if (function_exists('Swt\\is_spectra_plugin_status')) {
        $status = \Swt\is_spectra_plugin_status();
        if ($status === 'activated' || $status === 'installed' || $status === 'not-installed') {
            return $status;
        }
    }
    return $plugin_version !== '' ? 'activated' : 'not-installed';
}

/**
 * Reads a header field off a WP_Theme snapshot and normalises the result
 * to string. `WP_Theme::get()` returns `false` for unknown headers; that
 * would surface as `false` in the output otherwise. Kept here so the
 * `additionalProperties: false` output contract holds regardless of what
 * the underlying theme header layer returns.
 */
function so_theme_header_string(\WP_Theme $theme, string $header): string
{
    /** @var mixed $value */
    $value = $theme->get($header);
    return is_string($value) ? $value : '';
}

/**
 * Safely walk a nested `array<array-key, mixed>` structure and return the
 * value at `$path`, or an empty array if any step is missing / not an
 * array. Central utility for every ability that reads theme.json /
 * `wp_global_styles` — keeps the "silent fallback to empty" contract in
 * one place so audits can pin it. Accepts `array<array-key, mixed>`
 * because `json_decode(..., associative: true)` returns that widened type
 * (theme.json sub-nodes are frequently sequential arrays of objects).
 *
 * @param array<array-key, mixed> $data
 * @param list<string> $path
 * @return array<array-key, mixed>
 */
function so_traverse_array(array $data, array $path): array
{
    /** @var mixed $node */
    $node = $data;
    foreach ($path as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return [];
        }
        /** @var mixed $node */
        $node = $node[$key];
    }
    return is_array($node) ? $node : [];
}

/**
 * Walk the same nested path but coerce the leaf to string. Non-string /
 * missing leaves resolve to `$fallback` (default empty string). Keeps the
 * output contract of every `get-*` ability trivially safe.
 *
 * @param array<array-key, mixed> $data
 * @param list<string> $path
 */
function so_string_at(array $data, array $path, string $fallback = ''): string
{
    /** @var mixed $node */
    $node = $data;
    foreach ($path as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $fallback;
        }
        /** @var mixed $node */
        $node = $node[$key];
    }
    return is_string($node) && $node !== '' ? $node : $fallback;
}

/**
 * Clamp an int-castable value into `[$min, $max]`. Non-numeric input
 * resolves to `$min` so pagination inputs never blow past the ceiling
 * or fall below the floor.
 *
 * @param mixed $value
 */
function so_clamp_int(mixed $value, int $min, int $max): int
{
    if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
        return $min;
    }
    $n = (int) $value;
    if ($n < $min) {
        return $min;
    }
    if ($n > $max) {
        return $max;
    }
    return $n;
}

/**
 * Normalise a raw palette-entries list into `[{slug,name,color}]`. Silently
 * drops entries missing a slug or a color so the write allowlist never
 * ends up with dangling rows.
 *
 * @param array<array-key, mixed> $raw
 * @return list<array{slug: string, name: string, color: string}>
 */
function so_normalise_color_entries(array $raw): array
{
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $slug = so_string_at($entry, ['slug']);
        $color = so_string_at($entry, ['color']);
        if ($slug === '' || $color === '') {
            continue;
        }
        $out[] = [
            'slug' => $slug,
            'name' => so_string_at($entry, ['name'], fallback: $slug),
            'color' => $color,
        ];
    }
    return $out;
}

/**
 * Normalise a raw gradients list into `[{slug,name,gradient}]`.
 *
 * @param array<array-key, mixed> $raw
 * @return list<array{slug: string, name: string, gradient: string}>
 */
function so_normalise_gradients(array $raw): array
{
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $slug = so_string_at($entry, ['slug']);
        $gradient = so_string_at($entry, ['gradient']);
        if ($slug === '' || $gradient === '') {
            continue;
        }
        $out[] = [
            'slug' => $slug,
            'name' => so_string_at($entry, ['name'], fallback: $slug),
            'gradient' => $gradient,
        ];
    }
    return $out;
}

/**
 * Normalise the theme.json duotone list into `[{slug,name,colors[]}]`.
 * Each duotone entry declares 2+ colors under `colors[]`.
 *
 * @param array<array-key, mixed> $raw
 * @return list<array{slug: string, name: string, colors: list<string>}>
 */
function so_normalise_duotones(array $raw): array
{
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $slug = so_string_at($entry, ['slug']);
        if ($slug === '') {
            continue;
        }
        /** @var mixed $colors_raw */
        $colors_raw = $entry['colors'] ?? [];
        $colors = [];
        if (is_array($colors_raw)) {
            /** @var mixed $c */
            foreach ($colors_raw as $c) {
                if (!is_string($c) || $c === '') {
                    continue;
                }
                $colors[] = $c;
            }
        }
        $out[] = [
            'slug' => $slug,
            'name' => so_string_at($entry, ['name'], fallback: $slug),
            'colors' => $colors,
        ];
    }
    return $out;
}

/**
 * Normalise the theme.json fontFamilies list into
 * `[{name,slug,font_family}]`.
 *
 * @param array<array-key, mixed> $raw
 * @return list<array{name: string, slug: string, font_family: string}>
 */
function so_normalise_font_families(array $raw): array
{
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $slug = so_string_at($entry, ['slug']);
        $font_family = so_string_at($entry, ['fontFamily']);
        if ($slug === '' && $font_family === '') {
            continue;
        }
        $out[] = [
            'slug' => $slug,
            'name' => so_string_at($entry, ['name'], fallback: $slug),
            'font_family' => $font_family,
        ];
    }
    return $out;
}

/**
 * Normalise a theme.json preset list where each entry has `name`, `slug`,
 * and a variable value key (`size` for spacing / font-sizes / line-heights,
 * `weight` for font-weights). Any entry missing a slug is dropped.
 *
 * @param array<array-key, mixed> $raw
 * @return list<array<string, string>>
 */
function so_normalise_preset_triples(array $raw, string $value_key): array
{
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $slug = so_string_at($entry, ['slug']);
        if ($slug === '') {
            continue;
        }
        $row = [
            'slug' => $slug,
            'name' => so_string_at($entry, ['name'], fallback: $slug),
            $value_key => so_string_at($entry, [$value_key]),
        ];
        $out[] = $row;
    }
    return $out;
}

/**
 * Normalise the theme.json styles.blocks map into
 * `[{block, name, label}]`. WordPress stores block styles under
 * `styles.blocks.<block-name>.variations.<style-name>` in a shape that
 * varies by theme; Spectra One 1.2.x declares them under a flatter
 * `styles.blocks.<block>` extension bag. We surface whatever we find so
 * agents can inspect the block-scoped customisations.
 *
 * @param array<array-key, mixed> $raw
 * @return list<array{block: string, name: string, label: string}>
 */
function so_normalise_block_styles(array $raw): array
{
    $out = [];
    /** @var mixed $node */
    foreach ($raw as $block => $node) {
        if (!is_string($block) || !is_array($node)) {
            continue;
        }
        /** @var mixed $variations */
        $variations = $node['variations'] ?? [];
        if (is_array($variations) && $variations !== []) {
            /** @var mixed $variation */
            foreach ($variations as $name => $variation) {
                if (!is_string($name) || !is_array($variation)) {
                    continue;
                }
                $out[] = [
                    'block' => $block,
                    'name' => $name,
                    'label' => so_string_at($variation, ['title'], fallback: $name),
                ];
            }
            continue;
        }
        // Blocks with a flat customisation (no `variations`) still count as
        // a single row so the agent can see the surface exists.
        $out[] = [
            'block' => $block,
            'name' => 'default',
            'label' => $block,
        ];
    }
    return $out;
}

/**
 * Normalise the theme.json customTemplates list into
 * `[{name, title, post_types[]}]`.
 *
 * @param array<array-key, mixed> $raw
 * @return list<array{name: string, title: string, post_types: list<string>}>
 */
function so_normalise_custom_templates(array $raw): array
{
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $name = so_string_at($entry, ['name']);
        if ($name === '') {
            continue;
        }
        /** @var mixed $post_types_raw */
        $post_types_raw = $entry['postTypes'] ?? [];
        $post_types = [];
        if (is_array($post_types_raw)) {
            /** @var mixed $pt */
            foreach ($post_types_raw as $pt) {
                if (!is_string($pt) || $pt === '') {
                    continue;
                }
                $post_types[] = $pt;
            }
        }
        $out[] = [
            'name' => $name,
            'title' => so_string_at($entry, ['title'], fallback: $name),
            'post_types' => $post_types,
        ];
    }
    return $out;
}

/**
 * Parses the WP block-pattern header at the top of a `patterns/*.php`
 * file. WordPress declares the format under
 * https://developer.wordpress.org/themes/features/block-patterns/ — a
 * DocBlock with `Title:`, `Slug:`, `Categories:` (comma-separated) and
 * optional `Keywords:` fields.
 *
 * Returns null when the file has no parseable header. Reads only the
 * first 4 KiB of the file — pattern headers are always at the top and
 * files can be 100+ KiB otherwise.
 *
 * @return array{slug: string, title: string, categories: list<string>, keywords: list<string>}|null
 */
function so_read_pattern_header(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }
    $handle = fopen($path, mode: 'rb');
    if ($handle === false) {
        return null;
    }
    $chunk = fread($handle, length: 4096);
    fclose($handle);
    if (!is_string($chunk) || $chunk === '') {
        return null;
    }

    $fields = [
        'Title' => '',
        'Slug' => '',
        'Categories' => '',
        'Keywords' => '',
    ];
    foreach (array_keys($fields) as $field) {
        $matches = [];
        // /u flag: WordPress block-pattern headers frequently declare
        // Categories / Keywords in non-ASCII characters (CJK, Cyrillic,
        // Arabic, emoji). Without /u the greedy `(.+)` stops at the first
        // multi-byte continuation byte and Categories / Keywords silently
        // truncate — audit round 6, Sprint 6 round 2 Unicode probe.
        $found = preg_match(
            '/^[ \t\/*#@]*' . preg_quote($field, delimiter: '/') . ':\s*(.+)$/miu',
            $chunk,
            matches: $matches,
        );
        if ($found !== 1) {
            continue;
        }
        $fields[$field] = trim($matches[1]);
    }
    $slug = $fields['Slug'];
    $title = $fields['Title'];
    if ($slug === '' && $title === '') {
        return null;
    }

    $categories = so_split_csv($fields['Categories']);
    $keywords = so_split_csv($fields['Keywords']);
    if ($slug === '') {
        $slug = basename($path, suffix: '.php');
    }
    if ($title === '') {
        $title = ucfirst(str_replace(search: '-', replace: ' ', subject: basename($path, suffix: '.php')));
    }

    return [
        'slug' => $slug,
        'title' => $title,
        'categories' => $categories,
        'keywords' => $keywords,
    ];
}

/**
 * Split a comma-separated header field into a trimmed list of strings.
 * Empty / whitespace-only entries are dropped.
 *
 * @return list<string>
 */
function so_split_csv(string $csv): array
{
    if ($csv === '') {
        return [];
    }
    $parts = explode(separator: ',', string: $csv);
    $out = [];
    foreach ($parts as $part) {
        $trimmed = trim($part);
        if ($trimmed === '') {
            continue;
        }
        $out[] = $trimmed;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Sprint 3 — write path: locking + wp_global_styles read/write/flush.
// ---------------------------------------------------------------------------

/**
 * Whether a hex-color string is one of the two accepted 6-digit or 8-digit
 * (with alpha) formats. The 8-digit form covers the FF-tail alpha channel
 * (`#RRGGBBAA`) that the block editor now emits on some browsers.
 */
function so_is_hex_color(string $value): bool
{
    return preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $value) === 1;
}

/**
 * Acquire an exclusive lock keyed by `$slot`. The slot is prefixed with the
 * blog id so multisite sub-sites do not collide on the server-scoped MySQL
 * GET_LOCK namespace. Uses `wp_cache_add` when a persistent object cache is
 * present (the atomic path); falls back to MySQL GET_LOCK on stock WP; and
 * accepts an unlocked run when the backend does not implement GET_LOCK the
 * MySQL way (SQLite dev / CI drop-ins).
 *
 * Returns an opaque token string on success — pass it verbatim to
 * `so_release_lock` so a late release on a different writer's lock is a
 * safe no-op (token mismatch). Returns WP_Error only on genuine
 * contention (another writer holds the lock right now).
 */
function so_acquire_lock(string $slot): string|WP_Error
{
    $blog_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
    $key = 'so_lock_' . $blog_id . '_' . $slot;
    $token = bin2hex(random_bytes(length: 8));
    $group = 'novamira_spectra_one_lock';

    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            if (wp_cache_add($key, $token, $group, expire: 5)) {
                return 'cache:' . $key . '|' . $token;
            }
            usleep(microseconds: 50_000);
        }
        return so_invalid_input(__(
            'Could not acquire lock; another write on the same resource is in progress. Retry the call.',
            domain: 'novamira-pro',
        ));
    }

    global $wpdb;
    /** @var \wpdb $wpdb */
    $sql = (string) $wpdb->prepare('SELECT GET_LOCK(%s, %d) AS got', $key, 5);
    /** @var mixed $got */
    $got = $wpdb->get_var($sql);
    if ((string) $got === '1') {
        return 'sql:' . $key;
    }
    if ((string) $got === '0') {
        return so_invalid_input(__(
            'Could not acquire lock; another write on the same resource is in progress. Retry the call.',
            domain: 'novamira-pro',
        ));
    }
    // NULL / any other value: backend does not implement MySQL GET_LOCK
    // semantics (SQLite drop-in, some dev backends). Proceed unlocked —
    // matches the etch / kadence option-lock fallback and keeps the write
    // path usable outside production MySQL.
    return 'none:' . $key;
}

/**
 * Release a lock previously acquired via `so_acquire_lock`. Safe against
 * double-release and stale-token release — a token mismatch or
 * already-expired lock is a silent no-op.
 */
function so_release_lock(string $token): void
{
    if (str_starts_with($token, 'cache:')) {
        $payload = substr(string: $token, offset: strlen('cache:'));
        $sep = strpos(haystack: $payload, needle: '|');
        if ($sep === false) {
            return;
        }
        $key = substr(string: $payload, offset: 0, length: $sep);
        $cache_token = substr(string: $payload, offset: $sep + 1);
        $group = 'novamira_spectra_one_lock';
        /** @var mixed $current */
        $current = wp_cache_get($key, $group);
        if (is_string($current) && hash_equals($current, $cache_token)) {
            wp_cache_delete($key, $group);
        }
        return;
    }
    if (str_starts_with($token, 'sql:')) {
        $key = substr(string: $token, offset: strlen('sql:'));
        global $wpdb;
        /** @var \wpdb $wpdb */
        $wpdb->query((string) $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
    }

    // 'none:' tokens do not hold anything to release.
}

/**
 * Run `$fn` under the `$slot` lock, releasing on success OR failure.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|WP_Error
 */
function so_with_lock(string $slot, callable $fn): mixed
{
    $token = so_acquire_lock($slot);
    if ($token instanceof WP_Error) {
        return $token;
    }
    try {
        return $fn();
    } finally {
        so_release_lock($token);
    }
}

/**
 * Serialise every write that touches the Spectra One "design surface" —
 * wp_global_styles user overlay (settings + styles) AND the
 * swt_theme_options bag — under a single top-level lock pair. Both stores
 * feed the same block-editor cascade at render time (see the "Cascade"
 * gotcha in the SKILL); coordinating the two write slots removes the
 * ordering race between a set-typography call (swt_theme_options) and a
 * concurrent apply-style-variation / set-color-palette / set-theme-settings
 * call (wp_global_styles). Acquisition order matches apply-design so no
 * two writers can deadlock on the pair.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|WP_Error
 */
function so_with_design_lock(callable $fn): mixed
{
    return so_with_lock('global_styles', static fn(): mixed => so_with_lock('swt_theme_options', $fn));
}

/**
 * Trusted top-level keys inside the `wp_global_styles` post payload.
 * apply-design filters snapshot data through this list so a hand-edited
 * snapshot cannot smuggle in keys the per-slot set-* writers would never
 * write (e.g. arbitrary block-level namespaces added at the root).
 *
 * @return list<string>
 */
function so_apply_design_global_styles_allowlist(): array
{
    return ['settings', 'styles', 'version', 'isGlobalStylesUserThemeJSON'];
}

/**
 * Trusted keys inside the `swt_theme_options` bag. Union of every key
 * the per-slot set-* writers touch: set-theme-settings (`scroll_top`) +
 * set-typography (body / headings font + line-height + weight + size).
 * apply-design filters snapshot data through this list so a snapshot
 * cannot land keys outside the surface Novamira actually maintains.
 *
 * @return list<string>
 */
function so_apply_design_theme_options_allowlist(): array
{
    return [
        'scroll_top',
        'body-font-family',
        'body-font-weight',
        'body-line-height',
        'font-size-body',
        'headings-font-family',
        'headings-font-weight',
        'headings-line-height',
    ];
}

/**
 * Split the snapshot `global_styles` block into a kept map (trusted
 * top-level keys) + a rejected list (keys dropped because they fall
 * outside the trusted shape). Values are preserved as-is — the
 * per-writer allowlists still guard downstream sub-shapes.
 *
 * @param array<string, mixed> $global_styles
 * @return array{kept: array<string, mixed>, rejected: list<string>}
 */
function so_filter_snapshot_global_styles(array $global_styles): array
{
    return so_filter_snapshot_keys($global_styles, so_apply_design_global_styles_allowlist());
}

/**
 * Split the snapshot `theme_options` block into a kept map (allowlisted
 * keys) + a rejected list (keys dropped because the per-slot set-*
 * writers would never emit them).
 *
 * @param array<string, mixed> $theme_options
 * @return array{kept: array<string, mixed>, rejected: list<string>}
 */
function so_filter_snapshot_theme_options(array $theme_options): array
{
    return so_filter_snapshot_keys($theme_options, so_apply_design_theme_options_allowlist());
}

/**
 * @param array<string, mixed> $bag
 * @param list<string> $allowlist
 * @return array{kept: array<string, mixed>, rejected: list<string>}
 */
function so_filter_snapshot_keys(array $bag, array $allowlist): array
{
    /** @var array<string, mixed> $kept */
    $kept = [];
    /** @var list<string> $rejected */
    $rejected = [];
    /** @var mixed $value */
    foreach ($bag as $key => $value) {
        if (in_array($key, $allowlist, strict: true)) {
            $kept[$key] = $value;
            continue;
        }
        $rejected[] = $key;
    }
    sort($rejected);
    return ['kept' => $kept, 'rejected' => $rejected];
}

/**
 * Load the active `wp_global_styles` post + decoded payload. Returns null
 * when no user global-styles post exists (e.g. block theme not fully
 * bootstrapped). The write abilities early-return with
 * `spectra_one_no_global_styles` in that case so the failure code stays
 * stable across the surface.
 *
 * @return array{ID: int, styles: array<string, mixed>}|null
 */
function so_load_global_styles(): ?array
{
    if (!function_exists('Swt\\get_theme_custom_styles')) {
        return null;
    }
    $db_styles = \Swt\get_theme_custom_styles();
    /** @var mixed $raw_id */
    $raw_id = $db_styles['ID'] ?? 0;
    $post_id = is_numeric($raw_id) ? (int) $raw_id : 0;
    if ($post_id === 0) {
        return null;
    }
    /** @var mixed $raw_styles */
    $raw_styles = $db_styles['post_content'] ?? [];
    if (!is_array($raw_styles)) {
        $raw_styles = [];
    }
    /** @var array<string, mixed> $shaped */
    $shaped = [];
    /** @var mixed $value */
    foreach ($raw_styles as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $shaped[$key] = $value;
    }
    return ['ID' => $post_id, 'styles' => $shaped];
}

/**
 * Error factory: no user `wp_global_styles` post exists. Distinct from
 * `spectra_one_not_active` — the theme is active, but WordPress has not
 * created the user overlay row yet (a fresh install where the user has
 * never saved styles). Every write path treats this as fatal because it
 * has no target row to update.
 */
function so_no_global_styles(): WP_Error
{
    return new WP_Error(
        'spectra_one_no_global_styles',
        __(
            'No wp_global_styles user post exists yet. Save Global Styles once in the Site Editor to create it, then retry.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * Persist `$styles` back to the `wp_global_styles` post identified by
 * `$post_id`, stamping the two markers WordPress requires to honour a
 * user overlay (`isGlobalStylesUserThemeJSON` and `version: 2`). Without
 * them WP_Theme_JSON_Resolver::get_user_data() drops the post content
 * silently — the row saves fine but nothing renders.
 *
 * Returns the post id on success or a WP_Error on JSON encode / db failure.
 *
 * @param array<string, mixed> $styles
 */
function so_save_global_styles(int $post_id, array $styles): int|WP_Error
{
    $styles['version'] = 2;
    $styles['isGlobalStylesUserThemeJSON'] = true;
    $json = wp_json_encode($styles);
    if ($json === false) {
        return new WP_Error(
            'spectra_one_json_encode_failed',
            __('Failed to encode global styles as JSON.', domain: 'novamira-pro'),
            ['status' => 500],
        );
    }
    $result = wp_update_post([
        'ID' => $post_id,
        'post_content' => $json,
    ], wp_error: true);
    if ($result instanceof WP_Error) {
        return $result;
    }
    so_flush_global_styles_cache($post_id);
    return $post_id;
}

/**
 * Extract the list of preset slugs declared at `$path` inside a decoded
 * theme.json. Returned in declaration order — the Sprint 3 write paths
 * use it as the closed-set allowlist that agent-supplied values must
 * belong to.
 *
 * @param array<array-key, mixed> $theme_json
 * @param list<string> $path
 * @return list<string>
 */
function so_preset_slugs(array $theme_json, array $path): array
{
    $raw = so_traverse_array($theme_json, $path);
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $slug = so_string_at($entry, ['slug']);
        if ($slug === '') {
            continue;
        }
        $out[] = $slug;
    }
    return $out;
}

/**
 * Merge an agent-supplied font-size patch onto the theme's stored
 * responsive font-size array. Only the four accepted keys (desktop,
 * tablet, mobile, desktop-unit) are honoured; the incoming payload uses
 * the underscore variant (`desktop_unit`) that the ability contract
 * exposes, mapped back to the dash-key the theme's own option storage
 * expects.
 *
 * @param array<array-key, mixed> $current
 * @param array<array-key, mixed> $incoming
 * @return array<string, mixed>
 */
function so_merge_font_size(array $current, array $incoming): array
{
    /** @var array<string, mixed> $out */
    $out = [];
    /** @var mixed $value */
    foreach ($current as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    $map = [
        'desktop' => 'desktop',
        'tablet' => 'tablet',
        'mobile' => 'mobile',
        'desktop_unit' => 'desktop-unit',
    ];
    foreach ($map as $input_key => $storage_key) {
        if (!array_key_exists($input_key, $incoming)) {
            continue;
        }
        $out[$storage_key] = so_string_at($incoming, [$input_key]);
    }
    return $out;
}

/**
 * Walk `$path` and coerce the leaf to bool. Non-bool leaves fall back to
 * false. Kept lean for the `add-font-family` result unpacking where the
 * theme-native surface may return `1` / `'1'` for booleans.
 *
 * @param array<array-key, mixed> $data
 * @param list<string> $path
 */
function so_bool_at(array $data, array $path): bool
{
    /** @var mixed $node */
    $node = $data;
    foreach ($path as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return false;
        }
        /** @var mixed $node */
        $node = $node[$key];
    }
    if (is_bool($node)) {
        return $node;
    }
    if (is_string($node) || is_int($node)) {
        return filter_var($node, FILTER_VALIDATE_BOOLEAN);
    }
    return false;
}

/**
 * Apply a partial per-scope typography patch (body or headings) to the
 * `swt_theme_options` bag. Kept as a helper so
 * `spectra_one_set_typography` stays under the Mago cyclomatic /
 * Halstead complexity ceilings.
 *
 * `$mapping` maps the input short key (`font_family`, `font_weight`,
 * `line_height`) to a tuple of [storage_key, allowlist]. An unrecognised
 * value on a non-empty allowlist yields `spectra_one_unknown_setting`.
 * Returns the updated options bag + the list of storage keys that were
 * actually mutated.
 *
 * @param array<array-key, mixed> $options
 * @param array<array-key, mixed> $scope
 * @param array<string, array{0: string, 1: list<string>}> $mapping
 * @return array{options: array<array-key, mixed>, updated: list<string>}|WP_Error
 */
function so_apply_typography_scope(array $options, array $scope, array $mapping): array|WP_Error
{
    $updated = [];
    foreach ($mapping as $short => [$option_key, $allowlist]) {
        if (!array_key_exists($short, $scope)) {
            continue;
        }
        $value = so_string_at($scope, [$short]);
        if ($value === '') {
            // Empty string is the "clear back to theme default" write path
            // — never validated against the allowlist so callers can wipe a
            // slot regardless of whether the theme.json presets loaded.
            if (($options[$option_key] ?? '') !== $value) {
                $options[$option_key] = $value;
                $updated[] = $option_key;
            }
            continue;
        }
        if ($allowlist === []) {
            // Deny-all when the theme.json preset allowlist is empty (fresh
            // theme, presets not yet ingested). Falling through here on
            // pre-Sprint-3 code silently accepted arbitrary strings and
            // corrupted swt_theme_options — audit round 1, Security lens.
            return new WP_Error(
                'spectra_one_presets_unavailable',
                __(
                    'Theme.json preset allowlist is empty on this install — reload the block editor once so the theme ingests its presets, then retry.',
                    domain: 'novamira-pro',
                ),
                ['status' => 400],
            );
        }
        if (!in_array($value, $allowlist, strict: true)) {
            return so_unknown_setting("{$option_key}={$value}");
        }
        if (($options[$option_key] ?? '') !== $value) {
            $options[$option_key] = $value;
            $updated[] = $option_key;
        }
    }
    return ['options' => $options, 'updated' => $updated];
}

/**
 * Filter an untyped mixed array so only string-keyed entries survive.
 * Used by the design snapshot import path: the wp_options row + the
 * wp_global_styles post_content are both `array<array-key, mixed>` at
 * source, but the Novamira output contract declares
 * `additionalProperties: true` on an object — which requires string
 * keys to serialise cleanly to JSON.
 *
 * @param array<array-key, mixed> $raw
 * @return array<string, mixed>
 */
function so_shape_string_keyed(array $raw): array
{
    /** @var array<string, mixed> $out */
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Reject writes / reads that target a post in a status the display-
 * override abilities do not accept — revisions, auto-drafts, and trashed
 * posts. Every one of those statuses lets `get_post()` succeed but
 * makes the write semantically wrong: revision meta gets pruned with
 * the parent, auto-draft meta is discarded when the row is trashed,
 * trash meta becomes orphaned when the row is force-deleted. Called by
 * every ability that treats `post_id` as a discrete editable subject
 * — audit round 6, Sprint 6 Correctness probe.
 */
function so_post_display_target_error(\WP_Post $post): ?WP_Error
{
    return match ($post->post_status) {
        'trash' => new WP_Error(
            'spectra_one_post_is_trash',
            sprintf(
                /* translators: %d: post ID */
                __(
                    'Post %d is in trash — restore it before reading or writing its display overrides.',
                    domain: 'novamira-pro',
                ),
                $post->ID,
            ),
            ['status' => 400],
        ),
        'auto-draft' => new WP_Error(
            'spectra_one_post_is_auto_draft',
            sprintf(
                /* translators: %d: post ID */
                __(
                    'Post %d is an auto-draft — save it once before touching display overrides.',
                    domain: 'novamira-pro',
                ),
                $post->ID,
            ),
            ['status' => 400],
        ),
        'inherit' => new WP_Error(
            'spectra_one_post_is_revision',
            sprintf(
                /* translators: %d: post ID */
                __(
                    'Post %d is a revision (post_status = inherit) — target its parent post instead.',
                    domain: 'novamira-pro',
                ),
                $post->ID,
            ),
            ['status' => 400],
        ),
        default => null,
    };
}

/**
 * Canonical allowlist of post_types whose display overrides Spectra One
 * actually honours. The theme's metabox is wired on `post` + `page` — any
 * other post_type (attachments, revisions, custom post types the theme
 * did not opt into) would accept a postmeta write silently but the theme
 * never reads it back, so the write is inert. Callers rely on this to
 * refuse the request up front with a clear error.
 *
 * @return list<string>
 */
function so_display_supported_post_types(): array
{
    return ['post', 'page'];
}

/**
 * Reject writes / reads that target a post whose `post_type` is not on the
 * Spectra One display-overrides allowlist. Semantically distinct from
 * `so_post_display_target_error` (which gates on `post_status`) — a post
 * can be `publish` and still be the wrong type for the metabox surface.
 * Called by every ability that treats `post_id` as an override subject.
 */
function so_post_display_type_error(\WP_Post $post): ?WP_Error
{
    if (in_array($post->post_type, so_display_supported_post_types(), strict: true)) {
        return null;
    }
    return new WP_Error(
        'spectra_one_unsupported_post_type',
        sprintf(
            /* translators: 1: post_type slug, 2: comma-separated supported slugs */
            __(
                'Post type `%1$s` is not on the Spectra One display-overrides allowlist (%2$s). The theme metabox is not registered on this type — writes would be inert.',
                domain: 'novamira-pro',
            ),
            $post->post_type,
            implode(', ', so_display_supported_post_types()),
        ),
        ['status' => 400],
    );
}

/**
 * Read a string field off a registered WP_Block_Type without breaking on
 * the fact that WP declares the `title` / `category` / `description`
 * properties as `string|null`. Falls back to the empty string on
 * anything else so the output contract stays trivially safe.
 * Restricted to the three properties `list-spectra-blocks` actually
 * reads — a dynamic property access would defeat mago's static analysis.
 */
function so_block_type_string(\WP_Block_Type $type, string $property): string
{
    /** @var mixed $raw */
    $raw = match ($property) {
        'title' => $type->title,
        'category' => $type->category,
        'description' => $type->description,
        default => null,
    };
    return is_string($raw) ? $raw : '';
}

/**
 * Resolve `post_id` from the ability input, honouring the WP-native
 * alias `id`. Returns 0 when neither key is present or is non-positive
 * — every caller treats 0 as "not provided" and surfaces
 * `spectra_one_invalid_input`.
 *
 * @param array<string, mixed> $input
 */
function so_resolve_post_id_alias(array $input): int
{
    /** @var mixed $raw */
    $raw = $input['post_id'] ?? $input['id'] ?? null;
    if (is_int($raw)) {
        return $raw > 0 ? $raw : 0;
    }
    if (is_string($raw) && is_numeric($raw)) {
        $n = (int) $raw;
        return $n > 0 ? $n : 0;
    }
    return 0;
}

/**
 * Canonical map of the 5 short display-override keys → the postmeta keys
 * the theme's metabox extension writes to. Kept explicit here so audits
 * pin the writable meta surface at rest.
 *
 * @return array{header: string, footer: string, title: string, sticky: string, transparent: string}
 */
function so_display_meta_keys(): array
{
    return [
        'header' => '_swt_meta_header_display',
        'footer' => '_swt_meta_footer_display',
        'title' => '_swt_meta_site_title_display',
        'sticky' => '_swt_meta_sticky_header',
        'transparent' => '_swt_meta_transparent_header',
    ];
}

/**
 * Read one of the 5 short display-override slots as a boolean. The
 * theme stores the "override active" state as the literal `1` in a
 * postmeta row; a missing row means "inherit the theme defaults".
 *
 * `$slug` MUST be one of the keys returned by `so_display_meta_keys()` —
 * every callsite is internal, so an unknown slug is a programmer error
 * and short-circuits to false rather than throwing.
 */
function so_read_display_meta(int $post_id, string $slug): bool
{
    $map = so_display_meta_keys();
    if (!isset($map[$slug])) {
        return false;
    }
    /** @var mixed $raw */
    $raw = get_post_meta($post_id, $map[$slug], single: true);
    return $raw !== '' && $raw !== null && filter_var($raw, FILTER_VALIDATE_BOOLEAN);
}

/**
 * List the short display-override keys that are currently ACTIVE on a
 * post — the ones with a truthy postmeta row. Used by
 * `list-posts-with-display-overrides` to summarise which overrides are
 * set without echoing the full boolean map.
 *
 * @return list<string>
 */
function so_active_display_overrides(int $post_id): array
{
    $out = [];
    $short_to_slug = [
        'hide_header' => 'header',
        'hide_footer' => 'footer',
        'hide_title' => 'title',
        'sticky_header' => 'sticky',
        'transparent_header' => 'transparent',
    ];
    foreach ($short_to_slug as $field => $slug) {
        if (!so_read_display_meta($post_id, slug: $slug)) {
            continue;
        }
        $out[] = $field;
    }
    return $out;
}

/**
 * Mirror a boolean override to a `wp_postmeta` row: true → write value
 * `1`; false → delete the row so the theme defaults render. Extracted
 * from `so_write_post_display_settings` so the write path stays
 * branch-lean (mago lint's no-else-clause).
 */
// @mago-expect linter:no-boolean-flag-parameter -- $value is the caller's target boolean, not a mode switch
function so_apply_display_meta(int $post_id, string $meta_key, bool $value): void
{
    if ($value) {
        update_post_meta($post_id, $meta_key, meta_value: 1);
        return;
    }
    delete_post_meta($post_id, $meta_key);
}

/**
 * Walk `$path` and coerce the leaf to int. Non-int / non-numeric-string
 * leaves resolve to `$fallback` (default 0). Used by the Sprint 4
 * delegating abilities to normalise theme-native return shapes.
 *
 * @param array<array-key, mixed> $data
 * @param list<string> $path
 */
function so_int_at(array $data, array $path, int $fallback = 0): int
{
    /** @var mixed $node */
    $node = $data;
    foreach ($path as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $fallback;
        }
        /** @var mixed $node */
        $node = $node[$key];
    }
    if (is_int($node)) {
        return $node;
    }
    if (is_string($node) && is_numeric($node)) {
        return (int) $node;
    }
    return $fallback;
}

/**
 * Bust the `options` cache group for the `swt_theme_options` row. WP core
 * updates the DB and refreshes its own alloptions bag on `update_option`,
 * but a `wp_cache_delete` for the individual key is still required for
 * lookups the theme runs via `get_option($key)` on a subsequent request
 * against an ext object cache — audit round 1, Concurrency lens.
 */
function so_flush_swt_theme_options_cache(): void
{
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete('swt_theme_options', group: 'options');
        // Alloptions bag is what serves the autoload=true path — flush it
        // too so the same-request second read observes the freshly-saved
        // value.
        wp_cache_delete('alloptions', group: 'options');
    }
}

/**
 * Walk `$path` and coerce the leaf to a `list<int>`. Non-list leaves
 * resolve to `$fallback`. Used by `add-font-family` to normalise the
 * weights the theme-native install-font echoes back.
 *
 * @param array<array-key, mixed> $data
 * @param list<string> $path
 * @param list<int> $fallback
 * @return list<int>
 */
function so_extract_int_list(array $data, array $path, array $fallback = []): array
{
    /** @var mixed $node */
    $node = $data;
    foreach ($path as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $fallback;
        }
        /** @var mixed $node */
        $node = $node[$key];
    }
    if (!is_array($node)) {
        return $fallback;
    }
    $out = [];
    /** @var mixed $value */
    foreach ($node as $value) {
        if (is_int($value)) {
            $out[] = $value;
            continue;
        }
        if (is_string($value) && is_numeric($value)) {
            $out[] = (int) $value;
        }
    }
    return $out;
}

/**
 * Invalidate every cache layer that WordPress uses to serve a
 * `wp_global_styles` overlay. `wp_update_post` handles `clean_post_cache`
 * on its own but does NOT touch the `theme_json` cache group the
 * `WP_Theme_JSON_Resolver` layer keys under — so writes would surface on
 * the DB but the next request would still read the pre-write payload
 * out of the object cache. Call after every `wp_global_styles` write.
 */
function so_flush_global_styles_cache(int $post_id): void
{
    if (function_exists('clean_post_cache')) {
        clean_post_cache($post_id);
    }
    if (function_exists('wp_cache_delete')) {
        // The exact cache key varies by WP release — clear every group name
        // WordPress has used so the ability keeps working across upgrades.
        foreach ([
            'wp_theme_json_data_user',
            'wp_theme_json_data_default',
            'wp_theme_json_data_theme',
        ] as $key) {
            wp_cache_delete($key, group: 'theme_json');
        }
    }
    if (class_exists('WP_Theme_JSON_Resolver') && method_exists('WP_Theme_JSON_Resolver', 'clean_cached_data')) {
        \WP_Theme_JSON_Resolver::clean_cached_data();
    }
}

/**
 * Cached inventory of the 11 custom hooks the Spectra One theme exposes
 * (all filters, prefix `swt_*`). Duplicated here (not proxied to the
 * theme-native `spectra-one/list-hooks` runtime) so audits pin the
 * documented surface at rest — a future theme release that adds hooks
 * must be re-audited before Novamira reports them.
 *
 * @return list<array{name: string, type: string, parameters: list<string>, default_value: string, file: string, description: string}>
 */
function so_hooks_registry(): array
{
    return [
        [
            'name' => 'swt_dynamic_theme_css',
            'type' => 'filter',
            'parameters' => ['css'],
            'default_value' => "''",
            'file' => 'inc/scripts.php',
            'description' => __('Filters the dynamic inline CSS added to the frontend.', domain: 'novamira-pro'),
        ],
        [
            'name' => 'swt_dynamic_theme_js',
            'type' => 'filter',
            'parameters' => ['js'],
            'default_value' => "''",
            'file' => 'inc/scripts.php',
            'description' => __('Filters the dynamic inline JavaScript added to the frontend.', domain: 'novamira-pro'),
        ],
        [
            'name' => 'swt_enqueue_frontend_scripts',
            'type' => 'filter',
            'parameters' => ['enqueue'],
            'default_value' => 'true',
            'file' => 'inc/scripts.php',
            'description' => __(
                'Controls whether frontend scripts and styles are enqueued. Return false to disable.',
                domain: 'novamira-pro',
            ),
        ],
        [
            'name' => 'swt_enqueue_editor_scripts',
            'type' => 'filter',
            'parameters' => ['enqueue'],
            'default_value' => 'true',
            'file' => 'inc/scripts.php',
            'description' => __(
                'Controls whether block editor scripts and styles are enqueued. Return false to disable.',
                domain: 'novamira-pro',
            ),
        ],
        [
            'name' => 'swt_editor_localize',
            'type' => 'filter',
            'parameters' => ['data'],
            'default_value' => 'array (is_spectra_plugin, get_screen_id, ...)',
            'file' => 'inc/scripts.php',
            'description' => __(
                'Filters the localized data array passed to the block editor script.',
                domain: 'novamira-pro',
            ),
        ],
        [
            'name' => 'swt_languages_directory',
            'type' => 'filter',
            'parameters' => ['lang_dir'],
            'default_value' => 'SWT_DIR . "languages"',
            'file' => 'inc/scripts.php',
            'description' => __(
                'Filters the path to the theme languages directory for loading translations.',
                domain: 'novamira-pro',
            ),
        ],
        [
            'name' => 'swt_svg_icons',
            'type' => 'filter',
            'parameters' => ['icons'],
            'default_value' => 'array (parsed from assets/svg/svgs.json)',
            'file' => 'inc/utilities/helpers.php',
            'description' => __('Filters the SVG icons array used by the theme.', domain: 'novamira-pro'),
        ],
        [
            'name' => 'swt_modal_menu_center',
            'type' => 'filter',
            'parameters' => ['center'],
            'default_value' => 'true',
            'file' => 'inc/blocks/navigation.php',
            'description' => __(
                'Controls whether the mobile modal navigation menu content is centered.',
                domain: 'novamira-pro',
            ),
        ],
        [
            'name' => 'swt_spctr_editor_block_spacing',
            'type' => 'filter',
            'parameters' => ['spacing'],
            'default_value' => '15',
            'file' => 'inc/compatibility/spectra.php',
            'description' => __(
                'Filters the default block spacing value (in pixels) used in the Spectra plugin editor.',
                domain: 'novamira-pro',
            ),
        ],
        [
            'name' => 'swt_ability_show_in_rest',
            'type' => 'filter',
            'parameters' => ['show_in_rest', 'ability_id', 'ability_instance'],
            'default_value' => 'true',
            'file' => 'inc/abilities/ability.php',
            'description' => __(
                'Controls whether a specific Spectra One ability is visible in the REST API.',
                domain: 'novamira-pro',
            ),
        ],
        [
            'name' => 'swt_ability_mcp_public',
            'type' => 'filter',
            'parameters' => ['is_public', 'ability_id', 'ability_instance'],
            'default_value' => 'true',
            'file' => 'inc/abilities/ability.php',
            'description' => __(
                'Controls whether a specific Spectra One ability is publicly exposed via MCP.',
                domain: 'novamira-pro',
            ),
        ],
    ];
}
