<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-list-blocks', [
    'label' => __('List Spectra Blocks', domain: 'novamira-pro'),
    'description' => __(
        'Enumerate the Spectra blocks registered with WordPress. Returns compact records (name, family free|pro, category, dynamic_render, title). Filters: `family` (free|pro|all — default all), `name_contains` (substring match on the block name; case-insensitive), `category` (exact match on the block editor category). Progressive-disclosure: use novamira/spectra-get-block-schema for the full attribute schema of a specific block.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'family' => [
                'type' => 'string',
                'enum' => ['free', 'pro', 'all'],
                'description' => 'Restrict to `free` (uagb/*), `pro` (spectra-pro/*), or `all` (default).',
            ],
            'name_contains' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match on the full block name (e.g. `forms` matches `uagb/forms` and `uagb/cf7-styler`).',
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Exact-match filter on the block editor category (e.g. `uagb`).',
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
                        'family' => ['type' => 'string', 'enum' => ['free', 'pro']],
                        'category' => ['type' => 'string'],
                        'dynamic_render' => ['type' => 'boolean'],
                        'title' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'family', 'category', 'dynamic_render', 'title'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
        'required' => ['blocks', 'total', 'truncated'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_list_blocks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discovery of the Spectra block surface. Use before generating uagb/* markup so the agent picks a block that actually exists on this install.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/** Maximum number of block records returned per call — progressive disclosure cap. */
const SPECTRA_LIST_BLOCKS_CAP = 500;

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_list_blocks(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $family = is_string($input['family'] ?? null) ? $input['family'] : 'all';
    if (!in_array($family, ['free', 'pro', 'all'], strict: true)) {
        return sp_invalid_input('family must be one of: free, pro, all');
    }

    $name_contains = is_string($input['name_contains'] ?? null) ? strtolower($input['name_contains']) : '';
    $category = is_string($input['category'] ?? null) ? $input['category'] : '';

    $blocks = spectra_registered_blocks($family);

    if ($name_contains !== '') {
        $blocks = array_values(array_filter($blocks, static fn(array $b): bool => str_contains(
            strtolower((string) $b['name']),
            $name_contains,
        )));
    }
    if ($category !== '') {
        $blocks = array_values(array_filter(
            $blocks,
            static fn(array $b): bool => (string) $b['category'] === $category,
        ));
    }

    $total = count($blocks);
    $truncated = false;
    if ($total > SPECTRA_LIST_BLOCKS_CAP) {
        $blocks = array_slice($blocks, offset: 0, length: SPECTRA_LIST_BLOCKS_CAP);
        $truncated = true;
    }

    return [
        'blocks' => $blocks,
        'total' => $total,
        'truncated' => $truncated,
    ];
}
