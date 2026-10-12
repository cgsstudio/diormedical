<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-check-setup', [
    'label' => __('Check Ninja Forms Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Ninja Forms runtime state. Returns the plugin version, the minimum version Novamira Pro accepts, whether the NF3 storage tables exist, the number of forms on this install, the number of field types the install can build, the capability flags for the current user, and site-global anti-spam integration flags (hCaptcha / reCAPTCHA / Turnstile). Call this FIRST before any other ninja-forms-* ability so the agent can fail fast when Ninja Forms is inactive, below the minimum version, or has not finished its NF3 upgrader. All Ninja Forms management (forms / fields / notifications / submissions) is driven through the Novamira ninja-forms-* abilities — this integration does not require the native ninjaforms/* abilities.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plugin_active' => ['type' => 'boolean'],
            'version' => ['type' => 'string'],
            'min_required' => ['type' => 'string'],
            'min_satisfied' => ['type' => 'boolean'],
            'storage_ready' => ['type' => 'boolean'],
            'forms_total' => ['type' => 'integer'],
            'available_field_types' => ['type' => 'integer'],
            'capabilities' => ['type' => 'object'],
            'integrations' => ['type' => 'object'],
        ],
        'required' => [
            'plugin_active',
            'version',
            'min_required',
            'min_satisfied',
            'storage_ready',
            'forms_total',
            'available_field_types',
            'capabilities',
            'integrations',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call FIRST when Ninja Forms is the target. `plugin_active` reflects whether Ninja Forms is loaded AND >= NOVAMIRA_PRO_NINJAFORMS_MIN_VERSION; older installs report `false` so the agent halts before any CRUD. `storage_ready: false` means the NF3 upgrader has not finished — instruct the user to open the Ninja Forms admin page once so the install completes. `available_field_types` is the count of types add-field accepts; call ninja-forms-get-field-types-schema for the catalog. Within the form-model-migration workflow this is also the Phase 1 setup check.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function ninja_forms_check_setup(): array
{
    $plugin_active = ninja_forms_runtime_available();

    return [
        'plugin_active' => $plugin_active,
        'version' => ninja_forms_version(),
        'min_required' => NOVAMIRA_PRO_NINJAFORMS_MIN_VERSION,
        'min_satisfied' => $plugin_active,
        'storage_ready' => $plugin_active && ninja_forms_storage_ready(),
        'forms_total' => $plugin_active ? ninja_forms_count_forms() : 0,
        'available_field_types' => $plugin_active ? count(ninja_forms_field_registry()) : 0,
        'capabilities' => [
            'manage_forms' => current_user_can('manage_options'),
            'edit_forms' => current_user_can('manage_options'),
            'delete_forms' => current_user_can('manage_options'),
        ],
        'integrations' => [
            'hcaptcha_configured' => $plugin_active && ninja_forms_hcaptcha_configured(),
            'recaptcha_configured' => $plugin_active && ninja_forms_recaptcha_configured(),
            'turnstile_configured' => $plugin_active && ninja_forms_turnstile_configured(),
        ],
    ];
}
