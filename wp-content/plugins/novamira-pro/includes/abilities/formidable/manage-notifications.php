<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Formidable stores form actions as posts of the `frm_action` custom
 * post type. The action's TYPE (email / wppost / webhook / mailchimp …)
 * lives in `post_excerpt`; the form id it belongs to lives in
 * `menu_order`; the type-specific settings live in `post_content` as a
 * serialized PHP array. We never touch `post_excerpt` for anything other
 * than reading (it identifies the subclass dispatcher in Formidable's
 * UI) — write paths land via wp_insert_post / wp_update_post directly.
 */
const FRM_ACTION_POST_TYPE = 'frm_action';

const FRM_NOTIFICATION_ACTION_TYPE = 'email';

/**
 * Whitelist of keys allowed in an email-action's settings blob. Anything
 * else supplied by the caller is silently dropped — mirrors the FluentForms
 * audit-round-3 #F arbitrary-key-injection fix at the same surface.
 */
const FRM_NOTIFICATION_KEYS = [
    'email_to',
    'cc',
    'bcc',
    'reply_to',
    'from',
    'email_subject',
    'email_message',
    'plain_text',
    'inc_user_info',
    'event',
    'conditions',
];

// ---------------------------------------------------------------------------
// list-notifications
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-list-notifications', [
    'label' => __('List Formidable Notifications', domain: 'novamira-pro'),
    'description' => __(
        "Lists email notifications attached to a Formidable form (every form action whose type slug is `email`). Compact per-row payload: id, name, enabled (true when post_status='publish'), email_to (recipient string), email_subject, modified_at. Use this BEFORE get-notification to pick an action id. Non-email actions (wppost, webhook, mailchimp, …) ship from list-form-actions in S5 — this surface intentionally narrows to the email type so the agent never gets a mixed list with type-specific schemas it can't introspect.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
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
            'notifications' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'enabled' => ['type' => 'boolean'],
                        'email_to' => ['type' => 'string'],
                        'email_subject' => ['type' => 'string'],
                        'modified_at' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'name', 'enabled', 'email_to', 'email_subject', 'modified_at'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['notifications'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_list_notifications',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists email notifications on `form_id`. Returns a compact row per action so the agent can pick the id before calling get-notification for the full settings blob. `enabled` mirrors post_status (publish=true). To enumerate ALL action types (wppost / webhook / mailchimp / payments), use list-form-actions (S5).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string} $input
 * @return array{notifications: list<array{id: int, name: string, enabled: bool, email_to: string, email_subject: string, modified_at: string}>}|WP_Error
 */
function formidable_list_notifications(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_view_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    /** @var list<WP_Post> $posts */
    $posts = get_posts([
        'post_type' => FRM_ACTION_POST_TYPE,
        'post_status' => ['publish', 'draft'],
        'numberposts' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'meta_query' => [],
        'suppress_filters' => false,
    ]);

    $form_id = (int) $form->id;
    $rows = [];
    foreach ($posts as $post) {
        if ($post->post_excerpt !== FRM_NOTIFICATION_ACTION_TYPE) {
            continue;
        }
        if ((int) $post->menu_order !== $form_id) {
            continue;
        }
        $rows[] = frm_notification_to_compact_row($post);
    }

    return ['notifications' => $rows];
}

// ---------------------------------------------------------------------------
// get-notification
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-get-notification', [
    'label' => __('Get Formidable Notification', domain: 'novamira-pro'),
    'description' => __(
        'Reads the full settings blob for one email notification on a Formidable form. Pass `form_id` + `action_id`. Returns: id, name, enabled, plus the email settings (email_to, cc, bcc, reply_to, from, email_subject, email_message, plain_text, inc_user_info, event, conditions). Non-email actions are rejected — use list-form-actions for those.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'action_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id', 'action_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'notification' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'enabled' => ['type' => 'boolean'],
                    'modified_at' => ['type' => 'string'],
                    'settings' => ['type' => 'object'],
                ],
                'required' => ['id', 'name', 'enabled', 'modified_at', 'settings'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['notification'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the full email-notification payload by `action_id` (from list-notifications). The action MUST belong to `form_id`. Non-email actions are rejected with formidable_notification_type_mismatch — use list-form-actions for those.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, action_id?: int|string} $input
 * @return array{notification: array{id: int, name: string, enabled: bool, modified_at: string, settings: array<string, mixed>}}|WP_Error
 */
function formidable_get_notification(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_view_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $action = frm_notification_or_error((int) $form->id, $input['action_id'] ?? 0);
    if ($action instanceof WP_Error) {
        return $action;
    }

    $settings = frm_notification_decode_settings($action);

    return [
        'notification' => [
            'id' => (int) $action->ID,
            'name' => $action->post_title,
            'enabled' => $action->post_status === 'publish',
            'modified_at' => $action->post_modified_gmt,
            'settings' => $settings,
        ],
    ];
}

// ---------------------------------------------------------------------------
// create-notification
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-create-notification', [
    'label' => __('Create Formidable Notification', domain: 'novamira-pro'),
    'description' => __(
        "Creates an email notification (form action of type `email`) on a Formidable form. `name` becomes the action's admin label. `email_to` / `cc` / `bcc` / `reply_to` / `from` accept Formidable shortcodes (e.g. `[admin_email]`, `[1234]` for a field value). Every header-bound value (subject + addresses) is RFC 5322 validated — CR / LF / NULL are rejected with formidable_invalid_email_header. `enabled` defaults to true (post_status='publish'). Pass `event=['create']` (default) for new entries, `['update']` for entry updates, or both. `plain_text=true` sends as text/plain instead of HTML.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string', 'description' => 'Admin-visible action label.'],
            'email_to' => ['type' => 'string', 'description' => 'Recipient address or Formidable shortcode.'],
            'cc' => ['type' => 'string'],
            'bcc' => ['type' => 'string'],
            'reply_to' => ['type' => 'string'],
            'from' => ['type' => 'string'],
            'email_subject' => ['type' => 'string'],
            'email_message' => ['type' => 'string'],
            'plain_text' => ['type' => 'boolean', 'default' => false],
            'inc_user_info' => ['type' => 'boolean', 'default' => false],
            'event' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['create', 'update']],
                'description' => 'Entry lifecycle events that trigger this notification.',
                'default' => ['create'],
            ],
            'enabled' => ['type' => 'boolean', 'default' => true],
        ],
        'required' => ['form_id', 'name', 'email_to'],
        'additionalProperties' => false,
    ],
    'output_schema' => frm_notification_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_create_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an email notification on `form_id`. `name` and `email_to` are required. All header values (subject + addresses) are CRLF-stripped — embedded line separators return formidable_invalid_email_header. `enabled` defaults to true; pass false to register a draft notification.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int|string,
 *     name?: string,
 *     email_to?: string,
 *     cc?: string,
 *     bcc?: string,
 *     reply_to?: string,
 *     from?: string,
 *     email_subject?: string,
 *     email_message?: string,
 *     plain_text?: bool,
 *     inc_user_info?: bool,
 *     event?: list<string>,
 *     enabled?: bool,
 * } $input
 * @return array{notification: array{id: int, name: string, enabled: bool, modified_at: string, settings: array<string, mixed>}}|WP_Error
 */
