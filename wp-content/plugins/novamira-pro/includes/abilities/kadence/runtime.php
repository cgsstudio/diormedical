<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Kadence;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards + version helpers for the Kadence theme integration.
 *
 * Provides:
 *  - kadence_theme_available() — Kadence theme active at the minimum supported version
 *  - kadence_theme_version()   — theme version string ('' when inactive)
 *  - kadence_theme_tags()      — Kadence's template tag bag (`Kadence\kadence()`),
 *                                or null when the theme is not booted
 */

/**
 * Kadence theme version string, or '' when the theme is inactive.
 */
function kadence_theme_version(): string
{
    return defined('KADENCE_VERSION') ? (string) constant('KADENCE_VERSION') : '';
}

/**
 * Returns Kadence's Template_Tags bag, or null when the theme is not booted
 * (e.g. another theme is active). The bag exposes the option / palette /
 * sub_option / defaults methods documented in
 * `inc/components/options/component.php`. Callers should null-check before
 * using.
 */
function kadence_theme_tags(): ?\Kadence\Template_Tags
{
    if (!function_exists('Kadence\\kadence')) {
        return null;
    }
    return \Kadence\kadence();
}
