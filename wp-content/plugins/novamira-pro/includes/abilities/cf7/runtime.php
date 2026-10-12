<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for the Contact Form 7 integration.
 *
 * `cf7_runtime_available()` has been moved to includes/gates.php (loaded early
 * by novamira-pro.php) so the manifest gate closure can reference it without
 * loading this file at plugin bootstrap. The remaining helpers in this file are
 * loaded lazily by the CF7 loader (novamira_pro_load_cf7_abilities).
 */

/**
 * Contact Form 7 version string, or empty when the plugin is inactive.
 */
function cf7_version(): string
{
    return defined('WPCF7_VERSION') ? (string) constant('WPCF7_VERSION') : '';
}

/**
 * Whether Flamingo is active in this request. Flamingo is the canonical
 * companion plugin that persists CF7 submissions (CF7 itself does not).
 * Reported by check-setup so an agent can decide whether reading
 * submissions is even possible on this install — the Novamira Pro
 * surface intentionally does NOT cover the Flamingo CPTs in v1.
 */
function cf7_flamingo_active(): bool
{
    // Flamingo announces itself via a top-level `Flamingo` class loaded
    // from its main file. No version gate here — we only surface the
    // presence signal, never call the Flamingo API.
    return class_exists('Flamingo');
}

/**
 * Whether the CF7 reCAPTCHA module is loaded AND has site/secret keys
 * configured. CF7 ships the recaptcha module in core but it only
 * registers the `[recaptcha]` tag once keys land in
 * `wp_options.wpcf7['recaptcha']`. Surfaced by check-setup so an agent
 * doing audit work can flag "form lives without spam protection"
 * without scraping admin pages (round 2 audit L5-A1).
 */
function cf7_recaptcha_configured(): bool
{
    if (!class_exists('WPCF7_RECAPTCHA')) {
        return false;
    }
    /** @var mixed $opts */
    $opts = get_option('wpcf7');
    if (!is_array($opts) || !isset($opts['recaptcha']) || !is_array($opts['recaptcha'])) {
        return false;
    }
    /** @var mixed $secret */
    foreach ($opts['recaptcha'] as $secret) {
        if (is_string($secret) && $secret !== '') {
            return true;
        }
    }
    return false;
}

/**
 * Whether the Cloudflare Turnstile CF7 module is loaded AND has keys
 * configured. Mirrors `cf7_recaptcha_configured()` — same agent-audit
 * use case (round 2 audit L5-A1).
 */
function cf7_turnstile_configured(): bool
{
    if (!class_exists('WPCF7_TURNSTILE')) {
        return false;
    }
    /** @var mixed $opts */
    $opts = get_option('wpcf7');
    if (!is_array($opts) || !isset($opts['turnstile']) || !is_array($opts['turnstile'])) {
        return false;
    }
    /** @var mixed $secret */
    foreach ($opts['turnstile'] as $secret) {
        if (is_string($secret) && $secret !== '') {
            return true;
        }
    }
    return false;
}

/**
 * Whether WPML is active in this request. CF7 forms are language-aware
 * when WPML/Polylang are present (the `forms_total` count typically
 * includes per-language duplicates), so check-setup surfaces these
 * flags to let agents avoid double-edit / double-create across
 * languages (round 2 audit L5-A1).
 */
function cf7_wpml_active(): bool
{
    return defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress');
}

/**
 * Whether Polylang is active in this request. See `cf7_wpml_active()`
 * for the agent-audit rationale (round 2 audit L5-A1).
 */
function cf7_polylang_active(): bool
{
    return defined('POLYLANG_VERSION') || function_exists('pll_current_language');
}
