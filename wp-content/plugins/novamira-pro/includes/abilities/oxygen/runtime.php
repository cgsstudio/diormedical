<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for the Oxygen 6 integration.
 *
 * Oxygen 6.x is Soflyy's unified codebase running in
 * `BREAKDANCE_MODE=oxygen`. It shares the `Breakdance\*` code namespaces
 * with standalone Breakdance, but the PERSISTED identifiers are mode-aware:
 * post types are `oxygen_*` (not `breakdance_*`), the postmeta prefix is
 * `_oxygen_` (via `__bdox('_meta_prefix')`), and global options are
 * prefixed `oxygen_`. This integration therefore never hard-codes a
 * `breakdance_*`/`_breakdance_*` identifier — it goes through the native
 * mode-aware Data API (`Breakdance\Data\get_meta`/`set_meta`,
 * `get_global_option`/`set_global_option`) and the `BREAKDANCE_*_POST_TYPE`
 * constants, all of which resolve to the `oxygen_*` values at runtime.
 *
 * Layered detection:
 *  - oxygen_plugin_available() — Oxygen 6.x is active, `BREAKDANCE_MODE`
 *                                is `oxygen`, and the version floor is met
 *                                (defined in includes/gates.php)
 *  - ox_version()              — current Oxygen version string, '' when off
 *  - ox_license_mode()         — 'free' | 'pro' (from the stored license
 *                                validity payload — falls back to 'free'
 *                                when no license key is present)
 *  - ox_companions_state()     — detection of the legacy `oxygen-gutenberg`
 *                                and `oxygen-woocommerce` companion plugins
 *                                for diagnostic reporting (both are Oxygen
 *                                Classic add-ons and non-functional on Oxygen 6)
 *
 * The license readers go through `Breakdance\Data\get_global_option`, which
 * auto-prefixes with `oxygen_` (so the real option is `oxygen_license_key`)
 * and decodes the stored value. A licensed Oxygen site would look free if
 * we read the raw `breakdance_license_key` option instead.
 */

/**
 * Oxygen plugin version, or '' when the constant is not defined. Reads
 * `__BREAKDANCE_VERSION` because Oxygen 6 is the Soflyy unified codebase
 * — the constant name kept the Breakdance prefix intentionally.
 */
function ox_version(): string
{
    return defined('__BREAKDANCE_VERSION') ? (string) constant('__BREAKDANCE_VERSION') : '';
}

/**
 * Whether the running plugin identifies itself as Oxygen (not Breakdance).
 * The `BREAKDANCE_MODE` constant is defined in `oxygen/plugin.php` and is
 * the canonical mode discriminator across the Soflyy build system.
 */
function ox_mode_is_oxygen(): bool
{
    return defined('BREAKDANCE_MODE') && constant('BREAKDANCE_MODE') === 'oxygen';
}

/**
 * Read the currently stored Oxygen license key — or '' when none has been
 * entered yet. Goes through the native `Breakdance\Data\get_global_option`,
 * which auto-prefixes with `oxygen_` (real option `oxygen_license_key`) and
 * returns the already-decoded value. Reading the raw `breakdance_license_key`
 * option instead would make a licensed Oxygen site look free.
 */
function ox_license_key(): string
{
    if (!function_exists('Breakdance\\Data\\get_global_option')) {
        return '';
    }
    /** @var mixed $value */
    $value = \Breakdance\Data\get_global_option('license_key');
    return is_string($value) ? $value : '';
}

/**
 * Read the cached license-validity payload written after activation.
 * Returns null when no validity check has run yet or the
 * `oxygen_license_key_validity_info` option is unreadable. Uses the native
 * mode-aware option reader, which returns the decoded array.
 *
 * @return array<string, mixed>|null
 */
function ox_license_validity(): ?array
{
    if (!function_exists('Breakdance\\Data\\get_global_option')) {
        return null;
    }
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option('license_key_validity_info');
    if (!is_array($raw)) {
        return null;
    }
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out === [] ? null : $out;
}

/**
 * Resolve the RUNTIME subscription mode for the stored license, mirroring the
 * engine's own `LicenseKeyManager::getSubscriptionModeEligibleForStoredLicenseKey()`
 * exactly (`class.license-key-manager.php`): 'pro' only when
 * `intended_subscription_mode === 'pro'` AND the license is `valid`, or
 * `expired` with `has_license_been_paid_for` truthy. Everything else — including
 * `inactive` / `site_inactive` — is 'free', because Oxygen itself renders
 * Pro-only elements as free in those states. An earlier, looser "does this site
 * own Pro" reading reported `pro` for an inactive key while the builder was in
 * free mode; agents then used Pro-only elements that render broken. Depends only
 * on the cached payload (no remote EDD call).
 *
 * @return 'free'|'pro'
 */
