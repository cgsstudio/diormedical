<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/rank-math-edit-post-schema', [
    'label' => __('Edit Post Schema (Rank Math)', domain: 'novamira-pro'),
    'description' => __(
        'Sets the Rank Math schema (rich snippet) type for a post. schema_type is the schema.org type (Article, BlogPosting, NewsArticle, Product, Course, Event, Recipe, Book, JobPosting, Service, SoftwareApplication, VideoObject, Person, Restaurant) or "off" to remove the per-post schema (the post then inherits the post-type default). This replaces the post\'s per-post schema with a single primary entry of the chosen type; Rank Math fills the rest of the properties from its template. Requires the rich-snippet module. Identify the post by post_id (alias: id). Returns the resulting schema state.',
        domain: 'novamira-pro',
    ),
    'category' => 'rank-math',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'schema_type' => [
                'type' => 'string',
                'enum' => [
                    'off',
                    'Article',
                    'BlogPosting',
                    'NewsArticle',
                    'Product',
                    'Course',
                    'Event',
                    'Recipe',
                    'Book',
                    'JobPosting',
                    'Service',
                    'SoftwareApplication',
                    'VideoObject',
                    'Person',
                    'Restaurant',
                ],
                'description' => 'The schema.org type, or "off" to clear the per-post schema.',
            ],
        ],
        'required' => ['schema_type'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\RankMath\rank_math_edit_post_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Set the post\'s schema type. "off" clears the per-post schema so it inherits the post-type default. This REPLACES any existing per-post schema (including a manually-built multi-entry one) with a single primary entry of the chosen type, and "off" deletes all of it — destructive. Needs the rich-snippet module.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, id?: int, schema_type?: string} $args
 * @return array{post_id: int, schemas: list<array<string, mixed>>, default_type: string, has_custom: bool}|WP_Error
 */
function rank_math_edit_post_schema(array $args): array|WP_Error
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

    $type = is_string($args['schema_type'] ?? null) ? $args['schema_type'] : '';
    if ($type === '') {
        return new WP_Error('rank_math_invalid_schema', 'schema_type is required', ['status' => 400]);
    }

    $is_off = in_array($type, ['off', 'none'], strict: true);
    if ($is_off) {
        rm_clear_post_schemas($post->ID);
    }
    if (!$is_off) {
        rm_set_post_schema($post->ID, $type);
    }

    $entries = rm_schema_entries($post->ID);

    return [
        'post_id' => $post->ID,
        'schemas' => $entries,
        'default_type' => rm_default_schema_type($post->ID),
        'has_custom' => $entries !== [],
    ];
}
