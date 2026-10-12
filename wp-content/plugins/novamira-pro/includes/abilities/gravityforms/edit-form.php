<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-edit-form', [
    'label' => __('Edit Gravity Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Edit a Gravity Forms form\'s top-level properties (title, description, is_active) AND/OR apply a partial settings patch. Fields, notifications and confirmations are NOT touched by this ability — use manage-fields / manage-notifications / manage-confirmations for those. Optimistic concurrency: pass expected_date_updated (the value from the last get-form / list-forms call) to detect a concurrent write — the call returns gravityforms_concurrent_modification (409) instead of overwriting.',
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
            'title' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'New title (optional). Empty string is rejected.',
            ],
            'description' => [
                'type' => 'string',
                'description' => 'New description (optional).',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'inactive'],
                'description' => 'Flip activation state (optional).',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Partial settings patch — keys in this map replace the corresponding form settings (button, save, pagination, schedule, limit-entries, require-login, postCreation, etc.). Pass only the keys you want to change.',
                'additionalProperties' => true,
            ],
            'expected_date_updated' => [
                'type' => 'string',
                'description' => 'Optional CAS token. Pass the date_updated value from the last get-form / list-forms call. If the form was modified by another writer in the meantime, the ability returns gravityforms_concurrent_modification (409) and does NOT persist any changes.',
            ],
        ],
        'required' => ['form_id'],
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
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['id', 'title', 'status', 'date_created', 'date_updated', 'entry_count', 'changed'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_edit_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Edits only the top-level form metadata + settings. To change fields/notifications/confirmations call the corresponding manage-* ability. Returns the changed[] list so the caller can see exactly which keys took effect. Pass expected_date_updated for optimistic concurrency — the call rejects with 409 if the form was touched since the read.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     title?: string,
 *     description?: string,
 *     status?: string,
 *     settings?: array<string, mixed>,
 *     expected_date_updated?: string,
 * } $input
 * @return array{id: int, title: string, status: string, date_created: string, date_updated: string, entry_count: int, changed: list<string>}|WP_Error
 */
function gravityforms_edit_form(array $input): array|WP_Error
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

    $patched = $form;
    $changed = gravityforms_apply_top_level_form_patch($input, $patched);
    if ($changed instanceof WP_Error) {
        return $changed;
    }

    if ($changed === []) {
        return array_merge(gravityforms_form_row($patched), ['changed' => []]);
    }

    $result = \GFAPI::update_form($patched, $form_id);
    if ($result instanceof WP_Error) {
        return $result;
    }

    $after = \GFAPI::get_form($form_id);
    if ($after === false) {
        return gravityforms_form_not_found($form_id);
    }

    return array_merge(gravityforms_form_row($after), ['changed' => $changed]);
}

/**
 * Apply the top-level title / description / status / settings overrides to
 * the form array in place. Returns the list of changed keys (`title`,
 * `description`, `status`, `settings.<key>`...) or a WP_Error when a value
 * is invalid (e.g. empty title).
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $patched
 * @return list<string>|WP_Error
 */
function gravityforms_apply_top_level_form_patch(array $input, array &$patched): array|WP_Error
{
    $changed = [];

    if (isset($input['title'])) {
        $new_title = trim((string) $input['title']);
        if ($new_title === '') {
            return gravityforms_invalid_input('title cannot be empty.');
        }
        if ($new_title !== (string) ($patched['title'] ?? '')) {
            $patched['title'] = $new_title;
            $changed[] = 'title';
        }
    }
    if (isset($input['description'])) {
        $new_desc = trim((string) $input['description']);
        if ($new_desc !== (string) ($patched['description'] ?? '')) {
            $patched['description'] = $new_desc;
            $changed[] = 'description';
        }
    }
    if (isset($input['status'])) {
        $new_active = $input['status'] === 'active' ? 1 : 0;
        if ($new_active !== (int) ($patched['is_active'] ?? 1)) {
            $patched['is_active'] = $new_active;
            $changed[] = 'status';
        }
    }
    if (isset($input['settings']) && is_array($input['settings'])) {
        /** @var array<string, mixed> $settings */
        $settings = $input['settings'];
        foreach (gravityforms_apply_settings_patch($settings, $patched) as $key) {
            $changed[] = "settings.{$key}";
        }
    }

    return $changed;
}

/**
 * Apply a partial settings patch onto the form array. Structural keys are
 * skipped (they have their own abilities). Returns the list of settings
 * keys that actually changed.
 *
 * @param array<string, mixed> $patch
 * @param array<string, mixed> $patched
 * @return list<string>
 */
function gravityforms_apply_settings_patch(array $patch, array &$patched): array
{
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
    ];
    $changed = [];
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        if (in_array($key, $structural, strict: true)) {
            continue;
        }
        if (!array_key_exists($key, $patched) || $patched[$key] !== $value) {
            $patched[$key] = $value;
            $changed[] = $key;
        }
    }
    return $changed;
}
