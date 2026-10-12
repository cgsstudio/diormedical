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
 * Ability: create a new Voxel post type (config only; registers next request).
 */

wp_register_ability('novamira/voxel-create-post-type', [
    'label' => __('Create Voxel Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Create a new Voxel post type. Provide a key (lowercase letters, numbers, underscores; max 20), and singular and plural labels. The post type starts with a required title field; add more with novamira/voxel-add-field. The post type is registered on the next request. This changes site configuration.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'Post type key, e.g. "event". Lowercase a-z, 0-9, hyphen, underscore; must contain a letter; max 20.',
            ],
            'singular' => ['type' => 'string', 'description' => 'Singular label, e.g. "Event".'],
            'plural' => ['type' => 'string', 'description' => 'Plural label, e.g. "Events".'],
        ],
        'required' => ['key', 'singular', 'plural'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
            'registers_on_next_request' => ['type' => 'boolean'],
        ],
        'required' => ['key', 'singular', 'plural', 'registers_on_next_request'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_create_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Site-configuration change. The post type is live on the next request, and its search index table is built on that same request. It starts with a required title field; add more with novamira/voxel-add-field. Rejects reserved/duplicate keys and keys already registered. If posts of this type come back indexed=false, check novamira/voxel-check-setup index_tables_missing.',
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
function voxel_create_post_type(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['key'] ?? null) ? $input['key'] : '';
    $singular = is_string($input['singular'] ?? null) ? sanitize_text_field($input['singular']) : '';
    $plural = is_string($input['plural'] ?? null) ? sanitize_text_field($input['plural']) : '';
    if ($singular === '' || $plural === '') {
        return vx_invalid(__('Singular and plural labels are required.', domain: 'novamira-pro'));
    }

    return vx_with_lock('post_types', static function () use ($key, $singular, $plural): array|WP_Error {
        $all = vx_read_post_types_config();
        $invalid = vx_validate_new_key($key, max_len: 20, reserved: vx_reserved_post_type_keys(), existing: $all);
        if ($invalid instanceof WP_Error) {
            return $invalid;
        }
        if (post_type_exists($key)) {
            return vx_invalid(sprintf(
                /* translators: %s post type key. */
                __('A post type "%s" is already registered.', domain: 'novamira-pro'),
                $key,
            ));
        }

        $all[$key] = [
            'settings' => ['key' => $key, 'singular' => $singular, 'plural' => $plural],
            // Voxel only injects a title field while a post type has no fields of
            // its own (post-type-repository.php:68-74), so the first add-field
            // call would silently take it away and leave the submission form with
            // no title input. Its own admin persists the field explicitly for the
            // same reason; `required` is left to the field class default, exactly
            // as the admin does.
            'fields' => [['type' => 'title', 'key' => 'title', 'label' => 'Title']],
            'filters' => [],
        ];
        vx_write_post_types_config($all);
        // The index table cannot be built here: Voxel registers post types on
        // init/0 and the Index_Table object needs a registered post type, which
        // this key only becomes on the next request. Leave a marker for the
        // wp_loaded handler in index-maintenance.php.
        vx_mark_index_pending($key);
        // Deferred: the rules WordPress would build right now still describe the
        // configuration as it was when init ran. See index-maintenance.php.
        vx_mark_rewrite_pending();

        return [
            'key' => $key,
            'singular' => $singular,
            'plural' => $plural,
            'registers_on_next_request' => true,
        ];
    });
}
