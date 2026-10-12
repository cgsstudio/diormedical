<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-delete-popup', [
    'label' => __('Delete Spectra Popup', domain: 'novamira-pro'),
    'description' => __(
        'Permanently delete a Spectra Popup (skips the trash). Requires `confirm: true` in the payload — a safety interlock so an agent cannot destroy the row from a single-argument tool call. Returns the deleted id + title so the caller can log which popup went away.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'confirm' => [
                'type' => 'boolean',
                'description' => 'Must be true. Guards against accidental single-argument calls.',
            ],
        ],
        'required' => ['id', 'confirm'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
        'required' => ['id', 'title', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_delete_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent deletion (force_delete=true). Requires manage_options AND explicit confirm=true in the payload.',
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
function spectra_delete_popup(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    if (!isset($input['confirm']) || $input['confirm'] !== true) {
        return sp_invalid_input(
            'confirm=true is required. This ability permanently deletes the popup — no trash bin recovery.',
        );
    }

    $id = spectra_coerce_int($input['id'] ?? 0, minimum: 0);
    $post = sp_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $title = $post->post_title;

    /** @var mixed $deleted */
    $deleted = wp_delete_post($post->ID, force_delete: true);
    if ($deleted === false || $deleted === null) {
        return new WP_Error(
            'spectra_delete_failed',
            /* translators: %d: popup post id */
            sprintf(__('Failed to delete Spectra popup #%d.', domain: 'novamira-pro'), $post->ID),
            ['status' => 500],
        );
    }

    return [
        'id' => $post->ID,
        'title' => $title,
        'deleted' => true,
    ];
}
