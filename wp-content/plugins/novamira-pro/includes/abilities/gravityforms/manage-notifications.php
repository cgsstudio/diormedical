<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ─── list-notifications ───────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-list-notifications', [
    'label' => __('List Gravity Forms Notifications', domain: 'novamira-pro'),
    'description' => __(
        'List every notification configured on a Gravity Forms form, keyed by id. Use before crafting a notification mutation so you know what already exists and what id to target.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_list_notifications',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the notifications map verbatim — keys are GF-generated 13-char ids, values are notification configs (name, event, to, subject, message, fromName, from, replyTo, …). A brand-new form has zero notifications until create-notification is called.',
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
function gravityforms_list_notifications(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $notifications = isset($form['notifications']) && is_array($form['notifications']) ? $form['notifications'] : [];
    return [
        'form_id' => (int) $form['id'],
        'count' => count($notifications),
        'notifications' => $notifications,
    ];
}

// ─── create-notification ──────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-create-notification', [
    'label' => __('Create Gravity Forms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Append a notification to a Gravity Forms form. Required: form_id, name, to. Optional: subject, message, fromName, from, replyTo, bcc, event (form_submission | feed_processed, default form_submission), enabled (default true). Merge tags ({admin_email}, {all_fields}, {Field Name:N}) are accepted verbatim. The new notification gets a fresh 13-char id (uniqid-style).',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Internal name shown in the GF admin notifications list.',
            ],
            'to' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Recipient email(s). Merge tags allowed ({admin_email}, comma-separated for multiple).',
            ],
            'subject' => ['type' => 'string'],
            'message' => [
                'type' => 'string',
                'description' => 'Email body. {all_fields} expands to every field/value at submission time.',
            ],
            'fromName' => ['type' => 'string'],
            'from' => ['type' => 'string'],
            'replyTo' => ['type' => 'string'],
            'bcc' => ['type' => 'string'],
            'event' => [
                'type' => 'string',
                'enum' => ['form_submission', 'feed_processed'],
                'default' => 'form_submission',
            ],
            'enabled' => ['type' => 'boolean', 'default' => true],
        ],
        'required' => ['form_id', 'name', 'to'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification_id' => ['type' => 'string'],
            'notification' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'notification_id', 'notification'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_create_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Appends a notification with a fresh 13-char id. Empty/whitespace-only `to` is rejected to prevent silently broken notifications. message defaults to "{all_fields}" so an agent that omits it still gets useful email.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     name: string,
 *     to: string,
 *     subject?: string,
 *     message?: string,
 *     fromName?: string,
 *     from?: string,
 *     replyTo?: string,
 *     bcc?: string,
 *     event?: string,
 *     enabled?: bool,
 * } $input
 * @return array{form_id: int, notification_id: string, notification: array<array-key, mixed>}|WP_Error
 */
function gravityforms_create_notification(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $to = trim($input['to']);
    if ($to === '') {
        return gravityforms_invalid_input('to cannot be empty — notifications with no recipient silently break.');
    }

    $notifications = isset($form['notifications']) && is_array($form['notifications']) ? $form['notifications'] : [];
    $id = uniqid();
    $notification = gravityforms_build_notification($id, $input);
    $notifications[$id] = $notification;
    $form['notifications'] = $notifications;

    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'notification_id' => $id,
        'notification' => $notification,
    ];
}

// ─── edit-notification ────────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-edit-notification', [
    'label' => __('Edit Gravity Forms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Edit a notification on a Gravity Forms form by id. Pass any of: name, to, subject, message, fromName, from, replyTo, bcc, event, enabled. An empty `to` is rejected (would silently clear the recipient list).',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_id' => ['type' => 'string', 'minLength' => 1],
            'name' => ['type' => 'string'],
            'to' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
            'message' => ['type' => 'string'],
            'fromName' => ['type' => 'string'],
            'from' => ['type' => 'string'],
            'replyTo' => ['type' => 'string'],
            'bcc' => ['type' => 'string'],
            'event' => ['type' => 'string', 'enum' => ['form_submission', 'feed_processed']],
            'enabled' => ['type' => 'boolean'],
        ],
        'required' => ['form_id', 'notification_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification_id' => ['type' => 'string'],
            'notification' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['form_id', 'notification_id', 'notification', 'changed'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_edit_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent: a patch that produces no real change returns changed=[] without a DB write. `to` cannot be set to an empty string — that would clear the recipient list silently.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     notification_id: string,
 *     name?: string,
 *     to?: string,
 *     subject?: string,
 *     message?: string,
 *     fromName?: string,
 *     from?: string,
 *     replyTo?: string,
 *     bcc?: string,
 *     event?: string,
 *     enabled?: bool,
 * } $input
 * @return array{form_id: int, notification_id: string, notification: array<array-key, mixed>, changed: list<string>}|WP_Error
 */
function gravityforms_edit_notification(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $nid = $input['notification_id'];
    $notifications = isset($form['notifications']) && is_array($form['notifications']) ? $form['notifications'] : [];
    if (!isset($notifications[$nid]) || !is_array($notifications[$nid])) {
        return new WP_Error(
            'gravityforms_notification_not_found',
            "Notification id '{$nid}' not found on form " . (int) $form['id'],
            ['status' => 404],
        );
    }

    if (isset($input['to']) && trim($input['to']) === '') {
        return gravityforms_invalid_input('to cannot be set to an empty string.');
    }

    [$updated, $changed] = gravityforms_apply_notification_patch($notifications[$nid], $input);
    if ($changed === []) {
        return [
            'form_id' => (int) $form['id'],
            'notification_id' => $nid,
            'notification' => $updated,
            'changed' => [],
        ];
    }

    $notifications[$nid] = $updated;
    $form['notifications'] = $notifications;
    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'notification_id' => $nid,
        'notification' => $updated,
        'changed' => $changed,
    ];
}

