<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Abilities: ASE post type create / edit / delete (three abilities in one file).
 */

// ---------------------------------------------------------------------------
// create-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-create-post-type', [
    'label' => __('Create ASE Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ASE CCT post type definition. Required: slug (1-20 chars, lowercase letters/digits/underscores/hyphens), title (plural name), labels.singular. Auto-generates all 31 cpt_label_* postmeta keys from singular+plural unless overridden via labels_full. Idempotent on identical re-create (returns created: false). Returns ase_post_type_slug_reserved when slug collides with a WordPress core post type. Returns ase_post_type_slug_conflict when slug exists with different data.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'Post type slug. 1-20 chars, lowercase letters, digits, underscores, hyphens only.',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Plural display name, e.g. "Books". Used as post_title.',
            ],
            'labels' => [
                'type' => 'object',
                'description' => 'Singular and optional plural name. singular is required.',
                'properties' => [
                    'singular' => [
                        'type' => 'string',
                        'description' => 'Singular display name, e.g. "Book". Required.',
                    ],
                    'plural' => [
                        'type' => 'string',
                        'description' => 'Plural display name. Defaults to title when omitted.',
                    ],
                ],
                'required' => ['singular'],
            ],
            'supports' => [
                'type' => 'array',
                'description' => 'Post editor features. Default ["title", "editor"].',
                'items' => ['type' => 'string'],
            ],
            'taxonomies' => [
                'type' => 'array',
                'description' => 'Taxonomy slugs to associate. Default [].',
                'items' => ['type' => 'string'],
            ],
            'public' => ['type' => 'boolean', 'description' => 'Default true.'],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Default false.'],
            'menu_position' => ['type' => 'integer', 'description' => 'Admin menu position. Optional.'],
            'menu_icon' => ['type' => 'string', 'description' => 'Dashicon slug or base64 SVG. Optional.'],
            'rest' => [
                'type' => 'object',
                'description' => 'REST API settings.',
                'properties' => [
                    'show_in_rest' => ['type' => 'boolean'],
                    'rest_base' => ['type' => 'string'],
                ],
            ],
            'labels_full' => [
                'type' => 'object',
                'description' => 'Override specific auto-generated labels. Keys are label names without the "cpt_label_" prefix.',
            ],
            'capabilities' => [
                'type' => 'object',
                'description' => 'Capability settings. Keys: capability_type (post|page|custom), map_meta_cap (bool), custom_singular, custom_plural.',
            ],
            'rewrite' => [
                'type' => 'object',
                'description' => 'Rewrite settings. Keys: enabled (bool), slug (string), with_front (bool), feeds (bool), pages (bool), ep_mask (string), query_var (bool), query_var_string (string).',
            ],
        ],
        'required' => ['slug', 'title', 'labels'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'supports' => ['type' => 'array'],
            'taxonomies' => ['type' => 'array'],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'menu_position' => ['type' => ['integer', 'null']],
            'menu_icon' => ['type' => 'string'],
            'rest' => ['type' => 'object'],
            'source' => ['type' => 'string'],
            'editable' => ['type' => 'boolean'],
            'created' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_create_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ase_create_post_type(array $input): array
{
    $guard = cpt_manage_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug_error = cpt_validate_slug($input);
    if (is_array($slug_error)) {
        return $slug_error;
    }

    $slug = $slug_error;

    if (in_array($slug, ase_reserved_post_type_slugs(), strict: true)) {
        return [
            'error' => sprintf(
                __('"%s" is a reserved WordPress post type slug and cannot be used.', domain: 'novamira-pro'),
                $slug,
            ),
            'code' => 'ase_post_type_slug_reserved',
        ];
    }

    /** @var mixed $raw_title */
    $raw_title = $input['title'] ?? '';
    $title = is_string($raw_title) ? trim($raw_title) : '';
    if ($title === '') {
        return ['error' => __('title is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    /** @var mixed $raw_labels */
    $raw_labels = $input['labels'] ?? [];
    $labels_input = is_array($raw_labels) ? $raw_labels : [];

    /** @var mixed $raw_singular */
    $raw_singular = $labels_input['singular'] ?? '';
    $singular = is_string($raw_singular) ? trim($raw_singular) : '';
    if ($singular === '') {
        return ['error' => __('labels.singular is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    /** @var mixed $raw_plural */
    $raw_plural = $labels_input['plural'] ?? '';
    $plural = is_string($raw_plural) && trim($raw_plural) !== '' ? trim($raw_plural) : $title;

    $existing = ase_get_cpt_by_slug($slug);
    if ($existing !== null) {
        return cpt_handle_existing_on_create(
            existing_id: $existing->ID,
            slug: $slug,
            existing_title: $existing->post_title,
            incoming: ['singular' => $singular, 'plural' => $plural, 'input' => $input],
        );
    }

    return cpt_do_insert($slug, $title, $singular, $plural, $input);
}

// ---------------------------------------------------------------------------
// edit-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-edit-post-type', [
    'label' => __('Edit ASE Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ASE CCT post type definition by slug. The slug itself cannot be changed — renaming requires delete + create. Provide any subset of fields to update; absent keys are left unchanged (merge semantics). supports[] and taxonomies[] replace atomically when provided. rest, labels_full, capabilities, and rewrite sub-objects are partial-merged per key. Important: changing labels.singular/plural does NOT re-generate the 27 auto-label keys — use labels_full overrides to update individual label strings. flush_rewrite_rules() is called after every update.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The post type slug to edit (identifier — cannot be changed).',
            ],
            'title' => ['type' => 'string', 'description' => 'New plural name. Omit to keep existing.'],
            'labels' => [
                'type' => 'object',
                'description' => 'Update singular and/or plural names. Does NOT re-generate auto-label keys.',
                'properties' => [
                    'singular' => ['type' => 'string'],
                    'plural' => ['type' => 'string'],
                ],
            ],
            'supports' => [
                'type' => 'array',
                'description' => 'Full replacement supports array. Replaces atomically when provided.',
                'items' => ['type' => 'string'],
            ],
            'taxonomies' => [
                'type' => 'array',
                'description' => 'Full replacement taxonomies array. Replaces atomically when provided.',
                'items' => ['type' => 'string'],
            ],
            'public' => ['type' => 'boolean', 'description' => 'Omit to keep existing.'],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Omit to keep existing.'],
            'menu_position' => ['type' => 'integer', 'description' => 'Omit to keep existing.'],
            'menu_icon' => ['type' => 'string', 'description' => 'Omit to keep existing.'],
            'rest' => [
                'type' => 'object',
                'description' => 'Partial merge: only provided keys are updated.',
            ],
            'labels_full' => [
                'type' => 'object',
                'description' => 'Partial merge: each provided key updates the matching cpt_label_* postmeta.',
            ],
            'capabilities' => [
                'type' => 'object',
                'description' => 'Partial merge: only provided keys are updated.',
            ],
            'rewrite' => [
                'type' => 'object',
                'description' => 'Partial merge: only provided keys are updated.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'supports' => ['type' => 'array'],
            'taxonomies' => ['type' => 'array'],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'menu_position' => ['type' => ['integer', 'null']],
            'menu_icon' => ['type' => 'string'],
            'rest' => ['type' => 'object'],
            'source' => ['type' => 'string'],
            'editable' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_edit_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ase_edit_post_type(array $input): array
{
    $guard = cpt_manage_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $raw_slug */
    $raw_slug = $input['slug'] ?? '';
    $slug = is_string($raw_slug) ? trim($raw_slug) : '';
    if ($slug === '') {
        return ['error' => __('slug is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    $post = ase_get_cpt_by_slug($slug);
    if ($post === null) {
        return [
            'error' => sprintf(__('Post type "%s" not found.', domain: 'novamira-pro'), $slug),
            'code' => 'ase_post_type_not_found',
        ];
    }

    cpt_apply_title_update($post->ID, $input);
    cpt_apply_labels_update($post->ID, $input);
    cpt_apply_supports_update($post->ID, $input);
    cpt_apply_taxonomies_update($post->ID, $input);
    cpt_apply_public_update($post->ID, $input);
    cpt_apply_hierarchical_update($post->ID, $input);
    cpt_apply_menu_position_update($post->ID, $input);
    cpt_apply_menu_icon_update($post->ID, $input);
    cpt_apply_rest_update($post->ID, $input);
    cpt_apply_labels_full_update($post->ID, $input);
    cpt_apply_capabilities_update($post->ID, $input);
    cpt_apply_rewrite_update($post->ID, $input);

    flush_rewrite_rules();

    $updated_post = ase_get_cpt_by_slug($slug);
    if ($updated_post === null) {
        return [
            'error' => __('Failed to reload post type after edit.', domain: 'novamira-pro'),
            'code' => 'ase_reload_failed',
        ];
    }

    return cpt_build_shape($updated_post->ID, $slug, $updated_post->post_title, []);
}

// ---------------------------------------------------------------------------
// delete-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-delete-post-type', [
    'label' => __('Delete ASE Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an ASE CCT post type definition by slug. Safe by default: existing posts of the type are preserved in wp_posts (just unregistered). Pass force_delete_content: true to also hard-delete all posts of this type. Returns ase_post_type_not_found when the slug does not exist. flush_rewrite_rules() is called after deletion.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The post type slug to delete.',
            ],
            'force_delete_content' => [
                'type' => 'boolean',
                'description' => 'When true, hard-deletes all posts of this type before removing the definition. Default false (safe-delete).',
                'default' => false,
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
            'posts_deleted' => [
                'type' => 'integer',
                'description' => 'Number of content posts deleted. 0 when force_delete_content is false.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_delete_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ase_delete_post_type(array $input): array
{
    $guard = cpt_manage_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $raw_slug */
    $raw_slug = $input['slug'] ?? '';
    $slug = is_string($raw_slug) ? trim($raw_slug) : '';
    if ($slug === '') {
        return ['error' => __('slug is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    $post = ase_get_cpt_by_slug($slug);
    if ($post === null) {
        return [
            'error' => sprintf(__('Post type "%s" not found.', domain: 'novamira-pro'), $slug),
            'code' => 'ase_post_type_not_found',
        ];
    }

    $force_delete = ($input['force_delete_content'] ?? false) === true;
    $posts_deleted = 0;

    if ($force_delete) {
        /** @var array<int> $post_ids */
        $post_ids = get_posts([
            'post_type' => $slug,
            'numberposts' => -1,
            'fields' => 'ids',
            'post_status' => 'any',
            'suppress_filters' => false,
        ]);

        foreach ($post_ids as $pid) {
            wp_delete_post((int) $pid, force_delete: true);
        }

        $posts_deleted = count($post_ids);
    }

    wp_delete_post($post->ID, force_delete: true);
    flush_rewrite_rules();

    return [
        'slug' => $slug,
        'deleted' => true,
        'posts_deleted' => $posts_deleted,
    ];
}

// ---------------------------------------------------------------------------
// Private helpers (cpt_ prefix)
// ---------------------------------------------------------------------------

/**
 * Run all three standard CCT guards (runtime, pro, module) and return a
 * formatted error array on the first failure, or null when all pass.
 *
 * @return array<string, mixed>|null
 */
function cpt_manage_guard(): ?array
{
    $guard = ase_runtime_guard();
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $guard = ase_pro_guard();
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $guard = ase_module_guard('custom_content_types', __(
        'Enable the "Custom Content Types" module in ASE settings.',
        domain: 'novamira-pro',
    ));
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    return null;
}

/**
 * Validate and extract the slug from create input.
 *
 * Returns the slug string on success, or an error array on failure.
 *
 * @param array<string, mixed> $input
 * @return string|array<string, mixed>
 */
function cpt_validate_slug(array $input): string|array
{
    /** @var mixed $raw */
    $raw = $input['slug'] ?? '';
    $slug = is_string($raw) ? trim($raw) : '';

    if ($slug === '') {
        return ['error' => __('slug is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    if (strlen($slug) > 20) {
        return [
            'error' => __(
                'slug must be 1-20 chars, lowercase letters/digits/underscore/hyphen.',
                domain: 'novamira-pro',
            ),
            'code' => 'invalid_input',
        ];
    }

    if (preg_match('/^[a-z0-9_-]+$/', $slug) !== 1) {
        return [
            'error' => __(
                'slug must be 1-20 chars, lowercase letters/digits/underscore/hyphen.',
                domain: 'novamira-pro',
            ),
            'code' => 'invalid_input',
        ];
    }

    return $slug;
}

/**
 * Generate the 31 label defaults from singular and plural names.
 *
 * Templates verified against ASE's cpt-fields-values.php source.
 * Note: ASE stores these as empty strings by default and populates them only
 * when the user explicitly saves them via the admin UI. We pre-populate them
 * here so agents get sensible labels without a second edit step.
 *
 * @param string $singular
 * @param string $plural
 * @return array<string, string>  Keys are the cpt_label_* meta keys.
 */
function cpt_generate_labels(string $singular, string $plural): array
{
    $lower_singular = mb_strtolower($singular);
    $lower_plural = mb_strtolower($plural);

    return [
        'cpt_label_add_new' => 'Add New',
        'cpt_label_add_new_item' => 'Add New ' . $singular,
        'cpt_label_edit_item' => 'Edit ' . $singular,
        'cpt_label_new_item' => 'New ' . $singular,
        'cpt_label_view_item' => 'View ' . $singular,
        'cpt_label_view_items' => 'View ' . $plural,
        'cpt_label_search_items' => 'Search ' . $plural,
        'cpt_label_not_found' => 'No ' . $lower_plural . ' found.',
        'cpt_label_not_found_in_trash' => 'No ' . $lower_plural . ' found in Trash.',
        'cpt_label_parent_item_colon' => 'Parent ' . $singular . ':',
        'cpt_label_all_items' => 'All ' . $plural,
        'cpt_label_archives' => $singular . ' Archives',
        'cpt_label_attributes' => $singular . ' Attributes',
        'cpt_label_insert_into_item' => 'Insert into ' . $lower_singular,
        'cpt_label_uploaded_to_this_item' => 'Uploaded to this ' . $lower_singular,
        'cpt_label_featured_image' => 'Featured image',
        'cpt_label_set_featured_image' => 'Set featured image',
        'cpt_label_remove_featured_image' => 'Remove featured image',
        'cpt_label_use_featured_image' => 'Use as featured image',
        'cpt_label_menu_name' => $plural,
        'cpt_label_filter_items_list' => 'Filter ' . $lower_plural . ' list',
        'cpt_label_filter_by_date' => 'Filter by date',
        'cpt_label_items_list_navigation' => $plural . ' list navigation',
        'cpt_label_items_list' => $plural . ' list',
        'cpt_label_item_published' => $singular . ' published.',
        'cpt_label_item_published_privately' => $singular . ' published privately.',
        'cpt_label_item_reverted_to_draft' => $singular . ' reverted to draft.',
        'cpt_label_item_scheduled' => $singular . ' scheduled.',
        'cpt_label_item_updated' => $singular . ' updated.',
        'cpt_label_item_link' => $singular . ' Link',
        'cpt_label_item_link_description' => 'A link to a ' . $lower_singular . '.',
    ];
}

/**
 * Write all postmeta for a newly created CPT.
 *
 * @param int                  $post_id
 * @param string               $slug
 * @param string               $singular
 * @param string               $plural
 * @param array<string, mixed> $input     Full create input.
 */
function cpt_write_postmeta(int $post_id, string $slug, string $singular, string $plural, array $input): void
{
    update_post_meta($post_id, meta_key: 'cpt_key', meta_value: $slug);
    update_post_meta($post_id, meta_key: 'cpt_singular_name', meta_value: $singular);
    update_post_meta($post_id, meta_key: 'cpt_plural_name', meta_value: $plural);

    cpt_write_visibility_meta($post_id, $input);
    cpt_write_rest_meta($post_id, $input);
    cpt_write_menu_meta($post_id, $input);
    cpt_write_supports_taxonomies_meta($post_id, $input);
    cpt_write_labels_meta($post_id, $singular, $plural, $input);
    cpt_write_capabilities_meta($post_id, $input);
    cpt_write_rewrite_meta($post_id, $input);
}

/**
 * Write public/hierarchical and derived visibility flags.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_write_visibility_meta(int $post_id, array $input): void
{
    $public = ($input['public'] ?? true) !== false;
    update_post_meta($post_id, meta_key: 'cpt_public', meta_value: $public);
    update_post_meta($post_id, meta_key: 'cpt_hierarchical', meta_value: ($input['hierarchical'] ?? false) === true);
    update_post_meta($post_id, meta_key: 'cpt_show_ui', meta_value: $public);
    update_post_meta($post_id, meta_key: 'cpt_show_in_menu', meta_value: $public);
    update_post_meta($post_id, meta_key: 'cpt_show_in_nav_menus', meta_value: $public);
    update_post_meta($post_id, meta_key: 'cpt_publicly_queryable', meta_value: $public);
    update_post_meta($post_id, meta_key: 'cpt_exclude_from_search', meta_value: !$public);
    update_post_meta($post_id, meta_key: 'cpt_has_archive', meta_value: $public);
    update_post_meta($post_id, meta_key: 'cpt_can_export', meta_value: true);
    update_post_meta($post_id, meta_key: 'cpt_show_in_admin_bar', meta_value: $public);
}

/**
 * Write REST API postmeta keys.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_write_rest_meta(int $post_id, array $input): void
{
    $public = ($input['public'] ?? true) !== false;

    /** @var mixed $rest_input */
    $rest_input = $input['rest'] ?? [];
    $rest = is_array($rest_input) ? $rest_input : [];
    $show_in_rest = ($rest['show_in_rest'] ?? $public) !== false;
    update_post_meta($post_id, meta_key: 'cpt_show_in_rest', meta_value: $show_in_rest);
    update_post_meta($post_id, meta_key: 'cpt_rest_base', meta_value: (string) ($rest['rest_base'] ?? ''));
}

/**
 * Write menu_position and menu_icon postmeta.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_write_menu_meta(int $post_id, array $input): void
{
    /** @var mixed $menu_position */
    $menu_position = $input['menu_position'] ?? '';
    update_post_meta(
        $post_id,
        meta_key: 'cpt_menu_position',
        meta_value: $menu_position !== '' ? (int) $menu_position : '',
    );
    update_post_meta($post_id, meta_key: 'cpt_menu_icon', meta_value: (string) ($input['menu_icon'] ?? ''));
}

/**
 * Write cpt_supports and cpt_taxonomies postmeta (both serialized arrays).
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_write_supports_taxonomies_meta(int $post_id, array $input): void
{
    /** @var mixed $supports_input */
    $supports_input = $input['supports'] ?? ['title', 'editor'];
    $supports = is_array($supports_input) ? array_values(array_map('strval', $supports_input)) : ['title', 'editor'];
    update_post_meta($post_id, meta_key: 'cpt_supports', meta_value: $supports);

    /** @var mixed $taxonomies_input */
    $taxonomies_input = $input['taxonomies'] ?? [];
    $taxonomies = is_array($taxonomies_input) ? array_values(array_map('strval', $taxonomies_input)) : [];
    update_post_meta($post_id, meta_key: 'cpt_taxonomies', meta_value: $taxonomies);
}

/**
 * Write all 31 cpt_label_* postmeta keys, auto-generating defaults from singular+plural.
 *
 * @param int                  $post_id
 * @param string               $singular
 * @param string               $plural
 * @param array<string, mixed> $input
 */
function cpt_write_labels_meta(int $post_id, string $singular, string $plural, array $input): void
{
    $label_defaults = cpt_generate_labels($singular, $plural);

    /** @var mixed $labels_full_input */
    $labels_full_input = $input['labels_full'] ?? [];
    $labels_full = is_array($labels_full_input) ? $labels_full_input : [];

    foreach ($label_defaults as $meta_key => $default_value) {
        $agent_key = substr($meta_key, strlen('cpt_label_'));
        /** @var mixed $override */
        $override = $labels_full[$agent_key] ?? null;
        $value = $override !== null && is_string($override) ? $override : $default_value;
        update_post_meta($post_id, meta_key: $meta_key, meta_value: $value);
    }
}

/**
 * Write capability_type and related postmeta keys.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_write_capabilities_meta(int $post_id, array $input): void
{
    /** @var mixed $capabilities_input */
    $capabilities_input = $input['capabilities'] ?? [];
    $capabilities = is_array($capabilities_input) ? $capabilities_input : [];

    $capability_type = (string) ($capabilities['capability_type'] ?? 'post');
    if (!in_array($capability_type, ['post', 'page', 'custom'], strict: true)) {
        $capability_type = 'post';
    }

    update_post_meta($post_id, meta_key: 'cpt_capability_type', meta_value: $capability_type);
    update_post_meta(
        $post_id,
        meta_key: 'cpt_map_meta_cap',
        meta_value: ($capabilities['map_meta_cap'] ?? true) !== false,
    );
    update_post_meta(
        $post_id,
        meta_key: 'cpt_capability_type_custom_slug_singular',
        meta_value: (string) ($capabilities['custom_singular'] ?? ''),
    );
    update_post_meta(
        $post_id,
        meta_key: 'cpt_capability_type_custom_slug_plural',
        meta_value: (string) ($capabilities['custom_plural'] ?? ''),
    );
}

/**
 * Write rewrite and query_var postmeta keys.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_write_rewrite_meta(int $post_id, array $input): void
{
    /** @var mixed $rewrite_input */
    $rewrite_input = $input['rewrite'] ?? [];
    $rewrite = is_array($rewrite_input) ? $rewrite_input : [];

    $rewrite_enabled = ($rewrite['enabled'] ?? true) !== false;
    update_post_meta($post_id, meta_key: 'cpt_rewrite', meta_value: $rewrite_enabled);

    $rewrite_slug = (string) ($rewrite['slug'] ?? '');
    $use_custom_rewrite_slug = $rewrite_slug !== '';
    update_post_meta($post_id, meta_key: 'cpt_use_custom_rewrite_slug', meta_value: $use_custom_rewrite_slug);
    update_post_meta($post_id, meta_key: 'cpt_rewrite_custom_slug', meta_value: $rewrite_slug);
    update_post_meta($post_id, meta_key: 'cpt_with_front', meta_value: ($rewrite['with_front'] ?? true) !== false);
    update_post_meta($post_id, meta_key: 'cpt_feeds', meta_value: ($rewrite['feeds'] ?? false) === true);
    update_post_meta($post_id, meta_key: 'cpt_pages', meta_value: ($rewrite['pages'] ?? true) !== false);

    $ep_mask = (string) ($rewrite['ep_mask'] ?? 'EP_PERMALINK');
    $ep_mask_enabled = $ep_mask !== '' && $ep_mask !== 'EP_PERMALINK';
    update_post_meta($post_id, meta_key: 'cpt_ep_mask', meta_value: $ep_mask_enabled);
    update_post_meta($post_id, meta_key: 'cpt_ep_mask_custom', meta_value: $ep_mask_enabled ? $ep_mask : '');

    $query_var = ($rewrite['query_var'] ?? true) !== false;
    update_post_meta($post_id, meta_key: 'cpt_query_var', meta_value: $query_var);
    $query_var_string = (string) ($rewrite['query_var_string'] ?? '');
    $use_custom_qv = $query_var_string !== '';
    update_post_meta($post_id, meta_key: 'cpt_use_custom_query_var_string', meta_value: $use_custom_qv);
    update_post_meta($post_id, meta_key: 'cpt_query_var_string', meta_value: $query_var_string);
}

/**
 * Insert a new post type definition post and write all its postmeta.
 *
 * @param string               $slug
 * @param string               $title
 * @param string               $singular
 * @param string               $plural
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function cpt_do_insert(string $slug, string $title, string $singular, string $plural, array $input): array
{
    $post_id = wp_insert_post(postarr: [
        'post_type' => 'asenha_cpt',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => '',
        'post_content_filtered' => '',
        'post_excerpt' => '',
        'to_ping' => '',
        'pinged' => '',
    ], wp_error: true);

    if (is_wp_error($post_id)) {
        return ['error' => __('Failed to create post type.', domain: 'novamira-pro'), 'code' => 'ase_create_failed'];
    }

    cpt_write_postmeta($post_id, $slug, $singular, $plural, $input);
    flush_rewrite_rules();

    $shape = cpt_build_shape($post_id, $slug, $title, []);
    $shape['created'] = true;

    return $shape;
}

/**
 * Handle the case where a CPT with the same slug already exists on create.
 *
 * @param int                  $existing_id
 * @param string               $slug
 * @param string               $existing_title
 * @param array{singular: string, plural: string, input: array<string, mixed>} $incoming
 * @return array<string, mixed>
 */
function cpt_handle_existing_on_create(int $existing_id, string $slug, string $existing_title, array $incoming): array
{
    $singular = $incoming['singular'];
    $plural = $incoming['plural'];
    $input = $incoming['input'];

    $existing_singular = (string) get_post_meta($existing_id, key: 'cpt_singular_name', single: true);
    $existing_plural = $existing_title;

    $payload_matches =
        $existing_title === (string) ($input['title'] ?? $existing_title)
        && $existing_singular === $singular
        && $existing_plural === $plural;

    if ($payload_matches) {
        $shape = cpt_build_shape($existing_id, $slug, $existing_title, []);
        $shape['created'] = false;

        return $shape;
    }

    return [
        'error' => sprintf(
            __('A post type with slug "%s" already exists with different data.', domain: 'novamira-pro'),
            $slug,
        ),
        'code' => 'ase_post_type_slug_conflict',
    ];
}

// ---------------------------------------------------------------------------
// edit-post-type apply helpers
// ---------------------------------------------------------------------------

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_title_update(int $post_id, array $input): void
{
    if (!array_key_exists('title', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['title'];
    $new_title = is_string($raw) ? trim($raw) : '';
    if ($new_title === '') {
        return;
    }

    wp_update_post(['ID' => $post_id, 'post_title' => $new_title]);
    update_post_meta($post_id, meta_key: 'cpt_plural_name', meta_value: $new_title);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_labels_update(int $post_id, array $input): void
{
    if (!array_key_exists('labels', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['labels'];
    $labels = is_array($raw) ? $raw : [];

    if (array_key_exists('singular', $labels)) {
        /** @var mixed $s */
        $s = $labels['singular'];
        $singular = is_string($s) ? trim($s) : '';
        if ($singular !== '') {
            update_post_meta($post_id, meta_key: 'cpt_singular_name', meta_value: $singular);
        }
    }

    if (array_key_exists('plural', $labels)) {
        /** @var mixed $p */
        $p = $labels['plural'];
        $plural = is_string($p) ? trim($p) : '';
        if ($plural !== '') {
            update_post_meta($post_id, meta_key: 'cpt_plural_name', meta_value: $plural);
            wp_update_post(['ID' => $post_id, 'post_title' => $plural]);
        }
    }
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_supports_update(int $post_id, array $input): void
{
    if (!array_key_exists('supports', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['supports'];
    $supports = is_array($raw) ? array_values(array_map('strval', $raw)) : [];
    update_post_meta($post_id, meta_key: 'cpt_supports', meta_value: $supports);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_taxonomies_update(int $post_id, array $input): void
{
    if (!array_key_exists('taxonomies', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['taxonomies'];
    $taxonomies = is_array($raw) ? array_values(array_map('strval', $raw)) : [];
    update_post_meta($post_id, meta_key: 'cpt_taxonomies', meta_value: $taxonomies);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_public_update(int $post_id, array $input): void
{
    if (!array_key_exists('public', $input)) {
        return;
    }

    $public = $input['public'] !== false;
    update_post_meta($post_id, meta_key: 'cpt_public', meta_value: $public);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_hierarchical_update(int $post_id, array $input): void
{
    if (!array_key_exists('hierarchical', $input)) {
        return;
    }

    update_post_meta($post_id, meta_key: 'cpt_hierarchical', meta_value: $input['hierarchical'] === true);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_menu_position_update(int $post_id, array $input): void
{
    if (!array_key_exists('menu_position', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['menu_position'];
    update_post_meta($post_id, meta_key: 'cpt_menu_position', meta_value: is_int($raw) ? $raw : '');
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_menu_icon_update(int $post_id, array $input): void
{
    if (!array_key_exists('menu_icon', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['menu_icon'];
    update_post_meta($post_id, meta_key: 'cpt_menu_icon', meta_value: is_string($raw) ? $raw : '');
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_rest_update(int $post_id, array $input): void
{
    if (!array_key_exists('rest', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['rest'];
    $rest = is_array($raw) ? $raw : [];

    if (array_key_exists('show_in_rest', $rest)) {
        update_post_meta($post_id, meta_key: 'cpt_show_in_rest', meta_value: $rest['show_in_rest'] !== false);
    }

    if (array_key_exists('rest_base', $rest)) {
        update_post_meta($post_id, meta_key: 'cpt_rest_base', meta_value: (string) ($rest['rest_base'] ?? ''));
    }
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_labels_full_update(int $post_id, array $input): void
{
    if (!array_key_exists('labels_full', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['labels_full'];
    $labels_full = is_array($raw) ? $raw : [];

    $valid_keys = cpt_label_meta_keys();

    foreach ($valid_keys as $meta_key) {
        $agent_key = substr($meta_key, strlen('cpt_label_'));
        if (!array_key_exists($agent_key, $labels_full)) {
            continue;
        }

        /** @var mixed $value */
        $value = $labels_full[$agent_key];
        if (is_string($value)) {
            update_post_meta($post_id, meta_key: $meta_key, meta_value: $value);
        }
    }
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_capabilities_update(int $post_id, array $input): void
{
    if (!array_key_exists('capabilities', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['capabilities'];
    $capabilities = is_array($raw) ? $raw : [];

    if (array_key_exists('capability_type', $capabilities)) {
        $cap_type = (string) ($capabilities['capability_type'] ?? 'post');
        if (in_array($cap_type, ['post', 'page', 'custom'], strict: true)) {
            update_post_meta($post_id, meta_key: 'cpt_capability_type', meta_value: $cap_type);
        }
    }

    if (array_key_exists('map_meta_cap', $capabilities)) {
        update_post_meta($post_id, meta_key: 'cpt_map_meta_cap', meta_value: $capabilities['map_meta_cap'] !== false);
    }

    if (array_key_exists('custom_singular', $capabilities)) {
        update_post_meta(
            $post_id,
            meta_key: 'cpt_capability_type_custom_slug_singular',
            meta_value: (string) ($capabilities['custom_singular'] ?? ''),
        );
    }

    if (array_key_exists('custom_plural', $capabilities)) {
        update_post_meta(
            $post_id,
            meta_key: 'cpt_capability_type_custom_slug_plural',
            meta_value: (string) ($capabilities['custom_plural'] ?? ''),
        );
    }
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function cpt_apply_rewrite_update(int $post_id, array $input): void
{
    if (!array_key_exists('rewrite', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['rewrite'];
    $rewrite = is_array($raw) ? $raw : [];

    if (array_key_exists('enabled', $rewrite)) {
        update_post_meta($post_id, meta_key: 'cpt_rewrite', meta_value: $rewrite['enabled'] !== false);
    }

    if (array_key_exists('slug', $rewrite)) {
        $rewrite_slug = (string) ($rewrite['slug'] ?? '');
        $use_custom = $rewrite_slug !== '';
        update_post_meta($post_id, meta_key: 'cpt_use_custom_rewrite_slug', meta_value: $use_custom);
        update_post_meta($post_id, meta_key: 'cpt_rewrite_custom_slug', meta_value: $rewrite_slug);
    }

    if (array_key_exists('with_front', $rewrite)) {
        update_post_meta($post_id, meta_key: 'cpt_with_front', meta_value: $rewrite['with_front'] !== false);
    }

    if (array_key_exists('feeds', $rewrite)) {
        update_post_meta($post_id, meta_key: 'cpt_feeds', meta_value: $rewrite['feeds'] === true);
    }

    if (array_key_exists('pages', $rewrite)) {
        update_post_meta($post_id, meta_key: 'cpt_pages', meta_value: $rewrite['pages'] !== false);
    }

    if (array_key_exists('ep_mask', $rewrite)) {
        $ep_mask = (string) ($rewrite['ep_mask'] ?? 'EP_PERMALINK');
        $ep_mask_enabled = $ep_mask !== '' && $ep_mask !== 'EP_PERMALINK';
        update_post_meta($post_id, meta_key: 'cpt_ep_mask', meta_value: $ep_mask_enabled);
        update_post_meta($post_id, meta_key: 'cpt_ep_mask_custom', meta_value: $ep_mask_enabled ? $ep_mask : '');
    }

    if (array_key_exists('query_var', $rewrite)) {
        update_post_meta($post_id, meta_key: 'cpt_query_var', meta_value: $rewrite['query_var'] !== false);
    }

    if (array_key_exists('query_var_string', $rewrite)) {
        $qv_string = (string) ($rewrite['query_var_string'] ?? '');
        $use_custom_qv = $qv_string !== '';
        update_post_meta($post_id, meta_key: 'cpt_use_custom_query_var_string', meta_value: $use_custom_qv);
        update_post_meta($post_id, meta_key: 'cpt_query_var_string', meta_value: $qv_string);
    }
}
