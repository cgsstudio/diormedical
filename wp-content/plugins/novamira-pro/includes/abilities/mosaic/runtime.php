<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for the Mosaic Pro integration.
 *
 * Layered detection (each one checks the layer below as a hard
 * prerequisite):
 *  - mo_runtime_available() — Mosaic active at the required release AND
 *                             the core ~21 custom tables are present
 *  - mo_version()           — current Mosaic version, '' when off
 *  - mo_edition()           — 'free' | 'pro' (Mosaic defines MOSAIC_EDITION
 *                             at bootstrap, defaults to 'free' on the
 *                             public release; 'pro' on the bundled Pro
 *                             distribution)
 *
 * Detection sentinels: the `MOSAIC_VERSION` constant + the
 * `Mosaic\Database\MosaicDB` class (the canonical DB facade the rest of
 * Mosaic depends on). Together they ensure the plugin's autoloader has
 * fired AND the schema bootstrap has run before our abilities touch any
 * mosaic_* table.
 *
 * Gate functions (`mo_runtime_available`, `mo_tables_present`) live in
 * `includes/gates.php` so the manifest engine can call them at parse
 * time without loading this file eagerly.
 */

/**
 * Mosaic version string, or '' when the constant is not defined.
 */
function mo_version(): string
{
    return defined('MOSAIC_VERSION') ? (string) constant('MOSAIC_VERSION') : '';
}

/**
 * Mosaic edition — `pro` on the Pro distribution, `free` otherwise.
 * The constant is defined at the very top of `mosaic.php` via
 * `defines.php`, so it's available before any other Mosaic code runs.
 */
function mo_edition(): string
{
    if (!defined('MOSAIC_EDITION')) {
        return 'free';
    }
    $raw = (string) constant('MOSAIC_EDITION');
    return $raw === 'pro' ? 'pro' : 'free';
}

/**
 * True when running on the Pro distribution. Some abilities (component
 * marketplace ingestion, theme syncing, multi-theme switching) are
 * Pro-only.
 */
function mo_pro(): bool
{
    return mo_edition() === 'pro';
}

/**
 * Distribution channel Mosaic was installed from — typically `stable`,
 * `beta`, or `dev`. Surfaced in check-setup so the agent can flag the
 * user when a non-stable build is live (stub-drift risk is higher).
 */
function mo_channel(): string
{
    if (!defined('MOSAIC_CHANNEL')) {
        return 'stable';
    }
    return (string) constant('MOSAIC_CHANNEL');
}

/**
 * Reads the GlobalOption row Mosaic stores under the `MOSAIC_GLOBAL`
 * wp_option key. Returns an empty array when the option is missing or
 * malformed. Used by check-setup to surface plugin-level state without
 * each caller re-implementing the JSON decode.
 *
 * @return array<string, mixed>
 */
function mo_global_option(): array
{
    /** @var mixed $raw */
    $raw = get_option(option: 'MOSAIC_GLOBAL', default_value: []);
    if (is_array($raw)) {
        /** @var array<string, mixed> $raw */
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        /** @var mixed $decoded */
        $decoded = json_decode(json: $raw, associative: true);
        if (is_array($decoded)) {
            /** @var array<string, mixed> $decoded */
            return $decoded;
        }
    }
    return [];
}
