<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ─── list-notifications ───────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-list-notifications', [
    'label' => __('List WPForms Notifications', domain: 'novamira-pro'),
    'description' => __(
        'List every notification configured on a form, keyed by string-int id (the same id you pass to edit-notification / delete-notification). Use this before crafting a notification mutation so you know what already exists and what id to target.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Target form id.'],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'count' => ['type' => 'integer'],
            'notifications' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'count', 'notifications'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_list_notifications',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the notifications map verbatim — keys are string-int ids, values are notification configs (notification_name, email, subject, message, sender_*, replyto, …). A brand-new form has one default notification at id "1".',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int} $input
 * @return array{form_id: int, count: int, notifications: array<array-key, mixed>}|WP_Error
 */
function wpforms_list_notifications(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    // Round-1 audit HIGH: list-notifications read path was missing the
    // per-form capability gate (mutation paths already had it). Same fix
    // as list-confirmations + get-form.
    $cap_error = wpforms_capability_or_error('wpforms_view_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }
    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $notifications = wpforms_section($settings, key: 'notifications');
    return [
        'form_id' => (int) $form->ID,
        'count' => count($notifications),
        'notifications' => $notifications,
    ];
}

// ─── create-notification ──────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-create-notification', [
    'label' => __('Create WPForms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Append a notification to a form. Required: form_id, notification_name, email. Optional: subject, sender_name, sender_address, replyto, message, file_upload_attachments. Smart tags ({admin_email}, {site_name}, {field_id="N"}, {all_fields}) are accepted verbatim — WPForms resolves them at submission time. The new notification gets the next sequential id.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_name' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Internal label shown in the admin UI.',
            ],
            'email' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Recipient. Smart tags allowed (e.g. {admin_email}).',
            ],
            'subject' => ['type' => 'string'],
            'sender_name' => ['type' => 'string'],
            'sender_address' => ['type' => 'string'],
            'replyto' => ['type' => 'string'],
            'message' => [
                'type' => 'string',
                'description' => 'Email body. {all_fields} expands to every field/value.',
            ],
            'file_upload_attachments' => [
                'type' => 'boolean',
                'description' => 'Attach file-upload field uploads (Pro only).',
            ],
        ],
        'required' => ['form_id', 'notification_name', 'email'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification_id' => ['type' => 'integer'],
            'notification' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'notification_id', 'notification'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_create_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Appends a notification with the next monotonic id. Message defaults to "{all_fields}" so an agent that omits it still gets a useful email. Multiple recipients: separate emails with commas.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     notification_name: string,
 *     email: string,
 *     subject?: string,
 *     sender_name?: string,
 *     sender_address?: string,
 *     replyto?: string,
 *     message?: string,
 *     file_upload_attachments?: bool,
 * } $input
 * @return array{form_id: int, notification_id: int, notification: array<string, mixed>}|WP_Error
 */
function wpforms_create_notification(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $notifications = wpforms_section($settings, key: 'notifications');
    $id = wpforms_next_collection_id($notifications);
    $notification = wpforms_build_notification($input);
    // Fail-loud when sanitization (CRLF strip + sanitize_email per address)
    // collapsed every entry in the recipient list to empty — silently
    // persisting an empty email would create a notification that never
    // delivers, with no signal to the agent (audit batch 2 medium).
    if (($notification['email'] ?? '') === '') {
        return wpforms_invalid_input(
            'email sanitised to empty — provide at least one valid address or smart tag (e.g. {admin_email}).',
        );
    }

    $notifications[(string) $id] = $notification;
    $settings['notifications'] = $notifications;
    $blob['settings'] = $settings;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'notification_id' => $id,
        'notification' => $notification,
    ];
}

/**
 * Build a normalised notification entry from agent input. Keeps the keys
 * WPForms expects (notification_name / email / subject / message / sender_* /
 * replyto / file_upload_attachments) and falls back to safe defaults so the
 * resulting notification is functional even when an agent passes a minimal
 * payload.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wpforms_build_notification(array $input): array
{
    return [
        'notification_name' => wpforms_sanitize_notification_field(
            'notification_name',
            (string) $input['notification_name'],
        ),
        'email' => wpforms_sanitize_notification_field('email', (string) $input['email']),
        'subject' => wpforms_sanitize_notification_field(
            'subject',
            isset($input['subject']) ? (string) $input['subject'] : 'New Form Entry',
        ),
        'sender_name' => wpforms_sanitize_notification_field(
            'sender_name',
            isset($input['sender_name']) ? (string) $input['sender_name'] : '{site_name}',
        ),
        'sender_address' => wpforms_sanitize_notification_field(
            'sender_address',
            isset($input['sender_address']) ? (string) $input['sender_address'] : '{admin_email}',
        ),
        'replyto' => wpforms_sanitize_notification_field(
            'replyto',
            isset($input['replyto']) ? (string) $input['replyto'] : '',
        ),
        'message' => wpforms_sanitize_notification_field(
            'message',
            isset($input['message']) ? (string) $input['message'] : '{all_fields}',
        ),
        'file_upload_attachments' => wpforms_bool_string($input['file_upload_attachments'] ?? false),
    ];
}

// ─── edit-notification ────────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-edit-notification', [
    'label' => __('Edit WPForms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Partial merge update of one notification. Required: form_id, notification_id. Optional: any of notification_name, email, subject, sender_name, sender_address, replyto, message, file_upload_attachments. Keys not in the payload preserve their current value.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
            'sender_name' => ['type' => 'string'],
            'sender_address' => ['type' => 'string'],
            'replyto' => ['type' => 'string'],
            'message' => ['type' => 'string'],
            'file_upload_attachments' => ['type' => 'boolean'],
        ],
        'required' => ['form_id', 'notification_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification_id' => ['type' => 'integer'],
            'updated' => ['type' => 'array', 'items' => ['type' => 'string']],
            'notification' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'notification_id', 'updated', 'notification'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_edit_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge update — only the keys you pass change. The updated[] response names the keys that actually changed; empty list means a no-op.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{form_id: int, notification_id: int, updated: list<string>, notification: array<string, mixed>}|WP_Error
 */
