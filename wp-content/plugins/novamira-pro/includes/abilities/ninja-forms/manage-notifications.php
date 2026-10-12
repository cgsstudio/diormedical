<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// A Ninja Forms "notification" is an action whose type is `email`. The other
// action types (redirect / successmessage / save / webhook) are read-only via
// get-form (include=["actions"]) and written via novamira/execute-php — these
// abilities deliberately scope to email actions so the surface stays sharp.

// ---------------------------------------------------------------------------
// list-notifications
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ninja-forms-list-notifications', [
    'label' => __('List Ninja Forms Notifications', domain: 'novamira-pro'),
    'description' => __(
        'List every email notification on a Ninja Forms form. A notification is a Ninja "email" action; the other action types (redirect / success message / save / webhook) are NOT returned here — read them via ninja-forms-get-form (include=["actions"]). Required: form_id. Returns {form_id, notifications: [{id, label, active, to, subject, form_id}]} — compact rows (no message body / headers); call ninja-forms-get-notification for the full settings. Includes both active and inactive notifications (branch on `active`).',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => ['form_id' => ['type' => 'integer', 'minimum' => 1]],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notifications' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
        'required' => ['form_id', 'notifications'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_list_notifications',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns every email notification (active + inactive). For the full body/headers of one, call ninja-forms-get-notification. Non-email actions are not listed here. Capability: manage_options.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int} $input
 *
 * @return array{form_id: int, notifications: list<array<string, mixed>>}|WP_Error
 */
function ninja_forms_list_notifications(array $input): array|WP_Error
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

    $notifications = [];
    foreach (\Ninja_Forms()->form($form_id)->get_actions() as $action) {
        $settings = $action->get_settings();
        if (ninja_forms_str($settings['type'] ?? '') !== 'email') {
            continue;
        }
        $notifications[] = [
            'id' => (int) $action->get_id(),
            'label' => ninja_forms_str($settings['label'] ?? ''),
            'active' => ninja_forms_truthy($settings['active'] ?? null),
            'to' => ninja_forms_str($settings['to'] ?? ''),
            'subject' => ninja_forms_str($settings['email_subject'] ?? ''),
            'form_id' => $form_id,
        ];
    }

    return ['form_id' => $form_id, 'notifications' => $notifications];
}

// ---------------------------------------------------------------------------
// get-notification
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ninja-forms-get-notification', [
    'label' => __('Get a Ninja Forms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Fetch one email notification by id with its FULL settings (recipient, subject, message body, from/reply-to, cc/bcc, format). Required: form_id, notification_id. Secret-looking setting values are redacted and `has_secrets` flags whether any were; large string values (the message body) are byte-capped at `max_field_bytes` (default 8192, 0 disables). Returns ninja_forms_notification_not_found (404) when the id is not an email action on this form. Returns {form_id, notification: {id, type, label, active, settings, has_secrets}}.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_id' => ['type' => 'integer', 'minimum' => 1],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 8192,
                'description' => 'Per-string byte cap for settings values. Default 8192. Set 0 to disable.',
            ],
        ],
        'required' => ['form_id', 'notification_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'notification'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_get_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Find the id via ninja-forms-list-notifications first. Secret values are redacted; the message body is byte-capped (raise max_field_bytes for the full body). Capability: manage_options.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int, notification_id?: int, max_field_bytes?: int} $input
 *
 * @return array{form_id: int, notification: array<string, mixed>}|WP_Error
 */
function ninja_forms_get_notification(array $input): array|WP_Error
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

    $action = ninja_forms_notification_or_error($form_id, $input['notification_id'] ?? null);
    if ($action instanceof WP_Error) {
        return $action;
    }

    $settings = $action->get_settings();
    $max_field_bytes = ninja_forms_resolve_max_field_bytes($input);
    $redacted = ninja_forms_redact_settings($settings);
    // @mago-expect analysis:mixed-assignment
    $capped = ninja_forms_cap_settings_strings($redacted['settings'], $max_field_bytes);

    return [
        'form_id' => $form_id,
        'notification' => [
            'id' => (int) $action->get_id(),
            'type' => 'email',
            'label' => ninja_forms_str($settings['label'] ?? ''),
            'active' => ninja_forms_truthy($settings['active'] ?? null),
            'settings' => (object) (is_array($capped) ? $capped : []),
            'has_secrets' => $redacted['has_secrets'],
        ],
    ];
}

