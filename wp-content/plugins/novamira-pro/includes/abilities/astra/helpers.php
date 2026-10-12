<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** Maximum byte length for a single string setting value accepted by write abilities. */
const ASTRA_MAX_SETTING_VALUE_BYTES = 65_536;

/** Maximum combined serialized byte size for a full HFB grid payload (desktop + mobile). */
const ASTRA_MAX_HFB_GRID_BYTES = 262_144; // 256 KB

/**
 * Shared helpers for the Astra theme integration.
 *
 * Provides:
 *  - Error factories with HTTP status codes (astra_error, astra_*_error)
 *  - Coercion / sanitization (astra_coerce_int/bool/array, astra_strip_nul_bytes, astra_array_is_list)
 *  - Progressive disclosure (astra_cap_bytes, astra_cap_list)
 *  - Option locks (astra_with_settings_lock, astra_with_palette_lock)
 *  - CSS cache invalidation (astra_invalidate_dynamic_css_cache)
 *  - Storage abstraction (astra_read_setting, astra_read_settings_snapshot)
 *  - Post gate (astra_post_or_error)
 *  - Allowlist arrays (astra_global_settings_allowlist, astra_post_meta_allowlist, …)
 *  - Customizer bootstrap (ast_bootstrap_customize_manager, ast_is_astra_section, …)
 */

// ---------------------------------------------------------------------------
// Error factories
// ---------------------------------------------------------------------------

/**
 * Uniform WP_Error factory for this surface. The HTTP status is embedded in
 * `$data['status']` so the Abilities API can return the right 4xx code over
 * REST instead of falling back to 500.
 *
 * Code-to-status map:
 *   astra_invalid_input=400, astra_not_found=404,
 *   astra_theme_inactive=412, astra_addon_inactive=412,
 *   astra_module_disabled=412, astra_min_version_unmet=412
 */
function astra_error(string $code, string $message): WP_Error
{
    $status_map = [
        'astra_invalid_input' => 400,
        'astra_not_found' => 404,
        'astra_theme_inactive' => 412,
        'astra_addon_inactive' => 412,
        'astra_module_disabled' => 412,
        'astra_min_version_unmet' => 412,
        'astra_woocommerce_inactive' => 412,
    ];
    $status = $status_map[$code] ?? 400;
    return new WP_Error($code, $message, ['status' => $status]);
}

function astra_theme_inactive_error(): WP_Error
{
    return astra_error(
        'astra_theme_inactive',
        message: 'The Astra theme is not active or does not meet the minimum supported version. Check theme.active and theme.min_satisfied from astra-check-setup.',
    );
}

function astra_addon_required_error(string $module_slug): WP_Error
{
    return astra_error('astra_addon_inactive', sprintf(
        'This ability requires the Astra Pro Addon with the "%s" module active. Check addon.active and modules.%s from astra-check-setup.',
        $module_slug,
        $module_slug,
    ));
}

function astra_module_disabled_error(string $slug): WP_Error
{
    return astra_error('astra_module_disabled', sprintf(
        'The Astra Pro Addon module "%s" is not active. Enable it via astra-enable-module or from the Astra admin panel.',
        $slug,
    ));
}

function astra_unknown_setting_error(string $key): WP_Error
{
    return astra_error('astra_invalid_input', sprintf(
        'Unknown setting key "%s". Call astra-get-defaults to inspect the valid key set.',
        $key,
    ));
}

function astra_invalid_input_error(string $msg): WP_Error
{
    return astra_error('astra_invalid_input', $msg);
}

function astra_post_not_found_error(int $id): WP_Error
{
    return astra_error('astra_not_found', sprintf(
        'Post %d not found or is not accessible (trashed / revision / auto-draft).',
        $id,
    ));
}

// ---------------------------------------------------------------------------
// Coercion / sanitization
// ---------------------------------------------------------------------------

/**
 * Normalise a REST-supplied value to a real PHP int. WP REST schema validation
 * accepts numeric strings ("42") as valid `integer` and forwards them unchanged
 * — passing such a value to a typed `int $id` call triggers a TypeError.
 *
 * @param mixed $value
 */
function astra_coerce_int(mixed $value): int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && ctype_digit(ltrim($value, characters: '-'))) {
        return (int) $value;
    }
    return 0;
}

/**
 * Normalise a REST-supplied value to a real PHP bool. WP REST schema validation
 * accepts "true" / "false" / 1 / 0 as valid `boolean` — our callbacks compare
 * `=== true`, so string booleans silently miss. Cast here at the ability boundary.
 *
 * @param mixed $value
 */
function astra_coerce_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_string($value)) {
        $lower = strtolower($value);
        return $lower === 'true' || $lower === '1' || $lower === 'yes';
    }
    if (is_int($value)) {
        return $value === 1;
    }
    return false;
}

/**
 * Defensive cast for an ability `input` field declared array-typed in the JSON
 * Schema but that PHP's REST validator may still let a scalar slip through.
 * Returns the value as-is when it is already an array, or null when it is neither
 * array nor castable.
 *
 * @param mixed $value
 * @return array<array-key, mixed>|null
 */
function astra_coerce_array(mixed $value): ?array
{
    return is_array($value) ? $value : null;
}

/**
 * Strip NUL bytes from agent-supplied strings before they reach WP storage.
 * A NUL slips through `trim()` / `sanitize_text_field()` and confuses downstream
 * consumers (admin list tables, JSON output truncated at the NUL).
 */
function astra_strip_nul_bytes(string $value): string
{
    return str_replace(search: "\0", replace: '', subject: $value);
}

/**
 * WP-native ↔ short-name alias pairs every ability wrapping
 * `wp_insert_post` / `wp_update_post` accepts on input. Both the short
 * and the WP-native name are accepted; passing both
 * with different values returns `astra_alias_conflict`.
 *
 * @return array<string, string>
 */
function astra_wp_post_alias_map(): array
{
    return [
        'title' => 'post_title',
        'content' => 'post_content',
        'status' => 'post_status',
        'slug' => 'post_name',
    ];
}

/**
 * Normalise the WP-native aliases in a caller-supplied `wp_insert_post`
 * / `wp_update_post` payload down to the short-name form the callback
 * downstream expects. When BOTH forms are present with the same value,
 * the WP-native alias is silently dropped. When BOTH forms are present
 * with DIFFERENT values, returns `astra_alias_conflict` so the caller
 * makes an explicit choice.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_normalize_wp_aliases(array $input): array|WP_Error
{
    foreach (astra_wp_post_alias_map() as $short => $wp_native) {
        $has_short = array_key_exists($short, $input);
        $has_wp = array_key_exists($wp_native, $input);
        if (!$has_wp) {
            continue;
        }
        if ($has_short && $input[$short] !== $input[$wp_native]) {
            return astra_error('astra_alias_conflict', sprintf(
                'Both `%1$s` and `%2$s` were supplied with different values. Pass only one — they are aliases of the same field.',
                $short,
                $wp_native,
            ));
        }
        // Promote the WP-native form to its short-name equivalent, then
        // drop the wp-native key so downstream schema-strict paths never
        // see a "duplicate" key.
        if (!$has_short) {
            $input[$short] = $input[$wp_native];
        }
        unset($input[$wp_native]);
    }
    return $input;
}

/**
 * PHP 8.0 polyfill for `array_is_list()` (native in 8.1+). Checks that the
 * array's keys are exactly the contiguous 0..count-1 sequence; an empty array
 * is considered a list.
 *
 * @param array<array-key, mixed> $value
 */
function astra_array_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }
    return array_keys($value) === range(0, count($value) - 1);
}

/**
 * Emit an associative map as-is, or `new \stdClass()` when empty. Every
 * Astra output schema that declares an object (settings values, header
 * layout, palette detail, woocommerce settings, defaults map) must go
 * through this helper on its way out — a raw empty PHP array serialises
 * to JSON `[]`, which strict validators reject against `type: object`
 * and breaks round-trips on fresh installs. Callers pass string-keyed
 * maps; the `array-key` param exists only to accommodate the wider type
 * Mago infers from `is_array(...) ? ... : []`.
 *
 * @template TValue
 * @param array<array-key, TValue> $map
 * @return array<array-key, TValue>|\stdClass
 */
function astra_object_or_map(array $map): array|\stdClass
{
    return $map === [] ? new \stdClass() : $map;
}

/**
 * Cap the list-valued sub-fields of a rule bag so a single hook / header
 * with a runaway targeting list (e.g. `specific: [<10k post ids>]`) does
 * not blow past the agent's context budget on every read. Only the six
 * list-shaped sub-keys the theme reader iterates over are trimmed; scalar
 * sub-keys (`rule`, `taxonomy`) pass through untouched. Truncation is
 * silent by design — targeting rule lists this long always indicate a
 * misconfigured hook, and the compact `location_summary` / list-hooks
 * counters already surface the pre-cap totals.
 *
 * @param array<array-key, mixed> $bag
 * @return array<array-key, mixed>
 */
function astra_cap_rule_bag(array $bag, int $limit = 500): array
{
    static $list_subkeys = ['specific', 'specificPosts', 'posts', 'terms', 'users', 'taxonomies'];
    foreach ($list_subkeys as $key) {
        if (!isset($bag[$key]) || !is_array($bag[$key]) || count($bag[$key]) <= $limit) {
            continue;
        }
        $bag[$key] = array_slice($bag[$key], offset: 0, length: $limit);
    }
    return $bag;
}

// ---------------------------------------------------------------------------
// Progressive disclosure
// ---------------------------------------------------------------------------

/**
 * Truncate a string value to `$limit` bytes with a trailing marker so agents
 * know the value was cut. Non-string values pass through unchanged.
 *
 * @param mixed $value
 * @return mixed
 */
function astra_cap_bytes(mixed $value, int $limit = 2048): mixed
{
    if (!is_string($value)) {
        return $value;
    }
    $len = strlen($value);
    if ($len <= $limit) {
        return $value;
    }
    // Size the suffix using $len as the pessimistic truncated byte count so that
    // $target_len is always <= $limit - worst_suffix_len, keeping the final output
    // within the declared limit even after the UTF-8 boundary walk below.
    $worst_suffix = '... [truncated ' . $len . ' bytes]';
    $worst_suffix_len = strlen($worst_suffix);
    if ($worst_suffix_len >= $limit) {
        // Limit too small to fit even the suffix — return value unchanged.
        return $value;
    }
    $target_len = $limit - $worst_suffix_len;
    // Walk backward from $target_len to find a valid UTF-8 character boundary.
    // UTF-8 continuation bytes have the form 10xxxxxx (0x80–0xBF); cutting mid-
    // sequence produces an invalid byte stream. We step back until we land on a
    // leading byte (anything outside the 0x80–0xBF range).
    while ($target_len > 0 && (ord($value[$target_len]) & 0xC0) === 0x80) {
        $target_len--;
    }
    $actual_truncated = $len - $target_len;
    $suffix = '... [truncated ' . $actual_truncated . ' bytes]';
    return substr($value, offset: 0, length: $target_len) . $suffix;
}

