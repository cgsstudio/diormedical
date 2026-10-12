<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-edit-template', [
    'label' => __('Edit Oxygen Template', domain: 'novamira-pro'),
    'description' => __(
        'Patches an existing Oxygen template. Merge semantics on `settings`: only the top-level keys you pass are updated, omitted keys keep their current value (so you can patch `priority` without touching `ruleGroups`). `title` and `slug` are routed through wp_update_post. To replace the conditions wholesale use set-template-conditions instead. This ability is for everything that is NOT conditions. Two patches are GATED behind `allow_code_elements: true` even though they carry no code themselves, because they make the engine start running code the template ALREADY stores: clearing `settings.disabled` on a disabled template whose tree/ruleGroups contain an eval()\'d payload, and attaching a `settings.parentId` whose template does. Enabling or re-parenting a clean template needs no flag.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the template to edit.'],
            'post_id' => [
                'type' => 'integer',
                'description' => 'Alias of `id`, matching the element abilities. `id` wins when both are passed.',
            ],
            'title' => ['type' => 'string'],
            'post_title' => ['type' => 'string', 'description' => 'WordPress-native alias for `title`.'],
            'slug' => [
                'type' => 'string',
                'description' => 'URL slug for the template (WordPress-native alias for `post_name`).',
            ],
            'post_name' => ['type' => 'string', 'description' => 'WordPress-native alias for `slug`.'],
            'settings' => [
                'type' => 'object',
                'description' => 'Partial `_oxygen_template_settings` patch. Top-level keys in the patch are merged onto the stored settings (last write wins); omitted keys are preserved. To clear a key, pass it as null. The `type` key must be a registered template-type slug — discover the valid values via list-template-conditions (`template_types`).',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'description' => 'Explicit user approval to perform an edit that ARMS executable code already stored on a template — clearing `settings.disabled` on a template whose stored tree/ruleGroups contain an eval()\'d payload, or attaching a `settings.parentId` whose template does. The patch itself carries no code in that case, but the engine starts running the stored code as a result of it. Never set this on your own initiative: ask the user first, and only pass true after they explicitly confirm.',
            ],
        ],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['id']],
            ['required' => ['post_id']],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'template' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_edit_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge semantics on settings: only fields you pass are updated. Use set-template-conditions for the `ruleGroups` field; this ability does not touch conditions.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_edit_template(array $input): array|WP_Error
{
    $id = ox_template_id_arg($input);
    if ($id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'id (or post_id) must be a positive integer.',
            domain: 'novamira-pro',
        ));
    }
    // Route through ox_post_or_error so revisions / auto-drafts / trashed
    // rows are rejected uniformly — ox_load_template alone would accept
    // an auto-draft placeholder and silently clobber it.
    $post = ox_post_or_error($id, ox_template_post_types());
    if ($post instanceof WP_Error) {
        return $post;
    }

    // Validate the WHOLE request BEFORE any mutation. Otherwise a bad
    // settings.type (or a condition key that belongs to set-template-conditions)
    // would be rejected only AFTER the title/slug had already been persisted,
    // hiding a partial write behind an error response.
    $preflight = ox_edit_template_preflight($id, $input);
    if ($preflight instanceof WP_Error) {
        return $preflight;
    }

    // Snapshot BOTH mutable surfaces BEFORE any write: the post-row fields
    // (title/slug) AND the raw template-settings meta string. Either write can
    // fail (or a save_post listener can revert/mutate the settings during the
    // fields write), and a failure must leave NOTHING half-applied — so we can
    // restore both surfaces on any downstream error.
    /** @var mixed $before */
    $before = get_post($id);
    /** @var mixed $before_settings */
    $before_settings = ox_snapshot_template_settings_raw($id);

    $fields_error = ox_edit_template_write_fields($id, $input);
    if ($fields_error instanceof WP_Error) {
        $rollback_error = ox_rollback_template($id, $before, $before_settings);
        return $rollback_error ?? $fields_error;
    }
    // Observed-state on the post fields: wp_update_post can return the id while
    // an `wp_insert_post_data` / `save_post` hook keeps the OLD value, so the
    // truthy return is a declaration, not proof. Compare the persisted title to
    // the value WP would have stored (its own db-context sanitisation) and fail
    // when they differ. Slug is verified against WordPress's own canonical
    // sanitisation + uniqueness result, so a legitimate `-2` suffix passes but
    // a hook that retains/rewrites the old slug does not.
    $blocked = ox_template_field_write_blocked($id, $input);
    if ($blocked !== null) {
        $cause = ox_error('oxygen_write_not_applied', sprintf(
            /* translators: 1: field name, 2: post id */
            __(
                'The `%1$s` of template %2$d did not persist; a storage hook may have blocked, altered, or reverted the write.',
                domain: 'novamira-pro',
            ),
            $blocked,
            $id,
        ));
        $rollback_error = ox_rollback_template($id, $before, $before_settings);
        return $rollback_error ?? $cause;
    }

    if (isset($input['settings']) && is_array($input['settings'])) {
        /** @var array<string, mixed> $settings_patch */
        $settings_patch = $input['settings'];
        $err = ox_merge_template_settings_locked($id, $settings_patch);
        if ($err instanceof WP_Error) {
            // Restore BOTH surfaces — not just the title. The failed settings
            // lifecycle can leave the settings meta mutated by a save_post
            // listener (the reviewer's `priority: 777`), so a title-only
            // rollback would still leave altered settings behind the error.
            $rollback_error = ox_rollback_template($id, $before, $before_settings);
            return $rollback_error ?? $err;
        }
    }

    $fresh = ox_load_template($id);
    if ($fresh === null) {
        return ['success' => false, 'error' => 'Template saved but could not be re-loaded.'];
    }
    return ['success' => true, 'template' => ox_template_to_array($fresh)];
}