function wpforms_edit_notification(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $notification_id = (int) $input['notification_id'];
    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $notifications = wpforms_section($settings, key: 'notifications');
    $key = (string) $notification_id;

    if (!array_key_exists($key, $notifications) || !is_array($notifications[$key])) {
        return new WP_Error(
            'wpforms_notification_not_found',
            "Notification {$notification_id} does not exist on form {$form->ID}.",
            ['status' => 404],
        );
    }

    /** @var array<string, mixed> $notification */
    $notification = $notifications[$key];
    $updated = wpforms_apply_notification_update($notification, $input);

    // If the caller asked to update `email` and sanitization collapsed it
    // to empty, fail loud — same policy as create-notification, mirrored
    // here so the edit surface cannot silently clear a working recipient.
    if (in_array('email', $updated, strict: true) && ($notification['email'] ?? '') === '') {
        return wpforms_invalid_input(
            'email sanitised to empty — provide at least one valid address or smart tag (e.g. {admin_email}).',
        );
    }

    if ($updated === []) {
        return [
            'form_id' => (int) $form->ID,
            'notification_id' => $notification_id,
            'updated' => [],
            'notification' => $notification,
        ];
    }

    $notifications[$key] = $notification;
    $settings['notifications'] = $notifications;
    $blob['settings'] = $settings;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'notification_id' => $notification_id,
        'updated' => $updated,
        'notification' => $notification,
    ];
}

/**
 * Apply a partial merge to a notification entry. Returns the list of keys
 * that actually changed.
 *
 * @param array<string, mixed>    $notification
 * @param array<array-key, mixed> $input
 * @return list<string>
 */
function wpforms_apply_notification_update(array &$notification, array $input): array
{
    /** @var list<string> $touched */
    $touched = [];
    foreach (['notification_name', 'email', 'subject', 'sender_name', 'sender_address', 'replyto', 'message'] as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $next = wpforms_sanitize_notification_field($key, (string) $input[$key]);
        if (($notification[$key] ?? null) === $next) {
            continue;
        }
        $notification[$key] = $next;
        $touched[] = $key;
    }
    if (array_key_exists('file_upload_attachments', $input)) {
        $coerced = wpforms_bool_string($input['file_upload_attachments']);
        if (($notification['file_upload_attachments'] ?? null) !== $coerced) {
            $notification['file_upload_attachments'] = $coerced;
            $touched[] = 'file_upload_attachments';
        }
    }
    return $touched;
}

// ─── delete-notification ──────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-delete-notification', [
    'label' => __('Delete WPForms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Remove a notification from a form. Required: form_id, notification_id. Refuses to delete the last surviving notification — a form must keep at least one notification so submissions still trigger an email. Use edit-notification to repurpose the last one instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
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
            'remaining' => ['type' => 'integer'],
        ],
        'required' => ['form_id', 'notification_id', 'deleted', 'remaining'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_delete_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Refuses to delete the last notification (wpforms_last_notification). Edit-notification it instead. Submissions stored before the delete are not modified.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, notification_id: int} $input
 * @return array{form_id: int, notification_id: int, deleted: bool, remaining: int}|WP_Error
 */
function wpforms_delete_notification(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $notification_id = (int) $input['notification_id'];
    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $notifications = wpforms_section($settings, key: 'notifications');
    $key = (string) $notification_id;

    if (!array_key_exists($key, $notifications)) {
        return new WP_Error(
            'wpforms_notification_not_found',
            "Notification {$notification_id} does not exist on form {$form->ID}.",
            ['status' => 404],
        );
    }
    if (count($notifications) <= 1) {
        return new WP_Error(
            'wpforms_last_notification',
            "Cannot delete the last notification on form {$form->ID}; edit it instead.",
            ['status' => 400],
        );
    }

    unset($notifications[$key]);
    $settings['notifications'] = $notifications;
    $blob['settings'] = $settings;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'notification_id' => $notification_id,
        'deleted' => true,
        'remaining' => count($notifications),
    ];
}
