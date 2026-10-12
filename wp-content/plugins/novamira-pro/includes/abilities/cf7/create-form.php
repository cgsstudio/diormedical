<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

use WPCF7_ContactForm;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-create-form', [
    'label' => __('Create Contact Form 7 Form', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Contact Form 7 form. Required: `title`. Optional: `slug` (CPT post_name — defaults to a slugified title), `status` (publish|draft, default publish), `locale` (e.g. "it" or "en_US"), `form` (template body using CF7 form-tag syntax — call cf7-get-tags-schema for the vocabulary), `mail` (primary mail config), `mail_2` (secondary mail config), `messages` (validation / mail / spam strings), `additional_settings` (newline-separated key:value pairs CF7 modules read — e.g. "demo_mode: on"). Properties left out fall back to the CF7 default template for the requested locale. WP-native aliases accepted: `post_title` ↔ `title`, `post_name` ↔ `slug`, `post_status` ↔ `status`. Returns the full form shape (same as cf7-get-form).',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'Form title shown in the CF7 admin list. Required.'],
            'post_title' => ['type' => 'string', 'description' => 'WordPress-native alias for `title`.'],
            'slug' => [
                'type' => 'string',
                'description' => 'CPT post_name. Optional — WordPress generates one from the title when omitted.',
            ],
            'post_name' => ['type' => 'string', 'description' => 'WordPress-native alias for `slug`.'],
            'status' => [
                'type' => 'string',
                'description' => 'CPT post_status. One of publish | draft. Defaults to publish.',
                'enum' => ['publish', 'draft'],
            ],
            'post_status' => [
                'type' => 'string',
                'description' => 'WordPress-native alias for `status`.',
                'enum' => ['publish', 'draft'],
            ],
            'locale' => [
                'type' => 'string',
                'description' => 'Form locale (e.g. "it", "en_US"). Drives the default message pack and the template returned by WPCF7_ContactForm::get_template(). Defaults to the site locale.',
            ],
            'form' => [
                'type' => 'string',
                'description' => 'Form template body using CF7 form-tag syntax. Call cf7-get-tags-schema for the vocabulary. When omitted, CF7 fills in the locale\'s default starter template.',
            ],
            'mail' => [
                'type' => 'object',
                'description' => 'Primary mail config. Keys: subject, sender, recipient, additional_headers, body, attachments, use_html (bool), exclude_blank (bool). Mail tags like [your-name] interpolate values from form fields with that name.',
            ],
            'mail_2' => [
                'type' => 'object',
                'description' => 'Secondary mail config (auto-reply). Same shape as `mail` plus `active` (bool — defaults false on a fresh form).',
            ],
            'messages' => [
                'type' => 'object',
                'description' => 'Map of message key -> string. Keys CF7 ships: validation_error, mail_sent_ok, mail_sent_ng, spam, accept_terms, invalid_required, invalid_too_long, invalid_too_short, etc. Module-supplied keys (akismet, reCAPTCHA) are accepted as-is.',
            ],
            'additional_settings' => [
                'type' => 'string',
                'description' => 'Newline-separated `key: value` pairs read by CF7 modules (e.g. "demo_mode: on", "skip_mail: on"). The agent passes this as a raw string — CF7 parses it on its own.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'form' => ['type' => 'object'],
            'error' => ['type' => 'string'],
            'error_code' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_create_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Workflow: optionally call cf7-get-tags-schema → pick the form-tag types you need → assemble the template → call this with the title + form template + mail config → call cf7-get-form on the returned id to confirm the round-trip. For minimal forms, only `title` is required and CF7 fills in a locale-default template. mail.recipient defaults to the site admin email when omitted.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function cf7_create_form(array $input): array
{
    $input = cf7_normalize_wp_aliases($input);

    // `locale` is not in cf7_property_keys() (CF7 6.x dropped it from
    // set_properties — round 1 audit P9) but it IS accepted by
    // create-form, so we whitelist it here.
    $stranger = cf7_reject_unknown_top_keys($input, ['locale']);
    if ($stranger !== null) {
        return ['success' => false, 'error' => $stranger, 'error_code' => 'cf7_invalid_input'];
    }

    // round 2 audit L2-A5: a `" "` (whitespace-only) title used to pass
    // the `=== ''` guard, then WordPress silently zeroed it out, leaving
    // the form saved with `success:true` but no visible label. Trim
    // before testing so empty-after-trim is rejected at the callback
    // boundary.
    if (!isset($input['title']) || !is_string($input['title']) || trim($input['title']) === '') {
        return [
            'success' => false,
            'error' => 'title is required and must be a non-empty string.',
            'error_code' => 'cf7_invalid_input',
        ];
    }
    // Strip NUL bytes — they survive wp_insert_post and can truncate
    // strings in C-string contexts (round 2 audit L2-A5 / L2-A8).
    $input['title'] = str_replace(search: "\x00", replace: '', subject: $input['title']);

    $locale = isset($input['locale']) && is_string($input['locale']) ? $input['locale'] : '';
    $locale_error = cf7_validate_locale($locale);
    if ($locale_error !== null) {
        return ['success' => false, 'error' => $locale_error, 'error_code' => 'cf7_invalid_locale'];
    }
    $template_opts = $locale !== '' ? ['locale' => $locale] : [];

    $form = WPCF7_ContactForm::get_template($template_opts);

    // Apply only the canonical CF7 property keys present in $input.
    // `locale` is NOT a managed property in CF7 6.x — it's already
    // handled by get_template($template_opts) above for fresh forms.
    $patch = cf7_extract_property_patch($input);
    $form->set_properties(cf7_apply_property_patch($form->get_properties(), $patch));

    $id = $form->save();
    if ($id === 0) {
        return ['success' => false, 'error' => 'CF7 form save failed.', 'error_code' => 'cf7_save_failed'];
    }

    // CPT post fields — title is required, slug + status are optional.
    cf7_apply_cpt_post_updates($id, $input);

    // Reload to surface the post_title / post_name / post_status the agent
    // just wrote (the in-memory $form is stale on those CPT fields).
    $reloaded = WPCF7_ContactForm::get_instance($id);
    if ($reloaded === null) {
        return [
            'success' => false,
            'error' => 'Created form id ' . $id . ' could not be reloaded.',
            'error_code' => 'cf7_save_failed',
        ];
    }

    return ['success' => true, 'form' => cf7_form_to_full($reloaded, cf7_default_max_field_bytes())];
}
