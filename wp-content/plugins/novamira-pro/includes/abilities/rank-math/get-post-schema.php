<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/rank-math-get-post-schema', [
    'label' => __('Get Post Schema (Rank Math)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Rank Math schema (rich snippet) for a post: any per-post schema entries (with their @type and whether each is primary) and the default_type the post inherits from its post-type setting when it has no per-post schema. Requires the Rank Math rich-snippet (Schema) module. Identify the post by post_id (alias: id).',
        domain: 'novamira-pro',
    ),
    'category' => 'rank-math',
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
            'schemas' => ['type' => 'array', 'items' => ['type' => 'object']],
            'default_type' => ['type' => 'string'],
            'has_custom' => ['type' => 'boolean'],
        ],
        'required' => ['post_id', 'schemas', 'default_type', 'has_custom'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\RankMath\rank_math_get_post_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a post\'s schema before changing it. has_custom=false means the post has no explicit schema and inherits default_type (the post-type default). Set the type with rank-math-edit-post-schema. Needs the rich-snippet module (rank-math-check-setup reports supports_schema).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, id?: int} $args
 * @return array{post_id: int, schemas: list<array<string, mixed>>, default_type: string, has_custom: bool}|WP_Error
 */
function rank_math_get_post_schema(array $args): array|WP_Error
{
    if (!rm_detect_setup()['active']) {
        return rm_not_active();
    }
    if (!rm_module_active('rich-snippet')) {
        return new WP_Error(
            'rank_math_schema_unavailable',
            'the Rank Math Schema (rich-snippet) module is not active',
            [
                'status' => 400,
            ],
        );
    }

    $post = rm_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $entries = rm_schema_entries($post->ID);

    return [
        'post_id' => $post->ID,
        'schemas' => $entries,
        'default_type' => rm_default_schema_type($post->ID),
        'has_custom' => $entries !== [],
    ];
}