/**
 * Whole-request preflight for a settings patch, run BEFORE any mutation:
 *  1. the closed-schema validation (unknown key, wrong type, bad parent chain);
 *  2. the ARMING gate — a patch that carries no code itself but makes the engine
 *     start executing code this template (or a newly attached parent template)
 *     ALREADY stores. See ox_template_arming_payloads() for the two engine
 *     mechanisms and their citations. Without this, the very property the
 *     executable-payload gate exists to guarantee — "no agent write starts
 *     running operator code without explicit user approval" — is bypassable with
 *     a boolean.
 *
 * Returns the error to bail with, or null when the request is clean.
 *
 * @param array<string, mixed> $input
 */
function ox_edit_template_preflight(int $id, array $input): ?WP_Error
{
    if (!isset($input['settings']) || !is_array($input['settings'])) {
        return null;
    }
    $settings = $input['settings'];
    $settings_error = ox_validate_template_settings_input($settings, $id);
    if ($settings_error instanceof WP_Error) {
        return $settings_error;
    }
    $armed = ox_template_arming_payloads($id, $settings);
    if ($armed !== [] && !ox_coerce_bool($input['allow_code_elements'] ?? false)) {
        return ox_code_payload_block($armed);
    }
    return null;
}

/**
 * Restore both mutable template surfaces to a pre-write snapshot after a failed
 * edit, leaving no partial mutation behind the error. Order matters: restore the
 * post fields FIRST (via wp_update_post, which fires save_post — a listener may
 * re-mutate the settings there), THEN restore the settings LAST via a raw
 * set_meta that does NOT run the document-save lifecycle, so nothing can
 * re-corrupt them after the restore.
 *
 * @param mixed $before          the WP_Post snapshot (or a non-post value to skip)
 * @param mixed $before_settings the raw settings meta string captured before the write
 */
function ox_rollback_template(int $id, mixed $before, mixed $before_settings): ?WP_Error
{
    if ($before instanceof \WP_Post) {
        // get_post returns UNSLASHED data; wp_update_post unslashes AGAIN, so the
        // snapshot must be re-slashed or the rollback itself would strip a
        // backslash from the original title/slug it is meant to restore verbatim.
        wp_update_post([
            'ID' => $id,
            'post_title' => wp_slash($before->post_title),
            'post_name' => wp_slash($before->post_name),
        ]);
    }
    $settings_rollback = ox_restore_template_settings_snapshot($id, $before_settings);
    /** @var mixed $observed */
    $observed = get_post($id);
    $title_restored =
        $observed instanceof \WP_Post && $before instanceof \WP_Post && $observed->post_title === $before->post_title;
    $slug_restored =
        $observed instanceof \WP_Post && $before instanceof \WP_Post && $observed->post_name === $before->post_name;
    if ($title_restored && $slug_restored && $settings_rollback['restored']) {
        return null;
    }
    return ox_error(
        'oxygen_write_not_applied',
        sprintf(
            __(
                'The template edit failed and rollback was only partial. Retained title: `%1$s`; retained slug: `%2$s`; retained settings fingerprint: %3$s.',
                domain: 'novamira-pro',
            ),
            $observed instanceof \WP_Post ? $observed->post_title : '(post missing)',
            $observed instanceof \WP_Post ? $observed->post_name : '(post missing)',
            $settings_rollback['observed_fingerprint'],
        ),
        [
            'rollback' => [
                'restored' => false,
                'title' => $title_restored,
                'slug' => $slug_restored,
                'settings' => $settings_rollback['restored'],
            ],
        ],
    );
}

