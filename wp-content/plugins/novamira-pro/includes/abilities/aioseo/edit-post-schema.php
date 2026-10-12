<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use AIOSEO\Plugin\Common\Models\Post;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-edit-post-schema', [
    'label' => __('Edit Post Schema (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Controls the stored All in One SEO default (primary) schema-graph toggle for a post. schema_type is "default" or "off". AIOSEO fixes the default graph\'s type to the post-type default (e.g. BlogPosting for posts, WebPage for pages); this ability cannot change that type. The toggle is honoured only by AIOSEO\'s Pro schema renderer — see instructions. To add a specific schema.org type that needs per-type properties (Recipe, Product, FAQPage, Event, HowTo, etc.), add a custom graph to schema.graphs[] with novamira/execute-php (see the aioseo-integration skill for a recipe). Existing custom graphs are left intact. Identify the post by post_id (alias: id). Returns the resulting stored schema state.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'schema_type' => [
                'type' => 'string',
                'enum' => ['default', 'off'],
                'description' => '"default" stores the default graph as enabled; "off" stores it as disabled. Rendering requires AIOSEO\'s Pro schema renderer; see instructions.',
            ],
        ],
        'required' => ['schema_type'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_edit_post_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Change the post\'s stored default-graph toggle: "default" stores enabled; "off" stores disabled. Only AIOSEO\'s Pro schema renderer honours the toggle (`check-setup` → `pro: false` means it is absent). Without that renderer the front end emits the post-type default graph regardless, and this call reports stored state, not rendered output. Confirm the page\'s application/ld+json. The graph\'s type is fixed to the post-type default; add a schema.graphs[] custom graph via execute-php for Recipe/Product/etc. (see the skill). Custom graphs are left intact. Re-running is a no-op.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, id?: int, schema_type?: string} $args
 * @return array{post_id: int, schema: array<string, mixed>}|WP_Error
 */
function aioseo_edit_post_schema(array $args): array|WP_Error
{
    if (!aio_detect_setup()['active']) {
        return aio_not_active();
    }

    $post = aio_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $type = is_string($args['schema_type'] ?? null) ? $args['schema_type'] : '';
    if (!in_array($type, ['default', 'off'], strict: true)) {
        return new WP_Error('aioseo_invalid_schema', 'schema_type must be "default" or "off"', ['status' => 400]);
    }

    aies_apply($post->ID, $type);

    return [
        'post_id' => $post->ID,
        'schema' => aio_post_schema($post->ID, $post->post_type),
    ];
}

/**
 * Store the post's default (primary) schema graph as enabled ("default") or
 * disabled ("off"). AIOSEO fixes its type to the post-type default, so only
 * isEnabled changes. User-authored custom graphs remain intact because wiping
 * them would silently lose structured data, and they render independently of
 * the default graph.
 */
function aies_apply(int $post_id, string $type): void
{
    $model = Post::getPost($post_id);
    $schema = aio_schema_array($model);

    $default = is_array($schema['default'] ?? null) ? $schema['default'] : [];
    $default['isEnabled'] = $type !== 'off';
    $schema['default'] = $default;

    $model->schema = json_encode($schema);
    $model->save();
}