/**
 * Slice a rows array to a paged window and wrap it in a compact envelope.
 * `has_more` tells the agent whether there is more data beyond the current page.
 *
 * @param list<mixed> $rows
 * @return array{items: list<mixed>, has_more: bool, total: int}
 */
function astra_cap_list(array $rows, int $limit = 20, int $offset = 0): array
{
    $total = count($rows);
    $slice = array_slice($rows, $offset, $limit);
    return [
        'items' => array_values($slice),
        'has_more' => ($offset + $limit) < $total,
        'total' => $total,
    ];
}

// ---------------------------------------------------------------------------
// Option locks
// ---------------------------------------------------------------------------

/**
 * Acquire a MySQL advisory GET_LOCK around `$fn`, scoped to the `astra-settings`
 * option bag. Prevents data loss when multiple parallel write requests each read
 * the bag before the previous write commits (same race pattern kd_with_options_lock
 * protects against in the Kadence integration). Falls through without locking on
 * backends that do not support GET_LOCK (SQLite drop-in, some managed hosts).
 * Lock name is scoped by blog_id for multisite correctness.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function astra_with_settings_lock(callable $fn): mixed
{
    return ast_with_named_lock('astra-settings', $fn);
}

/**
 * Analogous to `astra_with_settings_lock` but scoped to the `astra-color-palettes`
 * option so palette writes do not race with settings writes.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function astra_with_palette_lock(callable $fn): mixed
{
    return ast_with_named_lock('astra-color-palettes', $fn);
}

/**
 * Analogous to `astra_with_settings_lock` but scoped to the
 * `_astra_ext_enabled_extensions` option. Prevents data loss when concurrent
 * enable/disable requests each read the option before the previous write commits.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function astra_with_extensions_lock(callable $fn): mixed
{
    return ast_with_named_lock('_astra_ext_enabled_extensions', $fn);
}

/**
 * Acquire a MySQL advisory GET_LOCK around `$fn`, scoped to the post meta bag
 * for the given post id. Prevents data loss when multiple parallel write requests
 * each read the meta before the previous write commits (read → merge → write race).
 * Lock name is scoped by blog_id for multisite correctness.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function astra_with_post_lock(int $post_id, callable $fn): mixed
{
    return ast_with_named_lock('astra_pm_' . $post_id, $fn);
}

/**
 * Test whether the current database backend supports MySQL advisory GET_LOCK.
 * Result is memoized for the lifetime of the request. Returns false on SQLite
 * drop-ins and managed database hosts that reject advisory lock queries.
 */
function astra_settings_lock_supported(): bool
{
    static $supported = null;
    /** @var bool|null $supported */
    if (is_bool($supported)) {
        return $supported;
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    $lock_timeout = 0;
    /** @var mixed $result */
    // @mago-expect analysis:possibly-invalid-argument
    $result = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', 'nvp_astra_lock_test', $lock_timeout));
    $supported = $result === '1' || $result === 1;
    if ($supported) {
        // @mago-expect analysis:possibly-invalid-argument
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'nvp_astra_lock_test'));
    }
    return $supported;
}