/**
 * Whether a requested post-field write did NOT take effect. Compares the
 * persisted `post_title` to the value WordPress itself would store for the
 * requested title (its own `db`-context sanitisation, which matches what
 * wp_update_post writes: trimming, kses, entity handling). Returns the field
 * name (`title` or `slug`) when the observed value differs from WordPress's
 * canonical expected value — else null.
 *
 * @param array<string, mixed> $input
 */
function ox_template_field_write_blocked(int $id, array $input): ?string
{
    /** @var mixed $title_raw */
    $title_raw = $input['post_title'] ?? $input['title'] ?? null;
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof \WP_Post) {
        return 'title';
    }
    if (is_string($title_raw)) {
        /** @var mixed $expected */
        $expected = sanitize_post_field('post_title', $title_raw, $id, context: 'db');
        if ($post->post_title !== $expected) {
            return 'title';
        }
    }
    /** @var mixed $slug_raw */
    $slug_raw = $input['post_name'] ?? $input['slug'] ?? null;
    if (!is_string($slug_raw) || $slug_raw === '') {
        return null;
    }
    $sanitized = sanitize_title($slug_raw);
    $expected_slug = wp_unique_post_slug($sanitized, $id, $post->post_status, $post->post_type, $post->post_parent);
    return $post->post_name === $expected_slug ? null : 'slug';
}

/**
 * Write the template's post-row fields (title / slug), accepting both the short
 * and WordPress-native aliases. Runs only after the whole request has already
 * been validated, so it never persists a partial edit for a request that will
 * be rejected downstream. Returns a WP_Error on a failed wp_update_post, else
 * null (including when there is nothing to write).
 *
 * @param array<string, mixed> $input
 */
function ox_edit_template_write_fields(int $id, array $input): ?WP_Error
{
    $post_update = ['ID' => $id];
    // wp_update_post EXPECTS slashed data (it wp_unslash-es internally), so the
    // raw title/slug must be wp_slash-ed first — otherwise a backslash in the
    // value is silently stripped on store (and the observed-title check would
    // then false-reject a legitimate `C:\path` / regex title).
    /** @var mixed $title_raw */
    $title_raw = $input['post_title'] ?? $input['title'] ?? null;
    if (is_string($title_raw)) {
        $post_update['post_title'] = wp_slash($title_raw);
    }
    /** @var mixed $slug_raw */
    $slug_raw = $input['post_name'] ?? $input['slug'] ?? null;
    if (is_string($slug_raw) && $slug_raw !== '') {
        if (sanitize_title($slug_raw) === '') {
            return ox_error('oxygen_invalid_input', __(
                '`slug` (or `post_name`) must contain at least one character WordPress can use in a URL slug.',
                domain: 'novamira-pro',
            ));
        }
        $post_update['post_name'] = wp_slash($slug_raw);
    }
    if (count($post_update) <= 1) {
        return null;
    }
    $r = wp_update_post($post_update, wp_error: true);
    if ($r instanceof WP_Error) {
        return ox_error('oxygen_invalid_input', 'wp_update_post failed: ' . $r->get_error_message());
    }
    return null;
}

/**
 * Read-modify-write of the per-post `_oxygen_template_settings`
 * postmeta under the per-post template-settings lock. Extracted so the
 * callback stays under the cyclomatic-complexity threshold and so
 * set-template-conditions can share the exact same lock slot key.
 *
 * @param array<string, mixed> $patch
 */
function ox_merge_template_settings_locked(int $id, array $patch): ?WP_Error
{
    $locked = ox_with_lock('template_settings_' . $id, static function () use ($id, $patch): WP_Error|bool {
        // When the patch changes the template type, validate it against the
        // live registry — an unknown type persists verbatim and never matches.
        if (array_key_exists('type', $patch)) {
            $type_error = ox_validate_template_type($patch['type']);
            if ($type_error instanceof WP_Error) {
                return $type_error;
            }
        }
        $existing = ox_read_template_settings($id);
        $merged = ox_merge_template_settings_patch($existing, $patch);
        $written = ox_write_template_settings($id, $merged);
        return $written instanceof WP_Error ? $written : true;
    });
    return $locked instanceof WP_Error ? $locked : null;
}
