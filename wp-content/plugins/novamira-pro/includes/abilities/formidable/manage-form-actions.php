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
 * Action types that are SAFE to delete via this generic surface.
 * `on_submit` is excluded because Formidable's UI treats the on-submit
 * action as a singleton per form per event — deleting it via this path
 * would leave the form without a confirmation flow until the agent
 * re-creates one. Use set-on-submit-action instead (which manages the
 * lifecycle properly).
 */
const FRM_PROTECTED_ACTION_TYPES = ['on_submit'];

// ---------------------------------------------------------------------------
// list-form-actions
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-list-form-actions', [
    'label' => __('List Formidable Form Actions', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates every form action on a Formidable form across ALL types (email, wppost, webhook, mailchimp, activecampaign, stripe, paypal, on_submit, …). Use the `type` filter to scope to one (e.g. `wppost`); omit to return everything. Compact per-row payload: id, name, type, enabled, modified_at. This is the generic surface — for email-action CRUD use manage-notifications.php (list-/get-/create-/edit-/delete-notification); for on-submit configuration use set-on-submit-action. Read/delete only at this surface; create/edit for non-email types go through novamira/execute-php with the Formidable action subclass dispatcher (see the SKILL.md companion for snippets).',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'type' => [
                'type' => 'string',
                'description' => 'Filter to a single action type (post_excerpt). Common values: email, wppost, webhook, mailchimp, on_submit. Omit to enumerate every type.',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'actions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'enabled' => ['type' => 'boolean'],
                        'modified_at' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'name', 'type', 'enabled', 'modified_at'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['actions'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_list_form_actions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists every form action regardless of type. Use type=email when you only want notifications (or call list-notifications which is purpose-built). The action `type` column is post_excerpt; the row id is wp_posts.ID.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, type?: string} $input
 * @return array{actions: list<array{id: int, name: string, type: string, enabled: bool, modified_at: string}>}|WP_Error
 */
function formidable_list_form_actions(array $input): array|WP_Error
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

    $type_filter = isset($input['type']) ? trim($input['type']) : '';

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
        if ((int) $post->menu_order !== $form_id) {
            continue;
        }
        if ($type_filter !== '' && $post->post_excerpt !== $type_filter) {
            continue;
        }
        $rows[] = [
            'id' => (int) $post->ID,
            'name' => $post->post_title,
            'type' => $post->post_excerpt,
            'enabled' => $post->post_status === 'publish',
            'modified_at' => $post->post_modified_gmt,
        ];
    }

    return ['actions' => $rows];
}

