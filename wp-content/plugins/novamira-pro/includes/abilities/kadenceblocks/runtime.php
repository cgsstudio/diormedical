<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\KadenceBlocks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards + version helpers for the Kadence Blocks integration.
 *
 * Scope note: this integration is an introspection layer only — block
 * catalogue and attribute schemas. Reads and writes of block trees on a
 * post live in the `novamira/gutenberg-*` abilities exposed by the base
 * Novamira (free) plugin. Pro extends the same `kadence/*` namespace as
 * the free Blocks plugin rather than registering under a separate
 * namespace, so distinguishing free from Pro is done via render_callback
 * class introspection (see kb_block_family() in helpers.php).
 */

/**
 * Kadence Blocks Pro version string, or '' when not installed / inactive.
 * Kadence Blocks Pro publishes its version under the shortened `KBP_VERSION`
 * constant (not the `KADENCE_BLOCKS_PRO_VERSION` style other Kadence plugins
 * use). Detection falls back to the bootstrap class for older builds that
 * pre-dated the constant.
 */
function kb_pro_version(): string
{
    if (defined('KBP_VERSION')) {
        return (string) constant('KBP_VERSION');
    }
    if (defined('KADENCE_BLOCKS_PRO_VERSION')) {
        return (string) constant('KADENCE_BLOCKS_PRO_VERSION');
    }
    foreach (['Kadence_Blocks_Pro', 'KadenceBlocksPro'] as $candidate) {
        if (class_exists($candidate)) {
            return 'unknown';
        }
    }
    return '';
}

/**
 * Whether Kadence Blocks Pro is active.
 */
function kb_pro_available(): bool
{
    return kb_pro_version() !== '';
}
