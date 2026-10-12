<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-get-theme', [
    'label' => __('Get Mosaic Theme', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Mosaic theme by id. Returns id, name, version, revision, ordering, status (`publish`), modified_gmt, and the full decoded `data` blob (settings, metadata, internal flags). Returns mo_not_found when the id is missing or the row has been soft-deleted (status = `delete`).',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Theme UUID — get one from mosaic-list-themes.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme' => ['type' => 'object'],
        ],
        'required' => ['theme'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_theme',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reads the full theme row including the data blob. Use mosaic-list-themes first to enumerate available ids.',
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
function mosaic_get_theme(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('themes', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: theme UUID. */
            __('Mosaic theme %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    return ['theme' => mo_present_theme($row)];
}
