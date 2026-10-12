<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-list-themes', [
    'label' => __('List Mosaic Themes', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Mosaic theme on the install. Mosaic stores themes in the `<prefix>mosaic_themes` table (NOT WP themes — Mosaic is its own theme system); each theme owns its templates, components, collections, and design-system records. Compact summary per theme — id, name, version, ordering, status, modified_gmt. Call mosaic-get-theme for the full data blob.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'description' => 'Max rows to return. Default 100, hard cap 500.',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 100,
            ],
            'offset' => [
                'type' => 'integer',
                'description' => 'Rows to skip before returning records.',
                'minimum' => 0,
                'default' => 0,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'themes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'version' => ['type' => 'string'],
                        'revision' => ['type' => 'string'],
                        'ordering' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['publish', 'delete']],
                        'modified_gmt' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'name', 'version', 'revision', 'ordering', 'status', 'modified_gmt'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['themes', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_list_themes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Most calls start here: every other Mosaic ability is scoped by themeID. The empty-array case means the user has never run the Mosaic onboarding (or has deleted every theme); surface that distinctly.',
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
function mosaic_list_themes(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $limit = mo_clamp_limit(isset($input['limit']) && is_int($input['limit']) ? $input['limit'] : null);
    $offset = isset($input['offset']) && is_int($input['offset']) ? max(0, $input['offset']) : 0;

    $rows = mo_select('themes', [], $limit, $offset);
    $total = mo_count('themes', []);
    $themes = array_map('Novamira\\Pro\\Abilities\\Mosaic\\mo_map_theme_row', $rows);

    return ['themes' => $themes, 'total' => $total];
}
