<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/astra-edit-page-header', [
    'label' => __('Edit Astra Page Header', domain: 'novamira-pro'),
    'description' => __(
        'Merge-patches an existing astra_adv_header post. Only keys you pass are updated; omitted keys keep their current value. Accepts WP-native aliases (`post_title` for `title`, `post_content` for `content`, `post_status` for `status`) — passing both a short name and its WP-native alias with different values returns astra_alias_conflict. If layout is provided it must be one of: advanced-headers-layout-1, advanced-headers-layout-2, disable. Content is capped at 32 KB. design is a full replace of the design settings array. Returns the full page header payload after the patch.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Post id of the page header to edit.'],
            'title' => ['type' => 'string', 'description' => 'Page header name. Accepts WP-native alias `post_title`.'],
            'post_title' => ['type' => 'string', 'description' => 'WP-native alias for `title`.'],
            'content' => [
                'type' => 'string',
                'description' => 'Post content body. Capped at 32 KB. Accepts WP-native alias `post_content`.',
            ],
            'post_content' => ['type' => 'string', 'description' => 'WP-native alias for `content`.'],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'Post status. Accepts WP-native alias `post_status`.',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'WP-native alias for `status`.',
            ],
            'layout' => [
                'type' => 'string',
                'description' => 'Layout type. One of: advanced-headers-layout-1, advanced-headers-layout-2, disable.',
            ],
            'design' => [
                'type' => 'object',
                'description' => 'Design settings array (full replace when provided).',
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
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'content' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'layout' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'design' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Design settings object. Empty object when not configured.',
            ],
            'location' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Targeting rules — string-keyed object. Sub-lists capped at 500 entries.',
            ],
            'exclusion' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Exclusion rules — string-keyed object. Sub-lists capped at 500 entries.',
            ],
            'users' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'User role rules — string-keyed object. Sub-lists capped at 500 entries.',
            ],
        ],
        'required' => ['id', 'title', 'status', 'layout'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_edit_page_header',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge-patch an existing page header — only pass the fields you want to change. layout must be one of the allowed enum values when provided.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Validate the layout enum value when present in the input. Returns WP_Error on failure.
 *
 * @param array<string, mixed> $input
 */
function eph_validate_layout(array $input): ?WP_Error
{
    if (!isset($input['layout'])) {
        return null;
    }
    $layout_val = is_string($input['layout']) ? $input['layout'] : '';
    if (!in_array($layout_val, astra_page_header_layout_allowlist(), strict: true)) {
        return astra_invalid_input_error(sprintf('`layout` must be one of: %s.', implode(
            ', ',
            astra_page_header_layout_allowlist(),
        )));
    }
    return null;
}

/**
 * Build the wp_update_post array from the input fields. Returns only ['ID'=>$id]
 * when no post-level fields are present (caller checks count > 1).
 *
 * @param array<string, mixed> $input
 * @return array{'ID': int, 'post_title'?: string, 'post_status'?: string, 'post_content'?: string}
 */
function eph_build_post_update(int $id, array $input): array
{
    $update = ['ID' => $id];
    if (isset($input['title']) && is_string($input['title'])) {
        $update['post_title'] = astra_strip_nul_bytes(trim($input['title']));
    }
    if (isset($input['status']) && in_array($input['status'], ['publish', 'draft'], strict: true)) {
        $update['post_status'] = $input['status'];
    }
    if (isset($input['content']) && is_string($input['content'])) {
        $update['post_content'] = (string) astra_cap_bytes(astra_strip_nul_bytes($input['content']), limit: 32_768);
    }
    return $update;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_edit_page_header(array $input): array|WP_Error
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

    // Normalise WP-native aliases before touching post fields.
    $normalised = astra_normalize_wp_aliases($input);
    if ($normalised instanceof WP_Error) {
        return $normalised;
    }
    $input = $normalised;

    // Deep-validate the shape of the rule bags (location / exclusion / users).
    $shape_error = astra_validate_meta_rules_shape($input);
    if ($shape_error instanceof WP_Error) {
        return $shape_error;
    }

    $id = astra_coerce_int($input['id'] ?? 0);
    $post = astra_page_header_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $layout_error = eph_validate_layout($input);
    if ($layout_error instanceof WP_Error) {
        return $layout_error;
    }

    $post_update = eph_build_post_update($id, $input);
    if (count($post_update) > 1) {
        $result = wp_update_post($post_update, wp_error: true);
        if ($result instanceof WP_Error) {
            return astra_error(
                'astra_invalid_input',
                message: 'wp_update_post failed: ' . $result->get_error_message(),
            );
        }
    }

    astra_write_page_header_meta($id, $input);

    clean_post_cache($id);

    /** @var WP_Post|null $fresh */
    $fresh = get_post($id);
    if (!$fresh instanceof WP_Post) {
        return astra_error('astra_not_found', message: sprintf(
            'Page header %d could not be reloaded after update.',
            $id,
        ));
    }
    return astra_page_header_to_array($fresh);
}
