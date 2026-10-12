<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * `gravityforms_min_runtime_available()` has been moved to includes/gates.php
 * (loaded early by novamira-pro.php) so the manifest gate closure can reference
 * it without loading this file at plugin bootstrap. This file is now loaded
 * lazily by novamira_pro_load_gravityforms_abilities() as its first step.
 */
