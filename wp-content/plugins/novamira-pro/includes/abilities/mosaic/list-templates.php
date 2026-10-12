<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-list-templates', [
    'label' => __('List Mosaic Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists templates under a specific Mosaic theme (templates are theme-scoped, so `theme_id` is required). Compact summary per template: id, name, theme_id, master_id, assign (one of `auto`/`manual`), path, ordering, status (`publish` or `delete`), modified_gmt. The `conditions` longtext is NOT in the summary; call mosaic-get-template for the full record including the visibility rules.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => [
                'type' => 'string',
                'description' => 'Theme UUID — get one via mosaic-list-themes. Required because Mosaic isolates templates per theme.',
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
            'templates' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'theme_id' => ['type' => 'string'],
                        'master_id' => ['type' => 'string'],
                        'assign' => ['type' => 'string', 'enum' => ['auto', 'manual']],
                        'path' => ['type' => 'string'],
                        'ordering' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['publish', 'delete']],
                        'modified_gmt' => ['type' => 'string'],
                    ],
                    'required' => [
                        'id',
                        'name',
                        'theme_id',
                        'master_id',
                        'assign',
                        'path',
                        'ordering',
                        'status',
                        'modified_gmt',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['templates', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always scope by theme_id (templates are theme-isolated). The summary omits `conditions` for size — call mosaic-get-template per id when you need rule details.',
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
function mosaic_list_templates(array $input): array|WP_Error
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
    $rows = mo_select('templates', $filters, $limit, $offset);
    $total = mo_count('templates', $filters);
    $templates = array_map('Novamira\\Pro\\Abilities\\Mosaic\\mo_map_template_row', $rows);

    return ['templates' => $templates, 'total' => $total];
}
