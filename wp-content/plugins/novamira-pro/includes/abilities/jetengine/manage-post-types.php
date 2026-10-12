<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use Exception;
use Jet_Engine_CPT_Data;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ----- create -----

wp_register_ability('novamira/jetengine-create-post-type', [
    'label' => __('Create JetEngine Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Register a new Custom Post Type through JetEngine. The CPT is persisted in JetEngine\'s store and registered via the standard register_post_type, so it is a normal WordPress post type AND shows up in JetEngine > Post Types. Input mirrors JetEngine\'s own grouped shape: general_settings (requires name; slug is generated from name if omitted; custom_storage:false keeps post data in wp_posts), labels, advanced_settings (public, supports, hierarchical, has_archive, show_in_rest…), and meta_fields. A conflicting slug returns post_type_slug_exists — use jetengine-edit-post-type instead. NOTE: this creates the post-type DEFINITION. JetEngine registers post types on the WordPress init hook, so a type created here only becomes a queryable, registered WordPress post type from the NEXT request onward — calling novamira/create-post with this slug in the SAME turn will fail with invalid_post_type. Create the type first, then create individual posts of it in a subsequent call.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'general_settings' => [
                'type' => 'object',
                'description' => 'Core settings. Requires name (the plural display name). slug is optional (generated from name if omitted). custom_storage (bool, default false) stores post data in a JetEngine custom table instead of wp_posts.',
            ],
            'labels' => [
                'type' => 'object',
                'description' => 'WordPress UI labels (singular_name, menu_name, add_new_item, edit_item, …). Optional; WordPress derives sensible defaults from the name.',
            ],
            'advanced_settings' => [
                'type' => 'object',
                'description' => 'register_post_type args: public, exclude_from_search, publicly_queryable, show_ui, show_in_menu, show_in_rest, query_var, rewrite, has_archive, hierarchical, capability_type, menu_position, menu_icon, supports (array). Optional.',
            ],
            'meta_fields' => [
                'type' => 'array',
                'description' => 'Optional initial meta-box field definitions for this post type.',
            ],
        ],
        'required' => ['general_settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'created' => ['type' => 'boolean'],
        ],
        'required' => ['slug', 'created'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_create_post_type',
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

// ----- edit -----

wp_register_ability('novamira/jetengine-edit-post-type', [
    'label' => __('Edit JetEngine Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Update an existing JetEngine Custom Post Type, identified by slug. Provide any of general_settings, labels, advanced_settings, meta_fields — each provided group is merged per-key over the stored definition; meta_fields, when provided, replaces the whole array. The slug itself is immutable here (changing it would require remapping existing posts). PHP-registered / built-in types that are not in JetEngine\'s store return post_type_not_found.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'The slug of the post type to edit.'],
            'general_settings' => [
                'type' => 'object',
                'description' => 'Partial general_settings; merged per-key (slug is ignored — immutable here).',
            ],
            'labels' => ['type' => 'object', 'description' => 'Partial labels; merged per-key.'],
            'advanced_settings' => ['type' => 'object', 'description' => 'Partial advanced_settings; merged per-key.'],
            'meta_fields' => [
                'type' => 'array',
                'description' => 'When provided, replaces the full meta_fields array.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'edited' => ['type' => 'boolean'],
        ],
        'required' => ['slug', 'edited'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_edit_post_type',
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

// ----- delete -----

wp_register_ability('novamira/jetengine-delete-post-type', [
    'label' => __('Delete JetEngine Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Permanently remove a JetEngine Custom Post Type DEFINITION by slug (it disappears from JetEngine > Post Types and is no longer registered). Existing posts of that type are NOT deleted by this call — they become orphaned until the type is re-registered or they are removed separately. Types not managed by JetEngine return post_type_not_found.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'The slug of the post type to delete.'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'slug' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'slug'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_delete_post_type',
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

// ----- callbacks -----

/**
 * Flatten JetEngine's grouped CPT shape into the single-level request array
 * that jet_engine()->cpt->data->set_request() expects.
 *
 * The group keys (general_settings / labels / advanced_settings) do not collide,
 * so a plain merge reproduces the flat shape JetEngine's own REST controller
 * builds. meta_fields lives at the top level.
 *
 * @param array<array-key, mixed> $general_settings
 * @param array<array-key, mixed> $labels
 * @param array<array-key, mixed> $advanced_settings
 * @param list<mixed> $meta_fields
 * @return array<array-key, mixed>
 */
function pt_flatten_request(array $general_settings, array $labels, array $advanced_settings, array $meta_fields): array
{
    $flat = array_merge($general_settings, $labels, $advanced_settings);
    $flat['meta_fields'] = array_values($meta_fields);

    return $flat;
}

/**
 * Read a group sub-array from the ability input, defaulting to an empty array.
 *
 * @param array<string, mixed> $input
 * @return array<array-key, mixed>
 */
function pt_input_array(array $input, string $key): array
{
    return is_array($input[$key] ?? null) ? $input[$key] : [];
}

/**
 * Persist a new post type from a flat request and return its integer id.
 *
 * @param array<array-key, mixed> $flat
 * @return int|WP_Error
 */
function pt_create_persist(Jet_Engine_CPT_Data $data, array $flat): int|WP_Error
{
    try {
        $data->set_request($flat);
        $new_id = $data->create_item(false);
    } catch (Exception $e) {
        return new WP_Error('jetengine_write_failed', $e->getMessage());
    }

    $id = is_numeric($new_id) ? (int) $new_id : 0;
    if ($id <= 0) {
        return new WP_Error('jetengine_write_failed', __('Failed to persist the post type.', domain: 'novamira-pro'));
    }

    return $id;
}

/**
 * Read back the slug JetEngine stored for a freshly-created post type
 * (it derives one from the name when the caller omits it).
 */
function pt_resolve_slug(Jet_Engine_CPT_Data $data, int $id, string $fallback): string
{
    $stored = $data->get_item_for_edit($id);
    if (!is_array($stored)) {
        return $fallback;
    }

    $general = jetengine_cct_coerce_array($stored['general_settings'] ?? null);
    $slug = (string) ($general['slug'] ?? '');

    return $slug !== '' ? $slug : $fallback;
}

/**
 * @param array<string, mixed> $input
 * @return array{slug: string, created: bool}|WP_Error
 */
function jetengine_create_post_type(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $general_settings = pt_input_array($input, key: 'general_settings');
    $name = (string) ($general_settings['name'] ?? '');
    if ($name === '') {
        return new WP_Error('invalid_input', __('general_settings.name is required.', domain: 'novamira-pro'));
    }

    $data = jetengine_cpt_data();
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine post-types data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    // JetEngine's server-side sanitize_item_request() hard-rejects an empty
    // slug ("Please set post type slug") — its admin UI derives the slug from
    // the name in JS before calling the REST endpoint, so the data layer never
    // sees a blank one. Mirror that here: when the caller omits the slug,
    // generate it from the name with JetEngine's own sanitizer so the stored
    // slug matches what the editor would have produced.
    $slug = (string) ($general_settings['slug'] ?? '');
    if ($slug === '') {
        $slug = $data->sanitize_slug($name);
        $general_settings['slug'] = $slug;
    }
    if ($slug === '') {
        return new WP_Error('invalid_input', __(
            'Could not derive a slug from the name; pass general_settings.slug explicitly.',
            domain: 'novamira-pro',
        ));
    }

    if (jetengine_cpt_find_row_by_slug($slug) !== null) {
        return new WP_Error(
            'post_type_slug_exists',
            __(
                'A post type with this slug already exists. Use jetengine-edit-post-type to modify it.',
                domain: 'novamira-pro',
            ),
            ['slug' => $slug],
        );
    }

    $flat = pt_flatten_request(
        $general_settings,
        pt_input_array($input, key: 'labels'),
        pt_input_array($input, key: 'advanced_settings'),
        array_values(pt_input_array($input, key: 'meta_fields')),
    );

    $new_id = pt_create_persist($data, $flat);
    if ($new_id instanceof WP_Error) {
        return $new_id;
    }

    jetengine_invalidate_raw_cache($data);

    return ['slug' => pt_resolve_slug($data, $new_id, $slug), 'created' => true];
}

/**
 * Merge an edit payload over the stored grouped definition and flatten it.
 *
 * @param array<string, mixed> $current The grouped item from get_item_for_edit().
 * @param array<string, mixed> $input   The ability input.
 * @return array<array-key, mixed>
 */
function pt_build_edit_flat(array $current, array $input, string $slug): array
{
    $general = array_replace(
        jetengine_cct_coerce_array($current['general_settings'] ?? null),
        is_array($input['general_settings'] ?? null) ? $input['general_settings'] : [],
    );
    $labels = array_replace(
        jetengine_cct_coerce_array($current['labels'] ?? null),
        is_array($input['labels'] ?? null) ? $input['labels'] : [],
    );
    $advanced = array_replace(
        jetengine_cct_coerce_array($current['advanced_settings'] ?? null),
        is_array($input['advanced_settings'] ?? null) ? $input['advanced_settings'] : [],
    );
    $fields = array_key_exists('meta_fields', $input) && is_array($input['meta_fields'])
        ? array_values($input['meta_fields'])
        : array_values(jetengine_cct_coerce_array($current['meta_fields'] ?? null));

    // The slug is immutable here: keep the stored one regardless of input.
    $general['slug'] = $slug;

    return pt_flatten_request($general, $labels, $advanced, $fields);
}

/**
 * @param array<string, mixed> $input
 * @return array{slug: string, edited: bool}|WP_Error
 */
function jetengine_edit_post_type(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = (string) ($input['slug'] ?? '');
    if ($slug === '') {
        return new WP_Error('invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    $row = jetengine_cpt_find_row_by_slug($slug);
    if ($row === null) {
        return new WP_Error('post_type_not_found', __(
            'Post type not found or not managed by JetEngine.',
            domain: 'novamira-pro',
        ));
    }

    $data = jetengine_cpt_data();
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine post-types data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $id = (int) ($row['id'] ?? 0);

    $current = $data->get_item_for_edit($id);
    if (!is_array($current)) {
        return new WP_Error('post_type_not_found', __('Post type not found.', domain: 'novamira-pro'));
    }

    $flat = pt_build_edit_flat($current, $input, $slug);
    $flat['id'] = $id;
    $flat['initial_slug'] = $slug;

    // JetEngine persists edits through edit_item(), NOT create_item(). The
    // underlying Jet_Engine_DB::update() is an upsert keyed on the row id:
    // edit_item() copies $request['id'] onto the sanitized item so the write
    // takes the UPDATE branch, whereas create_item() drops the id during
    // sanitize_item_from_request() and would INSERT a duplicate row with the
    // same slug. The boolean argument is the redirect flag (false = return,
    // no wp_safe_redirect()/die()), modelled on JetEngine's own REST
    // controller includes/components/post-types/rest-api/edit-post-type.php.
    try {
        $data->set_request($flat);
        $edited = $data->edit_item(false);
    } catch (Exception $e) {
        return new WP_Error('jetengine_write_failed', $e->getMessage());
    }

    if ($edited !== true) {
        return new WP_Error('jetengine_write_failed', __('Failed to update the post type.', domain: 'novamira-pro'));
    }

    jetengine_invalidate_raw_cache($data);

    return ['slug' => $slug, 'edited' => true];
}

/**
 * @param array<string, mixed> $input
 * @return array{deleted: bool, slug: string}|WP_Error
 */
function jetengine_delete_post_type(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = (string) ($input['slug'] ?? '');
    if ($slug === '') {
        return new WP_Error('invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    $row = jetengine_cpt_find_row_by_slug($slug);
    if ($row === null) {
        return new WP_Error('post_type_not_found', __(
            'Post type not found or not managed by JetEngine.',
            domain: 'novamira-pro',
        ));
    }

    $data = jetengine_cpt_data();
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine post-types data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $id = (int) ($row['id'] ?? 0);

    // Jet_Engine_Base_Data::delete_item() reads the row id from $this->request,
    // NOT from its argument — the single parameter is the redirect flag. Passing
    // the integer id positionally (delete_item($id)) makes $redirect truthy, so
    // JetEngine runs wp_safe_redirect()/die() AND, with no id in the request,
    // deletes nothing. Set the request first, then call with false (no redirect),
    // exactly as JetEngine's own REST controller
    // includes/components/post-types/rest-api/delete-post-type.php does.
    try {
        $data->set_request(['id' => $id]);
        $deleted = $data->delete_item(false);
    } catch (Exception $e) {
        return new WP_Error('jetengine_write_failed', $e->getMessage());
    }

    if ($deleted !== true) {
        return new WP_Error('jetengine_write_failed', __('Failed to delete the post type.', domain: 'novamira-pro'));
    }

    jetengine_invalidate_raw_cache($data);

    return ['deleted' => true, 'slug' => $slug];
}
