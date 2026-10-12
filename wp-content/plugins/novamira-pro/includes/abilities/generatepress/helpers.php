<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the GeneratePress abilities.
 *
 * Centralises the "read a key via the canonical API" and "merge a patch
 * onto generate_settings" plumbing so each ability stays small and so
 * the read/write contract is consistent across check-setup / get-settings
 * / set-settings.
 */

/**
 * Build a `WP_Error` with the HTTP status the REST API needs to return
 * the right 4xx code instead of falling back to 500. The Abilities API
 * routes the WP_Error through `WP_REST_Server::error_to_response()`,
 * which reads `$data['status']` to set the HTTP response code. Without
 * it, every WP_Error becomes a 500 over REST regardless of the
 * semantic. Codes map: gp_invalid_input=400, gp_not_found=404,
 * gp_confirmation_required=412, gp_no_backup=409.
 */
function gp_error(string $code, string $message): \WP_Error
{
    $status_map = [
        'gp_invalid_input' => 400,
        'gp_not_found' => 404,
        'gp_confirmation_required' => 412,
        'gp_no_backup' => 409,
    ];
    $status = $status_map[$code] ?? 400;
    return new \WP_Error($code, $message, ['status' => $status]);
}

/**
 * Normalise a value to a real PHP int. WP REST schema validation accepts
 * numeric strings ("123") as valid `integer` and forwards them to the
 * callback unchanged — passing such a value to a typed `int $id`
 * parameter triggers a TypeError. Use this on every id-like input read
 * before the typed call site.
 *
 * @param mixed $value
 */
function gp_coerce_int($value): int
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
 * Normalise a value to a real PHP bool. WP REST schema validation also
 * accepts "true" / "false" / 1 / 0 as valid `boolean` — but our callbacks
 * compare `=== true`, so string booleans silently miss. Cast here at the
 * ability boundary.
 *
 * @param mixed $value
 */
function gp_coerce_bool($value): bool
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
 * Strip NUL bytes from agent-supplied strings before they hit WP storage.
 * Used on title / key fields where a NUL would slip through `trim()` /
 * `sanitize_text_field()` and confuse downstream consumers (e.g. admin
 * list tables, JSON output truncated at the NUL).
 */
function gp_strip_nul_bytes(string $value): string
{
    return str_replace(search: "\0", replace: '', subject: $value);
}

/**
 * Invalidate GP's persistent dynamic-CSS cache. GeneratePress stores the
 * computed inline stylesheet in `generate_dynamic_css_output` (plus a
 * `_cached_version` companion) and serves it as-is on every front-end
 * request — so a settings change made outside the admin / Customizer
 * (e.g. our ability callbacks calling `update_option` directly, or
 * GP Premium's own `restore_theme_options` which does NOT invalidate)
 * would render stale CSS until the cache TTL is hit or the admin
 * "Regenerate CSS" button is clicked. Deleting the options forces
 * css-output.php to recompute on the next pageload.
 *
 * Mirrors the invalidation other GP REST endpoints already perform
 * (site-library `import_options`, `restore_content`, dashboard reset).
 */
function gp_invalidate_dynamic_css_cache(): void
{
    delete_option('generate_dynamic_css_output');
    delete_option('generate_dynamic_css_cached_version');
}

/**
 * Reads a single setting from `generate_settings` using the theme's
 * canonical accessor (applies defaults + the `generate_option` filter).
 *
 * @return mixed
 */
function gp_read_setting(string $key)
{
    if (!function_exists('generate_get_option')) {
        return null;
    }
    return generate_get_option($key);
}

/**
 * Returns the union of the theme defaults — used by `set-settings` to
 * decide whether a key is "known" (warning on unknown, write still
 * succeeds — the permissive surface we picked). Includes the per-section
 * default sets so add-on packages that filter into `generate_option_defaults`
 * still register.
 *
 * @return array<string, mixed>
 */
function gp_known_defaults(): array
{
    $out = [];
    if (function_exists('generate_get_defaults')) {
        $out = array_merge($out, generate_get_defaults());
    }
    if (function_exists('generate_get_color_defaults')) {
        $out = array_merge($out, generate_get_color_defaults());
    }
    if (function_exists('generate_get_default_fonts')) {
        $out = array_merge($out, generate_get_default_fonts());
    }
    if (function_exists('generate_spacing_get_defaults')) {
        $out = array_merge($out, generate_spacing_get_defaults());
    }
    return $out;
}

/**
 * Splits an incoming settings patch into (known, unknown) keys against
 * the union of theme defaults. The caller writes the merged result and
 * surfaces the unknown list as a `warnings` field on the response —
 * write still succeeds (permissive surface).
 *
 * @param array<string, mixed> $patch
 * @return array{known: array<string, mixed>, unknown: list<string>}
 */
function gp_partition_known_keys(array $patch): array
{
    $defaults = gp_known_defaults();
    $known = [];
    $unknown = [];
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        if (array_key_exists($key, $defaults)) {
            $known[$key] = $value;
            continue;
        }
        $unknown[] = $key;
    }
    return ['known' => $known, 'unknown' => $unknown];
}

