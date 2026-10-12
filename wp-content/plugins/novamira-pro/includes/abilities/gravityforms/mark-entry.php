<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-mark-entry', [
    'label' => __('Mark Gravity Forms Entry', domain: 'novamira-pro'),
    'description' => __(
        'Toggle a binary flag on an entry: read (is_read) or starred (is_starred). Returns the previous and new values so the caller can see whether the call actually flipped anything (idempotent: passing the existing value is a no-op).',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'flag' => ['type' => 'string', 'enum' => ['read', 'starred']],
            'value' => ['type' => 'boolean'],
        ],
        'required' => ['entry_id', 'flag', 'value'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer'],
            'flag' => ['type' => 'string'],
            'value_was' => ['type' => 'boolean'],
            'value_now' => ['type' => 'boolean'],
            'changed' => ['type' => 'boolean'],
        ],
        'required' => ['entry_id', 'flag', 'value_was', 'value_now', 'changed'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_mark_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'flag="read" toggles is_read; flag="starred" toggles is_starred. Idempotent — passing the existing value returns changed=false and skips the DB write. Read permission (view_entries) is sufficient — flipping flags is not a content edit.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id: int, flag: string, value: bool} $input
 * @return array{entry_id: int, flag: string, value_was: bool, value_now: bool, changed: bool}|WP_Error
 */
function gravityforms_mark_entry(array $input): array|WP_Error
{
    $entry = gravityforms_entry_or_error($input['entry_id']);
    if ($entry instanceof WP_Error) {
        return $entry;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_view_entries']);
    if ($cap !== null) {
        return $cap;
    }

    $entry_id = (int) $entry['id'];
    $flag = $input['flag'];
    $column = $flag === 'read' ? 'is_read' : 'is_starred';

    $was = (int) ($entry[$column] ?? 0) === 1;
    $new = $input['value'];
    if ($was === $new) {
        return [
            'entry_id' => $entry_id,
            'flag' => $flag,
            'value_was' => $was,
            'value_now' => $was,
            'changed' => false,
        ];
    }

    $result = \GFAPI::update_entry_property($entry_id, $column, $new ? 1 : 0);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'entry_id' => $entry_id,
        'flag' => $flag,
        'value_was' => $was,
        'value_now' => $new,
        'changed' => true,
    ];
}
