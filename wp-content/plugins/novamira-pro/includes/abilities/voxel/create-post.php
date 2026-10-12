<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: create a post (listing) of a Voxel post type with field values.
 */

wp_register_ability('novamira/voxel-create-post', [
    'label' => __('Create Voxel Post', domain: 'novamira-pro'),
    'description' => __(
        'Create a new listing of a Voxel post type and set its field values. Provide the post type, an optional title, status (draft, pending, publish or private; default draft), and a fields object mapping each field key to its value (text = string, number = number, location = {address, latitude, longitude}, taxonomy = array of term slugs, image/file = array of attachment ids). Field values are sanitized and validated exactly like the Voxel submission form: required fields are enforced for publish and pending, skipped for draft. On success the post is created and its search index is updated. Use novamira/voxel-get-post-type include=["fields"] to discover the field keys and types first.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'description' => 'The Voxel post type key, e.g. "places".',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'The post title (WordPress post_title). Alias: post_title.',
            ],
            'post_title' => ['type' => 'string', 'description' => 'Alias of title.'],
            'status' => [
                'type' => 'string',
                'enum' => ['draft', 'pending', 'publish', 'private'],
                'description' => 'Post status (default draft). Alias: post_status. Required fields are enforced for publish/pending.',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => ['draft', 'pending', 'publish', 'private'],
                'description' => 'Alias of status.',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Post content (WordPress post_content; the Voxel "description" field). Alias: post_content.',
            ],
            'post_content' => ['type' => 'string', 'description' => 'Alias of content.'],
            'slug' => ['type' => 'string', 'description' => 'URL slug (post_name). Alias: post_name.'],
            'post_name' => ['type' => 'string', 'description' => 'Alias of slug.'],
            'author' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Author user id (default: current user). Alias: post_author.',
            ],
            'post_author' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Alias of author.'],
            'fields' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Field values keyed by field key. Only the keys you provide are written.',
            ],
        ],
        'required' => ['post_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'post_type' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'permalink' => ['type' => 'string'],
            'indexed' => [
                'type' => 'boolean',
                'description' => 'Whether the post is actually present in the search index, read back after writing. False for a status the post type does not index, and false when the post type has no index table at all — check novamira/voxel-check-setup index_tables_missing.',
            ],
            'fields_written' => ['type' => 'array', 'items' => ['type' => 'string']],
            'fields_dropped' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Fields you supplied a value for that the field type could not parse and discarded without raising. The post was still created. These keys also appear in fields_written, because the write ran — only the value is missing.',
            ],
        ],
        'required' => [
            'post_id',
            'post_type',
            'status',
            'title',
            'slug',
            'permalink',
            'indexed',
            'fields_written',
            'fields_dropped',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_create_post',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover field keys/types with novamira/voxel-get-post-type include=["fields"] first. Required fields are enforced for publish/pending — use status="draft" to save partial data. A value the field rejects refuses the whole create (error voxel_field_validation, data.fields maps each bad field to its message) and no post is created. Not every bad value is rejected, though: some field types discard a value they cannot parse instead of raising, so the post is created without it — read the post back with novamira/voxel-get-post include=["values"] when a value matters.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_create_post(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $post_type = vx_post_type_or_error($key);
    if ($post_type instanceof WP_Error) {
        return $post_type;
    }

    $status = vx_normalize_write_status($input['status'] ?? $input['post_status'] ?? null, default: 'draft');
    if ($status instanceof WP_Error) {
        return $status;
    }

    $author = vx_resolve_author($input);
    if ($author instanceof WP_Error) {
        return $author;
    }

    $title = vx_resolve_title($input);
    $prep = vx_prepare_write($post_type, vx_collect_write_values($input), $title, [
        'all' => true,
        'bypass_required' => $status === 'draft',
        'post' => null,
    ]);
    if ($prep['errors'] !== []) {
        return vx_field_validation_error($prep['errors']);
    }

    $post_id = vx_insert_post_row([
        'post_type' => $key,
        'post_status' => $status,
        'post_title' => $title['value'],
        'post_author' => $author,
    ], $input);
    if ($post_id instanceof WP_Error) {
        return $post_id;
    }

    $post = \Voxel\Post::get($post_id);
    if (!is_object($post)) {
        return vx_operation_failed(__('The post was created but could not be loaded.', domain: 'novamira-pro'));
    }

    // Write the caller's fields; if a field's update() fatals, roll the new post
    // back so no orphan is left behind (the write phase is not otherwise atomic).
    try {
        $written = vx_apply_field_writes($post, $post_type, $prep['sanitized']);
    } catch (Throwable $e) {
        wp_delete_post($post_id, force_delete: true);
        return vx_operation_failed(sprintf(
            /* translators: %s underlying error message. */
            __('Could not write field values; the new post was removed: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    return vx_finalize_write($post_id, $key, $written, $prep['dropped']);
}
