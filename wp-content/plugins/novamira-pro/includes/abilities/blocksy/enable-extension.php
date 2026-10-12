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
 * Ability: enable one Blocksy Companion extension.
 */

wp_register_ability('novamira/blocksy-enable-extension', [
    'label' => __('Enable Blocksy Extension', domain: 'novamira-pro'),
    'description' => __(
        'Enables one Blocksy Companion extension by id (as listed by blocksy-list-extensions), through the Companion\'s own activation path: the extension\'s onActivation hook runs, it boots for the rest of this request, its id is appended to the `blocksy_active_extensions` option, and the dynamic-CSS caches refresh. Pro extensions are refused when the license plan fails the Companion boot gate (they would be stored but never boot). The whole toggle runs under a fail-closed cross-request lock on the state row with a fresh in-lock read (concurrent enables cannot lose each other\'s ids), and persistence is verified by a raw database re-read inside the lock. On failure the error says exactly what ran: bly_write_failed = state unchanged, lifecycle side effects compensated or never ran; bly_partial_side_effects = state unchanged but lifecycle side effects may remain; bly_refresh_failed = state persisted, only a post-persist step failed. For extensions that register post types or rewrites (product-reviews, woocommerce-extra, post-types-extra) the cached rewrite rules are dropped so permalinks regenerate correctly on the next request. Enabling an already-enabled extension is a no-op success (changed=false).',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Extension id from blocksy-list-extensions (e.g. `trending`, `product-reviews`, `woocommerce-extra`).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => bly_ext_toggle_output_schema(enabled_meaning: 'Always true on success.'),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_enable_extension',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The extension\'s init-time registrations (custom post types, shortcodes, widgets) take full effect on the NEXT request — init already fired in this one. Check the `warnings` list: it reports conditions like woocommerce-extra being enabled while WooCommerce is inactive (the extension then stores its state but self-guards and stays inert).',
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
function blocksy_enable_extension(array $input): array|WP_Error
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
    $row = $rows[$id];

    $installed_gate = bly_ext_installed_gate($row);
    if ($installed_gate !== null) {
        return $installed_gate;
    }
    $plan_gate = bly_ext_plan_gate($id);
    if ($plan_gate !== null) {
        return $plan_gate;
    }

    return bly_ext_locked_toggle($manager, $row, direction: 'enable');
}
