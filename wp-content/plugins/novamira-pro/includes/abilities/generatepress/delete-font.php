<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-delete-font', [
    'label' => __('Delete GeneratePress Font', domain: 'novamira-pro'),
    'description' => __(
        'Permanently removes a font from the GP Premium Font Library. Hard delete — the `gp_font` post is force-deleted, the per-font upload directory (`wp-content/uploads/generatepress/fonts/<slug>/`) is recursively wiped, and the bundled `fonts.css` is regenerated. No trash flow exists for this CPT — there is no undo. Before calling, run generatepress-list-fonts to confirm the id, and check that `generate_settings.font_body` / `font_headings` do not reference the font being removed (the front-end will silently fall back to a system font otherwise).',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the `gp_font` post from generatepress-list-fonts.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_delete_font',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always confirm the deletion with the user — there is no trash bin to recover from for `gp_font` posts. Files on disk are also removed.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id: int} $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_delete_font(array $input): array|WP_Error
{
    $id = gp_coerce_int($input['id']);
    if ($id <= 0) {
        return gp_error('gp_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof \WP_Post || $post->post_type !== 'gp_font') {
        return gp_error('gp_not_found', sprintf(__('gp_font post with id %d not found.', domain: 'novamira-pro'), $id));
    }

    $result = gp_dispatch_font_step('delete-font', ['fontId' => $id]);
    if ($result['error'] !== null) {
        return ['success' => false, 'error' => $result['error']];
    }
    return ['success' => true, 'id' => $id];
}
