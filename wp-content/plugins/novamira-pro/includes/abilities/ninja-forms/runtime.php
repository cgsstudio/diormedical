<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for the Ninja Forms integration.
 *
 * `ninja_forms_runtime_available()` and `ninja_forms_version()` have been moved
 * to includes/gates.php (loaded early by novamira-pro.php) so the manifest gate
 * closure can reference them without loading this file at plugin bootstrap. This
 * file is loaded lazily by novamira_pro_load_ninja_forms_abilities() as its
 * first step.
 *
 * Novamira Pro drives Ninja Forms entirely through its PHP model layer
 * (`Ninja_Forms()->form()`, the `nf3_*` tables, the `nf_sub` CPT) — the
 * full forms / fields / notifications / submissions surface plus the
 * canonical reader are all Novamira-native and do NOT depend on Ninja's
 * own `ninjaforms/*` abilities. The remaining helpers below are the per-request
 * signals used by individual abilities.
 */

/**
 * Whether the modern NF3 storage tables exist on this install. Ninja
 * Forms 3.x migrated from the legacy `wp_nf_*` schema to the `wp_nf3_*`
 * schema in 3.0; older sites that never ran the upgrader can still have
 * Ninja Forms active but no `wp_nf3_forms` table — every model-layer read
 * would then come back empty. Surface the gap via `check-setup` so the
 * agent can explain it to the user.
 */
function ninja_forms_storage_ready(): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = $wpdb->prefix . 'nf3_forms';
    // DB_NAME is defined by wp-config.php; reach for it via constant()
    // so the static analyser does not flag it as namespaced-undefined
    // (the namespaced lookup in `Novamira\Pro\Abilities\NinjaForms\DB_NAME`
    // is what tripped Mago).
    $sql = $wpdb->prepare(
        'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
        constant('DB_NAME'),
        $table,
    );
    if (!is_string($sql)) {
        return false;
    }
    $found = $wpdb->get_var($sql);
    return $found === $table;
}

/**
 * Whether the official Ninja Forms hCaptcha module is loaded AND has
 * keys configured. Surfaced by `check-setup` so the agent can flag forms
 * that ship without spam protection without scraping the admin UI.
 *
 * Ninja Forms stores spam-module config under `nf_settings.recaptcha_*`
 * / `nf_settings.hcaptcha_*` (no per-form override exists in Free).
 */
function ninja_forms_hcaptcha_configured(): bool
{
    /** @var mixed $opts */
    $opts = get_option('nf_settings');
    if (!is_array($opts)) {
        return false;
    }
    foreach (['hcaptcha_site_key', 'hcaptcha_secret_key'] as $key) {
        if (!isset($opts[$key]) || !is_string($opts[$key]) || $opts[$key] === '') {
            return false;
        }
    }
    return true;
}

/**
 * Whether the Ninja Forms Google reCAPTCHA module is configured. Same
 * shape as the hCaptcha gate.
 */
function ninja_forms_recaptcha_configured(): bool
{
    /** @var mixed $opts */
    $opts = get_option('nf_settings');
    if (!is_array($opts)) {
        return false;
    }
    foreach (['recaptcha_site_key', 'recaptcha_secret_key'] as $key) {
        if (!isset($opts[$key]) || !is_string($opts[$key]) || $opts[$key] === '') {
            return false;
        }
    }
    return true;
}

/**
 * Whether the Cloudflare Turnstile add-on (3rd-party / paid module) is
 * loaded with keys. Ninja Forms does not ship Turnstile in core — this
 * helper looks for the well-known third-party option keys (`nf_turnstile`
 * or `nf_settings.turnstile_*`) used by the most common add-ons.
 */
function ninja_forms_turnstile_configured(): bool
{
    /** @var mixed $opts */
    $opts = get_option('nf_settings');
    if (!is_array($opts)) {
        return false;
    }
    foreach (['turnstile_site_key', 'turnstile_secret_key'] as $key) {
        if (isset($opts[$key]) && is_string($opts[$key]) && $opts[$key] !== '') {
            return true;
        }
    }
    return false;
}
