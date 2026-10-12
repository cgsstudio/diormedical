<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/astra-get-hook', [
    'label' => __('Get Astra Advanced Hook', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single astra-advanced-hook post by id and returns the full payload: id, title, content (Gutenberg markup, capped at 32 KB with a truncation marker if exceeded), status, layout, action, priority, enabled, template_type, header, footer, padding, location, exclusion, users. The php-execution surface (ast-advanced-hook-with-php, ast-advanced-hook-php-code) is intentionally omitted — exposing raw PHP execution to AI agents is a code-injection vector. Requires the Astra Pro Addon with the advanced-hooks module active.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Post id of the astra-advanced-hook entry (from astra-list-hooks).',
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
            'content' => ['type' => 'string', 'description' => 'Gutenberg markup, capped at 32 KB.'],
            'status' => ['type' => 'string'],
            'layout' => ['type' => 'string'],
            'action' => ['type' => 'string'],
            'priority' => ['type' => 'integer'],
            'enabled' => ['type' => 'boolean'],
            'template_type' => ['type' => 'string'],
            'header' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Header configuration object (disable_header, sticky, shrink, etc.). Empty object when not configured.',
            ],
            'footer' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Footer configuration object. Empty object when not configured.',
            ],
            'padding' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Padding/spacing configuration object. Empty object when not configured.',
            ],
            'location' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Targeting rules — where the hook renders. Sub-lists (specific, specificPosts, posts, terms, users, taxonomies) are capped at 500 entries.',
            ],
            'exclusion' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Exclusion rules — where the hook is suppressed. Same sub-list cap as location.',
            ],
            'users' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'User role rules — which visitors see the hook. Sub-lists capped at 500 entries.',
            ],
        ],
        'required' => ['id', 'title', 'status', 'layout'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_hook',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass the integer id from astra-list-hooks. Returns the full hook payload including content body and all targeting arrays. The php-execution surface is permanently omitted.',
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
function astra_get_hook(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }
    if (!astra_addon_available()) {
        return astra_addon_required_error('advanced-hooks');
    }
    if (!astra_module_active('advanced-hooks')) {
        return astra_module_disabled_error('advanced-hooks');
    }
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'manage_options capability required.', ['status' => 403]);
    }

    $id = astra_coerce_int($input['id'] ?? 0);
    $post = astra_hook_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    return astra_hook_to_array($post);
}