/**
 * Internal: acquire/release a GET_LOCK advisory lock named after `$option_name`
 * and scoped by blog_id. Callers use the public `astra_with_*_lock` wrappers.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function ast_with_named_lock(string $option_name, callable $fn): mixed
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    // MySQL caps GET_LOCK names at 64 chars. `nvp_astra_` (10) + blog_id (≤10)
    // + `_` (1) + md5 (32) = 53 — safely under the cap.
    $lock_name = sprintf('nvp_astra_%d_%s', get_current_blog_id(), md5($option_name));

    /** @var mixed $acquired_raw */
    // @mago-expect analysis:possibly-invalid-argument
    $acquired_raw = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
    $acquired = $acquired_raw === '1' || $acquired_raw === 1;

    try {
        return $fn();
    } finally {
        if ($acquired) {
            // @mago-expect analysis:possibly-invalid-argument
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}

// ---------------------------------------------------------------------------
// CSS cache invalidation
// ---------------------------------------------------------------------------

/**
 * Flush Astra's persistent dynamic-CSS cache. Astra builds its inline stylesheet
 * from `astra-settings` and caches the result in options + transients; a write
 * made outside the Customizer (e.g. our ability callbacks) would render stale
 * CSS until the cache TTL expires unless we flush here. `Astra_Cache::refresh_assets()`
 * is the official invalidation endpoint; the option deletes cover the addon's
 * extra cache layer.
 */
function astra_invalidate_dynamic_css_cache(): void
{
    delete_option('astra-addon-cache-css-key');
    delete_option('astra-header-transient-cache');
    if (class_exists('Astra_Cache') && method_exists('Astra_Cache', 'instance')) {
        \Astra_Cache::instance()->refresh_assets();
    }
}

// ---------------------------------------------------------------------------
// Storage abstraction
// ---------------------------------------------------------------------------

/**
 * Read a single key from the `astra-settings` bag through the canonical theme
 * accessor, which applies defaults + the `astra_get_option` filter.
 *
 * @return mixed
 */
function astra_read_setting(string $key, mixed $default = ''): mixed
{
    if (!function_exists('astra_get_option')) {
        return $default;
    }
    return astra_get_option($key, $default);
}

/**
 * Return the full merged settings snapshot (stored values + defaults applied)
 * via the canonical theme accessor `astra_get_options()`.
 *
 * @return array<string, mixed>
 */
function astra_read_settings_snapshot(): array
{
    if (!function_exists('astra_get_options')) {
        return [];
    }
    $result = astra_get_options();
    // @mago-expect analysis:redundant-type-comparison
    // @mago-expect analysis:redundant-condition
    return is_array($result) ? $result : [];
}

// ---------------------------------------------------------------------------
// Post gate
// ---------------------------------------------------------------------------

/**
 * Load a post by id, rejecting states that make it inaccessible for editing:
 * trashed, auto-draft, or a revision. When `$expected_post_type` is given,
 * also rejects posts of a different type.
 *
 * @return WP_Post|WP_Error
 */
function astra_post_or_error(int $id, ?string $expected_post_type = null): WP_Post|WP_Error
{
    if ($id <= 0) {
        return astra_post_not_found_error($id);
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post) {
        return astra_post_not_found_error($id);
    }
    if (in_array($post->post_status, ['trash', 'auto-draft'], strict: true)) {
        return astra_post_not_found_error($id);
    }
    if ($post->post_type === 'revision') {
        return astra_post_not_found_error($id);
    }
    if ($expected_post_type !== null && $post->post_type !== $expected_post_type) {
        return new WP_Error(
            'astra_not_found',
            sprintf('Post %d exists but its type is "%s", not "%s".', $id, $post->post_type, $expected_post_type),
            ['status' => 404],
        );
    }
    return $post;
}

// ---------------------------------------------------------------------------
// Allowlist arrays
// ---------------------------------------------------------------------------

/**
 * Allowlist of `astra-settings` keys the integration accepts for reads and
 * writes. Covers ~200 keys from `Astra_Theme_Options::defaults()` spanning
 * layout, sidebar, colors, typography, header, footer, buttons, blog, and
 * miscellaneous settings. Keys not in this map are returned as `unknown_keys`
 * by write abilities rather than being silently absorbed.
 *
 * Values are the expected PHP type ('string', 'integer', 'boolean', 'array').
 * Responsive keys (font-size-h1, font-size-body, …) use 'array' to match
 * Astra's desktop/tablet/mobile object structure.
 *
 * Composed from private sub-maps to keep each map under the Halstead-volume
 * threshold of the static analyser.
 *
 * @return array<string, string>
 */
function astra_global_settings_allowlist(): array
{
    return array_merge(
        ast_layout_settings_map(),
        ast_color_settings_map(),
        ast_typography_settings_map(),
        ast_header_settings_map(),
        ast_footer_settings_map(),
        ast_blog_settings_map(),
        ast_misc_settings_map(),
    );
}

/**
 * Layout, container and sidebar keys.
 *
 * @return array<string, string>
 */
function ast_layout_settings_map(): array
{
    return [
        'site-layout' => 'string',
        'content-layout' => 'string',
        'site-content-layout' => 'string',
        'ast-site-content-layout' => 'string',
        'site-content-style' => 'string',
        'single-page-ast-content-layout' => 'string',
        'single-page-content-style' => 'string',
        'single-post-content-style' => 'string',
        'single-post-ast-content-layout' => 'string',
        'archive-post-ast-content-layout' => 'string',
        'site-content-width' => 'integer',
        'narrow-container-max-width' => 'integer',
        'content-width' => 'integer',
        'sidebar-layout' => 'string',
        'sidebar-width' => 'integer',
        'sidebar-last-widget-margin' => 'boolean',
        'site-sidebar-layout' => 'string',
        'site-sidebar-width' => 'integer',
        'site-sidebar-style' => 'string',
        'site-sticky-sidebar' => 'boolean',
        'single-page-sidebar-layout' => 'string',
        'single-page-sidebar-style' => 'string',
        'single-post-sidebar-layout' => 'string',
        'single-post-sidebar-style' => 'string',
        'archive-post-sidebar-layout' => 'string',
        'archive-post-sidebar-style' => 'string',
    ];
}

/**
 * Global color keys.
 *
 * @return array<string, string>
 */
function ast_color_settings_map(): array
{
    return [
        'theme-color' => 'string',
        'text-color' => 'string',
        'link-color' => 'string',
        'link-hover-color' => 'string',
        'link-h-color' => 'string',
        'headings-color' => 'string',
        'heading-base-color' => 'string',
        'background-color' => 'string',
        'content-bg-color' => 'string',
        'border-color' => 'string',
    ];
}

/**
 * Typography keys (body, headings global, H1–H6, site/post sizes).
 *
 * @return array<string, string>
 */
function ast_typography_settings_map(): array
{
    return [
        'body-font-family' => 'string',
        'body-font-variant' => 'string',
        'body-font-weight' => 'string',
        'body-font-style' => 'string',
        'body-line-height' => 'string',
        'body-text-transform' => 'string',
        'body-font-size' => 'string',
        'font-size-body' => 'array',
        'body-font-extras' => 'array',
        'para-margin-bottom' => 'string',
        'underline-content-links' => 'boolean',
        'font-family-heading' => 'string',
        'font-weight-heading' => 'string',
        'text-transform-heading' => 'string',
        'line-height-heading' => 'string',
        'headings-font-family' => 'string',
        'headings-font-weight' => 'string',
        'headings-font-extras' => 'array',
        'font-family-h1' => 'string',
        'font-size-h1' => 'array',
        'font-weight-h1' => 'string',
        'line-height-h1' => 'string',
        'text-transform-h1' => 'string',
        'font-family-h2' => 'string',
        'font-size-h2' => 'array',
        'font-weight-h2' => 'string',
        'line-height-h2' => 'string',
        'text-transform-h2' => 'string',
        'font-family-h3' => 'string',
        'font-size-h3' => 'array',
        'font-weight-h3' => 'string',
        'line-height-h3' => 'string',
        'text-transform-h3' => 'string',
        'font-family-h4' => 'string',
        'font-size-h4' => 'array',
        'font-weight-h4' => 'string',
        'font-family-h5' => 'string',
        'font-size-h5' => 'array',
        'font-family-h6' => 'string',
        'font-size-h6' => 'array',
        'font-size-site-title' => 'array',
        'font-size-site-tagline' => 'array',
        'font-size-page-title' => 'array',
        'font-size-post-tax' => 'array',
        'font-size-post-meta' => 'array',
    ];
}

/**
 * Header keys (branding, primary nav, buttons, mobile).
 *
 * @return array<string, string>
 */
function ast_header_settings_map(): array
{
    return [
        'header-main-layout' => 'string',
        'header-bg-color' => 'string',
        'header-text-color' => 'string',
        'header-link-color' => 'string',
        'header-link-h-color' => 'string',
        'site-title-color' => 'string',
        'site-description-color' => 'string',
        'logo-width' => 'array',
        'logo-mobile-width' => 'array',
        'display-site-title' => 'boolean',
        'display-site-tagline' => 'boolean',
        'header-color-site-title' => 'string',
        'header-color-h-site-title' => 'string',
        'header-color-site-tagline' => 'string',
        'disable-primary-nav' => 'boolean',
        'header-layouts' => 'string',
        'header-main-rt-section' => 'string',
        'header-display-outside-menu' => 'boolean',
        'header-main-rt-section-html' => 'string',
        'header-main-rt-section-button-text' => 'string',
        'header-main-rt-section-button-style' => 'string',
        'header-main-rt-section-button-text-color' => 'string',
        'header-main-rt-section-button-back-color' => 'string',
        'header-main-rt-section-button-text-h-color' => 'string',
        'header-main-rt-section-button-back-h-color' => 'string',
        'header-main-sep' => 'integer',
        'header-main-sep-color' => 'string',
        'header-main-layout-width' => 'string',
        'header-main-menu-label' => 'string',
        'header-main-menu-align' => 'string',
        'header-main-submenu-container-animation' => 'string',
        'mobile-header-breakpoint' => 'string',
        'primary-submenu-item-border' => 'boolean',
        'primary-submenu-b-color' => 'string',
        'primary-submenu-item-b-color' => 'string',
        'primary-header-button-font-family' => 'string',
        'primary-header-button-font-weight' => 'string',
        'primary-header-button-text-transform' => 'string',
        'primary-header-button-line-height' => 'integer',
        'primary-header-button-letter-spacing' => 'string',
    ];
}

/**
 * Footer keys (bar, widgets, small footer, buttons).
 *
 * @return array<string, string>
 */
function ast_footer_settings_map(): array
{
    return [
        'footer-bg-color' => 'string',
        'footer-color' => 'string',
        'footer-link-color' => 'string',
        'footer-link-h-color' => 'string',
        'footer-bar-bg-color' => 'string',
        'footer-bar-color' => 'string',
        'footer-adv-display-footer-row' => 'boolean',
        'footer-layout' => 'integer',
        'footer-adv-text-color' => 'string',
        'footer-adv-link-color' => 'string',
        'footer-adv-link-h-color' => 'string',
        'footer-adv-wgt-title-color' => 'string',
        'footer-sml-layout' => 'string',
        'footer-sml-section-1' => 'string',
        'footer-sml-section-1-credit' => 'string',
        'footer-sml-section-2' => 'string',
        'footer-sml-section-2-credit' => 'string',
        'footer-sml-dist-equal-align' => 'boolean',
        'footer-sml-divider' => 'integer',
        'footer-sml-divider-color' => 'string',
        'footer-layout-width' => 'string',
        'footer-adv' => 'string',
        'footer-adv-border-width' => 'string',
        'footer-adv-border-color' => 'string',
        'button-bg-color' => 'string',
        'button-bg-h-color' => 'string',
        'button-color' => 'string',
        'button-h-color' => 'string',
        'button-border-radius' => 'integer',
        'button-font-size' => 'string',
        'secondary-button-bg-color' => 'string',
        'secondary-button-bg-h-color' => 'string',
        'secondary-button-color' => 'string',
        'secondary-button-h-color' => 'string',
    ];
}

/**
 * Blog, archive and single-post keys.
 *
 * @return array<string, string>
 */
function ast_blog_settings_map(): array
{
    return [
        'archive-post-layout' => 'string',
        'blog-layout' => 'string',
        'blog-meta' => 'array',
        'blog-grid-columns' => 'integer',
        'blog-single-width' => 'string',
        'blog-single-max-width' => 'integer',
        'page-single-width' => 'string',
        'page-single-max-width' => 'integer',
        'single-content-images-shadow' => 'boolean',
        'blog-post-structure' => 'array',
        'blog-post-per-page' => 'integer',
        'blog-hover-effect' => 'string',
        'blog-width' => 'string',
        'blog-meta-date-type' => 'string',
        'blog-max-width' => 'integer',
        'blog-post-content' => 'string',
        'blog-category-style' => 'string',
        'blog-tag-style' => 'string',
        'blog-post-meta-divider-type' => 'string',
        'blog-meta-category-style' => 'string',
        'blog-meta-tag-style' => 'string',
        'blog-image-ratio-type' => 'string',
        'blog-image-ratio-pre-scale' => 'string',
        'blog-image-custom-scale-width' => 'integer',
        'blog-image-custom-scale-height' => 'integer',
        'ast-dynamic-single-post-elements-gap' => 'integer',
        'ast-dynamic-single-post-meta-font-weight' => 'string',
        'ast-dynamic-single-post-metadata' => 'array',
    ];
}

/**
 * Search, mobile, comments, and miscellaneous keys.
 *
 * @return array<string, string>
 */
function ast_misc_settings_map(): array
{
    return [
        'ast-search-results-per-page' => 'integer',
        'ast-search-live-search' => 'boolean',
        'mobile-header-toggle-btn-style' => 'string',
        'hide-custom-menu-mobile' => 'integer',
        'mobile-header-toggle-target' => 'string',
        'enable-comments-area' => 'boolean',
        'comment-form-position' => 'string',
        'comments-box-placement' => 'string',
        'close-icon-click-outsite' => 'boolean',
        'scroll-to-top-position' => 'string',
        'enable-scroll-to-id' => 'boolean',
        'site-accessibility-toggle' => 'boolean',
        'use-logo-svg-icon' => 'boolean',
        'wp-blocks-ui' => 'string',
    ];
}

/**
 * Allowlist of per-post meta keys the Astra meta boxes write to.
 * Sourced directly from `Astra_Meta_Boxes::post_meta_options()` (the
 * `astra_meta_box_options` filter baseline). These are the keys that
 * `astra_get_option_meta()` reads when `$post_id` is provided.
 *
 * @return list<string>
 */
function astra_post_meta_allowlist(): array
{
    return [
        // Layout / container
        'ast-site-content-layout',
        'site-content-style',
        'site-sidebar-layout',
        'site-sidebar-style',
        // Header display toggles
        'ast-global-header-display',
        'ast-main-header-display',
        'ast-hfb-above-header-display',
        'ast-hfb-below-header-display',
        'ast-hfb-mobile-header-display',
        // Page element toggles
        'ast-banner-title-visibility',
        'site-post-title',
        'ast-featured-img',
        'ast-breadcrumbs-content',
        'ast-disable-related-posts',
        // Footer display toggles
        'footer-sml-layout',
        'footer-adv-display',
        // Migration / misc
        'astra-migrate-meta-layouts',
        // Background (complex responsive array)
        'ast-page-background-enabled',
        'ast-page-background-meta',
        'ast-content-background-meta',
    ];
}

/**
 * Enum map for per-post meta keys that accept a finite set of string values.
 * Keys absent from this map are validated only as plain strings (NUL-stripped,
 * size-capped). Background array keys (`astra_post_meta_bg_keys()`) are
 * handled separately and never appear here.
 *
 * @return array<string, list<string>>
 */
function astra_post_meta_enum_map(): array
{
    // Checkbox-style toggles: '' means "inherit global", 'disabled' means explicitly off.
    $toggle = ['', 'disabled'];

    return [
        'ast-site-content-layout' => [
            'default',
            'plain-container',
            'boxed-container',
            'content-boxed-container',
            'page-builder',
            'narrow-container',
        ],
        'site-content-style' => ['default', 'unboxed', 'boxed'],
        'site-sidebar-layout' => ['default', 'no-sidebar', 'left-sidebar', 'right-sidebar'],
        'site-sidebar-style' => ['default', 'unboxed', 'boxed'],
        'ast-global-header-display' => $toggle,
        'ast-main-header-display' => $toggle,
        'ast-hfb-above-header-display' => $toggle,
        'ast-hfb-below-header-display' => $toggle,
        'ast-hfb-mobile-header-display' => $toggle,
        'ast-banner-title-visibility' => $toggle,
        'site-post-title' => $toggle,
        'ast-featured-img' => $toggle,
        'ast-breadcrumbs-content' => $toggle,
        'ast-disable-related-posts' => $toggle,
        'footer-sml-layout' => $toggle,
        'footer-adv-display' => $toggle,
        'ast-page-background-enabled' => ['default', 'enabled', 'disabled', ''],
    ];
}

/**
 * Meta keys whose value is a complex responsive background array.
 * `astra-set-post-settings` skips enum validation for these keys and
 * delegates structural validation to `astra-set-post-background`.
 *
 * @return list<string>
 */
function astra_post_meta_bg_keys(): array
{
    return ['ast-page-background-meta', 'ast-content-background-meta'];
}

/**
 * Allowlist of field names within each responsive viewport of a background
 * meta array (`ast-page-background-meta` / `ast-content-background-meta`).
 *
 * @return list<string>
 */
function astra_post_bg_field_allowlist(): array
{
    return [
        'background-color',
        'background-image',
        'background-repeat',
        'background-position',
        'background-size',
        'background-attachment',
        'background-type',
        'background-media',
        'overlay-type',
        'overlay-color',
        'overlay-opacity',
        'overlay-gradient',
    ];
}

/**
 * Ordered list of background viewport names used in responsive background meta.
 *
 * @return list<string>
 */
function astra_post_bg_viewports(): array
{
    return ['desktop', 'tablet', 'mobile'];
}

// ---------------------------------------------------------------------------
// Advanced Hooks CPT helpers (addon-gated advanced-hooks module)
// ---------------------------------------------------------------------------

/**
 * Valid `ast-advanced-hook-layout` values. Sourced from
 * `Astra_Ext_Advanced_Hooks_Loader::get_layout_type()` — excludes the
 * `0` placeholder sentinel. These are the values the Astra admin UI exposes.
 *
 * @return list<string>
 */
function astra_hook_layout_allowlist(): array
{
    return ['header', 'footer', '404-page', 'hooks', 'content', 'template'];
}

/**
 * Post-meta keys the integration reads and writes for astra-advanced-hook posts.
 * Intentionally omits `ast-advanced-hook-with-php` and `ast-advanced-hook-php-code`
 * — those keys enable raw PHP execution and must never be exposed to AI agents.
 *
 * @return list<string>
 */
function astra_hook_meta_allowlist(): array
{
    return [
        'ast-advanced-hook-layout',
        'ast-advanced-hook-action',
        'ast-advanced-hook-priority',
        'ast-advanced-hook-location',
        'ast-advanced-hook-exclusion',
        'ast-advanced-hook-users',
        'ast-advanced-hook-enabled',
        'ast-advanced-hook-template-type',
        'ast-advanced-hook-header',
        'ast-advanced-hook-footer',
        'ast-advanced-hook-padding',
    ];
}

/**
 * Deep-validate the shape of the display / user / device rule meta bags
 * (`location`, `exclusion`, `users`, `header`, `footer`, `padding`) before
 * they are persisted via `astra_write_hook_meta` /
 * `astra_write_page_header_meta`. Astra's own reader trusts these arrays
 * verbatim on render — a caller passing garbage would either silently
 * disable the rule or throw a render-time notice.
 *
 * Validated shape (permissive but bounded):
 *  - top-level must be a string-keyed array (list of anonymous entries is
 *    rejected — the theme reader keys on `rule` / `specific` / …)
 *  - top-level keys must be in the per-field allowlist below
 *  - primitive-typed subfields (`rule`, `type`) must be strings
 *  - list-typed subfields (`specific`, `specificPosts`, `posts`, `terms`,
 *    `users`, `taxonomies`) must be lists (int|string members only)
 *  - nested `exclusion` mirrors `location` — validated recursively
 *
 * Returns `null` when the input is well-formed OR the field is absent,
 * `WP_Error(astra_invalid_meta_shape)` otherwise so the calling ability
 * can reject the whole write before any DB touch.
 *
 * @param array<string, mixed> $input the raw caller payload
 */
function astra_validate_meta_rules_shape(array $input): ?WP_Error
{
    foreach (astra_meta_rules_shape_map() as $field => $allowed_keys) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $err = astra_validate_meta_rule_bag($field, $input[$field], $allowed_keys);
        if ($err instanceof WP_Error) {
            return $err;
        }
    }
    return null;
}

