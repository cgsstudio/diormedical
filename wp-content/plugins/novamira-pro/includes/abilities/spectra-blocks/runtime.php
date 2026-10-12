<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraBlocks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime for the Spectra Blocks integration.
 *
 * Covers the NEW Spectra Blocks plugin pair (free `spectra-blocks` +
 * `spectra-blocks-pro`, Brainstorm Force's ground-up rewrite) — NOT the
 * legacy Spectra (UAGB) plugin, which keeps its own specialization under
 * `includes/abilities/spectra/`. The availability gates —
 * `spectra_blocks_plugin_available()` and `spectra_blocks_pro_active()` —
 * live in `includes/gates.php` so the manifest entry and the loader can
 * share one implementation without pulling this file. This runtime exposes
 * the narrow output-shaping helpers every ability needs: the block-registry
 * snapshot, the block-family classifier, the activation-map reader, and
 * small type-safe coercers.
 */

/**
 * Classify a block name into the Spectra Blocks family. Returns one of:
 *  - `'free'` — namespace `spectra/*` (the free plugin's 48 blocks; Pro also
 *              contributes one `spectra/*` child block, which deliberately
 *              classifies as `free` here — family is a namespace fact, not a
 *              which-plugin-registered-it fact)
 *  - `'pro'`  — namespace `spectra-pro/*` (Spectra Blocks Pro blocks)
 *  - `'unknown'` — anything else, including the legacy `uagb/*` namespace.
 */
function sb_block_family(string $block_name): string
{
    if (str_starts_with($block_name, 'spectra/')) {
        return 'free';
    }
    if (str_starts_with($block_name, 'spectra-pro/')) {
        return 'pro';
    }
    return 'unknown';
}

/**
 * Snapshot of the WP block-type registry keyed by block name, filtered to
 * the `spectra/*` + `spectra-pro/*` namespaces. Spectra Blocks registers
 * every block from its built per-block `block.json` metadata via
 * `register_block_type_from_metadata`, so — unlike legacy UAGB with its
 * internal `$block_list` — the WP registry is the single source of truth
 * here; no plugin-internal list needs merging.
 *
 * Memoised per-request: the registry is populated during `init` (before
 * `wp_abilities_api_init` fires our callbacks) and treated as immutable for
 * the rest of the request, so chained ability calls (`check-setup` →
 * `list-blocks`) skip the full-registry iteration on the 2nd hop.
 *
 * @return array<string, \WP_Block_Type>
 */
function sb_registry_index(): array
{
    /** @var array<string, \WP_Block_Type>|null $cache */
    static $cache = null;
    if ($cache === null) {
        $cache = sb_registry_index_compute();
    }
    return $cache;
}

/**
 * Uncached compute path for `sb_registry_index()` — kept as its own function
 * so the memoiser stays a two-line entry point.
 *
 * @return array<string, \WP_Block_Type>
 */
function sb_registry_index_compute(): array
{
    if (!class_exists('\\WP_Block_Type_Registry')) {
        return [];
    }
    $all = \WP_Block_Type_Registry::get_instance()->get_all_registered();
    $out = [];
    foreach ($all as $name => $block_type) {
        $key = (string) $name;
        if (sb_block_family($key) === 'unknown') {
            continue;
        }
        $out[$key] = $block_type;
    }
    return $out;
}

/**
 * Read the `_spectra_blocks_blocks` activation map. Keys are UNPREFIXED free
 * block slugs (`container`, not `spectra/container`). The plugin writes two
 * vocabularies into the same option: its native toggle ability stores
 * `'yes'` / `'no'`, while the dashboard path stores `'disabled'` for a
 * deactivated block (its reader treats exactly `'disabled'` as inactive) and
 * seeds slug-echo values (`'container' => 'container'`) meaning "active".
 * Callers must therefore treat BOTH `'no'` and `'disabled'` as deactivated
 * and every other value as active — `sb_block_is_active()` encodes that
 * contract. Blocks absent from the map are active by default.
 *
 * @return array<string, string>
 */
function sb_activation_map(): array
{
    /** @var mixed $raw */
    $raw = get_option(option: SPECTRA_BLOCKS_ACTIVATION_OPTION, default_value: []);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $slug => $value) {
        if (!is_string($slug) || $slug === '') {
            continue;
        }
        if (is_string($value)) {
            $out[$slug] = $value;
            continue;
        }
        if (is_bool($value)) {
            // Defensive: a boolean written by a third-party tool still maps
            // onto the yes/no contract instead of silently disappearing.
            $out[$slug] = $value ? 'yes' : 'no';
        }
    }
    return $out;
}