function formidable_create_notification(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $name = isset($input['name']) ? trim($input['name']) : '';
    if ($name === '') {
        return frm_invalid_input('`name` is required and must be a non-empty string.');
    }
    $email_to = isset($input['email_to']) ? trim($input['email_to']) : '';
    if ($email_to === '') {
        return frm_invalid_input('`email_to` is required and must be a non-empty string.');
    }

    $settings = frm_build_notification_settings($input);
    if ($settings instanceof WP_Error) {
        return $settings;
    }

    $enabled = !isset($input['enabled']) || (bool) (int) $input['enabled'];

    $action_id = wp_insert_post([
        'post_type' => FRM_ACTION_POST_TYPE,
        'post_status' => $enabled ? 'publish' : 'draft',
        'post_title' => frm_strip_crlf(sanitize_text_field($name)),
        'post_excerpt' => FRM_NOTIFICATION_ACTION_TYPE,
        'menu_order' => (int) $form->id,
        'post_content' => maybe_serialize($settings),
    ], wp_error: true);

    if ($action_id instanceof WP_Error) {
        return $action_id;
    }
    if ($action_id <= 0) {
        return frm_error(
            code: 'formidable_create_notification_failed',
            message: 'wp_insert_post returned a non-positive id.',
        );
    }

    // @mago-expect analyzer:mixed-assignment
    $post = get_post($action_id);
    if (!$post instanceof WP_Post) {
        return frm_error(
            code: 'formidable_create_notification_failed',
            message: "Could not re-fetch newly created notification {$action_id}.",
            status: 500,
        );
    }
    return ['notification' => frm_notification_to_full_row($post)];
}

