<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;

/**
 * Abilities: manage the custom post types Etch registers from the etch_cpts
 * option (map { slug => register_post_type args }). The slug is caller-chosen
 * (a-z, 0-9, _ -, <=20 chars). CRUD is guarded by the etch_option_lock.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The etch_cpts option as a normalized map { slug => args }.
 *
 * @return array<array-key, mixed>
 */
function etch_cpts_option(): array
{
    return etch_option_array('etch_cpts');
}

/**
 * A valid post-type slug from input, or a WP_Error. Mirrors WP's constraints:
 * lowercase alphanumerics with _ or -, starting with a letter, max 20 chars.
 *
 * @return string|WP_Error
 */
function etch_cpt_slug_or_error(mixed $slug): string|WP_Error
{
    if (!is_string($slug) || $slug === '') {
        return etch_error('etch_invalid_input', __('A non-empty "slug" is required.', domain: 'novamira-pro'));
    }

    if (strlen($slug) > 20 || preg_match('#^[a-z][a-z0-9_-]*$#D', $slug) !== 1) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: the offending slug. */
            __(
                'Slug "%s" is invalid; use 1-20 lowercase letters, digits, "_" or "-", starting with a letter.',
                domain: 'novamira-pro',
            ),
            $slug,
        ));
    }

    return $slug;
}

/**
 * Resolve a cpt slug within the given map, or a WP_Error.
 *
 * @param array<array-key, mixed> $cpts
 * @return array<array-key, mixed>|WP_Error
 */
