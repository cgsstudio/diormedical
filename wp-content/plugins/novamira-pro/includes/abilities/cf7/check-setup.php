<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-check-setup', [
    'label' => __('Check Contact Form 7 Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns a snapshot of the Contact Form 7 install: whether the plugin is active, its version, whether the minimum supported release is satisfied, the count of saved forms, the current user\'s CF7 capabilities (edit / read / delete), and whether the Flamingo companion plugin is active (Flamingo is the standard way to persist CF7 submissions — without it CF7 keeps no record of who submitted what, and reading submissions is not possible). Call this once at the start of any session that will read or write CF7 forms.',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
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
            'forms_total' => ['type' => 'integer'],
            'capabilities' => [
                'type' => 'object',
                'properties' => [
                    'edit' => ['type' => 'boolean'],
                    'read' => ['type' => 'boolean'],
                    'delete' => ['type' => 'boolean'],
                ],
                'required' => ['edit', 'read', 'delete'],
            ],
            'integrations' => [
                'type' => 'object',
                'properties' => [
                    'flamingo_active' => ['type' => 'boolean'],
                    'recaptcha_configured' => [
                        'type' => 'boolean',
                        'description' => 'True when the CF7 reCAPTCHA module is loaded AND has site/secret keys saved. False indicates the form lives without spam protection.',
                    ],
                    'turnstile_configured' => [
                        'type' => 'boolean',
                        'description' => 'True when the Cloudflare Turnstile CF7 module is loaded AND has keys saved.',
                    ],
                    'wpml_active' => [
                        'type' => 'boolean',
                        'description' => 'True when WPML is active. CF7 forms are language-aware under WPML — `forms_total` will include per-language duplicates.',
                    ],
                    'polylang_active' => [
                        'type' => 'boolean',
                        'description' => 'True when Polylang is active. Same multilingual caveat as `wpml_active`.',
                    ],
                ],
                'required' => [
                    'flamingo_active',
                    'recaptcha_configured',
                    'turnstile_configured',
                    'wpml_active',
                    'polylang_active',
                ],
                'description' => 'Companion plugins / integrations observed in this request. Lets an agent decide whether to promise reading submissions (flamingo), warn about spam-gate gaps (recaptcha / turnstile), or coordinate per-language edits (wpml / polylang).',
            ],
        ],
        'required' => [
            'plugin_active',
            'version',
            'min_required',
            'min_satisfied',
            'forms_total',
            'capabilities',
            'integrations',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once at the start of a CF7 session. If `plugin_active=false` or `min_satisfied=false`, do not call any other cf7-* ability — surface the gap to the user. `integrations.flamingo_active` is informational only: CF7 does not save submissions natively and this integration does not cover submission reads.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function cf7_check_setup(): array
{
    $integrations = [
        'flamingo_active' => cf7_flamingo_active(),
        'recaptcha_configured' => cf7_recaptcha_configured(),
        'turnstile_configured' => cf7_turnstile_configured(),
        'wpml_active' => cf7_wpml_active(),
        'polylang_active' => cf7_polylang_active(),
    ];

    if (!cf7_runtime_available()) {
        return [
            'plugin_active' => defined('WPCF7_VERSION'),
            'version' => cf7_version(),
            'min_required' => NOVAMIRA_PRO_CF7_MIN_VERSION,
            'min_satisfied' => false,
            // Count is still safe to expose here: it goes through
            // wp_count_posts on the CF7 CPT which works even when CF7
            // is below the minimum supported version (round 2 audit
            // L1-A1 follow-up).
            'forms_total' => cf7_count_forms(),
            'capabilities' => [
                'edit' => false,
                'read' => false,
                'delete' => false,
            ],
            'integrations' => $integrations,
        ];
    }

    return [
        'plugin_active' => true,
        'version' => cf7_version(),
        'min_required' => NOVAMIRA_PRO_CF7_MIN_VERSION,
        'min_satisfied' => true,
        'forms_total' => cf7_count_forms(),
        'capabilities' => [
            'edit' => current_user_can('wpcf7_edit_contact_forms'),
            'read' => current_user_can('wpcf7_read_contact_forms'),
            'delete' => current_user_can('wpcf7_delete_contact_forms'),
        ],
        'integrations' => $integrations,
    ];
}
