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
 * Ability: edit an existing Voxel post — partial / merge update of the post
 * fields and/or its field values.
 */

wp_register_ability('novamira/voxel-edit-post', [
    'label' => __('Edit Voxel Post', domain: 'novamira-pro'),
    'description' => __(
        'Update an existing Voxel post. This is a partial update: only the title, status, content, slug and the field values you provide are changed; everything else is left untouched. Provided field values are sanitized and validated the same way as the Voxel form; a value the field rejects refuses the whole edit and nothing changes, while a value the field cannot parse may be discarded instead, letting the edit succeed without it. A field value set to empty clears that field. The search index is updated afterward. To replace the entire post use the create flow; to move a post to trash use novamira/voxel-delete-post.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The Voxel post id to edit.'],
            'title' => ['type' => 'string', 'description' => 'New title (post_title). Alias: post_title.'],
            'post_title' => ['type' => 'string', 'description' => 'Alias of title.'],
            'status' => [
                'type' => 'string',
                'enum' => ['draft', 'pending', 'publish', 'private'],
                'description' => 'New status. Alias: post_status.',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => ['draft', 'pending', 'publish', 'private'],
                'description' => 'Alias of status.',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'New content (post_content / description field). Alias: post_content.',
            ],
            'post_content' => ['type' => 'string', 'description' => 'Alias of content.'],
            'slug' => ['type' => 'string', 'description' => 'New URL slug (post_name). Alias: post_name.'],
            'post_name' => ['type' => 'string', 'description' => 'Alias of slug.'],
            'fields' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Field values to change, keyed by field key. Only these keys are touched; an empty value clears the field.',
            ],
        ],
        'required' => ['post_id'],
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
                'description' => 'Fields you supplied a value for that the field type could not parse and discarded without raising. The edit still went through. These keys also appear in fields_written, because the write ran — only the value is missing.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_edit_post',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update — omit what you do not want to change. Only the provided field values are validated (required-field enforcement applies to the values you send, not to unprovided fields). A value the field rejects refuses the edit (voxel_field_validation) and nothing changes. Some field types instead discard a value they cannot parse, in which case the edit succeeds without it — read the post back with novamira/voxel-get-post include=["values"] when a value matters.',
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
function voxel_edit_post(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $post_id = vx_coerce_int($input['post_id'] ?? null) ?? 0;
    $post = vx_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $post_type = vx_post_type_or_error((string) get_post_type($post_id));
    if ($post_type instanceof WP_Error) {
        return $post_type;
    }

    $status_provided = array_key_exists('status', $input) || array_key_exists('post_status', $input);
    $status = vx_normalize_write_status(
        $input['status'] ?? $input['post_status'] ?? null,
        (string) get_post_status($post_id),
    );
    if ($status instanceof WP_Error) {
        return $status;
    }

    $title = vx_resolve_title($input);
    $prep = vx_prepare_write($post_type, vx_collect_write_values($input), $title, [
        'all' => false,
        'bypass_required' => $status === 'draft',
        'post' => $post,
    ]);
    if ($prep['errors'] !== []) {
        return vx_field_validation_error($prep['errors']);
    }

    // Write field values FIRST so that if a field's update() fatals the WP post
    // columns (title/status/slug) are left untouched — the edit reports failure
    // without a half-applied status change.
    try {
        $written = vx_apply_field_writes($post, $post_type, $prep['sanitized']);
    } catch (Throwable $e) {
        return vx_operation_failed(sprintf(
            /* translators: %s underlying error message. */
            __('Could not write field values; no changes were applied: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    $columns = vx_apply_post_columns($input, $post_id, $title, $status_provided ? $status : null);
    if ($columns instanceof WP_Error) {
        return $columns;
    }

    return vx_finalize_write($post_id, (string) get_post_type($post_id), $written, $prep['dropped']);
}
