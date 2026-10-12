<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: trash or permanently delete a Voxel post, keeping Voxel's tables and
 * search index consistent.
 */

wp_register_ability('novamira/voxel-delete-post', [
    'label' => __('Delete Voxel Post', domain: 'novamira-pro'),
    'description' => __(
        'Move a Voxel post to trash, or permanently delete it. By default the post is trashed (recoverable) and removed from the search index. Pass force=true to permanently delete it: the post row and its related Voxel data (search index, relations, timeline, work hours) are removed, and orphaned follower rows are cleaned up. Only posts of a Voxel-managed post type can be deleted here.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The Voxel post id.'],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'false (default) trashes the post; true permanently deletes it.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'mode' => ['type' => 'string', 'enum' => ['trashed', 'deleted']],
            'ok' => ['type' => 'boolean'],
        ],
        'required' => ['post_id', 'mode', 'ok'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_delete_post',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default (force=false) trashes and is recoverable. force=true is permanent and cannot be undone — confirm intent before using it. Rejects non-Voxel post types.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{post_id: int, mode: string, ok: bool}|WP_Error
 */
function voxel_delete_post(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $post_id = vx_coerce_int($input['post_id'] ?? null) ?? 0;
    if ($post_id <= 0) {
        return vx_invalid(__('A positive post id is required.', domain: 'novamira-pro'));
    }

    // A laxer gate than vx_post_or_error: trashed posts must still be force-deletable.
    /** @var mixed $wp_post */
    $wp_post = get_post($post_id);
    if (!$wp_post instanceof WP_Post) {
        return vx_not_found('post', (string) $post_id);
    }
    if ($wp_post->post_type === 'revision') {
        return vx_invalid(__('Revisions cannot be deleted through this ability.', domain: 'novamira-pro'));
    }
    $post_type = vx_post_type_or_error($wp_post->post_type);
    if ($post_type instanceof WP_Error) {
        return vx_invalid(sprintf(
            /* translators: %1$d post id, %2$s WP post type. */
            __('Post %1$d is of type "%2$s", which is not managed by Voxel.', domain: 'novamira-pro'),
            $post_id,
            $wp_post->post_type,
        ));
    }

    $force = ($input['force'] ?? false) === true;
    $post = \Voxel\Post::get($post_id);

    if (!$force) {
        if ($wp_post->post_status === 'trash') {
            // Already trashed — report success rather than wp_trash_post's false.
            return ['post_id' => $post_id, 'mode' => 'trashed', 'ok' => true];
        }
        if (is_object($post)) {
            $post->unindex();
        }
        $trashed = wp_trash_post($post_id);
        return ['post_id' => $post_id, 'mode' => 'trashed', 'ok' => $trashed !== false && $trashed !== null];
    }

    if (is_object($post)) {
        $post->unindex();
    }
    $deleted = wp_delete_post($post_id, force_delete: true);
    vx_cleanup_orphan_followers($post_id);

    return ['post_id' => $post_id, 'mode' => 'deleted', 'ok' => $deleted !== false && $deleted !== null];
}

/**
 * Remove orphaned follower rows for a permanently deleted post. voxel_followers
 * is polymorphic with no foreign key to wp_posts, so a plain delete leaves rows
 * on BOTH sides: where the post was the followed object (object_type='post') and
 * where the post was itself the follower (follower_type='post'). Both are swept
 * so the follow graph stays consistent even if Voxel's daily cron never runs
 * (DISABLE_WP_CRON / low-traffic sites). Site-scoped via $wpdb->prefix.
 */
function vx_cleanup_orphan_followers(int $post_id): void
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = $wpdb->prefix . 'voxel_followers';
    $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
    $exists = is_string($sql) ? $wpdb->get_var($sql) : null;
    if ($exists !== $table) {
        return;
    }
    $wpdb->delete($table, ['object_type' => 'post', 'object_id' => $post_id]);
    $wpdb->delete($table, ['follower_type' => 'post', 'follower_id' => $post_id]);
}
