<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-create-post-type', [
    'label' => __('Create Meta Box Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new post type via MB Custom Post Type. Required: slug (also accepted as post_type, WordPress-native alias for slug) and title (also accepted as singular_name). Visibility controls include public (default true), show_ui, show_in_menu, publicly_queryable, exclude_from_search, has_archive, show_in_nav_menus, and show_in_admin_bar. Visibility fields other than public are persisted only when supplied, preserving their WordPress defaults. Other options include labels, hierarchical, supports, menu_icon, rewrite, show_in_rest, and taxonomies. Reserved slugs (post, page, meta-box, mb-post-type, mb-taxonomy, mb-settings-page, mb-relationship) are rejected. Rewrite rules are soft-flushed on create/edit/delete, so the new URLs resolve from the NEXT request; on CDN/edge-cached sites a 404 fetched before that may linger in cache until purged.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'Post type slug. 1–20 chars, lowercase alphanumeric + hyphens/underscores.',
            ],
            'post_type' => ['type' => 'string', 'description' => 'WordPress-native alias for slug.'],
            'title' => [
                'type' => 'string',
                'description' => 'Singular label used as the admin page title and as labels.singular_name.',
            ],
            'singular_name' => ['type' => 'string', 'description' => 'WordPress-native alias for title.'],
            'labels' => [
                'type' => 'object',
                'description' => 'Full labels array (name, singular_name, menu_name, etc.). Merged with auto-generated defaults.',
            ],
            'public' => ['type' => 'boolean', 'description' => 'Default true.'],
            'show_ui' => [
                'type' => 'boolean',
                'description' => 'Whether to generate an admin UI; defaults to public.',
            ],
            'show_in_menu' => [
                'type' => ['boolean', 'string'],
                'description' => 'Whether or where to show the post type in the admin menu; defaults to show_ui. A string names a parent menu slug.',
            ],
            'publicly_queryable' => [
                'type' => 'boolean',
                'description' => 'Whether front-end queries are allowed; defaults to public.',
            ],
            'exclude_from_search' => [
                'type' => 'boolean',
                'description' => 'Whether to exclude posts from front-end search; defaults to the inverse of public.',
            ],
            'has_archive' => [
                'type' => ['boolean', 'string'],
                'description' => 'Whether to enable an archive; defaults to false. A string sets the archive slug.',
            ],
            'show_in_nav_menus' => [
                'type' => 'boolean',
                'description' => 'Whether the post type is selectable in navigation menus; defaults to public.',
            ],
            'show_in_admin_bar' => [
                'type' => 'boolean',
                'description' => 'Whether the post type appears in the admin bar; defaults to show_in_menu.',
            ],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Default false.'],
            'supports' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Supported features: title, editor, thumbnail, excerpt, comments, etc.',
            ],
            'menu_icon' => ['type' => 'string', 'description' => 'Dashicons slug or URL.'],
            'show_in_rest' => ['type' => 'boolean', 'description' => 'Default true.'],
            'rewrite' => ['type' => 'object', 'description' => 'Rewrite options: {slug, with_front}.'],
            'taxonomies' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Taxonomy slugs to associate.',
            ],
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
    'execute_callback' => __NAMESPACE__ . '\\metabox_create_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a new post type via MB Custom Post Type. slug (or post_type) and title (or singular_name) are the minimal required inputs. Reserved WP slugs and Meta Box internal CPT slugs are rejected. Slug must be 1–20 characters.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/metabox-edit-post-type', [
    'label' => __('Edit Meta Box Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing MB Custom Post Type entry. Required: slug (or post_type as alias) to identify the record. All other fields follow PATCH semantics — omitted keys are preserved. Supports public, show_ui, show_in_menu, publicly_queryable, exclude_from_search, has_archive, show_in_nav_menus, and show_in_admin_bar visibility arguments. Changing the slug renames the post type but does NOT migrate existing posts.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'Slug of the post type to edit.'],
            'post_type' => ['type' => 'string', 'description' => 'WordPress-native alias for slug.'],
            'title' => ['type' => 'string'],
            'singular_name' => ['type' => 'string', 'description' => 'WordPress-native alias for title.'],
            'labels' => ['type' => 'object'],
            'public' => ['type' => 'boolean'],
            'show_ui' => [
                'type' => 'boolean',
                'description' => 'Whether to generate an admin UI; defaults to public.',
            ],
            'show_in_menu' => [
                'type' => ['boolean', 'string'],
                'description' => 'Whether or where to show the post type in the admin menu; defaults to show_ui. A string names a parent menu slug.',
            ],
            'publicly_queryable' => [
                'type' => 'boolean',
                'description' => 'Whether front-end queries are allowed; defaults to public.',
            ],
            'exclude_from_search' => [
                'type' => 'boolean',
                'description' => 'Whether to exclude posts from front-end search; defaults to the inverse of public.',
            ],
            'has_archive' => [
                'type' => ['boolean', 'string'],
                'description' => 'Whether to enable an archive; defaults to false. A string sets the archive slug.',
            ],
            'show_in_nav_menus' => [
                'type' => 'boolean',
                'description' => 'Whether the post type is selectable in navigation menus; defaults to public.',
            ],
            'show_in_admin_bar' => [
                'type' => 'boolean',
                'description' => 'Whether the post type appears in the admin bar; defaults to show_in_menu.',
            ],
            'hierarchical' => ['type' => 'boolean'],
            'supports' => ['type' => 'array', 'items' => ['type' => 'string']],
            'menu_icon' => ['type' => 'string'],
            'show_in_rest' => ['type' => 'boolean'],
            'rewrite' => ['type' => 'object'],
            'taxonomies' => ['type' => 'array', 'items' => ['type' => 'string']],
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
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['slug', 'title', 'source', 'db_post_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_edit_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Edits an MB CPT post type entry. PATCH semantics: fields you omit are preserved. Changing slug does not migrate existing posts — a warning is returned when posts exist under the old slug.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/metabox-delete-post-type', [
    'label' => __('Delete Meta Box Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an MB Custom Post Type entry. Pass force=true to delete even when posts of that type exist (posts are orphaned in the database with the old post_type value). Idempotent: deleting a non-existent slug returns deleted=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'Post type slug to delete.'],
            'post_type' => ['type' => 'string', 'description' => 'WordPress-native alias for slug.'],
            'force' => [
                'type' => 'boolean',
                'description' => 'When true, deletes even if posts of this type exist. Default false.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'slug' => ['type' => 'string'],
            'orphaned_posts_count' => ['type' => 'integer'],
        ],
        'required' => ['deleted', 'slug'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_delete_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanently deletes an MB CPT post type definition. Existing posts are NOT deleted but become orphaned. Pass force=true only with explicit user confirmation that orphaned posts are acceptable.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Normalize WP-native aliases (post_type → slug, singular_name → title).
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_normalize_cpt_input(array $args): array
{
    if (!isset($args['slug']) && isset($args['post_type'])) {
        $args['slug'] = $args['post_type'];
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
function metabox_create_post_type(array $args): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    $args = mb_normalize_cpt_input($args);

    /** @var mixed $slug_raw */
    $slug_raw = $args['slug'] ?? null;
    if (!is_string($slug_raw) || $slug_raw === '') {
        return new WP_Error('metabox_invalid_input', __('slug (or post_type) is required.', domain: 'novamira-pro'));
    }
    $slug = sanitize_key($slug_raw);

    if (strlen($slug) > 20) {
        return new WP_Error('metabox_invalid_input', __(
            'Post type slug must not exceed 20 characters.',
            domain: 'novamira-pro',
        ));
    }

    if (in_array($slug, mb_reserved_post_type_slugs(), strict: true)) {
        return new WP_Error('metabox_reserved_slug', sprintf(
            /* translators: %s: slug. */
            __('"%s" is a reserved slug and cannot be used.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    // Check for collision.
    $existing = mb_find_cpt_post($slug, post_type: 'mb-post-type');
    if ($existing !== null) {
        return new WP_Error('metabox_slug_exists', sprintf(
            /* translators: %s: slug. */
            __('A post type with slug "%s" already exists.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    /** @var mixed $title_raw */
    $title_raw = $args['title'] ?? $slug;
    $title = is_string($title_raw) ? $title_raw : $slug;

    $settings = mb_build_cpt_settings($slug, $title, $args);

    $settings_json = wp_json_encode($settings, flags: JSON_UNESCAPED_UNICODE);
    if ($settings_json === false) {
        return new WP_Error('metabox_encode_failed', __(
            'Failed to encode post type settings.',
            domain: 'novamira-pro',
        ));
    }

    $post_id = wp_insert_post([
        'post_type' => 'mb-post-type',
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
 * @return array{slug: string, title: string, source: 'db', db_post_id: int, warnings: list<string>}|WP_Error
 */
function metabox_edit_post_type(array $args): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    $args = mb_normalize_cpt_input($args);

    /** @var mixed $slug_raw */
    $slug_raw = $args['slug'] ?? null;
    if (!is_string($slug_raw) || $slug_raw === '') {
        return new WP_Error('metabox_invalid_input', __('slug (or post_type) is required.', domain: 'novamira-pro'));
    }
    $slug = $slug_raw;

    $post = mb_find_cpt_post($slug, post_type: 'mb-post-type');
    if ($post === null) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: slug. */
            __('Post type "%s" not found.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    $existing_settings = mb_decode_cpt_post_settings($post) ?? [];
    $title = mb_apply_cpt_patches($args, $post->post_title, $existing_settings);

    $settings_json = wp_json_encode($existing_settings, flags: JSON_UNESCAPED_UNICODE);
    if ($settings_json === false) {
        return new WP_Error('metabox_encode_failed', __(
            'Failed to encode post type settings.',
            domain: 'novamira-pro',
        ));
    }

    $result = wp_update_post([
        'ID' => $post->ID,
        'post_title' => $title,
        'post_content' => $settings_json,
    ], wp_error: true);

    if ($result instanceof WP_Error) {
        return $result;
    }

    $warnings = mb_cpt_edit_warnings($slug);

    mb_soft_flush_rewrite_rules();
    return ['slug' => $slug, 'title' => $title, 'source' => 'db', 'db_post_id' => $post->ID, 'warnings' => $warnings];
}

/**
 * Apply patch fields from $args onto $existing_settings (by reference).
 * Returns the resolved post title.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing_settings
 */
function mb_apply_cpt_patches(array $args, string $current_title, array &$existing_settings): string
{
    $title = mb_apply_cpt_title_patch($args, $current_title, $existing_settings);
    mb_apply_cpt_scalar_patches($args, $existing_settings);
    return $title;
}

/**
 * Apply title + singular_name label patch. Returns resolved title.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing_settings
 */
function mb_apply_cpt_title_patch(array $args, string $current_title, array &$existing_settings): string
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
 * Apply scalar, array-type and label patches (everything except title).
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing_settings
 */
function mb_apply_cpt_scalar_patches(array $args, array &$existing_settings): void
{
    foreach ([
        'public',
        'show_ui',
        'show_in_menu',
        'publicly_queryable',
        'exclude_from_search',
        'has_archive',
        'show_in_nav_menus',
        'show_in_admin_bar',
        'hierarchical',
        'menu_icon',
        'show_in_rest',
    ] as $k) {
        if (!array_key_exists($k, $args) || !mb_is_valid_cpt_scalar_patch($k, $args[$k])) {
            continue;
        }
        $existing_settings[$k] = $args[$k];
    }
    foreach (['supports', 'taxonomies'] as $k) {
        if (!isset($args[$k]) || !is_array($args[$k])) {
            continue;
        }
        $existing_settings[$k] = array_values(array_filter($args[$k], callback: 'is_string'));
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

function mb_is_valid_cpt_scalar_patch(string $key, mixed $value): bool
{
    return match ($key) {
        'show_in_menu', 'has_archive' => is_bool($value) || is_string($value),
        'menu_icon' => is_string($value),
        default => is_bool($value),
    };
}

/**
 * Count published posts of a CPT without warning when the post type is not
 * currently registered. `wp_count_posts()` returns a stdClass populated from
 * `get_post_stati()` which omits stati for unregistered types — accessing
 * `->publish` directly throws "Undefined property" in PHP 8+.
 */
function mb_cpt_published_count(string $slug): int
{
    /** @var mixed $counts */
    $counts = wp_count_posts($slug);
    if (!is_object($counts)) {
        return 0;
    }
    /** @var mixed $publish */
    $publish = $counts->publish ?? null;
    return is_numeric($publish) ? (int) $publish : 0;
}

/**
 * Build warnings list for a CPT edit (e.g. orphan warning if posts exist).
 *
 * @return list<string>
 */
function mb_cpt_edit_warnings(string $slug): array
{
    $warnings = [];
    $post_count = mb_cpt_published_count($slug);
    if ($post_count > 0) {
        $warnings[] = sprintf(
            /* translators: %1$d: post count, %2$s: slug. */
            __('%1$d posts exist under post type "%2$s". Changing the slug orphans them.', domain: 'novamira-pro'),
            $post_count,
            $slug,
        );
    }
    return $warnings;
}

/**
 * @param array{slug?: string, force?: bool, post_type?: string} $args
 * @return array{deleted: bool, slug: string, orphaned_posts_count?: int}|WP_Error
 */
function metabox_delete_post_type(array $args): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    $args = mb_normalize_cpt_input($args);

    /** @var mixed $slug_raw */
    $slug_raw = $args['slug'] ?? null;
    if (!is_string($slug_raw) || $slug_raw === '') {
        return new WP_Error('metabox_invalid_input', __('slug (or post_type) is required.', domain: 'novamira-pro'));
    }
    $slug = $slug_raw;
    /** @var mixed $force_raw */
    $force_raw = $args['force'] ?? false;
    $force = is_bool($force_raw) ? $force_raw : false;

    $post = mb_find_cpt_post($slug, post_type: 'mb-post-type');
    if ($post === null) {
        return ['deleted' => false, 'slug' => $slug];
    }

    $post_count = mb_cpt_published_count($slug);
    if ($post_count > 0 && !$force) {
        return new WP_Error('metabox_delete_blocked', sprintf(
            /* translators: 1: count, 2: slug. */
            __('%1$d posts use post type "%2$s"; pass force=true to delete anyway.', domain: 'novamira-pro'),
            $post_count,
            $slug,
        ));
    }

    $deleted = wp_delete_post($post->ID, force_delete: true);
    if ($deleted !== false && $deleted !== null) {
        mb_soft_flush_rewrite_rules();
    }
    $out = ['deleted' => $deleted !== false && $deleted !== null, 'slug' => $slug];
    if ($post_count > 0) {
        $out['orphaned_posts_count'] = $post_count;
    }
    return $out;
}

/**
 * Build the MB CPT settings array for a new post type.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_build_cpt_settings(string $slug, string $title, array $args): array
{
    $settings = mb_build_cpt_core_settings($slug, $title, $args);
    mb_apply_cpt_optional_settings($args, $settings);
    return $settings;
}

/**
 * Build core (always-present) CPT settings fields.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_build_cpt_core_settings(string $slug, string $title, array $args): array
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

    /** @var mixed $public_raw */
    $public_raw = $args['public'] ?? true;
    /** @var mixed $hier_raw */
    $hier_raw = $args['hierarchical'] ?? false;
    /** @var mixed $sir_raw */
    $sir_raw = $args['show_in_rest'] ?? true;

    return [
        'slug' => $slug,
        'labels' => $labels,
        'public' => is_bool($public_raw) ? $public_raw : true,
        'hierarchical' => is_bool($hier_raw) ? $hier_raw : false,
        'show_in_rest' => is_bool($sir_raw) ? $sir_raw : true,
        'menu_icon' => isset($args['menu_icon']) && is_string($args['menu_icon'])
            ? $args['menu_icon']
            : 'dashicons-admin-generic',
        'supports' => isset($args['supports']) && is_array($args['supports'])
            ? array_values(array_filter($args['supports'], callback: 'is_string'))
            : ['title', 'editor'],
    ];
}

/**
 * Append optional CPT visibility, taxonomy and rewrite settings.
 *
 * @param array<string, mixed>  $args
 * @param array<string, mixed> &$settings
 */
function mb_apply_cpt_optional_settings(array $args, array &$settings): void
{
    foreach ([
        'show_ui',
        'publicly_queryable',
        'exclude_from_search',
        'show_in_nav_menus',
        'show_in_admin_bar',
    ] as $key) {
        if (!array_key_exists($key, $args) || !is_bool($args[$key])) {
            continue;
        }
        $settings[$key] = $args[$key];
    }

    foreach (['show_in_menu', 'has_archive'] as $key) {
        if (!array_key_exists($key, $args) || !(is_bool($args[$key]) || is_string($args[$key]))) {
            continue;
        }
        $settings[$key] = $args[$key];
    }

    if (isset($args['taxonomies']) && is_array($args['taxonomies'])) {
        $settings['taxonomies'] = array_values(array_filter($args['taxonomies'], callback: 'is_string'));
    }

    if (isset($args['rewrite']) && is_array($args['rewrite'])) {
        $settings['rewrite'] = $args['rewrite'];
    }
}