// ---------------------------------------------------------------------------
// edit-notification
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-edit-notification', [
    'label' => __('Edit Formidable Notification', domain: 'novamira-pro'),
    'description' => __(
        'Partial-merge edit of one email notification. Identify by `form_id` + `action_id`. Only the keys you pass are written; `email_to` / `cc` / `bcc` / `reply_to` / `from` / `email_subject` are CRLF-validated on every write. `enabled=false` flips the underlying post to `draft` (Formidable skips draft actions on entry events); `enabled=true` restores `publish`. The settings blob is partial-merged on the whitelisted keys ONLY — unknown keys are silently dropped to prevent arbitrary-key injection (FluentForms audit round 3 #F lesson).',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'action_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string'],
            'email_to' => ['type' => 'string'],
            'cc' => ['type' => 'string'],
            'bcc' => ['type' => 'string'],
            'reply_to' => ['type' => 'string'],
            'from' => ['type' => 'string'],
            'email_subject' => ['type' => 'string'],
            'email_message' => ['type' => 'string'],
            'plain_text' => ['type' => 'boolean'],
            'inc_user_info' => ['type' => 'boolean'],
            'event' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['create', 'update']],
            ],
            'enabled' => ['type' => 'boolean'],
        ],
        'required' => ['form_id', 'action_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => frm_notification_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_edit_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial-merge edit. Pass only the keys you want to change. All header values are CRLF-validated. `enabled=true|false` flips publish vs draft on the underlying post (Formidable skips drafts on submit events). The whitelist drops any key not in the documented settings shape — there is no opaque-overwrite path.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{notification: array{id: int, name: string, enabled: bool, modified_at: string, settings: array<string, mixed>}}|WP_Error
 */
function formidable_edit_notification(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $action = frm_notification_or_error((int) $form->id, $input['action_id'] ?? 0);
    if ($action instanceof WP_Error) {
        return $action;
    }

    $overrides = frm_build_notification_settings_partial($input);
    if ($overrides instanceof WP_Error) {
        return $overrides;
    }

    $post_updates = frm_build_notification_post_updates($input, $action, $overrides);
    if ($post_updates instanceof WP_Error) {
        return $post_updates;
    }

    if (count($post_updates) === 1) {
        // Only `ID` — caller sent nothing mutating. Return current row unchanged.
        return ['notification' => frm_notification_to_full_row($action)];
    }

    return frm_persist_notification_edit((int) $action->ID, $post_updates);
}

/**
 * Build the wp_update_post update payload for the notification edit path.
 * Centralised so the main edit callback stays under the cyclomatic-complexity
 * threshold.
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>|WP_Error
 */
function frm_build_notification_post_updates(array $input, WP_Post $action, array $overrides): array|WP_Error
{
    /** @var array<string, mixed> $post_updates */
    $post_updates = ['ID' => (int) $action->ID];

    if (isset($input['name'])) {
        $name = trim((string) $input['name']);
        if ($name === '') {
            return frm_invalid_input('`name` must be a non-empty string when supplied.');
        }
        $post_updates['post_title'] = frm_strip_crlf(sanitize_text_field($name));
    }
    if (isset($input['enabled'])) {
        $post_updates['post_status'] = (bool) (int) $input['enabled'] ? 'publish' : 'draft';
    }
    if ($overrides !== [] || array_key_exists('event', $input)) {
        $existing = frm_notification_decode_settings($action);
        $post_updates['post_content'] = maybe_serialize(array_merge($existing, $overrides));
    }

    return $post_updates;
}

/**
 * Apply wp_update_post + re-fetch for the notification edit path.
 *
 * @param array<string, mixed> $post_updates
 * @return array{notification: array{id: int, name: string, enabled: bool, modified_at: string, settings: array<string, mixed>}}|WP_Error
 */
function frm_persist_notification_edit(int $action_id, array $post_updates): array|WP_Error
{
    // @mago-expect analyzer:possibly-invalid-argument
    // wp_update_post's stubbed shape lists every accepted post column; our
    // map is a strict subset (ID + post_title + post_status + post_content).
    // The analyzer cannot prove that without a structural check on every key,
    // so we acknowledge the wider-than-needed type and proceed.
    $updated = wp_update_post($post_updates, wp_error: true);
    if ($updated instanceof WP_Error) {
        return $updated;
    }

    // @mago-expect analyzer:mixed-assignment
    $fresh = get_post($action_id);
    if (!$fresh instanceof WP_Post) {
        return frm_error(
            code: 'formidable_edit_notification_failed',
            message: 'Could not re-fetch notification after update.',
            status: 500,
        );
    }
    return ['notification' => frm_notification_to_full_row($fresh)];
}

// ---------------------------------------------------------------------------
// delete-notification
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-delete-notification', [
    'label' => __('Delete Formidable Notification', domain: 'novamira-pro'),
    'description' => __(
        'Hard-deletes one email notification by `form_id` + `action_id`. No soft-delete — the row is removed from `wp_posts` via wp_delete_post(force=true). Idempotent: deleting an already-missing action returns deleted=false (no-op) rather than 404.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'action_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id', 'action_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'action_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
        'required' => ['form_id', 'action_id', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_delete_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Hard-deletes an email notification. Idempotent: re-call on an already-deleted action returns deleted=false. No undo.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, action_id?: int|string} $input
 * @return array{form_id: int, action_id: int, deleted: bool}|WP_Error
 */
function formidable_delete_notification(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $form_id = (int) $form->id;
    $action_id = frm_to_int($input['action_id'] ?? 0);
    if ($action_id <= 0) {
        return frm_invalid_input('`action_id` must be a positive integer.');
    }

    $action = frm_notification_or_error($form_id, $action_id);
    if ($action instanceof WP_Error) {
        if ($action->get_error_code() === 'formidable_notification_not_found') {
            return ['form_id' => $form_id, 'action_id' => $action_id, 'deleted' => false];
        }
        return $action;
    }

    $result = wp_delete_post($action_id, force_delete: true);
    if ($result === false || $result === null) {
        return frm_error(
            code: 'formidable_delete_notification_failed',
            message: "wp_delete_post returned a falsy value for action {$action_id}.",
        );
    }

    return ['form_id' => $form_id, 'action_id' => $action_id, 'deleted' => true];
}

// ===========================================================================
// Internal helpers (file-local — kept private to manage-notifications.php).
// ===========================================================================

/**
 * Output schema for the create / edit notification responses. Factored out
 * because both abilities return the identical envelope.
 *
 * @return array<string, mixed>
 */
function frm_notification_output_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'notification' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'enabled' => ['type' => 'boolean'],
                    'modified_at' => ['type' => 'string'],
                    'settings' => ['type' => 'object'],
                ],
                'required' => ['id', 'name', 'enabled', 'modified_at', 'settings'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['notification'],
        'additionalProperties' => false,
    ];
}

