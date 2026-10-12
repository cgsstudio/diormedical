<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

/**
 * Ability: Get the settable-field schema for a single Divi 5 module.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-get-module-schema', [
    'label' => __('Get Divi Module Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the settable content/configuration fields for one Divi 5 module as a list of field objects, each carrying its canonical attrName path (e.g. "content.innerContent"). Each field reports its label, description, category, editor component, value sub-fields, and whether it accepts dynamic content. Also lists the universal style groups available under module.decoration.* (background, spacing, border, fonts, …) and the module\'s allowed children. Accepts the module name with or without the "divi/" prefix. This is the per-module companion to novamira/divi-list-modules.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => [
                'type' => 'string',
                'description' => 'Module name, e.g. "divi/text" or "text".',
            ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'children' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'A list of content/configuration field objects, each with an attrName.',
            ],
            'style_groups' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Universal decoration groups available under module.decoration.*',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_get_module_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a module\'s fields before setting them. The "fields" list holds the module-specific content/config attrs (with their attrName path and whether they take dynamic content). Universal styles (background, spacing, border, fonts) are not listed per-field — they live under module.decoration.<group> for every module; see style_groups.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|\WP_Error
 */
function divi_get_module_schema(array $input)
{
    $requested = is_string($input['module'] ?? null) ? $input['module'] : '';
    if (trim($requested) === '') {
        return divi_error('invalid_input', message: 'A "module" name is required.');
    }

    $name = divi_normalize_module_name($requested);
    $meta = divi_module_metadata($name);
    if ($meta === null) {
        return divi_error(
            'unknown_module',
            sprintf('Unknown Divi module "%s". Use novamira/divi-list-modules to discover valid names.', $name),
            status: 404,
        );
    }

    /** @var array<string, mixed> $attributes */
    $attributes = is_array($meta['attributes'] ?? null) ? $meta['attributes'] : [];

    return [
        'name' => $name,
        'title' => is_string($meta['title'] ?? null) ? $meta['title'] : $name,
        'category' => is_string($meta['category'] ?? null) ? $meta['category'] : 'module',
        'children' => divi_children_names($meta),
        'fields' => divi_module_fields($name, $attributes),
        'style_groups' => divi_style_groups($attributes),
    ];
}
