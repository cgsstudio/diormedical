<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraBlocks;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-blocks-list-blocks', [
    'label' => __('List Spectra Blocks Blocks', domain: 'novamira-pro'),
    'description' => __(
        'Enumerate the Spectra Blocks block types registered with WordPress (namespaces spectra/* and spectra-pro/*). Returns compact records: name, title, pro (bool), active (activation state from the _spectra_blocks_blocks map — absent entries default to active; only free spectra/* blocks participate), parent (block.json parent list; empty for top-level blocks). Filters: `namespace` (free|pro|all — default all), `name_contains` (case-insensitive substring), `top_level_only` (drop child blocks that require a parent). `limit` clamps the result (default 200, hard cap 500).',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-blocks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'namespace' => [
                'type' => 'string',
                'enum' => ['free', 'pro', 'all'],
                'description' => 'Restrict to `free` (spectra/*), `pro` (spectra-pro/*), or `all` (default).',
            ],
            'name_contains' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match on the full block name (e.g. `form` matches spectra-pro/form-wrapper).',
            ],
            'top_level_only' => [
                'type' => 'boolean',
                'description' => 'When true, exclude child blocks (those declaring a block.json `parent`). Default false.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Max records returned (default 200, hard cap 500 — progressive disclosure).',
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
                        'pro' => ['type' => 'boolean'],
                        'active' => ['type' => 'boolean'],
                        'parent' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Block names this block must nest under; empty for top-level blocks.',
                        ],
                    ],
                    'required' => ['name', 'title', 'pro', 'active', 'parent'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
        'required' => ['blocks', 'total', 'truncated'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraBlocks\\spectra_blocks_list_blocks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discovery of the Spectra Blocks block surface. Use before generating spectra/* markup so the agent picks a block that exists and is active on this install; child blocks (non-empty parent) cannot stand alone.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/** Default number of block records returned per call. */
const SPECTRA_BLOCKS_LIST_BLOCKS_DEFAULT_LIMIT = 200;

/** Hard cap on block records per call — progressive disclosure. */
const SPECTRA_BLOCKS_LIST_BLOCKS_MAX_LIMIT = 500;

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_blocks_list_blocks(array $input): array|WP_Error
{
    if (!spectra_blocks_plugin_available()) {
        return sb_not_active();
    }

    $namespace = is_string($input['namespace'] ?? null) ? $input['namespace'] : 'all';
    if (!in_array($namespace, ['free', 'pro', 'all'], strict: true)) {
        return sb_invalid_input('namespace must be one of: free, pro, all');
    }
    $name_contains = is_string($input['name_contains'] ?? null) ? strtolower($input['name_contains']) : '';
    $limit = sb_coerce_int($input['limit'] ?? SPECTRA_BLOCKS_LIST_BLOCKS_DEFAULT_LIMIT, minimum: 1);
    if ($limit > SPECTRA_BLOCKS_LIST_BLOCKS_MAX_LIMIT) {
        $limit = SPECTRA_BLOCKS_LIST_BLOCKS_MAX_LIMIT;
    }

    $records = sb_list_blocks_records($input, $namespace, $name_contains);

    $total = count($records);
    $truncated = false;
    if ($total > $limit) {
        $records = array_slice($records, offset: 0, length: $limit);
        $truncated = true;
    }

    return [
        'blocks' => $records,
        'total' => $total,
        'truncated' => $truncated,
    ];
}

/**
 * Build the filtered compact block records from the registry snapshot. Kept
 * out of the callback so the input-validation branches and the per-record
 * filter branches stay under the complexity budget separately. Takes the raw
 * input bag for the `top_level_only` flag (already schema-validated as
 * boolean) and the pre-normalized namespace / substring filters.
 *
 * @param array<string, mixed> $input
 * @return list<array<string, mixed>>
 */
function sb_list_blocks_records(array $input, string $namespace, string $name_contains): array
{
    $top_level_only = ($input['top_level_only'] ?? false) === true;
    $map = sb_activation_map();
    $records = [];
    foreach (sb_registry_index() as $name => $block_type) {
        $family = sb_block_family($name);
        if ($namespace !== 'all' && $namespace !== $family) {
            continue;
        }
        if ($name_contains !== '' && !str_contains(strtolower($name), $name_contains)) {
            continue;
        }
        $parent = sb_block_parent_list($block_type);
        if ($top_level_only && $parent !== []) {
            continue;
        }
        $records[] = [
            'name' => $name,
            'title' => $block_type->title !== '' ? $block_type->title : $name,
            'pro' => $family === 'pro',
            'active' => sb_block_is_active($name, $map),
            'parent' => $parent,
        ];
    }
    return $records;
}

/**
 * Normalize `WP_Block_Type::$parent` into a clean string list. block.json
 * declares `parent` as an array of block names; the property is null for
 * top-level blocks, and a defensive string branch covers hand-registered
 * block types that pass a bare name.
 *
 * @return list<string>
 */
function sb_block_parent_list(\WP_Block_Type $block_type): array
{
    /** @var mixed $raw */
    $raw = $block_type->parent;
    if (is_string($raw) && $raw !== '') {
        return [$raw];
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $name */
    foreach ($raw as $name) {
        if (!is_string($name) || $name === '') {
            continue;
        }
        $out[] = $name;
    }
    return $out;
}
