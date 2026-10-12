<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-create-taxonomy', [
    'label' => __('Create Meta Box Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new custom taxonomy via MB Custom Post Type. Required: slug (also accepted as taxonomy, WordPress-native alias) and title (also accepted as singular_name). Optional: object_type (array of post type slugs to attach to), hierarchical (default false), public (default true), show_in_rest (default true), labels, rewrite. Rewrite rules are soft-flushed on create/edit/delete, so the new URLs resolve from the NEXT request; on CDN/edge-cached sites a 404 fetched before that may linger in cache until purged.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'Taxonomy slug. 1–32 chars.'],
            'taxonomy' => ['type' => 'string', 'description' => 'WordPress-native alias for slug.'],
            'title' => ['type' => 'string', 'description' => 'Singular label.'],
            'singular_name' => ['type' => 'string', 'description' => 'WordPress-native alias for title.'],
            'object_type' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Post type slugs to attach this taxonomy to.',
            ],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Default false.'],
            'public' => ['type' => 'boolean', 'description' => 'Default true.'],
            'show_in_rest' => ['type' => 'boolean', 'description' => 'Default true.'],
            'labels' => ['type' => 'object'],
            'rewrite' => ['type' => 'object'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'source' => ['type' => 'string', 'const' => 'db'],
            'db_post_id' => ['type' => 'integer'],
        ],
        'required' => ['slug', 'title', 'source', 'db_post_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_create_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a new custom taxonomy via MB Custom Post Type. slug (or taxonomy) and title (or singular_name) are the minimum. Attach to post types via object_type[].',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/metabox-edit-taxonomy', [
    'label' => __('Edit Meta Box Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing MB Custom Post Type taxonomy. Required: slug (or taxonomy alias) to identify the record. All other fields follow PATCH semantics — omitted keys are preserved.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'slug' => ['type' => 'string'],
            'taxonomy' => ['type' => 'string', 'description' => 'WordPress-native alias for slug.'],
            'title' => ['type' => 'string'],
            'singular_name' => ['type' => 'string', 'description' => 'WordPress-native alias for title.'],
            'object_type' => ['type' => 'array', 'items' => ['type' => 'string']],
            'hierarchical' => ['type' => 'boolean'],
            'public' => ['type' => 'boolean'],
            'show_in_rest' => ['type' => 'boolean'],
            'labels' => ['type' => 'object'],
            'rewrite' => ['type' => 'object'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'source' => ['type' => 'string', 'const' => 'db'],
            'db_post_id' => ['type' => 'integer'],
        ],
        'required' => ['slug', 'title', 'source', 'db_post_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_edit_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PATCH semantics: omitted keys are preserved. Changing slug does not migrate existing terms.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/metabox-delete-taxonomy', [
    'label' => __('Delete Meta Box Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an MB Custom Post Type taxonomy. Pass force=true to delete even when terms exist under that taxonomy. Idempotent: deleting a non-existent slug returns deleted=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'slug' => ['type' => 'string'],
            'taxonomy' => ['type' => 'string', 'description' => 'WordPress-native alias for slug.'],
            'force' => ['type' => 'boolean', 'description' => 'Delete even if terms exist. Default false.'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'slug' => ['type' => 'string'],
            'orphaned_terms_count' => ['type' => 'integer'],
        ],
        'required' => ['deleted', 'slug'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_delete_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanently deletes an MB CPT taxonomy definition. Existing terms are not removed. Pass force=true only with explicit user confirmation.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Normalize WP-native taxonomy aliases (taxonomy → slug, singular_name → title).
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_normalize_tax_input(array $args): array
{
    if (!isset($args['slug']) && isset($args['taxonomy'])) {
        $args['slug'] = $args['taxonomy'];
    }
    if (!isset($args['title']) && isset($args['singular_name'])) {
        $args['title'] = $args['singular_name'];
    }
    return $args;
}

/**
 * @param array<string, mixed> $args
 * @return array{slug: string, title: string, source: 'db', db_post_id: int}|WP_Error
 */
function metabox_create_taxonomy(array $args): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    $args = mb_normalize_tax_input($args);

    /** @var mixed $slug_raw */
    $slug_raw = $args['slug'] ?? null;
    if (!is_string($slug_raw) || $slug_raw === '') {
        return new WP_Error('metabox_invalid_input', __('slug (or taxonomy) is required.', domain: 'novamira-pro'));
    }
    $slug = sanitize_key($slug_raw);

    $existing = mb_find_cpt_post($slug, post_type: 'mb-taxonomy');
    if ($existing !== null) {
        return new WP_Error('metabox_slug_exists', sprintf(
            /* translators: %s: slug. */
            __('A taxonomy with slug "%s" already exists.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    /** @var mixed $title_raw */
    $title_raw = $args['title'] ?? $slug;
    $title = is_string($title_raw) ? $title_raw : $slug;

    $settings = mb_build_taxonomy_settings($slug, $title, $args);

    $settings_json = wp_json_encode($settings, flags: JSON_UNESCAPED_UNICODE);
    if ($settings_json === false) {
        return new WP_Error('metabox_encode_failed', __('Failed to encode taxonomy settings.', domain: 'novamira-pro'));
    }

    $post_id = wp_insert_post([
        'post_type' => 'mb-taxonomy',
        'post_title' => $title,
        'post_status' => 'publish',
        'post_name' => $slug,
        'post_content' => $settings_json,
    ], wp_error: true);

    if ($post_id instanceof WP_Error) {
        return $post_id;
    }

    mb_soft_flush_rewrite_rules();
    return ['slug' => $slug, 'title' => $title, 'source' => 'db', 'db_post_id' => $post_id];
}

/**
 * @param array<string, mixed> $args
 * @return array{slug: string, title: string, source: 'db', db_post_id: int}|WP_Error
 */
function metabox_edit_taxonomy(array $args): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    $args = mb_normalize_tax_input($args);

    /** @var mixed $slug_raw */
    $slug_raw = $args['slug'] ?? null;
    if (!is_string($slug_raw) || $slug_raw === '') {
        return new WP_Error('metabox_invalid_input', __('slug (or taxonomy) is required.', domain: 'novamira-pro'));
    }
    $slug = $slug_raw;

    $post = mb_find_cpt_post($slug, post_type: 'mb-taxonomy');
    if ($post === null) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: slug. */
            __('Taxonomy "%s" not found.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    $existing_settings = mb_decode_cpt_post_settings($post) ?? [];
    $title = mb_apply_tax_patches($args, $post->post_title, $existing_settings);

    $settings_json = wp_json_encode($existing_settings, flags: JSON_UNESCAPED_UNICODE);
    if ($settings_json === false) {
        return new WP_Error('metabox_encode_failed', __('Failed to encode taxonomy settings.', domain: 'novamira-pro'));
    }

    $result = wp_update_post([
        'ID' => $post->ID,
        'post_title' => $title,
        'post_content' => $settings_json,
    ], wp_error: true);

    if ($result instanceof WP_Error) {
        return $result;
    }

    mb_soft_flush_rewrite_rules();
    return ['slug' => $slug, 'title' => $title, 'source' => 'db', 'db_post_id' => $post->ID];
}

/**
 * Apply patch fields from $args onto $existing_settings (passed by reference).
 * Returns the resolved post title.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing_settings
 */
function mb_apply_tax_patches(array $args, string $current_title, array &$existing_settings): string
{
    $title = mb_apply_tax_title_patch($args, $current_title, $existing_settings);
    mb_apply_tax_scalar_patches($args, $existing_settings);
    return $title;
}

/**
 * Apply title + singular_name label patch. Returns resolved title.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing_settings
 */
function mb_apply_tax_title_patch(array $args, string $current_title, array &$existing_settings): string
{
    /** @var mixed $title_raw */
    $title_raw = $args['title'] ?? null;
    if ($title_raw !== null && is_string($title_raw)) {
        /** @var array<string, mixed> $labels */
        $labels = is_array($existing_settings['labels'] ?? null) ? $existing_settings['labels'] : [];
        $labels['singular_name'] = $title_raw;
        $existing_settings['labels'] = $labels;
    }
    return $title_raw !== null && is_string($title_raw) ? $title_raw : $current_title;
}

/**
 * Apply scalar, array, and label patches (everything except title).
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing_settings
 */
function mb_apply_tax_scalar_patches(array $args, array &$existing_settings): void
{
    foreach (['hierarchical', 'public', 'show_in_rest'] as $k) {
        if (!isset($args[$k])) {
            continue;
        }
        $existing_settings[$k] = $args[$k];
    }
    if (isset($args['object_type']) && is_array($args['object_type'])) {
        // Persist under MB's real `types` key (see mb_build_taxonomy_settings)
        // and drop the legacy `object_type` key MB ignores, so re-saving an old
        // taxonomy that was stored under the wrong key repairs the link.
        $existing_settings['types'] = array_values(array_filter($args['object_type'], callback: 'is_string'));
        unset($existing_settings['object_type']);
    }
    if (isset($args['rewrite']) && is_array($args['rewrite'])) {
        $existing_settings['rewrite'] = $args['rewrite'];
    }
    if (isset($args['labels']) && is_array($args['labels'])) {
        /** @var array<string, mixed> $existing_labels */
        $existing_labels = is_array($existing_settings['labels'] ?? null) ? $existing_settings['labels'] : [];
        $existing_settings['labels'] = array_merge($existing_labels, $args['labels']);
    }
}

/**
 * @param array{slug?: string, taxonomy?: string, force?: bool} $args
 * @return array{deleted: bool, slug: string, orphaned_terms_count?: int}|WP_Error
 */
function metabox_delete_taxonomy(array $args): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    $args = mb_normalize_tax_input($args);

    /** @var mixed $slug_raw */
    $slug_raw = $args['slug'] ?? null;
    if (!is_string($slug_raw) || $slug_raw === '') {
        return new WP_Error('metabox_invalid_input', __('slug (or taxonomy) is required.', domain: 'novamira-pro'));
    }
    $slug = $slug_raw;
    /** @var mixed $force_raw */
    $force_raw = $args['force'] ?? false;
    $force = is_bool($force_raw) ? $force_raw : false;

    $post = mb_find_cpt_post($slug, post_type: 'mb-taxonomy');
    if ($post === null) {
        return ['deleted' => false, 'slug' => $slug];
    }

    // Count terms before deletion.
    $term_count = 0;
    $tax_obj = get_taxonomy($slug);
    if ($tax_obj !== false) {
        $term_count = (int) wp_count_terms(['taxonomy' => $slug, 'hide_empty' => false]);
    }

    if ($term_count > 0 && !$force) {
        return new WP_Error('metabox_delete_blocked', sprintf(
            /* translators: 1: count, 2: slug. */
            __('%1$d terms exist in taxonomy "%2$s"; pass force=true to delete anyway.', domain: 'novamira-pro'),
            $term_count,
            $slug,
        ));
    }

    $deleted = wp_delete_post($post->ID, force_delete: true);
    if ($deleted !== false && $deleted !== null) {
        mb_soft_flush_rewrite_rules();
    }
    $out = ['deleted' => $deleted !== false && $deleted !== null, 'slug' => $slug];
    if ($term_count > 0) {
        $out['orphaned_terms_count'] = $term_count;
    }
    return $out;
}

/**
 * Build the MB CPT taxonomy settings array.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_build_taxonomy_settings(string $slug, string $title, array $args): array
{
    /** @var mixed $labels_raw */
    $labels_raw = $args['labels'] ?? [];
    $labels = is_array($labels_raw) ? $labels_raw : [];
    if (!isset($labels['singular_name'])) {
        $labels['singular_name'] = $title;
    }
    if (!isset($labels['name'])) {
        $labels['name'] = $title . 's';
    }

    /** @var mixed $hier_raw */
    $hier_raw = $args['hierarchical'] ?? false;
    /** @var mixed $public_raw */
    $public_raw = $args['public'] ?? true;
    /** @var mixed $sir_raw */
    $sir_raw = $args['show_in_rest'] ?? true;

    $settings = [
        'slug' => $slug,
        'labels' => $labels,
        'hierarchical' => is_bool($hier_raw) ? $hier_raw : false,
        'public' => is_bool($public_raw) ? $public_raw : true,
        'show_in_rest' => is_bool($sir_raw) ? $sir_raw : true,
    ];

    // MB Custom Post Type's TaxonomyRegister reads the `types` key from the
    // mb-taxonomy post_content — register_taxonomy($slug, $args['types'], $args).
    // `object_type` is the runtime WP_Taxonomy property name (and the agent-
    // facing input name), NOT the persisted key; writing it leaves the taxonomy
    // attached to zero post types. Persist the association under MB's `types`.
    $settings['types'] = isset($args['object_type']) && is_array($args['object_type'])
        ? array_values(array_filter($args['object_type'], callback: 'is_string'))
        : [];

    if (isset($args['rewrite']) && is_array($args['rewrite'])) {
        $settings['rewrite'] = $args['rewrite'];
    }

    return $settings;
}
