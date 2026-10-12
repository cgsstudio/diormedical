<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Formidable's style CPT slug — verified against
 * `FrmStylesController.php:11` (Formidable 6.32).
 */
const FRM_STYLE_POST_TYPE = 'frm_styles';

wp_register_ability('novamira/formidable-list-styles', [
    'label' => __('List Formidable Styles', domain: 'novamira-pro'),
    'description' => __(
        "Lists every Formidable form style (CPT `frm_styles`). Compact per-row payload: id, name, slug (post_name), is_default (post_status='publish' AND has the default-style menu_order). Use this BEFORE apply-style to pick a style_id for a form. Style CRUD itself (creating / editing the 250+ CSS variable set) is out of scope for this surface — use novamira/execute-php with FrmStyle::save_settings for one-off edits, or the Formidable admin UI for the full experience.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'styles' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'is_default' => ['type' => 'boolean'],
                        'modified_at' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'name', 'slug', 'is_default', 'modified_at'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['styles'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_list_styles',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Enumerates form styles registered as `frm_styles` posts. The numeric `id` is what apply-style takes. is_default flags the install-wide default style.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{styles: list<array{id: int, name: string, slug: string, is_default: bool, modified_at: string}>}|WP_Error
 */
function formidable_list_styles(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_view_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    /** @var list<WP_Post> $posts */
    $posts = get_posts([
        'post_type' => FRM_STYLE_POST_TYPE,
        'post_status' => ['publish', 'draft'],
        'numberposts' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'meta_query' => [],
        'suppress_filters' => false,
    ]);

    $rows = [];
    foreach ($posts as $post) {
        // Formidable marks the default style with menu_order=1 (per
        // FrmStylesController's save handler). Other styles get 0.
        $rows[] = [
            'id' => (int) $post->ID,
            'name' => $post->post_title,
            'slug' => $post->post_name,
            'is_default' => $post->post_status === 'publish' && (int) $post->menu_order === 1,
            'modified_at' => $post->post_modified_gmt,
        ];
    }

    return ['styles' => $rows];
}
