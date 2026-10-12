<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\GhostMode\Cli;

use WP_Error;

use function Novamira\Pro\GhostMode\acknowledgement_labels;
use function Novamira\Pro\GhostMode\runtime_available;
use function Novamira\Pro\is_license_active;

if (!defined('ABSPATH')) {
    exit();
}

function register(): void
{
    if (!defined('WP_CLI') || constant('WP_CLI') !== true || !class_exists('WP_CLI') || !runtime_available()) {
        return;
    }

    \WP_CLI::add_command('novamira ghost-mode enable', __NAMESPACE__ . '\\enable_command');
    \WP_CLI::add_command('novamira ghost-mode owners remove', __NAMESPACE__ . '\\owners_remove_command');
}

/**
 * Turn on Ghost Mode, or change who sees Novamira when it is already on.
 *
 * ## OPTIONS
 *
 * --visible-to=<users>
 * : Comma-separated user IDs, logins or emails of the administrators who keep seeing Novamira.
 *
 * [--yes]
 * : Skip the confirmation.
 *
 * ## EXAMPLES
 *
 *     wp novamira ghost-mode enable --visible-to=agency-admin,12
 *
 * @param list<string> $args
 * @param array<string, mixed> $assoc_args
 */
function enable_command(array $args, array $assoc_args): void
{
    require_editable_policy();

    // @mago-expect analysis:mixed-assignment
    $raw = $assoc_args['visible-to'] ?? '';
    if (!is_string($raw) || trim($raw) === '') {
        \WP_CLI::error(__(
            'Name the administrators who keep seeing Novamira with --visible-to.',
            domain: 'novamira-pro',
        ));

        return;
    }

    $ids = [];
    foreach (array_filter(
        array_map('trim', explode(',', $raw)),
        static fn(string $item): bool => $item !== '',
    ) as $item) {
        $ids[] = resolve_user_id($item);
    }

    foreach (acknowledgement_labels() as $label) {
        \WP_CLI::line('- ' . $label);
    }
    \WP_CLI::confirm(__('Turn on Ghost Mode with these administrators?', domain: 'novamira-pro'), $assoc_args);

    report(
        \Novamira\GhostMode\save_policy(
            enabled: true,
            visible_to: $ids,
            user_id: 0,
            source: 'cli',
            pro_version: NOVAMIRA_PRO_VERSION,
        ),
        __('Ghost Mode is on.', domain: 'novamira-pro'),
    );
}

/**
 * Remove an administrator from the list of users who see Novamira.
 *
 * ## OPTIONS
 *
 * <user>
 * : User ID, login or email.
 *
 * ## EXAMPLES
 *
 *     wp novamira ghost-mode owners remove client-admin
 *
 * @param list<string> $args
 * @param array<string, mixed> $assoc_args
 */
function owners_remove_command(array $args, array $assoc_args): void
{
    $policy = require_editable_policy();
    $user_id = resolve_user_id($args[0] ?? '');

    if (!in_array($user_id, $policy['visible_to'], strict: true)) {
        \WP_CLI::error(sprintf(
            /* translators: %s: user name. */
            __('%s is not on the list.', domain: 'novamira-pro'),
            \Novamira\GhostMode\describe_user($user_id),
        ));

        return;
    }

    $remaining = \Novamira\GhostMode\valid_owner_ids(array_values(array_diff($policy['visible_to'], [$user_id])));
    if ($policy['enabled'] && $remaining === []) {
        \WP_CLI::error(__(
            'Ghost Mode needs at least one administrator on the list. Add another one first, or turn Ghost Mode off.',
            domain: 'novamira-pro',
        ));

        return;
    }

    report(
        \Novamira\GhostMode\save_policy(
            enabled: $policy['enabled'],
            visible_to: $remaining,
            user_id: 0,
            source: 'cli',
            pro_version: NOVAMIRA_PRO_VERSION,
        ),
        sprintf(
            /* translators: %s: user name. */
            __('%s no longer sees Novamira.', domain: 'novamira-pro'),
            \Novamira\GhostMode\describe_user($user_id),
        ),
    );
}

/**
 * @return array{state: 'absent'|'ok'|'unreadable', enabled: bool, visible_to: list<int>, malformed: int, schema_version: int, updated_at: int, updated_by: int, source: string, pro_version: string}
 */
function require_editable_policy(): array
{
    if (!is_license_active()) {
        \WP_CLI::error(__(
            'An active Novamira Pro license is needed to turn on or change Ghost Mode.',
            domain: 'novamira-pro',
        ));
    }

    $policy = \Novamira\GhostMode\read_policy();
    if ($policy['state'] === 'unreadable' || $policy['schema_version'] > \Novamira\GhostMode\SCHEMA_VERSION) {
        \WP_CLI::error(__(
            'These Ghost Mode settings cannot be changed here. You can still turn Ghost Mode off with: wp novamira ghost-mode disable',
            domain: 'novamira-pro',
        ));
    }

    return $policy;
}

function resolve_user_id(string $identifier): int
{
    $user = ctype_digit($identifier) ? get_user_by('id', (int) $identifier) : get_user_by('login', $identifier);
    if ($user === false && str_contains($identifier, '@')) {
        $user = get_user_by('email', $identifier);
    }
    if ($user === false) {
        /* translators: %s: user ID, login or email. */
        \WP_CLI::error(sprintf(__('User not found: %s', domain: 'novamira-pro'), $identifier));

        return 0;
    }

    return $user->ID;
}

function report(?WP_Error $error, string $success): void
{
    if ($error instanceof WP_Error) {
        \WP_CLI::error($error->get_error_message());

        return;
    }
    \WP_CLI::success($success);
}
