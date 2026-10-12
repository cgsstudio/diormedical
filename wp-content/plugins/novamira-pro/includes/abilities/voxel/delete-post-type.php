<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: delete a Voxel post type (config), cleaning up its orphaned index
 * tables and optionally its posts.
 */

wp_register_ability('novamira/voxel-delete-post-type', [
    'label' => __('Delete Voxel Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Remove a Voxel post type from the site configuration and drop its orphaned search-index tables (Voxel leaves these behind). By default the existing posts are kept (they simply become unmanaged once the post type stops being registered next request). Pass delete_posts=true to also permanently delete every post of that type. This is a configuration change and cannot be undone.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string', 'description' => 'The post type key to delete.'],
            'delete_posts' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Also permanently delete every post of this type.',
            ],
        ],
        'required' => ['post_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
            'index_tables_dropped' => [
                'type' => 'boolean',
                'description' => 'Whether a search-index table actually existed and was removed. False when the type never had one — that is the normal case for a type created through these abilities.',
            ],
            'posts_deleted' => [
                'type' => 'integer',
                'description' => 'Posts genuinely removed. A post WordPress refused to delete is not counted.',
            ],
            'options_deleted' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Per-post-type options removed. Voxel writes these on admin save and never cleans them up.',
            ],
        ],
        'required' => ['key', 'deleted', 'index_tables_dropped', 'posts_deleted', 'options_deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_delete_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent configuration change. By default posts are kept; delete_posts=true also removes every post. Orphaned index tables and per-post-type options are cleaned up when they exist. index_tables_dropped=false simply means the type had no index table. Takes effect next request.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{key: string, deleted: bool, index_tables_dropped: bool, posts_deleted: int, options_deleted: list<string>}|WP_Error
 */
function voxel_delete_post_type(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $delete_posts = ($input['delete_posts'] ?? false) === true;

    // Refuse to delete a WordPress/Voxel built-in the config blob happens to carry
    // (e.g. 'post'). Otherwise delete_posts=true here would wipe all core content.
    // Symmetric with create-post-type, which rejects the same reserved keys.
    if (in_array($key, vx_reserved_post_type_keys(), strict: true)) {
        return vx_invalid(sprintf(
            /* translators: %s the post type key. */
            __('"%s" is a built-in type and cannot be deleted here.', domain: 'novamira-pro'),
            $key,
        ));
    }

    return vx_with_lock('post_types', static function () use ($key, $delete_posts): array|WP_Error {
        $all = vx_read_post_types_config();
        $config = vx_post_type_config_or_error($all, $key);
        if ($config instanceof WP_Error) {
            return $config;
        }

        $posts_deleted = 0;
        if ($delete_posts && post_type_exists($key)) {
            /** @var mixed $ids */
            $ids = get_posts(['post_type' => $key, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']);
            if (is_array($ids)) {
                /** @var mixed $pid */
                foreach ($ids as $pid) {
                    if (!is_numeric($pid)) {
                        continue;
                    }
                    // wp_delete_post returns false/null when the delete is refused
                    // (a filter vetoed it, the row vanished). Counting the attempt
                    // would report posts as gone while they are still there.
                    $removed = wp_delete_post((int) $pid, force_delete: true);
                    if ($removed === false || $removed === null) {
                        continue;
                    }
                    // Voxel's followers table has no FK to wp_posts, so a bulk
                    // delete leaves orphaned rows on both sides — sweep each post.
                    vx_cleanup_orphan_followers((int) $pid);
                    $posts_deleted++;
                }
            }
        }

        unset($all[$key]);
        vx_write_post_types_config($all);
        $index_tables_dropped = vx_drop_index_tables($key);
        $options_deleted = vx_delete_post_type_options($key);
        // Our own marker, if the type is deleted before its index table was ever
        // built. The handler would drop it on its own once the key left the
        // config; removing it here just avoids one pointless pass.
        delete_option(vx_index_pending_option($key));
        // Deferred: the rules WordPress would build right now still describe the
        // configuration as it was when init ran. See index-maintenance.php.
        vx_mark_rewrite_pending();

        // \Voxel\set() discards the update_option() result, so reading the stored
        // config back is the only way to report a real outcome. It refreshes its
        // own cache on write, so this sees the persisted value.
        $deleted = !array_key_exists($key, vx_read_post_types_config());

        return [
            'key' => $key,
            'deleted' => $deleted,
            'index_tables_dropped' => $index_tables_dropped,
            'posts_deleted' => $posts_deleted,
            'options_deleted' => $options_deleted,
        ];
    });
}
