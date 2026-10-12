<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmAppHelper;
use FrmForm;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Whitelist of `wp_frm_forms.options` keys this ability is willing to
 * write. Mirrors the curated subset of FrmForm options the admin UI
 * exposes under Settings → General. Anything outside this list is
 * silently dropped at the boundary (FluentForms audit round 3 #F
 * arbitrary-key-injection guard at the form-options surface).
 *
 * `custom_style` is INTENTIONALLY excluded — apply-style is the only
 * supported path for style binding so the agent can't bypass the
 * style-exists check.
 *
 * `success_msg` / `success_action` / `success_url` / `success_page_id`
 * are excluded too — those land on the on-submit action (S4.A6
 * set-on-submit-action), not on the form options, since Formidable
 * 6.0+ migrated those out of the options blob.
 */
const FRM_FORM_SETTINGS_KEYS = [
    'submit_value',
    'submit_html',
    'before_html',
    'after_html',
    'show_title',
    'show_description',
    'ajax_submit',
    'antispam',
    'js_validate',
    'save_draft',
    'editable',
    'logged_in',
    'no_save',
    'open_status',
    'open_date',
    'open_msg',
    'closed_msg',
    'open_form_msg',
];

wp_register_ability('novamira/formidable-edit-form-settings', [
    'label' => __('Edit Formidable Form Settings', domain: 'novamira-pro'),
    'description' => __(
        'Partial-merge edit of the `wp_frm_forms.options` blob (the Settings → General tab in the Formidable admin UI). Whitelisted keys: submit_value, submit_html, before_html, after_html, show_title, show_description, ajax_submit, antispam, js_validate, save_draft, editable, logged_in, no_save, open_status, open_date, open_msg, closed_msg, open_form_msg. The success_msg / success_action / success_url / success_page_id family has been migrated to the on-submit action in Formidable 6.0+ — write those via set-on-submit-action, not here. `custom_style` is REJECTED — use apply-style for style binding. Unknown keys are silently dropped.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'settings' => [
                'type' => 'object',
                'description' => 'Whitelisted form-options keys to merge. See description for the accepted list.',
            ],
        ],
        'required' => ['form_id', 'settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'changed' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Whitelisted keys actually applied to the options blob.',
            ],
            'dropped' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Caller-supplied keys silently dropped because they fell outside the whitelist (including custom_style — use apply-style instead).',
            ],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'changed', 'dropped', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_edit_form_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Edits the form's options blob (Settings → General). Whitelisted keys only — see description. `custom_style` is rejected (use apply-style). On-submit confirmation (success_msg/redirect/page) lives on the on-submit action in 6.0+ (use set-on-submit-action). The response reports `changed[]` (applied) and `dropped[]` (caller keys outside the whitelist) so the agent knows exactly what happened.",
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, settings?: array<string, mixed>} $input
 * @return array{form_id: int, changed: list<string>, dropped: list<string>, settings: array<string, mixed>}|WP_Error
 */
function formidable_edit_form_settings(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_change_settings');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $supplied = $input['settings'] ?? [];
    if ($supplied === []) {
        return frm_invalid_input('`settings` must be a non-empty object.');
    }

    [$merged, $changed, $dropped] = frm_filter_form_settings($supplied);

    if ($changed === []) {
        return frm_form_settings_response((int) $form->id, $changed, $dropped, frm_decode_form_options($form));
    }

    $current = frm_decode_form_options($form);
    $new_options = array_merge($current, $merged);

    $updated = FrmForm::update((int) $form->id, ['options' => $new_options]);
    if ($updated === false) {
        return frm_error(
            code: 'formidable_edit_settings_failed',
            message: "FrmForm::update returned false for form {$form->id}.",
        );
    }

    frm_invalidate_form_caches((int) $form->id);

    return frm_form_settings_response((int) $form->id, $changed, $dropped, $new_options);
}

/**
 * Filter caller-supplied settings against the whitelist. Returns:
 *  - $merged: keys to actually write
 *  - $changed: keys accepted (subset of input)
 *  - $dropped: keys rejected (outside whitelist OR explicitly blocked)
 *
 * @param array<string, mixed> $supplied
 * @return array{0: array<string, mixed>, 1: list<string>, 2: list<string>}
 */
function frm_filter_form_settings(array $supplied): array
{
    /** @var array<string, mixed> $merged */
    $merged = [];
    /** @var list<string> $changed */
    $changed = [];
    /** @var list<string> $dropped */
    $dropped = [];
    // @mago-expect analyzer:mixed-assignment
    foreach ($supplied as $key => $value) {
        if (!in_array($key, FRM_FORM_SETTINGS_KEYS, strict: true)) {
            $dropped[] = $key;
            continue;
        }
        $merged[$key] = $value;
        $changed[] = $key;
    }
    return [$merged, $changed, $dropped];
}

/**
 * Decode the form's options blob into an array. Returns [] when missing
 * or undecodable so callers don't have to nullable-branch.
 *
 * @return array<string, mixed>
 */
function frm_decode_form_options(\stdClass $form): array
{
    // FrmAppHelper::unserialize_or_decode is BY-REFERENCE and returns void —
    // it mutates the input. FrmForm::getOne already runs the decode on its
    // way back, so $form->options is usually an array; we cover the string
    // case for callers that read the row via a path that didn't decode.
    // @mago-expect analyzer:mixed-assignment
    $raw = $form->options ?? null;
    if (is_array($raw)) {
        return frm_filter_string_keyed($raw);
    }
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $raw */
    FrmAppHelper::unserialize_or_decode($raw);
    return is_array($raw) ? frm_filter_string_keyed($raw) : [];
}

/**
 * Keep only string-keyed entries from a generic array. Centralises the
 * type-narrowing shape the form-options decoder needs.
 *
 * @param array<array-key, mixed> $value
 * @return array<string, mixed>
 */
function frm_filter_string_keyed(array $value): array
{
    /** @var array<string, mixed> $out */
    $out = [];
    // @mago-expect analyzer:mixed-assignment
    foreach ($value as $k => $v) {
        if (!is_string($k)) {
            continue;
        }
        $out[$k] = $v;
    }
    return $out;
}

/**
 * Build the standard response envelope.
 *
 * @param list<string> $changed
 * @param list<string> $dropped
 * @param array<string, mixed> $settings
 * @return array{form_id: int, changed: list<string>, dropped: list<string>, settings: array<string, mixed>}
 */
function frm_form_settings_response(int $form_id, array $changed, array $dropped, array $settings): array
{
    return [
        'form_id' => $form_id,
        'changed' => $changed,
        'dropped' => $dropped,
        'settings' => $settings,
    ];
}