/**
 * Loads the GP Premium Elements hook catalogue (header / nav / content /
 * footer / wc, …). Falls back to an empty array when the Elements module
 * is not active. Returns the array as-is — callers reshape for output.
 *
 * @return array<string, array<string, mixed>>
 */
function gp_load_available_hooks(): array
{
    if (!gp_elements_active()) {
        return [];
    }
    if (!class_exists('GeneratePress_Elements_Helper')) {
        return [];
    }
    return \GeneratePress_Elements_Helper::get_available_hooks();
}

// ---------------------------------------------------------------------------
// Elements (Group B) helpers
// ---------------------------------------------------------------------------

/**
 * The element types the integration accepts. Mirrors the values used by
 * the plugin's metabox (class-metabox.php:245). `hook` and `block` are
 * the live shapes; `header` and `layout` are legacy / specialised.
 *
 * @return list<string>
 */
function gp_valid_element_types(): array
{
    return ['hook', 'header', 'layout', 'block'];
}

/**
 * The block_type values accepted when `type=block`. Sourced from the
 * GP Premium metabox dropdown (class-metabox.php — see the rendered
 * <select name="_generate_block_type">). Includes `hook` because a Block
 * Element can also be addressed as a hook target.
 *
 * @return list<string>
 */
function gp_valid_block_types(): array
{
    return [
        'site-header',
        'site-footer',
        'right-sidebar',
        'left-sidebar',
        'content-template',
        'loop-template',
        'search-modal',
        'page-hero',
        'post-meta-template',
        'post-navigation-template',
        'archive-navigation-template',
        'hook',
    ];
}

/**
 * Loads a `gp_elements` post by id. Returns null when the id does not
 * exist or points at a different post type — saves every caller a
 * `get_post()` + `post_type` check.
 */
function gp_load_element(int $id): ?\WP_Post
{
    if ($id <= 0) {
        return null;
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof \WP_Post) {
        return null;
    }
    if ($post->post_type !== 'gp_elements') {
        return null;
    }
    if ($post->post_status === 'trash') {
        // Treat trashed elements as gone — delete-element calls into here
        // and the trash branch needs the gp_not_found short-circuit on a
        // second invocation to keep its `idempotent: true` annotation honest.
        return null;
    }
    return $post;
}

/**
 * Reads every meta key the integration cares about and returns the
 * decoded snapshot. Intentionally OMITS `_generate_hook_execute_php` —
 * exposing arbitrary PHP execution to AI agents is a code-injection
 * vector we never want. The metabox-UI lets a human site-owner flip
 * that flag; an agent has no business reading or writing it.
 *
 * @return array<string, mixed>
 */
function gp_element_to_array(\WP_Post $post): array
{
    $id = $post->ID;
    $meta = static fn(string $k): string => (string) get_post_meta(post_id: $id, key: $k, single: true);
    $bool = static fn(string $k): bool => 'true' === get_post_meta(post_id: $id, key: $k, single: true);
    $type = $meta('_generate_element_type');
    // Block Elements live in post_content; Hook (and legacy header / layout)
    // Elements live in the _generate_element_content postmeta. Match the
    // plugin's renderer so a roundtrip get/edit/create stays consistent.
    $content = $type === 'block' ? $post->post_content : $meta('_generate_element_content');
    return [
        'id' => $id,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'type' => $type,
        'block_type' => $meta('_generate_block_type'),
        'content' => $content,
        'hook' => $meta('_generate_hook'),
        'custom_hook' => $meta('_generate_custom_hook'),
        'priority' => (int) get_post_meta(post_id: $id, key: '_generate_hook_priority', single: true),
        'execute_shortcodes' => $bool('_generate_hook_execute_shortcodes'),
        'disable_site_header' => $bool('_generate_hook_disable_site_header'),
        'disable_site_footer' => $bool('_generate_hook_disable_site_footer'),
        'display_conditions' => gp_read_conditions_meta(post_id: $id, meta_key: '_generate_element_display_conditions'),
        'exclude_conditions' => gp_read_conditions_meta(post_id: $id, meta_key: '_generate_element_exclude_conditions'),
        'user_conditions' => gp_read_conditions_meta(post_id: $id, meta_key: '_generate_element_user_conditions'),
    ];
}

/**
 * Compact summary used by list-elements — drops `content` (can be long
 * raw HTML / shortcode) and replaces full condition arrays with counts.
 * Use get-element on a specific id for the full payload.
 *
 * @return array<string, mixed>
 */
function gp_element_summary(\WP_Post $post): array
{
    $id = $post->ID;
    $display = gp_read_conditions_meta(post_id: $id, meta_key: '_generate_element_display_conditions');
    $exclude = gp_read_conditions_meta(post_id: $id, meta_key: '_generate_element_exclude_conditions');
    $user = gp_read_conditions_meta(post_id: $id, meta_key: '_generate_element_user_conditions');
    return [
        'id' => $id,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'type' => (string) get_post_meta(post_id: $id, key: '_generate_element_type', single: true),
        'block_type' => (string) get_post_meta(post_id: $id, key: '_generate_block_type', single: true),
        'hook' => (string) get_post_meta(post_id: $id, key: '_generate_hook', single: true),
        'priority' => (int) get_post_meta(post_id: $id, key: '_generate_hook_priority', single: true),
        'modified' => $post->post_modified,
        'conditions_count' => [
            'display' => count($display),
            'exclude' => count($exclude),
            'user' => count($user),
        ],
    ];
}

