<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GenerateBlocks;

use WP_Block_Type_Registry;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: full attribute schema + nesting constraints for a single block.
 */

wp_register_ability('novamira/generateblocks-get-block-schema', [
    'label' => __('Get GenerateBlocks Block Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full attribute schema, nesting constraints, and supports map for a single GenerateBlocks-family block. Attributes come straight from `WP_Block_Type::$attributes` — each key carries `type`, `default`, `enum` when relevant, and any other JSON Schema fields the block author declared. `parent` / `ancestor` are nesting constraints (the block can only live inside one of these). `uses_context` lists the block-context keys this block reads from its ancestors (e.g. a `loop-item` reads the `query` context). Pair this with generateblocks-list-blocks (catalogue lookup) and `novamira/gutenberg-add-pending-change` (write path).',
        domain: 'novamira-pro',
    ),
    'category' => 'generateblocks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Full block name, e.g. "generateblocks/element" or "generateblocks-pro/accordion-toggle". Must be in the `generateblocks/*` or `generateblocks-pro/*` namespace.',
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
            'parent' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Allowed direct parents. Empty list = no constraint.',
            ],
            'ancestor' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Required ancestor (anywhere up the tree). Empty list = no constraint.',
            ],
            'uses_context' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Block-context keys consumed from ancestors.',
            ],
            'provides_context' => [
                'type' => 'object',
                'description' => 'Block-context keys this block exposes to its descendants. Map of context-key -> attribute-name.',
            ],
            'supports' => [
                'type' => 'object',
                'description' => 'WP block supports map (align, anchor, etc.).',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['name', 'title', 'family', 'category', 'is_dynamic', 'attributes'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GenerateBlocks\\generateblocks_get_block_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass a full block name (e.g. "generateblocks/element") from generateblocks-list-blocks. Returns `generateblocks_not_found` WP_Error when the name is not registered or sits outside the GenerateBlocks namespaces. Use the `attributes` map to know which keys are accepted under `block_spec[].attributes`; respect `parent` / `ancestor` when placing the block (a block placed outside its required ancestor still renders but may break interactive behaviour).',
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
function generateblocks_get_block_schema(array $input): array|WP_Error
{
    $name = $input['name'];
    if ($name === '') {
        return new WP_Error('generateblocks_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }
    if (gb_block_family($name) === '') {
        return new WP_Error('generateblocks_invalid_input', sprintf(
            /* translators: %s: full block name. */
            __(
                'Block "%s" is outside the GenerateBlocks namespaces (`generateblocks/*` or `generateblocks-pro/*`). Use generateblocks-list-blocks to browse available blocks.',
                domain: 'novamira-pro',
            ),
            $name,
        ));
    }

    $block = WP_Block_Type_Registry::get_instance()->get_registered($name);
    if ($block === null) {
        return new WP_Error('generateblocks_not_found', sprintf(
            /* translators: %s: full block name. */
            __(
                'Block "%s" is not registered. Verify the spelling or the active plugin version.',
                domain: 'novamira-pro',
            ),
            $name,
        ));
    }

    return gb_full_schema($block);
}
