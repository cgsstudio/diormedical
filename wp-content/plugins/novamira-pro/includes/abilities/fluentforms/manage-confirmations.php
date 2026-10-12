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
// get-confirmation
// ====================================================================

wp_register_ability('novamira/fluentforms-get-confirmation', [
    'label' => __('Get Fluent Forms confirmation', domain: 'novamira-pro'),
    'description' => __(
        "Fetch the confirmation block for a form (what the user sees after submitting). FF stores ONE confirmation per form under formSettings.confirmation. Keys typically present: messageToShow (HTML success message), redirectTo ('samePage' | 'customPage' | 'customUrl'), customPage (page id when redirectTo=customPage), customUrl (URL when redirectTo=customUrl), samePageFormBehavior ('hide_form' | 'reset_form'). Returns an empty object when the form has no confirmation set yet. Required: form_id. Capability: fluentform_settings_manager.",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => ['form_id' => ['type' => 'integer', 'minimum' => 1]],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'confirmation' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'confirmation'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_confirmation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'A FF form has exactly ONE confirmation (not a list). Read with this ability, write with edit-confirmation.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, confirmation:array<array-key, mixed>}|WP_Error
 */
function fluentforms_get_confirmation(array $input): array|WP_Error
{
    $form = ff_settings_load_form_for_read($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $settings = ff_form_settings((int) $form->id);
    $confirmation = isset($settings['confirmation']) && is_array($settings['confirmation'])
        ? $settings['confirmation']
        : [];
    return ['form_id' => (int) $form->id, 'confirmation' => $confirmation];
}

// ====================================================================
// edit-confirmation
// ====================================================================

wp_register_ability('novamira/fluentforms-edit-confirmation', [
    'label' => __('Edit Fluent Forms confirmation', domain: 'novamira-pro'),
    'description' => __(
        'Partial-update the confirmation block. The `confirmation` payload is shallow-merged into formSettings.confirmation — only whitelisted keys are kept (messageToShow, redirectTo, customPage, customUrl, samePageFormBehavior); unknown keys land in warnings.dropped_keys. Pass `null` on a whitelisted key to remove it. When redirectTo=customUrl the customUrl must be a valid URL (esc_url_raw is applied). Required: form_id, confirmation. Capability: fluentform_settings_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'confirmation' => [
                'type' => 'object',
                'description' => 'Partial confirmation patch. Unknown top-level keys are dropped (warnings.dropped_keys).',
            ],
        ],
        'required' => ['form_id', 'confirmation'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'confirmation' => ['type' => 'object'],
            'warnings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'confirmation'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_edit_confirmation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Shallow merge with a whitelist. customUrl is URL-sanitised via esc_url_raw. Capability: fluentform_settings_manager.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Whitelisted top-level keys in the confirmation block.
 *
 * @return list<string>
 */
function ff_confirmation_whitelist(): array
{
    return ['messageToShow', 'redirectTo', 'customPage', 'customUrl', 'samePageFormBehavior'];
}

/**
 * @param array<array-key, mixed> $input
 *
 * @return array{form_id:int, confirmation:array<array-key, mixed>, warnings?:array{dropped_keys:list<string>}}|WP_Error
 */
function fluentforms_edit_confirmation(array $input): array|WP_Error
{
    $form = ff_settings_load_form_for_write($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    if (!isset($input['confirmation']) || !is_array($input['confirmation'])) {
        return ff_invalid('confirmation must be an object.');
    }
    [$kept, $dropped] = ff_filter_confirmation_patch($input['confirmation']);

    // Round-3 audit CRITICAL #E: per-key type guard.
    $rejected_types = [];
    /** @var mixed $value */
    foreach ($kept as $key => $value) {
        $key_str = (string) $key;
        if (ff_validate_confirmation_value($key_str, $value)) {
            continue;
        }
        $rejected_types[] = $key_str;
    }
    if ($rejected_types !== []) {
        sort($rejected_types);
        return ff_invalid(sprintf(
            'confirmation.{%s} have invalid types. Required: messageToShow/redirectTo/samePageFormBehavior/customUrl=string, customPage=int.',
            implode(',', $rejected_types),
        ));
    }

    // Read-modify-write the shared formSettings blob under a per-form lock so
    // a concurrent edit-form-settings does not lose this confirmation change.
    $form_id = (int) $form->id;
    $current = ff_with_form_settings_lock($form_id, static fn(): array|WP_Error => ff_persist_confirmation_patch(
        $form_id,
        $kept,
    ));
    if ($current instanceof WP_Error) {
        return $current;
    }
    ff_after_form_save((int) $form->id, event: 'updated');
    ff_clear_form_cache((int) $form->id);

    $payload = ['form_id' => (int) $form->id, 'confirmation' => $current];
    if ($dropped !== []) {
        $payload['warnings'] = ['dropped_keys' => $dropped];
    }
    return $payload;
}

/**
 * Merge the whitelisted confirmation patch into the stored formSettings and
 * persist it. Runs inside ff_with_form_settings_lock. Returns the new
 * confirmation block, or WP_Error when a customUrl fails the scheme whitelist.
 *
 * @param array<array-key, mixed> $kept
 *
 * @return array<array-key, mixed>|WP_Error
 */
function ff_persist_confirmation_patch(int $form_id, array $kept): array|WP_Error
{
    $settings = ff_form_settings($form_id);
    $current = isset($settings['confirmation']) && is_array($settings['confirmation']) ? $settings['confirmation'] : [];
    /** @var mixed $value */
    foreach ($kept as $key => $value) {
        if ($value === null) {
            unset($current[$key]);
            continue;
        }
        if ($key === 'customUrl' && is_string($value)) {
            // Round-3 audit CRITICAL #C: esc_url_raw does NOT reject
            // javascript: / data: / vbscript: schemes. ff_validate_url_safe
            // applies an http/https/mailto/tel scheme whitelist first.
            $safe = ff_validate_url_safe($value);
            if ($safe === '') {
                return ff_invalid('confirmation.customUrl must use http(s)/mailto/tel scheme.');
            }
            $current[$key] = $safe;
            continue;
        }
        $current[$key] = $value;
    }
    $settings['confirmation'] = $current;
    ff_set_form_meta($form_id, key: 'formSettings', value: $settings);
    return $current;
}

/**
 * Split a caller-supplied confirmation patch into the whitelisted keys
 * vs the dropped keys. Returns [$kept, $dropped] (dropped is sorted).
 *
 * @param array<array-key, mixed> $patch
 *
 * @return array{0: array<array-key, mixed>, 1: list<string>}
 */
function ff_filter_confirmation_patch(array $patch): array
{
    $whitelist = array_flip(ff_confirmation_whitelist());
    $kept = [];
    $dropped = [];
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        $key_str = (string) $key;
        if (!isset($whitelist[$key_str])) {
            $dropped[] = $key_str;
            continue;
        }
        $kept[$key_str] = $value;
    }
    sort($dropped);
    return [$kept, $dropped];
}
