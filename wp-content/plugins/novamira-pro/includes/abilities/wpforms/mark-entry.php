<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const NVP_WPFORMS_ENTRY_FLAGS = ['read', 'starred'];

wp_register_ability('novamira/wpforms-mark-entry', [
    'label' => __('Mark WPForms Entry', domain: 'novamira-pro'),
    'description' => __(
        'Toggle a boolean flag on one entry without rewriting its fields. Required: entry_id, flag (read | starred), value (boolean). flag=read maps to the entry.viewed column; flag=starred maps to entry.starred. Use this for quick triage actions (mark as read, star a lead, unstar a duplicate). Pro only.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'flag' => [
                'type' => 'string',
                'enum' => NVP_WPFORMS_ENTRY_FLAGS,
                'description' => 'Which flag to set: "read" (entry.viewed) or "starred" (entry.starred).',
            ],
            'value' => [
                'type' => 'boolean',
                'description' => 'New value for the flag.',
            ],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_mark_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. value_was is the flag\'s previous state; changed is false when the call is a no-op (already at the requested value). Fields and metadata are untouched.',
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
function wpforms_mark_entry(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    if (!wpforms_pro_active()) {
        return wpforms_pro_required('entries');
    }

    $entry = wpforms_entry_or_error($input['entry_id']);
    if ($entry instanceof WP_Error) {
        return $entry;
    }
    $entry_id = (int) ($entry->entry_id ?? 0);
    // Use wpforms_view_entries (not wpforms_edit_entries) — viewed/starred
    // are read-side flags WPForms gates via the per-entry view permission,
    // not the broader entry-edit cap. With $entry_id forwarded, the
    // wpforms_user_can filter chain resolves to view_entry_single per the
    // WPForms access checker, matching what the admin UI does on the same
    // surface (audit batch 3 medium).
    $cap_error = wpforms_capability_or_error('wpforms_view_entries', $entry_id);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $flag = $input['flag'];
    $column = $flag === 'read' ? 'viewed' : 'starred';
    $value = $input['value'];
    $value_was = (bool) (int) ($entry->{$column} ?? 0);

    if ($value_was === $value) {
        return [
            'entry_id' => $entry_id,
            'flag' => $flag,
            'value_was' => $value_was,
            'value_now' => $value,
            'changed' => false,
        ];
    }

    $handler = wpforms_entry_handler();
    if ($handler === null) {
        return wpforms_pro_required('entries');
    }
    $persisted = $handler->update($entry_id, [$column => $value ? 1 : 0]);
    if (!$persisted) {
        return new WP_Error('wpforms_mark_entry_failed', "WPForms refused to set {$column} on entry {$entry_id}.", [
            'status' => 500,
        ]);
    }

    return [
        'entry_id' => $entry_id,
        'flag' => $flag,
        'value_was' => $value_was,
        'value_now' => $value,
        'changed' => true,
    ];
}
