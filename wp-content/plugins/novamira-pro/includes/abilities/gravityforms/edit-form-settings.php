<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-edit-form-settings', [
    'label' => __('Edit Gravity Forms Form Settings', domain: 'novamira-pro'),
    'description' => __(
        'Replace the settings block of a Gravity Forms form. Settings is the bucket of top-level keys that are NOT fields / notifications / confirmations: button, save, pagination, scheduleForm*, limitEntries*, requireLogin*, postCreation, animation, css classes, etc. The new settings map fully replaces existing settings keys; structural keys (id, title, fields, notifications, confirmations, dates, is_active, is_trash) are protected and cannot be overwritten via this ability. Use edit-form to change title / description / is_active.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Form id to edit.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Replacement settings map. Every key in this object becomes a top-level key on the form; structural keys are filtered out. Pass an empty object to clear all settings overrides (form falls back to GF defaults).',
                'additionalProperties' => true,
            ],
            'expected_date_updated' => [
                'type' => 'string',
                'description' => 'Optional CAS token (form\'s date_updated from last read).',
            ],
        ],
        'required' => ['form_id', 'settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_updated' => ['type' => 'string'],
            'entry_count' => ['type' => 'integer'],
            'settings_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['id', 'title', 'status', 'date_created', 'date_updated', 'entry_count', 'settings_keys'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_edit_form_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Replaces the settings bucket wholesale. Existing settings keys NOT present in the new map are removed. Returns settings_keys (final list of settings keys on the form) so the caller can verify. Use edit-form for partial settings patches.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, settings: array<string, mixed>, expected_date_updated?: string} $input
 * @return array{id: int, title: string, status: string, date_created: string, date_updated: string, entry_count: int, settings_keys: list<string>}|WP_Error
 */
function gravityforms_edit_form_settings(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error((int) $input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $form_id = (int) $form['id'];
    if (
        isset($input['expected_date_updated'])
        && $input['expected_date_updated'] !== (string) ($form['date_updated'] ?? '')
    ) {
        return gravityforms_concurrent_modification_error($form_id);
    }

    $structural = [
        'id',
        'title',
        'description',
        'is_active',
        'is_trash',
        'fields',
        'notifications',
        'confirmations',
        'date_created',
        'date_updated',
        'view_count',
        'lead_count',
    ];
    $new_settings = array_diff_key($input['settings'], array_flip($structural));

    $sections = gravityforms_split_form_definition($form);
    $existing_settings_keys = array_keys($sections['settings']);

    // Build the patched form: keep structural keys, fields, notifications,
    // confirmations exactly as-is; replace every non-structural top-level key
    // with the new settings map.
    $patched = $form;
    foreach ($existing_settings_keys as $key) {
        unset($patched[$key]);
    }
    /** @var mixed $value */
    foreach ($new_settings as $key => $value) {
        $patched[$key] = $value;
    }

    $result = \GFAPI::update_form($patched, $form_id);
    if ($result instanceof WP_Error) {
        return $result;
    }

    $after = \GFAPI::get_form($form_id);
    if ($after === false) {
        return gravityforms_form_not_found($form_id);
    }
    $after_sections = gravityforms_split_form_definition($after);

    return array_merge(gravityforms_form_row($after), ['settings_keys' => array_keys($after_sections['settings'])]);
}
