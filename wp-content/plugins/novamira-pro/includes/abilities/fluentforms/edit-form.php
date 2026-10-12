<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-edit-form', [
    'label' => __('Edit a Fluent Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Partial-update a Fluent Forms form. Required: form_id. Mutable attributes (all optional, omitted means "leave as-is"): title, status, type, fields[]. fields[] is REPLACE-semantics (passing it overwrites the entire form_fields.fields[] list; the submit button and other stored form_fields keys such as a multi-step stepsWrapper are kept) — for partial field-level changes use novamira/fluentforms-add-field / edit-field / delete-field instead. Returns the updated compact form plus optional warnings.dropped_settings_per_field. Returns fluentforms_form_not_found (404) for missing ids, fluentforms_invalid_input (400) for bad shapes.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['published', 'unpublished']],
            'type' => ['type' => 'string', 'enum' => ['form', 'post']],
            'fields' => [
                'type' => 'array',
                'description' => 'REPLACES the entire fields[] array. Pass omit (do not include the key) to keep existing fields. Use add-field/edit-field/delete-field for incremental changes.',
                'items' => ['type' => 'object'],
            ],
        ],
        'required' => ['form_id'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_edit_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update — only keys present in the input are touched. The single exception is fields[] which is REPLACE-semantics: passing it OVERWRITES every existing field. For incremental field mutations use add-field/edit-field/delete-field. Renaming a field via this path will orphan existing submissions that reference the old name. Capability: fluentform_forms_manager.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{form: array<string, mixed>, warnings?: array<string, mixed>}|WP_Error
 */
function fluentforms_edit_form(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $form = ff_form_or_error($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = ff_capability_or_error('fluentform_forms_manager', (int) $form->id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $patch = ff_collect_edit_form_patch($input);
    if ($patch instanceof WP_Error) {
        return $patch;
    }

    $fields_outcome = ff_edit_form_apply_fields($form, $patch);
    if ($fields_outcome instanceof WP_Error) {
        return $fields_outcome;
    }
    ff_edit_form_apply_scalars($form, $patch);

    try {
        $form->save();
    } catch (Throwable $e) {
        return ff_operation_failed('Failed to save form: ' . $e->getMessage());
    }

    ff_bootstrap_default_form_meta((int) $form->id);
    ff_after_form_save((int) $form->id, event: 'updated');
    ff_clear_form_cache((int) $form->id);

    $payload = ['form' => ff_form_row($form)];
    if ($fields_outcome !== []) {
        $payload['warnings'] = ['dropped_settings_per_field' => $fields_outcome];
    }
    return $payload;
}

/**
 * Apply the fields[] portion of the patch to the form's form_fields blob.
 * Returns the dropped-settings map (possibly empty), or a WP_Error if the
 * caller-supplied fields[] contains an invalid descriptor.
 *
 * @param array<string, mixed> $patch
 *
 * @return array<int, list<string>>|WP_Error
 */
function ff_edit_form_apply_fields(\FluentForm\App\Models\Form $form, array $patch): array|WP_Error
{
    if (!array_key_exists('fields', $patch)) {
        return [];
    }
    $fields_in = is_array($patch['fields']) ? array_values($patch['fields']) : [];
    $built = ff_normalize_input_fields($fields_in);
    if ($built[2] !== []) {
        return $built[2][0];
    }
    // Replace fields[] only; the blob's other top-level keys (stepsWrapper, …)
    // are carried over from the stored form.
    $blob = ff_decode_fields(ff_str($form->form_fields));
    $blob['fields'] = $built[0];
    if ($blob['submitButton'] === []) {
        $blob['submitButton'] = ff_default_submit_button();
    }
    $encoded = ff_encode_fields($blob);
    if ($encoded === null) {
        return ff_invalid(
            'Field data could not be encoded (a settings object is nested too deeply for JSON, past the 512-level cap). No changes were saved.',
        );
    }
    $form->form_fields = $encoded;
    return $built[1];
}

/**
 * Apply the scalar fields (title/status/type) from the patch to the model.
 * No-op for keys absent from the patch; type-guards prevent a stray non-
 * string from corrupting the row.
 *
 * @param array<string, mixed> $patch
 */
function ff_edit_form_apply_scalars(\FluentForm\App\Models\Form $form, array $patch): void
{
    if (array_key_exists('title', $patch) && is_string($patch['title'])) {
        $form->title = $patch['title'];
    }
    if (array_key_exists('status', $patch) && is_string($patch['status'])) {
        $form->status = $patch['status'];
    }
    if (array_key_exists('type', $patch) && is_string($patch['type'])) {
        $form->type = $patch['type'];
    }
}

/**
 * Build the patch dict from caller input, validating types and rejecting
 * empty titles. Returns the patch map OR a WP_Error on the first invalid
 * value.
 *
 * @param array<string, mixed> $input
 *
 * @return array<string, mixed>|WP_Error
 */
function ff_collect_edit_form_patch(array $input): array|WP_Error
{
    $patch = [];
    if (array_key_exists('title', $input)) {
        if (!is_string($input['title'])) {
            return ff_invalid('title must be a string.');
        }
        $title = trim($input['title']);
        if ($title === '') {
            return ff_invalid('title cannot be empty.');
        }
        $patch['title'] = $title;
    }
    if (array_key_exists('status', $input)) {
        if (!is_string($input['status'])) {
            return ff_invalid('status must be a string.');
        }
        $patch['status'] = $input['status'];
    }
    if (array_key_exists('type', $input)) {
        if (!is_string($input['type'])) {
            return ff_invalid('type must be a string.');
        }
        $patch['type'] = $input['type'];
    }
    if (array_key_exists('fields', $input)) {
        if (!is_array($input['fields'])) {
            return ff_invalid('fields must be an array.');
        }
        $patch['fields'] = array_values($input['fields']);
    }
    return $patch;
}
