<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-get-form', [
    'label' => __('Get Gravity Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Return the full definition of one Gravity Forms form: fields (indexed array), notifications (id-keyed map), confirmations (id-keyed map), and settings (button, save, pagination, schedule, limit-entries, require-login, post-creation, animation, css classes, etc.). Use include_* flags to slim the response when only inspecting one section.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Gravity Forms form id.',
            ],
            'include_fields' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Include the fields[] block.',
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
            'include_settings' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Include the settings block (everything other than fields/notifications/confirmations).',
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
            'description' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_updated' => ['type' => 'string'],
            'entry_count' => ['type' => 'integer'],
            'fields' => ['type' => ['array', 'null']],
            'notifications' => ['type' => ['object', 'array', 'null']],
            'confirmations' => ['type' => ['object', 'array', 'null']],
            'settings' => ['type' => ['object', 'array', 'null']],
        ],
        'required' => [
            'id',
            'title',
            'description',
            'status',
            'date_created',
            'date_updated',
            'entry_count',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_get_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Decodes the form into its four canonical sections (fields, notifications, confirmations, settings). Settings includes button / save / pagination / schedule / limit-entries / require-login / post-creation / css / animation flags. Set include_* to false to slim the response.',
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
 *     include_notifications?: bool,
 *     include_confirmations?: bool,
 *     include_settings?: bool,
 * } $input
 * @return array<string, mixed>|WP_Error
 */
function gravityforms_get_form(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error((int) $input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $sections = gravityforms_split_form_definition($form);
    $output = gravityforms_get_form_top_level($form);
    gravityforms_attach_form_sections($output, $sections, $input);
    return $output;
}

/**
 * Build the top-level (always-present) keys of the get-form response.
 *
 * @param array<string, mixed> $form
 * @return array<string, mixed>
 */
function gravityforms_get_form_top_level(array $form): array
{
    $form_id = (int) $form['id'];
    return [
        'id' => $form_id,
        'title' => (string) ($form['title'] ?? ''),
        'description' => (string) ($form['description'] ?? ''),
        'status' => (int) ($form['is_active'] ?? 1) === 1 ? 'active' : 'inactive',
        'date_created' => (string) ($form['date_created'] ?? ''),
        'date_updated' => (string) ($form['date_updated'] ?? $form['date_created'] ?? ''),
        'entry_count' => \GFAPI::count_entries($form_id),
    ];
}

/**
 * Attach the four optional sections to the get-form output based on the
 * include_* flags. Defaults all to true (full form by default).
 *
 * @param array<string, mixed>                                                                      $output
 * @param array{fields: list<mixed>, notifications: array<mixed>, confirmations: array<mixed>, settings: array<mixed>} $sections
 * @param array<string, mixed>                                                                      $input
 */
function gravityforms_attach_form_sections(array &$output, array $sections, array $input): void
{
    $flags = [
        'fields' => 'include_fields',
        'notifications' => 'include_notifications',
        'confirmations' => 'include_confirmations',
        'settings' => 'include_settings',
    ];
    foreach ($flags as $section => $flag) {
        if (($input[$flag] ?? true) !== true) {
            continue;
        }
        $output[$section] = $sections[$section];
    }
}