/**
 * Reads a conditions meta key and normalises to `list<array{rule: string, object: string}>`.
 * The plugin stores display / exclude conditions as numerically-indexed
 * arrays of `{rule, object}` tuples, but USER conditions as a flat list
 * of rule strings (the evaluator does array_intersect on flat rules).
 * Accept both inbound shapes and normalise to the tuple form for the
 * public ability output.
 *
 * @return list<array{rule: string, object: string}>
 */
function gp_read_conditions_meta(int $post_id, string $meta_key): array
{
    /** @var mixed $raw */
    $raw = get_post_meta(post_id: $post_id, key: $meta_key, single: true);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $row */
    foreach ($raw as $row) {
        if (is_string($row)) {
            // Flat user_conditions row — re-wrap as tuple for the output.
            if ($row !== '') {
                $out[] = ['rule' => $row, 'object' => ''];
            }
            continue;
        }
        if (!is_array($row)) {
            continue;
        }
        $out[] = [
            'rule' => (string) ($row['rule'] ?? ''),
            'object' => (string) ($row['object'] ?? ''),
        ];
    }
    return $out;
}

/**
 * Applies the element field patch onto an existing gp_elements post.
 * Reads the keys the caller actually passed (omitted = unchanged) and
 * mirrors the plugin's save flow (string casts, "true"/"" storage for
 * the bool meta). NEVER writes `_generate_hook_execute_php`.
 *
 * @param array<string, mixed> $input
 */
function gp_apply_element_meta(int $post_id, array $input): void
{
    gp_apply_element_string_meta($post_id, $input);
    gp_apply_element_priority_meta($post_id, $input);
    gp_apply_element_bool_meta($post_id, $input);
}

/**
 * Persists the string-valued meta fields (type / block_type / content /
 * hook / custom_hook) from the input. Keys not present are left alone.
 *
 * @param array<string, mixed> $input
 */
function gp_apply_element_string_meta(int $post_id, array $input): void
{
    // Resolve the effective type: prefer the patch, fall back to the
    // currently stored value (relevant for edit-element where `type` may
    // be omitted). Drives the content-routing decision below.
    $previous_type = (string) get_post_meta(post_id: $post_id, key: '_generate_element_type', single: true);
    $effective_type = isset($input['type']) && is_string($input['type']) ? $input['type'] : $previous_type;

    // Type transition (hook → block or block → hook) must clear the OTHER
    // content storage — otherwise the dormant value resurfaces if the
    // element is later switched back. class-hooks.php:122 reads the meta,
    // class-block.php:229 reads post_content; they must not both hold
    // content for the same element.
    if ($previous_type !== '' && $previous_type !== $effective_type) {
        if ($effective_type === 'block' && $previous_type !== 'block') {
            delete_post_meta(post_id: $post_id, meta_key: '_generate_element_content');
        }
        if ($previous_type === 'block' && $effective_type !== 'block') {
            wp_update_post(['ID' => $post_id, 'post_content' => '']);
        }
    }

    $map = [
        'type' => '_generate_element_type',
        'block_type' => '_generate_block_type',
        'content' => '_generate_element_content',
        'hook' => '_generate_hook',
        'custom_hook' => '_generate_custom_hook',
    ];
    foreach ($map as $field => $meta_key) {
        if (!array_key_exists($field, $input) || !is_string($input[$field])) {
            continue;
        }
        // Block Elements render from post_content (see class-block.php:229
        // — `$content .= $block_element->post_content`). Route the
        // `content` field to wp_update_post for block types and skip the
        // postmeta write to avoid duplicating storage.
        if ($field === 'content' && $effective_type === 'block') {
            wp_update_post(['ID' => $post_id, 'post_content' => $input[$field]]);
            continue;
        }
        update_post_meta(post_id: $post_id, meta_key: $meta_key, meta_value: $input[$field]);
    }
}

/**
 * Persists the integer-valued meta (priority) from the input. Stored as
 * the cast string the plugin's metabox writes.
 *
 * @param array<string, mixed> $input
 */
function gp_apply_element_priority_meta(int $post_id, array $input): void
{
    if (!array_key_exists('priority', $input) || !is_int($input['priority'])) {
        return;
    }
    update_post_meta(post_id: $post_id, meta_key: '_generate_hook_priority', meta_value: (string) $input['priority']);
}

/**
 * Persists the boolean-valued meta from the input. The plugin stores
 * `"true"` / `""` (not raw bool), so we mirror that.
 *
 * @param array<string, mixed> $input
 */
