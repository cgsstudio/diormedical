<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ====================================================================
// list-notifications
// ====================================================================

wp_register_ability('novamira/fluentforms-list-notifications', [
    'label' => __('List Fluent Forms notifications', domain: 'novamira-pro'),
    'description' => __(
        'List the email notifications configured on a Fluent Forms form. Progressive disclosure: each entry is a COMPACT row {id (the storage row id), name, subject, enabled, recipient (a flat summary of sendTo — the address, or "field:<name>" / the routing type), has_conditionals}. The heavy fields are omitted by default and opted into via `include`: pass any of ["message","sendTo","conditionals"] to materialise them inline; or fetch one notification in full with novamira/fluentforms-get-notification. Required: form_id. Returns {form_id, notifications: list}. Capability: fluentform_settings_manager (falls back to forms_manager).',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'include' => [
                'type' => 'array',
                'description' => 'Heavy fields to materialise inline. Default [] returns only the compact rows.',
                'items' => ['type' => 'string', 'enum' => ['message', 'sendTo', 'conditionals']],
                'default' => [],
            ],
        ],
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
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_list_notifications',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns every notification — both enabled and disabled. Use `enabled` on each entry to branch. Compact by default; pass include:["message","sendTo","conditionals"] for the heavy fields, or get-notification for one in full.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, notifications:list<array<string, mixed>>}|WP_Error
 */
function fluentforms_list_notifications(array $input): array|WP_Error
{
    $form = ff_settings_load_form_for_read($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $include = ff_notification_include($input['include'] ?? null);
    $rows = [];
    foreach (ff_form_notifications((int) $form->id) as $notification) {
        $rows[] = ff_notification_compact_row($notification, $include);
    }
    return [
        'form_id' => (int) $form->id,
        'notifications' => $rows,
    ];
}

/**
 * Validate the caller `include` list down to the recognised heavy-field keys.
 *
 * @param mixed $raw
 *
 * @return list<string>
 */
function ff_notification_include(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $allowed = ['message', 'sendTo', 'conditionals'];
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $value) {
        if (!is_string($value) || !in_array($value, $allowed, strict: true)) {
            continue;
        }
        $out[] = $value;
    }
    return $out;
}

/**
 * True for the loose "truthy" forms FF stores flags in (bool true, 1, "1",
 * "true", "yes").
 *
 * @param mixed $value
 */
function ff_flag_truthy(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 'true', 'yes'], strict: true);
}

/**
 * Flatten a notification `sendTo` object to a one-line recipient summary: the
 * address for a static email, "field:<name>" for a field-driven recipient, or
 * the routing type otherwise.
 *
 * @param array<array-key, mixed> $send_to
 */
function ff_notification_recipient_summary(array $send_to): string
{
    $type = ff_str($send_to['type'] ?? 'email');
    return match ($type) {
        'email' => ff_str($send_to['email'] ?? ''),
        'field' => 'field:' . ff_str($send_to['field'] ?? ''),
        default => $type,
    };
}

/**
 * Project a stored notification to its compact list row, materialising the
 * heavy fields (message / sendTo / conditionals) only when opted into via
 * `include` — progressive disclosure so a form with big message bodies does
 * not return them all on a plain list.
 *
 * @param array<array-key, mixed> $notification
 * @param list<string>            $include
 *
 * @return array<string, mixed>
 */
function ff_notification_compact_row(array $notification, array $include): array
{
    $send_to = is_array($notification['sendTo'] ?? null) ? $notification['sendTo'] : [];
    $conditionals = is_array($notification['conditionals'] ?? null) ? $notification['conditionals'] : [];

    $row = [
        'id' => (int) ($notification['id'] ?? 0),
        'name' => ff_str($notification['name'] ?? ''),
        'subject' => ff_str($notification['subject'] ?? ''),
        'enabled' => ff_flag_truthy($notification['enabled'] ?? false),
        'recipient' => ff_notification_recipient_summary($send_to),
        'has_conditionals' => ff_flag_truthy($conditionals['status'] ?? false),
    ];

    $heavy = [
        'message' => ff_str($notification['message'] ?? ''),
        'sendTo' => $send_to,
        'conditionals' => $conditionals,
    ];
    /** @var mixed $value */
    foreach ($heavy as $key => $value) {
        if (!in_array($key, $include, strict: true)) {
            continue;
        }
        $row[$key] = $value;
    }
    return $row;
}

// ====================================================================
// get-notification
// ====================================================================

