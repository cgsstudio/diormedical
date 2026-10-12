<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro;

if (!defined('ABSPATH')) {
    exit();
}

/** WP-CLI entry points for the same license lifecycle used by the admin page. */
final class LicenseCliCommand
{
    /** Show the locally recorded license status without revealing the key. */
    public function status(): void
    {
        \WP_CLI::line(is_license_active() ? 'active' : 'inactive');
    }

    /**
     * Activate a license key read from standard input, never from argv.
     *
     * ## EXAMPLES
     *
     *     printf '%s' "$NOVAMIRA_PRO_LICENSE" | wp novamira-pro license activate
     */
    public function activate(): void
    {
        $input = stream_get_contents(stream: STDIN, length: 201);
        $key = is_string($input) ? trim($input) : '';
        if (!preg_match('/^[a-z0-9._-]{8,200}$/i', $key)) {
            \WP_CLI::error('Provide a valid Novamira Pro license key on standard input.');
            return;
        }

        [$success] = activate_new_license_key($key);
        if (!$success) {
            \WP_CLI::error('License activation failed. Check Novamira → License in WordPress for details.');
            return;
        }

        \WP_CLI::success('Novamira Pro license activated on this site.');
    }

    /** Deactivate this site's license, freeing its slot when accepted upstream. */
    public function deactivate(): void
    {
        \WP_CLI::confirm('Deactivate the Novamira Pro license on this site?');
        [$success] = deactivate_license();
        if (!$success) {
            \WP_CLI::error('License deactivation failed. Check Novamira → License in WordPress for details.');
            return;
        }

        \WP_CLI::success('Novamira Pro license deactivated on this site.');
    }
}

function register_license_cli(): void
{
    \WP_CLI::add_command('novamira-pro license', new LicenseCliCommand());
}
