<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use FluentForm\App\Models\Form;
use FluentForm\App\Models\Submission;
use Throwable;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-check-setup', [
    'label' => __('Check Fluent Forms Setup', domain: 'novamira-pro'),
    'description' => __(
        "Reports the Fluent Forms environment: whether the free plugin is active and at the minimum supported version, whether the Pro add-on is loaded (and its version), the current user's per-form capability map (forms_manager, entries_viewer, manage_entries, settings_manager, view_payments, manage_payments), the supports.* feature flags (payments, conversational_forms, quiz, draft_submissions, file_upload, integrations, pdf_generator), the configured marketing-integration feeds (mailchimp, activecampaign, hubspot, …, plus payment gateways), and the global forms_total / submissions_total counters. Call this first before any other Fluent Forms ability — the supports map lets the agent branch BEFORE invoking a Pro-gated surface that would otherwise return fluentforms_pro_required.",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        // No inputs. Keep properties an empty ARRAY (not an object): with
        // additionalProperties:false WordPress core's validator indexes
        // properties as an array for every extra key, which fatals on a stdClass.
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plugin_active' => ['type' => 'boolean'],
            'version' => ['type' => ['string', 'null']],
            'min_required' => ['type' => 'string'],
            'min_satisfied' => ['type' => 'boolean'],
            'pro_active' => ['type' => 'boolean'],
            'pro_version' => ['type' => ['string', 'null']],
            'pro_min_required' => ['type' => 'string'],
            'pro_min_satisfied' => ['type' => 'boolean'],
            'license_active' => ['type' => 'boolean'],
            'forms_total' => ['type' => 'integer'],
            'submissions_total' => ['type' => 'integer'],
            'capabilities' => [
                'type' => 'object',
                'properties' => [
                    'forms_manager' => ['type' => 'boolean'],
                    'entries_viewer' => ['type' => 'boolean'],
                    'manage_entries' => ['type' => 'boolean'],
                    'settings_manager' => ['type' => 'boolean'],
                    'view_payments' => ['type' => 'boolean'],
                    'manage_payments' => ['type' => 'boolean'],
                ],
                'required' => [
                    'forms_manager',
                    'entries_viewer',
                    'manage_entries',
                    'settings_manager',
                    'view_payments',
                    'manage_payments',
                ],
            ],
            'supports' => [
                'type' => 'object',
                'properties' => [
                    'payments' => ['type' => 'boolean'],
                    'conversational_forms' => ['type' => 'boolean'],
                    'quiz' => ['type' => 'boolean'],
                    'draft_submissions' => ['type' => 'boolean'],
                    'file_upload' => ['type' => 'boolean'],
                    'integrations' => ['type' => 'boolean'],
                    'pdf_generator' => ['type' => 'boolean'],
                ],
                'required' => [
                    'payments',
                    'conversational_forms',
                    'quiz',
                    'draft_submissions',
                    'file_upload',
                    'integrations',
                    'pdf_generator',
                ],
            ],
            'integrations_active' => [
                'type' => 'object',
                'properties' => [
                    'active_count' => ['type' => 'integer'],
                    'feeds' => ['type' => 'object'],
                ],
                'required' => ['active_count', 'feeds'],
            ],
        ],
        'required' => [
            'plugin_active',
            'version',
            'min_required',
            'min_satisfied',
            'pro_active',
            'pro_version',
            'pro_min_required',
            'pro_min_satisfied',
            'license_active',
            'forms_total',
            'submissions_total',
            'capabilities',
            'supports',
            'integrations_active',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first. (If this ability is missing from your tool list, Fluent Forms is not at the minimum supported version.) pro_active indicates the Pro add-on is loaded; the supports.* booleans tell you whether each Pro-gated surface (payments, quiz, draft submissions, conversational forms, file upload, integrations, PDF generator) is actually usable. license_active is informational only — Pro features run unlicensed. Branch on supports.* BEFORE invoking a Pro-only ability so the agent never gets a fluentforms_pro_required surprise.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     plugin_active: bool,
 *     version: string|null,
 *     min_required: string,
 *     min_satisfied: bool,
 *     pro_active: bool,
 *     pro_version: string|null,
 *     pro_min_required: string,
 *     pro_min_satisfied: bool,
 *     license_active: bool,
 *     forms_total: int,
 *     submissions_total: int,
 *     capabilities: array{
 *         forms_manager: bool,
 *         entries_viewer: bool,
 *         manage_entries: bool,
 *         settings_manager: bool,
 *         view_payments: bool,
 *         manage_payments: bool,
 *     },
 *     supports: array{
 *         payments: bool,
 *         conversational_forms: bool,
 *         quiz: bool,
 *         draft_submissions: bool,
 *         file_upload: bool,
 *         integrations: bool,
 *         pdf_generator: bool,
 *     },
 *     integrations_active: array{active_count: int, feeds: array<string, bool>},
 * }
 */
