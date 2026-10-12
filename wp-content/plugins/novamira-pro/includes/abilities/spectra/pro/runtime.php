<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra\Pro;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime for the Spectra Pro layer.
 *
 * The availability gate itself — `spectra_pro_active()` — lives in
 * `includes/gates.php` alongside its free counterpart so the manifest entry
 * and the loader can share one implementation. This file exposes the narrow
 * output-shaping helpers the Pro-only abilities need: the Spectra Pro
 * license accessor (distinct from Novamira Pro's own license) and the
 * canonical Pro popup-meta key set.
 *
 * Namespace convention. This Pro layer sits under the sub-namespace
 * `Novamira\Pro\Abilities\Spectra\Pro` because Spectra Pro is a physically
 * separate WP plugin (`spectra-pro/spectra-pro.php`) with its own release
 * cadence, licensing, and codebase — not merely a "Pro tier" of the free
 * Spectra plugin. This mirrors the plugin-boundary reality on disk. Other
 * integrations whose Pro layer ships as the same WP plugin (WPForms
 * Lite/Pro, AIOSEO free/Pro, SEOPress free/Pro) stay in a flat namespace
 * with `if (integration_pro_active())` gating inside the loader — see
 * `wpforms/list-entries.php` for the counter-example. Future integrations
 * should pick the pattern based on this boundary rule.
 */

/**
 * Spectra Pro's own license status, or false when the Brainstorm-Force
 * license manager is not loaded. Distinct from Novamira Pro's
 * `nvp_license_status`: an install can have Novamira Pro active + Spectra
 * Pro installed-but-unlicensed. Reads through BSF's canonical accessor so
 * we never touch the internal option key directly.
 */
function spectra_pro_license_active(): bool
{
    if (!class_exists('\\BSF_License_Manager')) {
        return false;
    }
    /** @var callable-string|array{class-string, string} $callable */
    $callable = ['\\BSF_License_Manager', 'bsf_is_active_license'];
    if (!is_callable($callable)) {
        return false;
    }
    /** @var mixed $result */
    $result = $callable('spectra-pro');
    return $result === true;
}

/**
 * Meta keys the Spectra Pro popup extension registers on the free
 * `spectra-popup` CPT. Enumerated by `list-popup-conditions` and
 * writable by `set-popup-conditions`. Source: BSF's own
 * `Spectra_Pro_Popup_Builder::register_pro_meta()` in
 * `spectra-pro/includes/extensions/popup-builder/spectra-pro-popup-builder.php`.
 */
const SPECTRA_PRO_POPUP_META_TRIGGER = 'spectra-popup-trigger';

const SPECTRA_PRO_POPUP_META_TRIGGER_DELAY = 'spectra-popup-trigger-delay';

const SPECTRA_PRO_POPUP_META_DISPLAY_INCLUSIONS = 'spectra-popup-display-inclusions';

const SPECTRA_PRO_POPUP_META_DISPLAY_EXCLUSIONS = 'spectra-popup-display-exclusions';

/**
 * Snapshot of every popup meta key the Pro layer manages. Kept as one
 * canonical list so `list-popup-conditions` and `set-popup-conditions`
 * never drift.
 *
 * @return list<string>
 */
function spectra_pro_popup_meta_keys(): array
{
    return [
        SPECTRA_PRO_POPUP_META_TRIGGER,
        SPECTRA_PRO_POPUP_META_TRIGGER_DELAY,
        SPECTRA_PRO_POPUP_META_DISPLAY_INCLUSIONS,
        SPECTRA_PRO_POPUP_META_DISPLAY_EXCLUSIONS,
    ];
}
