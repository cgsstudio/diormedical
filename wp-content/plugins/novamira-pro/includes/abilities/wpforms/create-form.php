<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-create-form', [
    'label' => __('Create WPForms Form', domain: 'novamira-pro'),
    'description' => __(
        'Create a new WPForms form. Required: title. Optional: description, status (publish | draft, default publish), and an initial settings/notifications/confirmations override. The new form starts empty (no fields) — add fields with add-field in a later step. Returns the new form id plus the same compact row list-forms emits.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Form title shown in the WPForms admin list and used as the default notification subject.',
            ],
            'description' => [
                'type' => 'string',
                'default' => '',
                'description' => 'Form description rendered above the fields (admin-only by default).',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'default' => 'publish',
                'description' => 'Initial post_status of the wpforms CPT row.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Optional partial settings block — merged on top of the WPForms defaults (form_title, form_desc, submit_text, anti_spam, ajax_submit, notifications, confirmations, etc.).',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_modified' => ['type' => 'string'],
            'entry_count' => ['type' => ['integer', 'null']],
        ],
        'required' => ['id', 'title', 'slug', 'status', 'date_created', 'date_modified', 'entry_count'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_create_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an empty form (no fields). Title is required. Pass settings overrides for any non-default form configuration — see get-form for the shape. Default notification + confirmation are seeded; call edit-form / manage-notifications later to refine.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     title: string,
 *     description?: string,
 *     status?: string,
 *     settings?: array<string, mixed>,
 * } $input
 * @return array{id: int, title: string, slug: string, status: string, date_created: string, date_modified: string, entry_count: int|null}|WP_Error
 */
function wpforms_create_form(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $cap_error = wpforms_capability_or_error('wpforms_create_forms');
    if ($cap_error !== null) {
        return $cap_error;
    }

    // Round-1 audit CRITICAL: title + description are embedded into the
    // default notification subject and the form-settings blob — strip CRLF
    // first (blocks email-header injection downstream) and apply
    // sanitize_text_field to neutralise any HTML/JS so a malicious title
    // can't reach the admin UI or notification email body verbatim.
    $title = sanitize_text_field(wpforms_strip_crlf(trim($input['title'])));
    if ($title === '') {
        return wpforms_invalid_input('title is required and cannot be empty.');
    }

    $description = isset($input['description'])
        ? sanitize_text_field(wpforms_strip_crlf(trim($input['description'])))
        : '';
    $status = ($input['status'] ?? 'publish') === 'draft' ? 'draft' : 'publish';
    $settings_overrides = is_array($input['settings'] ?? null) ? $input['settings'] : [];
    // Centralised sanitisation of the notifications/confirmations sub-blocks
    // so the same policy the manage-* abilities enforce also covers create.
    // Without this an agent with wpforms_create_forms can seed a brand-new
    // form whose notification sender_name carries an injected Bcc, or whose
    // confirmation message contains stored XSS — bypassing every A5 guard.
    $settings_overrides = wpforms_sanitize_settings_overrides($settings_overrides);

    $blob = wpforms_blank_form_blob($title, $description, $settings_overrides);

    $encoded = wpforms_encode_form($blob);
    if ($encoded instanceof WP_Error) {
        return $encoded;
    }

    $inserted = wp_insert_post([
        'post_type' => 'wpforms',
        'post_title' => $title,
        'post_status' => $status,
        'post_excerpt' => $description,
        'post_content' => $encoded,
    ], wp_error: true);
    if ($inserted instanceof WP_Error) {
        return $inserted;
    }

    // Sync the in-blob id with the freshly-assigned post id. WPForms relies
    // on blob.id matching the post id in several admin code paths.
    $persisted = wpforms_persist_form($inserted, $blob);
    if ($persisted instanceof WP_Error) {
        wp_delete_post($inserted, force_delete: true);
        return $persisted;
    }

    /** @var \WP_Post|null $form */
    $form = get_post($inserted);
    if (!$form instanceof \WP_Post) {
        return new WP_Error('wpforms_create_failed', 'Inserted form could not be read back.', ['status' => 500]);
    }

    return wpforms_form_row($form);
}