function ox_license_mode(): string
{
    $validity = ox_license_validity();
    if ($validity === null) {
        return 'free';
    }
    if (($validity['intended_subscription_mode'] ?? 'free') !== 'pro') {
        return 'free';
    }
    /** @var mixed $edd */
    $edd = $validity['edd_key_info'] ?? null;
    if (!is_array($edd)) {
        return 'free';
    }
    /** @var mixed $license_status */
    $license_status = $edd['license'] ?? null;
    if ($license_status === 'valid') {
        return 'pro';
    }
    if ($license_status === 'expired') {
        // Native checks `if ($info['has_license_been_paid_for'])` (truthy).
        // Compare explicitly to the falsy set so Mago sees no mixed operand.
        /** @var mixed $paid */
        $paid = $edd['has_license_been_paid_for'] ?? false;
        if ($paid !== false && $paid !== null && $paid !== 0 && $paid !== '' && $paid !== '0') {
            return 'pro';
        }
    }
    return 'free';
}

/**
 * Detection state of the two legacy Oxygen Classic companion plugins.
 *
 * Both are Oxygen Classic (pre-6) add-ons that ship with Oxygen 6 as
 * separate `wp-content/plugins/oxygen-*` directories on some installs
 * (users migrating from Classic). They are DIAGNOSTIC ONLY:
 *  - `oxygen-woocommerce` is verified 100% incompatible with Oxygen 6
 *    (its gate `class_exists('OxygenElement')` fails because the class
 *    is a Classic-only class that Oxygen 6 does not ship — the plugin
 *    loads but early-returns from `oxygen_woocommerce_init()`).
 *  - `oxygen-gutenberg` is ~85% incompatible via code inspection: it
 *    depends on postmeta `_ct_builder_shortcodes` (Classic storage) and
 *    the `oxygen_*` shortcode hooks (Classic API) that Oxygen 6 does
 *    not produce. Runtime confirmation would require activating it
 *    against Oxygen 6, which is out of scope for detection.
 *
 * The `functional_on_current_install` flag captures the "loaded but
 * inert" state: when true the plugin is installed and active but does
 * not (or almost certainly does not) contribute to the current install.
 * The `check-setup` ability surfaces this so an agent seeing an Oxygen 6
 * site with these companions active can guide the user toward the
 * standard `novamira/gutenberg-*` and `novamira/woocommerce-*` surfaces
 * instead.
 *
 * @return array{
 *   oxygen_gutenberg: array{installed: bool, active: bool, version: string, supported: false, functional_on_current_install: bool, reason: string},
 *   oxygen_woocommerce: array{installed: bool, active: bool, version: string, supported: false, functional_on_current_install: bool, reason: string},
 * }
 */
function ox_companions_state(): array
{
    return [
        'oxygen_gutenberg' => ox_companion_state(
            file_relative: 'oxygen-gutenberg/oxygen-gutenberg.php',
            version_constant: 'CT_OXYGEN_GUTENBERG_VERSION',
            reason: 'Legacy Oxygen Classic companion; depends on `_ct_builder_shortcodes` postmeta that Oxygen 6 does not produce.',
        ),
        'oxygen_woocommerce' => ox_companion_state(
            file_relative: 'oxygen-woocommerce/plugin.php',
            version_constant: 'CT_OXYGEN_WOOCOMMERCE_VERSION',
            reason: 'Legacy Oxygen Classic companion; gate `class_exists(\'OxygenElement\')` fails on Oxygen 6 — the plugin loads but registers nothing.',
        ),
    ];
}

/**
 * Detection state for a single legacy companion. Reads the plugin header
 * for the version when the plugin is installed but its own version
 * constant is not defined (self-including `wp-admin/includes/plugin.php`
 * as Etch / Ocean Extra do), so the diagnostic never depends on the
 * companion having been loaded.
 *
 * @return array{installed: bool, active: bool, version: string, supported: false, functional_on_current_install: bool, reason: string}
 */
function ox_companion_state(string $file_relative, string $version_constant, string $reason): array
{
    $abs = WP_PLUGIN_DIR . '/' . $file_relative;
    $installed = file_exists($abs);
    if (!$installed) {
        return [
            'installed' => false,
            'active' => false,
            'version' => '',
            'supported' => false,
            'functional_on_current_install' => false,
            'reason' => $reason,
        ];
    }
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $active = is_plugin_active($file_relative);
    $version = defined($version_constant) ? (string) constant($version_constant) : '';
    if ($version === '') {
        /** @var array{Version?: string} $data */
        $data = get_plugin_data($abs, markup: false, translate: false);
        $version = $data['Version'] ?? '';
    }
    return [
        'installed' => $installed,
        'active' => $active,
        'version' => $version,
        // Both companions are legacy Oxygen Classic and cannot function
        // on Oxygen 6 — `supported: false` is unconditional.
        'supported' => false,
        'functional_on_current_install' => false,
        'reason' => $reason,
    ];
}
