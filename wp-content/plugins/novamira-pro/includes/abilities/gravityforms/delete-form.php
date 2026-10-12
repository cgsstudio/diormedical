<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-delete-form', [
    'label' => __('Delete Gravity Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Delete a Gravity Forms form. Default trashes the form (is_trash flips to 1 — restorable from the Gravity Forms admin or by re-editing the form). Pass force=true to permanently delete the form AND all its entries (irreversible). A form already in trash is a no-op when force=false (idempotent); call with force=true to permanently delete a trashed form.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Form id to delete.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When false (default) the form is moved to trash (restorable). When true the form AND all its entries are permanently deleted.',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
            'force' => ['type' => 'boolean'],
            'status_was' => ['type' => 'string'],
            'status_now' => ['type' => ['string', 'null']],
        ],
        'required' => ['id', 'deleted', 'force', 'status_was', 'status_now'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_delete_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Trashes by default (soft, restorable). force=true permanently deletes the form AND every entry, note, and meta row associated. status_now is null after force-delete (the form no longer exists), else "trash". An already-trashed form returns deleted=true with status_was="trash" and is a no-op when force=false.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{form_id: int, force?: bool} $input
 * @return array{id: int, deleted: bool, force: bool, status_was: string, status_now: string|null}|WP_Error
 */
function gravityforms_delete_form(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error_with_trash((int) $input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = gravityforms_capability_or_error(['gravityforms_delete_forms']);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $form_id = (int) $form['id'];
    $force = ($input['force'] ?? false) === true;
    $is_trash = (int) ($form['is_trash'] ?? 0) === 1;
    $is_active = (int) ($form['is_active'] ?? 1) === 1;
    $status_was = match (true) {
        $is_trash => 'trash',
        $is_active => 'active',
        default => 'inactive',
    };

    if (!$force && $is_trash) {
        // Idempotent no-op — already trashed.
        return [
            'id' => $form_id,
            'deleted' => true,
            'force' => false,
            'status_was' => 'trash',
            'status_now' => 'trash',
        ];
    }

    if ($force) {
        $result = \GFAPI::delete_form($form_id);
        if ($result instanceof WP_Error) {
            return $result;
        }
        return [
            'id' => $form_id,
            'deleted' => true,
            'force' => true,
            'status_was' => $status_was,
            'status_now' => null,
        ];
    }

    // GFFormsModel::trash_form has an inverted return bool in current GF
    // releases ($result == false; returns true ONLY on failure). Don't trust
    // it — re-read the form and verify is_trash flipped instead.
    \GFFormsModel::trash_form($form_id);
    $after = \GFAPI::get_form($form_id);
    if ($after === false || (int) ($after['is_trash'] ?? 0) !== 1) {
        return gravityforms_operation_failed('GFFormsModel::trash_form did not trash the form.');
    }

    return [
        'id' => $form_id,
        'deleted' => true,
        'force' => false,
        'status_was' => $status_was,
        'status_now' => 'trash',
    ];
}
