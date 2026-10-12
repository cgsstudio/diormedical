<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-get-block-schema', [
    'label' => __('Get Spectra Block Schema', domain: 'novamira-pro'),
    'description' => __(
        'Return the attribute schema of a single Spectra block (`name` = `uagb/xxx` or `spectra-pro/xxx`). Output includes the block title, category, family (free|pro), whether it renders via PHP (`dynamic_render`), the `supports` map, and the full `attributes` map. Use novamira/spectra-list-blocks first to pick a name, or after check-setup to inspect a specific block.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Full block name, e.g. `uagb/container` or `spectra-pro/marketing-button`.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'family' => ['type' => 'string', 'enum' => ['free', 'pro']],
            'category' => ['type' => 'string'],
            'dynamic_render' => ['type' => 'boolean'],
            'supports' => ['type' => 'object'],
            'attributes' => ['type' => 'object'],
        ],
        'required' => ['name', 'title', 'family', 'category', 'dynamic_render', 'supports', 'attributes'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_get_block_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the attribute contract of one Spectra block. Progressive-disclosure: only call after picking a block from list-blocks.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_get_block_schema(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $name = is_string($input['name'] ?? null) ? $input['name'] : '';
    if ($name === '') {
        return sp_invalid_input('name is required');
    }

    $family = spectra_block_family($name);
    if ($family === 'unknown') {
        return sp_block_not_found($name);
    }

    // Two authoritative sources — see `spectra_registered_blocks()` for the
    // rationale. If neither knows about the name, treat it as not-found.
    $block = spectra_php_registry_index()[$name] ?? null;
    $uagb_meta = spectra_uagb_block_list()[$name] ?? null;
    if ($block === null && $uagb_meta === null) {
        return sp_block_not_found($name);
    }

    return [
        'name' => $name,
        'title' => spectra_pick_block_title($name, $block, $uagb_meta),
        'family' => $family,
        'category' => $block !== null ? $block->category ?? 'uagb' : 'uagb',
        'dynamic_render' => $block !== null && spectra_block_is_dynamic($block),
        // Cast to `object` when empty so PHP's `json_encode` emits `{}` instead
        // of `[]` — the output_schema declares `type: object`, and a strict
        // JSON-Schema client rejects an array. Static-render Spectra blocks
        // (the majority, only registered editor-side) always hit this path.
        'supports' => (object) spectra_block_supports($block),
        'attributes' => (object) spectra_block_attributes($block),
    ];
}

/**
 * Extract the `supports` map from a `WP_Block_Type`, or `[]` when the
 * block is not registered on the PHP side (static-render Spectra blocks
 * live only in `UAGB_Helper::$block_list` and expose their supports
 * exclusively via their editor-side registration).
 *
 * @return array<string, mixed>
 */
function spectra_block_supports(?\WP_Block_Type $block): array
{
    if ($block === null || !is_array($block->supports)) {
        return [];
    }
    /** @var array<string, mixed> */
    return $block->supports;
}

/**
 * Extract the `attributes` map from a `WP_Block_Type`, or `[]` on the
 * same static-render fallback as `spectra_block_supports()`.
 *
 * @return array<string, mixed>
 */
function spectra_block_attributes(?\WP_Block_Type $block): array
{
    if ($block === null || !is_array($block->attributes)) {
        return [];
    }
    /** @var array<string, mixed> */
    return $block->attributes;
}