wp_register_ability('novamira/fluentforms-get-notification', [
    'label' => __('Get a Fluent Forms notification', domain: 'novamira-pro'),
    'description' => __(
        'Fetch one notification by id. Required: form_id, notification_id. Returns {form_id, notification}. Returns fluentforms_notification_not_found (404) when no matching id exists on the form. Capability: fluentform_settings_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
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
            'notification' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'notification'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Look up the id via list-notifications first — FF assigns integer ids starting at 1.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, notification:array<array-key, mixed>}|WP_Error
 */
function fluentforms_get_notification(array $input): array|WP_Error
{
    $form = ff_settings_load_form_for_read($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $id = isset($input['notification_id']) && is_int($input['notification_id']) ? $input['notification_id'] : 0;
    if ($id <= 0) {
        return ff_invalid('notification_id must be a positive integer.');
    }
    [$idx, $entry] = ff_find_notification_index(ff_form_notifications((int) $form->id), $id);
    if ($idx < 0 || $entry === null) {
        return new WP_Error(
            'fluentforms_notification_not_found',
            sprintf('No notification with id %d on form %d.', $id, (int) $form->id),
            ['status' => 404],
        );
    }
    return ['form_id' => (int) $form->id, 'notification' => $entry];
}

// ====================================================================
// create-notification
// ====================================================================

wp_register_ability('novamira/fluentforms-create-notification', [
    'label' => __('Create a Fluent Forms notification', domain: 'novamira-pro'),
    'description' => __(
        "Append a new email notification to a form. Required: form_id, notification. The notification object accepts: name (admin label, default 'Notification'), sendTo (recipient string with merge tags, REQUIRED — fluentforms_invalid_input otherwise), subject, message, fromName, fromEmail, replyTo, bcc, enabled (default true), conditionals (per-notification routing). sendTo may be a plain email/merge-tag string (stored as {type:email,email:...}) or the native object. The new entry's `id` is the storage row id Fluent Forms assigns, echoed in the response. Capability: fluentform_settings_manager.",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification' => [
                'type' => 'object',
                'description' => 'Notification body. sendTo is required.',
            ],
        ],
        'required' => ['form_id', 'notification'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification' => ['type' => 'object'],
            'notification_id' => ['type' => 'integer'],
            'warnings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'notification', 'notification_id'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_create_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'sendTo is required — pass an email or a {inputs.field_name} merge tag. The id is auto-assigned. Capability: fluentform_settings_manager.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, notification:array<array-key, mixed>, notification_id:int, warnings?:array{dropped_keys:list<string>}}|WP_Error
 */
function fluentforms_create_notification(array $input): array|WP_Error
{
    $form = ff_settings_load_form_for_write($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $body = isset($input['notification']) && is_array($input['notification']) ? $input['notification'] : null;
    if ($body === null) {
        return ff_invalid('notification must be an object.');
    }
    // Round-3 audit CRITICAL #F: top-level whitelist drops arbitrary
    // caller-supplied fields. Round-3 audit CRITICAL #D: sendTo/fromEmail/
    // replyTo/bcc/cc are rejected outright when they contain CRLF / NULL.
    [$body, $dropped, $header_error] = ff_filter_notification_body($body);
    if ($header_error !== null) {
        return ff_invalid($header_error);
    }
    $send_to_ok =
        isset($body['sendTo']) && is_string($body['sendTo']) && trim($body['sendTo']) !== ''
        || isset($body['sendTo']) && is_array($body['sendTo']);
    if (!$send_to_ok) {
        return ff_invalid('notification.sendTo is required (an email address or a {inputs.field_name} merge tag).');
    }
    $entry = ff_normalize_notification($body);
    $new_id = ff_insert_notification_row((int) $form->id, $entry);
    if ($new_id <= 0) {
        return ff_operation_failed('Failed to save notification.');
    }
    ff_after_form_save((int) $form->id, event: 'created');
    ff_clear_form_cache((int) $form->id);
    $entry['id'] = $new_id;
    $payload = ['form_id' => (int) $form->id, 'notification' => $entry, 'notification_id' => $new_id];
    if ($dropped !== []) {
        $payload['warnings'] = ['dropped_keys' => $dropped];
    }
    return $payload;
}

// ====================================================================
// edit-notification
// ====================================================================

wp_register_ability('novamira/fluentforms-edit-notification', [
    'label' => __('Edit a Fluent Forms notification', domain: 'novamira-pro'),
    'description' => __(
        'Patch one notification by id. Shallow merge — only the keys you pass in `notification` are touched; pass null on a key to delete it. The id is immutable. Required: form_id, notification_id, notification. Returns fluentforms_notification_not_found (404) on missing id. Capability: fluentform_settings_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'notification_id' => ['type' => 'integer', 'minimum' => 1],
            'notification' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'notification_id', 'notification'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'notification' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
            'warnings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'notification', 'changed'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_edit_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Shallow merge — top-level keys replace. `id` is immutable. Pass null on a key to delete it. Capability: fluentform_settings_manager.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, notification:array<array-key, mixed>, changed:list<string>, warnings?:array{dropped_keys:list<string>}}|WP_Error
 */
function fluentforms_edit_notification(array $input): array|WP_Error
{
    $form = ff_settings_load_form_for_write($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $id = isset($input['notification_id']) && is_int($input['notification_id']) ? $input['notification_id'] : 0;
    if ($id <= 0) {
        return ff_invalid('notification_id must be a positive integer.');
    }
    $patch = isset($input['notification']) && is_array($input['notification']) ? $input['notification'] : null;
    if ($patch === null) {
        return ff_invalid('notification must be an object.');
    }
    // Round-3 audit CRITICAL #F + #D: top-level whitelist + CRLF guard on
    // sendTo/fromEmail/replyTo/bcc/cc. Same filter as create-notification.
    [$patch, $dropped, $header_error] = ff_filter_notification_body($patch);
    if ($header_error !== null) {
        return ff_invalid($header_error);
    }
    $current = ff_form_notifications((int) $form->id);
    [, $entry] = ff_find_notification_index($current, $id);
    if ($entry === null) {
        return new WP_Error(
            'fluentforms_notification_not_found',
            sprintf('No notification with id %d on form %d.', $id, (int) $form->id),
            ['status' => 404],
        );
    }
    [$merged, $changed] = ff_merge_notification_patch($entry, $patch);
    // Re-normalise so a patched sendTo/enabled keeps the native shape, and
    // write ONLY this notification's row (id scoped) — never the sibling rows.
    $merged = ff_normalize_notification($merged);
    if (!ff_update_notification_row((int) $form->id, $id, $merged)) {
        return ff_operation_failed('Failed to save notification.');
    }
    ff_after_form_save((int) $form->id, event: 'updated');
    ff_clear_form_cache((int) $form->id);
    $merged['id'] = $id;
    $payload = ['form_id' => (int) $form->id, 'notification' => $merged, 'changed' => $changed];
    if ($dropped !== []) {
        $payload['warnings'] = ['dropped_keys' => $dropped];
    }
    return $payload;
}

// ====================================================================
// delete-notification
// ====================================================================

wp_register_ability('novamira/fluentforms-delete-notification', [
    'label' => __('Delete a Fluent Forms notification', domain: 'novamira-pro'),
    'description' => __(
        'Remove one notification by id. Required: form_id, notification_id. Returns fluentforms_notification_not_found (404) when the id is absent. Other notifications keep their ids — there is no reindexing. Capability: fluentform_settings_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
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
            'removed_id' => ['type' => 'integer'],
            'remaining' => ['type' => 'integer'],
        ],
        'required' => ['form_id', 'removed_id', 'remaining'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_delete_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Other notifications keep their ids (no reindexing). To temporarily silence a notification without losing it, edit-notification with enabled=false instead.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, removed_id:int, remaining:int}|WP_Error
 */
function fluentforms_delete_notification(array $input): array|WP_Error
{
    $form = ff_settings_load_form_for_write($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $id = isset($input['notification_id']) && is_int($input['notification_id']) ? $input['notification_id'] : 0;
    if ($id <= 0) {
        return ff_invalid('notification_id must be a positive integer.');
    }
    $removed = ff_delete_notification_row((int) $form->id, $id);
    if ($removed === 0) {
        return new WP_Error(
            'fluentforms_notification_not_found',
            sprintf('No notification with id %d on form %d.', $id, (int) $form->id),
            ['status' => 404],
        );
    }
    ff_after_form_save((int) $form->id, event: 'updated');
    ff_clear_form_cache((int) $form->id);
    return [
        'form_id' => (int) $form->id,
        'removed_id' => $id,
        'remaining' => count(ff_form_notifications((int) $form->id)),
    ];
}

// ====================================================================
// shared helpers
// ====================================================================

/**
 * Apply the partial notification patch. Returns the merged entry + the
 * list of top-level keys actually touched (changed[] is empty on a
 * caller-supplied empty patch).
 *
 * @param array<array-key, mixed> $entry
 * @param array<array-key, mixed> $patch
 *
 * @return array{0: array<array-key, mixed>, 1: list<string>}
 */
function ff_merge_notification_patch(array $entry, array $patch): array
{
    $changed = [];
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        $key_str = (string) $key;
        if ($key_str === 'id') {
            // id is immutable — silently ignored, not surfaced as changed[].
            continue;
        }
        if ($value === null) {
            if (array_key_exists($key_str, $entry)) {
                unset($entry[$key_str]);
                $changed[] = $key_str;
            }
            continue;
        }
        $entry[$key_str] = $value;
        $changed[] = $key_str;
    }
    sort($changed);
    return [$entry, $changed];
}
