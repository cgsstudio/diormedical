<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraBlocks;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-blocks-get-popup', [
    'label' => __('Get Spectra Blocks Popup', domain: 'novamira-pro'),
    'description' => __(
        'Read a single Spectra Blocks Popup Builder post (`spectra-blocks-popup` CPT) in full: title, slug, status, content (raw block markup — a `spectra/popup-builder` wrapper tree, byte-capped), the three meta values (popup_type, enabled, repetition), modified timestamp. `max_field_bytes` adjusts the 20_000-byte default content cap up to the hard maximum of 100_000 bytes — there is no uncapped mode; `content_truncated: true` means the stored markup is larger than the cap. Trashed, auto-draft, revision, and non-popup ids return spectra_blocks_popup_not_found. Progressive-disclosure counterpart of novamira/spectra-blocks-list-popups.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-blocks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Popup post id (from list-popups.popups[].id).',
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100_000,
                'description' => 'Byte cap for `content`. Default 20_000, hard maximum 100_000 — output is always bounded.',
            ],
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
            'content' => ['type' => 'string'],
            'content_truncated' => ['type' => 'boolean'],
            'popup_type' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'repetition' => ['type' => 'number'],
            'modified' => ['type' => 'string'],
        ],
        'required' => [
            'id',
            'title',
            'slug',
            'status',
            'content',
            'content_truncated',
            'popup_type',
            'enabled',
            'repetition',
            'modified',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraBlocks\\spectra_blocks_get_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Full popup fetch. Raise max_field_bytes (hard maximum 100_000) when the default 20_000-byte cap truncates; content_truncated=true at the maximum means the stored markup is larger than any single response will carry.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_blocks_get_popup(array $input): array|WP_Error
{
    if (!spectra_blocks_plugin_available()) {
        return sb_not_active();
    }

    $id = sb_coerce_int($input['id'] ?? 0, minimum: 0);
    $post = sb_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $max_bytes = isset($input['max_field_bytes'])
        ? sb_coerce_int($input['max_field_bytes'], minimum: 1)
        : SPECTRA_BLOCKS_DEFAULT_MAX_FIELD_BYTES;
    if ($max_bytes > SPECTRA_BLOCKS_MAX_FIELD_BYTES_CAP) {
        $max_bytes = SPECTRA_BLOCKS_MAX_FIELD_BYTES_CAP;
    }

    $content_result = sb_truncate_body($post->post_content, $max_bytes);
    $meta = sb_popup_meta_read($post->ID);

    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'status' => $post->post_status,
        'content' => $content_result['body'],
        'content_truncated' => $content_result['truncated'],
        'popup_type' => $meta['type'],
        'enabled' => $meta['enabled'],
        'repetition' => $meta['repetition'],
        'modified' => $post->post_modified_gmt,
    ];
}
