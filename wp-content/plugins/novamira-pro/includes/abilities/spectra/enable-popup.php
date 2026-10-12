<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-enable-popup', [
    'label' => __('Enable Spectra Popup', domain: 'novamira-pro'),
    'description' => __(
        'Flip the `spectra-popup-enabled` meta flag to true. Idempotent — calling on an already-enabled popup returns the same response. Paired with novamira/spectra-disable-popup for the reverse operation. Meta writes flush the post cache so a follow-up read sees the fresh value.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_enable_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Enable the popup (idempotent). Use novamira/spectra-disable-popup for the reverse.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_enable_popup(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }
    $id = spectra_coerce_int($input['id'] ?? 0, minimum: 0);
    $post = sp_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    sp_popup_meta_write($post->ID, ['enabled' => true]);
    return spectra_popup_compact_response($post->ID);
}