function gp_apply_element_bool_meta(int $post_id, array $input): void
{
    $map = [
        'execute_shortcodes' => '_generate_hook_execute_shortcodes',
        'disable_site_header' => '_generate_hook_disable_site_header',
        'disable_site_footer' => '_generate_hook_disable_site_footer',
    ];
    foreach ($map as $field => $meta_key) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        // Accept string booleans ("true"/"false") in addition to real bools
        // because the WP REST schema layer happily forwards string forms.
        $value = gp_coerce_bool($input[$field]) ? 'true' : '';
        update_post_meta(post_id: $post_id, meta_key: $meta_key, meta_value: $value);
    }
}

/**
 * Validates the type / block_type / hook combination an agent submitted.
 * Returns an error message string when the combination is invalid, or
 * null when it passes. Same shape both create-element and edit-element
 * use so the rejection wording is consistent.
 *
 * @param array<string, mixed> $input
 */
function gp_validate_element_shape(array $input): ?string
{
    /** @var mixed $type */
    $type = $input['type'] ?? null;
    if ($type === null) {
        return null;
    }
    if (!is_string($type) || !in_array($type, gp_valid_element_types(), strict: true)) {
        return sprintf(
            'Unknown element type "%s". Valid types: %s.',
            is_scalar($type) ? (string) $type : gettype($type),
            implode(', ', gp_valid_element_types()),
        );
    }
    if ($type === 'block') {
        /** @var mixed $block_type */
        $block_type = $input['block_type'] ?? null;
        if (!is_string($block_type) || $block_type === '') {
            return 'When `type=block`, `block_type` is required. Use code-snippets-style discovery: call any get-element on a known block to see the value, or check gp_valid_block_types() in the integration source.';
        }
        if (!in_array($block_type, gp_valid_block_types(), strict: true)) {
            return sprintf(
                'Unknown block_type "%s". Valid block types: %s.',
                $block_type,
                implode(', ', gp_valid_block_types()),
            );
        }
    }
    if ($type === 'hook') {
        // hook is REQUIRED when type=hook (was conditional `array_key_exists`
        // before, which let create-element silently produce Hook Elements
        // with no `_generate_hook` meta — those would never fire on the
        // frontend). edit-element passes the validator a `$merged` payload
        // pre-resolved against postmeta so an unchanged hook on a hook→hook
        // edit still satisfies this check.
        /** @var mixed $hook */
        $hook = $input['hook'] ?? null;
        if (!is_string($hook) || $hook === '') {
            return 'When `type=hook`, `hook` must be a non-empty action name (or "custom" with `custom_hook` supplied).';
        }
    }
    return null;
}

/**
 * Whitelists a conditions array submitted by the agent into the
 * `list<array{rule: string, object: string}>` storage shape. Drops
 * rows missing `rule`. Used by set-element-conditions.
 *
 * @param mixed $raw
 * @return list<array{rule: string, object: string}>
 */
