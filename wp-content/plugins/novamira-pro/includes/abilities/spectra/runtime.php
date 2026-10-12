<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime for the Spectra (UAGB) integration.
 *
 * The availability gates themselves — `spectra_plugin_available()` and
 * `spectra_pro_active()` — live in `includes/gates.php` so the manifest entry
 * and the loader can share one implementation without pulling this file. This
 * runtime exposes the narrow output-shaping helpers every ability needs:
 * version strings, the admin-settings option wrapper, block registry reads,
 * license mode, and small type-safe coercers.
 */

/**
 * Spectra plugin version string, or '' when the plugin is inactive. Public
 * accessor over the `UAGB_VER` constant with an is-defined guard so
 * output-shaping code paths never fatal on missing constants. The Pro version
 * accessor (`spectra_pro_version()`) lives in `includes/gates.php` alongside
 * its gate helper to keep the plugin-vs-Pro identity questions in one place.
 */
function spectra_version(): string
{
    return defined('UAGB_VER') ? (string) constant('UAGB_VER') : '';
}

/**
 * Read a Spectra admin-settings option. Wraps
 * `UAGB_Admin_Helper::get_admin_settings_option` when available, and falls
 * back to the raw `get_option()` when the helper class is not loaded (e.g.
 * during smoke bootstrap before the plugin's admin surface fires). The
 * return type is deliberately `mixed` — callers coerce with the small
 * `spectra_coerce_*` helpers below.
 */
function spectra_admin_settings_option(string $key, mixed $default_value = false): mixed
{
    // UAGB_Admin_Helper::get_admin_settings_option is defined by the plugin
    // (see /classes/class-uagb-admin-helper.php), but Novamira Pro's static
    // stubs deliberately do NOT declare it — UAGB is external. Dispatch via
    // the callable + `is_callable` guard so the analyzer sees a proper
    // conditional call instead of a non-existent method.
    /** @var callable-string|array{class-string, string} $callable */
    $callable = ['\\UAGB_Admin_Helper', 'get_admin_settings_option'];
    if (is_callable($callable)) {
        return $callable($key, $default_value);
    }
    return get_option(option: $key, default_value: $default_value);
}

/**
 * Classify a block name into the Spectra family. Returns one of:
 *  - `'free'` — namespace `uagb/*` (the free plugin's block registry)
 *  - `'pro'`  — namespace `spectra-pro/*` (Pro add-on blocks) OR any block
 *              whose registration category the plugin marks as `spectra-pro`
 *  - `'unknown'` — anything else. Callers surface this as `spectra_block_not_found`.
 *
 * Kept off the availability gates so a mistyped `block_name` input never
 * flips the whole-surface gate — invalid inputs stay in the callback's
 * WP_Error path.
 */
function spectra_block_family(string $block_name): string
{
    if (str_starts_with($block_name, 'uagb/')) {
        return 'free';
    }
    if (str_starts_with($block_name, 'spectra-pro/')) {
        return 'pro';
    }
    return 'unknown';
}

/**
 * Enumerate the Spectra blocks known to this install. Two sources are
 * merged, in this order of authority:
 *
 *   1. `UAGB_Helper::$block_list` — the canonical UAGB internal registry.
 *      Contains ALL Spectra blocks (~77 on free UAGB, more on Pro),
 *      including the static-render blocks that never call `register_block_type`
 *      from PHP. This is the source of truth for the agent-visible catalogue
 *      because those static blocks are perfectly usable in `post_content`
 *      markup even though the PHP registry never sees them.
 *   2. `WP_Block_Type_Registry` — populated only for blocks that register a
 *      PHP `render_callback` (dynamic-render blocks like `uagb/forms`,
 *      `uagb/post-grid`, `uagb/popup-builder`, …). Used ONLY to enrich each
 *      record with the `dynamic_render` flag and to resolve `category`.
 *
 * Blocks the user has disabled via UAGB's `_uagb_blocks` registry stay in the
 * result set — the ability reports what the install *supports*, not what a
 * specific editor session happens to show; callers use the deactivation
 * registry (Sprint 2's `set-block-activation`) for the toggled state.
 *
 * @return array<int, array<string, mixed>> compact records
 *   (name, family, category, dynamic_render, title)
 */
function spectra_registered_blocks(string $family = 'all'): array
{
    $registry_types = spectra_php_registry_index();
    $uagb_list = spectra_uagb_block_list();

    $out = [];
    foreach ($uagb_list as $name => $meta) {
        $record = spectra_make_block_record($name, $family, $registry_types[$name] ?? null, $meta);
        if ($record !== null) {
            $out[] = $record;
        }
    }

    // Merge in any Spectra-namespaced block registered via the WP registry
    // that UAGB's own list didn't declare — covers custom third-party
    // extensions in the same namespace and Pro blocks that UAGB does not
    // pre-declare in `$block_list`.
    foreach ($registry_types as $name => $block_type) {
        if (isset($uagb_list[$name])) {
            continue;
        }
        $record = spectra_make_block_record($name, $family, $block_type, uagb_meta: null);
        if ($record !== null) {
            $out[] = $record;
        }
    }

    return $out;
}

