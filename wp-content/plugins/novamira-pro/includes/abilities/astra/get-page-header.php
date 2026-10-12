<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/astra-get-page-header', [
    'label' => __('Get Astra Page Header', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single astra_adv_header post by id and returns the full payload: id, title, content (capped at 32 KB with a truncation marker if exceeded), status, layout, enabled, design, location, exclusion, users. `layout` is the sub-key value inside the ast-advanced-headers-layout meta array — one of: advanced-headers-layout-1, advanced-headers-layout-2, disable. `enabled` is derived from post_status (publish → true, draft → false); there is no ast-advanced-headers-enabled meta key. Requires the Astra Pro Addon with the advanced-headers module active.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Post id of the astra_adv_header entry (from astra-list-page-headers).',
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
            'content' => ['type' => 'string', 'description' => 'Post content, capped at 32 KB.'],
            'status' => ['type' => 'string'],
            'layout' => [
                'type' => 'string',
                'description' => 'Layout sub-key value: advanced-headers-layout-1, advanced-headers-layout-2, or disable.',
            ],
            'enabled' => [
                'type' => 'boolean',
                'description' => 'True when status=publish, false when status=draft.',
            ],
            'design' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Design settings object (colors, background, padding, etc.). Empty object when not configured.',
            ],
            'location' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Targeting rules — where the page header renders. Sub-lists (specific, specificPosts, posts, terms, users, taxonomies) are capped at 500 entries.',
            ],
            'exclusion' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Exclusion rules — where the page header is suppressed. Same sub-list cap as location.',
            ],
            'users' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'User role rules — which visitors see the page header. Sub-lists capped at 500 entries.',
            ],
        ],
        'required' => ['id', 'title', 'status', 'layout'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_page_header',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass the integer id from astra-list-page-headers. Returns the full page header payload including design settings and all targeting arrays.',
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
function astra_get_page_header(array $input): array|WP_Error
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

    $id = astra_coerce_int($input['id'] ?? 0);
    $post = astra_page_header_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    return astra_page_header_to_array($post);
}
