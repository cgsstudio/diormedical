<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\KadenceBlocks;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/kadenceblocks-list-blocks', [
    'label' => __('List Kadence Blocks', domain: 'novamira-pro'),
    'description' => __(
        'Lists every block registered in the `kadence/*` namespace as a compact catalogue: name, title, family (free/pro), category, is_dynamic, attribute count, plus parent / ancestor nesting hints. Use this to browse the catalogue before calling kadenceblocks-get-block-schema for the full attribute schema of a specific block. Output is intentionally lean (no attribute schemas) — call get-block-schema only on the block you intend to author.',
        domain: 'novamira-pro',
    ),
    'category' => 'kadenceblocks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'family' => [
                'type' => 'string',
                'enum' => ['free', 'pro', 'all'],
                'description' => 'Restrict to one of the two families. Defaults to `all`. Free / Pro is computed from render_callback class introspection — both live under the same `kadence/*` namespace.',
                'default' => 'all',
            ],
            'name_contains' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring filter on the block name (e.g. "form", "header", "tab"). Empty returns every block.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'blocks' => [
                'type' => 'array',
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
            'total' => ['type' => 'integer'],
        ],
        'required' => ['blocks', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\KadenceBlocks\\kadenceblocks_list_blocks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Browse the Kadence Blocks catalogue before composing a block tree. Filter by `family="pro"` to see only blocks that require Kadence Blocks Pro, or by `name_contains="form"` to narrow to a family (form / header / tab / accordion / query). Use `parent` and `ancestor` to understand nesting constraints — e.g. `column` requires a `rowlayout` ancestor; placing it elsewhere renders but breaks the row layout.',
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
function kadenceblocks_list_blocks(array $input): array
{
    $family_filter = $input['family'] ?? 'all';
    $name_filter = isset($input['name_contains']) ? strtolower($input['name_contains']) : '';

    $records = [];
    foreach (kb_registered_blocks() as $name => $block) {
        $family = kb_block_family($block);
        if ($family_filter === 'free' && $family !== 'free') {
            continue;
        }
        if ($family_filter === 'pro' && $family !== 'pro') {
            continue;
        }
        if ($name_filter !== '' && stripos($name, $name_filter) === false) {
            continue;
        }
        $records[] = kb_compact_record($block);
    }

    usort($records, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

    return ['blocks' => $records, 'total' => count($records)];
}