/**
 * Fields that carry rule-shaped payloads. Each maps to an allowlist of
 * top-level keys the theme reader honours; anything else is a typo the
 * caller should see. Kept as one canonical map so the validator + any
 * future editor UI never drift.
 *
 * @return array<string, list<string>>
 */
function astra_meta_rules_shape_map(): array
{
    return [
        'location' => ['rule', 'specific', 'specificPosts', 'posts', 'taxonomy', 'terms', 'taxonomies'],
        'exclusion' => ['rule', 'specific', 'specificPosts', 'posts', 'taxonomy', 'terms', 'taxonomies'],
        'users' => ['rule', 'specific', 'users'],
        'header' => ['disable_header', 'disable_primary_header', 'disable_main_menu', 'sticky', 'transparent'],
        'footer' => ['disable_footer', 'disable_primary_footer'],
        'padding' => ['above', 'below', 'left', 'right', 'top', 'bottom'],
    ];
}

/**
 * Validate one rule bag (`location`, `users`, ...) against its allowed
 * keys and the primitive-shape guards. Returns WP_Error on any shape
 * violation; `null` on success or an empty bag (empty is a legitimate
 * clear).
 *
 * @param list<string> $allowed_keys
 */
function astra_validate_meta_rule_bag(string $field, mixed $raw, array $allowed_keys): ?WP_Error
{
    if (!is_array($raw)) {
        return astra_error('astra_invalid_meta_shape', sprintf('`%s` must be an object (associative array).', $field));
    }
    if ($raw === []) {
        return null;
    }
    if (astra_array_is_list($raw)) {
        return astra_error('astra_invalid_meta_shape', sprintf(
            '`%s` must be a string-keyed object, not a positional list.',
            $field,
        ));
    }
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            return astra_error('astra_invalid_meta_shape', sprintf('`%s` has a non-string key.', $field));
        }
        if (!in_array($key, $allowed_keys, strict: true)) {
            return astra_error('astra_invalid_meta_shape', sprintf(
                'Unknown key `%1$s` in `%2$s`. Allowed keys: %3$s.',
                $key,
                $field,
                implode(', ', $allowed_keys),
            ));
        }
        $err = astra_validate_meta_rule_subfield($field, $key, $value);
        if ($err instanceof WP_Error) {
            return $err;
        }
    }
    return null;
}

/**
 * Type-check a single subfield inside a rule bag. Delegates through a
 * flat `if` sequence so mago's type inference doesn't fold the
 * separate string / list branches into an unreachable-elseif chain.
 */
function astra_validate_meta_rule_subfield(string $field, string $key, mixed $value): ?WP_Error
{
    if (in_array($key, ['rule', 'type', 'taxonomy'], strict: true)) {
        if (!is_string($value)) {
            return astra_error('astra_invalid_meta_shape', sprintf('`%1$s.%2$s` must be a string.', $field, $key));
        }
        return null;
    }
    if (in_array($key, ['specific', 'specificPosts', 'posts', 'terms', 'users', 'taxonomies'], strict: true)) {
        return astra_validate_meta_rule_list_subfield($field, $key, $value);
    }
    return null;
}

/**
 * List-typed subfields (`specific`, `posts`, `terms`, ...) must be a
 * positional list of int / string members. Anything else is a shape
 * violation the theme reader would silently ignore or trip on.
 */
function astra_validate_meta_rule_list_subfield(string $field, string $key, mixed $value): ?WP_Error
{
    if (!is_array($value)) {
        return astra_error('astra_invalid_meta_shape', sprintf('`%1$s.%2$s` must be a positional list.', $field, $key));
    }
    if (!astra_array_is_list($value)) {
        return astra_error('astra_invalid_meta_shape', sprintf('`%1$s.%2$s` must be a positional list.', $field, $key));
    }
    /** @var mixed $item */
    foreach ($value as $item) {
        if (!is_int($item) && !is_string($item)) {
            return astra_error('astra_invalid_meta_shape', sprintf(
                '`%1$s.%2$s[]` items must be int or string.',
                $field,
                $key,
            ));
        }
    }
    return null;
}

/**
 * Load an `astra-advanced-hook` post by id, or return WP_Error. Delegates to
 * `astra_post_or_error`, which rejects trashed, auto-draft, and revision posts.
 *
 * @return WP_Post|WP_Error
 */
function astra_hook_or_error(int $id): WP_Post|WP_Error
{
    return astra_post_or_error($id, expected_post_type: 'astra-advanced-hook');
}

/**
 * Full payload for an astra-advanced-hook post. Content is capped at 32 KB
 * with a trailing marker so agents know the value was truncated. The
 * php-execution surface (`ast-advanced-hook-with-php`, `ast-advanced-hook-php-code`)
 * is intentionally omitted.
 *
 * @return array<string, mixed>
 */
function astra_hook_to_array(WP_Post $post): array
{
    $id = $post->ID;

    $meta_str = static fn(string $k): string => (string) get_post_meta(post_id: $id, key: $k, single: true);
    $meta_arr = static function (string $k) use ($id): array {
        /** @var mixed $v */
        $v = get_post_meta(post_id: $id, key: $k, single: true);
        return is_array($v) ? $v : [];
    };

    $enabled_raw = (string) get_post_meta(post_id: $id, key: 'ast-advanced-hook-enabled', single: true);
    $priority_raw = (string) get_post_meta(post_id: $id, key: 'ast-advanced-hook-priority', single: true);

    $content = (string) astra_cap_bytes($post->post_content, limit: 32_768);

    return [
        'id' => $id,
        'title' => $post->post_title,
        'content' => $content,
        'status' => $post->post_status,
        'layout' => $meta_str('ast-advanced-hook-layout'),
        'action' => $meta_str('ast-advanced-hook-action'),
        'priority' => $priority_raw !== '' ? (int) $priority_raw : 10,
        'enabled' => $enabled_raw !== 'no',
        'template_type' => $meta_str('ast-advanced-hook-template-type'),
        'header' => astra_object_or_map($meta_arr('ast-advanced-hook-header')),
        'footer' => astra_object_or_map($meta_arr('ast-advanced-hook-footer')),
        'padding' => astra_object_or_map($meta_arr('ast-advanced-hook-padding')),
        'location' => astra_object_or_map(astra_cap_rule_bag($meta_arr('ast-advanced-hook-location'))),
        'exclusion' => astra_object_or_map(astra_cap_rule_bag($meta_arr('ast-advanced-hook-exclusion'))),
        'users' => astra_object_or_map(astra_cap_rule_bag($meta_arr('ast-advanced-hook-users'))),
    ];
}

/**
 * Compact summary for list-hooks. Omits the content body (can be large) and
 * replaces full targeting arrays with a human-readable count string.
 *
 * @return array<string, mixed>
 */
function astra_hook_summary(WP_Post $post): array
{
    $id = $post->ID;

    /** @var mixed $location_raw */
    $location_raw = get_post_meta(post_id: $id, key: 'ast-advanced-hook-location', single: true);
    /** @var mixed $exclusion_raw */
    $exclusion_raw = get_post_meta(post_id: $id, key: 'ast-advanced-hook-exclusion', single: true);
    $location_count = is_array($location_raw) ? count($location_raw) : 0;
    $exclusion_count = is_array($exclusion_raw) ? count($exclusion_raw) : 0;
    $location_summary =
        $location_count
        . ' rule'
        . ($location_count !== 1 ? 's' : '')
        . ', '
        . $exclusion_count
        . ' exclusion'
        . ($exclusion_count !== 1 ? 's' : '');

    $enabled_raw = (string) get_post_meta(post_id: $id, key: 'ast-advanced-hook-enabled', single: true);
    $priority_raw = (string) get_post_meta(post_id: $id, key: 'ast-advanced-hook-priority', single: true);

    return [
        'id' => $id,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'layout' => (string) get_post_meta(post_id: $id, key: 'ast-advanced-hook-layout', single: true),
        'action' => (string) get_post_meta(post_id: $id, key: 'ast-advanced-hook-action', single: true),
        'enabled' => $enabled_raw !== 'no',
        'priority' => $priority_raw !== '' ? (int) $priority_raw : 10,
        'template_type' => (string) get_post_meta(post_id: $id, key: 'ast-advanced-hook-template-type', single: true),
        'location_summary' => $location_summary,
    ];
}

