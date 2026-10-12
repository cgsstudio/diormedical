<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-delete-form', [
    'label' => __('Delete a Ninja Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Permanently delete a Ninja Forms form by id. This CASCADES: the form, all its fields, all its actions, AND all its submissions are removed (Ninja Forms has no per-form trash — deletion is immediate and irreversible). Idempotent: deleting a form that does not exist returns {deleted: false} rather than an error, so a retry is safe. Returns {deleted: bool, form_id}.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'form_id' => ['type' => 'integer'],
        ],
        'required' => ['deleted', 'form_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_delete_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'IRREVERSIBLE cascade delete (form + fields + actions + submissions). Idempotent — a missing form returns deleted:false, not an error. Confirm with the user before deleting a form that has submissions. Capability: manage_options.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int} $input
 *
 * @return array{deleted: bool, form_id: int}|WP_Error
 */
function ninja_forms_delete_form(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form_id = ninja_forms_pos_int($input['form_id'] ?? null);
    if ($form_id <= 0) {
        return ninja_forms_invalid('form_id must be a positive integer.');
    }

    // Idempotent: a missing form is a no-op success, not a 404.
    if (!ninja_forms_row_exists('nf3_forms', $form_id)) {
        return ['deleted' => false, 'form_id' => $form_id];
    }

    // Form::delete() cascades fields / actions / submissions and busts the
    // form cache on its own.
    \Ninja_Forms()->form($form_id)->get()->delete();

    return ['deleted' => true, 'form_id' => $form_id];
}
