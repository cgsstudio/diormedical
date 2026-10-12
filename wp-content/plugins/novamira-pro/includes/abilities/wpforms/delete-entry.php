<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-delete-entry', [
    'label' => __('Delete WPForms Entry', domain: 'novamira-pro'),
    'description' => __(
        'Permanently delete one submission (entry). Required: entry_id. Unlike forms, entries have no trash state — this is an immediate, irreversible delete. Per-field rows in wp_wpforms_entry_fields and per-entry meta in wp_wpforms_entry_meta are also removed. Pro only.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
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
            'entry_id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
        'required' => ['entry_id', 'form_id', 'deleted'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_delete_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Irreversible — entries have no trash state. The form definition is NOT affected; only the submission and its child rows. A second call on the same entry_id returns wpforms_entry_not_found 404 (response shape differs between first and second call, so the annotation is honest about not being strictly idempotent).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{entry_id: int} $input
 * @return array{entry_id: int, form_id: int, deleted: bool}|WP_Error
 */
function wpforms_delete_entry(array $input): array|WP_Error
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
    $cap_error = wpforms_capability_or_error('wpforms_delete_entries', (int) ($entry->entry_id ?? 0));
    if ($cap_error !== null) {
        return $cap_error;
    }

    $handler = wpforms_entry_handler();
    if ($handler === null) {
        return wpforms_pro_required('entries');
    }

    $entry_id = (int) ($entry->entry_id ?? 0);
    $form_id = (int) ($entry->form_id ?? 0);

    $result = $handler->delete($entry_id);
    if (!$result) {
        return new WP_Error('wpforms_delete_entry_failed', "WPForms refused to delete entry {$entry_id}.", [
            'status' => 500,
        ]);
    }

    return [
        'entry_id' => $entry_id,
        'form_id' => $form_id,
        'deleted' => true,
    ];
}
