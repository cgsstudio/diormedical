<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const NVP_WPFORMS_CONFIRMATION_TYPES = ['message', 'page', 'redirect'];

// ─── list-confirmations ───────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-list-confirmations', [
    'label' => __('List WPForms Confirmations', domain: 'novamira-pro'),
    'description' => __(
        'List every confirmation configured on a form, keyed by string-int id. A confirmation is what the user sees after submitting — a thank-you message, a redirect to a WP page, or a redirect to an external URL. Use this before crafting a confirmation mutation.',
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
            'confirmations' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'count', 'confirmations'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_list_confirmations',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the confirmations map verbatim. Each entry has type (message | page | redirect) plus type-specific keys: message + message_scroll for message; page (post id) for page; redirect (URL) for redirect. A brand-new form has one default at id "1".',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int} $input
 * @return array{form_id: int, count: int, confirmations: array<array-key, mixed>}|WP_Error
 */
function wpforms_list_confirmations(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    // Round-1 audit CRITICAL: list-confirmations had no capability gate —
    // mutation paths (create/edit/delete) already checked it, the read path
    // was missing. Use wpforms_view_forms to match the read-side semantics
    // of get-form / list-notifications.
    $cap_error = wpforms_capability_or_error('wpforms_view_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }
    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $confirmations = wpforms_section($settings, key: 'confirmations');
    return [
        'form_id' => (int) $form->ID,
        'count' => count($confirmations),
        'confirmations' => $confirmations,
    ];
}

// ─── create-confirmation ──────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-create-confirmation', [
    'label' => __('Create WPForms Confirmation', domain: 'novamira-pro'),
    'description' => __(
        'Append a confirmation to a form. Required: form_id, name, type. Type-specific required keys: type=message → message; type=page → page (target WP post id); type=redirect → redirect (target URL). Optional: message_scroll (auto-scroll to the message on submit) for type=message.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string', 'minLength' => 1, 'description' => 'Internal label shown in the admin UI.'],
            'type' => [
                'type' => 'string',
                'enum' => NVP_WPFORMS_CONFIRMATION_TYPES,
                'description' => 'What the user sees after submitting.',
            ],
            'message' => ['type' => 'string', 'description' => 'Required when type=message. Supports HTML.'],
            'message_scroll' => ['type' => 'boolean', 'description' => 'Auto-scroll to the message (type=message).'],
            'page' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Required when type=page. WP post id to redirect to.',
            ],
            'redirect' => ['type' => 'string', 'description' => 'Required when type=redirect. Target URL.'],
        ],
        'required' => ['form_id', 'name', 'type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'confirmation_id' => ['type' => 'integer'],
            'confirmation' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'confirmation_id', 'confirmation'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_create_confirmation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'type=message + missing message → wpforms_invalid_input. type=page + missing or non-existent page → wpforms_invalid_input. type=redirect + missing URL → wpforms_invalid_input. The agent should validate the page/redirect target before calling.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{form_id: int, confirmation_id: int, confirmation: array<string, mixed>}|WP_Error
 */
function wpforms_create_confirmation(array $input): array|WP_Error
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

    $confirmation = wpforms_build_confirmation($input);
    if ($confirmation instanceof WP_Error) {
        return $confirmation;
    }

    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $confirmations = wpforms_section($settings, key: 'confirmations');
    $id = wpforms_next_collection_id($confirmations);

    $confirmations[(string) $id] = $confirmation;
    $settings['confirmations'] = $confirmations;
    $blob['settings'] = $settings;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'confirmation_id' => $id,
        'confirmation' => $confirmation,
    ];
}

