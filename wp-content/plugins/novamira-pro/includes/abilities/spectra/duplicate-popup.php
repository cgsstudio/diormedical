<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-duplicate-popup', [
    'label' => __('Duplicate Spectra Popup', domain: 'novamira-pro'),
    'description' => __(
        'Deep-copy a Spectra Popup into a brand-new draft: post content + slug + every canonical meta key (popup_type, enabled, repetition) plus any additional `spectra-popup-*` / `spectra-pro-*` meta the source carries (Pro adds display conditions). Optional `title_suffix` (default " (Copy)") is appended to the copied title so agents can find the new row.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'title_suffix' => [
                'type' => 'string',
                'description' => 'Suffix appended to the copied title. Default " (Copy)". Pass empty string to keep the original title verbatim.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'source_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'popup_type' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'repetition' => ['type' => 'integer'],
            'modified' => ['type' => 'string'],
        ],
        'required' => [
            'id',
            'source_id',
            'title',
            'slug',
            'status',
            'popup_type',
            'enabled',
            'repetition',
            'modified',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_duplicate_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Deep clone into a fresh draft. Every spectra-popup-* / spectra-pro-* meta key is copied verbatim.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/** Meta-key prefixes the duplicate ability copies verbatim onto the new post. */
const SPECTRA_POPUP_META_COPY_PREFIXES = ['spectra-popup-', 'spectra-pro-'];

/** Upper bound on the caller-supplied `title_suffix` (bytes). */
const SPECTRA_DUPLICATE_TITLE_SUFFIX_MAX_BYTES = 128;

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_duplicate_popup(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $source_id = spectra_coerce_int($input['id'] ?? 0, minimum: 0);
    $source = sp_post_or_error($source_id);
    if ($source instanceof WP_Error) {
        return $source;
    }

    // Cap the caller-supplied suffix at 128 chars so a runaway payload
    // cannot force a large title concat into wp_insert_post before the DB
    // varchar boundary trims it. Realistic suffixes ("[v2]", " (Copy)")
    // sit well under this ceiling.
    $suffix = is_string($input['title_suffix'] ?? null) ? $input['title_suffix'] : ' (Copy)';
    if (strlen($suffix) > SPECTRA_DUPLICATE_TITLE_SUFFIX_MAX_BYTES) {
        return sp_invalid_input(sprintf(
            'title_suffix must not exceed %d bytes.',
            SPECTRA_DUPLICATE_TITLE_SUFFIX_MAX_BYTES,
        ));
    }

    $new_id_raw = wp_insert_post([
        'post_type' => SPECTRA_POPUP_POST_TYPE,
        'post_title' => $source->post_title . $suffix,
        'post_content' => $source->post_content,
        'post_status' => 'draft',
    ], wp_error: true);
    if ($new_id_raw instanceof WP_Error) {
        return $new_id_raw;
    }
    $new_id = (int) $new_id_raw;
    if ($new_id <= 0) {
        return sp_invalid_input('wp_insert_post returned an invalid id');
    }

    spectra_duplicate_popup_copy_meta($source_id, $new_id);

    $response = spectra_popup_compact_response($new_id);
    if ($response instanceof WP_Error) {
        return $response;
    }
    $response['source_id'] = $source_id;
    return $response;
}

/**
 * Copy every `spectra-popup-*` and `spectra-pro-*` meta row from the source
 * popup to the new one. Kept separate from the main callback so the copy
 * loop can be extended for Sprint 4 Pro-specific keys without touching
 * the write orchestration.
 */
function spectra_duplicate_popup_copy_meta(int $source_id, int $new_id): void
{
    /** @var array<string, array<int, string>>|string|false $meta */
    $meta = get_post_meta($source_id);
    if (!is_array($meta)) {
        return;
    }
    foreach ($meta as $key => $values) {
        if (!spectra_popup_meta_key_should_copy($key)) {
            continue;
        }
        // WP stores multi-value meta as an array of serialised strings. Restore
        // each value so meta-value repeating keys survive the copy.
        foreach ($values as $value) {
            add_post_meta($new_id, $key, maybe_unserialize($value));
        }
    }
    clean_post_cache($new_id);
}

/**
 * Whether the meta key belongs to Spectra's own namespace and therefore
 * should be copied. WordPress core meta keys (`_edit_lock`, `_wp_*`) and
 * third-party meta stay behind so a duplicated popup does not carry over
 * unrelated state.
 */
function spectra_popup_meta_key_should_copy(string $key): bool
{
    foreach (SPECTRA_POPUP_META_COPY_PREFIXES as $prefix) {
        if (str_starts_with($key, $prefix)) {
            return true;
        }
    }
    return false;
}
