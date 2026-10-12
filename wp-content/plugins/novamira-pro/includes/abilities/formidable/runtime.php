<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Runtime gates for the Formidable Forms specialization.
 *
 * Loaded eagerly at plugin bootstrap (alongside the elementor / breakdance /
 * mosaic / wpbakery runtime gates) so the category registrar and the ability
 * loader can decide whether to even pull the rest of the integration into
 * memory. Keeping these checks in their own file means helpers.php and the
 * stubbed FrmForm / FrmField / FrmEntry namespace stay unloaded on sites
 * where Formidable is absent.
 *
 * Detection sentinel is the `\FrmAppHelper` class plus its
 * `plugin_version()` static accessor — free Formidable does NOT define a
 * top-level `FRM_VERSION` constant (verified against Formidable 6.32, file
 * `formidable/classes/helpers/FrmAppHelper.php:44`), so the usual
 * `defined('PLUGIN_VERSION')` shape used by the SeoPress / Yoast gates
 * doesn't apply here. The class-exists guard ensures the autoloader
 * registered in `formidable/formidable.php:60` has fired.
 *
 * Pro hard-depends on free and exposes its version via the public static
 * `\FrmProDb::$plug_version`. License status is informational only — Pro
 * features run unlicensed (same semantics as the SeoPress / AIOSEO / Yoast
 * integrations), so the Pro gate keys on the class, not the license option.
 */

/**
 * Whether Formidable Forms (free) is loaded AND meets the minimum
 * supported version. Mutation abilities check this before any other
 * gate (capability, input validation) so an inactive install fails with
 * the canonical `formidable_not_active` code first.
 */
function frm_runtime_available(): bool
{
    return frm_plugin_present()
    && version_compare(
        version1: \FrmAppHelper::plugin_version(),
        version2: \NOVAMIRA_PRO_FORMIDABLE_MIN_VERSION,
        operator: '>=',
    );
}

/**
 * Whether Formidable Forms Pro is loaded AND meets the minimum supported
 * version. Used to gate registration of Pro-only abilities (applications,
 * cancel-subscription) so they never appear in `discover-abilities` on
 * sites that don't have Pro.
 */
function frm_pro_active(): bool
{
    if (!frm_runtime_available()) {
        return false;
    }
    if (!class_exists('\FrmProDb') || !property_exists('\FrmProDb', 'plug_version')) {
        return false;
    }
    if (!version_compare(
        version1: \FrmProDb::$plug_version,
        version2: \NOVAMIRA_PRO_FORMIDABLE_PRO_MIN_VERSION,
        operator: '>=',
    )) {
        return false;
    }
    // Same multisite defensive check as in frm_plugin_present — Pro classes
    // can persist across `switch_to_blog` even when the destination blog
    // does NOT have Pro network-activated. Verify per-blog active state.
    if (function_exists('is_multisite') && is_multisite()) {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!is_plugin_active('formidable-pro/formidable-pro.php')) {
            return false;
        }
    }
    return true;
}

/**
 * Whether the Stripe Lite (free) payment surface is usable on this install
 * — i.e. the `wp_frm_payments` table exists and the `FrmTransLitePayment`
 * model class is loaded. Gates the read-only payment / subscription
 * abilities (list-transactions, get-transaction, list-subscriptions,
 * get-subscription) so they ship on free Formidable installs that have
 * Stripe Lite configured, NOT only on Pro.
 *
 * `cancel-subscription` is additionally gated on `frm_pro_active()` because
 * the cancel pipeline lives in the Pro Stripe controller. See plan
 * "Anti-pattern checklist Formidable-specifics #11".
 */
function frm_payments_supported(): bool
{
    return (
        frm_runtime_available()
        && class_exists('\FrmTransLitePayment')
        && class_exists('\FrmTransLiteAppHelper')
        && method_exists('\FrmTransLiteAppHelper', 'payments_table_exists')
        && \FrmTransLiteAppHelper::payments_table_exists()
    );
}