/**
 * Build a normalised confirmation entry from agent input. Validates the
 * type-specific required fields up-front so we never persist a broken config.
 *
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function wpforms_build_confirmation(array $input): array|WP_Error
{
    $type = (string) $input['type'];
    $confirmation = [
        'name' => sanitize_text_field((string) $input['name']),
        'type' => $type,
    ];

    if ($type === 'message') {
        if (!isset($input['message']) || trim((string) $input['message']) === '') {
            return wpforms_invalid_input('message is required when type=message.');
        }
        $confirmation['message'] = wpforms_sanitize_html_message((string) $input['message']);
        $confirmation['message_scroll'] = wpforms_bool_string($input['message_scroll'] ?? true);
        return $confirmation;
    }

    if ($type === 'page') {
        $page_id = wpforms_validate_confirmation_page($input['page'] ?? 0);
        if ($page_id instanceof WP_Error) {
            return $page_id;
        }
        $confirmation['page'] = $page_id;
        return $confirmation;
    }

    $redirect = wpforms_validate_confirmation_redirect($input['redirect'] ?? '');
    if ($redirect instanceof WP_Error) {
        return $redirect;
    }
    $confirmation['redirect'] = $redirect;
    return $confirmation;
}

// ─── edit-confirmation ────────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-edit-confirmation', [
    'label' => __('Edit WPForms Confirmation', domain: 'novamira-pro'),
    'description' => __(
        'Partial merge update of one confirmation. Required: form_id, confirmation_id. Optional: name, message, message_scroll, page, redirect. The confirmation type CANNOT be changed (delete + create to switch type, since the meaningful keys differ). Updating fields that do not match the current type is rejected.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'confirmation_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string'],
            'message' => ['type' => 'string'],
            'message_scroll' => ['type' => 'boolean'],
            'page' => ['type' => 'integer', 'minimum' => 1],
            'redirect' => ['type' => 'string'],
        ],
        'required' => ['form_id', 'confirmation_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'confirmation_id' => ['type' => 'integer'],
            'updated' => ['type' => 'array', 'items' => ['type' => 'string']],
            'confirmation' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'confirmation_id', 'updated', 'confirmation'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_edit_confirmation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Type cannot be changed. Trying to pass `message` to a type=page confirmation is rejected with wpforms_invalid_input. Use delete + create to switch type.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{form_id: int, confirmation_id: int, updated: list<string>, confirmation: array<string, mixed>}|WP_Error
 */
function wpforms_edit_confirmation(array $input): array|WP_Error
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

    $confirmation_id = (int) $input['confirmation_id'];
    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $confirmations = wpforms_section($settings, key: 'confirmations');
    $key = (string) $confirmation_id;

    if (!array_key_exists($key, $confirmations) || !is_array($confirmations[$key])) {
        return new WP_Error(
            'wpforms_confirmation_not_found',
            "Confirmation {$confirmation_id} does not exist on form {$form->ID}.",
            ['status' => 404],
        );
    }

    /** @var array<string, mixed> $confirmation */
    $confirmation = $confirmations[$key];
    $updated = wpforms_apply_confirmation_update($confirmation, $input);
    if ($updated instanceof WP_Error) {
        return $updated;
    }

    if ($updated === []) {
        return [
            'form_id' => (int) $form->ID,
            'confirmation_id' => $confirmation_id,
            'updated' => [],
            'confirmation' => $confirmation,
        ];
    }

    $confirmations[$key] = $confirmation;
    $settings['confirmations'] = $confirmations;
    $blob['settings'] = $settings;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'confirmation_id' => $confirmation_id,
        'updated' => $updated,
        'confirmation' => $confirmation,
    ];
}

/**
 * Apply a partial merge to a confirmation entry, rejecting any key that does
 * not belong to the current type. Returns the list of changed keys, or a
 * WP_Error when an invalid key combination is detected.
 *
 * @param array<string, mixed>    $confirmation
 * @param array<array-key, mixed> $input
 * @return list<string>|WP_Error
 */
function wpforms_apply_confirmation_update(array &$confirmation, array $input): array|WP_Error
{
    $type = (string) ($confirmation['type'] ?? 'message');

    // Type-mismatch check: reject keys that belong to a different confirmation type.
    $message_keys = ['message', 'message_scroll'];
    $page_keys = ['page'];
    $redirect_keys = ['redirect'];
    $type_keys = ['message' => $message_keys, 'page' => $page_keys, 'redirect' => $redirect_keys];
    foreach ($type_keys as $other_type => $keys) {
        if ($other_type === $type) {
            continue;
        }
        foreach ($keys as $k) {
            if (array_key_exists($k, $input)) {
                return wpforms_invalid_input(
                    "Key '{$k}' is not valid for a confirmation of type '{$type}'. Use delete + create to change type.",
                );
            }
        }
    }

    /** @var list<string> $touched */
    $touched = [];
    if (array_key_exists('name', $input)) {
        $next = sanitize_text_field((string) $input['name']);
        if (($confirmation['name'] ?? null) !== $next) {
            $confirmation['name'] = $next;
            $touched[] = 'name';
        }
    }

    $type_touched = wpforms_apply_confirmation_type_keys($confirmation, $input, $type);
    if ($type_touched instanceof WP_Error) {
        return $type_touched;
    }
    return [...$touched, ...$type_touched];
}

/**
 * Apply the type-specific subset of an edit-confirmation payload. Dispatches
 * to the matching per-type helper so each branch stays small. Returns the
 * list of touched keys, OR a WP_Error from the page/redirect validators when
 * the new value fails the same validation rules create-confirmation enforces.
 *
 * @param array<string, mixed>    $confirmation
 * @param array<array-key, mixed> $input
 * @return list<string>|WP_Error
 */