function gp_normalise_conditions_input($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $row */
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rule = isset($row['rule']) && is_string($row['rule']) ? $row['rule'] : '';
        if ($rule === '') {
            continue;
        }
        $object = '';
        if (isset($row['object']) && is_scalar($row['object'])) {
            $object = (string) $row['object'];
        }
        $out[] = ['rule' => $rule, 'object' => $object];
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Site Library (Group C) helpers
// ---------------------------------------------------------------------------

/**
 * The apply-site steps the ability dispatches and the GP Premium REST
 * route each maps to. Order matters: theme_options first (so subsequent
 * imports can rely on the new option set), plugins next, then content,
 * then site_options (element locations / reading pages), and finally
 * widgets.
 *
 * @return array<string, string>
 */
function gp_site_steps_map(): array
{
    return [
        'theme_options' => 'import_theme_options',
        'plugins' => 'activate_plugins',
        'content' => 'import_content',
        'site_options' => 'import_site_options',
        'widgets' => 'import_widgets',
    ];
}

/**
 * Default apply-site step list. Excludes `plugins` — installing plugins
 * is the riskier path (plugin downloads from wp.org, code execution on
 * activation) and we leave it opt-in.
 *
 * @return list<string>
 */
function gp_site_default_steps(): array
{
    return ['theme_options', 'content', 'site_options', 'widgets'];
}

/**
 * Compact row used by list-sites — drops fields that are only relevant
 * to the WP admin browser (`image_width` / `image_height`) and leaves the
 * keys the agent actually needs to pick / apply a template.
 *
 * @param array<string, mixed> $site
 * @return array<string, mixed>
 */
function gp_site_summary(array $site): array
{
    $string = static fn(string $key): string => isset($site[$key]) && is_string($site[$key]) ? $site[$key] : '';
    return [
        'name' => $string('name'),
        // Upstream catalogue (gpsites.co + sites.generatepress.com) returns
        // `category` and `page_builder` as either a string ("Business") or
        // a list (["Business", "Agency"]). Normalise to a list so the agent
        // can iterate uniformly — and so the filter logic actually matches.
        'category' => gp_normalise_string_list(site: $site, key: 'category'),
        'description' => $string('description'),
        'page_builder' => gp_normalise_string_list(site: $site, key: 'page_builder'),
        'preview_url' => $string('preview_url'),
        'author_name' => $string('author_name'),
        'min_theme_version' => $string('min_theme_version'),
        // Upstream stores `plugins` as a JSON-encoded string ('{"name":"slug",…}')
        // or sometimes a real array. Decode either way into a `{name => slug}`
        // map the agent can read.
        'plugins' => gp_normalise_site_plugins($site),
        'directory' => $string('directory'),
    ];
}

/**
 * Read a field that the upstream Site Library catalogue stores as either
 * a single string or a list of strings (category, page_builder). Always
 * returns a flat `list<string>` for the public ability shape.
 *
 * @param array<string, mixed> $site
 * @return list<string>
 */
function gp_normalise_string_list(array $site, string $key): array
{
    /** @var mixed $raw */
    $raw = $site[$key] ?? null;
    if (is_string($raw)) {
        $trimmed = trim($raw);
        return $trimmed === '' ? [] : [$trimmed];
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $item */
    foreach ($raw as $item) {
        if (!is_string($item) || trim($item) === '') {
            continue;
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * Read the `plugins` field. Upstream sometimes JSON-encodes the
 * `{name => slug}` map as a string (`'{"GenerateBlocks Pro":"…"}'`) —
 * decode when needed, accept the array shape when it's already there.
 *
 * @param array<string, mixed> $site
 * @return array<string, string>
 */
function gp_normalise_site_plugins(array $site): array
{
    /** @var mixed $raw */
    $raw = $site['plugins'] ?? null;
    if (is_string($raw) && $raw !== '') {
        /** @var mixed $decoded */
        $decoded = json_decode($raw, associative: true);
        if (is_array($decoded)) {
            $raw = $decoded;
        }
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $name => $value) {
        if (!is_string($name) || !is_string($value)) {
            continue;
        }
        $out[$name] = $value;
    }
    return $out;
}

/**
 * Filter a Site Library catalogue (`name => {…}` map) by category and/or
 * page_builder. Both filters case-insensitive; pass null to skip a filter.
 * Lifted out of generatepress_list_sites to keep that callback under the
 * cyclomatic-complexity threshold.
 *
 * @param array<string, mixed> $sites
 * @return list<array<string, mixed>>
 */
function gp_filter_sites_catalogue(array $sites, ?string $category, ?string $builder): array
{
    $rows = [];
    /** @var mixed $site */
    foreach ($sites as $site) {
        if (!is_array($site)) {
            continue;
        }
        /** @var array<string, mixed> $site */
        // category / page_builder upstream can be string OR list — match
        // case-insensitively against any element.
        if ($category !== null && !gp_field_contains_value(site: $site, key: 'category', needle: $category)) {
            continue;
        }
        if ($builder !== null && !gp_field_contains_value(site: $site, key: 'page_builder', needle: $builder)) {
            continue;
        }
        $rows[] = gp_site_summary($site);
    }
    return $rows;
}

/**
 * Case-insensitive containment check against a Site Library field that
 * can be a single string or a list of strings.
 *
 * @param array<string, mixed> $site
 */
function gp_field_contains_value(array $site, string $key, string $needle): bool
{
    $needle = strtolower($needle);
    foreach (gp_normalise_string_list(site: $site, key: $key) as $candidate) {
        if (strtolower($candidate) === $needle) {
            return true;
        }
    }
    return false;
}

/**
 * Forces a refresh of the `generatepress_sites` cached catalogue by
 * dispatching the upstream REST endpoint with `forceRefresh=true`. The
 * GP Premium handler writes the option as a side effect — we throw the
 * response away and re-read the option afterwards. Keeps the contract
 * with the plugin in one place (no parallel fetch implementation).
 */
function gp_force_refresh_sites_catalogue(): void
{
    $request = new \WP_REST_Request('GET', '/generatepress-site-library/v1/get_sites');
    $request->set_query_params(['forceRefresh' => true]);
    rest_do_request($request);
}

/**
 * Looks up a Site Library entry by `name` from the cached catalogue.
 * Returns null when the entry is missing — the catalogue is keyed by
 * `name` upstream, so a direct lookup is enough.
 *
 * @return array<string, mixed>|null
 */
function gp_find_site(string $name): ?array
{
    /** @var mixed $sites */
    $sites = get_option('generatepress_sites', []);
    if (!is_array($sites) || !isset($sites[$name]) || !is_array($sites[$name])) {
        return null;
    }
    // @mago-expect analysis:less-specific-return-statement
    return $sites[$name];
}

/**
 * Probe the remote `options.json` / `content.xml` / `widgets.wie` files
 * for a given site directory. Returns booleans only — the agent uses
 * this to know which steps will succeed without downloading the assets.
 *
 * @return array{has_options: bool, has_content: bool, has_widgets: bool}
 */
function gp_site_asset_presence(string $directory): array
{
    if ($directory === '' || !class_exists('GeneratePress_Site_Library_Helper')) {
        return ['has_options' => false, 'has_content' => false, 'has_widgets' => false];
    }
    // Upstream `directory` may or may not carry a trailing slash — normalise
    // so we never emit URLs like `…/trainer//options.json` (servers happen to
    // tolerate them today but it's fragile).
    $base = rtrim($directory, characters: '/');
    return [
        'has_options' => \GeneratePress_Site_Library_Helper::file_exists($base . '/options.json'),
        'has_content' => \GeneratePress_Site_Library_Helper::file_exists($base . '/content.xml'),
        'has_widgets' => \GeneratePress_Site_Library_Helper::file_exists($base . '/widgets.wie'),
    ];
}

/**
 * Dispatch one apply-site step via the GP Premium REST API. Generates
 * a wp_rest nonce server-side and sets it on the request — the upstream
 * `verify_nonce()` accepts it because we are still inside the same
 * authenticated session (novamira_permission_callback already required
 * manage_options). Returns the response on success, an error string on
 * failure.
 *
 * @param array<string, mixed> $site_data
 * @return string|null Error message, or null when the step succeeded.
 */
function gp_dispatch_site_step(string $route, array $site_data): ?string
{
    $request = new \WP_REST_Request('POST', '/generatepress-site-library/v1/' . $route);
    $request->set_body_params(['siteData' => $site_data]);
    $request->set_header('X-WP-Nonce', (string) wp_create_nonce('wp_rest'));
    $response = rest_do_request($request);

    if ($response->is_error()) {
        $err = $response->as_error();
        if ($err === null) {
            return 'step_failed: unknown error';
        }
        return $err->get_error_code() . ': ' . $err->get_error_message();
    }

    /** @var mixed $data */
    $data = $response->get_data();
    if (is_array($data) && isset($data['success']) && $data['success'] === false) {
        $message = isset($data['response']) && is_string($data['response']) ? $data['response'] : 'unknown';
        return 'step_failed: ' . $message;
    }
    return null;
}

// ---------------------------------------------------------------------------
// Font Library (Group D) helpers
// ---------------------------------------------------------------------------

/**
 * Remove the empty per-font directory GP Premium's upload-fonts endpoint
 * creates when it tries (and fails) to download a non-existent Google
 * Family. Without this cleanup, repeated failed calls leave phantom
 * folders under wp-content/uploads/generatepress/fonts/.
 */
function gp_cleanup_failed_font_dir(string $slug): void
{
    if ($slug === '') {
        return;
    }
    $uploads = wp_get_upload_dir();
    $base = isset($uploads['basedir']) && is_string($uploads['basedir']) ? $uploads['basedir'] : '';
    if ($base === '') {
        return;
    }
    $dir = trailingslashit($base) . 'generatepress/fonts/' . $slug . '/';
    if (
        class_exists('GeneratePress_Pro_Font_Library')
        && method_exists('GeneratePress_Pro_Font_Library', 'delete_directory')
        && file_exists($dir)
    ) {
        \GeneratePress_Pro_Font_Library::delete_directory($dir);
    }
}

/**
 * Convert a list of Google Font variant strings (`400`, `400italic`, `700i`)
 * into the rich shape the GP Premium /optimize-google-fonts/ endpoint
 * expects (`{fontFamily, fontStyle, fontWeight, src}`). `src` is left
 * empty — the upstream endpoint resolves the actual Google CDN URLs.
 *
 * @param list<string> $variants
 * @return list<array<string, mixed>>
 */
function gp_format_google_font_variants(string $family, array $variants): array
{
    $out = [];
    foreach ($variants as $raw) {
        $variant = trim(strtolower($raw));
        if ($variant === '') {
            continue;
        }
        $italic = str_contains($variant, 'italic') || str_ends_with($variant, 'i');
        $weight_part = preg_replace(pattern: '/[^0-9]/', replacement: '', subject: $variant);
        $weight = $weight_part === '' || $weight_part === null ? '400' : $weight_part;
        $out[] = [
            'fontFamily' => $family,
            'fontStyle' => $italic ? 'italic' : 'normal',
            'fontWeight' => $weight,
            'src' => '',
        ];
    }
    if ($out === []) {
        $out[] = ['fontFamily' => $family, 'fontStyle' => 'normal', 'fontWeight' => '400', 'src' => ''];
    }
    return $out;
}

/**
 * Dispatch a Font Library REST step (`optimize-google-fonts`, `upload-fonts`,
 * `delete-font`) via rest_do_request. Mirrors gp_dispatch_site_step — the
 * nonce is generated server-side because the caller is already authenticated
 * via novamira_permission_callback. Returns a `{error: ?string, data: mixed}`
 * tuple — error is null on success.
 *
 * @param array<string, mixed> $params
 * @return array{error: ?string, data: mixed}
 */
function gp_dispatch_font_step(string $route, array $params): array
{
    $request = new \WP_REST_Request('POST', '/generatepress-font-library/v1/' . $route);
    $request->set_body_params($params);
    $request->set_header('X-WP-Nonce', (string) wp_create_nonce('wp_rest'));
    $response = rest_do_request($request);

    if ($response->is_error()) {
        $err = $response->as_error();
        $msg = $err === null ? 'step_failed: unknown error' : $err->get_error_code() . ': ' . $err->get_error_message();
        return ['error' => $msg, 'data' => null];
    }

    /** @var mixed $data */
    $data = $response->get_data();
    if (is_array($data) && isset($data['success']) && $data['success'] === false) {
        $message = isset($data['response']) && is_string($data['response']) ? $data['response'] : 'unknown';
        return ['error' => 'step_failed: ' . $message, 'data' => $data];
    }
    return ['error' => null, 'data' => $data];
}

// ---------------------------------------------------------------------------
// Modules / CSS utility (Group E) helpers
// ---------------------------------------------------------------------------

/**
 * Module catalogue: short slug → (option name, constant name, display
 * label). Slugs match the per-module accessor names so list-modules row
 * keys line up with the gates in runtime.php.
 *
 * @return array<string, array{option: string, constant: string, label: string}>
 */
/**
 * Boot a WP_Customize_Manager instance + trigger `customize_register` so
 * every theme + plugin registers its sections / controls. CLI / REST
 * requests don't normally hit the Customizer init path, so we do it
 * ourselves on demand. Returns null when the manager class is missing
 * (extremely defensive — class ships with core WP).
 */
function gp_bootstrap_customize_manager(): ?\WP_Customize_Manager
{
    if (!class_exists('WP_Customize_Manager')) {
        require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
    }
    if (!class_exists('WP_Customize_Manager')) {
        return null;
    }
    global $wp_customize;
    if (!$wp_customize instanceof \WP_Customize_Manager) {
        $wp_customize = new \WP_Customize_Manager();
        do_action('customize_register', $wp_customize);
    }
    return $wp_customize;
}

/**
 * Walks the registered Customizer sections and produces the public
 * ability shape. Honours the same `section` filter (single section id)
 * and `scope` (`gp_only` | `all`) the get-customizer-schema ability
 * accepts.
 *
 * @return list<array<string, mixed>>
 */
function gp_collect_customizer_sections(\WP_Customize_Manager $manager, string $scope, string $section_filter): array
{
    $out = [];
    /** @var \WP_Customize_Section $section */
    foreach ($manager->sections() as $section) {
        if ($section_filter !== '' && $section->id !== $section_filter) {
            continue;
        }
        if ($scope === 'gp_only' && !gp_is_generatepress_section($section->id)) {
            continue;
        }
        $out[] = [
            'id' => $section->id,
            'title' => $section->title,
            'panel' => $section->panel,
            'priority' => $section->priority,
        ];
    }
    return $out;
}

/**
 * Walks the registered Customizer controls and produces the public
 * ability shape. `$control->setting` can be a `WP_Customize_Setting`
 * instance OR a bare string id (the latter is what title / placeholder
 * controls bind to) — we accept both.
 *
 * @return list<array<string, mixed>>
 */
function gp_collect_customizer_controls(\WP_Customize_Manager $manager, string $scope, string $section_filter): array
{
    $gp_sections = gp_known_gp_section_ids($manager);
    $out = [];
    /** @var \WP_Customize_Control $control */
    foreach ($manager->controls() as $control) {
        if ($section_filter !== '' && $control->section !== $section_filter) {
            continue;
        }
        $setting_id = gp_resolve_customizer_setting_id($control->setting);
        $settings_map = gp_resolve_customizer_settings_map($control->settings);
        // Pull a usable setting id from the multi-setting map when the
        // primary binding came back blank (responsive controls etc.).
        if ($setting_id === '' && $settings_map !== []) {
            $setting_id = $settings_map['default'] ?? $settings_map['desktop'] ?? array_values($settings_map)[0];
        }
        if ($scope === 'gp_only') {
            $belongs =
                gp_is_generatepress_setting($control->id, $setting_id)
                || gp_is_generatepress_settings_map($settings_map)
                || in_array($control->section, $gp_sections, strict: true);
            if (!$belongs) {
                continue;
            }
        }
        $out[] = [
            'id' => $control->id,
            'setting' => $setting_id,
            'settings' => $settings_map,
            'label' => $control->label,
            'type' => $control->type,
            'choices' => $control->choices,
            'section' => $control->section,
            'priority' => $control->priority,
        ];
    }
    return $out;
}

/**
 * Set of section ids that belong to the GP namespace per id-prefix. Used
 * by gp_collect_customizer_controls to retain controls whose own id does
 * NOT carry a generate_/gp_ prefix but live inside a GP section (e.g.
 * `title_tagline → hide_title`).
 *
 * @return list<string>
 */
function gp_known_gp_section_ids(\WP_Customize_Manager $manager): array
{
    $out = [];
    /** @var \WP_Customize_Section $section */
    foreach ($manager->sections() as $section) {
        if (!gp_is_generatepress_section($section->id)) {
            continue;
        }
        $out[] = $section->id;
    }
    return $out;
}

/**
 * GP-ownership predicate for the multi-setting map — true when ANY id in
 * the map carries the generate_/gp_ prefix.
 *
 * @param array<string, string> $settings_map
 */
function gp_is_generatepress_settings_map(array $settings_map): bool
{
    foreach ($settings_map as $sid) {
        if (str_starts_with($sid, 'generate_') || str_starts_with($sid, 'gp_')) {
            return true;
        }
    }
    return false;
}

/**
 * Resolve the `setting` field of a WP_Customize_Control to a stable id
 * string. The field can be either a Setting object (`->id`) or a raw
 * string id, depending on the control type — both are accepted.
 *
 * @param mixed $setting
 */
function gp_resolve_customizer_setting_id($setting): string
{
    if ($setting instanceof \WP_Customize_Setting) {
        return $setting->id;
    }
    // Controls without a single bound setting expose the literal string
    // 'default' (the selector key into $control->settings). That's not a
    // usable setting id — return '' so the agent doesn't try to write to
    // a non-existent option. The richer multi-setting list is exposed via
    // gp_resolve_customizer_settings_map().
    if (is_string($setting) && $setting !== 'default') {
        return $setting;
    }
    return '';
}

/**
 * Some Customizer controls (range sliders, responsive groups) bind
 * multiple settings under `$control->settings` keyed by viewport (`desktop`,
 * `tablet`, `mobile`) or panel position. Surface the full map so the
 * agent can pick the right id for set-settings.
 *
 * @param mixed $settings
 * @return array<string, string>
 */
function gp_resolve_customizer_settings_map($settings): array
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
 * GP-ownership predicate for sections — true when the id is the
 * conventional GeneratePress / GP Premium prefix.
 */
function gp_is_generatepress_section(string $id): bool
{
    return str_starts_with($id, 'generate_') || str_starts_with($id, 'gp_');
}

/**
 * GP-ownership predicate for controls — true when the control or its
 * bound setting belongs to the GP namespace (id prefix or setting bound
 * to `generate_settings[…]` / `generate_*` option).
 */
function gp_is_generatepress_setting(string $control_id, string $setting_id): bool
{
    if (str_starts_with($control_id, 'generate_') || str_starts_with($control_id, 'gp_')) {
        return true;
    }
    if (str_starts_with($setting_id, 'generate_') || str_starts_with($setting_id, 'gp_')) {
        return true;
    }
    return false;
}

/**
 * Resolve the typography CSS stream from GP Premium's Typography module.
 * The function is only defined when the module is active AND its bootstrap
 * file has loaded — return '' otherwise so the get-css-output ability can
 * still report the (empty) stream uniformly.
 */
function gp_resolve_typography_css(): string
{
    if (!function_exists('generate_typography_get_premium_css')) {
        return '';
    }
    return generate_typography_get_premium_css();
}

function gp_modules_catalogue(): array
{
    return [
        'backgrounds' => [
            'option' => 'generate_package_backgrounds',
            'constant' => 'GENERATE_BACKGROUNDS',
            'label' => 'Backgrounds',
        ],
        'blog' => ['option' => 'generate_package_blog', 'constant' => 'GENERATE_BLOG', 'label' => 'Blog'],
        'colors' => [
            'option' => 'generate_package_colors',
            'constant' => 'GENERATE_COLORS',
            'label' => 'Colors',
        ],
        'copyright' => [
            'option' => 'generate_package_copyright',
            'constant' => 'GENERATE_COPYRIGHT',
            'label' => 'Copyright',
        ],
        'disable_elements' => [
            'option' => 'generate_package_disable_elements',
            'constant' => 'GENERATE_DISABLE_ELEMENTS',
            'label' => 'Disable Elements',
        ],
        'elements' => [
            'option' => 'generate_package_elements',
            'constant' => 'GENERATE_ELEMENTS',
            'label' => 'Elements',
        ],
        'hooks' => ['option' => 'generate_package_hooks', 'constant' => 'GENERATE_HOOKS', 'label' => 'Hooks'],
        'menu_plus' => [
            'option' => 'generate_package_menu_plus',
            'constant' => 'GENERATE_MENU_PLUS',
            'label' => 'Menu Plus',
        ],
        'page_header' => [
            'option' => 'generate_package_page_header',
            'constant' => 'GENERATE_PAGE_HEADER',
            'label' => 'Page Header',
        ],
        'secondary_nav' => [
            'option' => 'generate_package_secondary_nav',
            'constant' => 'GENERATE_SECONDARY_NAV',
            'label' => 'Secondary Nav',
        ],
        'sections' => [
            'option' => 'generate_package_sections',
            'constant' => 'GENERATE_SECTIONS',
            'label' => 'Sections',
        ],
        'spacing' => ['option' => 'generate_package_spacing', 'constant' => 'GENERATE_SPACING', 'label' => 'Spacing'],
        'typography' => [
            'option' => 'generate_package_typography',
            'constant' => 'GENERATE_TYPOGRAPHY',
            'label' => 'Typography',
        ],
        'woocommerce' => [
            'option' => 'generate_package_woocommerce',
            'constant' => 'GENERATE_WOOCOMMERCE',
            'label' => 'WooCommerce',
        ],
        'site_library' => [
            'option' => 'generate_package_site_library',
            'constant' => 'GENERATE_SITE_LIBRARY',
            'label' => 'Site Library',
        ],
        'font_library' => [
            'option' => 'generate_package_font_library',
            'constant' => 'GENERATE_FONT_LIBRARY',
            'label' => 'Font Library',
        ],
    ];
}