function etch_cpt_or_error(array $cpts, string $slug): array|WP_Error
{
    if (!isset($cpts[$slug])) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %s: post type slug. */
            __('Custom post type "%s" was not found.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    /** @var mixed $args */
    $args = $cpts[$slug];

    return is_array($args) ? $args : [];
}

/**
 * Validate a register_post_type-style args object (array within the size caps).
 *
 * @return array<array-key, mixed>|WP_Error
 */
function etch_cpt_args_or_error(mixed $args): array|WP_Error
{
    if (!is_array($args)) {
        return etch_error('etch_invalid_input', __('"args" must be an object.', domain: 'novamira-pro'));
    }

    $encoded = wp_json_encode($args, flags: 0, depth: ETCH_MAX_ATTRS_DEPTH);
    if (!is_string($encoded) || strlen($encoded) > ETCH_MAX_ATTRS_BYTES) {
        return etch_error('etch_args_too_large', __(
            '"args" is too large or too deeply nested to store safely.',
            domain: 'novamira-pro',
        ));
    }

    return $args;
}

/**
 * Read the label out of a stored cpt args array.
 *
 * @param array<array-key, mixed> $args
 */
function etch_cpt_label(array $args): string
{
    /** @var mixed $label */
    $label = $args['label'] ?? '';

    return is_string($label) ? $label : '';
}

/**
 * Flag that permalinks need flushing on the next request. Etch registers CPTs
 * and taxonomies on `init`; a freshly created/edited/deleted type only gets
 * working archive/term permalinks once rewrite rules are flushed. We mirror
 * Etch's own CustomPostTypeService::set_etch_transient() instead of calling
 * flush_rewrite_rules() inline (which is expensive and unsafe mid-request).
 */
function etch_set_flush_rewrite_transient(): void
{
    set_transient('etch_flush_rewrite_rules', value: true, expiration: 300);
}

// ---------------------------------------------------------------------------
// list-post-types
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-post-types', [
    'label' => __('List Etch Post Types', domain: 'novamira-pro'),
    'description' => __(
        'Lists the custom post types Etch registers (the etch_cpts option). Returns a compact entry per type { slug, label }. Fetch the full register_post_type args with novamira/etch-get-post-type.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'count' => ['type' => 'integer'],
            'post_types' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_post_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'These are Etch-managed CPTs only (not all WordPress post types). Use the slug with novamira/etch-get-post-type for the full args.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, post_types: list<array{slug: string, label: string}>}
 */
function etch_list_post_types(): array
{
    $cpts = etch_cpts_option();

    $out = [];
    foreach (array_keys($cpts) as $slug) {
        // A numeric-looking slug key would arrive as an int; skip non-string
        // keys so the output never carries a coerced/invalid post type slug.
        if (!is_string($slug)) {
            continue;
        }
        /** @var mixed $args */
        $args = $cpts[$slug];
        if (!is_array($args)) {
            continue;
        }

        $out[] = [
            'slug' => $slug,
            'label' => etch_cpt_label($args),
        ];
    }

    return [
        'count' => count($out),
        'post_types' => $out,
    ];
}

// ---------------------------------------------------------------------------
// get-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-post-type', [
    'label' => __('Get Etch Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Etch custom post type in full: slug and args (the register_post_type configuration, e.g. label, public, show_in_rest, supports, has_archive).',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'The post type slug.'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'args' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a post type before editing its args with novamira/etch-edit-post-type.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{slug: string, args: array<array-key, mixed>}|WP_Error
 */
function etch_get_post_type(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $slug = etch_cpt_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    $cpts = etch_cpts_option();
    $args = etch_cpt_or_error($cpts, $slug);
    if ($args instanceof WP_Error) {
        return $args;
    }

    return ['slug' => $slug, 'args' => $args];
}

// ---------------------------------------------------------------------------
// create-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-post-type', [
    'label' => __('Create Etch Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Registers a new Etch custom post type. Requires slug (1-20 lowercase letters/digits/_/-) and label; args are extra register_post_type options (public and show_in_rest default to true). Errors if the slug already exists. Returns slug and label.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'The post type slug (1-20 chars).'],
            'label' => ['type' => 'string', 'description' => 'The human label.'],
            'args' => ['type' => 'object', 'description' => 'Extra register_post_type args.'],
        ],
        'required' => ['slug', 'label'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'label' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The slug is permanent and must be unique. Pass register_post_type options in args (supports, has_archive, hierarchical, menu_icon, …); public and show_in_rest default to true so the type is usable and REST-visible.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{slug: string, label: string}|WP_Error
 */
function etch_create_post_type(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $slug = etch_cpt_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    // Reject slugs that already exist as a WordPress post type (a core built-in
    // like "post"/"page", or another plugin's type) — registering over them
    // silently corrupts the existing type.
    if (post_type_exists($slug)) {
        return etch_error('etch_exists', sprintf(
            /* translators: %s: post type slug. */
            __('"%s" is already a registered post type and cannot be re-created.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    /** @var mixed $label */
    $label = $input['label'] ?? '';
    if (!is_string($label) || trim($label) === '') {
        return etch_error('etch_invalid_input', __('A non-empty "label" is required.', domain: 'novamira-pro'));
    }

    $args = etch_cpt_args_or_error($input['args'] ?? []);
    if ($args instanceof WP_Error) {
        return $args;
    }

    $clean_label = sanitize_text_field($label);
    $args['label'] = $clean_label;
    if (!isset($args['public'])) {
        $args['public'] = true;
    }
    if (!isset($args['show_in_rest'])) {
        $args['show_in_rest'] = true;
    }

    $lock = etch_option_lock_acquire('etch_cpts');
    try {
        $cpts = etch_cpts_option();
        if (isset($cpts[$slug])) {
            return etch_error('etch_exists', sprintf(
                /* translators: %s: post type slug. */
                __('Custom post type "%s" already exists.', domain: 'novamira-pro'),
                $slug,
            ));
        }
        $cpts[$slug] = $args;
        update_option('etch_cpts', $cpts);
    } finally {
        etch_option_lock_release($lock);
    }

    etch_set_flush_rewrite_transient();

    return ['slug' => $slug, 'label' => $clean_label];
}

// ---------------------------------------------------------------------------
// edit-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-post-type', [
    'label' => __('Edit Etch Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Etch custom post type (partial update of its args). Provide slug and any of label or args (args merge into the stored register_post_type config). Returns slug and label.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'args' => ['type' => 'object'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'label' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial: args you pass are merged over the existing args; pass label to update the label. The slug cannot change.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{slug: string, label: string}|WP_Error
 */
function etch_edit_post_type(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $slug = etch_cpt_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    $merge = [];
    if (isset($input['args'])) {
        $merge = etch_cpt_args_or_error($input['args']);
        if ($merge instanceof WP_Error) {
            return $merge;
        }
    }

    $new_label = null;
    if (isset($input['label'])) {
        /** @var mixed $label */
        $label = $input['label'];
        if (!is_string($label) || trim($label) === '') {
            return etch_error('etch_invalid_input', __('"label" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $new_label = sanitize_text_field($label);
    }

    $result = ['slug' => $slug, 'label' => ''];
    $lock = etch_option_lock_acquire('etch_cpts');
    try {
        $cpts = etch_cpts_option();
        $args = etch_cpt_or_error($cpts, $slug);
        if ($args instanceof WP_Error) {
            return $args;
        }

        $args = array_merge($args, $merge);
        if ($new_label !== null) {
            $args['label'] = $new_label;
        }
        $cpts[$slug] = $args;
        update_option('etch_cpts', $cpts);

        $result = ['slug' => $slug, 'label' => etch_cpt_label($args)];
    } finally {
        etch_option_lock_release($lock);
    }

    etch_set_flush_rewrite_transient();

    return $result;
}

// ---------------------------------------------------------------------------
// delete-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-post-type', [
    'label' => __('Delete Etch Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an Etch custom post type registration by slug. Existing posts of that type remain in the database but the type is no longer registered. Returns slug and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE for the registration only — existing posts of this type are orphaned (not deleted). Re-creating the slug restores access to them.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{slug: string, deleted: bool}|WP_Error
 */
function etch_delete_post_type(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $slug = etch_cpt_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    $lock = etch_option_lock_acquire('etch_cpts');
    try {
        $cpts = etch_cpts_option();
        $args = etch_cpt_or_error($cpts, $slug);
        if ($args instanceof WP_Error) {
            return $args;
        }

        unset($cpts[$slug]);
        update_option('etch_cpts', $cpts);
    } finally {
        etch_option_lock_release($lock);
    }

    etch_set_flush_rewrite_transient();

    return ['slug' => $slug, 'deleted' => true];
}
