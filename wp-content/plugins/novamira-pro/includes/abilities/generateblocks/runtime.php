<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GenerateBlocks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards + version helpers for the GenerateBlocks integration.
 *
 * Provides:
 *  - gb_available()        — free GenerateBlocks active at any version
 *  - gb_version()          — free version string ('' when inactive)
 *  - gb_pro_available()    — Pro plugin active
 *  - gb_pro_version()      — Pro version string ('' when inactive)
 *
 * Scope note: this integration is an introspection layer only — block
 * catalogue, attribute schemas, presence flags. The actual reading and
 * writing of block trees on a post lives in the `novamira/gutenberg-*`
 * abilities exposed by the base Novamira (free) plugin. Pro abilities
 * here never duplicate that surface; they only surface
 * GenerateBlocks-specific metadata so the agent can compose a valid
 * `block_spec` before calling `novamira/gutenberg-add-pending-change`.
 */

/**
 * GenerateBlocks Pro version string, or '' when not installed / inactive.
 * Detection: prefer the modern `GENERATEBLOCKS_PRO_VERSION` constant; fall
 * back to a class lookup for older builds that did not expose the constant.
 */
function gb_pro_version(): string
{
    if (defined('GENERATEBLOCKS_PRO_VERSION')) {
        return (string) constant('GENERATEBLOCKS_PRO_VERSION');
    }
    if (class_exists('GenerateBlocksPro') || class_exists('GenerateBlocks_Pro')) {
        return 'unknown';
    }
    return '';
}

/**
 * Whether GenerateBlocks Pro is active.
 */
function gb_pro_available(): bool
{
    return gb_pro_version() !== '';
}
