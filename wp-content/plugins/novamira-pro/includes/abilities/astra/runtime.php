<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards and module-detection helpers for the Astra integration.
 *
 * Provides:
 *  - astra_theme_version()      — ASTRA_THEME_VERSION string or ''
 *  - astra_addon_version()      — ASTRA_EXT_VER string or ''
 *  - astra_module_active(slug)  — per-module gate via Astra_Ext_Extension::is_active()
 *  - astra_active_module_map()  — {slug => bool} for all 20 known modules
 *  - astra_module_slugs()       — ordered list of all 20 module slugs
 *  - astra_module_labels()      — slug => human label map
 *  - astra_default_module_slugs() — modules active on a fresh Astra Pro install
 */

/**
 * Astra theme version string, or '' when the theme is inactive.
 */
function astra_theme_version(): string
{
    return defined('ASTRA_THEME_VERSION') ? (string) constant('ASTRA_THEME_VERSION') : '';
}

/**
 * Astra Pro Addon version string, or '' when the addon is not installed.
 */
function astra_addon_version(): string
{
    return defined('ASTRA_EXT_VER') ? (string) constant('ASTRA_EXT_VER') : '';
}

/**
 * Whether a given Astra Pro Addon module slug is currently active.
 * Uses the addon's own registry (`Astra_Ext_Extension::is_active`) so any
 * future re-modelling of the storage format stays transparent to callers.
 * Returns false on installs where the addon is not loaded at all.
 */
function astra_module_active(string $slug): bool
{
    if (!class_exists('Astra_Ext_Extension')) {
        return false;
    }
    return \Astra_Ext_Extension::is_active($slug);
}

/**
 * Returns a {slug => active} map for every Astra Pro Addon module the
 * integration cares about. Used by check-setup and list-modules to surface
 * the full matrix without calling astra_module_active() 20 times at
 * each call site.
 *
 * @return array<string, bool>
 */
function astra_active_module_map(): array
{
    $map = [];
    foreach (astra_module_slugs() as $slug) {
        $map[$slug] = astra_module_active($slug);
    }
    return $map;
}

/**
 * Ordered list of all Astra Pro Addon module slugs known to the integration.
 * Order follows the Astra admin UI grouping (core → commerce → LMS → misc).
 *
 * @return list<string>
 */
function astra_module_slugs(): array
{
    return [
        'advanced-hooks',
        'advanced-headers',
        'advanced-footer',
        'blog-pro',
        'colors-and-background',
        'mobile-header',
        'header-sections',
        'site-layouts',
        'spacing',
        'sticky-header',
        'transparent-header',
        'typography',
        'woocommerce',
        'edd',
        'learndash',
        'lifterlms',
        'nav-menu',
        'advanced-search',
        'white-label',
        'scroll-to-top',
    ];
}

/**
 * Human-readable label for each Astra Pro Addon module slug.
 *
 * @return array<string, string>
 */
function astra_module_labels(): array
{
    return [
        'advanced-hooks' => 'Advanced Hooks',
        'advanced-headers' => 'Advanced Headers',
        'advanced-footer' => 'Advanced Footer',
        'blog-pro' => 'Blog Pro',
        'colors-and-background' => 'Colors & Background',
        'mobile-header' => 'Mobile Header',
        'header-sections' => 'Header Sections',
        'site-layouts' => 'Site Layouts',
        'spacing' => 'Spacing',
        'sticky-header' => 'Sticky Header',
        'transparent-header' => 'Transparent Header',
        'typography' => 'Typography',
        'woocommerce' => 'WooCommerce',
        'edd' => 'Easy Digital Downloads',
        'learndash' => 'LearnDash',
        'lifterlms' => 'LifterLMS',
        'nav-menu' => 'Nav Menu',
        'advanced-search' => 'Advanced Search',
        'white-label' => 'White Label',
        'scroll-to-top' => 'Scroll To Top',
    ];
}

/**
 * Module slugs that are active by default on a fresh Astra Pro Addon install
 * (before the user changes anything in the admin). Delegates to the addon's
 * own `get_default_addons()` when available so the list stays in sync if the
 * addon changes its defaults; falls back to the known baseline (advanced-hooks
 * and advanced-search) when the class is not loaded.
 *
 * @return list<string>
 */
function astra_default_module_slugs(): array
{
    if (class_exists('Astra_Ext_Extension') && method_exists('Astra_Ext_Extension', 'get_default_addons')) {
        $defaults = \Astra_Ext_Extension::get_default_addons();
        // @mago-expect analysis:redundant-type-comparison
        // @mago-expect analysis:redundant-type-comparison
        // @mago-expect analysis:redundant-logical-operation
        if (is_array($defaults) && $defaults !== []) {
            $out = [];
            foreach ($defaults as $slug) {
                if ($slug === '') {
                    continue;
                }
                $out[] = $slug;
            }
            if ($out !== []) {
                return $out;
            }
        }
    }
    return ['advanced-hooks', 'advanced-search'];
}