// ---------------------------------------------------------------------------
// get-form-action
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-get-form-action', [
    'label' => __('Get Formidable Form Action', domain: 'novamira-pro'),
    'description' => __(
        'Reads the full payload for one form action of ANY type. Returns id, name, type, enabled, modified_at, and the decoded settings blob (verbatim — no whitelist filter, since the shape varies by action type). Pass `action_id`; the action MUST belong to `form_id`. Use this when you need to inspect a non-email action (wppost, webhook, payment, …) — for email actions, get-notification gives you the same payload with a curated key list.',
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
            'action' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'enabled' => ['type' => 'boolean'],
                    'modified_at' => ['type' => 'string'],
                    'settings' => ['type' => 'object'],
                ],
                'required' => ['id', 'name', 'type', 'enabled', 'modified_at', 'settings'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['action'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_form_action',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the full payload for one form action by `action_id`. Settings is the raw decoded blob — shape is type-specific (e.g. webhook has url/method; wppost has post_type/post_status/fields).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, action_id?: int|string} $input
 * @return array{action: array{id: int, name: string, type: string, enabled: bool, modified_at: string, settings: array<string, mixed>}}|WP_Error
 */
function formidable_get_form_action(array $input): array|WP_Error
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
    $action = frm_form_action_or_error((int) $form->id, $input['action_id'] ?? 0);
    if ($action instanceof WP_Error) {
        return $action;
    }

    return [
        'action' => [
            'id' => (int) $action->ID,
            'name' => $action->post_title,
            'type' => $action->post_excerpt,
            'enabled' => $action->post_status === 'publish',
            'modified_at' => $action->post_modified_gmt,
            'settings' => frm_form_action_decode_settings($action),
        ],
    ];
}

// ---------------------------------------------------------------------------
// delete-form-action
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-delete-form-action', [
    'label' => __('Delete Formidable Form Action', domain: 'novamira-pro'),
    'description' => __(
        'Hard-deletes one form action by `action_id`. Works for any type EXCEPT on_submit (route those through set-on-submit-action which manages the singleton lifecycle properly). For email actions specifically, delete-notification has the same effect with the same idempotency contract. Idempotent: deleting an already-missing action returns deleted=false.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_delete_form_action',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Hard-deletes one form action. Rejects on_submit (use set-on-submit-action). Idempotent: re-call on a missing action returns deleted=false.',
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
function formidable_delete_form_action(array $input): array|WP_Error
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

    $action = frm_form_action_or_error($form_id, $action_id);
    if ($action instanceof WP_Error) {
        if ($action->get_error_code() === 'formidable_action_not_found') {
            return ['form_id' => $form_id, 'action_id' => $action_id, 'deleted' => false];
        }
        return $action;
    }

    if (in_array($action->post_excerpt, FRM_PROTECTED_ACTION_TYPES, strict: true)) {
        return frm_invalid_input(
            "Action type '{$action->post_excerpt}' is managed via a dedicated ability (set-on-submit-action). Use that surface instead of delete-form-action.",
        );
    }

    $result = wp_delete_post($action_id, force_delete: true);
    if ($result === false || $result === null) {
        return frm_error(
            code: 'formidable_delete_action_failed',
            message: "wp_delete_post returned a falsy value for action {$action_id}.",
        );
    }

    return ['form_id' => $form_id, 'action_id' => $action_id, 'deleted' => true];
}

// ===========================================================================
// Internal helpers (private to this file)
// ===========================================================================

/**
 * Resolve a form action by id + form_id. Cross-form refs and non-action
 * posts return a typed WP_Error.
 */
function frm_form_action_or_error(int $form_id, mixed $action_id): WP_Post|WP_Error
{
    $id = frm_to_int($action_id);
    if ($id <= 0) {
        return frm_invalid_input('`action_id` must be a positive integer.');
    }
    // @mago-expect analyzer:mixed-assignment
    $post = get_post($id);
    if (!$post instanceof WP_Post || $post->post_type !== FRM_ACTION_POST_TYPE) {
        return new WP_Error('formidable_action_not_found', "Formidable form action not found: {$id}", [
            'status' => 404,
        ]);
    }
    if ((int) $post->menu_order !== $form_id) {
        return new WP_Error(
            'formidable_action_form_mismatch',
            "Action {$id} belongs to form {$post->menu_order}, not the requested form {$form_id}.",
            ['status' => 400],
        );
    }
    return $post;
}

/**
 * Decode an action's settings blob into an array. Empty when missing /
 * undecodable. Unlike notifications, this generic surface does NOT
 * apply a whitelist — the shape varies by action type and the agent
 * needs the verbatim payload to make sense of it.
 *
 * @return array<string, mixed>
 */
function frm_form_action_decode_settings(WP_Post $post): array
{
    /** @var mixed $raw */
    $raw = $post->post_content;
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = maybe_unserialize($raw);
    if (!is_array($decoded)) {
        return [];
    }
    /** @var array<string, mixed> $out */
    $out = [];
    // Action settings are type-specific (email vs webhook vs payment) and
    // values can be any shape Formidable serialized in.
    // @mago-expect analyzer:mixed-assignment
    foreach ($decoded as $k => $v) {
        if (!is_string($k)) {
            continue;
        }
        $out[$k] = $v;
    }
    return $out;
}
