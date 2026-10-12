<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for the Voxel theme integration.
 *
 * The registration gate (includes/gates.php `voxel_theme_present()`) is
 * presence-only, so a below-minimum Voxel still registers the whole surface and
 * `voxel-check-setup` can report the gap. Every OTHER voxel-* callback must call
 * `vx_require_min()` first so it refuses with a clean WP_Error instead of running
 * against an unverified version.
 *
 * Provides:
 *  - vx_min_version()   — the pinned floor string
 *  - vx_min_satisfied() — bool: Voxel present AND version >= floor
 *  - vx_require_min()    — ?WP_Error: null when OK, else voxel_version_unsupported
 */

/**
 * The minimum supported Voxel version (the pinned, verified floor).
 */
function vx_min_version(): string
{
    return NOVAMIRA_PRO_VOXEL_THEME_MIN_VERSION;
}

/**
 * Whether Voxel is present AND at or above the minimum supported version.
 * Distinct from the presence-only registration gate `voxel_theme_present()`.
 */
function vx_min_satisfied(): bool
{
    if (!voxel_theme_present()) {
        return false;
    }
    $version = voxel_theme_version();
    return $version !== '' && version_compare($version, vx_min_version(), operator: '>=');
}

/**
 * Guard for every non-check-setup callback: returns a WP_Error when Voxel is
 * missing or below the floor, null when the surface is safe to run.
 */
function vx_require_min(): ?WP_Error
{
    if (!voxel_theme_present()) {
        return vx_not_active();
    }
    if (!vx_min_satisfied()) {
        return vx_version_unsupported();
    }
    return null;
}
