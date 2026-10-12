<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-get-form', [
    'label' => __('Get WPForms Form', domain: 'novamira-pro'),
    'description' => __(
        'Return the full definition of one WPForms form: fields (array indexed by field id), settings, notifications (settings.notifications), confirmations (settings.confirmations), payments, and form meta. post_content is a JSON blob; this ability decodes it and returns the canonical top-level sections.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'WPForms form id (the post id of the wpforms CPT row).',
            ],
            'include_fields' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Include the fields[] block.',
            ],
            'include_settings' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Include the settings{} block (excluding notifications/confirmations).',
            ],
            'include_notifications' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Include the notifications block.',
            ],
            'include_confirmations' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Include the confirmations block.',
            ],
            'include_payments' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include the payments block (Pro). Off by default — payments configs are large.',
            ],
            'include_meta' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include the meta block.',
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
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_modified' => ['type' => 'string'],
            'entry_count' => ['type' => ['integer', 'null']],
            'fields' => ['type' => ['array', 'object', 'null']],
            'settings' => ['type' => ['object', 'null']],
            'notifications' => ['type' => ['array', 'object', 'null']],
            'confirmations' => ['type' => ['array', 'object', 'null']],
            'payments' => ['type' => ['array', 'object', 'null']],
            'meta' => ['type' => ['object', 'null']],
        ],
        'required' => [
            'id',
            'title',
            'slug',
            'status',
            'date_created',
            'date_modified',
            'entry_count',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_get_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Decodes post_content (JSON) into the canonical top-level sections. Set include_* flags to false to slim the response (e.g. when only inspecting fields). Notifications and confirmations live UNDER settings in the raw blob but are surfaced separately here for clarity. include_payments defaults off because the payments block is large.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     include_fields?: bool,
 *     include_settings?: bool,
 *     include_notifications?: bool,
 *     include_confirmations?: bool,
 *     include_payments?: bool,
 *     include_meta?: bool,
 * } $input
 * @return array<string, mixed>|WP_Error
 */
function wpforms_get_form(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }

    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }

    // Round-1 audit CRITICAL: per-form capability check on read access —
    // global novamira_permission_callback alone is not enough on multi-user
    // installs where wpforms_view_forms is gated per-form via the
    // `wpforms_user_can` filter chain (team-mode).
    $cap_error = wpforms_capability_or_error('wpforms_view_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $decoded = wpforms_decode_form($form);
    if ($decoded instanceof WP_Error) {
        return $decoded;
    }

    return wpforms_get_form_payload($form, $decoded, $input);
}

/**
 * Build the get-form response payload from a resolved form + decoded
 * blob + the per-include opt-in flags. Split out of wpforms_get_form to
 * keep the entry-point under mago's cyclomatic threshold after the
 * round-1 capability-check addition.
 *
 * @param array<string, mixed> $decoded
 * @param array<string, mixed> $input
 *
 * @return array<string, mixed>
 */
function wpforms_get_form_payload(\WP_Post $form, array $decoded, array $input): array
{
    $output = [
        'id' => (int) $form->ID,
        'title' => $form->post_title,
        'slug' => $form->post_name,
        'status' => $form->post_status,
        'date_created' => $form->post_date_gmt,
        'date_modified' => $form->post_modified_gmt,
        'entry_count' => wpforms_form_entry_count((int) $form->ID),
    ];

    if (($input['include_fields'] ?? true) === true) {
        $output['fields'] = $decoded['fields'];
    }
    if (($input['include_settings'] ?? true) === true) {
        // Strip notifications/confirmations from settings since they are surfaced
        // as their own top-level sections — the caller gets each block exactly
        // once, no duplication.
        $settings = wpforms_section($decoded, key: 'settings');
        unset($settings['notifications'], $settings['confirmations']);
        $output['settings'] = $settings;
    }
    if (($input['include_notifications'] ?? true) === true) {
        $output['notifications'] = $decoded['notifications'];
    }
    if (($input['include_confirmations'] ?? true) === true) {
        $output['confirmations'] = $decoded['confirmations'];
    }
    if (($input['include_payments'] ?? false) === true) {
        $output['payments'] = $decoded['payments'];
    }
    if (($input['include_meta'] ?? false) === true) {
        $output['meta'] = $decoded['meta'];
    }

    return $output;
}
