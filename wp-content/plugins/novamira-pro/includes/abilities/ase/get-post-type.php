<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Get a single ASE post type with progressive disclosure.
 */
wp_register_ability('novamira/ase-get-post-type', [
    'label' => __('Get ASE Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single ASE CCT post type definition by slug. Default shape: key, slug, title, labels (singular+plural), supports, taxonomies, public, hierarchical, menu_position, menu_icon, rest, source, editable. Opt-in expansion via include array: "labels_full" (all 31 cpt_label_* postmeta keys), "capabilities" (capability_type, map_meta_cap, custom slugs), "rewrite" (slug, with_front, feeds, pages, ep_mask, query_var). Unknown include values are silently ignored.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The registered post type slug (e.g. "book").',
            ],
            'include' => [
                'type' => 'array',
                'description' => 'Optional sections to expand. Valid values: "labels_full", "capabilities", "rewrite". Unknown values are silently ignored.',
                'items' => ['type' => 'string'],
                'default' => [],
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
            'labels_full' => [
                'type' => 'object',
                'description' => 'All 31 label postmeta values. Only present when "labels_full" is in include.',
            ],
            'capabilities' => [
                'type' => 'object',
                'description' => 'Capability type and map_meta_cap settings. Only present when "capabilities" is in include.',
            ],
            'rewrite' => [
                'type' => 'object',
                'description' => 'Rewrite rules, query_var, ep_mask, and related settings. Only present when "rewrite" is in include.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_get_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns compact essentials by default. Use include: ["labels_full"] to see all 31 label keys, include: ["capabilities"] for capability_type/map_meta_cap settings, include: ["rewrite"] for slug/with_front/feeds/pages/ep_mask/query_var.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ase_get_post_type(array $input): array
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

    /** @var mixed $raw_include */
    $raw_include = $input['include'] ?? [];
    $include = is_array($raw_include) ? array_map('strval', $raw_include) : [];

    return cpt_build_shape($post->ID, $slug, $post->post_title, $include);
}

/**
 * Build the agent-facing shape for a post type definition.
 *
 * @param int           $post_id The asenha_cpt post ID.
 * @param string        $slug    The cpt_key value.
 * @param string        $title   The post_title (plural name).
 * @param list<string>  $include Expansion flags.
 * @return array<string, mixed>
 */
function cpt_build_shape(int $post_id, string $slug, string $title, array $include): array
{
    $singular = (string) get_post_meta($post_id, key: 'cpt_singular_name', single: true);

    $supports = cpt_read_serialized_array($post_id, meta_key: 'cpt_supports', default: ['title', 'editor']);
    $taxonomies = cpt_read_serialized_array($post_id, meta_key: 'cpt_taxonomies', default: []);

    $public = '1' === (string) get_post_meta($post_id, key: 'cpt_public', single: true);
    $hierarchical = '1' === (string) get_post_meta($post_id, key: 'cpt_hierarchical', single: true);

    /** @var mixed $raw_menu_position */
    $raw_menu_position = get_post_meta($post_id, key: 'cpt_menu_position', single: true);
    $menu_position = $raw_menu_position !== '' && $raw_menu_position !== false ? (int) $raw_menu_position : null;

    $menu_icon = (string) get_post_meta($post_id, key: 'cpt_menu_icon', single: true);

    $show_in_rest = '1' === (string) get_post_meta($post_id, key: 'cpt_show_in_rest', single: true);
    $rest_base = (string) get_post_meta($post_id, key: 'cpt_rest_base', single: true);

    $shape = [
        'key' => 'ase_cpt_' . $post_id,
        'slug' => $slug,
        'title' => $title,
        'labels' => [
            'singular' => $singular,
            'plural' => $title,
        ],
        'supports' => array_values($supports),
        'taxonomies' => array_values($taxonomies),
        'public' => $public,
        'hierarchical' => $hierarchical,
        'menu_position' => $menu_position,
        'menu_icon' => $menu_icon,
        'rest' => [
            'show_in_rest' => $show_in_rest,
            'rest_base' => $rest_base,
        ],
        'source' => 'db',
        'editable' => true,
    ];

    if (in_array('labels_full', $include, strict: true)) {
        $shape['labels_full'] = cpt_read_labels_full($post_id);
    }

    if (in_array('capabilities', $include, strict: true)) {
        $shape['capabilities'] = cpt_read_capabilities($post_id);
    }

    if (in_array('rewrite', $include, strict: true)) {
        $shape['rewrite'] = cpt_read_rewrite($post_id);
    }

    return $shape;
}

/**
 * Read all 31 cpt_label_* postmeta keys and return them keyed without the
 * "cpt_label_" prefix.
 *
 * @param int $post_id
 * @return array<string, string>
 */
function cpt_read_labels_full(int $post_id): array
{
    $keys = cpt_label_meta_keys();
    $result = [];

    foreach ($keys as $meta_key) {
        $agent_key = substr($meta_key, strlen('cpt_label_'));
        $result[$agent_key] = (string) get_post_meta($post_id, key: $meta_key, single: true);
    }

    return $result;
}

/**
 * Read capability-related postmeta.
 *
 * @param int $post_id
 * @return array<string, mixed>
 */
function cpt_read_capabilities(int $post_id): array
{
    $capability_type = (string) get_post_meta($post_id, key: 'cpt_capability_type', single: true);
    if ($capability_type === '') {
        $capability_type = 'post';
    }

    $map_meta_cap = '1' === (string) get_post_meta($post_id, key: 'cpt_map_meta_cap', single: true);
    $custom_singular = (string) get_post_meta($post_id, key: 'cpt_capability_type_custom_slug_singular', single: true);
    $custom_plural = (string) get_post_meta($post_id, key: 'cpt_capability_type_custom_slug_plural', single: true);

    return [
        'capability_type' => $capability_type,
        'map_meta_cap' => $map_meta_cap,
        'custom_singular' => $custom_singular,
        'custom_plural' => $custom_plural,
    ];
}

/**
 * Read rewrite-related postmeta.
 *
 * @param int $post_id
 * @return array<string, mixed>
 */
function cpt_read_rewrite(int $post_id): array
{
    $enabled = '1' === (string) get_post_meta($post_id, key: 'cpt_rewrite', single: true);

    $use_custom_slug = '1' === (string) get_post_meta($post_id, key: 'cpt_use_custom_rewrite_slug', single: true);
    $custom_slug = (string) get_post_meta($post_id, key: 'cpt_rewrite_custom_slug', single: true);
    $rewrite_slug = $use_custom_slug && $custom_slug !== '' ? $custom_slug : '';

    $with_front = '1' === (string) get_post_meta($post_id, key: 'cpt_with_front', single: true);
    $feeds = '1' === (string) get_post_meta($post_id, key: 'cpt_feeds', single: true);
    $pages = '1' === (string) get_post_meta($post_id, key: 'cpt_pages', single: true);

    $ep_mask_enabled = '1' === (string) get_post_meta($post_id, key: 'cpt_ep_mask', single: true);
    $ep_mask = $ep_mask_enabled
        ? (string) get_post_meta($post_id, key: 'cpt_ep_mask_custom', single: true)
        : 'EP_PERMALINK';

    $query_var = '1' === (string) get_post_meta($post_id, key: 'cpt_query_var', single: true);
    $query_var_string = (string) get_post_meta($post_id, key: 'cpt_query_var_string', single: true);

    return [
        'enabled' => $enabled,
        'slug' => $rewrite_slug,
        'with_front' => $with_front,
        'feeds' => $feeds,
        'pages' => $pages,
        'ep_mask' => $ep_mask,
        'query_var' => $query_var,
        'query_var_string' => $query_var_string,
    ];
}

/**
 * Read a serialized array postmeta value, falling back to a default.
 *
 * @param int          $post_id
 * @param string       $meta_key
 * @param array<mixed> $default
 * @return array<mixed>
 */
function cpt_read_serialized_array(int $post_id, string $meta_key, array $default): array
{
    /** @var mixed $raw */
    $raw = get_post_meta($post_id, key: $meta_key, single: true);

    if ($raw === false || $raw === '') {
        return $default;
    }

    if (is_string($raw)) {
        /** @var mixed $raw */
        $raw = maybe_unserialize($raw);
    }

    return is_array($raw) ? $raw : $default;
}

/**
 * Return the ordered list of all cpt_label_* postmeta keys.
 *
 * ASE stores 31 label keys (verified against the source save-cpt file).
 *
 * @return list<string>
 */
function cpt_label_meta_keys(): array
{
    return [
        'cpt_label_add_new',
        'cpt_label_add_new_item',
        'cpt_label_edit_item',
        'cpt_label_new_item',
        'cpt_label_view_item',
        'cpt_label_view_items',
        'cpt_label_search_items',
        'cpt_label_not_found',
        'cpt_label_not_found_in_trash',
        'cpt_label_parent_item_colon',
        'cpt_label_all_items',
        'cpt_label_archives',
        'cpt_label_attributes',
        'cpt_label_insert_into_item',
        'cpt_label_uploaded_to_this_item',
        'cpt_label_featured_image',
        'cpt_label_set_featured_image',
        'cpt_label_remove_featured_image',
        'cpt_label_use_featured_image',
        'cpt_label_menu_name',
        'cpt_label_filter_items_list',
        'cpt_label_filter_by_date',
        'cpt_label_items_list_navigation',
        'cpt_label_items_list',
        'cpt_label_item_published',
        'cpt_label_item_published_privately',
        'cpt_label_item_reverted_to_draft',
        'cpt_label_item_scheduled',
        'cpt_label_item_updated',
        'cpt_label_item_link',
        'cpt_label_item_link_description',
    ];
}
