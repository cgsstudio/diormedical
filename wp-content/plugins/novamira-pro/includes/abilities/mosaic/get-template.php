<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-get-template', [
    'label' => __('Get Mosaic Template', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Mosaic template by id. Returns id, name, theme_id, master_id, assign (`auto` or `manual`), path, ordering, status (`publish`), version, revision, modified_gmt, and the decoded `conditions` JSON (visibility rules controlling which front-end pages the template applies to; an empty rule set decodes to `[]`). The element tree itself lives in the `nodes` table — call mosaic-get-element-tree (Sprint 3) for the canvas contents. Returns mo_not_found for missing or soft-deleted templates.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Template UUID — get one from mosaic-list-templates.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'template' => ['type' => 'object'],
        ],
        'required' => ['template'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reads the template row including the conditions JSON. The element tree is separate — use mosaic-get-element-tree per template id when you need the canvas.',
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
function mosaic_get_template(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('templates', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: template UUID. */
            __('Mosaic template %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    return ['template' => mo_present_template($row)];
}
