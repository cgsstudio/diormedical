<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime detection helpers for the Blocksy integration.
 *
 * The Blocksy theme defines no version constant — the canonical version
 * source is the parent theme header (`wp_get_theme(get_template())`).
 * The companion plugin defines `BLOCKSY__FILE__` and the
 * `blocksy_companion_*` global accessors.
 *
 * Provides:
 *  - bly_theme_version()           — parent theme Version header or ''
 *  - bly_theme_name()              — parent theme Name header or ''
 *  - bly_companion_active()        — Blocksy Companion plugin is loaded
 *  - bly_companion_version()       — companion version string or ''
 *  - bly_companion_pro_code_present() — Pro code bundle installed (licensed or not)
 *  - bly_companion_licensed()      — Pro license active for base_pro features
 *  - bly_companion_plan()          — license plan slug or ''
 */

/**
 * Blocksy parent theme version, or '' when the theme is not active.
 * `function_exists('blocksy_manager')` implies the theme's functions.php
 * ran, i.e. Blocksy (or a child of it) is the active theme.
 */
function bly_theme_version(): string
{
    if (!function_exists('blocksy_manager')) {
        return '';
    }
    return wp_get_theme(get_template())->get('Version');
}

/**
 * Blocksy parent theme display name ('' when inactive). White-label
 * installs may rename this — treat it as informational only and gate on
 * `function_exists('blocksy_manager')` instead.
 */
function bly_theme_name(): string
{
    if (!function_exists('blocksy_manager')) {
        return '';
    }
    return wp_get_theme(get_template())->get('Name');
}

/**
 * Whether the Blocksy Companion plugin (free or Pro) is active. The
 * companion bootstrap defines `BLOCKSY__FILE__`; the class check covers
 * exotic loading orders.
 */
function bly_companion_active(): bool
{
    return defined('BLOCKSY__FILE__') || class_exists('Blocksy\\Plugin');
}

/**
 * Blocksy Companion version string, or '' when the plugin is not active.
 */
function bly_companion_version(): string
{
    if (!function_exists('blocksy_companion_get_version')) {
        return '';
    }
    return blocksy_companion_get_version();
}

/**
 * Whether the Companion Pro code bundle is installed. This is a
 * code-presence check only (`class_exists('Blocksy\Premium')`) — it can
 * be true while the license is inactive. Pair with `bly_companion_licensed()`.
 */
function bly_companion_pro_code_present(): bool
{
    if (!function_exists('blocksy_companion_can_use_premium_code')) {
        return false;
    }
    return blocksy_companion_can_use_premium_code();
}

/**
 * Whether the site has an active license for the base Pro feature set.
 * Can diverge from `bly_companion_pro_code_present()` — Pro zip installed
 * but unlicensed is a legitimate (and common) state.
 */
function bly_companion_licensed(): bool
{
    if (!function_exists('blocksy_companion_site_has_feature')) {
        return false;
    }
    return blocksy_companion_site_has_feature();
}

/**
 * License plan slug reported by the companion capabilities object
 * (free / personal / professional / agency, with `_v2` variants), or ''
 * when the companion is not active.
 */
function bly_companion_plan(): string
{
    if (!function_exists('blocksy_companion_get_capabilities')) {
        return '';
    }
    return blocksy_companion_get_capabilities()->get_plan();
}