/**
 * Write astra-advanced-hook meta values from an input array. Only writes keys
 * present in `$input` (merge semantics). Handles type coercion and NUL stripping.
 * Never writes `ast-advanced-hook-with-php` or `ast-advanced-hook-php-code`.
 *
 * Coercion rules:
 *   layout, action, template_type — string, NUL-stripped
 *   priority                      — int (via astra_coerce_int)
 *   enabled                       — bool → 'yes' / 'no' string in meta
 *   location, exclusion, users, header, footer, padding — array passthrough
 *
 * @param array<string, mixed> $input
 */
function astra_write_hook_meta(int $id, array $input): void
{
    // String meta keys
    $string_meta = [
        'layout' => 'ast-advanced-hook-layout',
        'action' => 'ast-advanced-hook-action',
        'template_type' => 'ast-advanced-hook-template-type',
    ];
    foreach ($string_meta as $field => $meta_key) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $value = is_string($input[$field]) ? astra_strip_nul_bytes($input[$field]) : '';
        update_post_meta($id, $meta_key, $value);
    }

    // Priority (int)
    if (array_key_exists('priority', $input)) {
        update_post_meta($id, meta_key: 'ast-advanced-hook-priority', meta_value: astra_coerce_int($input['priority']));
    }

    // Enabled (bool → 'yes' / 'no')
    if (array_key_exists('enabled', $input)) {
        $en = astra_coerce_bool($input['enabled']);
        update_post_meta($id, meta_key: 'ast-advanced-hook-enabled', meta_value: $en ? 'yes' : 'no');
    }

    // Array meta (passthrough — Astra reads these via get_post_meta single=true and expects arrays)
    $array_meta = [
        'location' => 'ast-advanced-hook-location',
        'exclusion' => 'ast-advanced-hook-exclusion',
        'users' => 'ast-advanced-hook-users',
        'header' => 'ast-advanced-hook-header',
        'footer' => 'ast-advanced-hook-footer',
        'padding' => 'ast-advanced-hook-padding',
    ];
    foreach ($array_meta as $field => $meta_key) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $arr = astra_coerce_array($input[$field]);
        if ($arr !== null) {
            update_post_meta($id, $meta_key, $arr);
        }
    }
}

/**
 * Partition a raw per-post meta patch against `astra_post_meta_allowlist()`.
 *
 * For keys present in `astra_post_meta_enum_map()`, validates the value is a
 * member of the declared enum. Background keys (`astra_post_meta_bg_keys()`)
 * bypass enum validation and accept any value that coerces to array via
 * `astra_coerce_array()`. All other allowlisted keys are validated as plain
 * strings (NUL-stripped, size-capped at 64 KB).
 *
 * Returns:
 *   known   — keys that are allowlisted and valid, ready for `update_post_meta()`
 *   unknown — keys not in the allowlist
 *   invalid — allowlisted keys with enum violations, type errors, or oversized strings
 *
 * @param array<array-key, mixed> $patch
 * @return array{known: array<string, mixed>, unknown: list<string>, invalid: list<array{key: string, reason: string}>}
 */
function astra_partition_post_settings_input(array $patch): array
{
    $allowlist_idx = array_flip(astra_post_meta_allowlist());
    $enum_map = astra_post_meta_enum_map();
    $bg_idx = array_flip(astra_post_meta_bg_keys());

    $known = [];
    $unknown = [];
    $invalid = [];

    /** @var mixed $value */
    foreach ($patch as $raw_key => $value) {
        $key = is_string($raw_key) ? $raw_key : '';
        if ($key === '') {
            continue;
        }
        if (!array_key_exists($key, $allowlist_idx)) {
            $unknown[] = $key;
            continue;
        }

        // Background keys: require array.
        if (array_key_exists($key, $bg_idx)) {
            $arr = astra_coerce_array($value);
            if ($arr === null) {
                $invalid[] = [
                    'key' => $key,
                    'reason' => 'expected array (responsive background object), got ' . gettype($value),
                ];
                continue;
            }
            $serialized_size = strlen(serialize($arr));
            if ($serialized_size > ASTRA_MAX_SETTING_VALUE_BYTES) {
                $invalid[] = [
                    'key' => $key,
                    'reason' =>
                        'array serializes to '
                            . $serialized_size
                            . ' bytes (limit '
                            . ASTRA_MAX_SETTING_VALUE_BYTES
                            . ')',
                ];
                continue;
            }
            $known[$key] = $arr;
            continue;
        }

        // All other keys store strings. Sanitize before validation.
        if (!is_string($value)) {
            $invalid[] = [
                'key' => $key,
                'reason' => 'expected string, got ' . gettype($value),
            ];
            continue;
        }
        $value = astra_strip_nul_bytes($value);
        if (strlen($value) > ASTRA_MAX_SETTING_VALUE_BYTES) {
            $invalid[] = [
                'key' => $key,
                'reason' => 'string value exceeds 64 KB limit (' . strlen($value) . ' bytes)',
            ];
            continue;
        }

        // Enum validation for keys that declare a finite value set.
        if (array_key_exists($key, $enum_map)) {
            if (!in_array($value, $enum_map[$key], strict: true)) {
                $accepted = implode(', ', array_map(static fn(string $v) => $v === ''
                    ? '(empty string)'
                    : '"' . $v . '"', $enum_map[$key]));
                $invalid[] = [
                    'key' => $key,
                    'reason' => 'invalid value "' . $value . '"; accepted: ' . $accepted,
                ];
                continue;
            }
        }

        $known[$key] = $value;
    }

    return ['known' => $known, 'unknown' => $unknown, 'invalid' => $invalid];
}

/**
 * Typography-specific setting keys from `astra-settings` with their expected
 * PHP type. Used by `astra-set-typography` to partition the input patch against
 * a focused allowlist (body/headings/H1–H6 font family, size, weight,
 * line-height, letter-spacing, text-transform, and extras arrays).
 *
 * Responsive size keys (font-size-body, font-size-h1, …) store a
 * {desktop, tablet, mobile, desktop-unit, tablet-unit, mobile-unit} array;
 * pass an array value when setting them.
 *
 * @return array<string, string>
 */
function astra_typography_settings_allowlist(): array
{
    return [
        // Body
        'body-font-family' => 'string',
        'body-font-variant' => 'string',
        'body-font-weight' => 'string',
        'body-font-style' => 'string',
        'body-line-height' => 'string',
        'body-text-transform' => 'string',
        'font-size-body' => 'array',
        'body-font-extras' => 'array',
        'para-margin-bottom' => 'string',
        'underline-content-links' => 'boolean',

        // Headings — global
        'headings-font-family' => 'string',
        'headings-font-weight' => 'string',
        'headings-font-extras' => 'array',
        'font-family-heading' => 'string',
        'font-weight-heading' => 'string',
        'text-transform-heading' => 'string',
        'line-height-heading' => 'string',

        // H1
        'font-family-h1' => 'string',
        'font-size-h1' => 'array',
        'font-weight-h1' => 'string',
        'line-height-h1' => 'string',
        'text-transform-h1' => 'string',

        // H2
        'font-family-h2' => 'string',
        'font-size-h2' => 'array',
        'font-weight-h2' => 'string',
        'line-height-h2' => 'string',
        'text-transform-h2' => 'string',

        // H3
        'font-family-h3' => 'string',
        'font-size-h3' => 'array',
        'font-weight-h3' => 'string',
        'line-height-h3' => 'string',
        'text-transform-h3' => 'string',

        // H4
        'font-family-h4' => 'string',
        'font-size-h4' => 'array',
        'font-weight-h4' => 'string',

        // H5
        'font-family-h5' => 'string',
        'font-size-h5' => 'array',

        // H6
        'font-family-h6' => 'string',
        'font-size-h6' => 'array',

        // Site / post title sizes
        'font-size-site-title' => 'array',
        'font-size-site-tagline' => 'array',
        'font-size-page-title' => 'array',
        'font-size-post-tax' => 'array',
        'font-size-post-meta' => 'array',
    ];
}

/**
 * Header/Footer Builder element slugs recognised by the Astra HFB grid.
 * Sprint 3 (HFB read/write) uses this as the validity gate for element
 * slugs submitted by the agent.
 *
 * @return list<string>
 */
function astra_hfb_element_slugs(): array
{
    return [
        'logo',
        'menu-1',
        'menu-2',
        'menu-3',
        'button-1',
        'button-2',
        'button-3',
        'html-1',
        'html-2',
        'html-3',
        'search',
        'account',
        'cart',
        'edd-cart',
        'woo-cart',
        'mobile-trigger',
        'widget-1',
        'widget-2',
        'widget-3',
        'widget-4',
    ];
}

/**
 * Desktop header builder grid cell keys. The grid has 3 rows × 5 columns plus
 * a `popup` row with a single `popup_content` cell. Returning the full flat
 * list lets `astra_validate_hfb_grid` check any cell key regardless of which
 * row it appears in.
 *
 * @return list<string>
 */
function astra_hfb_cell_keys_desktop(): array
{
    return [
        'above_left',
        'above_left_center',
        'above_center',
        'above_right_center',
        'above_right',
        'primary_left',
        'primary_left_center',
        'primary_center',
        'primary_right_center',
        'primary_right',
        'below_left',
        'below_left_center',
        'below_center',
        'below_right_center',
        'below_right',
        // popup row — single cell that holds the off-canvas menu element
        'popup_content',
    ];
}

/**
 * Mobile header builder grid cell keys. Mobile uses a 3-row × 3-column layout
 * (left, center, right per row) plus the same popup cell as desktop.
 * Keys are identical to the desktop names — Astra does NOT use a `mobile_`
 * prefix in the stored option; the desktop/mobile distinction lives in the
 * option key (`header-desktop-items` vs `header-mobile-items`).
 *
 * @return list<string>
 */
function astra_hfb_cell_keys_mobile(): array
{
    return [
        'above_left',
        'above_center',
        'above_right',
        'primary_left',
        'primary_center',
        'primary_right',
        'below_left',
        'below_center',
        'below_right',
        // popup row — same single cell as desktop
        'popup_content',
    ];
}

/**
 * Footer desktop builder grid cell keys. The footer grid uses numeric column
 * suffixes (_1 … _5) rather than positional names, with 3 rows × 5 columns.
 * How many of those columns are visible is governed by separate hba/hbb/hb
 * column-count settings, but all 15 keys are structurally valid.
 *
 * @return list<string>
 */
function astra_hfb_footer_cell_keys_desktop(): array
{
    return [
        'above_1',
        'above_2',
        'above_3',
        'above_4',
        'above_5',
        'primary_1',
        'primary_2',
        'primary_3',
        'primary_4',
        'primary_5',
        'below_1',
        'below_2',
        'below_3',
        'below_4',
        'below_5',
    ];
}

/**
 * Footer mobile builder grid cell keys. Mobile footer has 3 rows × 2 columns
 * (the same numeric suffix scheme as desktop, capped at _2).
 *
 * @return list<string>
 */
function astra_hfb_footer_cell_keys_mobile(): array
{
    return [
        'above_1',
        'above_2',
        'primary_1',
        'primary_2',
        'below_1',
        'below_2',
    ];
}

