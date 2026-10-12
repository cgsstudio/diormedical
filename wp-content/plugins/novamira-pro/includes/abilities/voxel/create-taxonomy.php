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
 * Ability: create a new Voxel taxonomy (config only; registers next request).
 */

wp_register_ability('novamira/voxel-create-taxonomy', [
    'label' => __('Create Voxel Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Create a new Voxel taxonomy. Provide a key (lowercase letters, numbers, underscores), singular and plural labels, and the post types it attaches to. Optionally set hierarchical and public flags. The taxonomy is registered on the next request. Note: this changes site configuration; it does not add terms (use novamira/voxel-create-term for that).',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'Taxonomy key, e.g. "cuisine". Lowercase a-z, 0-9, hyphen, underscore; must contain a letter; max 32.',
            ],
            'singular' => ['type' => 'string', 'description' => 'Singular label, e.g. "Cuisine".'],
            'plural' => ['type' => 'string', 'description' => 'Plural label, e.g. "Cuisines".'],
            'post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Post type keys this taxonomy is attached to (at least one existing type).',
            ],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Whether terms can have parents (default true).'],
            'publicly_queryable' => [
                'type' => 'boolean',
                'description' => 'Whether the taxonomy has public archive pages.',
            ],
        ],
        'required' => ['key', 'singular', 'plural', 'post_types'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
            'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'registers_on_next_request' => ['type' => 'boolean'],
        ],
        'required' => ['key', 'singular', 'plural', 'post_types', 'registers_on_next_request'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_create_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Site-configuration change. The taxonomy is live on the next request (not the same call). Rejects reserved/duplicate keys and keys already registered.',
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
function voxel_create_taxonomy(array $input): array|WP_Error
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
    $post_types = vx_valid_post_type_list($input['post_types'] ?? null);
    if ($post_types === []) {
        return vx_invalid(__('At least one existing post type is required.', domain: 'novamira-pro'));
    }

    return vx_with_lock('taxonomies', static function () use (
        $input,
        $key,
        $singular,
        $plural,
        $post_types,
    ): array|WP_Error {
        $all = vx_read_taxonomies_config();
        $invalid = vx_validate_new_key($key, max_len: 32, reserved: vx_reserved_taxonomy_keys(), existing: $all);
        if ($invalid instanceof WP_Error) {
            return $invalid;
        }
        if (taxonomy_exists($key)) {
            return vx_invalid(sprintf(
                /* translators: %s taxonomy key. */
                __('A taxonomy "%s" is already registered.', domain: 'novamira-pro'),
                $key,
            ));
        }

        $all[$key] = ['settings' => vx_new_taxonomy_settings($key, $singular, $plural, $post_types, $input)];
        vx_write_taxonomies_config($all);
        // Deferred: the rules WordPress would build right now still describe the
        // configuration as it was when init ran. See index-maintenance.php.
        vx_mark_rewrite_pending();

        return [
            'key' => $key,
            'singular' => $singular,
            'plural' => $plural,
            'post_types' => $post_types,
            'registers_on_next_request' => true,
        ];
    });
}

/**
 * Assemble the stored taxonomy `settings` slice, mapping the optional boolean
 * flags to Voxel's `yes`/`no` string form.
 *
 * @param list<string> $post_types
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>
 */
function vx_new_taxonomy_settings(string $key, string $singular, string $plural, array $post_types, array $input): array
{
    $settings = ['key' => $key, 'singular' => $singular, 'plural' => $plural, 'post_type' => $post_types];
    if (array_key_exists('hierarchical', $input)) {
        $settings['hierarchical'] = $input['hierarchical'] === true ? 'yes' : 'no';
    }
    if (array_key_exists('publicly_queryable', $input)) {
        $settings['publicly_queryable'] = $input['publicly_queryable'] === true ? 'yes' : 'no';
    }
    return $settings;
}

/**
 * Coerce a post-types input into a list of Voxel-managed post type keys. A key
 * counts only when it is present in the voxel:post_types config blob, which
 * excludes core/private types like page, attachment and wp_template (attaching a
 * Voxel taxonomy to those is meaningless) and includes a type created in an
 * earlier request that has not registered yet. Nonexistent, non-string and
 * duplicate entries are dropped.
 *
 * @param mixed $value
 * @return list<string>
 */
function vx_valid_post_type_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $managed = vx_read_post_types_config();
    $out = [];
    /** @var mixed $item */
    foreach ($value as $item) {
        if (!is_string($item) || $item === '' || !isset($managed[$item]) || in_array($item, $out, strict: true)) {
            continue;
        }
        $out[] = $item;
    }
    return $out;
}
