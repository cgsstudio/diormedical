<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/astra-create-hook', [
    'label' => __('Create Astra Advanced Hook', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new astra-advanced-hook post. Required: title (accepts WP-native alias `post_title`) and layout. When layout=hooks, action (a WordPress action hook name, e.g. wp_head) is also required. Content (accepts WP-native alias `post_content`) is treated as raw Gutenberg block markup and capped at 32 KB. Status accepts WP-native alias `post_status`. Passing both a short name and its WP-native alias with different values returns `astra_alias_conflict`. The php-execution surface is permanently blocked — use code-snippets-integration for PHP snippet injection instead. Defaults: status=draft, priority=10, enabled=true. Returns the compact hook shape (id, title, status, layout, action, priority, enabled).',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'description' => 'Hook name visible in the admin list. Required. Accepts WP-native alias `post_title`.',
            ],
            'post_title' => [
                'type' => 'string',
                'description' => 'WP-native alias for `title`. Passing both with different values returns astra_alias_conflict.',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Gutenberg block markup rendered at the hook position. Capped at 32 KB. Accepts WP-native alias `post_content`.',
            ],
            'post_content' => [
                'type' => 'string',
                'description' => 'WP-native alias for `content`.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'Post status. Defaults to draft. Accepts WP-native alias `post_status`.',
                'default' => 'draft',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'WP-native alias for `status`.',
            ],
            'layout' => [
                'type' => 'string',
                'description' => 'Layout type. Required. One of: header, footer, 404-page, hooks, content, template. Use hooks to attach to a WordPress action hook.',
            ],
            'action' => [
                'type' => 'string',
                'description' => 'WordPress action hook name (e.g. wp_head, astra_header_before). Required when layout=hooks.',
            ],
            'priority' => [
                'type' => 'integer',
                'description' => 'Hook execution priority. Default 10.',
                'default' => 10,
            ],
            'enabled' => [
                'type' => 'boolean',
                'description' => 'Whether the hook is active. Default true.',
                'default' => true,
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
            'header' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Header configuration — string-keyed object. Allowed keys: disable_header, disable_primary_header, disable_main_menu, sticky, transparent.',
            ],
            'footer' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Footer configuration — string-keyed object. Allowed keys: disable_footer, disable_primary_footer.',
            ],
            'padding' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Padding/spacing configuration — string-keyed object. Allowed keys: above, below, left, right, top, bottom.',
            ],
            'template_type' => ['type' => 'string', 'description' => 'Template type identifier (optional).'],
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
            'action' => ['type' => 'string'],
            'priority' => ['type' => 'integer'],
            'enabled' => ['type' => 'boolean'],
        ],
        'required' => ['id', 'title', 'status', 'layout'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_create_hook',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a new advanced hook. When layout=hooks, provide the WordPress action name in the action field. Default status is draft — set status=publish to make it immediately active. The php-execution surface is permanently blocked; use code-snippets-integration for PHP snippets.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Validate and resolve create-hook inputs. Returns a resolved array or WP_Error.
 *
 * @param array<string, mixed> $input
 * @return array{title: string, layout: string, action: string, status: string, content: string}|WP_Error
 */
function ch_validate_create_inputs(array $input): array|WP_Error
{
    $title = is_string($input['title'] ?? null) ? astra_strip_nul_bytes(trim($input['title'])) : '';
    if ($title === '') {
        return astra_invalid_input_error('`title` is required and must be a non-empty string.');
    }

    $layout = is_string($input['layout'] ?? null) ? astra_strip_nul_bytes($input['layout']) : '';
    if (!in_array($layout, astra_hook_layout_allowlist(), strict: true)) {
        return astra_invalid_input_error(sprintf('`layout` must be one of: %s.', implode(
            ', ',
            astra_hook_layout_allowlist(),
        )));
    }

    $action = is_string($input['action'] ?? null) ? astra_strip_nul_bytes(trim($input['action'])) : '';
    if ($layout === 'hooks' && $action === '') {
        return astra_invalid_input_error(
            '`action` is required when layout=hooks. Provide a WordPress action hook name, e.g. wp_head.',
        );
    }

    $raw_status = is_string($input['status'] ?? null) ? $input['status'] : 'draft';
    $status = in_array($raw_status, ['publish', 'draft'], strict: true) ? $raw_status : 'draft';

    $raw_content = is_string($input['content'] ?? null) ? astra_strip_nul_bytes($input['content']) : '';
    $content = (string) astra_cap_bytes($raw_content, limit: 32_768);

    return compact('title', 'layout', 'action', 'status', 'content');
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_create_hook(array $input): array|WP_Error
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

    // Normalise WP-native aliases (post_title / post_content / post_status)
    // down to short-name form before validation. Both may be supplied; a
    // mismatch returns astra_alias_conflict.
    $normalised = astra_normalize_wp_aliases($input);
    if ($normalised instanceof WP_Error) {
        return $normalised;
    }
    $input = $normalised;

    // Deep-validate the shape of the rule bags (location / exclusion /
    // users / header / footer / padding) before any DB touch. A shape
    // error rejects the entire write — no partial post created.
    $shape_error = astra_validate_meta_rules_shape($input);
    if ($shape_error instanceof WP_Error) {
        return $shape_error;
    }

    $validated = ch_validate_create_inputs($input);
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    [
        'title' => $title,
        'layout' => $layout,
        'action' => $action,
        'status' => $status,
        'content' => $content,
    ] = $validated;

    $post_id = wp_insert_post([
        'post_type' => 'astra-advanced-hook',
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => $status,
    ], wp_error: true);

    if ($post_id instanceof WP_Error) {
        return astra_error('astra_invalid_input', message: 'wp_insert_post failed: ' . $post_id->get_error_message());
    }

    $meta_input = $input;
    if (!array_key_exists('priority', $meta_input)) {
        $meta_input['priority'] = 10;
    }
    if (!array_key_exists('enabled', $meta_input)) {
        $meta_input['enabled'] = true;
    }
    astra_write_hook_meta($post_id, $meta_input);

    clean_post_cache($post_id);

    $priority_raw = (string) get_post_meta(post_id: $post_id, key: 'ast-advanced-hook-priority', single: true);
    $enabled_raw = (string) get_post_meta(post_id: $post_id, key: 'ast-advanced-hook-enabled', single: true);

    return [
        'id' => $post_id,
        'title' => $title,
        'status' => $status,
        'layout' => $layout,
        'action' => $action,
        'priority' => $priority_raw !== '' ? (int) $priority_raw : 10,
        'enabled' => $enabled_raw !== 'no',
    ];
}