/**
 * Footer HFB element slugs. The footer builder uses completely different
 * elements from the header builder: copyright bar, footer menu, widget areas,
 * social icon sets, HTML blocks, and buttons. Covers both free and Astra Pro
 * Addon items available in v4.13.5.
 *
 * @return list<string>
 */
function astra_hfb_footer_element_slugs(): array
{
    return [
        'copyright',
        'menu',
        'widget-1',
        'widget-2',
        'widget-3',
        'widget-4',
        'social-icons-1',
        'social-icons-2',
        'html-1',
        'html-2',
        'html-3',
        'button-1',
        'button-2',
        'divider-1',
        'divider-2',
        'divider-3',
    ];
}

/**
 * `astra-settings` keys that belong to the header builder configuration rows
 * (primary, above, and below header). These cover height, spacing, background,
 * layout width, separator, and stack behavior for all three header rows.
 * Used by get-header-layout to extract the header-specific config slice from
 * the full `astra-settings` bag.
 *
 * @return list<string>
 */
function astra_hfb_header_config_keys(): array
{
    return [
        // Primary header row
        'hb-header-main-layout-width',
        'hb-header-height',
        'hb-header-main-sep',
        'hb-header-main-sep-color',
        'hb-header-main-menu-align',
        'hb-header-bg-obj-responsive',
        'hb-header-spacing',
        'hb-stack',
        // Above header row
        'hba-header-height',
        'hba-header-spacing',
        'hba-header-bg-obj-responsive',
        'hba-header-layout',
        'hba-header-separator',
        'hba-header-bottom-border-color',
        'hba-header-text-color-responsive',
        'hba-header-link-color-responsive',
        'hba-header-link-h-color-responsive',
        'hba-stack',
        // Below header row
        'hbb-header-height',
        'hbb-header-spacing',
        'hbb-header-bg-obj-responsive',
        'hbb-header-layout',
        'hbb-header-separator',
        'hbb-header-bottom-border-color',
        'hbb-stack',
    ];
}

/**
 * `astra-settings` keys that belong to the footer builder configuration rows
 * (above, primary/below footer). Used by get-footer-layout to extract the
 * footer-specific config slice.
 *
 * @return list<string>
 */
function astra_hfb_footer_config_keys(): array
{
    return [
        // Above footer row
        'hba-footer-height',
        'hba-footer-column',
        'hba-footer-layout',
        'hba-footer-layout-width',
        'hba-footer-vertical-alignment',
        'hba-footer-bg-obj-responsive',
        // Below footer row (small footer bar)
        'hbb-footer-height',
        'hbb-footer-column',
        'hbb-footer-layout',
        'hbb-footer-layout-width',
        'hbb-footer-separator',
        'hbb-footer-top-border-color',
        'hbb-footer-vertical-alignment',
        'hbb-footer-bg-obj-responsive',
        // Primary footer row
        'hb-footer-height',
        'hb-footer-column',
        'hb-footer-layout',
        'hb-footer-layout-width',
        'hb-footer-separator',
        'hb-footer-main-sep',
        'hb-footer-main-sep-color',
        'hb-footer-bg-obj-responsive',
        'hb-footer-bottom-border-color',
        'hb-footer-vertical-alignment',
        'hb-primary-footer-height',
    ];
}

/**
 * Validate and normalize a full HFB grid payload against the appropriate cell
 * and element allowlists. Walks every row → cell → element in `$input`.
 *
 * Unknown cell keys are collected in `unknown_cells` and skipped; unknown
 * element slugs are collected in `unknown_elements` and skipped. Both sets are
 * de-duplicated. The returned `clean` grid contains only known cells with only
 * known element slugs — it is safe to persist directly.
 *
 * Variant determines which allowlists to use:
 *   'desktop'        → header desktop cells + header element slugs
 *   'mobile'         → header mobile cells  + header element slugs
 *   'footer-desktop' → footer desktop cells + footer element slugs
 *   'footer-mobile'  → footer mobile cells  + footer element slugs
 *
 * @param array<array-key, mixed> $input
 * @param string $variant 'desktop' | 'mobile' | 'footer-desktop' | 'footer-mobile'
 * @return array{clean: array<string, array<string, list<string>>>, unknown_cells: list<string>, unknown_elements: list<string>}
 */
function astra_validate_hfb_grid(array $input, string $variant): array
{
    [$valid_cells, $valid_elements] = match ($variant) {
        'footer-desktop' => [astra_hfb_footer_cell_keys_desktop(), astra_hfb_footer_element_slugs()],
        'footer-mobile' => [astra_hfb_footer_cell_keys_mobile(), astra_hfb_footer_element_slugs()],
        'mobile' => [astra_hfb_cell_keys_mobile(), astra_hfb_element_slugs()],
        default => [astra_hfb_cell_keys_desktop(), astra_hfb_element_slugs()],
    };

    /** @var array<string, mixed> $valid_cells_index */
    $valid_cells_index = array_flip($valid_cells);
    /** @var array<string, mixed> $valid_elements_index */
    $valid_elements_index = array_flip($valid_elements);

    /** @var array<string, array<string, list<string>>> $clean */
    $clean = [];
    $all_unknown_cells = [];
    $all_unknown_elements = [];

    /** @var mixed $row */
    foreach ($input as $row_key => $row) {
        if (!is_string($row_key) || !is_array($row)) {
            continue;
        }
        $row_result = ast_validate_hfb_row($row, $valid_cells_index, $valid_elements_index);
        $clean[$row_key] = $row_result['clean_row'];
        $all_unknown_cells = array_merge($all_unknown_cells, $row_result['unknown_cells']);
        $all_unknown_elements = array_merge($all_unknown_elements, $row_result['unknown_elements']);
    }

    return [
        'clean' => $clean,
        'unknown_cells' => array_values(array_unique($all_unknown_cells)),
        'unknown_elements' => array_values(array_unique($all_unknown_elements)),
    ];
}

/**
 * Validate one row of an HFB grid — the cell-key layer.
 *
 * @param array<array-key, mixed> $row
 * @param array<string, mixed>    $valid_cells_index    Result of array_flip on the cell allowlist.
 * @param array<string, mixed>    $valid_elements_index Result of array_flip on the element allowlist.
 * @return array{clean_row: array<string, list<string>>, unknown_cells: list<string>, unknown_elements: list<string>}
 */
function ast_validate_hfb_row(array $row, array $valid_cells_index, array $valid_elements_index): array
{
    /** @var array<string, list<string>> $clean_row */
    $clean_row = [];
    /** @var list<string> $unknown_cells */
    $unknown_cells = [];
    $all_unknown_elements = [];

    /** @var mixed $elements */
    foreach ($row as $cell_key => $elements) {
        if (!is_string($cell_key)) {
            continue;
        }
        if (!array_key_exists($cell_key, $valid_cells_index)) {
            $unknown_cells[] = $cell_key;
            continue;
        }
        $elements_list = is_array($elements) ? $elements : [];
        $filtered = ast_filter_hfb_elements($elements_list, $valid_elements_index);
        $clean_row[$cell_key] = $filtered['clean'];
        $all_unknown_elements = array_merge($all_unknown_elements, $filtered['unknown']);
    }

    return [
        'clean_row' => $clean_row,
        'unknown_cells' => $unknown_cells,
        'unknown_elements' => $all_unknown_elements,
    ];
}

/**
 * Filter an HFB cell's element list, separating known slugs from unknown ones.
 *
 * @param array<array-key, mixed> $elements
 * @param array<string, mixed>    $valid_index Result of array_flip on the element allowlist.
 * @return array{clean: list<string>, unknown: list<string>}
 */
function ast_filter_hfb_elements(array $elements, array $valid_index): array
{
    $clean = [];
    $unknown = [];
    /** @var mixed $slug */
    foreach ($elements as $slug) {
        if (!is_string($slug)) {
            continue;
        }
        if (!array_key_exists($slug, $valid_index)) {
            $unknown[] = $slug;
            continue;
        }
        $clean[] = $slug;
    }
    return ['clean' => $clean, 'unknown' => $unknown];
}

/**
 * Return the valid HFB cell keys for a specific header row and device variant.
 * Used by `reorder-header-row` to validate that `from_cell` and `to_cell`
 * belong to the stated row. The `popup` row is intentionally excluded — it
 * holds the off-canvas menu and is not a target for element reordering.
 *
 * @return list<string>
 */
function astra_hfb_header_row_cells(string $row, string $variant): array
{
    if ($variant === 'mobile') {
        return match ($row) {
            'above' => ['above_left', 'above_center', 'above_right'],
            'primary' => ['primary_left', 'primary_center', 'primary_right'],
            'below' => ['below_left', 'below_center', 'below_right'],
            default => [],
        };
    }
    // Desktop (default and any unrecognised variant)
    return match ($row) {
        'above' => ['above_left', 'above_left_center', 'above_center', 'above_right_center', 'above_right'],
        'primary' => ['primary_left', 'primary_left_center', 'primary_center', 'primary_right_center', 'primary_right'],
        'below' => ['below_left', 'below_left_center', 'below_center', 'below_right_center', 'below_right'],
        default => [],
    };
}

// ---------------------------------------------------------------------------
// Settings partition helpers (used by edit-settings + set-typography)
// ---------------------------------------------------------------------------

/**
 * Return the PHP type label of an arbitrary value. Mirrors `gettype()` but
 * collapses 'double' and 'float' to 'double', and maps 'boolean' to
 * 'boolean' (not the full gettype 'boolean'). Return values are always one
 * of: 'string', 'integer', 'boolean', 'array', 'double', 'NULL', 'object'.
 *
 * @param mixed $v
 */
function astra_php_type_of(mixed $v): string
{
    return match (true) {
        is_string($v) => 'string',
        is_int($v) => 'integer',
        is_bool($v) => 'boolean',
        is_array($v) => 'array',
        is_float($v) => 'double',
        is_null($v) => 'NULL',
        default => 'object',
    };
}

/**
 * Whether `$v` satisfies the `$expected` type declared in the allowlist.
 * Lenient for integer: accepts PHP int, whole-number float, and digit-only
 * string (WP REST may forward numeric strings as-is).
 *
 * @param mixed $v
 */
function astra_type_matches(string $expected, mixed $v): bool
{
    return match ($expected) {
        'string' => is_string($v),
        'boolean' => is_bool($v),
        'array' => is_array($v),
        'integer' => is_int($v)
            || is_float($v) && floor($v) === $v
            || is_string($v) && $v !== '' && ctype_digit(ltrim($v, characters: '-')),
        default => true,
    };
}

/**
 * Partition a raw settings patch against an allowlist.
 *
 * When `$allowlist` is null the global settings allowlist is used; pass
 * `astra_typography_settings_allowlist()` to partition a typography patch.
 *
 * Returns:
 *   known     — keys that are allowlisted and whose value type matches
 *   unknown   — keys not present in the allowlist
 *   mismatched — keys in the allowlist but whose value has the wrong type
 *
 * @param array<array-key, mixed>     $patch
 * @param array<string, string>|null $allowlist key => expected_type map
 * @return array{known: array<string, mixed>, unknown: list<string>, mismatched: list<array{key: string, expected: string, got: string}>}
 */
