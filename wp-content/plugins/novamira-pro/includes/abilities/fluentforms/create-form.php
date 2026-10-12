<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use FluentForm\App\Models\Form;
use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-create-form', [
    'label' => __('Create a Fluent Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Create a new Fluent Forms form. Required: title (non-empty string). Optional: status ("published" | "unpublished", default "published"; an "unpublished" form renders as EMPTY output on the front end, with no message); type ("form" | "post", default "form"); fields[] — list of field descriptors using the agent-facing input shape ({type, label, required, name?, settings?}). Each field is run through ff_build_element so it lands as the native FF {element, attributes, settings, editor_options} structure. Returns the freshly-created form (compact shape) plus optional warnings.dropped_settings_per_field listing settings keys that fell outside the catalog whitelist for each field. Use novamira/fluentforms-get-field-types-schema first to discover available field types and supported settings.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'title' => [
                'type' => 'string',
                'description' => 'Form title (admin-visible). Required, non-empty after trim.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'unpublished'],
                'default' => 'published',
            ],
            'type' => [
                'type' => 'string',
                'enum' => ['form', 'post'],
                'default' => 'form',
            ],
            'fields' => [
                'type' => 'array',
                'description' => 'List of field descriptors in the agent-facing shape: {type, label?, required?, name?, settings?}. The submit button is auto-injected when omitted.',
                'items' => ['type' => 'object'],
                'default' => [],
            ],
        ],
        'required' => ['title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form' => ['type' => 'object'],
            'warnings' => ['type' => 'object'],
        ],
        'required' => ['form'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_create_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Build a form by passing fields[] in the friendly shape — the ability translates to FF\'s native nested element/attributes/settings format. Unknown settings keys are silently dropped (a warnings.dropped_settings_per_field map reports them). Pro-only field types (payment_method, repeater, …) are rejected with fluentforms_pro_field_not_available when Pro is absent. Capability: fluentform_forms_manager.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{form: array<string, mixed>, warnings?: array<string, mixed>}|WP_Error
 */
function fluentforms_create_form(array $input): array|WP_Error
{
    $guard = ff_create_form_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }
    $parsed = ff_create_form_parse_input($input);
    if ($parsed instanceof WP_Error) {
        return $parsed;
    }
    return ff_create_form_persist($parsed);
}

/**
 * Version + capability guard. Returns null on success.
 */
function ff_create_form_guard(): ?WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $cap = ff_capability_or_error('fluentform_forms_manager');
    return $cap instanceof WP_Error ? $cap : null;
}

/**
 * Normalise + validate caller input, returning the build-ready blob.
 *
 * @param array<string, mixed> $input
 *
 * @return array{title:string,status:string,type:string,fields:list<array<string,mixed>>,dropped:array<int,list<string>>,dropped_keys:array<int,list<string>>}|WP_Error
 */
function ff_create_form_parse_input(array $input): array|WP_Error
{
    $title = isset($input['title']) && is_string($input['title']) ? trim($input['title']) : '';
    if ($title === '') {
        return ff_invalid('title must be a non-empty string.');
    }
    $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : 'published';
    $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : 'form';
    $fields_input = isset($input['fields']) && is_array($input['fields']) ? array_values($input['fields']) : [];

    [$fields, $dropped, $rejected, $dropped_keys] = ff_normalize_input_fields($fields_input);
    if ($rejected !== []) {
        return $rejected[0];
    }
    return [
        'title' => $title,
        'status' => $status,
        'type' => $type,
        'fields' => $fields,
        'dropped' => $dropped,
        'dropped_keys' => $dropped_keys,
    ];
}

/**
 * Persist the form via Form::create() and emit the response envelope.
 *
 * @param array{title:string,status:string,type:string,fields:list<array<string,mixed>>,dropped:array<int,list<string>>,dropped_keys:array<int,list<string>>} $parsed
 *
 * @return array{form: array<string, mixed>, warnings?: array<string, mixed>}|WP_Error
 */
function ff_create_form_persist(array $parsed): array|WP_Error
{
    $blob = ff_encode_fields(['fields' => $parsed['fields'], 'submitButton' => ff_default_submit_button()]);
    if ($blob === null) {
        return ff_invalid(
            'Field data could not be encoded (a settings object is nested too deeply for JSON, past the 512-level cap). The form was not created.',
        );
    }
    try {
        $form = Form::create([
            'title' => $parsed['title'],
            'status' => $parsed['status'],
            'type' => $parsed['type'],
            'form_fields' => $blob,
            'has_payment' => 0,
            'has_conversion' => 0,
            'appearance_settings' => '',
            'conditions' => '',
            'created_by' => (string) get_current_user_id(),
        ]);
    } catch (Throwable $e) {
        return ff_operation_failed('Failed to create form: ' . $e->getMessage());
    }
    if ((int) $form->id <= 0) {
        return ff_operation_failed('Fluent Forms did not return a created form.');
    }

    ff_bootstrap_default_form_meta((int) $form->id);
    ff_after_form_save((int) $form->id, event: 'created');
    ff_clear_form_cache((int) $form->id);

    $warnings = [];
    if ($parsed['dropped'] !== []) {
        $warnings['dropped_settings_per_field'] = $parsed['dropped'];
    }
    if ($parsed['dropped_keys'] !== []) {
        $warnings['dropped_field_keys'] = $parsed['dropped_keys'];
    }
    if ($parsed['fields'] === []) {
        $warnings['empty_form'] = 'The form has no fields, so it renders as empty output on the front end. Pass at least one field.';
    }
    $payload = ['form' => ff_form_row($form)];
    if ($warnings !== []) {
        $payload['warnings'] = $warnings;
    }
    return $payload;
}

// ff_normalize_input_fields, ff_check_field_input, and ff_default_submit_button
// live in helpers.php since audit round 1 — they are shared with edit-form and
// manage-fields, so the helpers file is their canonical home.
