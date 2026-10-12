<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for the GeneratePress integration.
 *
 * Layered detection following the Elementor-style multi-feature pattern:
 *  - gp_theme_available()       — the free theme is active at the required version
 *  - gp_premium_available()     — GP Premium plugin is active
 *  - gp_module_active(opt,cnst) — generic per-module gate via the theme's helper
 *  - gp_elements_active()       — Elements module (CPT gp_elements + helper class)
 *  - gp_site_library_active()   — Site Library module
 *  - gp_font_library_active()   — Font Library module
 *
 * A module is active when either the `generate_package_<name>` option is
 * "activated" in the DB OR the `GENERATE_<NAME>` constant is defined
 * (the latter is the wp-config override path GP supports). All gates use
 * the theme's own `generatepress_is_module_active()` helper to stay in
 * sync if the contract changes.
 */

/**
 * GP Premium plugin is active at the minimum supported release. The
 * plugin's `generatepress_is_module_active()` helper is the contract for
 * per-module detection — we require it as part of "premium available".
 */
function gp_premium_available(): bool
{
    return (
        defined('GP_PREMIUM_VERSION')
        && function_exists('generatepress_is_module_active')
        && version_compare((string) constant('GP_PREMIUM_VERSION'), NOVAMIRA_PRO_GP_PREMIUM_MIN_VERSION, operator: '>=')
    );
}

/**
 * Generic per-module gate. Wraps GP Premium's own
 * `generatepress_is_module_active($option, $constant)` so the contract
 * stays the same as the theme's UI. Returns false when Premium is not
 * present at all.
 */
function gp_module_active(string $option, string $constant): bool
{
    if (!gp_premium_available()) {
        return false;
    }
    return generatepress_is_module_active($option, $constant);
}

/**
 * Elements module: enables the `gp_elements` CPT (hook elements, block
 * elements, page hero, layout, etc.) plus the conditions helper.
 */
function gp_elements_active(): bool
{
    return gp_module_active(option: 'generate_package_elements', constant: 'GENERATE_ELEMENTS');
}

/**
 * Site Library module: enables the starter-template browser + import
 * REST endpoints. Fetches templates from gpsites.co.
 */
function gp_site_library_active(): bool
{
    return gp_module_active(option: 'generate_package_site_library', constant: 'GENERATE_SITE_LIBRARY');
}

/**
 * Font Library module: enables the local Google Fonts management (CPT
 * gp_font / FONT_LIBRARY_CPT) and the bundled fonts REST endpoint.
 */
function gp_font_library_active(): bool
{
    return gp_module_active(option: 'generate_package_font_library', constant: 'GENERATE_FONT_LIBRARY');
}

/**
 * GeneratePress theme version string, or empty string when the theme is
 * inactive / undetected.
 */
function gp_theme_version(): string
{
    return defined('GENERATE_VERSION') ? (string) constant('GENERATE_VERSION') : '';
}

/**
 * GP Premium plugin version string, or empty string when the plugin is
 * inactive / undetected.
 */
function gp_premium_version(): string
{
    return defined('GP_PREMIUM_VERSION') ? (string) constant('GP_PREMIUM_VERSION') : '';
}

/**
 * GenerateBlocks (free) version string, or empty string when not installed.
 * GenerateBlocks is the canonical block library for building visual content
 * on a GeneratePress site — Container / Grid / Headline / Button / Image /
 * Query Loop, all native Gutenberg blocks. Sites that have GP without GB
 * can only do header / footer chrome + typography settings; for a rich page
 * body GB is effectively required.
 */
function gp_generateblocks_version(): string
{
    return defined('GENERATEBLOCKS_VERSION') ? (string) constant('GENERATEBLOCKS_VERSION') : '';
}

/**
 * GenerateBlocks Pro version string, or empty string when not installed.
 * GB Pro adds Tabs / Accordion / dynamic-data binding / Local Hero / Pattern
 * Library on top of the free blocks. The detection covers both the modern
 * `GENERATEBLOCKS_PRO_VERSION` constant and a fallback class check for
 * older builds.
 */
function gp_generateblocks_pro_version(): string
{
    if (defined('GENERATEBLOCKS_PRO_VERSION')) {
        return (string) constant('GENERATEBLOCKS_PRO_VERSION');
    }
    // Fallback: presence of one of the Pro bootstrap classes.
    if (class_exists('GenerateBlocksPro') || class_exists('GenerateBlocks_Pro')) {
        return 'unknown';
    }
    return '';
}