/**
 * Whether a registered block counts as active per the activation map. Only
 * free `spectra/*` blocks participate in the map (the plugin strips the
 * `spectra/` prefix before keying); `spectra-pro/*` blocks have no
 * activation registry and always report active. Absent map entry = active
 * (the plugin's own default); the stored values `'no'` (native toggle
 * ability) and `'disabled'` (dashboard path) mean deactivated, everything
 * else is active — see `sb_activation_map()` for the dual vocabulary.
 *
 * @param array<string, string> $map result of `sb_activation_map()`
 */
function sb_block_is_active(string $block_name, array $map): bool
{
    if (!str_starts_with($block_name, 'spectra/')) {
        return true;
    }
    $slug = substr($block_name, offset: strlen('spectra/'));
    if (!isset($map[$slug])) {
        return true;
    }
    return !in_array($map[$slug], ['no', 'disabled'], strict: true);
}

/**
 * Read a `spectra_blocks_{key}` settings option with the plugin's own
 * fallback semantics: site option on multisite when the per-blog row is
 * absent, then the provided default. Mirrors `Spectra_Blocks_Settings::get`
 * without calling into the plugin class, so smoke bootstrap paths that run
 * before the plugin's admin surface loads still resolve.
 */
function sb_setting(string $key, mixed $default_value = false): mixed
{
    $option_name = 'spectra_blocks_' . $key;
    /** @var mixed $value */
    $value = get_option(option: $option_name, default_value: null);
    if ($value !== null) {
        return $value;
    }
    if (is_multisite()) {
        return get_site_option(option: $option_name, default_value: $default_value);
    }
    return $default_value;
}

/**
 * Coerce a mixed option value to its canonical string form so ability output
 * stays stable regardless of how the plugin serialised the storage (native
 * strings, cast booleans, numerics).
 */
function sb_option_to_string(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }
    if (is_bool($value)) {
        return $value ? 'enabled' : 'disabled';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return '';
}

/**
 * Coerce a mixed input to int with a floor. Used by list-input schemas that
 * accept `limit` / `offset` — MCP clients occasionally serialise numeric
 * parameters as strings, and PHP's own casting would silently turn `'abc'`
 * into `0` without a floor.
 */
function sb_coerce_int(mixed $value, int $minimum = 0): int
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
 * body and a `truncated` boolean flag — progressive disclosure for
 * potentially-large text fields (popup block markup). The cap is always
 * enforced: values outside [1, SPECTRA_BLOCKS_MAX_FIELD_BYTES_CAP] are
 * clamped to the hard maximum, so no caller can request unbounded output.
 * The cap is measured in bytes; if the byte cut would land inside a
 * multi-byte UTF-8 codepoint the helper rewinds past the incomplete final
 * sequence so `json_encode` never rejects the response with
 * `JSON_ERROR_UTF8`.
 *
 * @return array{body:string, truncated:bool}
 */
function sb_truncate_body(string $body, int $max_bytes): array
{
    if ($max_bytes < 1 || $max_bytes > SPECTRA_BLOCKS_MAX_FIELD_BYTES_CAP) {
        $max_bytes = SPECTRA_BLOCKS_MAX_FIELD_BYTES_CAP;
    }
    if (strlen($body) <= $max_bytes) {
        return ['body' => $body, 'truncated' => false];
    }
    $cut = substr($body, offset: 0, length: $max_bytes);
    return ['body' => substr($cut, offset: 0, length: sb_utf8_safe_length($cut)), 'truncated' => true];
}

