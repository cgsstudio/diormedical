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
 * Ability: replace the Spectra One per-post display overrides for a single
 * post. The write path always mirrors ALL
 * 5 booleans to postmeta (true → meta row present with value `1`, false →
 * meta row deleted so the theme's default rules kick back in). Callers
 * that only want a partial patch should call `get-post-display-settings`
 * first, then pass the merged shape here.
 */

wp_register_ability('novamira/spectra-one-set-post-display-settings', [
    'label' => __('Set Spectra One Post Display Settings', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the Spectra One per-post display overrides on the post identified by `post_id` (accepts both `post_id` and the WP-native alias `id`). Only `post` and `page` are supported — the theme metabox is not registered on other post types, so a call against a different type is rejected up front with `spectra_one_unsupported_post_type`. All 5 boolean fields (hide_header, hide_footer, hide_title, sticky_header, transparent_header) are always required — passing false on a slot removes the meta row so the theme defaults render. Partial patches are NOT supported by this ability (that is the semantic of `set-`); call `novamira/spectra-one-get-post-display-settings` first, merge the fields you want to change, then invoke this. `clean_post_cache` is called after every write so the next render observes the new state.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'minimum' => 1],
            'id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'WP-native alias for post_id.',
            ],
            'hide_header' => ['type' => 'boolean'],
            'hide_footer' => ['type' => 'boolean'],
            'hide_title' => ['type' => 'boolean'],
            'sticky_header' => ['type' => 'boolean'],
            'transparent_header' => ['type' => 'boolean'],
        ],
        'required' => [
            'hide_header',
            'hide_footer',
            'hide_title',
            'sticky_header',
            'transparent_header',
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'minimum' => 1],
            'updated_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'The override keys that actually changed value on this call.',
            ],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'hide_header' => ['type' => 'boolean'],
                    'hide_footer' => ['type' => 'boolean'],
                    'hide_title' => ['type' => 'boolean'],
                    'sticky_header' => ['type' => 'boolean'],
                    'transparent_header' => ['type' => 'boolean'],
                ],
                'required' => [
                    'hide_header',
                    'hide_footer',
                    'hide_title',
                    'sticky_header',
                    'transparent_header',
                ],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['post_id', 'updated_keys', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_set_post_display_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always confirm the subject with the user before running this — echo `post_title` from novamira/spectra-one-get-post-display-settings first. The write REPLACES the overrides wholesale; a partial patch is not supported.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     post_id?: int,
 *     id?: int,
 *     hide_header: bool,
 *     hide_footer: bool,
 *     hide_title: bool,
 *     sticky_header: bool,
 *     transparent_header: bool,
 * } $input
 * @return array{
 *     post_id: int,
 *     updated_keys: list<string>,
 *     settings: array{
 *         hide_header: bool,
 *         hide_footer: bool,
 *         hide_title: bool,
 *         sticky_header: bool,
 *         transparent_header: bool,
 *     },
 * }|WP_Error
 */
function spectra_one_set_post_display_settings(array $input): array|WP_Error
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

    if (!current_user_can('edit_post', $post_id)) {
        return new WP_Error(
            'spectra_one_forbidden',
            __('The current user does not have permission to edit this post.', domain: 'novamira-pro'),
            ['status' => 403],
        );
    }

    return so_with_lock('post_display_' . $post_id, static fn(): array => so_write_post_display_settings(
        $post_id,
        $input,
    ));
}

/**
 * Locked read-modify-write for `set-post-display-settings`. Diffs each of
 * the 5 boolean overrides against the current postmeta and writes only
 * the deltas — `updated_keys` reflects that.
 *
 * @param array<string, mixed> $input
 * @return array{
 *     post_id: int,
 *     updated_keys: list<string>,
 *     settings: array{
 *         hide_header: bool,
 *         hide_footer: bool,
 *         hide_title: bool,
 *         sticky_header: bool,
 *         transparent_header: bool,
 *     },
 * }
 */
function so_write_post_display_settings(int $post_id, array $input): array
{
    /** @var list<string> $updated_keys */
    $updated_keys = [];
    $meta_keys = so_display_meta_keys();

    $requested = [
        'hide_header' => filter_var($input['hide_header'], FILTER_VALIDATE_BOOLEAN),
        'hide_footer' => filter_var($input['hide_footer'], FILTER_VALIDATE_BOOLEAN),
        'hide_title' => filter_var($input['hide_title'], FILTER_VALIDATE_BOOLEAN),
        'sticky_header' => filter_var($input['sticky_header'], FILTER_VALIDATE_BOOLEAN),
        'transparent_header' => filter_var($input['transparent_header'], FILTER_VALIDATE_BOOLEAN),
    ];

    $short_to_slug = [
        'hide_header' => 'header',
        'hide_footer' => 'footer',
        'hide_title' => 'title',
        'sticky_header' => 'sticky',
        'transparent_header' => 'transparent',
    ];

    foreach ($requested as $field => $new_value) {
        $slug = $short_to_slug[$field];
        $prior = so_read_display_meta($post_id, slug: $slug);
        if ($prior === $new_value) {
            continue;
        }
        so_apply_display_meta($post_id, $meta_keys[$slug], $new_value);
        $updated_keys[] = $field;
    }

    if ($updated_keys !== [] && function_exists('clean_post_cache')) {
        clean_post_cache($post_id);
    }

    return [
        'post_id' => $post_id,
        'updated_keys' => $updated_keys,
        'settings' => $requested,
    ];
}
