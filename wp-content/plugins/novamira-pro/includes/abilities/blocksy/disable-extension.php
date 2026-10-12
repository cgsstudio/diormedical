<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: disable one Blocksy Companion extension.
 */

wp_register_ability('novamira/blocksy-disable-extension', [
    'label' => __('Disable Blocksy Extension', domain: 'novamira-pro'),
    'description' => __(
        'Disables one Blocksy Companion extension by id, through the Companion\'s own deactivation path: the extension\'s onDeactivation hook runs, its id is removed from the `blocksy_active_extensions` option, and the dynamic-CSS caches refresh. The extension\'s settings and content (its wp_options rows, posts of its custom post types) are NOT deleted — disabling only stops it from booting on future requests. The whole toggle runs under a fail-closed cross-request lock on the state row with a fresh in-lock read (concurrent toggles cannot lose each other\'s ids), and persistence is verified by a raw database re-read inside the lock. On failure the error says exactly what ran: bly_write_failed = state unchanged, lifecycle side effects compensated or never ran; bly_partial_side_effects = state unchanged but lifecycle side effects may remain; bly_refresh_failed = state persisted, only a post-persist step failed. For extensions that register post types or rewrites (product-reviews, woocommerce-extra, post-types-extra) the cached rewrite rules are dropped so the stale rules disappear on the next request. Disabling an already-disabled extension is a no-op success (changed=false).',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Extension id from blocksy-list-extensions.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => bly_ext_toggle_output_schema(enabled_meaning: 'Always false on success.'),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_disable_extension',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Within the request that disables it, an extension that was already booted stays loaded (the Companion does not tear the object down) — the disable takes full effect on the NEXT request. Content the extension created (e.g. product-review posts) survives and reappears on re-enable.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: string, name: string, enabled: bool, changed: bool, rewrite_rules_flushed: bool, warnings: list<string>}|WP_Error
 */
function blocksy_disable_extension(array $input): array|WP_Error
{
    $manager = bly_extensions_manager();
    if ($manager instanceof WP_Error) {
        return $manager;
    }

    $rows = bly_ext_rows($manager);
    $id = bly_ext_validate_id($input['id'] ?? null, $rows);
    if ($id instanceof WP_Error) {
        return $id;
    }
    return bly_ext_locked_toggle($manager, $rows[$id], direction: 'disable');
}
