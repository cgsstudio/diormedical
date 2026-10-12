<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-edit-form-settings', [
    'label' => __('Edit WPForms Form Settings', domain: 'novamira-pro'),
    'description' => __(
        'Sharp partial-merge editor for the most-used form-level settings on a single form. Use this instead of edit-form.settings when you only need to tweak common knobs: submit button text, anti-spam toggle, AJAX submission, store-spam-entries, honeypot, dynamic population, form CSS class. Each input field has a typed schema so the agent gets clear validation up front. For anything beyond these keys, fall back to edit-form with a settings merge.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Target form id.'],
            'submit_text' => [
                'type' => 'string',
                'description' => 'Submit button label (e.g. "Send", "Get a Quote").',
            ],
            'submit_text_processing' => [
                'type' => 'string',
                'description' => 'Label shown while the submission is in flight (e.g. "Sending...").',
            ],
            'form_class' => [
                'type' => 'string',
                'description' => 'Extra CSS classes added to the <form> wrapper.',
            ],
            'anti_spam' => [
                'type' => 'boolean',
                'description' => 'Enable the modern anti-spam token check (default in new forms).',
            ],
            'honeypot' => [
                'type' => 'boolean',
                'description' => 'Enable the legacy honeypot field. anti_spam already covers most bots; only flip this on if you also need the honeypot.',
            ],
            'ajax_submit' => [
                'type' => 'boolean',
                'description' => 'Submit without a full page reload.',
            ],
            'dynamic_population' => [
                'type' => 'boolean',
                'description' => 'Allow URL query-string parameters to pre-fill matching fields.',
            ],
            'store_spam_entries' => [
                'type' => 'boolean',
                'description' => 'Persist entries flagged as spam (Pro only — has no effect on Lite).',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'updated' => ['type' => 'array', 'items' => ['type' => 'string']],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'updated', 'settings'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_edit_form_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial merge of common form-level settings — pass only the knobs you want to change. updated[] reports the keys that actually changed; empty list means a no-op. Boolean keys are stored internally as the "0" / "1" strings WPForms expects but accepted as actual booleans here.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     submit_text?: string,
 *     submit_text_processing?: string,
 *     form_class?: string,
 *     anti_spam?: bool,
 *     honeypot?: bool,
 *     ajax_submit?: bool,
 *     dynamic_population?: bool,
 *     store_spam_entries?: bool,
 * } $input
 * @return array{form_id: int, updated: list<string>, settings: array<string, mixed>}|WP_Error
 */
function wpforms_edit_form_settings(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_edit_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $blob = wpforms_decode_blob($form);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    /** @var array<string, mixed> $settings */
    $settings = wpforms_section($blob, key: 'settings');
    $updated = wpforms_apply_settings_strings($settings, $input);
    $updated = [...$updated, ...wpforms_apply_settings_bools($settings, $input)];

    if ($updated === []) {
        return [
            'form_id' => (int) $form->ID,
            'updated' => [],
            'settings' => $settings,
        ];
    }

    $blob['settings'] = $settings;
    $persisted = wpforms_persist_form((int) $form->ID, $blob, $form->post_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return [
        'form_id' => (int) $form->ID,
        'updated' => $updated,
        'settings' => $settings,
    ];
}

/**
 * Apply the string-typed settings inputs (submit_text, submit_text_processing,
 * form_class) and return the touched keys.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 * @return list<string>
 */
function wpforms_apply_settings_strings(array &$settings, array $input): array
{
    /** @var list<string> $touched */
    $touched = [];
    foreach (['submit_text', 'submit_text_processing', 'form_class'] as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $next = (string) $input[$key];
        if (($settings[$key] ?? null) === $next) {
            continue;
        }
        $settings[$key] = $next;
        $touched[] = $key;
    }
    return $touched;
}

/**
 * Apply the boolean-typed settings inputs (anti_spam, honeypot, ajax_submit,
 * dynamic_population, store_spam_entries) coerced to the "0"/"1" strings
 * WPForms persists, and return the touched keys.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 * @return list<string>
 */
function wpforms_apply_settings_bools(array &$settings, array $input): array
{
    /** @var list<string> $touched */
    $touched = [];
    foreach (['anti_spam', 'honeypot', 'ajax_submit', 'dynamic_population', 'store_spam_entries'] as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $coerced = wpforms_bool_string($input[$key]);
        if (($settings[$key] ?? null) === $coerced) {
            continue;
        }
        $settings[$key] = $coerced;
        $touched[] = $key;
    }
    return $touched;
}
