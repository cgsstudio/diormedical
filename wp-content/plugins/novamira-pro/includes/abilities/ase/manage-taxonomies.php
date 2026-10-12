<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Abilities: ASE taxonomy create / edit / delete (three abilities in one file).
 */

// ---------------------------------------------------------------------------
// create-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-create-taxonomy', [
    'label' => __('Create ASE Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ASE CCT taxonomy definition. Required: slug (1-32 chars, lowercase letters/digits/underscores/hyphens), title (plural name), labels.singular. Auto-generates all 26 ctax_label_* postmeta keys from singular+plural unless overridden via labels_full. Default hierarchical=true (matches WP core category). Idempotent on identical re-create (returns created: false). Returns ase_taxonomy_slug_reserved when slug collides with a WordPress core taxonomy (category, post_tag, nav_menu, link_category, post_format). Returns ase_taxonomy_slug_conflict when slug exists with different data.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'Taxonomy slug. 1-32 chars, lowercase letters, digits, underscores, hyphens only.',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Plural display name, e.g. "Genres". Used as post_title.',
            ],
            'labels' => [
                'type' => 'object',
                'description' => 'Singular and optional plural name. singular is required.',
                'properties' => [
                    'singular' => [
                        'type' => 'string',
                        'description' => 'Singular display name, e.g. "Genre". Required.',
                    ],
                    'plural' => [
                        'type' => 'string',
                        'description' => 'Plural display name. Defaults to title when omitted.',
                    ],
                ],
                'required' => ['singular'],
            ],
            'post_types' => [
                'type' => 'array',
                'description' => 'Post type slugs this taxonomy attaches to. Default [].',
                'items' => ['type' => 'string'],
            ],
            'public' => ['type' => 'boolean', 'description' => 'Default true.'],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Default true (like WP core categories).'],
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
                'description' => 'Override specific auto-generated labels. Keys are label names without the "ctax_label_" prefix.',
            ],
            'rewrite' => [
                'type' => 'object',
                'description' => 'Rewrite settings. Keys: enabled (bool), slug (string), with_front (bool), hierarchical_urls (bool), ep_mask (string), query_var (bool), query_var_string (string).',
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
            'post_types' => ['type' => 'array'],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'rest' => ['type' => 'object'],
            'source' => ['type' => 'string'],
            'editable' => ['type' => 'boolean'],
            'created' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_create_taxonomy',
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
function ase_create_taxonomy(array $input): array
{
    $guard = tax_manage_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug_result = tax_validate_slug($input);
    if (is_array($slug_result)) {
        return $slug_result;
    }

    $slug = $slug_result;

    if (in_array($slug, ase_reserved_taxonomy_slugs(), strict: true)) {
        return [
            'error' => sprintf(
                __('"%s" is a reserved WordPress taxonomy slug and cannot be used.', domain: 'novamira-pro'),
                $slug,
            ),
            'code' => 'ase_taxonomy_slug_reserved',
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

    $existing = ase_get_taxonomy_by_slug($slug);
    if ($existing !== null) {
        return tax_handle_existing_on_create(
            existing_id: $existing->ID,
            slug: $slug,
            existing_title: $existing->post_title,
            incoming: ['singular' => $singular, 'plural' => $plural, 'input' => $input],
        );
    }

    return tax_do_insert($slug, $title, $singular, $plural, $input);
}

// ---------------------------------------------------------------------------
// edit-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-edit-taxonomy', [
    'label' => __('Edit ASE Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ASE CCT taxonomy definition by slug. The slug itself cannot be changed — renaming requires delete + create. Provide any subset of fields to update; absent keys are left unchanged (merge semantics). post_types[] replaces atomically when provided. rest, labels_full, and rewrite sub-objects are partial-merged per key. Important: changing labels.singular/plural does NOT re-generate the 26 auto-label keys — use labels_full overrides to update individual label strings. flush_rewrite_rules() is called after every update.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The taxonomy slug to edit (identifier — cannot be changed).',
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
            'post_types' => [
                'type' => 'array',
                'description' => 'Full replacement post_types array. Replaces atomically when provided.',
                'items' => ['type' => 'string'],
            ],
            'public' => ['type' => 'boolean', 'description' => 'Omit to keep existing.'],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Omit to keep existing.'],
            'rest' => [
                'type' => 'object',
                'description' => 'Partial merge: only provided keys are updated.',
            ],
            'labels_full' => [
                'type' => 'object',
                'description' => 'Partial merge: each provided key updates the matching ctax_label_* postmeta.',
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
            'post_types' => ['type' => 'array'],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'rest' => ['type' => 'object'],
            'source' => ['type' => 'string'],
            'editable' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_edit_taxonomy',
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
function ase_edit_taxonomy(array $input): array
{
    $guard = tax_manage_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $raw_slug */
    $raw_slug = $input['slug'] ?? '';
    $slug = is_string($raw_slug) ? trim($raw_slug) : '';
    if ($slug === '') {
        return ['error' => __('slug is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    $post = ase_get_taxonomy_by_slug($slug);
    if ($post === null) {
        return [
            'error' => sprintf(__('Taxonomy "%s" not found.', domain: 'novamira-pro'), $slug),
            'code' => 'ase_taxonomy_not_found',
        ];
    }

    tax_apply_title_update($post->ID, $input);
    tax_apply_labels_update($post->ID, $input);
    tax_apply_post_types_update($post->ID, $input);
    tax_apply_public_update($post->ID, $input);
    tax_apply_hierarchical_update($post->ID, $input);
    tax_apply_rest_update($post->ID, $input);
    tax_apply_labels_full_update($post->ID, $input);
    tax_apply_rewrite_update($post->ID, $input);

    flush_rewrite_rules();

    $updated_post = ase_get_taxonomy_by_slug($slug);
    if ($updated_post === null) {
        return [
            'error' => __('Failed to reload taxonomy after edit.', domain: 'novamira-pro'),
            'code' => 'ase_reload_failed',
        ];
    }

    return ctax_build_shape($updated_post->ID, $slug, $updated_post->post_title, []);
}

// ---------------------------------------------------------------------------
// delete-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-delete-taxonomy', [
    'label' => __('Delete ASE Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an ASE CCT taxonomy definition by slug. Safe by default: existing terms of this taxonomy are preserved. Pass force_delete_terms: true to also hard-delete all terms. Returns ase_taxonomy_not_found when the slug does not exist. flush_rewrite_rules() is called after deletion.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The taxonomy slug to delete.',
            ],
            'force_delete_terms' => [
                'type' => 'boolean',
                'description' => 'When true, hard-deletes all existing terms of this taxonomy before removing the definition. Default false (safe-delete).',
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
            'terms_deleted' => [
                'type' => 'integer',
                'description' => 'Number of terms deleted. 0 when force_delete_terms is false.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_delete_taxonomy',
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
function ase_delete_taxonomy(array $input): array
{
    $guard = tax_manage_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $raw_slug */
    $raw_slug = $input['slug'] ?? '';
    $slug = is_string($raw_slug) ? trim($raw_slug) : '';
    if ($slug === '') {
        return ['error' => __('slug is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    $post = ase_get_taxonomy_by_slug($slug);
    if ($post === null) {
        return [
            'error' => sprintf(__('Taxonomy "%s" not found.', domain: 'novamira-pro'), $slug),
            'code' => 'ase_taxonomy_not_found',
        ];
    }

    $force_delete_terms = ($input['force_delete_terms'] ?? false) === true;
    $terms_deleted = 0;

    if ($force_delete_terms) {
        /** @var mixed $term_ids_raw */
        $term_ids_raw = get_terms([
            'taxonomy' => $slug,
            'hide_empty' => false,
            'fields' => 'ids',
        ]);

        if (!is_wp_error($term_ids_raw) && is_array($term_ids_raw)) {
            $term_ids_int = array_map('intval', $term_ids_raw);
            foreach ($term_ids_int as $tid) {
                wp_delete_term($tid, $slug);
            }

            $terms_deleted = count($term_ids_int);
        }
    }

    wp_delete_post($post->ID, force_delete: true);
    flush_rewrite_rules();

    return [
        'slug' => $slug,
        'deleted' => true,
        'terms_deleted' => $terms_deleted,
    ];
}

// ---------------------------------------------------------------------------
// Private helpers (tax_ prefix)
// ---------------------------------------------------------------------------

/**
 * Run all three standard CCT guards (runtime, pro, module) and return a
 * formatted error array on the first failure, or null when all pass.
 *
 * @return array<string, mixed>|null
 */
function tax_manage_guard(): ?array
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
function tax_validate_slug(array $input): string|array
{
    /** @var mixed $raw */
    $raw = $input['slug'] ?? '';
    $slug = is_string($raw) ? trim($raw) : '';

    if ($slug === '') {
        return ['error' => __('slug is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    if (strlen($slug) > 32) {
        return [
            'error' => __(
                'slug must be 1-32 chars, lowercase letters/digits/underscore/hyphen.',
                domain: 'novamira-pro',
            ),
            'code' => 'invalid_input',
        ];
    }

    if (preg_match('/^[a-z0-9_-]+$/', $slug) !== 1) {
        return [
            'error' => __(
                'slug must be 1-32 chars, lowercase letters/digits/underscore/hyphen.',
                domain: 'novamira-pro',
            ),
            'code' => 'invalid_input',
        ];
    }

    return $slug;
}

/**
 * Generate the 26 label defaults from singular and plural names.
 *
 * Templates verified against ASE's ctax-form-fields.php source.
 * Note: ASE stores these as empty strings by default and populates them only
 * when the user explicitly saves them via the admin UI. We pre-populate them
 * here so agents get sensible labels without a second edit step.
 *
 * @param string $singular
 * @param string $plural
 * @return array<string, string>  Keys are the ctax_label_* meta keys.
 */
function tax_generate_labels(string $singular, string $plural): array
{
    $lower_singular = mb_strtolower($singular);
    $lower_plural = mb_strtolower($plural);

    return [
        'ctax_label_search_items' => 'Search ' . $plural,
        'ctax_label_popular_items' => 'Popular ' . $plural,
        'ctax_label_all_items' => 'All ' . $plural,
        'ctax_label_parent_item' => 'Parent ' . $singular,
        'ctax_label_parent_item_colon' => 'Parent ' . $singular . ':',
        'ctax_label_name_field_description' => 'The name is how it appears on your site.',
        'ctax_label_slug_field_description' => 'The "slug" is the URL-friendly version of the name.',
        'ctax_label_parent_field_description' => 'Assign a parent term to create a hierarchy.',
        'ctax_label_desc_field_description' => 'The description is not prominent by default; however, some themes may show it.',
        'ctax_label_edit_item' => 'Edit ' . $singular,
        'ctax_label_view_item' => 'View ' . $singular,
        'ctax_label_update_item' => 'Update ' . $singular,
        'ctax_label_add_new_item' => 'Add New ' . $singular,
        'ctax_label_new_item_name' => 'New ' . $singular . ' Name',
        'ctax_label_separate_items_with_commas' => 'Separate ' . $lower_plural . ' with commas',
        'ctax_label_add_or_remove_items' => 'Add or remove ' . $lower_plural,
        'ctax_label_choose_from_most_used' => 'Choose from the most used ' . $lower_plural,
        'ctax_label_not_found' => 'No ' . $lower_plural . ' found.',
        'ctax_label_no_terms' => 'No ' . $lower_plural,
        'ctax_label_filter_by_item' => 'Filter by ' . $lower_singular,
        'ctax_label_items_list_navigation' => $plural . ' list navigation',
        'ctax_label_items_list' => $plural . ' list',
        'ctax_label_most_used' => 'Most Used',
        'ctax_label_back_to_items' => 'Back to ' . $plural,
        'ctax_label_item_link' => $singular . ' Link',
        'ctax_label_item_link_description' => 'A link to a ' . $lower_singular . '.',
    ];
}

/**
 * Write all postmeta for a newly created taxonomy.
 *
 * @param int                  $post_id
 * @param string               $slug
 * @param string               $singular
 * @param string               $plural
 * @param array<string, mixed> $input     Full create input.
 */
function tax_write_postmeta(int $post_id, string $slug, string $singular, string $plural, array $input): void
{
    update_post_meta($post_id, meta_key: 'ctax_key', meta_value: $slug);
    update_post_meta($post_id, meta_key: 'ctax_singular_name', meta_value: $singular);
    update_post_meta($post_id, meta_key: 'ctax_plural_name', meta_value: $plural);

    tax_write_post_types_meta($post_id, $input);
    tax_write_visibility_meta($post_id, $input);
    tax_write_admin_meta($post_id, $input);
    tax_write_rest_meta($post_id, $input);
    tax_write_labels_meta($post_id, $singular, $plural, $input);
    tax_write_frontend_meta($post_id, $input);
}

/**
 * Write ctax_post_types postmeta (serialized array).
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_write_post_types_meta(int $post_id, array $input): void
{
    /** @var mixed $post_types_input */
    $post_types_input = $input['post_types'] ?? [];
    $post_types = is_array($post_types_input) ? array_values(array_map('strval', $post_types_input)) : [];
    update_post_meta($post_id, meta_key: 'ctax_post_types', meta_value: $post_types);
}

/**
 * Write public/hierarchical postmeta.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_write_visibility_meta(int $post_id, array $input): void
{
    $public = ($input['public'] ?? true) !== false;
    // Taxonomies default hierarchical=true (like WP core categories).
    $hierarchical = ($input['hierarchical'] ?? true) !== false;

    update_post_meta($post_id, meta_key: 'ctax_public', meta_value: $public);
    update_post_meta($post_id, meta_key: 'ctax_hierarchical', meta_value: $hierarchical);
    update_post_meta($post_id, meta_key: 'ctax_publicly_queryable', meta_value: $public);
}

/**
 * Write admin-facing visibility postmeta keys.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_write_admin_meta(int $post_id, array $input): void
{
    $public = ($input['public'] ?? true) !== false;

    update_post_meta($post_id, meta_key: 'ctax_show_ui', meta_value: $public);
    update_post_meta($post_id, meta_key: 'ctax_show_in_menu', meta_value: $public);
    update_post_meta($post_id, meta_key: 'ctax_show_in_nav_menus', meta_value: $public);
    update_post_meta($post_id, meta_key: 'ctax_show_tagcloud', meta_value: $public);
    update_post_meta($post_id, meta_key: 'ctax_show_in_quick_edit', meta_value: $public);
    update_post_meta($post_id, meta_key: 'ctax_show_admin_column', meta_value: false);
}

/**
 * Write REST API postmeta keys.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_write_rest_meta(int $post_id, array $input): void
{
    $public = ($input['public'] ?? true) !== false;

    /** @var mixed $rest_input */
    $rest_input = $input['rest'] ?? [];
    $rest = is_array($rest_input) ? $rest_input : [];
    $show_in_rest = ($rest['show_in_rest'] ?? $public) !== false;
    update_post_meta($post_id, meta_key: 'ctax_show_in_rest', meta_value: $show_in_rest);
    update_post_meta($post_id, meta_key: 'ctax_rest_base', meta_value: (string) ($rest['rest_base'] ?? ''));
    update_post_meta($post_id, meta_key: 'ctax_rest_namespace', meta_value: '');
    update_post_meta($post_id, meta_key: 'ctax_rest_controller_class', meta_value: '');
}

/**
 * Write all 26 ctax_label_* postmeta keys, auto-generating defaults from singular+plural.
 *
 * @param int                  $post_id
 * @param string               $singular
 * @param string               $plural
 * @param array<string, mixed> $input
 */
function tax_write_labels_meta(int $post_id, string $singular, string $plural, array $input): void
{
    $label_defaults = tax_generate_labels($singular, $plural);

    /** @var mixed $labels_full_input */
    $labels_full_input = $input['labels_full'] ?? [];
    $labels_full = is_array($labels_full_input) ? $labels_full_input : [];

    foreach ($label_defaults as $meta_key => $default_value) {
        $agent_key = substr($meta_key, strlen('ctax_label_'));
        /** @var mixed $override */
        $override = $labels_full[$agent_key] ?? null;
        $value = $override !== null && is_string($override) ? $override : $default_value;
        update_post_meta($post_id, meta_key: $meta_key, meta_value: $value);
    }
}

/**
 * Write frontend/rewrite/query_var postmeta keys.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_write_frontend_meta(int $post_id, array $input): void
{
    /** @var mixed $rewrite_input */
    $rewrite_input = $input['rewrite'] ?? [];
    $rewrite = is_array($rewrite_input) ? $rewrite_input : [];

    $rewrite_enabled = ($rewrite['enabled'] ?? true) !== false;
    update_post_meta($post_id, meta_key: 'ctax_rewrite', meta_value: $rewrite_enabled);

    $rewrite_slug = (string) ($rewrite['slug'] ?? '');
    $use_custom_rewrite_slug = $rewrite_slug !== '';
    update_post_meta($post_id, meta_key: 'ctax_use_custom_rewrite_slug', meta_value: $use_custom_rewrite_slug);
    update_post_meta($post_id, meta_key: 'ctax_rewrite_custom_slug', meta_value: $rewrite_slug);
    update_post_meta($post_id, meta_key: 'ctax_with_front', meta_value: ($rewrite['with_front'] ?? true) !== false);
    update_post_meta(
        $post_id,
        meta_key: 'ctax_hierarchical_urls',
        meta_value: ($rewrite['hierarchical_urls'] ?? false) === true,
    );

    $ep_mask = (string) ($rewrite['ep_mask'] ?? 'EP_NONE');
    $ep_mask_enabled = $ep_mask !== '' && $ep_mask !== 'EP_NONE';
    update_post_meta($post_id, meta_key: 'ctax_ep_mask', meta_value: $ep_mask_enabled);
    update_post_meta($post_id, meta_key: 'ctax_ep_mask_custom', meta_value: $ep_mask_enabled ? $ep_mask : '');

    $query_var = ($rewrite['query_var'] ?? true) !== false;
    update_post_meta($post_id, meta_key: 'ctax_query_var', meta_value: $query_var);
    $query_var_string = (string) ($rewrite['query_var_string'] ?? '');
    $use_custom_qv = $query_var_string !== '';
    update_post_meta($post_id, meta_key: 'ctax_use_custom_query_var_string', meta_value: $use_custom_qv);
    update_post_meta($post_id, meta_key: 'ctax_query_var_string', meta_value: $query_var_string);

    update_post_meta($post_id, meta_key: 'ctax_sort', meta_value: false);
}

/**
 * Insert a new taxonomy definition post and write all its postmeta.
 *
 * @param string               $slug
 * @param string               $title
 * @param string               $singular
 * @param string               $plural
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function tax_do_insert(string $slug, string $title, string $singular, string $plural, array $input): array
{
    $post_id = wp_insert_post(postarr: [
        'post_type' => 'asenha_ctax',
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
        return ['error' => __('Failed to create taxonomy.', domain: 'novamira-pro'), 'code' => 'ase_create_failed'];
    }

    tax_write_postmeta($post_id, $slug, $singular, $plural, $input);
    flush_rewrite_rules();

    $shape = ctax_build_shape($post_id, $slug, $title, []);
    $shape['created'] = true;

    return $shape;
}

/**
 * Handle the case where a taxonomy with the same slug already exists on create.
 *
 * @param int                  $existing_id
 * @param string               $slug
 * @param string               $existing_title
 * @param array{singular: string, plural: string, input: array<string, mixed>} $incoming
 * @return array<string, mixed>
 */
function tax_handle_existing_on_create(int $existing_id, string $slug, string $existing_title, array $incoming): array
{
    $singular = $incoming['singular'];
    $plural = $incoming['plural'];
    $input = $incoming['input'];

    $existing_singular = (string) get_post_meta($existing_id, key: 'ctax_singular_name', single: true);
    $existing_plural = $existing_title;

    $payload_matches =
        $existing_title === (string) ($input['title'] ?? $existing_title)
        && $existing_singular === $singular
        && $existing_plural === $plural;

    if ($payload_matches) {
        $shape = ctax_build_shape($existing_id, $slug, $existing_title, []);
        $shape['created'] = false;

        return $shape;
    }

    return [
        'error' => sprintf(
            __('A taxonomy with slug "%s" already exists with different data.', domain: 'novamira-pro'),
            $slug,
        ),
        'code' => 'ase_taxonomy_slug_conflict',
    ];
}

// ---------------------------------------------------------------------------
// edit-taxonomy apply helpers
// ---------------------------------------------------------------------------

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_apply_title_update(int $post_id, array $input): void
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
    update_post_meta($post_id, meta_key: 'ctax_plural_name', meta_value: $new_title);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_apply_labels_update(int $post_id, array $input): void
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
            update_post_meta($post_id, meta_key: 'ctax_singular_name', meta_value: $singular);
        }
    }

    if (array_key_exists('plural', $labels)) {
        /** @var mixed $p */
        $p = $labels['plural'];
        $plural = is_string($p) ? trim($p) : '';
        if ($plural !== '') {
            update_post_meta($post_id, meta_key: 'ctax_plural_name', meta_value: $plural);
            wp_update_post(['ID' => $post_id, 'post_title' => $plural]);
        }
    }
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_apply_post_types_update(int $post_id, array $input): void
{
    if (!array_key_exists('post_types', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['post_types'];
    $post_types = is_array($raw) ? array_values(array_map('strval', $raw)) : [];
    update_post_meta($post_id, meta_key: 'ctax_post_types', meta_value: $post_types);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_apply_public_update(int $post_id, array $input): void
{
    if (!array_key_exists('public', $input)) {
        return;
    }

    $public = $input['public'] !== false;
    update_post_meta($post_id, meta_key: 'ctax_public', meta_value: $public);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_apply_hierarchical_update(int $post_id, array $input): void
{
    if (!array_key_exists('hierarchical', $input)) {
        return;
    }

    update_post_meta($post_id, meta_key: 'ctax_hierarchical', meta_value: $input['hierarchical'] === true);
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_apply_rest_update(int $post_id, array $input): void
{
    if (!array_key_exists('rest', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['rest'];
    $rest = is_array($raw) ? $raw : [];

    if (array_key_exists('show_in_rest', $rest)) {
        update_post_meta($post_id, meta_key: 'ctax_show_in_rest', meta_value: $rest['show_in_rest'] !== false);
    }

    if (array_key_exists('rest_base', $rest)) {
        update_post_meta($post_id, meta_key: 'ctax_rest_base', meta_value: (string) ($rest['rest_base'] ?? ''));
    }
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function tax_apply_labels_full_update(int $post_id, array $input): void
{
    if (!array_key_exists('labels_full', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['labels_full'];
    $labels_full = is_array($raw) ? $raw : [];

    $valid_keys = ctax_label_meta_keys();

    foreach ($valid_keys as $meta_key) {
        $agent_key = substr($meta_key, strlen('ctax_label_'));
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
function tax_apply_rewrite_update(int $post_id, array $input): void
{
    if (!array_key_exists('rewrite', $input)) {
        return;
    }

    /** @var mixed $raw */
    $raw = $input['rewrite'];
    $rewrite = is_array($raw) ? $raw : [];

    if (array_key_exists('enabled', $rewrite)) {
        update_post_meta($post_id, meta_key: 'ctax_rewrite', meta_value: $rewrite['enabled'] !== false);
    }

    if (array_key_exists('slug', $rewrite)) {
        $rewrite_slug = (string) ($rewrite['slug'] ?? '');
        $use_custom = $rewrite_slug !== '';
        update_post_meta($post_id, meta_key: 'ctax_use_custom_rewrite_slug', meta_value: $use_custom);
        update_post_meta($post_id, meta_key: 'ctax_rewrite_custom_slug', meta_value: $rewrite_slug);
    }

    if (array_key_exists('with_front', $rewrite)) {
        update_post_meta($post_id, meta_key: 'ctax_with_front', meta_value: $rewrite['with_front'] !== false);
    }

    if (array_key_exists('hierarchical_urls', $rewrite)) {
        update_post_meta(
            $post_id,
            meta_key: 'ctax_hierarchical_urls',
            meta_value: $rewrite['hierarchical_urls'] === true,
        );
    }

    if (array_key_exists('ep_mask', $rewrite)) {
        $ep_mask = (string) ($rewrite['ep_mask'] ?? 'EP_NONE');
        $ep_mask_enabled = $ep_mask !== '' && $ep_mask !== 'EP_NONE';
        update_post_meta($post_id, meta_key: 'ctax_ep_mask', meta_value: $ep_mask_enabled);
        update_post_meta($post_id, meta_key: 'ctax_ep_mask_custom', meta_value: $ep_mask_enabled ? $ep_mask : '');
    }

    if (array_key_exists('query_var', $rewrite)) {
        update_post_meta($post_id, meta_key: 'ctax_query_var', meta_value: $rewrite['query_var'] !== false);
    }

    if (array_key_exists('query_var_string', $rewrite)) {
        $qv_string = (string) ($rewrite['query_var_string'] ?? '');
        $use_custom_qv = $qv_string !== '';
        update_post_meta($post_id, meta_key: 'ctax_use_custom_query_var_string', meta_value: $use_custom_qv);
        update_post_meta($post_id, meta_key: 'ctax_query_var_string', meta_value: $qv_string);
    }
}
