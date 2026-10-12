<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-get-form', [
    'label' => __('Get a Ninja Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Fetch one Ninja Forms form by id. Always returns the compact attributes {id, title, key, created_at, updated_at, fields_count, actions_count, submissions_count}. The heavy blocks are OPT-IN via the `include` array — progressive disclosure keeps the default payload small. Valid include values: "fields" (each field as {id, type, key, label, required, order, settings} where settings is the full per-field settings map, including list-field options), "actions" (each action as {id, type, label, active, settings}), "settings" (the full form-level settings object). Returns ninja_forms_form_not_found (404) when the id is absent.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Ninja Forms form id.'],
            'include' => [
                'type' => 'array',
                'description' => 'Sub-resources to materialise. Default [] returns only the compact attributes.',
                'items' => ['type' => 'string', 'enum' => ['fields', 'actions', 'settings']],
                'default' => [],
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 8192,
                'description' => 'Per-string byte cap for included settings values (field defaults, help text, email bodies, option lists). Default 8192. Set to 0 to disable capping.',
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
            'key' => ['type' => 'string'],
            'created_at' => ['type' => ['string', 'null']],
            'updated_at' => ['type' => ['string', 'null']],
            'fields_count' => ['type' => 'integer'],
            'actions_count' => ['type' => 'integer'],
            'submissions_count' => ['type' => 'integer'],
            'fields' => ['type' => ['array', 'null']],
            'actions' => ['type' => ['array', 'null']],
            'settings' => ['type' => ['object', 'null']],
        ],
        'required' => [
            'id',
            'title',
            'key',
            'created_at',
            'updated_at',
            'fields_count',
            'actions_count',
            'submissions_count',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_get_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Opt in to `include` only for the blocks you need — the default payload is the compact attributes, which keeps the agent context small for large forms. Field choices (list-field options) live inside each field\'s `settings` block when include=["fields"]. The form-level settings (success message, layout, calculations) come with include=["settings"].',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int, include?: list<string>} $input
 *
 * @return array<string, mixed>|WP_Error
 */
function ninja_forms_get_form(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form = ninja_forms_form_or_error($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $form_id = (int) $form->get_id();
    $include = ninja_forms_normalize_include($input['include'] ?? []);
    $max_field_bytes = ninja_forms_resolve_max_field_bytes($input);

    // Compact base (id + columns + live counts), read fresh from the DB.
    $payload = ninja_forms_form_compact($form_id);

    if (in_array('fields', $include, strict: true)) {
        $payload['fields'] = ninja_forms_collect_fields($form_id, $max_field_bytes);
    }
    if (in_array('actions', $include, strict: true)) {
        $payload['actions'] = ninja_forms_collect_actions($form_id, $max_field_bytes);
    }
    if (in_array('settings', $include, strict: true)) {
        // Form-level settings can hold add-on secrets — redact then cap.
        $redacted = ninja_forms_redact_settings($form->get_settings());
        $payload['settings'] = (object) ninja_forms_cap_settings_strings($redacted['settings'], $max_field_bytes);
    }

    return $payload;
}

/**
 * Coerce the caller's `include[]` to a clean list of the documented
 * sub-resource names. Unknown values are silently dropped so a minor typo
 * doesn't fail the whole call.
 *
 * @param mixed $raw
 *
 * @return list<string>
 */
function ninja_forms_normalize_include(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $allowed = ['fields', 'actions', 'settings'];
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $value) {
        if (!is_string($value) || !in_array($value, $allowed, strict: true) || in_array($value, $out, strict: true)) {
            continue;
        }
        $out[] = $value;
    }
    return $out;
}

/**
 * Collect every field on a form as a compact-plus-settings row: the common
 * attributes surfaced for quick scanning, with the per-field settings map
 * (including list-field options) under `settings`. String settings values
 * are byte-capped at `$max_field_bytes`. Field settings are NOT secret-
 * redacted — a field's `key` is its slug, not a credential.
 *
 * @return list<array<string, mixed>>
 */
function ninja_forms_collect_fields(int $form_id, int $max_field_bytes): array
{
    $out = [];
    foreach (\Ninja_Forms()->form($form_id)->get_fields() as $field) {
        $settings = $field->get_settings();
        // @mago-expect analysis:mixed-assignment
        $capped = ninja_forms_cap_settings_strings($settings, $max_field_bytes);
        $out[] = [
            'id' => (int) $field->get_id(),
            'type' => ninja_forms_setting_str($settings, key: 'type'),
            'key' => ninja_forms_setting_str($settings, key: 'key'),
            'label' => ninja_forms_setting_str($settings, key: 'label'),
            'required' => ninja_forms_truthy($settings['required'] ?? null),
            'order' => ninja_forms_non_neg_int($settings['order'] ?? 0),
            'settings' => (object) (is_array($capped) ? $capped : []),
        ];
    }
    return $out;
}

/**
 * Collect every action (notification / redirect / success-message / webhook
 * / …) on a form as {id, type, label, active, settings, has_secrets}. Action
 * settings carry transport secrets (webhook auth, API keys, SMTP creds), so
 * they are secret-redacted before being byte-capped; `has_secrets` flags
 * whether any value was redacted.
 *
 * @return list<array<string, mixed>>
 */
function ninja_forms_collect_actions(int $form_id, int $max_field_bytes): array
{
    $out = [];
    foreach (\Ninja_Forms()->form($form_id)->get_actions() as $action) {
        $settings = $action->get_settings();
        $redacted = ninja_forms_redact_settings($settings);
        // @mago-expect analysis:mixed-assignment
        $capped = ninja_forms_cap_settings_strings($redacted['settings'], $max_field_bytes);
        $out[] = [
            'id' => (int) $action->get_id(),
            'type' => ninja_forms_setting_str($settings, key: 'type'),
            'label' => ninja_forms_setting_str($settings, key: 'label'),
            'active' => ninja_forms_truthy($settings['active'] ?? null),
            'settings' => (object) (is_array($capped) ? $capped : []),
            'has_secrets' => $redacted['has_secrets'],
        ];
    }
    return $out;
}

/**
 * Read a string setting from a settings map.
 *
 * @param array<string, mixed> $settings
 */
function ninja_forms_setting_str(array $settings, string $key): string
{
    return ninja_forms_str($settings[$key] ?? '');
}
