<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-edit-form', [
    'label' => __('Edit a Ninja Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Edit the FORM-LEVEL settings of an existing Ninja Forms form — a shallow merge: only the keys you pass are touched, the rest are preserved. Pass `title` to rename, and/or `settings` (an object of form-level setting keys, e.g. {default_label_pos, show_title, clear_complete, success message keys}). This ability does NOT touch fields or actions — use ninja-forms-add-field / edit-field / delete-field for fields and ninja-forms-create-notification / edit-notification for notifications; passing a `fields` or `actions` key here is rejected with ninja_forms_invalid_input. Returns the updated compact form + the list of changed setting keys. Returns ninja_forms_form_not_found (404) when the id is absent.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'title' => ['type' => 'string', 'description' => 'New form title.'],
            'settings' => ['type' => 'object', 'description' => 'Form-level settings to merge.'],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['form', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_edit_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Form-level settings only — shallow merge, re-running with the same values is a no-op (idempotent). Concurrency: there is NO lock, and Ninja\'s model rewrites the whole settings row on save, so two edits to the SAME form racing in parallel are last-write-wins on the ENTIRE settings blob (the later save can drop the earlier edit, even on a different key). Fine for a single-operator agent; serialise edits if you run them concurrently. Field / action edits go through the dedicated abilities. Capability: manage_options.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int, title?: string, settings?: array<string, mixed>} $input
 *
 * @return array{form: array<string, mixed>, changed: list<string>}|WP_Error
 */
function ninja_forms_edit_form(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    // `fields` / `actions` keys are rejected by the input schema
    // (additionalProperties:false) before this callback runs — field and
    // action edits go through the dedicated ninja-forms-*-field /
    // *-notification abilities.
    $form = ninja_forms_form_or_error($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $form_id = (int) $form->get_id();

    // Assemble the patch: top-level `title` + the `settings` object. The
    // settings pass through ninja_forms_string_keyed, which strips the
    // model-managed keys (id / parent_id / created_at / updated_at) so a
    // caller can't clobber the row identity or our timestamps.
    $patch = ninja_forms_string_keyed($input['settings'] ?? null);
    if (isset($input['title'])) {
        $patch['title'] = trim(ninja_forms_str($input['title']));
    }
    if ($patch === []) {
        return ninja_forms_invalid('nothing to edit — pass `title` and/or `settings`.');
    }

    $current = $form->get_settings();
    /** @var list<string> $changed */
    $changed = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($patch as $key => $value) {
        if (array_key_exists($key, $current) && ninja_forms_setting_equal($current[$key], $value)) {
            continue;
        }
        $changed[] = $key;
    }

    if ($changed !== []) {
        $form->update_settings($patch);
        $form->update_setting('updated_at', current_time('mysql'));
        $form->save();
        ninja_forms_bust_cache($form_id);
    }

    return [
        'form' => ninja_forms_form_compact($form_id),
        'changed' => $changed,
    ];
}
