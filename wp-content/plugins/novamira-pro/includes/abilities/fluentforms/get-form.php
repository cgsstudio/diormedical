<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-get-form', [
    'label' => __('Get a Fluent Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Fetch one Fluent Forms form by id. Always returns id, title, status, type, has_payment, has_conversion, created_at, updated_at. The fields[] array, settings blob, notifications list, confirmation block, and meta_keys index are OPT-IN via the `include` parameter — progressive disclosure keeps the default payload small. Valid include values: "fields" (decoded form_fields.fields[] + submit_button), "settings" (decoded formSettings JSON), "notifications" (decoded notifications JSON), "confirmation" (the formSettings.confirmation sub-block), "meta_keys" (deduplicated list of meta_key column values for this form). Returns fluentforms_form_not_found (404) when the id is absent.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'description' => 'Numeric form id.',
                'minimum' => 1,
            ],
            'include' => [
                'type' => 'array',
                'description' => 'Sub-resources to materialise. Default [] returns only the compact form attributes.',
                'items' => [
                    'type' => 'string',
                    'enum' => ['fields', 'settings', 'notifications', 'confirmation', 'meta_keys'],
                ],
                'default' => [],
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
            'status' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'has_payment' => ['type' => 'boolean'],
            'has_conversion' => ['type' => 'boolean'],
            'created_at' => ['type' => ['string', 'null']],
            'updated_at' => ['type' => ['string', 'null']],
            'fields' => ['type' => ['array', 'null']],
            'submit_button' => ['type' => ['object', 'null']],
            'settings' => ['type' => ['object', 'null']],
            'notifications' => ['type' => ['array', 'null']],
            'confirmation' => ['type' => ['object', 'null']],
            'meta_keys' => ['type' => ['array', 'null']],
        ],
        'required' => [
            'id',
            'title',
            'status',
            'type',
            'has_payment',
            'has_conversion',
            'created_at',
            'updated_at',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always opt in to `include` for the sub-resources you actually need — the default payload is the compact attributes only, which keeps the agent context small for large forms. Note that fields[] in the response is REPLACE-semantics if you edit-form with it; for partial field-level mutations call add-field / edit-field / delete-field instead. settings is the raw formSettings blob (one big object); edit-form-settings is the safe write path. confirmation is a single block under formSettings.confirmation (FF has one confirmation per form, not many).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array<string, mixed>|WP_Error
 */
function fluentforms_get_form(array $input): array|WP_Error
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
        $cap_view = ff_capability_or_error('fluentform_entries_viewer', (int) $form->id);
        if ($cap_view instanceof WP_Error) {
            return $cap;
        }
    }

    $include = ff_normalize_include(
        isset($input['include']) && is_array($input['include']) ? array_values($input['include']) : [],
    );

    return ff_form_full($form, $include);
}

/**
 * Coerce the caller's `include[]` to a clean list<string> with only the
 * documented sub-resource names. Unknown values are silently dropped so a
 * minor typo doesn't fail the whole call.
 *
 * @param list<mixed> $raw
 *
 * @return list<string>
 */
function ff_normalize_include(array $raw): array
{
    $allowed = ['fields', 'settings', 'notifications', 'confirmation', 'meta_keys'];
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $value) {
        if (!is_string($value) || !in_array($value, $allowed, strict: true)) {
            continue;
        }
        if (in_array($value, $out, strict: true)) {
            continue;
        }
        $out[] = $value;
    }
    return $out;
}