function astra_partition_settings_input(array $patch, ?array $allowlist = null): array
{
    $list = $allowlist ?? astra_global_settings_allowlist();
    $known = [];
    $unknown = [];
    $mismatched = [];

    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if (!array_key_exists($key, $list)) {
            $unknown[] = $key;
            continue;
        }
        $expected = $list[$key];
        // Sanitize and cap string values before type-matching and storing.
        if (is_string($value)) {
            $value = astra_strip_nul_bytes($value);
            if (strlen($value) > ASTRA_MAX_SETTING_VALUE_BYTES) {
                $mismatched[] = [
                    'key' => $key,
                    'expected' => 'string_lt_64kb',
                    'got' => 'oversized_string_' . strlen($value) . 'bytes',
                ];
                continue;
            }
        }
        // Cap array values by serialized size to prevent multi-MB blobs reaching storage.
        if (is_array($value)) {
            $serialized_size = strlen(serialize($value));
            if ($serialized_size > ASTRA_MAX_SETTING_VALUE_BYTES) {
                $mismatched[] = [
                    'key' => $key,
                    'expected' => 'array_lt_64kb',
                    'got' => 'oversized_array_' . $serialized_size . 'bytes',
                ];
                continue;
            }
        }
        if (!astra_type_matches($expected, $value)) {
            $mismatched[] = ['key' => $key, 'expected' => $expected, 'got' => astra_php_type_of($value)];
            continue;
        }
        $known[$key] = $value;
    }

    return ['known' => $known, 'unknown' => $unknown, 'mismatched' => $mismatched];
}

// ---------------------------------------------------------------------------
// Palette helpers (shared by get-color-palette and set-color-palette)
// ---------------------------------------------------------------------------

/**
 * Read the `astra-color-palettes` option, falling back to Astra's built-in
 * defaults when the option has never been saved.
 *
 * @return array<array-key, mixed>
 */
function ast_load_palette_option(): array
{
    /** @var mixed $raw */
    $raw = get_option('astra-color-palettes', []);
    $data = is_array($raw) ? $raw : [];

    if (
        $data === []
        && class_exists('Astra_Global_Palette')
        && method_exists('Astra_Global_Palette', 'get_default_color_palette')
    ) {
        $data = \Astra_Global_Palette::get_default_color_palette();
    }

    return $data;
}

// ---------------------------------------------------------------------------
// Customizer bootstrap helpers
// ---------------------------------------------------------------------------

/**
 * Boot a WP_Customize_Manager instance and fire `customize_register` so the
 * theme and plugins register all their sections and controls. CLI / REST
 * requests do not normally hit the Customizer init path, so we bootstrap on
 * demand. Returns null on installs that somehow lack the manager class.
 */
function ast_bootstrap_customize_manager(): ?\WP_Customize_Manager
{
    // Guard: if a previous attempt to fire `customize_register` threw (e.g. Astra
    // Customizer classes not autoloaded in CLI/REST context), do not retry — the
    // hook's partial side-effects may cause a different fatal on the second call.
    static $failed = false;
    // @mago-expect analysis:impossible-condition
    if ($failed) {
        return null;
    }

    if (!class_exists('WP_Customize_Manager')) {
        require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
    }
    if (!class_exists('WP_Customize_Manager')) {
        return null;
    }
    global $wp_customize;
    if (!$wp_customize instanceof \WP_Customize_Manager) {
        $wp_customize = new \WP_Customize_Manager();
        try {
            do_action('customize_register', $wp_customize);
        } catch (\Throwable $e) {
            // Astra's Customizer config classes may not be autoloaded in CLI/REST
            // context (e.g., Astra_Customizer_Config_Base not found). Mark the
            // bootstrap as failed so callers return empty output without retrying.
            $wp_customize = null;
            $failed = true;
            return null;
        }
    }
    return $wp_customize;
}

/**
 * Whether a Customizer section id belongs to the Astra namespace.
 */
function ast_is_astra_section(string $id): bool
{
    return (
        str_starts_with($id, 'astra-')
        || str_starts_with($id, 'astra_')
        || str_starts_with($id, 'ast-')
        || str_starts_with($id, 'ast_')
    );
}

/**
 * Whether a Customizer control id or its bound setting id belongs to Astra.
 */
function ast_is_astra_control(string $control_id, string $setting_id): bool
{
    return ast_is_astra_section($control_id) || ast_is_astra_section($setting_id);
}

/**
 * Resolve the primary setting id from a Customizer control. The `$setting`
 * field on a control can be a `WP_Customize_Setting` instance or a bare string
 * 'default' (meaning the control has no meaningful single bound setting).
 *
 * @param mixed $setting
 */
function ast_resolve_customizer_setting_id(mixed $setting): string
{
    if ($setting instanceof \WP_Customize_Setting) {
        return $setting->id;
    }
    if (is_string($setting) && $setting !== 'default') {
        return $setting;
    }
    return '';
}

/**
 * Resolve the multi-setting map from a Customizer control (responsive controls
 * bind desktop / tablet / mobile settings simultaneously). Returns an empty array
 * when the control does not use the multi-setting pattern.
 *
 * @param mixed $settings
 * @return array<string, string>
 */
function ast_resolve_customizer_settings_map(mixed $settings): array
{
    if (!is_array($settings)) {
        return [];
    }
    $out = [];
    /** @var mixed $entry */
    foreach ($settings as $key => $entry) {
        if (!is_string($key)) {
            continue;
        }
        if ($entry instanceof \WP_Customize_Setting) {
            $out[$key] = $entry->id;
            continue;
        }
        if (is_string($entry)) {
            $out[$key] = $entry;
        }
    }
    return $out;
}

/**
 * Collect the Astra-owned section ids from the booted Customizer manager.
 * Used by the controls walker to retain controls whose own id does not carry
 * an Astra prefix but live inside an Astra section.
 *
 * @return list<string>
 */
