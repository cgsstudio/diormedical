<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-duplicate-form', [
    'label' => __('Duplicate a Ninja Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Duplicate an existing Ninja Forms form — copies the form settings, every field, and every action into a brand-new form (submissions are NOT copied). Uses Ninja Forms\' own synchronous duplication, so the copy is complete and immediately readable. By default the new title is "<original> - copy"; pass `new_title` to set it explicitly. Returns the new form as a compact row. Returns ninja_forms_form_not_found (404) when the source id is absent.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Source form id to copy.'],
            'new_title' => ['type' => 'string', 'description' => 'Title for the copy (default "<original> - copy").'],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form' => ['type' => 'object'],
            'duplicated_from' => ['type' => 'integer'],
        ],
        'required' => ['form', 'duplicated_from'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_duplicate_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Copies settings + fields + actions into a new form (not submissions). Pass new_title to name the copy. Capability: manage_options.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{form_id?: int, new_title?: string} $input
 *
 * @return array{form: array<string, mixed>, duplicated_from: int}|WP_Error
 */
function ninja_forms_duplicate_form(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $source = ninja_forms_form_or_error($input['form_id'] ?? null);
    if ($source instanceof WP_Error) {
        return $source;
    }
    $source_id = (int) $source->get_id();

    $new_id = ninja_forms_pos_int(\NF_Database_Models_Form::duplicate($source_id));
    if ($new_id <= 0) {
        return ninja_forms_error(
            'ninja_forms_duplicate_failed',
            message: 'Ninja Forms did not return a new form id for the copy.',
        );
    }

    $new_title = isset($input['new_title']) ? trim(ninja_forms_str($input['new_title'])) : '';
    if ($new_title !== '') {
        $copy = \Ninja_Forms()->form($new_id)->get();
        $copy->update_setting('title', $new_title);
        $copy->save();
    }
    // Form::duplicate() copies the row but leaves the `key` slug column empty
    // (and a renamed copy needs a fresh slug anyway) — backfill it from the
    // copy's final title so the duplicate has the same stable handle every
    // form-created-by-us gets.
    $copy_title = $new_title !== '' ? $new_title : ninja_forms_form_columns($new_id)['title'];
    ninja_forms_set_form_key($new_id, $copy_title);
    ninja_forms_bust_cache($new_id);

    return [
        'form' => ninja_forms_form_compact($new_id),
        'duplicated_from' => $source_id,
    ];
}
