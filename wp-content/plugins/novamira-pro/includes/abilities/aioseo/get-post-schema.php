<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-get-post-schema', [
    'label' => __('Get Post Schema (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the stored All in One SEO schema (structured data) state for a post. Returns default_graph (the stored "enabled"/"off" toggle), default_type (the fixed post-type default, e.g. BlogPosting for posts or WebPage for pages), custom_graphs (stored Schema-Generator graph types), and has_custom_graphs. The toggle and custom graphs require AIOSEO\'s Pro schema renderer — see instructions. Identify the post by post_id (alias: id).',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'schema' => ['type' => 'object'],
        ],
        'required' => ['post_id', 'schema'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_get_post_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a post\'s stored schema state: default_graph is the stored toggle, default_type is the fixed post-type type, and custom_graphs are stored Schema-Generator graphs. Only AIOSEO\'s Pro schema renderer honours the toggle and emits custom graphs (`check-setup` → `pro: false` means it is absent). Without that renderer the front end always emits the post-type default and ignores custom graphs. Confirm the page\'s application/ld+json before reporting a graph as live. Use edit-post-schema to change the toggle; add a schema.graphs[] entry with execute-php for a custom type (see the skill).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, id?: int} $args
 * @return array{post_id: int, schema: array<string, mixed>}|WP_Error
 */
function aioseo_get_post_schema(array $args): array|WP_Error
{
    if (!aio_detect_setup()['active']) {
        return aio_not_active();
    }

    $post = aio_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    return [
        'post_id' => $post->ID,
        'schema' => aio_post_schema($post->ID, $post->post_type),
    ];
}
