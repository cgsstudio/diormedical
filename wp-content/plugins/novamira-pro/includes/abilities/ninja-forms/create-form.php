<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-create-form', [
    'label' => __('Create a Ninja Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Create a new Ninja Forms form. Required: title (non-empty string). Optional: fields[] — each {type (required, must be a slug from ninja-forms-get-field-types-schema), label?, key? (auto-derived from label when omitted, made unique per form), required? (bool), settings? (object of extra per-type settings)}; actions[] — each {type (e.g. "email", "successmessage", "redirect"), label?, active? (bool, default true), settings? (object)}. A submit button is appended automatically when fields are supplied and none is of type "submit" (a Ninja form is unusable without one). Field types are validated up front — an unknown type fails the whole call with ninja_forms_invalid_input. Returns the new form as a compact row {id, title, key, fields_count, actions_count, submissions_count, created_at, updated_at} plus warnings[] (auto-added submit, derived/uniquified keys).',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'Form title (required, non-empty).'],
            'fields' => [
                'type' => 'array',
                'description' => 'Field descriptors to create, in order.',
                'items' => ['type' => 'object'],
                'default' => [],
            ],
            'actions' => [
                'type' => 'array',
                'description' => 'Action descriptors (notifications / redirects / success messages).',
                'items' => ['type' => 'object'],
                'default' => [],
            ],
        ],
        'required' => ['title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form' => ['type' => 'object'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['form', 'warnings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_create_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Validate field type slugs with ninja-forms-get-field-types-schema FIRST — an unknown type fails the whole create. A submit button is auto-appended when you pass fields[] without one. For incremental field changes after creation use ninja-forms-add-field / edit-field / delete-field; for notifications use ninja-forms-create-notification. Capability: manage_options.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{title?: string, fields?: list<mixed>, actions?: list<mixed>} $input
 *
 * @return array{form: array<string, mixed>, warnings: list<string>}|WP_Error
 */
function ninja_forms_create_form(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $title = isset($input['title']) ? trim(ninja_forms_str($input['title'])) : '';
    if ($title === '') {
        return ninja_forms_invalid('title is required and must be a non-empty string.');
    }

    $fields_raw = is_array($input['fields'] ?? null) ? array_values($input['fields']) : [];
    $actions_raw = is_array($input['actions'] ?? null) ? array_values($input['actions']) : [];

    // Validate every field type before any write so a bad type fails clean.
    $type_error = ninja_forms_validate_field_types($fields_raw);
    if ($type_error instanceof WP_Error) {
        return $type_error;
    }

    $form_id = ninja_forms_persist_form_shell($title);
    if ($form_id <= 0) {
        return ninja_forms_error('ninja_forms_create_failed', message: 'Failed to persist the new form.');
    }

    /** @var list<string> $warnings */
    $warnings = [];
    ninja_forms_create_fields($form_id, $fields_raw, $warnings);
    ninja_forms_create_actions($form_id, $actions_raw, $warnings);
    ninja_forms_bust_cache($form_id);

    return [
        'form' => ninja_forms_form_compact($form_id),
        'warnings' => $warnings,
    ];
}

/**
 * Up-front validation of every fields[] entry: each must be an object with a
 * `type` that exists in the live field registry. Returns a WP_Error on the
 * first offender, or null when all types are valid.
 *
 * @param list<mixed> $fields_raw
 */
function ninja_forms_validate_field_types(array $fields_raw): ?WP_Error
{
    $registry = ninja_forms_field_registry();
    // @mago-expect analysis:mixed-assignment
    foreach ($fields_raw as $field) {
        if (!is_array($field)) {
            return ninja_forms_invalid('each fields[] entry must be an object.');
        }
        $type = isset($field['type']) ? ninja_forms_str($field['type']) : '';
        if ($type === '' || !isset($registry[$type])) {
            return ninja_forms_invalid(sprintf(
                "Unknown field type '%s'. Call ninja-forms-get-field-types-schema for the catalog.",
                $type,
            ));
        }
    }
    return null;
}

/**
 * Inserts the form shell (title + sensible defaults) and returns its new id
 * (0 on failure). Instantiates the form model DIRECTLY rather than via
 * `Ninja_Forms()->form()->get()` — the factory caches a single new-form
 * object per request (`static $forms['']`), so a second create in the same
 * request would otherwise re-save (and mutate) the first form instead of
 * inserting a new one.
 */
function ninja_forms_persist_form_shell(string $title): int
{
    global $wpdb;
    $form = new \NF_Database_Models_Form($wpdb, '');
    $form->update_settings([
        'title' => $title,
        'default_label_pos' => 'above',
        'show_title' => 1,
        'created_at' => current_time('mysql'),
    ]);
    $form->save();
    $form_id = (int) $form->get_id();
    if ($form_id <= 0) {
        return 0;
    }
    ninja_forms_set_form_key($form_id, $title);
    return $form_id;
}

/**
 * Creates the caller's fields in order (unique keys) and appends a submit
 * button when fields are present and none is a submit.
 *
 * @param list<mixed>  $fields_raw
 * @param list<string> $warnings
 */
function ninja_forms_create_fields(int $form_id, array $fields_raw, array &$warnings): void
{
    $used_keys = [];
    $has_submit = false;
    $order = 1;
    // @mago-expect analysis:mixed-assignment
    foreach ($fields_raw as $field) {
        if (!is_array($field)) {
            continue;
        }
        $persisted = ninja_forms_persist_field($form_id, $field, $order, $used_keys, $warnings);
        $used_keys[$persisted['key']] = true;
        if (($field['type'] ?? '') === 'submit') {
            $has_submit = true;
        }
        $order++;
    }

    if ($fields_raw !== [] && !$has_submit) {
        ninja_forms_persist_field($form_id, ['type' => 'submit', 'label' => 'Submit'], $order, $used_keys, $warnings);
        $warnings[] = 'A submit button was appended automatically (a Ninja form requires one).';
    }
}

/**
 * Creates the caller's actions.
 *
 * @param list<mixed>  $actions_raw
 * @param list<string> $warnings
 */
function ninja_forms_create_actions(int $form_id, array $actions_raw, array &$warnings): void
{
    // @mago-expect analysis:mixed-assignment
    foreach ($actions_raw as $action) {
        if (!is_array($action)) {
            $warnings[] = 'Skipped a non-object actions[] entry.';
            continue;
        }
        ninja_forms_persist_action($form_id, $action);
    }
}
