<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-edit-template', [
    'label' => __('Edit Breakdance Template', domain: 'novamira-pro'),
    'description' => __(
        'Patches an existing Breakdance template. Merge semantics on `settings`: only the top-level keys you pass are updated, omitted keys keep their current value (so you can patch `priority` without touching `ruleGroups`). `title` and `status` are routed through wp_update_post. To replace the conditions wholesale use set-template-conditions instead. This ability is for everything that is NOT conditions.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the template to edit.'],
            'post_id' => [
                'type' => 'integer',
                'description' => 'Alias of `id`, matching the element abilities. `id` wins when both are passed.',
            ],
            'title' => ['type' => 'string'],
            'slug' => [
                'type' => 'string',
                'description' => 'URL slug for the template (WordPress-native alias for `post_name`).',
            ],
            'post_name' => ['type' => 'string', 'description' => 'WordPress-native alias for `slug`.'],
            'settings' => [
                'type' => 'object',
                'description' => 'Partial `_breakdance_template_settings` patch. Top-level keys in the patch are merged onto the stored settings (last write wins); omitted keys are preserved. To clear a key, pass it as null. The `type` key must be a registered template-type slug — discover the valid values via list-template-conditions (`template_types`); for a single template targeting a custom post type it is that post type\'s slug (e.g. `location`).',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_edit_template',
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
function breakdance_edit_template(array $input): array|WP_Error
{
    $id = bd_template_id_arg($input);
    if ($id <= 0) {
        return bd_error('bd_invalid_input', __('id (or post_id) must be a positive integer.', domain: 'novamira-pro'));
    }
    // Round 11 B10 — `bd_load_template` accepts auto-draft / revision
    // placeholders, so editing one would silently clobber a WP-managed
    // intermediate post. Route the write path through `bd_post_or_error`
    // which rejects every placeholder uniformly.
    $post = bd_post_or_error($id, bd_template_post_types());
    if ($post instanceof WP_Error) {
        return $post;
    }

    $post_update = ['ID' => $id];
    if (isset($input['title']) && is_string($input['title'])) {
        $post_update['post_title'] = $input['title'];
    }
    // Slug + WordPress-native `post_name` alias.
    /** @var mixed $slug_raw */
    $slug_raw = $input['post_name'] ?? $input['slug'] ?? null;
    if (is_string($slug_raw) && $slug_raw !== '') {
        $post_update['post_name'] = $slug_raw;
    }
    if (count($post_update) > 1) {
        $r = wp_update_post($post_update, wp_error: true);
        if ($r instanceof \WP_Error) {
            return ['success' => false, 'error' => 'wp_update_post failed: ' . $r->get_error_message()];
        }
    }

    if (isset($input['settings']) && is_array($input['settings'])) {
        /** @var array<string, mixed> $settings_patch */
        $settings_patch = $input['settings'];
        $err = bd_merge_template_settings_locked($id, $settings_patch);
        if ($err instanceof WP_Error) {
            return $err;
        }
    }

    $fresh = bd_load_template($id);
    if ($fresh === null) {
        return ['success' => false, 'error' => 'Template saved but could not be re-loaded.'];
    }
    return ['success' => true, 'template' => bd_template_to_array($fresh)];
}

/**
 * Read-modify-write of the per-post `_breakdance_template_settings`
 * postmeta under the per-post template-settings lock. Extracted from
 * `breakdance_edit_template` so the callback stays under the mago
 * cyclomatic-complexity threshold after the Round 11 B6 lock + B10
 * trash-recheck additions.
 *
 * @param array<string, mixed> $patch
 */
function bd_merge_template_settings_locked(int $id, array $patch): ?WP_Error
{
    $locked = bd_with_lock('template_settings_' . $id, static function () use ($id, $patch): WP_Error|bool {
        $existing = bd_read_template_settings($id);
        $merged = array_merge($existing, $patch);
        $written = bd_write_template_settings($id, $merged);
        return $written instanceof WP_Error ? $written : true;
    });
    return $locked instanceof WP_Error ? $locked : null;
}