function fluentforms_check_setup(): array
{
    $detect = ff_detect_setup();

    if (!$detect['active']) {
        // FF is not on this site. Return the documented shape with every flag
        // off — check-setup is the discovery surface, never an error path.
        return ff_check_setup_inactive();
    }

    return [
        'plugin_active' => true,
        'version' => $detect['version'],
        'min_required' => \NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION,
        'min_satisfied' => $detect['min_satisfied'],
        'pro_active' => $detect['pro'],
        'pro_version' => $detect['pro_version'],
        'pro_min_required' => \NOVAMIRA_PRO_FLUENTFORMS_PRO_MIN_VERSION,
        'pro_min_satisfied' => $detect['pro_min_satisfied'],
        'license_active' => $detect['licensed'],
        'forms_total' => ff_safe_count(Form::class),
        'submissions_total' => ff_safe_count(Submission::class),
        'capabilities' => ff_capability_snapshot(),
        'supports' => [
            'payments' => ff_supports('payments'),
            'conversational_forms' => ff_supports('conversational_forms'),
            'quiz' => ff_supports('quiz'),
            'draft_submissions' => ff_supports('draft_submissions'),
            'file_upload' => ff_supports('file_upload'),
            'integrations' => ff_supports('integrations'),
            'pdf_generator' => ff_supports('pdf_generator'),
        ],
        'integrations_active' => ff_active_integrations(),
    ];
}

/**
 * Shape returned when Fluent Forms is not active. Every documented key is
 * present so the schema validator and downstream consumers see a stable
 * envelope regardless of plugin state.
 *
 * @return array{
 *     plugin_active: bool,
 *     version: string|null,
 *     min_required: string,
 *     min_satisfied: bool,
 *     pro_active: bool,
 *     pro_version: string|null,
 *     pro_min_required: string,
 *     pro_min_satisfied: bool,
 *     license_active: bool,
 *     forms_total: int,
 *     submissions_total: int,
 *     capabilities: array{
 *         forms_manager: bool,
 *         entries_viewer: bool,
 *         manage_entries: bool,
 *         settings_manager: bool,
 *         view_payments: bool,
 *         manage_payments: bool,
 *     },
 *     supports: array{
 *         payments: bool,
 *         conversational_forms: bool,
 *         quiz: bool,
 *         draft_submissions: bool,
 *         file_upload: bool,
 *         integrations: bool,
 *         pdf_generator: bool,
 *     },
 *     integrations_active: array{active_count: int, feeds: array<string, bool>},
 * }
 */
function ff_check_setup_inactive(): array
{
    return [
        'plugin_active' => false,
        'version' => null,
        'min_required' => \NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION,
        'min_satisfied' => false,
        'pro_active' => false,
        'pro_version' => null,
        'pro_min_required' => \NOVAMIRA_PRO_FLUENTFORMS_PRO_MIN_VERSION,
        'pro_min_satisfied' => false,
        'license_active' => false,
        'forms_total' => 0,
        'submissions_total' => 0,
        'capabilities' => [
            'forms_manager' => false,
            'entries_viewer' => false,
            'manage_entries' => false,
            'settings_manager' => false,
            'view_payments' => false,
            'manage_payments' => false,
        ],
        'supports' => [
            'payments' => false,
            'conversational_forms' => false,
            'quiz' => false,
            'draft_submissions' => false,
            'file_upload' => false,
            'integrations' => false,
            'pdf_generator' => false,
        ],
        'integrations_active' => ff_active_integrations(),
    ];
}

/**
 * Per-user capability snapshot. Reads the six Fluent Forms native capabilities
 * directly (no per-form scope here — check-setup is global) so the agent can
 * see the user's surface area before issuing any write.
 *
 * @return array{
 *     forms_manager: bool,
 *     entries_viewer: bool,
 *     manage_entries: bool,
 *     settings_manager: bool,
 *     view_payments: bool,
 *     manage_payments: bool,
 * }
 */
function ff_capability_snapshot(): array
{
    return [
        'forms_manager' => current_user_can('fluentform_forms_manager'),
        'entries_viewer' => current_user_can('fluentform_entries_viewer'),
        'manage_entries' => current_user_can('fluentform_manage_entries'),
        'settings_manager' => current_user_can('fluentform_settings_manager'),
        'view_payments' => current_user_can('fluentform_view_payments'),
        'manage_payments' => current_user_can('fluentform_manage_payments'),
    ];
}

/**
 * Defensively count rows on a FluentForm model. On a freshly installed FF the
 * custom tables (`fluentform_forms`, `fluentform_submissions`) exist after
 * activation, but on a brand-new multisite subsite or a half-activated install
 * the table may be absent — `count()` then throws. Catch and report 0 so the
 * check-setup ability is never the surface that surfaces the wpdb error.
 *
 * @param class-string<\FluentForm\App\Models\Model> $model
 */
function ff_safe_count(string $model): int
{
    try {
        return $model::count();
    } catch (Throwable) {
        return 0;
    }
}
