<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * ACPT Pro license is currently valid. v1 abilities don't gate on this, but
 * `acpt-check-setup` surfaces it so the agent can warn the user when an
 * operation would require Pro features.
 */
function acpt_license_active(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (!class_exists('ACPT\\Admin\\ACPT_License_Manager')) {
        $cache = false;
        return false;
    }
    return \ACPT\Admin\ACPT_License_Manager::isLicenseValid();
}
