<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-delete-entry', [
    'label' => __('Delete Gravity Forms Entry', domain: 'novamira-pro'),
    'description' => __(
        'Delete a Gravity Forms entry. Default trashes the entry (status flips to "trash" — restorable from the GF admin). Pass force=true to permanently delete (irreversible). Returns the previous status so the caller can confirm what was removed.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When false (default) the entry is moved to trash (restorable). When true the entry is permanently deleted.',
            ],
        ],
        'required' => ['entry_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
            'force' => ['type' => 'boolean'],
            'status_was' => ['type' => 'string'],
            'status_now' => ['type' => ['string', 'null']],
        ],
        'required' => ['entry_id', 'form_id', 'deleted', 'force', 'status_was', 'status_now'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_delete_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Trashes by default (soft, restorable). force=true permanently deletes. status_now is null after force-delete (the entry no longer exists), else "trash". A second force=true call on the same id returns gravityforms_entry_not_found — delete is NOT idempotent on permanent delete.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{entry_id: int, force?: bool} $input
 * @return array{entry_id: int, form_id: int, deleted: bool, force: bool, status_was: string, status_now: string|null}|WP_Error
 */
function gravityforms_delete_entry(array $input): array|WP_Error
{
    $entry = gravityforms_entry_or_error($input['entry_id']);
    if ($entry instanceof WP_Error) {
        return $entry;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_delete_entries']);
    if ($cap !== null) {
        return $cap;
    }

    $entry_id = (int) $entry['id'];
    $form_id = (int) ($entry['form_id'] ?? 0);
    $force = ($input['force'] ?? false) === true;
    $status_was = (string) ($entry['status'] ?? 'active');

    if ($force) {
        $result = \GFAPI::delete_entry($entry_id);
        if ($result instanceof WP_Error) {
            return $result;
        }
        return [
            'entry_id' => $entry_id,
            'form_id' => $form_id,
            'deleted' => true,
            'force' => true,
            'status_was' => $status_was,
            'status_now' => null,
        ];
    }

    if ($status_was === 'trash') {
        return [
            'entry_id' => $entry_id,
            'form_id' => $form_id,
            'deleted' => true,
            'force' => false,
            'status_was' => 'trash',
            'status_now' => 'trash',
        ];
    }

    $result = \GFAPI::update_entry_property($entry_id, 'status', 'trash');
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'entry_id' => $entry_id,
        'form_id' => $form_id,
        'deleted' => true,
        'force' => false,
        'status_was' => $status_was,
        'status_now' => 'trash',
    ];
}
