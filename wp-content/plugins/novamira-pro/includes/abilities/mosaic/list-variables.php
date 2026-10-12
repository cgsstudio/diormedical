<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-list-variables', [
    'label' => __('List Mosaic Collection Variables', domain: 'novamira-pro'),
    'description' => __(
        'Lists design-token variables under a Mosaic collection. Variables are the atomic design tokens (colors, font sizes, spacings, radii, shadows) that components reference via the CSS custom property declared in `data.customProperty` (NOT the row id). Compact summary per variable: id, theme_id, collection_id, ordering, status (`publish`), name, custom_property (the CSS custom property the variable pins; empty when Mosaic derives the name from the collection and variable labels) and css_variable (the custom property name the variable emits — safe to reference as `var(<css_variable>)` only when custom_property is set — except background variables (type `imageGradient`), which emit only suffixed longhands such as `<css_variable>-background-image`; an unpinned variable is emitted only while an element references it natively and its derived name changes whenever the collection or variable label changes, so pin a custom_property with mosaic-edit-variable before using its name as literal CSS). The full `data` blob (with `usage`, `propertyGroups`, `skinsData`) is omitted from the list to keep responses small even on Figma-imported design systems with 5000+ variables; use `mosaic-get-variable` for the full record. Scope by `collection_id` (required, get one from mosaic-list-collections); optionally filter by theme_id for an additional safety check.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => [
                'type' => 'string',
                'description' => 'Theme UUID for an extra scope check.',
            ],
            'collection_id' => [
                'type' => 'string',
                'description' => 'Collection UUID — required. Get one via mosaic-list-collections.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 100,
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
            ],
        ],
        'required' => ['collection_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'variables' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['variables', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_list_variables',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Variables are atomic design tokens. The decoded `data` blob carries `name`, `customProperty`, `usage`, `propertyGroups`, and `skinsData` (skin/mode-keyed values) — NOT a single flat `value` / `type` pair.',
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
function mosaic_list_variables(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $collection_id = mo_norm_uuid($input['collection_id'] ?? null);
    if ($collection_id === '') {
        return mo_error('mo_invalid_input', __('collection_id is required.', domain: 'novamira-pro'));
    }
    $limit = mo_clamp_limit(isset($input['limit']) && is_int($input['limit']) ? $input['limit'] : null);
    $offset = isset($input['offset']) && is_int($input['offset']) ? max(0, $input['offset']) : 0;

    $filters = [
        'parentID' => $collection_id,
        'parentType' => 'collection',
    ];
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    if ($theme_id !== '') {
        $filters['themeID'] = $theme_id;
    }
    $rows = mo_select('collection_variables', $filters, $limit, $offset);
    $total = mo_count('collection_variables', $filters);
    $variables = array_map('Novamira\\Pro\\Abilities\\Mosaic\\mo_map_variable_row', $rows);

    return ['variables' => $variables, 'total' => $total];
}
