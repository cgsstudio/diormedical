<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/formidable-check-setup', [
    'label' => __('Check Formidable Setup', domain: 'novamira-pro'),
    'description' => __(
        "Reports the Formidable Forms environment in one call: whether the free plugin is active and at the minimum supported version, whether the Pro add-on is loaded (and its version), whether the Stripe Lite payment surface is configured (wp_frm_payments table present), the current user's Formidable-native capabilities (view_forms, edit_forms, change_settings, view_entries, edit_entries, delete_entries), the install size (forms_total, entries_total), and the field-type catalog actually renderable on this site. Call this first before any other Formidable ability — the payments_supported flag and the capabilities map let the agent branch BEFORE invoking a surface that would otherwise return formidable_pro_required or formidable_capability_missing.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        // No inputs. Keep properties an empty ARRAY (not an object) so
        // WordPress core's additionalProperties:false validator does not
        // fatal on a stdClass when an extra key arrives.
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
            'payments_supported' => ['type' => 'boolean'],
            'forms_total' => ['type' => 'integer'],
            'entries_total' => ['type' => 'integer'],
            'capabilities' => [
                'type' => 'object',
                'properties' => [
                    'view_forms' => ['type' => 'boolean'],
                    'edit_forms' => ['type' => 'boolean'],
                    'change_settings' => ['type' => 'boolean'],
                    'view_entries' => ['type' => 'boolean'],
                    'edit_entries' => ['type' => 'boolean'],
                    'delete_entries' => ['type' => 'boolean'],
                ],
                'required' => [
                    'view_forms',
                    'edit_forms',
                    'change_settings',
                    'view_entries',
                    'edit_entries',
                    'delete_entries',
                ],
                'additionalProperties' => false,
            ],
            'available_field_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
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
            'payments_supported',
            'forms_total',
            'entries_total',
            'capabilities',
            'available_field_types',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Call this first on a Formidable site. The ability is registered as soon as Formidable is loaded (any version), so a missing ability means Formidable itself is not installed.\n\nRead the envelope this way:\n- plugin_active=true + min_satisfied=false → Formidable is installed but BELOW min_required (Novamira Pro's floor). Tell the user to upgrade Formidable; no other Formidable ability is registered on this install.\n- plugin_active=true + min_satisfied=true → Every non-Pro Formidable ability is registered. Proceed.\n- pro_active=true + pro_min_satisfied=true → Pro abilities (applications, cancel-subscription) are also registered.\n- payments_supported=true → list-transactions, get-transaction, list-subscriptions, get-subscription are registered (works on free Formidable with Stripe Lite, doesn't need Pro). cancel-subscription additionally needs pro_active.\n- license_active is informational ONLY — never gate on it, Pro features run unlicensed.\n\nCapability map (gates each write surface — branch before calling):\n- edit_forms gates create-form / edit-form / delete-form / add-field / edit-field / delete-field / set-on-submit-action.\n- change_settings gates edit-form-settings / apply-style / create-notification / edit-notification / delete-notification.\n- view_entries gates list-entries / get-entry / list-transactions / get-transaction.\n- edit_entries gates edit-entry / set-entry-status.\n- delete_entries gates delete-entry.\n- view_forms gates list-forms / get-form (rarely revoked).\n\nUse available_field_types to validate the `type` value before calling add-field — passing a non-listed type returns formidable_invalid_input.\n\nThis ability is idempotent and cheap (per-blog cached). Safe to re-call after a setup change. Typical next call: novamira/formidable-list-forms to enumerate available forms.",
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
 *     payments_supported: bool,
 *     forms_total: int,
 *     entries_total: int,
 *     capabilities: array{
 *         view_forms: bool,
 *         edit_forms: bool,
 *         change_settings: bool,
 *         view_entries: bool,
 *         edit_entries: bool,
 *         delete_entries: bool,
 *     },
 *     available_field_types: list<string>,
 * }
 */
function formidable_check_setup(): array
{
    $detect = frm_detect_setup();

    if (!$detect['active']) {
        // Formidable is not on this site (or below min version). Return the
        // documented shape with every flag off — check-setup is the discovery
        // surface and must NEVER be an error path.
        return frm_check_setup_inactive();
    }

    return [
        'plugin_active' => true,
        'version' => $detect['version'],
        'min_required' => \NOVAMIRA_PRO_FORMIDABLE_MIN_VERSION,
        'min_satisfied' => $detect['min_satisfied'],
        'pro_active' => $detect['pro'],
        'pro_version' => $detect['pro_version'],
        'pro_min_required' => \NOVAMIRA_PRO_FORMIDABLE_PRO_MIN_VERSION,
        'pro_min_satisfied' => $detect['pro_min_satisfied'],
        'license_active' => $detect['licensed'],
        'payments_supported' => $detect['payments_supported'],
        'forms_total' => frm_count_forms(),
        'entries_total' => frm_count_entries(),
        'capabilities' => frm_capability_snapshot(),
        'available_field_types' => frm_available_field_types(),
    ];
}
