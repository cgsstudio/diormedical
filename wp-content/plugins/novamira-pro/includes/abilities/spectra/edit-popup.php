<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-edit-popup', [
    'label' => __('Edit Spectra Popup', domain: 'novamira-pro'),
    'description' => __(
        'Merge-update a Spectra Popup. Every field is optional — only the keys the caller passes are written. Accepts short names (title, content, status, slug) or WP-native aliases (post_title, post_content, post_status, post_name); passing both aliases of the same field returns spectra_alias_conflict. Meta subset (popup_type, enabled, repetition) also mergeable. The post cache is flushed after the write so a follow-up get-popup sees fresh values.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'title' => ['type' => 'string'],
            'post_title' => ['type' => 'string'],
            'content' => ['type' => 'string'],
            'post_content' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'publish', 'private', 'pending']],
            'post_status' => ['type' => 'string', 'enum' => ['draft', 'publish', 'private', 'pending']],
            'slug' => ['type' => 'string'],
            'post_name' => ['type' => 'string'],
            'popup_type' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'repetition' => ['type' => 'integer', 'minimum' => 0],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'popup_type' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'repetition' => ['type' => 'integer'],
            'modified' => ['type' => 'string'],
        ],
        'required' => ['id', 'title', 'slug', 'status', 'popup_type', 'enabled', 'repetition', 'modified'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_edit_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial merge update; only fields present in the payload are written. Requires manage_options.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_edit_popup(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $id = spectra_coerce_int($input['id'] ?? 0, minimum: 0);
    $post = sp_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    foreach (['title', 'content', 'status', 'slug'] as $alias_short) {
        if (sp_has_alias_conflict($input, short: $alias_short)) {
            return sp_alias_conflict($alias_short);
        }
    }

    // A caller that passes `enabled` MUST pass a real boolean — silent
    // skip on `enabled: 'true'` would surprise the agent.
    if (array_key_exists('enabled', $input) && !is_bool($input['enabled'])) {
        return sp_invalid_input('enabled must be a boolean (true or false)');
    }

    $post_updates = spectra_edit_popup_post_updates($input, $post->ID);
    if ($post_updates !== []) {
        // wp_update_post declares a strict shape but our payload keys are
        // whitelisted to the WP-native names, all of which belong to the
        // accepted set.
        // @mago-expect analysis:possibly-invalid-argument
        $result = wp_update_post($post_updates, wp_error: true);
        if ($result instanceof WP_Error) {
            return $result;
        }
    }

    $meta_updates = spectra_edit_popup_meta_updates($input);
    if ($meta_updates !== []) {
        sp_popup_meta_write($post->ID, $meta_updates);
    }

    // clean_post_cache is called inside sp_popup_meta_write; also call it
    // for the post-level fields path so the response reads fresh values.
    clean_post_cache($post->ID);

    return spectra_popup_compact_response($post->ID);
}

/**
 * Build the post-level wp_update_post payload from the caller's aliased
 * inputs. Returns `[]` when the caller supplied no post-level fields —
 * the caller skips `wp_update_post` in that case so an edit that only
 * touches meta does not bump `post_modified` unnecessarily.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function spectra_edit_popup_post_updates(array $input, int $id): array
{
    $updates = ['ID' => $id];
    $touched = false;
    foreach ([
        'title' => 'post_title',
        'content' => 'post_content',
        'status' => 'post_status',
        'slug' => 'post_name',
    ] as $short => $native) {
        /** @var mixed $value */
        $value = sp_pick_alias($input, short: $short);
        if ($value === null) {
            continue;
        }
        if (!is_string($value)) {
            continue;
        }
        $updates[$native] = $value;
        $touched = true;
    }
    return $touched ? $updates : [];
}

/**
 * Build the meta-level updates bag from the caller's inputs. Returns `[]`
 * when the caller supplied no meta fields — callers skip the meta writer
 * to keep the `post_modified` timestamp truthful.
 *
 * @param array<string, mixed> $input
 * @return array{type?:string, enabled?:bool, repetition?:int}
 */
function spectra_edit_popup_meta_updates(array $input): array
{
    $out = [];
    if (isset($input['popup_type']) && is_string($input['popup_type']) && $input['popup_type'] !== '') {
        $out['type'] = $input['popup_type'];
    }
    if (isset($input['enabled']) && is_bool($input['enabled'])) {
        $out['enabled'] = $input['enabled'];
    }
    if (isset($input['repetition']) && (is_int($input['repetition']) || is_numeric($input['repetition']))) {
        $out['repetition'] = spectra_coerce_int($input['repetition'], minimum: 0);
    }
    return $out;
}
