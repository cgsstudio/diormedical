<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GenerateBlocks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate every GenerateBlocks-family block registered on the
 * site as a compact catalogue. Per-attribute schemas come from
 * `generateblocks-get-block-schema`.
 */

wp_register_ability('novamira/generateblocks-list-blocks', [
    'label' => __('List GenerateBlocks Blocks', domain: 'novamira-pro'),
    'description' => __(
        'Lists every block currently registered in the `generateblocks/*` (free) or `generateblocks-pro/*` (Pro) namespace as a compact catalogue: name, title, family, category, is_dynamic, attribute count, and parent/ancestor nesting hints. Use this to browse the available blocks before calling generateblocks-get-block-schema for the full attribute schema of a specific block. Output is deliberately lean (no attribute schemas) — call get-block-schema only on the block you intend to author.',
        domain: 'novamira-pro',
    ),
    'category' => 'generateblocks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'family' => [
                'type' => 'string',
                'enum' => ['free', 'pro', 'all'],
                'description' => 'Restrict to one of the two families. Defaults to `all`.',
                'default' => 'all',
            ],
            'name_contains' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring filter on the block name (e.g. "tab", "accordion"). Empty returns every block.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'blocks' => [
                'type' => 'array',
                'description' => 'Compact block records. Each record carries `name`, `title`, `family`, `category`, `is_dynamic`, `attributes_count`, plus `parent` / `ancestor` (lists of block names that constrain where this block can be inserted). Empty lists mean no constraint.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'family' => ['type' => 'string', 'enum' => ['free', 'pro']],
                        'category' => ['type' => 'string'],
                        'is_dynamic' => ['type' => 'boolean'],
                        'attributes_count' => ['type' => 'integer'],
                        'parent' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'ancestor' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => [
                        'name',
                        'title',
                        'family',
                        'category',
                        'is_dynamic',
                        'attributes_count',
                        'parent',
                        'ancestor',
                    ],
                ],
            ],
            'total' => ['type' => 'integer', 'description' => 'Number of records returned.'],
        ],
        'required' => ['blocks', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GenerateBlocks\\generateblocks_list_blocks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Browse the GenerateBlocks catalogue before composing a block tree. Filter by `family="pro"` to see only blocks that require GB Pro, or by `name_contains="tab"` to narrow to a family by substring. Use `parent` and `ancestor` to understand nesting constraints (e.g. `accordion-toggle` requires an `accordion-item` ancestor — putting it elsewhere will render but break the interactive behaviour).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{family?: 'free'|'pro'|'all', name_contains?: string} $input
 * @return array{blocks: list<array<string, mixed>>, total: int}
 */
function generateblocks_list_blocks(array $input): array
{
    $family_filter = $input['family'] ?? 'all';
    $name_filter = isset($input['name_contains']) ? strtolower($input['name_contains']) : '';

    $records = [];
    foreach (gb_registered_blocks() as $name => $block) {
        $family = gb_block_family($name);
        if ($family_filter === 'free' && $family !== 'free') {
            continue;
        }
        if ($family_filter === 'pro' && $family !== 'pro') {
            continue;
        }
        if ($name_filter !== '' && stripos($name, $name_filter) === false) {
            continue;
        }
        $records[] = gb_compact_record($block);
    }

    usort($records, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

    return [
        'blocks' => $records,
        'total' => count($records),
    ];
}
