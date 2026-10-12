<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for the Breakdance integration.
 *
 * Layered detection:
 *  - bd_plugin_available()  — Breakdance is active at the required version
 *                             (defined in includes/gates.php)
 *  - bd_license_mode()      — 'free' | 'pro' (prefers Breakdance's canonical
 *                              runtime subscription mode, with a stored
 *                              license-validity fallback)
 *  - bd_version()           — current Breakdance version string, '' when off
 *
 * Detection sentinel is the `__BREAKDANCE_VERSION` constant + the
 * `Breakdance\Data\get_global_option` function (both defined in
 * `plugin.php` + `data/api/options.php` — together they're our minimum
 * required public surface).
 *
 * Storage note: Breakdance's Data API JSON-encodes every global option on
 * write (`Breakdance\Data\encode_before_writing_to_wp` = one `wp_json_encode`),
 * so a raw `get_option('breakdance_*')` returns a JSON STRING, never a PHP
 * array. The license readers below decode accordingly. We read raw options
 * instead of `Breakdance\Data\get_global_option` on purpose: these helpers
 * must not fatal when Breakdance is inactive.
 */

/**
 * Breakdance plugin version, or '' when the constant is not defined.
 */
function bd_version(): string
{
    return defined('__BREAKDANCE_VERSION') ? (string) constant('__BREAKDANCE_VERSION') : '';
}

/**
 * Read the currently stored Breakdance license key — or '' when none
 * has been entered yet. Storage option is `breakdance_license_key`
 * (Breakdance maps it from `__bdox('meta_prefix') . 'license_key'`).
 * The stored value is a JSON-encoded string (`"the-key"`, quotes
 * included — see the storage note above), so decode before returning.
 */
function bd_license_key(): string
{
    /** @var mixed $raw */
    $raw = get_option(option: 'breakdance_license_key', default_value: '');
    if (!is_string($raw) || $raw === '') {
        return '';
    }
    /** @var mixed $decoded */
    $decoded = json_decode(json: $raw, associative: true);
    if (is_string($decoded)) {
        return $decoded;
    }
    // Not JSON at all → a raw value that pre-dates Breakdance's Data API.
    // Valid JSON but not a string (null, number, …) → no usable key.
    return json_last_error() !== JSON_ERROR_NONE ? $raw : '';
}

/**
 * Read the cached license-validity payload Breakdance writes after
 * activation. Returns null when no validity check has run yet or the
 * `breakdance_license_key_validity_info` option is unreadable.
 *
 * The stored value is a JSON string (see the storage note above) —
 * `bd_decode_json_option` handles both that and a bare-array value.
 * Requiring `is_array` on the raw option here made this always return
 * null, so `license.mode` reported `free` on every site regardless of
 * the actual Pro entitlement.
 *
 * @return array<string, mixed>|null
 */
function bd_license_validity(): ?array
{
    /** @var mixed $raw */
    $raw = get_option(option: 'breakdance_license_key_validity_info', default_value: null);
    $decoded = bd_decode_json_option($raw);
    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out === [] ? null : $out;
}

/**
 * Resolve the agent-facing subscription mode. When Breakdance is active and
 * its canonical helper is loaded, use that initialized runtime value. Otherwise
 * mirror LicenseKeyManager's exact eligibility rule against the cached option.
 *
 * @return 'free'|'pro'
 */
function bd_license_mode(): string
{
    return bd_license_mode_detection()['mode'];
}

/**
 * Return both the resolved mode and the path used to detect it.
 *
 * @return array{mode: 'free'|'pro', source: 'canonical'|'fallback'}
 */
function bd_license_mode_detection(): array
{
    $active = bd_version() !== '' && function_exists('Breakdance\\Data\\get_global_option');
    if ($active && function_exists('Breakdance\\Subscription\\getSubscriptionMode')) {
        /** @var mixed $runtime_mode */
        $runtime_mode = \Breakdance\Subscription\getSubscriptionMode();
        if ($runtime_mode === 'free' || $runtime_mode === 'pro') {
            return ['mode' => $runtime_mode, 'source' => 'canonical'];
        }
    }

    return ['mode' => bd_license_mode_from_validity(bd_license_validity()), 'source' => 'fallback'];
}

/**
 * Mirror Breakdance LicenseKeyManager's stored-license eligibility rule.
 *
 * @param array<string, mixed>|null $validity
 * @return 'free'|'pro'
 */
function bd_license_mode_from_validity(?array $validity): string
{
    if ($validity === null) {
        return 'free';
    }
    if (($validity['intended_subscription_mode'] ?? 'free') !== 'pro') {
        return 'free';
    }
    /** @var mixed $edd_key_info */
    $edd_key_info = $validity['edd_key_info'] ?? null;
    if (!is_array($edd_key_info)) {
        return 'free';
    }
    /** @var mixed $license_status */
    $license_status = $edd_key_info['license'] ?? null;
    if ($license_status === 'valid') {
        return 'pro';
    }
    if ($license_status === 'expired' && bd_license_truthy($edd_key_info['has_license_been_paid_for'] ?? false)) {
        return 'pro';
    }
    return 'free';
}

/**
 * Apply PHP's truthiness rules without leaving a mixed boolean cast for static
 * analysis. The fallback payload is JSON-decoded, but keep the residual branch
 * faithful for callers that pass a hand-built value.
 */
function bd_license_truthy(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    if (is_float($value)) {
        return $value !== 0.0;
    }
    if (is_string($value)) {
        return $value !== '' && $value !== '0';
    }
    if (is_array($value)) {
        return $value !== [];
    }
    return $value !== null;
}
