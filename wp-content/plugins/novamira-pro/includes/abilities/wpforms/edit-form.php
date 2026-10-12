<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-edit-form', [
    'label' => __('Edit WPForms Form', domain: 'novamira-pro'),
    'description' => __(
        'Partially update a WPForms form. Required: form_id. Optional: title, description, status (publish | draft), and merge updates for settings, notifications, confirmations, and meta. Field management is handled by add-field / edit-field / delete-field — fields here is accepted only when explicitly provided and REPLACES the entire fields map (read with get-form first to avoid wiping unrelated fields).',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'WPForms form id to update.',
            ],
            'title' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'New form title. Also re-sets settings.form_title to match.',
            ],
            'description' => [
                'type' => 'string',
                'description' => 'New form description. Also re-sets settings.form_desc.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'New post_status.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Partial settings merge. Keys provided overwrite existing settings; keys omitted are preserved. notifications/confirmations live here too but are easier to write via their dedicated keys below.',
                'additionalProperties' => true,
            ],
            'notifications' => [
                'type' => 'object',
                'description' => 'Full notifications map (keyed by string id). REPLACES the existing notifications block — read with get-form first if you only want to amend one.',
                'additionalProperties' => true,
            ],
            'confirmations' => [
                'type' => 'object',
                'description' => 'Full confirmations map (keyed by string id). REPLACES the existing confirmations block.',
                'additionalProperties' => true,
            ],
            'fields' => [
                'type' => 'object',
                'description' => 'Full fields map (keyed by string id). REPLACES the existing fields. Prefer add-field / edit-field / delete-field for targeted changes.',
                'additionalProperties' => true,
            ],
            'meta' => [
                'type' => 'object',
                'description' => 'Partial meta merge (template marker, etc.).',
                'additionalProperties' => true,
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
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_modified' => ['type' => 'string'],
            'entry_count' => ['type' => ['integer', 'null']],
            'updated' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => [
            'id',
            'title',
            'slug',
            'status',
            'date_created',
            'date_modified',
            'entry_count',
            'updated',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_edit_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update — keys not in the payload are preserved. EXCEPTION: notifications, confirmations, and fields are full REPLACE writes when provided (their internal id-keyed maps cannot be safely merged). Read get-form first when amending those. The updated[] list reports which top-level keys were touched.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     title?: string,
 *     description?: string,
 *     status?: string,
 *     settings?: array<string, mixed>,
 *     notifications?: array<string, mixed>,
 *     confirmations?: array<string, mixed>,
 *     fields?: array<string, mixed>,
 *     meta?: array<string, mixed>,
 * } $input
 * @return array{id: int, title: string, slug: string, status: string, date_created: string, date_modified: string, entry_count: int|null, updated: list<string>}|WP_Error
 */
function wpforms_edit_form(array $input): array|WP_Error
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
    /** @var list<string> $updated */
    $updated = [];

    $post_update = wpforms_collect_post_changes($input, $form, $settings, $updated);
    $blob_error = wpforms_apply_blob_changes($input, $blob, $settings, $updated);
    if ($blob_error !== null) {
        return $blob_error;
    }
    $blob['settings'] = $settings;

    if ($updated === []) {
        return wpforms_form_changed_response($form, $updated);
    }

    // CAS guard token. wp_update_post() unconditionally bumps post_modified_gmt
    // — if we capture the timestamp before the post-row write and pass it to
    // the blob persist, the CAS WHERE clause matches zero rows whenever the
    // request crosses a MySQL-second boundary (sub-second smoke runs hide it).
    // Re-read AFTER the post-row write so the timestamp reflects the only
    // legitimate writer in this request's scope.
    $expected_modified_gmt = $form->post_modified_gmt;
    if ($post_update !== []) {
        $result = wpforms_apply_post_update((int) $form->ID, $post_update);
        if ($result instanceof WP_Error) {
            return $result;
        }
        /** @var mixed $refreshed_ts */
        $refreshed_ts = get_post_field('post_modified_gmt', (int) $form->ID);
        $expected_modified_gmt = is_string($refreshed_ts) ? $refreshed_ts : $form->post_modified_gmt;
    }

    $persisted = wpforms_persist_form((int) $form->ID, $blob, $expected_modified_gmt);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    /** @var \WP_Post|null $refreshed */
    $refreshed = get_post((int) $form->ID);
    if (!$refreshed instanceof \WP_Post) {
        return new WP_Error('wpforms_edit_failed', 'Updated form could not be read back.', ['status' => 500]);
    }

    return wpforms_form_changed_response($refreshed, $updated);
}

/**
 * Apply the post-row changes collected by wpforms_collect_post_changes(). The
 * map is intentionally narrow (only the keys we actually edit) so wp_update_post's
 * strict shape stays satisfied.
 *
 * @param array{post_title?: string, post_excerpt?: string, post_status?: string} $changes
 */
function wpforms_apply_post_update(int $form_id, array $changes): int|\WP_Error
{
    $payload = $changes;
    $payload['ID'] = $form_id;
    $result = wp_update_post($payload, wp_error: true);
    return $result instanceof WP_Error ? $result : (int) $result;
}

/**
 * Apply post-row level changes (title, description, status) from the input,
 * mirror them into the blob's settings, and record what changed. The returned
 * map is intentionally narrow (only WP-row keys) so wpforms_apply_post_update()
 * can hand it to wp_update_post without a shape mismatch.
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $settings
 * @param list<string>         $updated
 * @return array{post_title?: string, post_excerpt?: string, post_status?: string}
 */
