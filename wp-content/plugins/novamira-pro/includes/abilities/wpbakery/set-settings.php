<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Set WPBakery per-post settings.
 *
 * Writes the WPBakery-owned meta on a post. Each field is optional —
 * only the keys explicitly passed in the input are updated.
 */

wp_register_ability('novamira/wpbakery-set-settings', [
    'label' => __('Set WPBakery Settings', domain: 'novamira-pro'),
    'description' => __(
        'Writes WPBakery-owned meta on a post. Pass `wpbakery_edited: true` to mark the post as a WPBakery page (sets _wpb_vc_js_status to the literal string "true" so the "Edit with WPBakery" admin link appears and the frontend assets load). Pass `custom_css` to set the page-level custom CSS textarea. Pass `shortcodes_custom_css` to overwrite the CSS WPBakery generates from element Design Options — usually you should leave this alone and let WPBakery regenerate it. Only the keys explicitly provided in the input are updated.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to update.',
            ],
            'wpbakery_edited' => [
                'type' => 'boolean',
                'description' => 'When true, mark the post as a WPBakery page (sets _wpb_vc_js_status="true"). When false, unset the flag. Omit to leave unchanged.',
            ],
            'custom_css' => [
                'type' => 'string',
                'description' => 'Page-level custom CSS. Stored in _wpb_post_custom_css. Omit to leave unchanged.',
            ],
            'shortcodes_custom_css' => [
                'type' => 'string',
                'description' => 'CSS generated from element Design Options. Stored in _wpbakery_shortcodes_custom_css. Omit to leave unchanged.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'updated' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'List of keys that were actually changed by this call.',
            ],
        ],
        'required' => ['post_id', 'updated'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_set_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this right after create-post on a WPBakery page, passing wpbakery_edited=true. Without that, the saved shortcodes will render as literal text on the frontend and the admin will not surface the "Edit with WPBakery" link. Only the keys you pass are written — omitted keys are untouched.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     post_id: int,
 *     wpbakery_edited?: bool,
 *     custom_css?: string,
 *     shortcodes_custom_css?: string,
 * } $input
 * @return array{post_id: int, updated: list<string>}|WP_Error
 */
function wpbakery_set_settings(array $input): array|WP_Error
{
    $post_id = $input['post_id'];
    /** @var \WP_Post|null $post */
    $post = $post_id > 0 ? get_post($post_id) : null;
    if (!$post instanceof \WP_Post) {
        return new WP_Error('wpbakery_not_found', sprintf(
            /* translators: %d: post ID. */
            __('Post %d not found.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if ($post->post_status === 'trash') {
        return new WP_Error('wpbakery_post_in_trash', sprintf(
            /* translators: %d: post ID. */
            __('Post %d is in the trash. Untrash it before writing.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if ($post->post_status === 'auto-draft') {
        return new WP_Error('wpbakery_post_auto_draft', sprintf(
            /* translators: %d: post ID. */
            __(
                'Post %d is an auto-draft (temporary placeholder); create a real post first via novamira/create-post.',
                domain: 'novamira-pro',
            ),
            $post_id,
        ));
    }
    if ($post->post_type === 'revision') {
        return new WP_Error('wpbakery_post_is_revision', sprintf(
            /* translators: %d: post ID. */
            __(
                'Post %d is a revision (immutable history snapshot). Target the parent post instead.',
                domain: 'novamira-pro',
            ),
            $post_id,
        ));
    }

    $updated = [];

    if (array_key_exists('wpbakery_edited', $input)) {
        $value = $input['wpbakery_edited'] ? 'true' : 'false';
        update_post_meta($post_id, meta_key: WPB_META_EDITED, meta_value: $value);
        $updated[] = 'wpbakery_edited';
    }

    if (array_key_exists('custom_css', $input)) {
        update_post_meta($post_id, meta_key: WPB_META_CUSTOM_CSS, meta_value: $input['custom_css']);
        $updated[] = 'custom_css';
    }

    if (array_key_exists('shortcodes_custom_css', $input)) {
        update_post_meta($post_id, meta_key: WPB_META_SHORTCODES_CSS, meta_value: $input['shortcodes_custom_css']);
        $updated[] = 'shortcodes_custom_css';
    }

    return [
        'post_id' => $post_id,
        'updated' => $updated,
    ];
}
