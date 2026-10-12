<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ====================================================================
// get-form-settings — readonly fetch of the formSettings blob
// ====================================================================

wp_register_ability('novamira/fluentforms-get-form-settings', [
    'label' => __('Get Fluent Forms form settings', domain: 'novamira-pro'),
    'description' => __(
        "Fetch the formSettings blob for a form. Returns the decoded JSON object — the same payload the FF admin UI saves to wp_fluentform_form_meta with meta_key='formSettings'. Common keys: restrictions (max submissions, scheduling under restrictions.scheduleForm, login required under restrictions.requireLogin), layout (label placement, error display), submitButton (text, styling), confirmation (the success-page block — also readable via novamira/fluentforms-get-confirmation), and any add-on-specific keys. Returns an empty object when the form has no formSettings row yet. Required: form_id. Returns fluentforms_form_not_found (404) when the form is absent. Capability: fluentform_settings_manager (falls back to forms_manager).",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'settings'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_form_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the raw formSettings blob. Write with novamira/fluentforms-edit-form-settings (partial merge — never POST the whole blob unless you really mean a full replace).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, settings:array<array-key, mixed>}|WP_Error
 */
function fluentforms_get_form_settings(array $input): array|WP_Error
{
    $loaded = ff_settings_load_form_for_read($input['form_id'] ?? null);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    return [
        'form_id' => (int) $loaded->id,
        'settings' => ff_form_settings((int) $loaded->id),
    ];
}

// ====================================================================
// edit-form-settings — partial merge into formSettings (whitelisted keys)
// ====================================================================

wp_register_ability('novamira/fluentforms-edit-form-settings', [
    'label' => __('Edit Fluent Forms form settings', domain: 'novamira-pro'),
    'description' => __(
        'Partial-update the formSettings blob. The `settings` payload is shallow-merged into the stored blob — only whitelisted top-level keys are accepted (restrictions, layout, submitButton, conditionals); unknown keys land in warnings.dropped_keys. Pass `null` on a whitelisted key to remove it. Login-required and form scheduling are NOT top-level keys — they live under restrictions (restrictions.requireLogin, restrictions.scheduleForm). Confirmation is NOT mutable here — use novamira/fluentforms-edit-confirmation for that. Required: form_id, settings. Capability: fluentform_settings_manager (falls back to forms_manager). Returns the updated settings + warnings.dropped_keys.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'settings' => [
                'type' => 'object',
                'description' => 'Partial settings object. Top-level keys outside the whitelist are dropped (reported in warnings.dropped_keys). Pass null on a key to remove it.',
            ],
        ],
        'required' => ['form_id', 'settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'settings' => ['type' => 'object'],
            'warnings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'settings'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_edit_form_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Shallow partial merge with a top-level-key whitelist. Use this for layout / restrictions / submitButton; use edit-confirmation for the confirmation block. Capability: fluentform_settings_manager.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Top-level keys edit-form-settings is allowed to touch. Confirmation is
 * intentionally excluded — its own ability owns that surface.
 *
 * @return list<string>
 */
function ff_form_settings_whitelist(): array
{
    return ['restrictions', 'layout', 'submitButton', 'conditionals'];
}

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, settings:array<array-key, mixed>, warnings?:array{dropped_keys:list<string>}}|WP_Error
 */
function fluentforms_edit_form_settings(array $input): array|WP_Error
{
    $form = ff_settings_load_form_for_write($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    if (!isset($input['settings']) || !is_array($input['settings'])) {
        return ff_invalid('settings must be an object.');
    }
    $whitelist = array_flip(ff_form_settings_whitelist());
    $dropped = [];
    $rejected_types = [];
    $kept = [];
    /** @var mixed $value */
    foreach ($input['settings'] as $key => $value) {
        $key_str = (string) $key;
        if (!isset($whitelist[$key_str])) {
            $dropped[] = $key_str;
            continue;
        }
        // Round-3 audit CRITICAL #E: each whitelisted key has a strict
        // type contract. Without it restrictions/layout could be set to a
        // string and corrupt the blob.
        if (!ff_validate_form_setting_value($key_str, $value)) {
            $rejected_types[] = $key_str;
            continue;
        }
        $kept[$key_str] = $value;
    }
    if ($rejected_types !== []) {
        sort($rejected_types);
        return ff_invalid(sprintf('settings.{%s} have invalid types. Required: restrictions/layout/submitButton/conditionals=object.', implode(
            ',',
            $rejected_types,
        )));
    }

    // Read-modify-write the shared formSettings blob under a per-form lock so
    // a concurrent edit-confirmation does not lose this settings change.
    $current = ff_with_form_settings_lock((int) $form->id, static function () use ($form, $kept): array {
        $current = ff_form_settings((int) $form->id);
        /** @var mixed $value */
        foreach ($kept as $key => $value) {
            if ($value === null) {
                unset($current[$key]);
                continue;
            }
            $current[$key] = $value;
        }
        ff_set_form_meta((int) $form->id, key: 'formSettings', value: $current);
        return $current;
    });
    ff_after_form_save((int) $form->id, event: 'updated');
    ff_clear_form_cache((int) $form->id);

    $payload = ['form_id' => (int) $form->id, 'settings' => $current];
    if ($dropped !== []) {
        sort($dropped);
        $payload['warnings'] = ['dropped_keys' => $dropped];
    }
    return $payload;
}

// ff_settings_load_form_for_read / _for_write / ff_settings_resolve_with_caps
// live in helpers.php since audit round 3 — they are shared with
// manage-notifications.php and manage-confirmations.php so the helpers file
// is their canonical home.
