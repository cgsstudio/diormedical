<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read the Spectra One per-post display overrides for a single
 * post / page. The theme's metabox exposes 5 boolean toggles that override
 * the site-wide theme defaults on a specific post — this ability surfaces
 * them in one shot, plus the post title so the agent can confirm the
 * subject with the user before invoking the writer.
 */

wp_register_ability('novamira/spectra-one-get-post-display-settings', [
    'label' => __('Get Spectra One Post Display Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Spectra One per-post display overrides for the post identified by `post_id` (accepts both `post_id` and the WP-native alias `id`): 5 booleans that override the theme defaults on this specific post — hide_header, hide_footer, hide_title, sticky_header, transparent_header. Only `post` and `page` are supported — the theme metabox is not registered on other post types, so a call against a different type is rejected with `spectra_one_unsupported_post_type`. `post_title` is echoed so the caller can confirm the subject with the user before changing anything. Read-only; the writer is novamira/spectra-one-set-post-display-settings.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer', 'minimum' => 1],
            'id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'WP-native alias for post_id.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'minimum' => 1],
            'post_title' => ['type' => 'string', 'maxLength' => 200],
            'post_type' => ['type' => 'string', 'maxLength' => 60],
            'hide_header' => ['type' => 'boolean'],
            'hide_footer' => ['type' => 'boolean'],
            'hide_title' => ['type' => 'boolean'],
            'sticky_header' => ['type' => 'boolean'],
            'transparent_header' => ['type' => 'boolean'],
        ],
        'required' => [
            'post_id',
            'post_title',
            'post_type',
            'hide_header',
            'hide_footer',
            'hide_title',
            'sticky_header',
            'transparent_header',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_get_post_display_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Use novamira/spectra-one-list-posts-with-display-overrides to discover which posts have overrides set at all before deep-diving with this ability.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, id?: int} $input
 * @return array{
 *     post_id: int,
 *     post_title: string,
 *     post_type: string,
 *     hide_header: bool,
 *     hide_footer: bool,
 *     hide_title: bool,
 *     sticky_header: bool,
 *     transparent_header: bool,
 * }|WP_Error
 */
function spectra_one_get_post_display_settings(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $post_id = so_resolve_post_id_alias($input);
    if ($post_id === 0) {
        return so_invalid_input(__(
            'post_id (or its alias `id`) is required and must be a positive integer.',
            domain: 'novamira-pro',
        ));
    }

    /** @var mixed $post */
    $post = get_post($post_id);
    if (!$post instanceof \WP_Post) {
        return new WP_Error(
            'spectra_one_post_not_found',
            sprintf(
                /* translators: %d: post ID */
                __('Post %d not found.', domain: 'novamira-pro'),
                $post_id,
            ),
            ['status' => 404],
        );
    }

    $status_error = so_post_display_target_error($post);
    if ($status_error instanceof WP_Error) {
        return $status_error;
    }

    $type_error = so_post_display_type_error($post);
    if ($type_error instanceof WP_Error) {
        return $type_error;
    }

    return [
        'post_id' => (int) $post->ID,
        'post_title' => $post->post_title,
        'post_type' => $post->post_type,
        'hide_header' => so_read_display_meta($post_id, slug: 'header'),
        'hide_footer' => so_read_display_meta($post_id, slug: 'footer'),
        'hide_title' => so_read_display_meta($post_id, slug: 'title'),
        'sticky_header' => so_read_display_meta($post_id, slug: 'sticky'),
        'transparent_header' => so_read_display_meta($post_id, slug: 'transparent'),
    ];
}
