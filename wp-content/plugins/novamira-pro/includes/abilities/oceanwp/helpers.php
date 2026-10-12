<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Maximum number of items returned by collection-style endpoints. Caller
 * may set `limit` lower; we clamp to this so a malicious or unaware caller
 * cannot dump an enormous payload through the agent.
 */
const OW_LIST_HARD_MAX = 500;

/**
 * Maximum bytes a single text-style value (e.g. `ocean_custom_css`) is
 * returned at when included inline. Caller can opt-in to a higher cap via
 * `max_field_bytes` on read endpoints; the default keeps `get-settings`
 * compact for the agent.
 */
const OW_FIELD_BYTES_DEFAULT = 4096;

// ---------------------------------------------------------------------------
// Detection — theme + Ocean Extra companion. Both static-cached per request.
// ---------------------------------------------------------------------------

/**
 * Snapshot of OceanWP theme detection. Static-cached per request.
 *
 * @return array{active: bool, version: string, min_supported: string, min_satisfied: bool}
 */
function ow_detect_setup(): array
{
    /** @var array{active: bool, version: string, min_supported: string, min_satisfied: bool}|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $min = NOVAMIRA_PRO_OCEANWP_THEME_MIN_VERSION;
    if (!defined('OCEANWP_THEME_VERSION') || !function_exists('oceanwp_post_layout')) {
        return ['active' => false, 'version' => '', 'min_supported' => $min, 'min_satisfied' => false];
    }
    $version = (string) constant('OCEANWP_THEME_VERSION');
    return [
        'active' => true,
        'version' => $version,
        'min_supported' => $min,
        'min_satisfied' => version_compare($version, $min, operator: '>='),
    ];
}

/**
 * Snapshot of Ocean Extra companion plugin detection. Static-cached per
 * request. Mirrors `\Novamira\Pro\Abilities\OceanWP\ocean_extra_available()`
 * shape so abilities can return a uniform `ocean_extra` block.
 *
 * @return array{active: bool, version: string, min_supported: string, min_satisfied: bool}
 */
function ow_detect_ocean_extra(): array
{
    /** @var array{active: bool, version: string, min_supported: string, min_satisfied: bool}|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $min = NOVAMIRA_PRO_OCEAN_EXTRA_MIN_VERSION;
    $active = \Novamira\Pro\Abilities\OceanWP\ocean_extra_active();
    if (!$active) {
        return ['active' => false, 'version' => '', 'min_supported' => $min, 'min_satisfied' => false];
    }
    $version = \Novamira\Pro\Abilities\OceanWP\ocean_extra_version();
    return [
        'active' => true,
        'version' => $version,
        'min_supported' => $min,
        'min_satisfied' => $version !== '' && version_compare($version, $min, operator: '>='),
    ];
}

/**
 * Snapshot of optional third-party integrations OceanWP detects at boot.
 * Mirrors the constants in `oceanwp/functions.php:166-176`.
 *
 * @return array{
 *   sticky_header: bool,
 *   sticky_footer: bool,
 *   ecommerce: bool,
 *   elementor: bool,
 *   beaver_builder: bool,
 *   woocommerce: bool,
 *   edd: bool,
 *   lifterlms: bool,
 *   auto_load_next_post: bool,
 *   learndash: bool
 * }
 */
function ow_detect_integrations(): array
{
    return [
        'sticky_header' => ow_bool_const('OCEANWP_STICKY_HEADER_ACTIVE'),
        'sticky_footer' => ow_bool_const('OCEANWP_STICKY_FOOTER_ACTIVE'),
        'ecommerce' => ow_bool_const('OCEANWP_ECOMM_ACTIVE'),
        'elementor' => ow_bool_const('OCEANWP_ELEMENTOR_ACTIVE'),
        'beaver_builder' => ow_bool_const('OCEANWP_BEAVER_BUILDER_ACTIVE'),
        'woocommerce' => ow_bool_const('OCEANWP_WOOCOMMERCE_ACTIVE'),
        'edd' => ow_bool_const('OCEANWP_EDD_ACTIVE'),
        'lifterlms' => ow_bool_const('OCEANWP_LIFTERLMS_ACTIVE'),
        'auto_load_next_post' => ow_bool_const('OCEANWP_ALNP_ACTIVE'),
        'learndash' => ow_bool_const('OCEANWP_LEARNDASH_ACTIVE'),
    ];
}

/**
 * Strict boolean-constant reader. Returns true only when the named constant
 * is defined AND its value is the boolean literal `true`. Avoids the
 * `(bool) constant(...)` mixed-cast warning analyzer flags. OceanWP defines
 * its integration flags as `class_exists(...)` which always returns bool,
 * so the strict equality is safe.
 */
function ow_bool_const(string $name): bool
{
    if (!defined($name)) {
        return false;
    }
    return constant($name) === true;
}

// ---------------------------------------------------------------------------
// Error builders. One code per concrete failure mode. Status codes match
// the canonical mapping (400 = client error, 503 = transient busy).
// ---------------------------------------------------------------------------

function ow_not_active(): WP_Error
{
    return new WP_Error(
        'oceanwp_not_active',
        __(
            'The OceanWP theme is not active or is below the minimum supported version. Activate OceanWP and ensure it is at least the version reported by `oceanwp-check-setup`.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

function ow_requires_ocean_extra(): WP_Error
{
    return new WP_Error(
        'oceanwp_requires_ocean_extra',
        __(
            'This ability requires the free Ocean Extra companion plugin to be installed, active, and at the minimum supported version. Install Ocean Extra from wordpress.org and try again.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

function ow_invalid_input(string $message): WP_Error
{
    return new WP_Error('oceanwp_invalid_input', $message, ['status' => 400]);
}

/**
 * Build the canonical "this slug is not an Ocean Extra module we know
 * about" rejection. The `oceanwp_unknown_*` family signals discover-then-
 * retry to the agent, distinct from `oceanwp_invalid_input` which signals
 * fix-the-shape.
 *
 * @param list<string> $valid
 */
function ow_unknown_module(string $module, array $valid): WP_Error
{
    return new WP_Error(
        'oceanwp_unknown_module',
        sprintf(
            /* translators: 1: module slug, 2: comma-separated list of valid slugs */
            __('Unknown module "%1$s". Valid modules: %2$s.', domain: 'novamira-pro'),
            $module,
            implode(', ', $valid),
        ),
        ['status' => 400],
    );
}

/**
 * Environmental rejection — WooCommerce isn't loaded so a Woo-scoped
 * ability would be a no-op. Distinct from `oceanwp_invalid_input` so an
 * agent dispatching on the error code can recognise the "install
 * WooCommerce" surface vs an actual input-shape problem.
 */
function ow_requires_woocommerce(): WP_Error
{
    return new WP_Error(
        'oceanwp_requires_woocommerce',
        __(
            'This ability requires WooCommerce to be active. Install/activate WooCommerce on this site and try again.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * Persistence failure — a write into wp_options or postmeta returned
 * false (typically a `pre_update_option_*` filter cancelled the write or
 * a DB error). Distinct code so the agent can surface the actual
 * failure instead of trusting a misleading `persisted: true`.
 */
function ow_persist_failed(string $entity): WP_Error
{
    return new WP_Error(
        'oceanwp_persist_failed',
        sprintf(
            /* translators: %s: the entity (option / postmeta key) that failed to persist. */
            __(
                'Failed to persist "%s". A WordPress filter may have blocked the update, or the database write returned false. The previous value is intact.',
                domain: 'novamira-pro',
            ),
            $entity,
        ),
        ['status' => 500],
    );
}

/**
 * Caller passed a slug pointing at an `oceanwp_library` post that is
 * not in the publishable status range Ocean Extra renders at the front-
 * end. Distinct from `oceanwp_invalid_input` (which covers wrong type /
 * missing post) so the agent can self-correct: "the template exists but
 * is in draft/trash/private — publish it or pick another."
 */
function ow_template_not_published(int $template_id, string $current_status): WP_Error
{
    return new WP_Error(
        'oceanwp_template_not_published',
        sprintf(
            /* translators: 1: template id, 2: current post_status (e.g. draft, trash) */
            __(
                'OceanWP library template %1$d is not in publish state (current status: %2$s). Publish the template first, or pick a different template id from `oceanwp-list-library-items`.',
                domain: 'novamira-pro',
            ),
            $template_id,
            $current_status,
        ),
        ['status' => 400],
    );
}

function ow_invalid_post(int $post_id): WP_Error
{
    return new WP_Error(
        'oceanwp_invalid_post',
        sprintf(
            /* translators: %d: the post id the caller passed. */
            __(
                'No post found with id %d, or the post is in a non-editable state (trash, auto-draft, inherit). Pass a published or draft post id.',
                domain: 'novamira-pro',
            ),
            $post_id,
        ),
        ['status' => 400],
    );
}

/**
 * Combined OceanWP + Ocean Extra gate. Returns null when both are
 * available, or the appropriate WP_Error (`oceanwp_not_active` /
 * `oceanwp_requires_ocean_extra`) otherwise. Sprint-4 abilities all
 * call this at the top of the callback to skip the work without
 * touching the database.
 */
function ow_assert_ocean_extra_available(): ?WP_Error
{
    if (!\Novamira\Pro\Abilities\OceanWP\oceanwp_theme_available()) {
        return ow_not_active();
    }
    $oe = ow_detect_ocean_extra();
    if (!$oe['active'] || !$oe['min_satisfied']) {
        return ow_requires_ocean_extra();
    }
    return null;
}

/**
 * Catalogue of Ocean Extra Library template slot → theme_mod key
 * mapping. Each slot is one of the four customizer-driven "use a
 * Library template" mount points OceanWP reads at render time (in
 * `inc/customizer/options/header.php` and `footer-*.php`). The
 * `apply-library-template` ability writes the template id into the
 * matching theme_mod.
 *
 * @return array<string, string>
 */
function ow_library_slot_map(): array
{
    return [
        'header' => 'ocean_header_template',
        'topbar' => 'ocean_topbar_template',
        'footer_widgets' => 'ocean_footer_widgets_replace',
        'custom_404' => 'ocean_custom_404_template',
    ];
}

/**
 * Curated catalogue of Ocean Extra modules — slug → human label and the
 * sentinel class that proves the module's bootstrap loaded. Used by
 * `list-modules` (class_exists() probe gives the enabled state) and as
 * the allowlist for `enable-module` / `disable-module`. The actual on/off
 * persistence lives in the Ocean Extra-managed option
 * `oceanwp_modules` (an associative array of module-slug → bool); we
 * write into it via update_option after merging with the existing value.
 *
 * @return array<string, array{label: string, class: string}>
 */
function ow_ocean_extra_modules(): array
{
    return [
        'sticky_header' => ['label' => 'Sticky Header', 'class' => 'Ocean_Sticky_Header'],
        'sticky_footer' => ['label' => 'Sticky Footer', 'class' => 'Ocean_Sticky_Footer'],
        'side_panel' => ['label' => 'Side Panel', 'class' => 'Ocean_Side_Panel'],
        'popup_login' => ['label' => 'Popup Login', 'class' => 'Ocean_Popup_Login'],
        'footer_callout' => ['label' => 'Footer Callout', 'class' => 'Ocean_Footer_Callout'],
        'instagram' => ['label' => 'Instagram', 'class' => 'Ocean_Instagram'],
        'cookie_notice' => ['label' => 'Cookie Notice', 'class' => 'Ocean_Cookie_Notice'],
        'portfolio' => ['label' => 'Portfolio', 'class' => 'Ocean_Portfolio'],
        'woo_popup' => ['label' => 'WooCommerce Popup', 'class' => 'Ocean_Woo_Popup'],
        'full_screen' => ['label' => 'Full Screen', 'class' => 'Ocean_Full_Screen'],
        'white_label' => ['label' => 'White Label', 'class' => 'Ocean_White_Label'],
        'elementor_widgets' => ['label' => 'Elementor Widgets', 'class' => 'Ocean_Elementor_Widgets'],
    ];
}

/**
 * Resolve which page-override keys to read given an optional `fields[]`
 * filter. Mirrors the get-settings resolver but for the page-overrides
 * allowlist. Returned tuple is [names, unknown]; unknown is populated only
 * when the caller passed an explicit fields list.
 *
 * @param list<string> $requested
 * @param array<string, array{type: string, panel?: string, description: string, enum?: list<string>}> $allowlist
 * @return array{0: list<string>, 1: list<string>}
 */
function ow_resolve_page_override_names(array $requested, array $allowlist): array
{
    if ($requested === []) {
        return [array_keys($allowlist), []];
    }
    $names = [];
    $unknown = [];
    foreach ($requested as $name) {
        if ($name === '') {
            continue;
        }
        if (!isset($allowlist[$name])) {
            $unknown[] = $name;
            continue;
        }
        $names[] = $name;
    }
    return [$names, $unknown];
}

/**
 * Classify a name → value map of incoming page-override writes into three
 * disjoint buckets: candidates (validated, ready to persist), to_delete
 * (null values → delete_post_meta), rejected (validation failures or
 * unknown allowlist keys). Used by `set-page-overrides` to keep the main
 * callback's cyclomatic complexity under mago's threshold.
 *
 * @param array<int|string, mixed> $raw
 * @param array<string, array{type: string, panel?: string, description: string, enum?: list<string>}> $allowlist
 * @return array{
 *   candidates: array<string, bool|int|float|string>,
 *   to_delete: list<string>,
 *   rejected: list<array{name: string, reason: string}>
 * }
 */
function ow_classify_page_override_inputs(array $raw, array $allowlist): array
{
    /** @var array<string, bool|int|float|string> $candidates */
    $candidates = [];
    $to_delete = [];
    $rejected = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $name => $value) {
        if (!is_string($name)) {
            $rejected[] = ['name' => '<non-string-key>', 'reason' => 'Meta key must be a string.'];
            continue;
        }
        if (!isset($allowlist[$name])) {
            $rejected[] = ['name' => $name, 'reason' => 'Meta key is not in the agent allowlist.'];
            continue;
        }
        if ($value === null) {
            $to_delete[] = $name;
            continue;
        }
        $normalized = ow_validate_setting_value($name, $value, $allowlist[$name]);
        if ($normalized instanceof WP_Error) {
            $rejected[] = ['name' => $name, 'reason' => $normalized->get_error_message()];
            continue;
        }
        if (is_array($normalized)) {
            $rejected[] = ['name' => $name, 'reason' => 'Internal: page overrides do not accept object values.'];
            continue;
        }
        $candidates[$name] = $normalized;
    }
    return ['candidates' => $candidates, 'to_delete' => $to_delete, 'rejected' => $rejected];
}

/**
 * Validate that a post id refers to an existing post in an editable state.
 * Rejects trash, auto-draft, inherit (revisions) so write paths cannot
 * silently mutate post meta on a post the user never meant to edit.
 */
function ow_post_or_error(int $post_id): WP_Post|WP_Error
{
    if ($post_id <= 0) {
        return ow_invalid_post($post_id);
    }
    // @mago-expect analysis:mixed-assignment
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return ow_invalid_post($post_id);
    }
    if (in_array($post->post_status, ['trash', 'auto-draft', 'inherit'], strict: true)) {
        return ow_invalid_post($post_id);
    }
    return $post;
}

/**
 * Catalogue of `ocean_*` postmeta keys the agent can read via
 * `oceanwp-get-page-overrides` and write via `oceanwp-set-page-overrides`.
 * Each entry uses the same meta shape as `ow_customizer_allowlist()` so
 * `ow_validate_setting_value()` validates them identically.
 *
 * Only keys the OceanWP theme actually READS at render time are included —
 * anything else would be inert noise. The full list of render-time keys is
 * documented in Agent 1's theme inventory; this curated subset focuses on
 * the high-leverage override surface (layout, breadcrumb colors, post-
 * format media). Post-format media keys are mutually exclusive at
 * render time based on the post's chosen format, but we don't enforce
 * that — callers may set them all and the theme picks per-post.
 *
 * @return array<string, array{
 *   type: 'boolean'|'integer'|'string'|'enum'|'color',
 *   description: string,
 *   enum?: list<string>
 * }>
 */
function ow_page_overrides_allowlist(): array
{
    /** @var array<string, array{type: 'boolean'|'integer'|'string'|'enum'|'color', description: string, enum?: list<string>}>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return $cache = [
        // Layout override.
        'ocean_post_layout' => [
            'type' => 'enum',
            'description' => 'Per-post layout override (sidebar position / full-width / full-screen).',
            'enum' => ['right-sidebar', 'left-sidebar', 'both-sidebars', 'full-width', 'full-screen'],
        ],
        'ocean_both_sidebars_style' => [
            'type' => 'enum',
            'description' => 'Per-post both-sidebars arrangement (only effective when layout is "both-sidebars").',
            'enum' => ['scs-style', 'css-style', 'ccs-style'],
        ],
        // Per-page breadcrumb colors (the theme reads these in
        // inc/customizer/css-output/colors.php:71-74).
        'ocean_breadcrumbs_color' => [
            'type' => 'color',
            'description' => 'Per-page breadcrumb text color (CSS color string).',
        ],
        'ocean_breadcrumbs_separator_color' => [
            'type' => 'color',
            'description' => 'Per-page breadcrumb separator color.',
        ],
        'ocean_breadcrumbs_links_color' => [
            'type' => 'color',
            'description' => 'Per-page breadcrumb link color.',
        ],
        'ocean_breadcrumbs_links_hover_color' => [
            'type' => 'color',
            'description' => 'Per-page breadcrumb link hover color.',
        ],
        // Post-format video.
        'ocean_post_video_embed' => [
            'type' => 'string',
            'description' => 'Video embed code or URL (post format: video).',
        ],
        'ocean_post_self_hosted_media' => [
            'type' => 'string',
            'description' => 'Self-hosted video URL (post format: video).',
        ],
        'ocean_post_oembed' => [
            'type' => 'string',
            'description' => 'OEmbed URL (post format: video).',
        ],
        // Post-format quote.
        'ocean_quote_format' => [
            'type' => 'string',
            'description' => 'Quote content (post format: quote).',
        ],
        'ocean_quote_format_link' => [
            'type' => 'string',
            'description' => 'Quote source link (post format: quote).',
        ],
        // Post-format link.
        'ocean_link_format' => [
            'type' => 'string',
            'description' => 'Link URL (post format: link).',
        ],
        'ocean_link_format_target' => [
            'type' => 'enum',
            'description' => 'Link target attribute (post format: link).',
            'enum' => ['_self', '_blank'],
        ],
    ];
}

// ---------------------------------------------------------------------------
// State readers. theme_mods are read via WP core APIs, never directly off
// `get_option('theme_mods_oceanwp')` — `get_theme_mod()` applies the modern
// theme-mod filter pipeline a stale option-read would skip.
// ---------------------------------------------------------------------------

/**
 * Read a single theme_mod with the OceanWP default if not set, coerced to
 * scalar. Returns null for unset settings without an OceanWP default.
 */
function ow_theme_mod(string $setting): bool|int|float|string|null
{
    $defaults = ow_oceanwp_defaults();
    $default = $defaults[$setting] ?? null;
    /** @var mixed $value */
    $value = get_theme_mod($setting, $default);
    if ($value === null) {
        return null;
    }
    if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
        return $value;
    }
    return null;
}

/**
 * Read a typography theme_mod and return its decoded object, or [] when the
 * mod is unset. OceanWP stores typography settings as JSON-encoded objects.
 *
 * @return array<string, scalar|null>
 */
function ow_theme_mod_typography(string $setting): array
{
    $default = '';
    /** @var mixed $raw */
    $raw = get_theme_mod($setting, $default);
    if ($raw === '' || $raw === null) {
        return [];
    }
    if (is_array($raw)) {
        /** @var array<string, mixed> $raw */
        return ow_filter_scalar_assoc($raw);
    }
    if (!is_string($raw)) {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($raw, associative: true);
    if (!is_array($decoded)) {
        return [];
    }
    /** @var array<string, mixed> $decoded */
    return ow_filter_scalar_assoc($decoded);
}

/**
 * Returns the OceanWP default value map (the same map the theme falls back
 * to when a Customizer setting has never been touched). Static-cached.
 *
 * @return array<string, scalar|null>
 */
function ow_oceanwp_defaults(): array
{
    /** @var array<string, scalar|null>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (!function_exists('oceanwp_default_theme_options')) {
        return [];
    }
    /** @var mixed $raw */
    $raw = oceanwp_default_theme_options();
    if (!is_array($raw)) {
        return [];
    }
    /** @var array<string, mixed> $raw */
    return ow_filter_scalar_assoc($raw);
}

// ---------------------------------------------------------------------------
// Coercion helpers. Keep callers free of repeated cast clutter and centralize
// the "is this value-shaped" decisions in one place.
// ---------------------------------------------------------------------------

/**
 * Filter an associative array down to keys whose values are scalar (or
 * null), discarding nested arrays / objects. Used to flatten theme_mod
 * blobs into agent-friendly shapes.
 *
 * @param array<int|string, mixed> $input
 * @return array<string, scalar|null>
 */
function ow_filter_scalar_assoc(array $input): array
{
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($input as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }
        if ($value === null || is_scalar($value)) {
            $out[$key] = $value;
        }
    }
    return $out;
}

/**
 * Coerce a value to bool, accepting the common WP truthy/falsy spellings.
 * Unrecognised values resolve to false (e.g. arrays, objects, unknown strings).
 */
function ow_coerce_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    if (is_string($value)) {
        $trimmed = strtolower(trim($value));
        if (in_array($trimmed, ['1', 'true', 'yes', 'on'], strict: true)) {
            return true;
        }
    }
    return false;
}

/**
 * Coerce a value to int when it parses cleanly. Returns null when the
 * value is missing or non-numeric (so callers can distinguish "0" from
 * "absent" — important for clamp semantics).
 */
function ow_coerce_int_or_null(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && $value !== '' && preg_match('/^-?\d+$/', $value) === 1) {
        return (int) $value;
    }
    return null;
}

/**
 * Coerce a value to a trimmed string when it is a scalar. Empty input
 * (empty string, null, false) returns null so callers can branch on
 * absence vs blank.
 */
function ow_coerce_string_or_null(mixed $value): ?string
{
    if (is_string($value)) {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if ($value === true) {
        return '1';
    }
    return null;
}

/**
 * Truncate a string to at most $max_bytes, appending a UTF-8-safe ellipsis
 * marker when truncation actually happens. Returns the original string when
 * it already fits. Uses `mb_strcut` so the cut never lands inside a
 * multi-byte UTF-8 codepoint (the previous byte-level `substr` could
 * produce invalid UTF-8 immediately before the appended marker — see
 * audit round 2 M#2).
 */
function ow_truncate_field(string $value, int $max_bytes): string
{
    if (strlen($value) <= $max_bytes) {
        return $value;
    }
    $cut = function_exists('mb_strcut')
        ? mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8')
        : substr($value, offset: 0, length: $max_bytes);
    return $cut . '…';
}

// ---------------------------------------------------------------------------
// Lock — single per-blog mutex around theme_mods writes. Mirrors weglot's
// `wg_with_options_lock` pattern: object-cache primary with add_option
// INSERT IGNORE fallback, CAS release via owner token, multisite-safe key.
// Used by every Sprint-2/3 write ability so concurrent set-* calls never
// interleave read-modify-write on the theme_mods_oceanwp blob.
// ---------------------------------------------------------------------------

const OW_LOCK_GROUP = 'nvp_oceanwp';

/**
 * Acquire the per-blog OceanWP theme_mods lock, run $fn, release the lock
 * (CAS-guarded so we never release a lock another process has stolen).
 * Returns whatever $fn returns, or a `oceanwp_lock_busy` WP_Error if the
 * lock could not be acquired within ~2 seconds. Thin wrapper around
 * `ow_with_named_lock` kept for ergonomics + back-compat with the Sprint-2
 * call sites.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|WP_Error
 */
function ow_with_theme_mods_lock(callable $fn, int $stale_after_seconds = 15): mixed
{
    return ow_with_named_lock('theme_mods', $fn, $stale_after_seconds);
}

/**
 * Acquire the per-blog OceanWP modules-option lock. Used by Sprint-4
 * `oceanwp-enable-module` / `oceanwp-disable-module` to serialise the
 * read-modify-write cycle on the `oceanwp_modules` option blob. Sharing
 * the lock namespace with theme_mods would be safe (both are per-blog
 * OceanWP writes) but a dedicated key avoids needlessly blocking
 * unrelated typography / header writes while a module toggle is in
 * flight.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|WP_Error
 */
function ow_with_oceanwp_modules_lock(callable $fn, int $stale_after_seconds = 15): mixed
{
    return ow_with_named_lock('oceanwp_modules', $fn, $stale_after_seconds);
}

/**
 * Generic per-blog named lock. Acquires `nvp_oceanwp_<name>_lock_<blog_id>`,
 * runs `$fn`, releases via CAS so a thief that took over a stale lock
 * never gets its lock deleted by us. Returns whatever `$fn` returns, or a
 * `oceanwp_lock_busy` WP_Error when acquisition fails within ~2 s.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|WP_Error
 */
function ow_with_named_lock(string $name, callable $fn, int $stale_after_seconds = 15): mixed
{
    $key = sprintf('nvp_oceanwp_%s_lock_%d', $name, get_current_blog_id());
    $owner = ow_lock_new_owner_token();
    $now = time();
    $value = $owner . '|' . $now;
    $ttl = $stale_after_seconds + 30;
    $using_cache = wp_using_ext_object_cache();

    $acquired = false;
    for ($attempt = 0; $attempt < 20; ++$attempt) {
        if ($using_cache) {
            if (wp_cache_add($key, $value, OW_LOCK_GROUP, $ttl)) {
                $acquired = true;
                break;
            }
            /** @var mixed $existing */
            $existing = wp_cache_get($key, OW_LOCK_GROUP);
            if (is_string($existing) && ow_lock_is_stale($existing, $now, $stale_after_seconds)) {
                wp_cache_set($key, $value, OW_LOCK_GROUP, $ttl);
                $acquired = true;
                break;
            }
            usleep(100_000);
            continue;
        }
        if (add_option($key, $value, deprecated: '', autoload: false)) {
            $acquired = true;
            break;
        }
        /** @var mixed $existing */
        $existing = get_option($key, default_value: '');
        if (is_string($existing) && ow_lock_is_stale($existing, $now, $stale_after_seconds)) {
            update_option($key, $value, autoload: false);
            $acquired = true;
            break;
        }
        usleep(100_000);
    }

    if (!$acquired) {
        return new WP_Error(
            'oceanwp_lock_busy',
            __('Another OceanWP write is in progress on this site. Try again in a moment.', domain: 'novamira-pro'),
            ['status' => 503],
        );
    }

    try {
        return $fn();
    } finally {
        ow_release_lock($key, $value);
    }
}

/**
 * Generate a fresh per-acquisition owner token (16-byte hex). Used so the
 * release step can verify it owns the current lock before deleting — if a
 * stale-cleanup stole this lock mid-execution, we MUST NOT release the
 * thief's lock.
 */
function ow_lock_new_owner_token(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Release the lock only when the stored value still matches our acquisition
 * token. `hash_equals` is constant-time and defuses the mago
 * `no-insecure-comparison` lint. Re-checks `wp_using_ext_object_cache()` so
 * the release path stays symmetric with the acquire path even if the
 * environment switched mid-execution.
 */
function ow_release_lock(string $key, string $owner_value): void
{
    if (wp_using_ext_object_cache()) {
        /** @var mixed $current */
        $current = wp_cache_get($key, OW_LOCK_GROUP);
        if (is_string($current) && hash_equals($current, $owner_value)) {
            wp_cache_delete($key, OW_LOCK_GROUP);
        }
        return;
    }
    /** @var mixed $current */
    $current = get_option($key, default_value: '');
    if (is_string($current) && hash_equals($current, $owner_value)) {
        delete_option($key);
    }
}

/**
 * A stored lock value is stale when its timestamp is missing, unparseable,
 * zero, or older than $stale_after seconds. Treating malformed values as
 * stale prevents a corrupt sentinel from wedging the write path indefinitely.
 */
function ow_lock_is_stale(string $lock_value, int $now, int $stale_after): bool
{
    $parts = explode('|', $lock_value, limit: 2);
    if (count($parts) !== 2) {
        return true;
    }
    $ts = (int) $parts[1];
    if ($ts <= 0) {
        return true;
    }
    return ($now - $ts) > $stale_after;
}

// ---------------------------------------------------------------------------
// Allowlist-aware value validation. Sprint-2 writers call this on every
// candidate write so the actual `set_theme_mod` call receives a value of the
// declared type. Returns the normalized value or a `oceanwp_invalid_input`
// WP_Error describing the specific violation (so the agent can self-correct
// without guessing).
// ---------------------------------------------------------------------------

/**
 * Validate + coerce a candidate value against its allowlist entry. The
 * caller has already verified the setting name is in the allowlist; this
 * focuses on the value shape.
 *
 * @param array{type: string, panel?: string, description: string, enum?: list<string>} $meta
 * @return bool|int|float|string|array<string, scalar|null>|WP_Error
 */
function ow_validate_setting_value(string $name, mixed $value, array $meta): bool|int|float|string|array|WP_Error
{
    switch ($meta['type']) {
        case 'boolean':
            return ow_coerce_bool($value);
        case 'integer':
            $coerced = ow_coerce_int_or_null($value);
            if ($coerced === null) {
                return ow_invalid_input(sprintf(
                    /* translators: %s = setting name */ 'Setting "%s" requires an integer value.',
                    $name,
                ));
            }
            return $coerced;
        case 'color':
        case 'string':
            $coerced = ow_coerce_string_or_null($value);
            if ($coerced === null) {
                return ow_invalid_input(sprintf(
                    /* translators: %s = setting name */ 'Setting "%s" requires a non-empty string.',
                    $name,
                ));
            }
            return $coerced;
        case 'enum':
            $coerced = ow_coerce_string_or_null($value);
            $enum = $meta['enum'] ?? [];
            if ($coerced === null || !in_array($coerced, $enum, strict: true)) {
                return ow_invalid_input(sprintf(
                    /* translators: 1: setting name, 2: allowed values */
                    'Setting "%1$s" must be one of: %2$s.',
                    $name,
                    implode(', ', $enum),
                ));
            }
            return $coerced;
        case 'typography':
            if (!is_array($value) || $value === []) {
                return ow_invalid_input(sprintf(
                    /* translators: %s = setting name */
                    'Setting "%s" requires a non-empty object with typography keys (font-family, font-weight, font-size, line-height, letter-spacing, text-transform).',
                    $name,
                ));
            }
            // Reject JSON arrays (sequential int keys) and any associative shape
            // whose values are not scalar — both flow through ow_filter_scalar_assoc
            // as silent drops and would clobber the existing typography blob with
            // an empty / partial JSON literal on persist. See audit round 1 M#8.
            // @mago-expect analysis:mixed-assignment
            foreach ($value as $sub_key => $sub_value) {
                if (!is_string($sub_key) || $sub_key === '') {
                    return ow_invalid_input(sprintf(
                        /* translators: %s = setting name */
                        'Setting "%s" requires an associative object — sequential JSON arrays are rejected because they would silently drop typography keys.',
                        $name,
                    ));
                }
                if ($sub_value !== null && !is_scalar($sub_value)) {
                    return ow_invalid_input(sprintf(
                        /* translators: 1: setting name, 2: offending sub-key */
                        'Setting "%1$s" has non-scalar value under key "%2$s". Typography sub-values must be scalar (string, int, float, bool) or null.',
                        $name,
                        $sub_key,
                    ));
                }
            }
            return ow_filter_scalar_assoc($value);
        default:
            return ow_invalid_input(sprintf(
                /* translators: 1: setting name, 2: declared type */
                'Internal: setting "%1$s" has unsupported allowlist type "%2$s".',
                $name,
                $meta['type'],
            ));
    }
}

/**
 * Persist a single normalized value to the OceanWP theme_mods blob. For
 * typography (array) we JSON-encode to match how OceanWP itself stores
 * those settings; for scalars we hand off directly to `set_theme_mod`.
 *
 * @param bool|int|float|string|array<string, scalar|null> $normalized
 */
function ow_persist_setting(string $name, bool|int|float|string|array $normalized): void
{
    if (is_array($normalized)) {
        set_theme_mod($name, wp_json_encode($normalized));
        return;
    }
    set_theme_mod($name, $normalized);
}

/**
 * Wire-shape guard: emit an associative map as-is, or `new \stdClass()`
 * when empty. Output schemas that declare `type: object` require `{}`
 * on the wire — a raw empty PHP array JSON-encodes as `[]` and breaks
 * strict validators against the ability's declared schema. Every
 * OceanWP output field typed `object` that can be empty on some code
 * path should go through this helper on the way out.
 *
 * @template TValue
 * @param array<string, TValue> $map
 * @return array<string, TValue>|\stdClass
 */
function ow_object_or_map(array $map): array|\stdClass
{
    return $map === [] ? new \stdClass() : $map;
}

/**
 * Translate the inner `oceanwp-set-customizer-settings` result map back
 * through the public alias vocabulary so wrapper abilities
 * (`set-header-config`, `set-typography-preset`, `set-woo-features`) do not
 * leak internal `ocean_*` theme_mod names in `changed[]`, `unchanged[]`,
 * or `values{}`. Audit round 2 M#7.
 *
 * The inner ability's `values` field is `array<string, mixed>|\stdClass`
 * (empty stdClass on all-inputs-rejected — see audit round 3 F2). This
 * helper's own `values` output is likewise wrapped by ow_object_or_map()
 * so the wrapper abilities' declared `type: object` schema is respected
 * on the wire even if the inner map ends up empty.
 *
 * @param array{persisted: bool, changed: list<string>, unchanged: list<string>, rejected: list<array{name: string, reason: string}>, values: array<string, mixed>|\stdClass} $result
 * @param array<string, string> $alias_for theme_mod_name => alias
 * @return array{persisted: bool, changed: list<string>, unchanged: list<string>, values: array<string, mixed>|\stdClass}
 */
function ow_translate_result_to_aliases(array $result, array $alias_for): array
{
    $values = [];
    /** @var iterable<string, mixed> $result_values */
    $result_values = $result['values'] instanceof \stdClass ? (array) $result['values'] : $result['values'];
    // @mago-expect analysis:mixed-assignment
    foreach ($result_values as $name => $value) {
        $values[$alias_for[$name] ?? $name] = $value;
    }
    return [
        'persisted' => $result['persisted'],
        'changed' => array_values(array_map(static fn(string $n): string => $alias_for[$n] ?? $n, $result['changed'])),
        'unchanged' => array_values(array_map(
            static fn(string $n): string => $alias_for[$n] ?? $n,
            $result['unchanged'],
        )),
        'values' => ow_object_or_map($values),
    ];
}

/**
 * Compose a single user-facing WP_Error from a list of pre-validation
 * rejections, rewriting each entry to surface the public alias rather than
 * the internal `ocean_*` theme_mod name. Used by the wrappers
 * (`oceanwp-set-header-config`, `oceanwp-set-typography-preset`) so the
 * caller only ever sees the alias they actually passed. See audit round 1
 * M#11.
 *
 * @param list<array{name: string, reason: string}> $rejections
 * @param array<string, string> $alias_for theme_mod_name => alias
 */
function ow_format_alias_rejections(array $rejections, array $alias_for, string $error_prefix): WP_Error
{
    $details = [];
    foreach ($rejections as $r) {
        $alias = $alias_for[$r['name']] ?? $r['name'];
        $clean_reason = str_replace(['"' . $r['name'] . '"', $r['name']], ['"' . $alias . '"', $alias], $r['reason']);
        $details[] = sprintf('%s: %s', $alias, $clean_reason);
    }
    return ow_invalid_input(sprintf('%s %s', $error_prefix, implode('; ', $details)));
}

/**
 * Pre-validate a name → value map against the Customizer allowlist WITHOUT
 * persisting. Returns the rejection list (empty when every entry is valid).
 *
 * Used by the alias-schema wrappers (`oceanwp-set-header-config`,
 * `oceanwp-set-typography-preset`) before they delegate to
 * `oceanwp-set-customizer-settings`. The inner ability does partial writes
 * (it persists every value that validates and reports the failures in
 * `rejected[]` without aborting); a wrapper that converts the resulting
 * `rejected` array into a WP_Error after the fact would already have
 * mutated state by the time the error is returned. This helper closes that
 * gap — the wrapper runs validation FIRST, surfaces any rejection as a
 * pre-state-mutation error, and only delegates when every entry passes.
 *
 * See audit round 1 HIGH finding.
 *
 * @param array<string, mixed> $settings
 * @return list<array{name: string, reason: string}>
 */
function ow_prevalidate_customizer_writes(array $settings): array
{
    $allowlist = ow_customizer_allowlist();
    $rejections = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($settings as $name => $value) {
        if (!isset($allowlist[$name])) {
            $rejections[] = ['name' => $name, 'reason' => 'Setting is not in the agent allowlist.'];
            continue;
        }
        $normalized = ow_validate_setting_value($name, $value, $allowlist[$name]);
        if ($normalized instanceof WP_Error) {
            $rejections[] = ['name' => $name, 'reason' => $normalized->get_error_message()];
        }
    }
    return $rejections;
}

// ---------------------------------------------------------------------------
// Customizer panel catalog. Static — mirrors the 17 panels declared in
// `inc/customizer/options/options.php` of OceanWP 4.1.x. Each entry exposes
// the panel id (Customizer section / sub-panel id), the human label, the
// approximate number of settings it owns, the parent Customizer panel
// (when the OceanWP options file ties into one), and whether the panel is
// conditional on a third-party integration.
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{
 *   id: string,
 *   label: string,
 *   approx_settings: int,
 *   description: string,
 *   conditional_on: string
 * }>
 */
// @mago-expect lint:halstead
function ow_customizer_panel_catalog(): array
{
    return [
        'typography' => [
            'id' => 'typography',
            'label' => 'Typography',
            'approx_settings' => 18,
            'description' => 'Google Fonts, body and per-heading (h1–h6) typography.',
            'conditional_on' => '',
        ],
        'colors' => [
            'id' => 'colors',
            'label' => 'General Colors',
            'approx_settings' => 13,
            'description' => 'Primary, links, background, border colors and palette.',
            'conditional_on' => '',
        ],
        'general' => [
            'id' => 'general',
            'label' => 'General Options',
            'approx_settings' => 73,
            'description' => 'Site layout, background image, site icon, buttons, forms, scroll-to-top, pagination.',
            'conditional_on' => '',
        ],
        'page' => [
            'id' => 'page',
            'label' => 'Page Title & Breadcrumbs',
            'approx_settings' => 76,
            'description' => 'Page title bar, breadcrumbs (home, trail, taxonomy, custom text), search result, 404.',
            'conditional_on' => '',
        ],
        'topbar' => [
            'id' => 'topbar',
            'label' => 'Top Bar',
            'approx_settings' => 26,
            'description' => 'Top bar enable, content slots, social profiles, layout.',
            'conditional_on' => '',
        ],
        'header' => [
            'id' => 'header',
            'label' => 'Header',
            'approx_settings' => 186,
            'description' => 'Header type (8 styles), custom header, additional styling, logo, menu, search, mobile menu, vertical header.',
            'conditional_on' => '',
        ],
        'blog' => [
            'id' => 'blog',
            'label' => 'Blog',
            'approx_settings' => 124,
            'description' => 'Archive layout, content builder, pagination / infinite scroll / load more, single post layout, typography, related posts.',
            'conditional_on' => '',
        ],
        'sidebar' => [
            'id' => 'sidebar',
            'label' => 'Sidebar',
            'approx_settings' => 13,
            'description' => 'Sidebar width, padding, widget styling.',
            'conditional_on' => '',
        ],
        'footer_widgets' => [
            'id' => 'footer_widgets',
            'label' => 'Footer Widgets',
            'approx_settings' => 17,
            'description' => 'Footer widgets enable, container, columns, padding, colors, fixed footer.',
            'conditional_on' => '',
        ],
        'footer_copyright' => [
            'id' => 'footer_copyright',
            'label' => 'Footer Bottom (Copyright)',
            'approx_settings' => 11,
            'description' => 'Copyright text, visibility, padding, colors.',
            'conditional_on' => '',
        ],
        'seo' => [
            'id' => 'seo',
            'label' => 'SEO',
            'approx_settings' => 10,
            'description' => 'OpenGraph, schema, twitter handle, facebook appid, image alt.',
            'conditional_on' => '',
        ],
        'performance' => [
            'id' => 'performance',
            'label' => 'Performance',
            'approx_settings' => 11,
            'description' => 'Disable FontAwesome / SVG icons / lightbox / emoji / simple-line-icons / widgets stylesheet / scroll effect.',
            'conditional_on' => '',
        ],
        'woocommerce' => [
            'id' => 'woocommerce',
            'label' => 'WooCommerce',
            'approx_settings' => 276,
            'description' => 'Menu cart, archives, single product, cart, checkout, styling, off-canvas filter, distraction-free, quick view, multistep, floating bar.',
            'conditional_on' => 'woocommerce',
        ],
        'edd' => [
            'id' => 'edd',
            'label' => 'Easy Digital Downloads',
            'approx_settings' => 86,
            'description' => 'EDD-specific layout, cart and product page tweaks.',
            'conditional_on' => 'edd',
        ],
        'learndash' => [
            'id' => 'learndash',
            'label' => 'LearnDash',
            'approx_settings' => 23,
            'description' => 'LearnDash course/lesson layout overrides.',
            'conditional_on' => 'learndash',
        ],
        'lifterlms' => [
            'id' => 'lifterlms',
            'label' => 'LifterLMS',
            'approx_settings' => 41,
            'description' => 'LifterLMS course / lesson / member layout overrides.',
            'conditional_on' => 'lifterlms',
        ],
    ];
}

// ---------------------------------------------------------------------------
// Customizer setting allowlist. THE curated set of theme_mods the agent
// can read via `oceanwp-get-settings` and (in Sprint 2) write via
// `oceanwp-set-customizer-settings`. Anything outside this list must go
// through `novamira/execute-php` (per the user rule: simple things in
// execute-php, not bloated abilities). Tier-1 high-leverage settings only.
// ---------------------------------------------------------------------------

/**
 * @return array<string, array{
 *   type: 'boolean'|'integer'|'string'|'enum'|'color'|'typography',
 *   panel: string,
 *   description: string,
 *   enum?: list<string>
 * }>
 */
// @mago-expect lint:halstead
function ow_customizer_allowlist(): array
{
    /** @var array<string, array{type: 'boolean'|'integer'|'string'|'enum'|'color'|'typography', panel: string, description: string, enum?: list<string>}>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return [
        // Global layout & background.
        'ocean_main_layout_style' => [
            'type' => 'enum',
            'panel' => 'general',
            'description' => 'Site container layout style.',
            'enum' => ['boxed', 'separate', 'full-width'],
        ],
        'ocean_background_color' => [
            'type' => 'color',
            'panel' => 'general',
            'description' => 'Outer site background color (used when layout is boxed/separate).',
        ],
        'ocean_main_background_color' => [
            'type' => 'color',
            'panel' => 'general',
            'description' => 'Content area background color.',
        ],

        // Scroll-to-top.
        'ocean_scroll_top' => [
            'type' => 'boolean',
            'panel' => 'general',
            'description' => 'Enable the floating "scroll to top" button.',
        ],

        // Typography (clusters; values are objects shaped {font-family, font-weight, font-size, ...}).
        'ocean_body_typography' => [
            'type' => 'typography',
            'panel' => 'typography',
            'description' => 'Body typography (font-family, font-weight, font-size, line-height, letter-spacing).',
        ],
        'ocean_headings_typography' => [
            'type' => 'typography',
            'panel' => 'typography',
            'description' => 'Headings typography (applies to h1–h6 unless overridden).',
        ],
        'ocean_h1_typography' => [
            'type' => 'typography',
            'panel' => 'typography',
            'description' => 'H1 typography override.',
        ],
        'ocean_h2_typography' => [
            'type' => 'typography',
            'panel' => 'typography',
            'description' => 'H2 typography override.',
        ],

        // Colors.
        'ocean_primary_color' => [
            'type' => 'color',
            'panel' => 'colors',
            'description' => 'Primary brand color used across links, buttons, accents.',
        ],
        'ocean_hover_primary_color' => [
            'type' => 'color',
            'panel' => 'colors',
            'description' => 'Hover variant of the primary color.',
        ],
        'ocean_links_color' => [
            'type' => 'color',
            'panel' => 'colors',
            'description' => 'Default link color.',
        ],
        'ocean_links_color_hover' => [
            'type' => 'color',
            'panel' => 'colors',
            'description' => 'Default link hover color.',
        ],
        'ocean_border_color' => [
            'type' => 'color',
            'panel' => 'colors',
            'description' => 'Default border color used across UI elements.',
        ],

        // Header.
        'ocean_header_style' => [
            'type' => 'enum',
            'panel' => 'header',
            'description' => 'Header layout style.',
            'enum' => ['minimal', 'transparent', 'top', 'full_screen', 'center', 'medium', 'vertical', 'custom'],
        ],
        'ocean_header_height' => [
            'type' => 'integer',
            'panel' => 'header',
            'description' => 'Header height in pixels.',
        ],
        'ocean_has_transparent_header' => [
            'type' => 'boolean',
            'panel' => 'header',
            'description' => 'Make the page header transparent (only effective with header style "transparent").',
        ],
        'ocean_custom_header_template' => [
            'type' => 'integer',
            'panel' => 'header',
            'description' => 'Library template id used as a custom header (only used when header style is "custom").',
        ],
        'ocean_menu_typography_font_family' => [
            'type' => 'string',
            'panel' => 'header',
            'description' => 'Menu font family.',
        ],

        // Top bar.
        'ocean_top_bar' => [
            'type' => 'boolean',
            'panel' => 'topbar',
            'description' => 'Enable the top bar.',
        ],
        'ocean_top_bar_style' => [
            'type' => 'enum',
            'panel' => 'topbar',
            'description' => 'Top bar layout style.',
            'enum' => ['one', 'two', 'three', 'four', 'five'],
        ],

        // Page title & breadcrumbs.
        'ocean_page_header_heading' => [
            'type' => 'boolean',
            'panel' => 'page',
            'description' => 'Show the page title heading on single posts and pages.',
        ],
        'ocean_breadcrumbs' => [
            'type' => 'boolean',
            'panel' => 'page',
            'description' => 'Show breadcrumbs in the page header.',
        ],
        'ocean_breadcrumbs_separator' => [
            'type' => 'string',
            'panel' => 'page',
            'description' => 'Breadcrumb separator string (e.g. "/", "→").',
        ],

        // Blog.
        'ocean_blog_archives_layout' => [
            'type' => 'enum',
            'panel' => 'blog',
            'description' => 'Blog archive layout.',
            'enum' => ['large-entry', 'grid-entry', 'list-entry', 'thumb-entry'],
        ],
        'ocean_blog_grid_columns' => [
            'type' => 'integer',
            'panel' => 'blog',
            'description' => 'Number of columns for grid blog layout.',
        ],
        'ocean_blog_single_layout' => [
            'type' => 'enum',
            'panel' => 'blog',
            'description' => 'Default single post layout (sidebar position / full width / full screen).',
            'enum' => ['right-sidebar', 'left-sidebar', 'full-width', 'full-screen', 'both-sidebars'],
        ],

        // Footer widgets.
        'ocean_footer_widgets' => [
            'type' => 'boolean',
            'panel' => 'footer_widgets',
            'description' => 'Enable the footer widgets row.',
        ],
        'ocean_footer_widgets_columns' => [
            'type' => 'enum',
            'panel' => 'footer_widgets',
            'description' => 'Number of footer widget columns.',
            'enum' => ['1', '2', '3', '4', '5'],
        ],

        // Footer copyright.
        'ocean_footer_copyright_text' => [
            'type' => 'string',
            'panel' => 'footer_copyright',
            'description' => 'Footer copyright HTML.',
        ],
        'ocean_footer_copyright_visibility' => [
            'type' => 'boolean',
            'panel' => 'footer_copyright',
            'description' => 'Show the footer copyright row.',
        ],

        // Performance.
        'ocean_disable_fontawesome' => [
            'type' => 'boolean',
            'panel' => 'performance',
            'description' => 'Stop enqueueing the bundled FontAwesome CSS.',
        ],
        'ocean_disable_svg_icons' => [
            'type' => 'boolean',
            'panel' => 'performance',
            'description' => 'Stop enqueueing the bundled SVG icons sprite.',
        ],
        'ocean_disable_lightbox' => [
            'type' => 'boolean',
            'panel' => 'performance',
            'description' => 'Disable the gallery lightbox script.',
        ],
        'ocean_disable_emoji' => [
            'type' => 'boolean',
            'panel' => 'performance',
            'description' => 'Disable the WordPress emoji script and styles.',
        ],

        // WooCommerce — Sprint 3 sets these via the dedicated `set-woo-features`
        // ability, but they ARE in the read-allowlist so `get-settings` can
        // surface them.
        'ocean_woo_off_canvas_filter' => [
            'type' => 'boolean',
            'panel' => 'woocommerce',
            'description' => 'Enable the off-canvas product filter sidebar on shop archives.',
        ],
        'ocean_woo_distraction_free_cart' => [
            'type' => 'boolean',
            'panel' => 'woocommerce',
            'description' => 'Hide header / footer on the cart page.',
        ],
        'ocean_woo_distraction_free_checkout' => [
            'type' => 'boolean',
            'panel' => 'woocommerce',
            'description' => 'Hide header / footer on the checkout page.',
        ],
        'ocean_woo_quick_view' => [
            'type' => 'boolean',
            'panel' => 'woocommerce',
            'description' => 'Enable the product quick view modal on shop archives.',
        ],
        'ocean_woo_display_floating_bar' => [
            'type' => 'enum',
            'panel' => 'woocommerce',
            'description' => 'Display the sticky add-to-cart bar on single product pages.',
            'enum' => ['enable', 'mobile', 'disable'],
        ],
        'ocean_woo_products_style' => [
            'type' => 'enum',
            'panel' => 'woocommerce',
            'description' => 'Product image hover behavior.',
            'enum' => ['default', 'hover'],
        ],
        'ocean_woo_product_ajax_add_to_cart' => [
            'type' => 'boolean',
            'panel' => 'woocommerce',
            'description' => 'Enable AJAX add-to-cart on single product pages.',
        ],
        'ocean_menu_cart_style' => [
            'type' => 'enum',
            'panel' => 'woocommerce',
            'description' => 'Menu cart icon style.',
            'enum' => ['icon', 'icon_count', 'icon_total', 'bag'],
        ],
        'ocean_woo_add_mobile_mini_cart' => [
            'type' => 'boolean',
            'panel' => 'woocommerce',
            'description' => 'Show the mobile mini-cart bar.',
        ],
    ];
}

// ---------------------------------------------------------------------------
// Hook catalog. Curated subset of the ~158 actions + ~314 filters OceanWP
// exposes — only the layout-level hooks an agent realistically wants to
// attach to via `novamira/execute-php` snippets. The full grep-able list
// is still visible in the theme source; this catalog is the "agent-useful"
// surface promoted into the SKILL.md vocabulary.
// ---------------------------------------------------------------------------

/**
 * @return array<int, array{
 *   name: string,
 *   type: 'action'|'filter',
 *   group: string,
 *   description: string
 * }>
 */
// @mago-expect lint:halstead
function ow_hook_catalog(): array
{
    /** @var list<array{name: string, type: 'action'|'filter', group: string, description: string}>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return [
        // Layout / outer wrap.
        [
            'name' => 'ocean_before_outer_wrap',
            'type' => 'action',
            'group' => 'layout',
            'description' => 'Fires just before the outermost site wrapper opens. Use to inject site-wide banners.',
        ],
        [
            'name' => 'ocean_after_outer_wrap',
            'type' => 'action',
            'group' => 'layout',
            'description' => 'Fires after the outermost site wrapper closes.',
        ],
        // Header.
        [
            'name' => 'ocean_before_header',
            'type' => 'action',
            'group' => 'header',
            'description' => 'Fires just before the <header> opens.',
        ],
        [
            'name' => 'ocean_after_header',
            'type' => 'action',
            'group' => 'header',
            'description' => 'Fires just after the <header> closes — common slot for promo bars.',
        ],
        [
            'name' => 'ocean_display_header',
            'type' => 'filter',
            'group' => 'header',
            'description' => 'Boolean filter — return false to suppress the header for the current request.',
        ],
        [
            'name' => 'ocean_custom_header_template',
            'type' => 'filter',
            'group' => 'header',
            'description' => 'Override the Library post id used as the custom header template.',
        ],
        // Logo / nav.
        [
            'name' => 'ocean_after_logo',
            'type' => 'action',
            'group' => 'header',
            'description' => 'Fires after the site logo container — slot for inline contact info.',
        ],
        [
            'name' => 'ocean_after_nav',
            'type' => 'action',
            'group' => 'header',
            'description' => 'Fires after the primary nav — slot for icons / CTAs.',
        ],
        // Top bar.
        [
            'name' => 'ocean_display_top_bar',
            'type' => 'filter',
            'group' => 'topbar',
            'description' => 'Boolean filter — return false to suppress the top bar for the current request.',
        ],
        // Page header.
        [
            'name' => 'ocean_before_page_header',
            'type' => 'action',
            'group' => 'page',
            'description' => 'Fires just before the page header / title bar.',
        ],
        [
            'name' => 'ocean_after_page_header',
            'type' => 'action',
            'group' => 'page',
            'description' => 'Fires just after the page header / title bar.',
        ],
        [
            'name' => 'ocean_display_page_header',
            'type' => 'filter',
            'group' => 'page',
            'description' => 'Boolean filter — return false to suppress the page header.',
        ],
        [
            'name' => 'ocean_display_breadcrumbs',
            'type' => 'filter',
            'group' => 'page',
            'description' => 'Boolean filter — return false to suppress breadcrumbs.',
        ],
        // Main / content.
        [
            'name' => 'ocean_before_main',
            'type' => 'action',
            'group' => 'main',
            'description' => 'Fires just before the <main> opens.',
        ],
        [
            'name' => 'ocean_after_main',
            'type' => 'action',
            'group' => 'main',
            'description' => 'Fires just after the <main> closes.',
        ],
        [
            'name' => 'ocean_before_content',
            'type' => 'action',
            'group' => 'main',
            'description' => 'Fires just before single post / page content opens.',
        ],
        [
            'name' => 'ocean_after_content',
            'type' => 'action',
            'group' => 'main',
            'description' => 'Fires just after single post / page content closes.',
        ],
        // Footer.
        [
            'name' => 'ocean_before_footer',
            'type' => 'action',
            'group' => 'footer',
            'description' => 'Fires just before the <footer> opens.',
        ],
        [
            'name' => 'ocean_after_footer',
            'type' => 'action',
            'group' => 'footer',
            'description' => 'Fires just after the <footer> closes.',
        ],
        [
            'name' => 'ocean_display_footer_widgets',
            'type' => 'filter',
            'group' => 'footer',
            'description' => 'Boolean filter — return false to suppress the footer widgets row.',
        ],
        [
            'name' => 'ocean_display_footer_bottom',
            'type' => 'filter',
            'group' => 'footer',
            'description' => 'Boolean filter — return false to suppress the footer copyright row.',
        ],
        // Scroll up.
        [
            'name' => 'ocean_display_scroll_up_button',
            'type' => 'filter',
            'group' => 'general',
            'description' => 'Boolean filter — return false to suppress the floating scroll-to-top button.',
        ],
    ];
}