/**
 * Length of `$cut` with any INCOMPLETE trailing UTF-8 sequence removed. A
 * final code point that is fully present — even a multi-byte one whose last
 * byte is a continuation byte — is kept: the helper walks back over at most
 * three continuation bytes to the lead byte, derives the sequence length the
 * lead byte declares, and only trims when fewer bytes are present than
 * declared. Malformed input (continuation bytes with no lead, ASCII followed
 * by continuation bytes) is returned unchanged — truncation must not try to
 * repair strings that were never valid UTF-8.
 */
function sb_utf8_safe_length(string $cut): int
{
    $len = strlen($cut);
    $i = $len - 1;
    $continuation = 0;
    while ($i >= 0 && $continuation < 3 && (ord($cut[$i]) & 0xC0) === 0x80) {
        $i--;
        $continuation++;
    }
    if ($i < 0) {
        return $len;
    }
    $lead = ord($cut[$i]);
    if ($lead < 0xC0) {
        return $len;
    }
    $declared = 2;
    if ($lead >= 0xE0) {
        $declared = 3;
    }
    if ($lead >= 0xF0) {
        $declared = 4;
    }
    return ($continuation + 1) < $declared ? $i : $len;
}

/**
 * Default per-field byte cap for progressive disclosure. Popup block trees
 * run a few KiB in practice; 20 000 bytes keeps the full tree for normal
 * popups while bounding pathological ones. Callers override via a
 * per-ability `max_field_bytes` input up to the hard maximum below.
 */
const SPECTRA_BLOCKS_DEFAULT_MAX_FIELD_BYTES = 20_000;

/**
 * Hard maximum any caller can raise a per-field byte cap to. There is no
 * uncapped mode: a single field response is always bounded so one popup can
 * never flood the agent context. Callers that hit the ceiling see the
 * `truncated` flag and know the stored content is larger.
 */
const SPECTRA_BLOCKS_MAX_FIELD_BYTES_CAP = 100_000;

/**
 * Post type slug for the Popup Builder CPT registered by Spectra Blocks.
 * Kept as a constant so ability files never repeat the literal.
 */
const SPECTRA_BLOCKS_POPUP_POST_TYPE = 'spectra-blocks-popup';

/**
 * Migration-compat popup CPT. Spectra Blocks re-registers the `spectra-popup`
 * CPT (used by the UAGB spectra-v3 beta) when nothing else has, so popups
 * created in the beta remain editable. Sprint 1 only counts these in
 * check-setup — list/get target the canonical CPT above.
 */
const SPECTRA_BLOCKS_COMPAT_POPUP_POST_TYPE = 'spectra-popup';

/**
 * Popup meta keys registered by Spectra Blocks (`register_post_meta` in
 * `includes/Blocks/class-popup-builder.php`), with their registered
 * defaults: type `'unset'`, enabled `false`, repetition `1`.
 */
const SPECTRA_BLOCKS_POPUP_META_TYPE = 'spectra-blocks-popup-type';

const SPECTRA_BLOCKS_POPUP_META_ENABLED = 'spectra-blocks-popup-enabled';

const SPECTRA_BLOCKS_POPUP_META_REPETITION = 'spectra-blocks-popup-repetition';

/**
 * Option key of the block activation map. Keys are unprefixed free block
 * slugs; the literal values `'no'` (native toggle ability) and `'disabled'`
 * (dashboard path) mean deactivated, anything else is active. See
 * `sb_activation_map()`.
 */
const SPECTRA_BLOCKS_ACTIVATION_OPTION = '_spectra_blocks_blocks';

/**
 * Option key of the Global Block Styles store. Shared cross-plugin between
 * Spectra Blocks and Spectra Blocks Pro (the plugin documents the key as an
 * intentional shared surface). Each entry carries `blockName`, style values,
 * and `post_ids` usage backrefs — abilities summarise it, never inline it.
 */
const SPECTRA_BLOCKS_GBS_OPTION = 'spectra_global_block_styles';
