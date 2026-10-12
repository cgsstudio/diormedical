<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Runtime gates for the Fluent Forms specialization.
 *
 * `ff_min_runtime_available()` has been moved to includes/gates.php (loaded
 * early by novamira-pro.php) so the manifest gate closure can reference it
 * without loading this file at plugin bootstrap. This file is loaded lazily by
 * novamira_pro_load_fluentforms_abilities() as its first step.
 *
 * Free FF defines both `FLUENTFORM` (the plugin basename) and
 * `FLUENTFORM_VERSION`. FF Pro hard-depends on free and defines
 * `FLUENTFORMPRO` + `FLUENTFORMPRO_VERSION`. License status is informational
 * only — Pro abilities run unlicensed on a Pro install (same semantics as the
 * SeoPress, AIOSEO, and Yoast integrations).
 */

/**
 * Whether Fluent Forms Pro is loaded AND meets the minimum supported version.
 * Used to gate registration of Pro-only abilities (transactions, subscriptions,
 * draft submissions, quiz) so they never appear in `discover-abilities` on
 * sites that don't have Pro.
 */
function ff_pro_runtime_available(): bool
{
    return (
        ff_min_runtime_available()
        && defined('FLUENTFORMPRO_VERSION')
        && version_compare(
            version1: (string) constant('FLUENTFORMPRO_VERSION'),
            version2: \NOVAMIRA_PRO_FLUENTFORMS_PRO_MIN_VERSION,
            operator: '>=',
        )
    );
}