// ---------------------------------------------------------------------------
// create-notification
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ninja-forms-create-notification', [
    'label' => __('Create a Ninja Forms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Add an email notification to a form. Required: form_id, to (recipient — an email address or a Ninja merge tag like {field:email}). Optional: subject, message (the body), label (admin name), active (bool, default true), and `settings` (an object of extra native email-action keys, e.g. from_name, from_address, reply_to, cc, bcc, email_format — see ninja-forms-get-notification on an existing one for the shape). The friendly `subject`/`message` map to the native email_subject/email_message keys. Returns the new notification as a compact row {id, label, active, to, subject, form_id}.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'to' => ['type' => 'string', 'description' => 'Recipient (email or merge tag). Required.'],
            'subject' => ['type' => 'string'],
            'message' => ['type' => 'string', 'description' => 'Email body (HTML allowed).'],
            'label' => ['type' => 'string', 'description' => 'Admin label for the notification.'],
            'active' => ['type' => 'boolean', 'default' => true],
            'settings' => ['type' => 'object', 'description' => 'Extra native email-action settings.'],
        ],
        'required' => ['form_id', 'to'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['notification' => ['type' => 'object']],
        'required' => ['notification'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_create_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => '`to` is required (email or {field:key} merge tag). `subject`/`message` are the friendly aliases for email_subject/email_message. Put any other native key under `settings`. Capability: manage_options.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int,
 *     to?: string,
 *     subject?: string,
 *     message?: string,
 *     label?: string,
 *     active?: bool,
 *     settings?: array<string, mixed>,
 * } $input
 *
 * @return array{notification: array<string, mixed>}|WP_Error
 */
function ninja_forms_create_notification(array $input): array|WP_Error
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

    $to = isset($input['to']) ? trim(ninja_forms_str($input['to'])) : '';
    if ($to === '') {
        return ninja_forms_invalid('to is required (a recipient email address or a {field:key} merge tag).');
    }

    $settings = ninja_forms_notification_settings_from_input($input);
    $settings['to'] = $to;

    $action_id = ninja_forms_persist_action($form_id, [
        'type' => 'email',
        'label' => ninja_forms_str($input['label'] ?? ''),
        'active' => $input['active'] ?? true,
        'settings' => $settings,
    ]);
    ninja_forms_bust_cache($form_id);

    return ['notification' => ninja_forms_notification_compact($form_id, $action_id)];
}

// ---------------------------------------------------------------------------
// edit-notification
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ninja-forms-edit-notification', [
    'label' => __('Edit a Ninja Forms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Partial-merge edit of one email notification. Required: form_id, notification_id. Optional (only the keys you pass are touched): to, subject, message, label, active, and `settings` (merged onto the action\'s current settings). The friendly subject/message map to email_subject/email_message. The action `type` stays "email" (not mutable). Returns ninja_forms_notification_not_found (404) when the id is not an email action on the form. Returns {notification, changed} (idempotent: a no-op re-run returns an empty changed[]).',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_id' => ['type' => 'integer', 'minimum' => 1],
            'to' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
            'message' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
            'settings' => ['type' => 'object', 'description' => 'Extra native email-action settings to merge.'],
        ],
        'required' => ['form_id', 'notification_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'notification' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['notification', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_edit_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Shallow per-key merge — re-running with the same values is a no-op (idempotent). `subject`/`message` map to email_subject/email_message, and `changed[]` reports those NATIVE keys (e.g. you pass `subject`, you get back `["email_subject"]`). `type` is not mutable. Concurrency: no lock; Ninja rewrites the whole action-settings row on save, so parallel edits to the SAME notification are last-write-wins on the ENTIRE blob — serialise concurrent edits. Capability: manage_options.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int,
 *     notification_id?: int,
 *     to?: string,
 *     subject?: string,
 *     message?: string,
 *     label?: string,
 *     active?: bool,
 *     settings?: array<string, mixed>,
 * } $input
 *
 * @return array{notification: array<string, mixed>, changed: list<string>}|WP_Error
 */