/**
 * Build one compact block record from the two possible sources. Returns
 * `null` when the block should be excluded (unknown namespace or the
 * caller's `$family` filter rejects it) so the caller stays a flat
 * `if ($record !== null)` append.
 *
 * @param array<array-key, mixed>|null $uagb_meta
 * @return array<string, mixed>|null
 */
function spectra_make_block_record(
    string $name,
    string $family_filter,
    ?\WP_Block_Type $block_type,
    ?array $uagb_meta,
): ?array {
    $fam = spectra_block_family($name);
    if ($fam === 'unknown') {
        return null;
    }
    if ($family_filter !== 'all' && $family_filter !== $fam) {
        return null;
    }
    return [
        'name' => $name,
        'family' => $fam,
        'category' => $block_type !== null ? $block_type->category ?? 'uagb' : 'uagb',
        'dynamic_render' => $block_type !== null && spectra_block_is_dynamic($block_type),
        'title' => spectra_pick_block_title($name, $block_type, $uagb_meta),
    ];
}

/**
 * Prefer the PHP registry's title (localised via `register_block_type`)
 * over the UAGB internal-list title (raw English label) over the raw
 * block name.
 *
 * @param array<array-key, mixed>|null $uagb_meta
 */
function spectra_pick_block_title(string $name, ?\WP_Block_Type $block_type, ?array $uagb_meta): string
{
    if ($block_type !== null && $block_type->title !== '') {
        return $block_type->title;
    }
    if ($uagb_meta !== null) {
        /** @var mixed $title */
        $title = $uagb_meta['title'] ?? null;
        if (is_string($title) && $title !== '') {
            return $title;
        }
    }
    return $name;
}

/**
 * Read `UAGB_Helper::$block_list` when the plugin runtime is loaded,
 * returning an empty map otherwise. Kept as a separate helper so
 * `spectra_registered_blocks()` stays focused on the merge logic and so
 * static analysis sees a clean `class_exists` gate instead of a
 * bare-string property access.
 *
 * Memoised per-request: `UAGB_Helper::$block_list` is populated once during
 * plugin bootstrap and never mutated in-request, so a static cache lets
 * chained ability calls (`check-setup` → `list-blocks` → `get-block-schema`)
 * skip the reflection + iteration on the 2nd + 3rd hop.
 *
 * @return array<string, array<array-key, mixed>>
 */
function spectra_uagb_block_list(): array
{
    /** @var array<string, array<array-key, mixed>>|null $cache */
    static $cache = null;
    if ($cache === null) {
        $cache = spectra_uagb_block_list_compute();
    }
    return $cache;
}

/**
 * Uncached compute path for `spectra_uagb_block_list()` — kept as its own
 * function so the memoiser stays a two-line entry point and the analyzer
 * sees a single unconditional return here.
 *
 * @return array<string, array<array-key, mixed>>
 */
function spectra_uagb_block_list_compute(): array
{
    if (!class_exists('\\UAGB_Helper') || !property_exists('\\UAGB_Helper', 'block_list')) {
        return [];
    }
    // Read the static property via reflection to keep static analysis happy
    // when the UAGB stubs do not declare `$block_list` — the property is
    // documented public API of UAGB and this is the same access strategy
    // used by every other UAGB downstream integration.
    $ref = new \ReflectionClass('\\UAGB_Helper');
    if (!$ref->hasProperty('block_list')) {
        return [];
    }
    /** @var mixed $list */
    $list = $ref->getProperty('block_list')->getValue();
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    /** @var mixed $meta */
    foreach ($list as $name => $meta) {
        if (!is_string($name) || $name === '') {
            continue;
        }
        $out[$name] = is_array($meta) ? $meta : [];
    }
    return $out;
}

/**
 * Snapshot of the WP block-type registry keyed by block name, filtered
 * to Spectra namespaces only. Kept separate from
 * `spectra_registered_blocks()` so the merge helper stays short.
 *
 * Memoised per-request: the WP block-type registry is populated during
 * `init` (way before `wp_abilities_api_init` fires our callbacks) and
 * treated as immutable for the rest of the request. Reusing the snapshot
 * across chained ability calls in the same request avoids reiterating
 * every registered block type twice.
 *
 * @return array<string, \WP_Block_Type>
 */
function spectra_php_registry_index(): array
{
    /** @var array<string, \WP_Block_Type>|null $cache */
    static $cache = null;
    if ($cache === null) {
        $cache = spectra_php_registry_index_compute();
    }
    return $cache;
}

/**
 * Uncached compute path for `spectra_php_registry_index()`. Same rationale
 * as `spectra_uagb_block_list_compute()`.
 *
 * @return array<string, \WP_Block_Type>
 */
