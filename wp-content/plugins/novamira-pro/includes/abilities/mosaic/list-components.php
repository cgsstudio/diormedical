<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-list-components', [
    'label' => __('List Mosaic Components', domain: 'novamira-pro'),
    'description' => __(
        'Lists Mosaic components (reusable element subtrees) under a theme, optionally filtered to one component category. Components are the building blocks an agent picks from to compose pages: buttons, cards, hero sections, navigation menus, footer rows. Summary per component: id, theme_id, name, path, parent_id, parent_type (typically `componentCategory`), ordering, status (`publish` or `delete`).',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => [
                'type' => 'string',
                'description' => 'Theme UUID. Required because components are theme-scoped.',
            ],
            'category_id' => [
                'type' => 'string',
                'description' => 'Optional component category UUID. Omit to list every component in the theme.',
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
        'required' => ['theme_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'components' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['components', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_list_components',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Components are theme-isolated. Get categories first via mosaic-list-component-categories (S4), then narrow with category_id when the theme has many components.',
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
function mosaic_list_components(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    if ($theme_id === '') {
        return mo_error('mo_invalid_input', __('theme_id is required.', domain: 'novamira-pro'));
    }
    $limit = mo_clamp_limit(isset($input['limit']) && is_int($input['limit']) ? $input['limit'] : null);
    $offset = isset($input['offset']) && is_int($input['offset']) ? max(0, $input['offset']) : 0;

    $filters = ['themeID' => $theme_id];
    if (isset($input['category_id']) && is_string($input['category_id']) && $input['category_id'] !== '') {
        $filters['parentID'] = $input['category_id'];
        $filters['parentType'] = 'componentCategory';
    }
    $rows = mo_select('components', $filters, $limit, $offset);
    $total = mo_count('components', $filters);
    $components = array_map('Novamira\\Pro\\Abilities\\Mosaic\\mo_map_component_row', $rows);

    return ['components' => $components, 'total' => $total];
}
