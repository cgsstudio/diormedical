<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-get-popup', [
    'label' => __('Get Spectra Popup', domain: 'novamira-pro'),
    'description' => __(
        'Read a single Spectra Popup Builder post (`spectra-popup` CPT) in full: title, status, slug, content (block markup, byte-capped by default), full meta bundle (popup_type, enabled, repetition), modified timestamp. Use `max_field_bytes` to override the 20_000-byte content cap (0 disables truncation). Progressive-disclosure counterpart of novamira/spectra-list-popups.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
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
                'minimum' => 0,
                'description' => 'Byte cap for `content`. Default 20_000. Pass 0 to disable truncation.',
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
            'repetition' => ['type' => 'integer'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_get_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Full popup fetch. Use max_field_bytes=0 to inspect the entire block tree when the default 20_000-byte cap truncates.',
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
function spectra_get_popup(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $id = spectra_coerce_int($input['id'] ?? 0, minimum: 0);
    $post = sp_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $max_bytes = isset($input['max_field_bytes'])
        ? spectra_coerce_int($input['max_field_bytes'], minimum: 0)
        : SPECTRA_DEFAULT_MAX_FIELD_BYTES;

    $content_result = spectra_truncate_body($post->post_content, $max_bytes);
    $meta = sp_popup_meta_read($post->ID);

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
