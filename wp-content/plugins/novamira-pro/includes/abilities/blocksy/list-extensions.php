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
 * Ability: enumerate the Blocksy Companion extensions.
 */

wp_register_ability('novamira/blocksy-list-extensions', [
    'label' => __('List Blocksy Extensions', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates every Blocksy Companion extension known to this install: the four free ones (cookies-consent, newsletter-subscribe, product-reviews, trending) and the Pro catalog (mega-menu, woocommerce-extra, custom-fonts, …). Per extension: display name, short description, free/pro flag, enabled state (the `blocksy_active_extensions` option), whether it can be enabled here (`available` — files installed AND the license plan allows it), and whether it has dashboard-level settings readable via blocksy-get-extension-settings (`has_settings` — extensions without it keep their options in the customizer, served by blocksy-list-options/get-option/edit-option).',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => new \stdClass(),
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plan' => [
                'type' => 'string',
                'description' => 'Companion license plan slug (free / personal / professional / agency, with _v2 variants) — the input to pro-extension gating.',
            ],
            'extensions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'pro' => ['type' => 'boolean'],
                        'enabled' => ['type' => 'boolean'],
                        'available' => [
                            'type' => 'boolean',
                            'description' => 'True when blocksy-enable-extension can enable it on this install: extension files present and the license plan passes the Companion boot gate.',
                        ],
                        'has_settings' => [
                            'type' => 'boolean',
                            'description' => 'True when the extension has dashboard-level settings readable via blocksy-get-extension-settings.',
                        ],
                    ],
                    'required' => ['id', 'name', 'description', 'pro', 'enabled', 'available', 'has_settings'],
                    'additionalProperties' => false,
                ],
            ],
            'extensions_omitted' => [
                'type' => 'integer',
                'description' => 'How many registered extensions were omitted beyond the 50-row cap (0 on every known Companion version — the 2.1.x catalog is 15).',
            ],
        ],
        'required' => ['plan', 'extensions', 'extensions_omitted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_list_extensions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'available=false with pro=true means the plan (or a free Companion build) blocks the extension — do not attempt to enable it. enabled=true only means the state row lists it; extensions boot on the NEXT request after being enabled, and woocommerce-extra additionally self-guards on WooCommerce being active.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{
 *     plan: string,
 *     extensions: list<array{id: string, name: string, description: string, pro: bool, enabled: bool, available: bool, has_settings: bool}>,
 *     extensions_omitted: int,
 * }|WP_Error
 */
function blocksy_list_extensions(array $input): array|WP_Error
{
    $manager = bly_extensions_manager();
    if ($manager instanceof WP_Error) {
        return $manager;
    }

    $active = bly_ext_active_raw();
    $settings_registry = bly_ext_settings_registry();

    $extensions = [];
    $omitted = 0;
    foreach (bly_ext_rows($manager) as $id => $row) {
        if (count($extensions) >= BLY_MAX_EXTENSIONS) {
            $omitted++;
            continue;
        }
        $extensions[] = [
            'id' => $id,
            'name' => $row['name'],
            'description' => $row['description'],
            'pro' => $row['pro'],
            'enabled' => in_array($id, $active, strict: true),
            'available' => $row['installed'] && bly_ext_plan_gate($id) === null,
            'has_settings' => isset($settings_registry[$id]),
        ];
    }

    return [
        'plan' => bly_companion_plan(),
        'extensions' => $extensions,
        'extensions_omitted' => $omitted,
    ];
}