function wpforms_collect_post_changes(array $input, \WP_Post $form, array &$settings, array &$updated): array
{
    $post_update = [];

    if (isset($input['title'])) {
        // Round-1 audit CRITICAL: same CRLF-injection guard as create-form —
        // sanitize before comparing so a CRLF-only change between the user
        // input and the stored title is also caught + neutralised.
        $title = sanitize_text_field(wpforms_strip_crlf((string) $input['title']));
        if ($title !== $form->post_title) {
            $post_update['post_title'] = $title;
            $settings['form_title'] = $title;
            $updated[] = 'title';
        }
    }
    if (isset($input['description'])) {
        $description = sanitize_text_field(wpforms_strip_crlf((string) $input['description']));
        if ($description !== $form->post_excerpt) {
            $post_update['post_excerpt'] = $description;
            $settings['form_desc'] = $description;
            $updated[] = 'description';
        }
    }
    if (isset($input['status']) && $input['status'] !== $form->post_status) {
        $post_update['post_status'] = (string) $input['status'];
        $updated[] = 'status';
    }

    return $post_update;
}

/**
 * Apply blob-level changes (settings merge, notifications/confirmations/fields
 * replace, meta merge) and record what changed.
 *
 * @param array<string, mixed>      $input
 * @param array<array-key, mixed>   $blob
 * @param array<array-key, mixed>   $settings
 * @param list<string>              $updated
 */
function wpforms_apply_blob_changes(array $input, array &$blob, array &$settings, array &$updated): ?WP_Error
{
    wpforms_apply_settings_change($input, $settings, $updated);
    wpforms_apply_notifications_change($input, $settings, $updated);
    wpforms_apply_confirmations_change($input, $settings, $updated);
    $fields_error = wpforms_apply_fields_change($input, $blob, $updated);
    if ($fields_error !== null) {
        return $fields_error;
    }
    wpforms_apply_meta_change($input, $blob, $updated);
    return null;
}

/**
 * Apply the settings.* merge, sanitising the nested notifications /
 * confirmations sub-blocks before merge.
 *
 * @param array<string, mixed>    $input
 * @param array<array-key, mixed> $settings
 * @param list<string>            $updated
 */
function wpforms_apply_settings_change(array $input, array &$settings, array &$updated): void
{
    if (!isset($input['settings']) || !is_array($input['settings'])) {
        return;
    }
    // Sanitize notifications/confirmations sub-blocks BEFORE the merge so
    // the same CRLF / email-header / wp_kses_post policy the dedicated
    // manage-* abilities enforce also covers the broader settings path.
    $settings_input = $input['settings'];
    if (isset($settings_input['notifications']) && is_array($settings_input['notifications'])) {
        $settings_input['notifications'] = wpforms_sanitize_notifications_map($settings_input['notifications']);
    }
    if (isset($settings_input['confirmations']) && is_array($settings_input['confirmations'])) {
        $settings_input['confirmations'] = wpforms_sanitize_confirmations_map($settings_input['confirmations']);
    }
    $settings = array_replace($settings, $settings_input);
    $updated[] = 'settings';
}

/**
 * Apply the top-level notifications replace.
 *
 * @param array<string, mixed>    $input
 * @param array<array-key, mixed> $settings
 * @param list<string>            $updated
 */
function wpforms_apply_notifications_change(array $input, array &$settings, array &$updated): void
{
    if (!isset($input['notifications']) || !is_array($input['notifications'])) {
        return;
    }
    $settings['notifications'] = wpforms_sanitize_notifications_map($input['notifications']);
    $updated[] = 'notifications';
}

/**
 * Apply the top-level confirmations replace.
 *
 * @param array<string, mixed>    $input
 * @param array<array-key, mixed> $settings
 * @param list<string>            $updated
 */
function wpforms_apply_confirmations_change(array $input, array &$settings, array &$updated): void
{
    if (!isset($input['confirmations']) || !is_array($input['confirmations'])) {
        return;
    }
    $settings['confirmations'] = wpforms_sanitize_confirmations_map($input['confirmations']);
    $updated[] = 'confirmations';
}

/**
 * Apply the fields replace — validated FIRST so a malformed map cannot
 * land in the blob (round-1 audit CRITICAL).
 *
 * @param array<string, mixed>    $input
 * @param array<array-key, mixed> $blob
 * @param list<string>            $updated
 */
function wpforms_apply_fields_change(array $input, array &$blob, array &$updated): ?WP_Error
{
    if (!isset($input['fields']) || !is_array($input['fields'])) {
        return null;
    }
    $validation = wpforms_validate_fields_map($input['fields']);
    if ($validation !== null) {
        return $validation;
    }
    $blob['fields'] = $input['fields'];
    $updated[] = 'fields';
    return null;
}

/**
 * Apply the meta.* merge (last-writer-wins per-key).
 *
 * @param array<string, mixed>    $input
 * @param array<array-key, mixed> $blob
 * @param list<string>            $updated
 */
function wpforms_apply_meta_change(array $input, array &$blob, array &$updated): void
{
    if (!isset($input['meta']) || !is_array($input['meta'])) {
        return;
    }
    $blob['meta'] = array_replace(wpforms_section($blob, key: 'meta'), $input['meta']);
    $updated[] = 'meta';
}

/**
 * Build the edit-form response row — the compact form row plus the list of
 * top-level keys that changed during this call.
 *
 * @param list<string> $updated
 * @return array{id: int, title: string, slug: string, status: string, date_created: string, date_modified: string, entry_count: int|null, updated: list<string>}
 */
function wpforms_form_changed_response(\WP_Post $form, array $updated): array
{
    $row = wpforms_form_row($form);
    $row['updated'] = $updated;
    return $row;
}
