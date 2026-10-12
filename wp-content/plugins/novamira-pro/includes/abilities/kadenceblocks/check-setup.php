<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\KadenceBlocks;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/kadenceblocks-check-setup', [
    'label' => __('Check Kadence Blocks Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the presence + version of Kadence Blocks (free) and Kadence Blocks Pro, plus the count of registered blocks per family. This integration is an introspection layer ONLY — it does NOT read or write post_content. To read or write block trees on a post, use the `novamira/gutenberg-*` abilities exposed by the base Novamira plugin. Note: Kadence Blocks Pro extends the same `kadence/*` namespace as the free plugin (unlike GenerateBlocks which uses a separate `generateblocks-pro/*` namespace). The free/Pro distinction is recovered by inspecting each block\'s render_callback class — surfaced here as a count per family for at-a-glance sanity checks.',
        domain: 'novamira-pro',
    ),
    'category' => 'kadenceblocks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => ['type' => 'boolean'],
            'version' => ['type' => 'string'],
            'pro_active' => ['type' => 'boolean'],
            'pro_version' => ['type' => 'string'],
            'blocks_registered' => [
                'type' => 'object',
                'description' => 'Counts of registered blocks per family. Both `free` and `pro` blocks live in the `kadence/*` namespace; the split is computed via render_callback class introspection.',
                'properties' => [
                    'free' => ['type' => 'integer'],
                    'pro' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                ],
                'required' => ['free', 'pro', 'total'],
            ],
        ],
        'required' => ['active', 'version', 'pro_active', 'pro_version', 'blocks_registered'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\KadenceBlocks\\kadenceblocks_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once at the start of any session that composes Kadence Blocks content. When `active=false`, stop and surface to the user — none of the other kadenceblocks-* abilities will return useful data. `pro_active=true` unlocks the ~29 extra blocks (dynamichtml, dynamiclist, postgrid, query, repeater, modal, splitcontent, advanced-form*, header-*, off-canvas, slider, …) on top of the ~58 free blocks. Reminder: introspection-only — write block trees via the base Novamira plugin\'s `novamira/gutenberg-*` pipeline.',
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
 *     blocks_registered: array{free: int, pro: int, total: int},
 * }
 */
function kadenceblocks_check_setup(): array
{
    $free_count = 0;
    $pro_count = 0;
    foreach (kb_registered_blocks() as $block) {
        if (kb_block_family($block) === 'pro') {
            $pro_count++;
            continue;
        }
        $free_count++;
    }
    return [
        'active' => kb_available(),
        'version' => kb_version(),
        'pro_active' => kb_pro_available(),
        'pro_version' => kb_pro_version(),
        'blocks_registered' => [
            'free' => $free_count,
            'pro' => $pro_count,
            'total' => $free_count + $pro_count,
        ],
    ];
}
