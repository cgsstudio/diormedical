<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-create-form', [
    'label' => __('Create Gravity Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Create a new Gravity Forms form. Required: title. Optional: description, status (active | inactive, default active), and an initial settings override (button, save, pagination, schedule, limit-entries, require-login, etc.). The new form starts empty (no fields) — add fields with add-field in a later step. Returns the new form id plus the same compact row list-forms emits.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Form title shown in the Gravity Forms admin list and used as the default email subject in notifications.',
            ],
            'description' => [
                'type' => 'string',
                'default' => '',
                'description' => 'Form description rendered above the fields on the front end (if showFormDescription is enabled).',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'inactive'],
                'default' => 'active',
                'description' => 'Initial activation state. Inactive forms render no front-end shortcode output.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Optional partial settings block — merged on top of GF defaults (button, save, pagination, schedule, limit-entries, require-login, postCreation, animation, etc.). Pass only the keys you want to override.',
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
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_updated' => ['type' => 'string'],
            'entry_count' => ['type' => 'integer'],
        ],
        'required' => ['id', 'title', 'status', 'date_created', 'date_updated', 'entry_count'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_create_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an empty form (no fields, no notifications, no confirmations). Title is required. Pass settings overrides for any non-default form configuration — see get-form for the shape. Default notification + default confirmation are NOT seeded; use manage-notifications / manage-confirmations to add them.',
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
 * @return array{id: int, title: string, status: string, date_created: string, date_updated: string, entry_count: int}|WP_Error
 */
function gravityforms_create_form(array $input): array|WP_Error
{
    if (!gravityforms_detect_setup()['active']) {
        return gravityforms_not_active();
    }
    $cap_error = gravityforms_capability_or_error('gravityforms_create_form');
    if ($cap_error !== null) {
        return $cap_error;
    }

    $title = trim($input['title']);
    if ($title === '') {
        return gravityforms_invalid_input('title is required and cannot be empty.');
    }
    $description = trim($input['description'] ?? '');
    $is_active = ($input['status'] ?? 'active') === 'active' ? 1 : 0;

    $form_meta = [
        'title' => $title,
        'description' => $description,
        'is_active' => $is_active,
        'fields' => [],
        'notifications' => [],
        'confirmations' => [],
    ];
    if (isset($input['settings'])) {
        // Merge overrides on top — keys in $input['settings'] win, but the
        // structural keys above stay protected so an override can't drop them.
        $structural = ['title', 'description', 'is_active', 'fields', 'notifications', 'confirmations'];
        $overrides = array_diff_key($input['settings'], array_flip($structural));
        $form_meta = array_merge($overrides, $form_meta);
    }

    $form_id = \GFAPI::add_form($form_meta);
    if ($form_id instanceof WP_Error) {
        return $form_id;
    }
    if ($form_id <= 0) {
        return gravityforms_operation_failed('GFAPI::add_form returned no form id.');
    }

    // GFAPI::add_form() does NOT persist is_active to the wp_gf_form row
    // (only to the meta JSON), so the row stays at the schema default
    // is_active=1. When the caller asked for inactive, explicitly flip the
    // column via update_form_property — the canonical "deactivate" path.
    if ($is_active === 0) {
        $flip = \GFAPI::update_form_property($form_id, 'is_active', 0);
        if ($flip instanceof WP_Error) {
            return $flip;
        }
    }

    $form = \GFAPI::get_form($form_id);
    if ($form === false) {
        return gravityforms_form_not_found($form_id);
    }

    return gravityforms_form_row($form);
}
