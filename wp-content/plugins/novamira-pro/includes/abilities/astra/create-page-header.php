<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/astra-create-page-header', [
    'label' => __('Create Astra Page Header', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new astra_adv_header post. Required: title (accepts WP-native alias `post_title`) and layout. layout must be one of: advanced-headers-layout-1, advanced-headers-layout-2, disable. Content (accepts WP-native alias `post_content`) is capped at 32 KB. Default status is draft (accepts WP-native alias `post_status`); set status=publish to activate immediately. Passing both a short name and its WP-native alias with different values returns astra_alias_conflict. Returns the compact shape (id, title, status, layout, enabled). Note: there is no ast-advanced-headers-enabled meta key in the Astra Pro Addon — enabled is derived from status (publish → true, draft → false). Requires the Astra Pro Addon with the advanced-headers module active.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'description' => 'Page header name visible in the admin list. Required. Accepts WP-native alias `post_title`.',
            ],
            'post_title' => [
                'type' => 'string',
                'description' => 'WP-native alias for `title`.',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Post content body. Capped at 32 KB. Accepts WP-native alias `post_content`.',
            ],
            'post_content' => [
                'type' => 'string',
                'description' => 'WP-native alias for `content`.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'Post status. Defaults to draft. Set to publish to make the header active. Accepts WP-native alias `post_status`.',
                'default' => 'draft',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'WP-native alias for `status`.',
            ],
            'layout' => [
                'type' => 'string',
                'description' => 'Layout type. Required. One of: advanced-headers-layout-1, advanced-headers-layout-2, disable.',
            ],
            'design' => [
                'type' => 'object',
                'description' => 'Design settings array (colors, background-color, bg-image, bg-size, parallax-device, etc.).',
            ],
            'location' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Targeting rules — string-keyed object. Allowed keys: rule, specific, specificPosts, posts, taxonomy, terms, taxonomies.',
            ],
            'exclusion' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Exclusion rules — string-keyed object with the same allowlist as location.',
            ],
            'users' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'User role rules — string-keyed object. Allowed keys: rule, specific, users.',
            ],
        ],
        'required' => ['title', 'layout'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'layout' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean', 'description' => 'True when status=publish, false when status=draft.'],
        ],
        'required' => ['id', 'title', 'status', 'layout', 'enabled'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_create_page_header',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a new advanced page header. Default status is draft — set status=publish to activate immediately. layout is required; use advanced-headers-layout-2 for the standard banner layout.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Validate and resolve create-page-header inputs. Returns a resolved array or WP_Error.
 *
 * @param array<string, mixed> $input
 * @return array{title: string, layout: string, status: string, content: string}|WP_Error
 */
function cph_validate_create_inputs(array $input): array|WP_Error
{
    $title = is_string($input['title'] ?? null) ? astra_strip_nul_bytes(trim($input['title'])) : '';
    if ($title === '') {
        return astra_invalid_input_error('`title` is required and must be a non-empty string.');
    }

    $layout = is_string($input['layout'] ?? null) ? astra_strip_nul_bytes($input['layout']) : '';
    if (!in_array($layout, astra_page_header_layout_allowlist(), strict: true)) {
        return astra_invalid_input_error(sprintf('`layout` must be one of: %s.', implode(
            ', ',
            astra_page_header_layout_allowlist(),
        )));
    }

    $raw_status = is_string($input['status'] ?? null) ? $input['status'] : 'draft';
    $status = in_array($raw_status, ['publish', 'draft'], strict: true) ? $raw_status : 'draft';

    $raw_content = is_string($input['content'] ?? null) ? astra_strip_nul_bytes($input['content']) : '';
    $content = (string) astra_cap_bytes($raw_content, limit: 32_768);

    return compact('title', 'layout', 'status', 'content');
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_create_page_header(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }
    if (!astra_addon_available()) {
        return astra_addon_required_error('advanced-headers');
    }
    if (!astra_module_active('advanced-headers')) {
        return astra_module_disabled_error('advanced-headers');
    }
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'manage_options capability required.', ['status' => 403]);
    }

    // Normalise WP-native aliases (post_title / post_content / post_status)
    // down to short-name form before validation.
    $normalised = astra_normalize_wp_aliases($input);
    if ($normalised instanceof WP_Error) {
        return $normalised;
    }
    $input = $normalised;

    // Deep-validate the shape of the rule bags (location / exclusion /
    // users) before any DB touch.
    $shape_error = astra_validate_meta_rules_shape($input);
    if ($shape_error instanceof WP_Error) {
        return $shape_error;
    }

    $validated = cph_validate_create_inputs($input);
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    [
        'title' => $title,
        'layout' => $layout,
        'status' => $status,
        'content' => $content,
    ] = $validated;

    $post_id = wp_insert_post([
        'post_type' => 'astra_adv_header',
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => $status,
    ], wp_error: true);

    if ($post_id instanceof WP_Error) {
        return astra_error('astra_invalid_input', message: 'wp_insert_post failed: ' . $post_id->get_error_message());
    }

    astra_write_page_header_meta($post_id, $input);

    clean_post_cache($post_id);

    return [
        'id' => $post_id,
        'title' => $title,
        'status' => $status,
        'layout' => $layout,
        'enabled' => $status === 'publish',
    ];
}