function wpforms_apply_confirmation_type_keys(array &$confirmation, array $input, string $type): array|WP_Error
{
    return match ($type) {
        'message' => wpforms_apply_confirmation_message_keys($confirmation, $input),
        'page' => wpforms_apply_confirmation_page_keys($confirmation, $input),
        'redirect' => wpforms_apply_confirmation_redirect_keys($confirmation, $input),
        default => [],
    };
}

/**
 * Apply message + message_scroll edits to a type=message confirmation.
 *
 * @param array<string, mixed>    $confirmation
 * @param array<array-key, mixed> $input
 * @return list<string>
 */
function wpforms_apply_confirmation_message_keys(array &$confirmation, array $input): array
{
    /** @var list<string> $touched */
    $touched = [];
    if (array_key_exists('message', $input)) {
        $next = wpforms_sanitize_html_message((string) $input['message']);
        if (($confirmation['message'] ?? null) !== $next) {
            $confirmation['message'] = $next;
            $touched[] = 'message';
        }
    }
    if (array_key_exists('message_scroll', $input)) {
        $coerced = wpforms_bool_string($input['message_scroll']);
        if (($confirmation['message_scroll'] ?? null) !== $coerced) {
            $confirmation['message_scroll'] = $coerced;
            $touched[] = 'message_scroll';
        }
    }
    return $touched;
}

/**
 * Apply a page id edit to a type=page confirmation. Silently skips a
 * non-existent target so the persist short-circuits without spurious updates.
 *
 * @param array<string, mixed>    $confirmation
 * @param array<array-key, mixed> $input
 * @return list<string>|WP_Error
 */
function wpforms_apply_confirmation_page_keys(array &$confirmation, array $input): array|WP_Error
{
    if (!array_key_exists('page', $input)) {
        return [];
    }
    // Same validator as create-confirmation — pre-fix this silently accepted
    // trash/revision/attachment ids OR swallowed non-existent ids as no-ops
    // (inconsistent with the create surface that returned 400).
    $next = wpforms_validate_confirmation_page($input['page']);
    if ($next instanceof WP_Error) {
        return $next;
    }
    if (($confirmation['page'] ?? null) === $next) {
        return [];
    }
    $confirmation['page'] = $next;
    return ['page'];
}

/**
 * Apply a redirect URL edit to a type=redirect confirmation.
 *
 * @param array<string, mixed>    $confirmation
 * @param array<array-key, mixed> $input
 * @return list<string>|WP_Error
 */
function wpforms_apply_confirmation_redirect_keys(array &$confirmation, array $input): array|WP_Error
{
    if (!array_key_exists('redirect', $input)) {
        return [];
    }
    // Same validator as create-confirmation — pre-fix an empty or
    // disallowed-protocol URL (javascript:, data:, file:) silently persisted
    // as empty, breaking type=redirect confirmations with no signal.
    $next = wpforms_validate_confirmation_redirect($input['redirect']);
    if ($next instanceof WP_Error) {
        return $next;
    }
    if (($confirmation['redirect'] ?? null) === $next) {
        return [];
    }
    $confirmation['redirect'] = $next;
    return ['redirect'];
}

// ─── delete-confirmation ──────────────────────────────────────────────────

wp_register_ability('novamira/wpforms-delete-confirmation', [
    'label' => __('Delete WPForms Confirmation', domain: 'novamira-pro'),
    'description' => __(
        'Remove a confirmation from a form. Required: form_id, confirmation_id. Refuses to delete the last surviving confirmation — a form must always have at least one confirmation so users get feedback after submitting.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'confirmation_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id', 'confirmation_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'confirmation_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
            'remaining' => ['type' => 'integer'],
        ],
        'required' => ['form_id', 'confirmation_id', 'deleted', 'remaining'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_delete_confirmation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Refuses to delete the last confirmation (wpforms_last_confirmation). Edit-confirmation it instead.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, confirmation_id: int} $input
 * @return array{form_id: int, confirmation_id: int, deleted: bool, remaining: int}|WP_Error
 */
function wpforms_delete_confirmation(array $input): array|WP_Error
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

    $confirmation_id = (int) $input['confirmation_id'];
    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $confirmations = wpforms_section($settings, key: 'confirmations');
    $key = (string) $confirmation_id;

    if (!array_key_exists($key, $confirmations)) {
        return new WP_Error(
            'wpforms_confirmation_not_found',
            "Confirmation {$confirmation_id} does not exist on form {$form->ID}.",
            ['status' => 404],
        );
    }
    if (count($confirmations) <= 1) {
        return new WP_Error(
            'wpforms_last_confirmation',
            "Cannot delete the last confirmation on form {$form->ID}; edit it instead.",
            ['status' => 400],
        );
    }

    unset($confirmations[$key]);
    $settings['confirmations'] = $confirmations;
    $blob['settings'] = $settings;

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'confirmation_id' => $confirmation_id,
        'deleted' => true,
        'remaining' => count($confirmations),
    ];
}
