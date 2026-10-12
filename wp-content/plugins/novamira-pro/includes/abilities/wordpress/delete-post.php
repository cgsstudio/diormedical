<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WordPress;

use WP_Error;
use WP_Post;

/**
 * Ability: Delete a WordPress post (any post type).
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/delete-post', [
    'label' => __('Delete Post', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a WordPress post of any post type. By default moves it to the trash; set force=true to bypass the trash and delete permanently. Identifies the target via `post_id` (short alias: `id`).',
        domain: 'novamira-pro',
    ),
    'category' => 'wordpress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The ID of the post to delete. Short alias: `id`.',
            ],
            'id' => [
                'type' => 'integer',
                'description' => 'Short alias for `post_id`.',
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'When true, permanently deletes the post (bypassing the trash). When false, moves it to the trash if the post type supports trashing; a post already in the trash is refused with already_trashed instead.',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['post_id']],
            ['required' => ['id']],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'post_type' => ['type' => 'string'],
            'previous_status' => ['type' => 'string'],
            'result' => [
                'type' => 'string',
                'enum' => ['trashed', 'deleted'],
                'description' => '"trashed" when the post was moved to trash, "deleted" when it was permanently removed.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\WordPress\wordpress_delete_post',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default behavior moves the post to the trash, which is reversible unless the site sets EMPTY_TRASH_DAYS to 0 — with that setting WordPress deletes permanently instead of trashing. Pass force=true only when the user explicitly asks for a permanent delete. A post that is already in the trash is left untouched by a call without force: the reply is the error already_trashed (HTTP 409) and nothing is deleted. From there, either re-call with force=true to remove it permanently, or take the post out of the trash first. No ability untrashes a post: a real WordPress restore — which also restores the comments trashed with the post and runs the untrash_post/untrashed_post hooks other plugins listen for — is done from the WordPress admin, or with novamira/execute-php calling wp_untrash_post(). Setting a status with novamira/update-post also clears the 409, but it only changes the post status: the comments stay trashed and the untrash hooks do not run, so it is not a restore.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function wordpress_delete_post(array $input): array|WP_Error
{
    // Accept `id` as a short alias for `post_id` for symmetry with update-post.
    if (!array_key_exists('post_id', $input) && array_key_exists('id', $input)) {
        $input['post_id'] = $input['id'];
    }

    if (!array_key_exists('post_id', $input)) {
        return wordpress_error('missing_id', message: 'One of `post_id` or `id` is required.');
    }

    $post_id = (int) $input['post_id'];
    $force = ($input['force'] ?? false) === true;

    /** @var WP_Post|null $post */
    $post = get_post($post_id);

    if (!$post) {
        return wordpress_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    $previous_status = $post->post_status;
    $post_type = $post->post_type;

    // wp_trash_post() returns false for a post already in the trash. That is a
    // settled state, not a failed write: retrying cannot change it, and the
    // caller resolves it by passing force or restoring the post first.
    if (!$force && $previous_status === 'trash') {
        return wordpress_error('already_trashed', sprintf(
            'Post %d is already in the trash. Pass force: true to delete it permanently, or restore it first.',
            $post_id,
        ));
    }

    $result = $force ? wp_delete_post($post_id, force_delete: true) : wp_trash_post($post_id);

    if (!$result) {
        return wordpress_error('delete_failed', sprintf(
            'Failed to %s post %d.',
            $force ? 'delete' : 'trash',
            $post_id,
        ));
    }

    return [
        'post_id' => $post_id,
        'post_type' => $post_type,
        'previous_status' => $previous_status,
        'result' => $force || $previous_status === 'trash' ? 'deleted' : 'trashed',
    ];
}
