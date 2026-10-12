<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-list-collections', [
    'label' => __('List Mosaic Collections', domain: 'novamira-pro'),
    'description' => __(
        'Lists Mosaic design-token collections under a theme. A collection groups variables / modes / skins together — e.g. a "Brand Colors" collection holds color variables across light + dark modes. Summary per collection: id, themeID, parent_id, parent_type, ordering, status, plus the decoded `data` blob (collection name + metadata).',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => [
                'type' => 'string',
                'description' => 'Theme UUID. Required because collections are theme-scoped.',
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
            'collections' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['collections', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_list_collections',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Collections are the root of the design-token tree. Drill into each via mosaic-list-variables / mosaic-list-modes to inspect their contents.',
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
function mosaic_list_collections(array $input): array|WP_Error
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
    $rows = mo_select('collections', $filters, $limit, $offset);
    $total = mo_count('collections', $filters);
    $collections = array_map('Novamira\\Pro\\Abilities\\Mosaic\\mo_map_collection_row', $rows);

    return ['collections' => $collections, 'total' => $total];
}