function ninja_forms_edit_notification(array $input): array|WP_Error
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

    $action = ninja_forms_notification_or_error($form_id, $input['notification_id'] ?? null);
    if ($action instanceof WP_Error) {
        return $action;
    }
    $action_id = (int) $action->get_id();

    $patch = ninja_forms_notification_settings_from_input($input);
    if (isset($input['to'])) {
        $patch['to'] = trim(ninja_forms_str($input['to']));
    }
    if (isset($input['label'])) {
        $patch['label'] = ninja_forms_str($input['label']);
    }
    if (isset($input['active'])) {
        $patch['active'] = ninja_forms_truthy($input['active']) ? 1 : 0;
    }
    // `type` stays email — never let a merge change it.
    unset($patch['type']);

    $current = $action->get_settings();
    /** @var list<string> $changed */
    $changed = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($patch as $key => $value) {
        if (array_key_exists($key, $current) && ninja_forms_setting_equal($current[$key], $value)) {
            continue;
        }
        $changed[] = $key;
    }

    if ($changed !== []) {
        $action->update_settings($patch);
        $action->save();
        ninja_forms_bust_cache($form_id);
    }

    return [
        'notification' => ninja_forms_notification_compact($form_id, $action_id),
        'changed' => $changed,
    ];
}

// ---------------------------------------------------------------------------
// delete-notification
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ninja-forms-delete-notification', [
    'label' => __('Delete a Ninja Forms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Permanently delete one email notification from a form. Required: form_id, notification_id. Idempotent: deleting an already-missing notification (or an id that is not an email action) is a no-op that returns deleted=false. To temporarily silence a notification without losing it, use ninja-forms-edit-notification with active=false instead. Returns {form_id, notification_id, deleted}.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id', 'notification_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
        'required' => ['form_id', 'notification_id', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_delete_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent — re-deleting a missing notification (or a non-email action id) returns deleted=false. To silence without deleting, edit-notification active=false. No undo. Capability: manage_options.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int, notification_id?: int} $input
 *
 * @return array{form_id: int, notification_id: int, deleted: bool}|WP_Error
 */
function ninja_forms_delete_notification(array $input): array|WP_Error
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
    $notification_id = ninja_forms_pos_int($input['notification_id'] ?? null);

    $action = ninja_forms_notification_or_error($form_id, $notification_id);
    if ($action instanceof WP_Error) {
        // Idempotent re-call: a missing id OR a non-email action is a no-op,
        // not an error. Invalid input (bad id format) still propagates.
        if ($action->get_error_code() === 'ninja_forms_notification_not_found') {
            return ['form_id' => $form_id, 'notification_id' => $notification_id, 'deleted' => false];
        }
        return $action;
    }

    $action->delete();
    ninja_forms_bust_cache($form_id);

    return ['form_id' => $form_id, 'notification_id' => $notification_id, 'deleted' => true];
}

// ---------------------------------------------------------------------------
// shared
// ---------------------------------------------------------------------------

/**
 * Build the native email-action settings map from the notification ability
 * input: the caller's `settings` passthrough (string keys, managed keys
 * stripped) plus the friendly subject/message aliases mapped to the native
 * email_subject/email_message keys. `to` / label / active / type are handled
 * by the callers, not here.
 *
 * @param array<string, mixed> $input
 *
 * @return array<string, mixed>
 */
function ninja_forms_notification_settings_from_input(array $input): array
{
    $settings = ninja_forms_string_keyed($input['settings'] ?? null);
    if (isset($input['subject'])) {
        $settings['email_subject'] = ninja_forms_str($input['subject']);
    }
    if (isset($input['message'])) {
        $settings['email_message'] = ninja_forms_str($input['message']);
    }
    return $settings;
}
