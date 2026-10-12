<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Answers the base plugin's `novamira_design_authority` filter for Bricks.
 * The Bricks stores (color palette, variables, theme styles, global classes)
 * lead on a Bricks site, so the declaration is:
 *
 * - `['level' => 'hybrid', 'builder' => 'Bricks']` while a Novamira design is
 *   active: the Bricks stores stay the source of truth, the design only fills
 *   gaps or applies on explicit request, and its tokens are materialized in
 *   Bricks first.
 * - `['level' => 'builder', 'builder' => 'Bricks']` without an active design:
 *   Bricks is the single source of truth and no design document is created
 *   unless the user asks for one.
 *
 * Everything else passes `$level` through untouched: Bricks not active,
 * `design_active` missing from `$context` or not a bool, or a declaration
 * already made by another builder (an array whose `builder` label is not
 * Bricks). On base-plugin builds without this filter the callback never
 * runs, which is harmless.
 *
 * @param array<string, mixed> $context
 */
function bricks_design_authority(mixed $level, array $context = []): mixed
{
    if (!defined('BRICKS_VERSION')) {
        return $level;
    }
    if (!isset($context['design_active']) || !is_bool($context['design_active'])) {
        return $level;
    }
    if (is_array($level) && isset($level['builder']) && $level['builder'] !== 'Bricks') {
        return $level;
    }

    return ['level' => $context['design_active'] ? 'hybrid' : 'builder', 'builder' => 'Bricks'];
}

/**
 * Hooks the filter once. Runs on `after_setup_theme` (the first hook where the
 * Bricks theme constant exists) so instruction previews built before the
 * abilities initialise see the answer too; a named callback keeps a repeated
 * call a no-op.
 */
function bricks_register_design_authority(): void
{
    if (!defined('BRICKS_VERSION')) {
        return;
    }

    add_filter(
        'novamira_design_authority',
        callback: __NAMESPACE__ . '\\bricks_design_authority',
        priority: 10,
        accepted_args: 2,
    );
}
