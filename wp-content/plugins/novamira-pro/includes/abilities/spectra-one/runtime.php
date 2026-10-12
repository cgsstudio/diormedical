<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards + version helpers for the Spectra One theme
 * integration.
 *
 * The `spectra_one_theme_available()` / `spectra_plugin_available()` gates
 * themselves live in `includes/gates.php` so the manifest entry + the
 * per-ability sub-gates can share one implementation. This runtime file
 * exposes the narrow version-string helpers used by output shaping
 * (check-setup, get-theme-info) — kept off the gate path so they never
 * short-circuit the availability decision on ill-formed constants.
 */

/**
 * Spectra One theme version string, or '' when the theme is inactive.
 * The theme declares the constant inside its own `Swt` namespace
 * (`functions.php:18` → `const SWT_VER = '...';`), so the lookup MUST be
 * namespaced — `defined('SWT_VER')` against the global scope returns false
 * against the shipping theme.
 */
function spectra_one_theme_version(): string
{
    return defined('Swt\\SWT_VER') ? (string) constant('Swt\\SWT_VER') : '';
}

/**
 * Spectra (UAGB) plugin version string, or '' when the plugin is inactive.
 * The plugin is OPTIONAL — most abilities function without it.
 */
function spectra_plugin_version(): string
{
    return defined('UAGB_VER') ? (string) constant('UAGB_VER') : '';
}

/**
 * Whether Spectra One is BOTH the WordPress active theme (stylesheet or
 * parent) AND its PHP runtime has loaded. `Swt\SWT_VER` alone stays defined
 * for the rest of the request after any code path pulls the theme's
 * bootstrap; the stylesheet check makes sure a mid-request
 * `switch_theme()` doesn't leave the flag stuck-truthy on the new theme.
 * The version constant is inside the theme's own `Swt` namespace, so the
 * `defined()` lookup MUST be namespaced.
 */
function spectra_one_is_active_theme(): bool
{
    if (!defined('Swt\\SWT_VER')) {
        return false;
    }
    $stylesheet = get_stylesheet();
    $template = get_template();
    return $stylesheet === 'spectra-one' || $template === 'spectra-one';
}
