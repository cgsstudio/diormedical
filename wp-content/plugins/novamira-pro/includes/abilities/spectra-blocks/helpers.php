<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraBlocks;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Spectra Blocks integration: uniform error factories
 * with stable codes tests assert on, and the popup CPT + meta accessors.
 */

// -----------------------------------------------------------------------------
// Error factories — stable codes; do not rename.
// -----------------------------------------------------------------------------

/**
 * Emitted by every non-check-setup ability when the Spectra Blocks plugin is
 * missing, below the min-supported floor, or refused to initialize (legacy
 * UAGB spectra-v3 conflict, WP/PHP requirement failure). Callers surface it
 * as the "install / activate / un-conflict the plugin" signal — the
 * check-setup ability is the one place that returns a shaped state instead.
 */
function sb_not_active(): WP_Error
{
    return new WP_Error(
        'spectra_blocks_not_active',
        __(
            'Spectra Blocks plugin is not active at or above the minimum supported version (or refused to initialize). Call novamira/spectra-blocks-check-setup for details.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * Uniform "input failed validation" error. `$detail` is the per-callsite
 * reason. Keep the code stable — smoke + audit assert on it.
 */
function sb_invalid_input(string $detail): WP_Error
{
    return new WP_Error('spectra_blocks_invalid_input', $detail, ['status' => 400]);
}

/**
 * Emitted whenever a popup id argument does not resolve to a post of the
 * Spectra Blocks popup CPT — auto-drafts, trashed posts, revisions, and
 * mismatched post types all end here.
 */
function sb_popup_not_found(int $id): WP_Error
{
    return new WP_Error(
        'spectra_blocks_popup_not_found',
        /* translators: %d: popup post id */
        sprintf(__('Spectra Blocks popup #%d not found or not a popup post.', domain: 'novamira-pro'), $id),
        ['status' => 404],
    );
}

// -----------------------------------------------------------------------------
// Popup CPT accessors.
// -----------------------------------------------------------------------------

/**
 * Resolve a popup id to a `WP_Post` of the `spectra-blocks-popup` post type
 * or return a `spectra_blocks_popup_not_found` error. Rejects auto-drafts,
 * trashed posts, and revisions per the standard gate every read path shares.
 * The migration-compat `spectra-popup` CPT is deliberately NOT accepted —
 * Sprint 1 only counts it in check-setup.
 */
function sb_post_or_error(int $id): WP_Post|WP_Error
{
    if ($id <= 0) {
        return sb_popup_not_found($id);
    }
    /** @var WP_Post|null $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post) {
        return sb_popup_not_found($id);
    }
    if ($post->post_type !== SPECTRA_BLOCKS_POPUP_POST_TYPE) {
        return sb_popup_not_found($id);
    }
    if (in_array($post->post_status, ['auto-draft', 'trash'], strict: true)) {
        return sb_popup_not_found($id);
    }
    return $post;
}

/**
 * Read the standard popup meta bundle in one call — returns the three
 * registered keys with the plugin's registered defaults (type `'unset'`,
 * enabled `false`, repetition `1`) so ability output never leaks an
 * inconsistent shape when the meta rows are missing (fresh popup before any
 * save). Repetition is registered as JSON type `number` and the plugin's
 * own readers keep fractional values, so the numeric value is preserved
 * as-is (`3` stays int, `'2.5'` becomes float) instead of being floored to
 * int.
 *
 * @return array{type:string, enabled:bool, repetition:int|float}
 */
function sb_popup_meta_read(int $id): array
{
    /** @var mixed $type */
    $type = get_post_meta($id, SPECTRA_BLOCKS_POPUP_META_TYPE, single: true);
    /** @var mixed $enabled */
    $enabled = get_post_meta($id, SPECTRA_BLOCKS_POPUP_META_ENABLED, single: true);
    /** @var mixed $repetition */
    $repetition = get_post_meta($id, SPECTRA_BLOCKS_POPUP_META_REPETITION, single: true);
    return [
        'type' => is_string($type) && $type !== '' ? $type : 'unset',
        'enabled' => sb_popup_enabled_flag($enabled),
        'repetition' => sb_popup_repetition_value($repetition),
    ];
}

/**
 * Normalize the raw repetition meta into a number without losing precision:
 * integral values stay int (`'3'` → 3), fractional ones become float
 * (`'2.5'` → 2.5) — matching the plugin's registered `number` meta type.
 * Plain integer strings within the platform range are parsed losslessly as
 * int BEFORE any float conversion, so exact values above 2^53 (e.g.
 * `'9007199254740993'`) survive instead of snapping to the nearest double.
 * Non-numeric / missing rows fall back to the registered default of 1, and
 * so do non-finite values: `is_numeric('1e309')` is true but the cast
 * overflows to INF, and INF/NAN both break `json_encode` — unrepresentable
 * meta must never poison the whole response.
 */
function sb_popup_repetition_value(mixed $raw): int|float
{
    if (is_int($raw)) {
        return $raw;
    }
    if (is_string($raw)) {
        // FILTER_VALIDATE_INT accepts optional-sign digit strings and
        // rejects anything outside [PHP_INT_MIN, PHP_INT_MAX] — the exact
        // lossless-int contract. It also rejects leading zeroes, so the
        // string is canonicalized first ('09007199254740993' must stay
        // lossless, not fall through to the float path). Fractional /
        // exponent / out-of-range forms fall through to the float path
        // below.
        $int = filter_var(sb_canonical_int_string($raw), FILTER_VALIDATE_INT);
        if (is_int($int)) {
            return $int;
        }
    }
    return sb_finite_number($raw) ?? 1;
}

/**
 * Canonicalize an optional-sign digit-only string by trimming redundant
 * leading zeroes (`'09'` → `'9'`, `'-007'` → `'-7'`, `'000'` → `'0'`) so
 * FILTER_VALIDATE_INT can validate it against the platform integer range.
 * Anything not matching the plain decimal shape is returned unchanged and
 * left to the caller's float path.
 */
function sb_canonical_int_string(string $raw): string
{
    $matches = [];
    if (preg_match('/^([+-]?)0*([0-9]+)$/', $raw, $matches) !== 1) {
        return $raw;
    }
    $sign = $matches[1] === '-' && $matches[2] !== '0' ? '-' : '';
    return $sign . $matches[2];
}

/**
 * Convert a raw meta value to a finite number, or null when it is
 * non-numeric or would not survive the float conversion finitely
 * (`'1e309'` overflows to INF; stored INF/NAN floats stay non-finite).
 */
function sb_finite_number(mixed $raw): int|float|null
{
    /** @var mixed $value */
    $value = is_string($raw) && is_numeric($raw) ? (float) $raw : $raw;
    if (!is_float($value) || !is_finite($value)) {
        return null;
    }
    return sb_number_shape($value);
}

/**
 * Collapse a finite float to int when it is integral AND castable within
 * the platform integer range; otherwise keep the float — a huge finite
 * value like 1e308 is returned untouched instead of tripping an undefined
 * out-of-range cast.
 */
function sb_number_shape(float $value): int|float
{
    if (!sb_float_fits_int($value)) {
        return $value;
    }
    $int = (int) $value;
    return (float) $int === $value ? $int : $value;
}

/**
 * Whether a finite float can be cast to int without leaving the platform
 * integer range. Bounds derive from PHP_INT_MIN / PHP_INT_MAX rather than
 * any hardcoded width: the low boundary is a power of two and therefore
 * exact in a double on every platform, while `(float) PHP_INT_MAX` is
 * exact on 32-bit builds (2^31-1 fits a double) but rounds UP to
 * PHP_INT_MAX + 1 on 64-bit builds — so the top boundary is inclusive only
 * where the conversion is exact (PHP_INT_SIZE < 8) and exclusive otherwise.
 */
function sb_float_fits_int(float $value): bool
{
    if ($value < (float) PHP_INT_MIN) {
        return false;
    }
    $max = (float) PHP_INT_MAX;
    // The platform check is deliberate: the analyzer const-folds
    // PHP_INT_SIZE for its own 64-bit target, but this code also runs on
    // 32-bit builds where the inclusive boundary is the correct one.
    // @mago-expect analysis:redundant-comparison
    // @mago-expect analysis:impossible-condition
    return PHP_INT_SIZE < 8 ? $value <= $max : $value < $max;
}

/**
 * Normalize the `spectra-blocks-popup-enabled` meta value into a strict
 * boolean. The meta is registered as `type: boolean` (REST writes store real
 * booleans, serialised as `'1'` / `''` in the meta table), but direct
 * `update_post_meta` callers and migrated rows can leave `1`, `'true'`, or
 * `'on'` behind — a single-branch strict check would misclassify those.
 * Kept as a helper so the reader and any future query filter share one
 * truthiness contract.
 */
function sb_popup_enabled_flag(mixed $raw): bool
{
    if (is_bool($raw)) {
        return $raw;
    }
    if (is_int($raw)) {
        return $raw === 1;
    }
    if (is_string($raw)) {
        return in_array($raw, ['1', 'on', 'true'], strict: true);
    }
    return false;
}
