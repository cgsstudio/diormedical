<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

use WP_Error;
use WP_Post;
use WPCF7_ContactForm;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-edit-form', [
    'label' => __('Edit Contact Form 7 Form', domain: 'novamira-pro'),
    'description' => __(
        'Merges new field values into an existing Contact Form 7 form identified by id. Only the keys you pass are changed — omitted keys keep their current value. For nested objects (`mail`, `mail_2`, `messages`) the merge is shallow per-key so you can patch only `mail.recipient` without losing the rest of the mail config. Use cf7-get-form first to read the current state, then call this with only the keys to change. WP-native aliases accepted: `post_title` ↔ `title`, `post_name` ↔ `slug`, `post_status` ↔ `status`. Returns the full form shape after the merge.',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the form to edit.'],
            'title' => ['type' => 'string'],
            'post_title' => ['type' => 'string', 'description' => 'WordPress-native alias for `title`.'],
            'slug' => ['type' => 'string'],
            'post_name' => ['type' => 'string', 'description' => 'WordPress-native alias for `slug`.'],
            'status' => ['type' => 'string', 'enum' => ['publish', 'draft']],
            'post_status' => [
                'type' => 'string',
                'description' => 'WordPress-native alias for `status`.',
                'enum' => ['publish', 'draft'],
            ],
            'locale' => ['type' => 'string'],
            'form' => [
                'type' => 'string',
                'description' => 'New template body. REPLACES the existing template (the merge for `form` is whole-string, not per-tag).',
            ],
            'mail' => [
                'type' => 'object',
                'description' => 'Patch for the primary mail config. Shallow merge per key — pass only the keys you want to change (e.g. `{"recipient":"new@example.com"}` leaves subject / body / sender intact).',
            ],
            'mail_2' => [
                'type' => 'object',
                'description' => 'Patch for the secondary mail config. Same semantics as `mail`.',
            ],
            'messages' => [
                'type' => 'object',
                'description' => 'Patch for the message strings. Shallow merge per key.',
            ],
            'additional_settings' => [
                'type' => 'string',
                'description' => 'Newline-separated key:value pairs. REPLACES the existing blob.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'form' => ['type' => 'object'],
            'error' => ['type' => 'string'],
            'error_code' => ['type' => 'string'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Non-fatal notices about a successful edit (a change-audit listener threw, or the write ran without the per-form concurrency lock). The edit still applied.',
            ],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_edit_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge semantics: only fields present in the input are updated. For `mail` / `mail_2` / `messages`, the merge is per inner key. For `form` and `additional_settings`, the whole string is replaced. Read first via cf7-get-form to see the current state. When changing the form template, remember that mail bodies reference field names via [your-name] — renaming a field without updating mail.body breaks the email.',
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
function cf7_edit_form(array $input): array
{
    $input = cf7_normalize_wp_aliases($input);

    $id = isset($input['id']) && is_int($input['id']) ? $input['id'] : 0;
    if ($id <= 0) {
        return [
            'success' => false,
            'error' => 'id must be a positive integer.',
            'error_code' => 'cf7_invalid_input',
        ];
    }

    $stranger = cf7_reject_unknown_top_keys($input, ['locale']);
    if ($stranger !== null) {
        return ['success' => false, 'error' => $stranger, 'error_code' => 'cf7_invalid_input'];
    }

    $locale_input = isset($input['locale']) && is_string($input['locale']) ? $input['locale'] : '';
    $locale_error = cf7_validate_locale($locale_input);
    if ($locale_error !== null) {
        return ['success' => false, 'error' => $locale_error, 'error_code' => 'cf7_invalid_locale'];
    }

    // Read-modify-write must run under the form-id advisory lock —
    // without it, parallel edit calls produce deterministic lost writes
    // because WPCF7_ContactForm::save() persists ALL six postmeta keys
    // from the in-memory snapshot, so concurrent edits on different
    // fields still overwrite each other (round 1 audit P3).
    return cf7_with_form_lock($id, static fn(): array => cf7_perform_edit_form_rmw($id, $input));
}

/**
 * Read-modify-write body executed inside the form-id advisory lock.
 * Extracted from `cf7_edit_form` to keep that function within the
 * project's complexity budget (round 2 audit refactor following
 * cf7_emit_form_change_audit / cf7_sanitize_mail_array additions).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function cf7_perform_edit_form_rmw(int $id, array $input): array
{
    $form = cf7_form_or_error($id);
    if ($form instanceof WP_Error) {
        return cf7_failure_from_wp_error($form);
    }

    // Snapshot the mail-sensitive fields BEFORE save so we can fire a
    // delta-aware audit hook (round 2 audit L3-A6 / L5-A6 — CF7 does
    // not provide a native before/after diff on edit).
    /** @var mixed $before_mail_raw */
    $before_mail_raw = $form->prop('mail');
    /** @var mixed $before_mail_2_raw */
    $before_mail_2_raw = $form->prop('mail_2');
    $before_mail = is_array($before_mail_raw) ? $before_mail_raw : [];
    $before_mail_2 = is_array($before_mail_2_raw) ? $before_mail_2_raw : [];

    // Capture the original post_status BEFORE save(): CF7's save()
    // hardcodes `post_status => 'publish'` (round 1 audit P2).
    /** @var WP_Post|null $original_post */
    $original_post = get_post($id);
    $original_status = $original_post instanceof WP_Post ? $original_post->post_status : 'publish';

    // Merge the canonical CF7 properties first.
    $patch = cf7_extract_property_patch($input);
    $locale_changed = cf7_apply_locale_change($form, $input);
    $form_saved = false;
    if ($patch !== [] || $locale_changed) {
        $form->set_properties(cf7_apply_property_patch($form->get_properties(), $patch));
        if ($form->save() === 0) {
            return ['success' => false, 'error' => 'CF7 form save failed.', 'error_code' => 'cf7_save_failed'];
        }
        $form_saved = true;
    }

    // CPT-level field updates (title / slug / status). The status
    // restoration injection ONLY makes sense when save() ran; on a
    // true no-op edit skip wp_update_post entirely (round 2 audit
    // L2-A6 / L4-A6).
    $cpt_input = $input;
    if ($form_saved && !array_key_exists('status', $cpt_input)) {
        $cpt_input['status'] = $original_status;
    }
    cf7_apply_cpt_post_updates($id, $cpt_input);

    $reloaded = WPCF7_ContactForm::get_instance($id);
    if ($reloaded === null) {
        return [
            'success' => false,
            'error' => sprintf('Form id %d could not be reloaded after edit.', $id),
            'error_code' => 'cf7_form_not_found',
        ];
    }

    $response = ['success' => true, 'form' => cf7_form_to_full($reloaded, cf7_default_max_field_bytes())];

    if ($form_saved) {
        $audit_warning = cf7_emit_form_change_audit($id, $before_mail, $before_mail_2, $reloaded);
        if ($audit_warning !== null) {
            $response = cf7_append_warning($response, $audit_warning);
        }
    }

    return $response;
}
