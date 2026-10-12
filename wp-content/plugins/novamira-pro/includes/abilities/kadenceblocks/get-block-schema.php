<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\KadenceBlocks;

use WP_Block_Type_Registry;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/kadenceblocks-get-block-schema', [
    'label' => __('Get Kadence Block Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full attribute schema, nesting constraints, and supports map for a single Kadence-family block. Attributes come straight from `WP_Block_Type::$attributes` — each key carries `type`, `default`, `enum` when relevant, and any other JSON Schema fields the block author declared. `parent` / `ancestor` are nesting constraints. `uses_context` lists the block-context keys this block reads from ancestors. Pair this with kadenceblocks-list-blocks (catalogue lookup) and `novamira/gutenberg-add-pending-change` (write path).',
        domain: 'novamira-pro',
    ),
    'category' => 'kadenceblocks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Full block name, e.g. "kadence/rowlayout" or "kadence/postgrid". Must be in the `kadence/*` namespace.',
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
            'is_dynamic' => ['type' => 'boolean'],
            'attributes' => [
                'type' => 'object',
                'description' => 'Map of attribute name -> JSON Schema as declared by the block. Pass the values you want under this key when building the `attributes` field of a block_spec for gutenberg-add-pending-change.',
            ],
            'parent' => ['type' => 'array', 'items' => ['type' => 'string']],
            'ancestor' => ['type' => 'array', 'items' => ['type' => 'string']],
            'uses_context' => ['type' => 'array', 'items' => ['type' => 'string']],
            'provides_context' => ['type' => 'object'],
            'supports' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['name', 'title', 'family', 'category', 'is_dynamic', 'attributes'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\KadenceBlocks\\kadenceblocks_get_block_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass a full block name (e.g. "kadence/rowlayout") from kadenceblocks-list-blocks. Returns `kadenceblocks_not_found` WP_Error when the name is not registered or sits outside the `kadence/*` namespace. Use the `attributes` map to know which keys are accepted under `block_spec[].attributes`; respect `parent` / `ancestor` when placing the block.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{name: string} $input
 * @return array<string, mixed>|WP_Error
 */
function kadenceblocks_get_block_schema(array $input): array|WP_Error
{
    $name = $input['name'];
    if ($name === '') {
        return new WP_Error('kadenceblocks_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }
    if (!str_starts_with($name, 'kadence/')) {
        return new WP_Error('kadenceblocks_invalid_input', sprintf(
            /* translators: %s: full block name. */
            __(
                'Block "%s" is outside the Kadence namespace (`kadence/*`). Use kadenceblocks-list-blocks to browse available blocks.',
                domain: 'novamira-pro',
            ),
            $name,
        ));
    }

    $block = WP_Block_Type_Registry::get_instance()->get_registered($name);
    if ($block === null) {
        return new WP_Error('kadenceblocks_not_found', sprintf(
            /* translators: %s: full block name. */
            __(
                'Block "%s" is not registered. Verify the spelling or whether Kadence Blocks Pro is required for this block.',
                domain: 'novamira-pro',
            ),
            $name,
        ));
    }

    return kb_full_schema($block);
}