/**
 * Resolve an action_id to a `frm_action` post AND verify it's an email
 * notification belonging to the supplied form. Returns a typed WP_Error
 * on every failure mode so the calling ability has clear branching.
 */
function frm_notification_or_error(int $form_id, mixed $action_id): WP_Post|WP_Error
{
    $id = frm_to_int($action_id);
    if ($id <= 0) {
        return frm_invalid_input('`action_id` must be a positive integer.');
    }
    // @mago-expect analyzer:mixed-assignment
    $post = get_post($id);
    if (!$post instanceof WP_Post || $post->post_type !== FRM_ACTION_POST_TYPE) {
        return new WP_Error('formidable_notification_not_found', "Formidable notification not found: {$id}", [
            'status' => 404,
        ]);
    }
    if ($post->post_excerpt !== FRM_NOTIFICATION_ACTION_TYPE) {
        return new WP_Error(
            'formidable_notification_type_mismatch',
            "Action {$id} is of type '{$post->post_excerpt}', not 'email'. Use list-form-actions for non-email actions.",
            ['status' => 400],
        );
    }
    if ((int) $post->menu_order !== $form_id) {
        return new WP_Error(
            'formidable_notification_form_mismatch',
            "Notification {$id} belongs to form {$post->menu_order}, not the requested form {$form_id}.",
            ['status' => 400],
        );
    }
    return $post;
}

/**
 * Decode the settings blob from a notification post. Always returns an
 * array (empty when undecodable) so callers don't need to nullable-branch.
 *
 * @return array<string, mixed>
 */