// ─── delete-notification ──────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-delete-notification', [
    'label' => __('Delete Gravity Forms Notification', domain: 'novamira-pro'),
    'description' => __(
        'Remove a notification from a Gravity Forms form by id. Returns the removed notification for confirmation. A missing notification is treated as idempotent success.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_id' => ['type' => 'string', 'minLength' => 1],
        ],
        'required' => ['form_id', 'notification_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification_id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
            'removed_notification' => ['type' => ['object', 'null']],
            'remaining_count' => ['type' => 'integer'],
        ],
        'required' => ['form_id', 'notification_id', 'deleted', 'removed_notification', 'remaining_count'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_delete_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent: missing notification_id returns deleted=true with removed_notification=null and no DB write.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, notification_id: string} $input
 * @return array{form_id: int, notification_id: string, deleted: bool, removed_notification: array<array-key, mixed>|null, remaining_count: int}|WP_Error
 */
function gravityforms_delete_notification(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $nid = $input['notification_id'];
    $notifications = isset($form['notifications']) && is_array($form['notifications']) ? $form['notifications'] : [];
    if (!isset($notifications[$nid])) {
        return [
            'form_id' => (int) $form['id'],
            'notification_id' => $nid,
            'deleted' => true,
            'removed_notification' => null,
            'remaining_count' => count($notifications),
        ];
    }

    $removed = is_array($notifications[$nid]) ? $notifications[$nid] : [];
    unset($notifications[$nid]);
    $form['notifications'] = $notifications;
    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'notification_id' => $nid,
        'deleted' => true,
        'removed_notification' => $removed,
        'remaining_count' => count($notifications),
    ];
}

// ─── shared helpers ───────────────────────────────────────────────────────

/**
 * Build a notification entry from create-notification input.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function gravityforms_build_notification(string $id, array $input): array
{
    $notification = [
        'id' => $id,
        'name' => trim((string) ($input['name'] ?? '')),
        'event' => (string) ($input['event'] ?? 'form_submission'),
        'to' => trim((string) ($input['to'] ?? '')),
        'toType' => 'email',
        'subject' => (string) ($input['subject'] ?? ''),
        'message' => (string) ($input['message'] ?? '{all_fields}'),
        'isActive' => ($input['enabled'] ?? true) === true,
    ];
    foreach (['fromName', 'from', 'replyTo', 'bcc'] as $key) {
        if (!isset($input[$key])) {
            continue;
        }
        $notification[$key] = (string) $input[$key];
    }
    return $notification;
}

/**
 * Apply a partial notification-edit patch. Returns [updated, changed-keys].
 *
 * @param array<array-key, mixed> $notification
 * @param array<array-key, mixed> $input
 * @return array{0: array<array-key, mixed>, 1: list<string>}
 */
function gravityforms_apply_notification_patch(array $notification, array $input): array
{
    /** @var list<string> $changed */
    $changed = [];
    $string_keys = ['name', 'to', 'subject', 'message', 'fromName', 'from', 'replyTo', 'bcc', 'event'];
    foreach ($string_keys as $key) {
        if (!isset($input[$key])) {
            continue;
        }
        $val = (string) $input[$key];
        if (($notification[$key] ?? null) !== $val) {
            $notification[$key] = $val;
            $changed[] = $key;
        }
    }
    if (isset($input['enabled'])) {
        $val = $input['enabled'] === true;
        if (($notification['isActive'] ?? null) !== $val) {
            $notification['isActive'] = $val;
            $changed[] = 'enabled';
        }
    }
    return [$notification, $changed];
}