function spectra_php_registry_index_compute(): array
{
    if (!class_exists('\\WP_Block_Type_Registry')) {
        return [];
    }
    $all = \WP_Block_Type_Registry::get_instance()->get_all_registered();
    $out = [];
    foreach ($all as $name => $block_type) {
        $key = (string) $name;
        if (spectra_block_family($key) === 'unknown') {
            continue;
        }
        $out[$key] = $block_type;
    }
    return $out;
}

/**
 * Whether a block type registers a PHP `render_callback`. WP_Block_Type's
 * stub types `render_callback` as `callable(...mixed): mixed` — which the
 * analyzer reads as never-null — so introspecting the raw property with
 * reflection lets us tell the "no callback set" case apart from "an actual
 * closure was set" without confusing static analysis.
 */
function spectra_block_is_dynamic(\WP_Block_Type $block_type): bool
{
    $ref = new \ReflectionProperty($block_type, 'render_callback');
    /** @var mixed $value */
    $value = $ref->getValue($block_type);
    return $value !== null;
}

/**
 * Novamira Pro license mode, mirrored on the base plugin's stored status.
 * Returns 'pro' when the license is active, 'free' otherwise. Kept in the
 * runtime so check-setup can report it without pulling licensing.php.
 *
 * @return 'free'|'pro'
 */
function spectra_license_mode(): string
{
    /** @var mixed $status */
    $status = get_option(option: 'nvp_license_status', default_value: 'inactive');
    return $status === 'active' ? 'pro' : 'free';
}

/**
 * Coerce a mixed input to int with a floor. Used by list-input schemas
 * that accept `limit` / `offset` — MCP clients occasionally serialise
 * numeric parameters as strings, and PHP's own casting would silently
 * turn `'abc'` into `0` without a floor. Keep the floor here.
 */
function spectra_coerce_int(mixed $value, int $minimum = 0): int
{
    if (is_int($value)) {
        return $value >= $minimum ? $value : $minimum;
    }
    if (is_string($value) && is_numeric($value)) {
        $int = (int) $value;
        return $int >= $minimum ? $int : $minimum;
    }
    return $minimum;
}

/**
 * Truncate a body string at a byte cap and return both the (possibly cut)
 * body and a `truncated` boolean flag. Every ability that surfaces
 * post_content, submission payloads, or any potentially-large text field
 * clamps through this helper — progressive disclosure per plan. A cap of
 * `0` disables truncation entirely (callers pass it when they explicitly
 * want the full body). The cap is measured in bytes; if the naive byte cut
 * would land inside a multi-byte UTF-8 codepoint the helper rewinds to the
 * previous codepoint boundary so `json_encode` never rejects the response
 * with `JSON_ERROR_UTF8`.
 *
 * @return array{body:string, truncated:bool}
 */
function spectra_truncate_body(string $body, int $max_bytes): array
{
    if ($max_bytes <= 0) {
        return ['body' => $body, 'truncated' => false];
    }
    if (strlen($body) <= $max_bytes) {
        return ['body' => $body, 'truncated' => false];
    }
    $cut = substr($body, offset: 0, length: $max_bytes);
    // Rewind to the last well-formed UTF-8 codepoint boundary if the naive
    // cut landed inside a multi-byte sequence. Continuation bytes have the
    // high bits `10xxxxxx`; strip any trailing run of them plus a partial
    // lead byte to keep the output valid UTF-8.
    $len = strlen($cut);
    while ($len > 0) {
        $byte = ord($cut[$len - 1]);
        // Complete codepoint (single-byte ASCII or trailing continuation
        // has already been popped) → done.
        if ($byte < 0x80) {
            break;
        }
        // Lead byte of a multi-byte sequence — the sequence would need
        // more bytes than we have, so drop this partial lead too.
        if ($byte >= 0xC0) {
            $len--;
            break;
        }
        // Continuation byte — keep rewinding.
        $len--;
    }
    return ['body' => substr($cut, offset: 0, length: $len), 'truncated' => true];
}

/**
 * Default per-field byte cap for progressive disclosure. Balances agent
 * context frugality against the popup builder's real-world block trees,
 * which run 5–15 KiB in practice. Callers can override via a per-ability
 * `max_field_bytes` input; passing 0 disables the cap.
 */
const SPECTRA_DEFAULT_MAX_FIELD_BYTES = 20_000;

/**
 * Post type slug for the Popup Builder CPT registered by UAGB. Kept as a
 * constant so ability files never repeat the literal — a single grep
 * gives the authoritative definition.
 */
const SPECTRA_POPUP_POST_TYPE = 'spectra-popup';

/**
 * Popup meta keys registered by UAGB (`register_post_meta` in
 * `class-uagb-init-blocks.php`). Sub-set the free layer touches; Pro adds
 * more via `register_spectra_pro_popup_meta`.
 */
const SPECTRA_POPUP_META_TYPE = 'spectra-popup-type';

const SPECTRA_POPUP_META_ENABLED = 'spectra-popup-enabled';

const SPECTRA_POPUP_META_REPETITION = 'spectra-popup-repetition';
