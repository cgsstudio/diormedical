<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-get-entry', [
    'label' => __('Get Gravity Forms Entry', domain: 'novamira-pro'),
    'description' => __(
        'Return one Gravity Forms entry. Includes the entry meta (id, form_id, status, date_created, date_updated, is_read, is_starred, ip, user_agent, source_url, created_by) and the field values keyed by input id (so for a "1.3" sub-input the key is "1.3"). Each field value is a string — multi-input fields (name, address, checkbox) surface as multiple keys.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['entry_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_updated' => ['type' => 'string'],
            'is_read' => ['type' => 'boolean'],
            'is_starred' => ['type' => 'boolean'],
            'ip' => ['type' => 'string'],
            'user_agent' => ['type' => 'string'],
            'source_url' => ['type' => 'string'],
            'created_by' => ['type' => ['integer', 'null']],
            'fields' => ['type' => 'object'],
        ],
        'required' => ['id', 'form_id', 'status', 'date_created', 'date_updated', 'fields'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_get_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns entry meta + field values. Multi-input fields surface as multiple keyed entries in fields{} (e.g. "1.3" for first name on a name field). For the field schema of the parent form call get-form.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id: int} $input
 * @return array<string, mixed>|WP_Error
 */
function gravityforms_get_entry(array $input): array|WP_Error
{
    $entry = gravityforms_entry_or_error($input['entry_id']);
    if ($entry instanceof WP_Error) {
        return $entry;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_view_entries']);
    if ($cap !== null) {
        return $cap;
    }

    return gravityforms_entry_detail($entry);
}
