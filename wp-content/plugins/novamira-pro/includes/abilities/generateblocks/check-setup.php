<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GenerateBlocks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: introspection-only snapshot of the GenerateBlocks install.
 */

wp_register_ability('novamira/generateblocks-check-setup', [
    'label' => __('Check GenerateBlocks Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the presence + version of GenerateBlocks (free) and GenerateBlocks Pro, plus the count of blocks registered under each namespace. This integration is an introspection layer ONLY — it does NOT read or write post_content. To read or write block trees on a post, use the `novamira/gutenberg-*` abilities exposed by the base Novamira plugin (gutenberg-get-content, gutenberg-add-pending-change, gutenberg-enable-batch-finalization, …). Use this check-setup at the start of any session that will compose GenerateBlocks block_spec payloads before submitting them via gutenberg-add-pending-change.',
        domain: 'novamira-pro',
    ),
    'category' => 'generateblocks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => [
                'type' => 'boolean',
                'description' => 'GenerateBlocks (free) plugin active.',
            ],
            'version' => ['type' => 'string'],
            'pro_active' => [
                'type' => 'boolean',
                'description' => 'GenerateBlocks Pro plugin active. Adds tabs / accordion / carousel / navigation / site-header blocks on top of the free set, plus dynamic-data binding attributes on the free blocks.',
            ],
            'pro_version' => ['type' => 'string'],
            'blocks_registered' => [
                'type' => 'object',
                'description' => 'Counts of registered blocks per family. `free` covers the `generateblocks/*` namespace; `pro` covers `generateblocks-pro/*`. Use as a sanity check: when GenerateBlocks is active, the free count is non-zero; when Pro is also active, the pro count is non-zero.',
                'properties' => [
                    'free' => ['type' => 'integer'],
                    'pro' => ['type' => 'integer'],
                ],
                'required' => ['free', 'pro'],
            ],
        ],
        'required' => ['active', 'version', 'pro_active', 'pro_version', 'blocks_registered'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GenerateBlocks\\generateblocks_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once at the start of any session that will build page content with GenerateBlocks. When `active=false`, stop and surface to the user — none of the other generateblocks-* abilities will return useful data. Use the result to gate the composition path: if `pro_active=true`, you can use the extra `generateblocks-pro/*` blocks (tabs, accordion, carousel, navigation, site-header) on top of the free ones. Reminder: this ability is introspection-only — never use it to gate a `gutenberg-write-content` call directly; native blocks always go through `gutenberg-add-pending-change` + finalization for safety.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     version: string,
 *     pro_active: bool,
 *     pro_version: string,
 *     blocks_registered: array{free: int, pro: int},
 * }
 */
function generateblocks_check_setup(): array
{
    $free_count = 0;
    $pro_count = 0;
    foreach (array_keys(gb_registered_blocks()) as $name) {
        if (gb_block_family($name) === 'pro') {
            $pro_count++;
            continue;
        }
        $free_count++;
    }
    return [
        'active' => gb_available(),
        'version' => gb_version(),
        'pro_active' => gb_pro_available(),
        'pro_version' => gb_pro_version(),
        'blocks_registered' => [
            'free' => $free_count,
            'pro' => $pro_count,
        ],
    ];
}
