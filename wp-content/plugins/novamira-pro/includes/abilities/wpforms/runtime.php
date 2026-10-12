<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * `wpforms_min_runtime_available()` has been moved to includes/gates.php (loaded
 * early by novamira-pro.php) so the manifest gate closure can reference it without
 * loading this file at plugin bootstrap. The remaining helpers in this file are
 * loaded lazily by the WPForms loader (novamira_pro_load_wpforms_abilities).
 */

/**
 * Whether WPForms Pro is active — gates the entries CRUD abilities + Pro-only
 * field types (file-upload, payment, signature, etc.).
 *
 * WPForms ships Lite and Pro from the same plugin slug branch; the Pro presence
 * marker is the `pro/wpforms-pro.php` bootstrap file. The runtime check below
 * mirrors what the WPForms loader uses internally.
 */
function wpforms_pro_active(): bool
{
    if (!wpforms_min_runtime_available()) {
        return false;
    }
    if (defined('WPFORMS_PLUGIN_SLUG') && (string) constant('WPFORMS_PLUGIN_SLUG') === 'wpforms') {
        return true;
    }
    return defined('WPFORMS_PLUGIN_DIR') && is_file((string) constant('WPFORMS_PLUGIN_DIR') . 'pro/wpforms-pro.php');
}