function ast_known_astra_section_ids(\WP_Customize_Manager $manager): array
{
    $out = [];
    /** @var \WP_Customize_Section $section */
    foreach ($manager->sections() as $section) {
        if (!ast_is_astra_section($section->id)) {
            continue;
        }
        $out[] = $section->id;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Advanced Headers CPT helpers (addon-gated advanced-headers module)
// ---------------------------------------------------------------------------

/**
 * Valid values for the `layout` sub-key of the `ast-advanced-headers-layout`
 * meta array. Sourced from the Astra Pro Addon admin UI radio buttons.
 *
 * Note: `ast-advanced-headers-layout` is stored as a PHP array — this allowlist
 * applies only to the `layout` sub-key, not to the top-level meta value.
 *
 * @return list<string>
 */
function astra_page_header_layout_allowlist(): array
{
    return ['advanced-headers-layout-1', 'advanced-headers-layout-2', 'disable'];
}

/**
 * Post-meta keys the integration reads and writes for astra_adv_header posts.
 * All five meta values are stored as PHP arrays (serialised by WordPress).
 *
 * @return list<string>
 */
function astra_page_header_meta_allowlist(): array
{
    return [
        'ast-advanced-headers-layout',
        'ast-advanced-headers-design',
        'ast-advanced-headers-location',
        'ast-advanced-headers-exclusion',
        'ast-advanced-headers-users',
    ];
}

/**
 * Load an astra_adv_header post by id, or return WP_Error. Delegates to
 * `astra_post_or_error`, which rejects trashed, auto-draft, and revision posts.
 *
 * @return WP_Post|WP_Error
 */
function astra_page_header_or_error(int $id): WP_Post|WP_Error
{
    return astra_post_or_error($id, expected_post_type: 'astra_adv_header');
}

/**
 * Full payload for an astra_adv_header post. Content is capped at 32 KB with
 * a trailing marker so agents know the value was truncated.
 *
 * `ast-advanced-headers-layout` is a PHP array in the database. This function
 * exposes only the `layout` sub-key as a plain string. Use
 * `astra_write_page_header_meta` to update it (merge-patch behaviour preserves
 * the other sub-keys such as `breadcrumb` and `force-transparent-disabled`).
 *
 * There is no `ast-advanced-headers-enabled` meta key in the Astra Pro Addon.
 * `enabled` is derived from post_status: publish → true, draft → false.
 *
 * @return array<string, mixed>
 */
function astra_page_header_to_array(WP_Post $post): array
{
    $id = $post->ID;
    $meta_arr = static function (string $k) use ($id): array {
        /** @var mixed $v */
        $v = get_post_meta(post_id: $id, key: $k, single: true);
        return is_array($v) ? $v : [];
    };

    /** @var mixed $layout_meta_raw */
    $layout_meta_raw = get_post_meta(post_id: $id, key: 'ast-advanced-headers-layout', single: true);
    $layout_meta = is_array($layout_meta_raw) ? $layout_meta_raw : [];
    $layout = is_string($layout_meta['layout'] ?? null) ? $layout_meta['layout'] : '';

    $content = (string) astra_cap_bytes($post->post_content, limit: 32_768);

    return [
        'id' => $id,
        'title' => $post->post_title,
        'content' => $content,
        'status' => $post->post_status,
        'layout' => $layout,
        'enabled' => $post->post_status === 'publish',
        'design' => astra_object_or_map($meta_arr('ast-advanced-headers-design')),
        'location' => astra_object_or_map(astra_cap_rule_bag($meta_arr('ast-advanced-headers-location'))),
        'exclusion' => astra_object_or_map(astra_cap_rule_bag($meta_arr('ast-advanced-headers-exclusion'))),
        'users' => astra_object_or_map(astra_cap_rule_bag($meta_arr('ast-advanced-headers-users'))),
    ];
}

/**
 * Compact summary for list-page-headers. Omits the content body and the design
 * array; replaces full targeting arrays with a human-readable count string.
 *
 * @return array<string, mixed>
 */
function astra_page_header_summary(WP_Post $post): array
{
    $id = $post->ID;

    /** @var mixed $layout_meta_raw */
    $layout_meta_raw = get_post_meta(post_id: $id, key: 'ast-advanced-headers-layout', single: true);
    $layout_meta = is_array($layout_meta_raw) ? $layout_meta_raw : [];
    $layout = is_string($layout_meta['layout'] ?? null) ? $layout_meta['layout'] : '';

    /** @var mixed $location_raw */
    $location_raw = get_post_meta(post_id: $id, key: 'ast-advanced-headers-location', single: true);
    /** @var mixed $exclusion_raw */
    $exclusion_raw = get_post_meta(post_id: $id, key: 'ast-advanced-headers-exclusion', single: true);
    $location_count = is_array($location_raw) ? count($location_raw) : 0;
    $exclusion_count = is_array($exclusion_raw) ? count($exclusion_raw) : 0;
    $location_summary =
        $location_count
        . ' rule'
        . ($location_count !== 1 ? 's' : '')
        . ', '
        . $exclusion_count
        . ' exclusion'
        . ($exclusion_count !== 1 ? 's' : '');

    return [
        'id' => $id,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'layout' => $layout,
        'enabled' => $post->post_status === 'publish',
        'location_summary' => $location_summary,
    ];
}

// ---------------------------------------------------------------------------
// WooCommerce Astra Pro module allowlist (addon-gated `woocommerce` module)
// ---------------------------------------------------------------------------

/**
 * Shop archive grid, display, toolbar, and colour keys from the Astra Pro
 * WooCommerce module. All live in the `astra-settings` option bag and are
 * only meaningful when the `woocommerce` module is active.
 *
 * @return array<string, string>
 */
function ast_woo_shop_settings_map(): array
{
    return [
        'shop-style' => 'string',
        'shop-hover-style' => 'string',
        'shop-button-style' => 'string',
        'shop-product-structure' => 'array',
        'shop-page-title-display' => 'boolean',
        'shop-breadcrumb-display' => 'boolean',
        'shop-pagination' => 'string',
        'shop-pagination-style' => 'string',
        'shop-toolbar-structure' => 'array',
        'shop-toolbar-structure-with-hiddenset' => 'array',
        'shop-button-padding' => 'array',
        'shop-product-background-color' => 'string',
        'shop-product-title-color' => 'string',
        'shop-product-price-color' => 'string',
        'shop-product-content-color' => 'string',
        'shop-product-content-padding' => 'array',
        'shop-page-list-style-alignment' => 'array',
        'shop-infinite-scroll-event' => 'string',
        'shop-off-canvas-trigger-type' => 'string',
        'show-woo-grid-orders' => 'string',
    ];
}

/**
 * Single-product page (gallery, tabs, related products, colours, sale badge) keys.
 *
 * @return array<string, string>
 */
function ast_woo_product_settings_map(): array
{
    return [
        'single-product-gallery-layout' => 'string',
        'single-product-image-width' => 'integer',
        'single-product-image-zoom-effect' => 'boolean',
        'single-product-sticky-product-image' => 'boolean',
        'single-product-sticky-summary' => 'boolean',
        'single-product-tabs-layout' => 'string',
        'single-product-tabs-display' => 'boolean',
        'single-product-related-display' => 'boolean',
        'single-product-up-sells-display' => 'boolean',
        'single-product-related-upsell-grid' => 'array',
        'single-product-related-upsell-per-page' => 'integer',
        'single-product-recently-viewed-display' => 'boolean',
        'single-product-nav-style' => 'string',
        'single-product-rating-color' => 'string',
        'single-product-title-color' => 'string',
        'single-product-price-color' => 'string',
        'single-product-content-color' => 'string',
        'single-product-breadcrumb-color' => 'string',
        'single-product-breadcrumb-disable' => 'boolean',
        'single-product-add-to-cart-action' => 'string',
        'single-product-select-variations' => 'boolean',
        'single-product-structure' => 'array',
        'accordion-inside-woo-summary' => 'boolean',
        'product-gallery-thumbnail-columns' => 'integer',
        'product-sale-style' => 'string',
        'product-sale-notification' => 'string',
        'product-sale-bg-color' => 'string',
        'product-sale-color' => 'string',
    ];
}

/**
 * Cart page (layout, plus-minus buttons, cross-sells, sticky totals) keys.
 *
 * @return array<string, string>
 */
function ast_woo_cart_settings_map(): array
{
    return [
        'cart-modern-layout' => 'boolean',
        'cart-multistep-checkout' => 'boolean',
        'cart-multistep-checkout-font-case' => 'string',
        'cart-multistep-checkout-size' => 'integer',
        'cart-multistep-steps-numbers' => 'boolean',
        'cart-plus-minus-button-type' => 'string',
        'cart-sticky-cart-totals' => 'boolean',
        'cart-ajax-cart-quantity' => 'boolean',
        'cart-cross-sell-disable' => 'boolean',
        'enable-cart-upsells' => 'boolean',
        'plusminus-background-color' => 'string',
        'plusminus-text-color' => 'string',
        'plusminus-background-hover-color' => 'string',
        'plusminus-text-hover-color' => 'string',
    ];
}

/**
 * Checkout page (layout type, distraction-free, two-step, colours) keys.
 *
 * @return array<string, string>
 */
function ast_woo_checkout_settings_map(): array
{
    return [
        'checkout-layout-type' => 'string',
        'checkout-content-width' => 'string',
        'checkout-content-max-width' => 'integer',
        'checkout-distraction-free' => 'boolean',
        'checkout-coupon-display' => 'boolean',
        'checkout-order-notes-display' => 'boolean',
        'checkout-order-review-sticky' => 'boolean',
        'checkout-order-review-product-images' => 'boolean',
        'checkout-labels-as-placeholders' => 'boolean',
        'checkout-persistence-form-data' => 'boolean',
        'checkout-back-to-cart-button' => 'boolean',
        'checkout-place-order-text' => 'string',
        'checkout-modern-layout-type' => 'string',
        'checkout-modern-checkout-button-padlock' => 'boolean',
        'checkout-modern-checkout-button-price' => 'boolean',
        'two-step-checkout' => 'boolean',
        'order-summary-background-color' => 'string',
        'payment-option-content-background-color' => 'string',
    ];
}

/**
 * Product filter widget (position, accordion, max-height, active filters) keys.
 *
 * @return array<string, string>
 */
function ast_woo_filter_settings_map(): array
{
    return [
        'shop-filter-position' => 'string',
        'shop-filter-accordion' => 'boolean',
        'shop-filter-max-height' => 'integer',
        'shop-filter-scrollbar-max-height' => 'integer',
        'shop-filter-collapsable-columns' => 'array',
        'shop-filter-list-to-buttons' => 'boolean',
        'shop-filter-trigger-link' => 'string',
        'shop-filter-trigger-custom-class' => 'string',
        'shop-active-filters-display' => 'boolean',
        'shop-active-filters-sticky-sidebar' => 'boolean',
        'filter-background-color' => 'string',
    ];
}

/**
 * Quick-view popup (enable toggle, stick-cart, background colour) keys.
 *
 * @return array<string, string>
 */
function ast_woo_misc_settings_map(): array
{
    return [
        'shop-quick-view-enable' => 'string',
        'shop-quick-view-stick-cart' => 'boolean',
        'quick-view-background-color' => 'string',
    ];
}

/**
 * Full WooCommerce module allowlist — all six partition sub-maps merged.
 * Maps each key to its expected PHP type for write-side validation. Keys
 * absent from this map are reported as `unknown_keys` by write abilities
 * rather than being silently absorbed.
 *
 * All keys live inside `astra-settings`. They are only populated when the
 * Astra Pro Addon `woocommerce` module is active.
 *
 * Composed from private sub-maps to keep each map under the Halstead-volume
 * threshold of the static analyser (same pattern as astra_global_settings_allowlist).
 *
 * @return array<string, string>
 */
function astra_woo_settings_allowlist(): array
{
    return array_merge(
        ast_woo_shop_settings_map(),
        ast_woo_product_settings_map(),
        ast_woo_cart_settings_map(),
        ast_woo_checkout_settings_map(),
        ast_woo_filter_settings_map(),
        ast_woo_misc_settings_map(),
    );
}

/**
 * Return the list of `astra-settings` key names that belong to the named
 * WooCommerce module partition, or null when the partition name is invalid.
 *
 * Partition names correspond directly to the `partition` input field on
 * `astra-get-woocommerce-settings`.
 *
 * @return list<string>|null
 */
function astra_woo_partition_keys(string $partition): ?array
{
    return match ($partition) {
        'shop_grid' => array_keys(ast_woo_shop_settings_map()),
        'product_styling' => array_keys(ast_woo_product_settings_map()),
        'cart_style' => array_keys(ast_woo_cart_settings_map()),
        'checkout_layout' => array_keys(ast_woo_checkout_settings_map()),
        'filter' => array_keys(ast_woo_filter_settings_map()),
        'quick_view' => array_keys(ast_woo_misc_settings_map()),
        default => null,
    };
}

/**
 * Partition a raw WooCommerce settings patch against the WC-only allowlist.
 *
 * Delegates to `astra_partition_settings_input` with the WC allowlist as the
 * explicit allowlist argument. All validation rules (NUL strip, 64 KB cap,
 * type matching) are inherited from the shared helper — no duplication needed.
 *
 * Returns:
 *   known     — allowlisted keys with a matching value type, ready to persist
 *   unknown   — keys not in the WC allowlist (may include valid global Astra keys)
 *   mismatched — allowlisted keys whose value type did not match the declared type
 *
 * @param array<array-key, mixed> $patch
 * @return array{known: array<string, mixed>, unknown: list<string>, mismatched: list<array{key: string, expected: string, got: string}>}
 */
function astra_partition_woo_settings_input(array $patch): array
{
    return astra_partition_settings_input($patch, astra_woo_settings_allowlist());
}

/**
 * Write astra_adv_header meta values from an input array (merge semantics —
 * only keys present in `$input` are written).
 *
 * Coercion rules:
 *   layout   — string, NUL-stripped; merged as the `layout` sub-key inside the
 *              `ast-advanced-headers-layout` array, preserving all other sub-keys
 *              (breadcrumb, force-transparent-disabled, above/below-header-enabled, etc.)
 *   design   — array, full replace of ast-advanced-headers-design
 *   location, exclusion, users — array passthrough, full replace
 *
 * @param array<string, mixed> $input
 */
function astra_write_page_header_meta(int $id, array $input): void
{
    // Layout: merge-patch only the `layout` sub-key within the stored array.
    if (array_key_exists('layout', $input)) {
        $layout_val = is_string($input['layout']) ? astra_strip_nul_bytes($input['layout']) : '';
        /** @var mixed $current_raw */
        $current_raw = get_post_meta(post_id: $id, key: 'ast-advanced-headers-layout', single: true);
        $current = is_array($current_raw) ? $current_raw : [];
        $current['layout'] = $layout_val;
        update_post_meta($id, meta_key: 'ast-advanced-headers-layout', meta_value: $current);
    }

    // Design: full array replace.
    if (array_key_exists('design', $input)) {
        $design = astra_coerce_array($input['design']);
        if ($design !== null) {
            update_post_meta($id, meta_key: 'ast-advanced-headers-design', meta_value: $design);
        }
    }

    // Targeting arrays: full replace.
    $array_meta = [
        'location' => 'ast-advanced-headers-location',
        'exclusion' => 'ast-advanced-headers-exclusion',
        'users' => 'ast-advanced-headers-users',
    ];
    foreach ($array_meta as $field => $meta_key) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $arr = astra_coerce_array($input[$field]);
        if ($arr !== null) {
            update_post_meta($id, $meta_key, $arr);
        }
    }
}