function frm_notification_decode_settings(WP_Post $post): array
{
    /** @var mixed $raw */
    $raw = $post->post_content;
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = maybe_unserialize($raw);
    return is_array($decoded) ? frm_notification_whitelist_settings($decoded) : [];
}

/**
 * Drop every key not in FRM_NOTIFICATION_KEYS. Mirrors the FluentForms
 * audit-round-3 #F arbitrary-key-injection guard at the same surface —
 * keeps the response shape stable AND prevents an attacker (or a
 * misbehaving third-party filter on the action) from leaking arbitrary
 * keys to the response.
 *
 * @param array<int|string, mixed> $settings
 * @return array<string, mixed>
 */
function frm_notification_whitelist_settings(array $settings): array
{
    /** @var array<string, mixed> $kept */
    $kept = [];
    foreach (FRM_NOTIFICATION_KEYS as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        $kept[$key] = $settings[$key];
    }
    return $kept;
}

/**
 * Reduce a notification post to the compact list row shape.
 *
 * @return array{id: int, name: string, enabled: bool, email_to: string, email_subject: string, modified_at: string}
 */
function frm_notification_to_compact_row(WP_Post $post): array
{
    $settings = frm_notification_decode_settings($post);
    return [
        'id' => (int) $post->ID,
        'name' => $post->post_title,
        'enabled' => $post->post_status === 'publish',
        'email_to' => isset($settings['email_to']) && is_string($settings['email_to']) ? $settings['email_to'] : '',
        'email_subject' => isset($settings['email_subject']) && is_string($settings['email_subject'])
            ? $settings['email_subject']
            : '',
        'modified_at' => $post->post_modified_gmt,
    ];
}

/**
 * Reduce a notification post to the full row shape (create / edit response).
 *
 * @return array{id: int, name: string, enabled: bool, modified_at: string, settings: array<string, mixed>}
 */
function frm_notification_to_full_row(WP_Post $post): array
{
    return [
        'id' => (int) $post->ID,
        'name' => $post->post_title,
        'enabled' => $post->post_status === 'publish',
        'modified_at' => $post->post_modified_gmt,
        'settings' => frm_notification_decode_settings($post),
    ];
}

/**
 * Build the full settings array from caller input for the create path —
 * applies every required default for keys the caller didn't pass. Returns
 * a WP_Error if any header-bound value contains CR / LF / NULL.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function frm_build_notification_settings(array $input): array|WP_Error
{
    $partial = frm_build_notification_settings_partial($input);
    if ($partial instanceof WP_Error) {
        return $partial;
    }
    $defaults = [
        'email_to' => '',
        'cc' => '',
        'bcc' => '',
        'reply_to' => '',
        'from' => '',
        'email_subject' => '',
        'email_message' => '',
        'plain_text' => 0,
        'inc_user_info' => 0,
        'event' => ['create'],
        'conditions' => [],
    ];
    return array_merge($defaults, $partial);
}

/**
 * Build the settings array for the edit path — only the keys the caller
 * supplied are present, so the merger downstream can partial-merge
 * cleanly. Returns a WP_Error on invalid header values.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function frm_build_notification_settings_partial(array $input): array|WP_Error
{
    /** @var array<string, mixed> $out */
    $out = [];
    foreach (['email_to', 'cc', 'bcc', 'reply_to', 'from', 'email_subject'] as $header_key) {
        if (!array_key_exists($header_key, $input)) {
            continue;
        }
        $value = (string) $input[$header_key];
        $validated = frm_validate_email_header($value);
        if ($validated instanceof WP_Error) {
            return $validated;
        }
        $out[$header_key] = $validated;
    }
    if (isset($input['email_message'])) {
        $out['email_message'] = (string) $input['email_message'];
    }
    if (isset($input['plain_text'])) {
        $out['plain_text'] = (bool) (int) $input['plain_text'] ? 1 : 0;
    }
    if (isset($input['inc_user_info'])) {
        $out['inc_user_info'] = (bool) (int) $input['inc_user_info'] ? 1 : 0;
    }
    if (isset($input['event']) && is_array($input['event'])) {
        $out['event'] = array_values(array_filter(
            $input['event'],
            static fn(mixed $v): bool => is_string($v) && in_array($v, ['create', 'update'], strict: true),
        ));
        if ($out['event'] === []) {
            $out['event'] = ['create'];
        }
    }
    return $out;
}
