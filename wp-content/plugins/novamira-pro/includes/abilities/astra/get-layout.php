<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: resolve the effective layout decisions Astra makes for a post or the
 * global archive context.
 */

wp_register_ability('novamira/astra-get-layout', [
    'label' => __('Get Astra Layout', domain: 'novamira-pro'),
    'description' => __(
        'Resolves the effective container, sidebar, header, and post-title display settings for a specific post (`post_id`) or the global archive context (`post_id=0`). Each field shows whether the value comes from per-post meta (`source=meta`) or the global `astra-settings` bag (`source=global`). Use before writing per-post overrides via `astra-set-post-settings` to see what is already active. Read-only — does not mutate state.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'Post id to resolve layout for. Pass 0 (or omit) for the global archive context.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'container_layout' => ['type' => 'string'],
            'container_style' => ['type' => 'string'],
            'sidebar_layout' => ['type' => 'string'],
            'sidebar_style' => ['type' => 'string'],
            'main_header_display' => ['type' => 'string'],
            'post_title_display' => ['type' => 'string'],
            'source' => [
                'type' => 'object',
                'description' => 'Per-field source: `meta` when the value comes from per-post override, `global` when it resolves to the site-wide setting.',
                'properties' => [
                    'container_layout' => ['type' => 'string', 'enum' => ['meta', 'global']],
                    'container_style' => ['type' => 'string', 'enum' => ['meta', 'global']],
                    'sidebar_layout' => ['type' => 'string', 'enum' => ['meta', 'global']],
                    'sidebar_style' => ['type' => 'string', 'enum' => ['meta', 'global']],
                    'main_header_display' => ['type' => 'string', 'enum' => ['meta', 'global']],
                    'post_title_display' => ['type' => 'string', 'enum' => ['meta', 'global']],
                ],
                'required' => [
                    'container_layout',
                    'container_style',
                    'sidebar_layout',
                    'sidebar_style',
                    'main_header_display',
                    'post_title_display',
                ],
            ],
        ],
        'required' => [
            'container_layout',
            'container_style',
            'sidebar_layout',
            'sidebar_style',
            'main_header_display',
            'post_title_display',
            'source',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_layout',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass `post_id` when the user names a specific page or post — Astra resolves layout with per-post overrides that can differ from the global setting. Omit (or pass 0) for the site-wide defaults. The `source` map tells you whether each value would be affected by editing the global setting vs. a per-post override.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int} $input
 * @return array<string, mixed>
 */
function astra_get_layout(array $input): array
{
    $post_id = astra_coerce_int($input['post_id'] ?? 0);

    if ($post_id <= 0) {
        return astra_gl_global_layout();
    }

    $post_or_error = astra_post_or_error($post_id);
    if ($post_or_error instanceof \WP_Error) {
        return astra_gl_global_layout();
    }

    return astra_gl_post_layout($post_id);
}

/**
 * Layout snapshot for the global archive context (no per-post meta).
 *
 * @return array<string, mixed>
 */
function astra_gl_global_layout(): array
{
    return [
        'container_layout' => (string) astra_read_setting('site-content-layout', default: 'default'),
        'container_style' => (string) astra_read_setting('site-content-style', default: 'default'),
        'sidebar_layout' => (string) astra_read_setting('site-sidebar-layout', default: 'default'),
        'sidebar_style' => (string) astra_read_setting('site-sidebar-style', default: 'default'),
        'main_header_display' => (string) astra_read_setting('ast-main-header-display', default: 'default'),
        'post_title_display' => (string) astra_read_setting('site-post-title', default: 'default'),
        'source' => [
            'container_layout' => 'global',
            'container_style' => 'global',
            'sidebar_layout' => 'global',
            'sidebar_style' => 'global',
            'main_header_display' => 'global',
            'post_title_display' => 'global',
        ],
    ];
}

/**
 * Layout snapshot resolved for a specific post_id, with per-field source tracking.
 *
 * @return array<string, mixed>
 */
function astra_gl_post_layout(int $post_id): array
{
    // key => [meta_key, global_key, default]
    $field_map = [
        'container_layout' => ['ast-site-content-layout', 'site-content-layout', 'default'],
        'container_style' => ['site-content-style', 'site-content-style', 'default'],
        'sidebar_layout' => ['site-sidebar-layout', 'site-sidebar-layout', 'default'],
        'sidebar_style' => ['site-sidebar-style', 'site-sidebar-style', 'default'],
        'main_header_display' => ['ast-main-header-display', 'ast-main-header-display', 'default'],
        'post_title_display' => ['site-post-title', 'site-post-title', 'default'],
    ];

    $values = [];
    $source = [];

    foreach ($field_map as $output_key => [$meta_key, $global_key, $fallback]) {
        // Call astra_get_option_meta with only_meta=true to detect whether a
        // per-post override is actually stored.
        $meta_only = function_exists('astra_get_option_meta')
            ? (string) astra_get_option_meta($meta_key, default: '', only_meta: true, extension: '', post_id: $post_id)
            : '';

        // Default to global; override below when a per-post value is present.
        $values[$output_key] = (string) astra_read_setting($global_key, $fallback);
        $source[$output_key] = 'global';

        if ($meta_only !== '' && $meta_only !== 'default') {
            // Per-post override is present — resolve the full value (with fallback).
            $values[$output_key] = function_exists('astra_get_option_meta')
                ? (string) astra_get_option_meta(
                    $meta_key,
                    default: $fallback,
                    only_meta: false,
                    extension: '',
                    post_id: $post_id,
                )
                : $meta_only;
            $source[$output_key] = 'meta';
        }
    }

    return array_merge($values, ['source' => $source]);
}
