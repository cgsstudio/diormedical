<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Get a single ASE taxonomy with progressive disclosure.
 */
wp_register_ability('novamira/ase-get-taxonomy', [
    'label' => __('Get ASE Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single ASE CCT taxonomy definition by slug. Default shape: key, slug, title, labels (singular+plural), post_types, public, hierarchical, rest, source, editable. Opt-in expansion via include array: "labels_full" (all 26 ctax_label_* postmeta keys), "rewrite" (slug, with_front, hierarchical_urls, ep_mask, query_var). Unknown include values are silently ignored. No "capabilities" include — taxonomies use WordPress core\'s fixed capability model.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The registered taxonomy slug (e.g. "genre").',
            ],
            'include' => [
                'type' => 'array',
                'description' => 'Optional sections to expand. Valid values: "labels_full", "rewrite". Unknown values are silently ignored.',
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
            'post_types' => ['type' => 'array'],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'rest' => ['type' => 'object'],
            'source' => ['type' => 'string'],
            'editable' => ['type' => 'boolean'],
            'labels_full' => [
                'type' => 'object',
                'description' => 'All 26 label postmeta values. Only present when "labels_full" is in include.',
            ],
            'rewrite' => [
                'type' => 'object',
                'description' => 'Rewrite rules, query_var, ep_mask, and related settings. Only present when "rewrite" is in include.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_get_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns compact essentials by default. Use include: ["labels_full"] to see all 26 label keys, include: ["rewrite"] for slug/with_front/hierarchical_urls/ep_mask/query_var.',
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
function ase_get_taxonomy(array $input): array
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

    $post = ase_get_taxonomy_by_slug($slug);
    if ($post === null) {
        return [
            'error' => sprintf(__('Taxonomy "%s" not found.', domain: 'novamira-pro'), $slug),
            'code' => 'ase_taxonomy_not_found',
        ];
    }

    /** @var mixed $raw_include */
    $raw_include = $input['include'] ?? [];
    $include = is_array($raw_include) ? array_map('strval', $raw_include) : [];

    return ctax_build_shape($post->ID, $slug, $post->post_title, $include);
}

/**
 * Build the agent-facing shape for a taxonomy definition.
 *
 * @param int           $post_id The asenha_ctax post ID.
 * @param string        $slug    The ctax_key value.
 * @param string        $title   The post_title (plural name).
 * @param list<string>  $include Expansion flags.
 * @return array<string, mixed>
 */
function ctax_build_shape(int $post_id, string $slug, string $title, array $include): array
{
    $singular = (string) get_post_meta($post_id, key: 'ctax_singular_name', single: true);

    $post_types = ctax_read_serialized_array($post_id, meta_key: 'ctax_post_types', default: []);

    $public = '1' === (string) get_post_meta($post_id, key: 'ctax_public', single: true);
    $hierarchical = '1' === (string) get_post_meta($post_id, key: 'ctax_hierarchical', single: true);

    $show_in_rest = '1' === (string) get_post_meta($post_id, key: 'ctax_show_in_rest', single: true);
    $rest_base = (string) get_post_meta($post_id, key: 'ctax_rest_base', single: true);

    $shape = [
        'key' => 'ase_tax_' . $post_id,
        'slug' => $slug,
        'title' => $title,
        'labels' => [
            'singular' => $singular,
            'plural' => $title,
        ],
        'post_types' => array_values($post_types),
        'public' => $public,
        'hierarchical' => $hierarchical,
        'rest' => [
            'show_in_rest' => $show_in_rest,
            'rest_base' => $rest_base,
        ],
        'source' => 'db',
        'editable' => true,
    ];

    if (in_array('labels_full', $include, strict: true)) {
        $shape['labels_full'] = ctax_read_labels_full($post_id);
    }

    if (in_array('rewrite', $include, strict: true)) {
        $shape['rewrite'] = ctax_read_rewrite($post_id);
    }

    return $shape;
}

/**
 * Read all 26 ctax_label_* postmeta keys and return them keyed without the
 * "ctax_label_" prefix.
 *
 * @param int $post_id
 * @return array<string, string>
 */
function ctax_read_labels_full(int $post_id): array
{
    $keys = ctax_label_meta_keys();
    $result = [];

    foreach ($keys as $meta_key) {
        $agent_key = substr($meta_key, strlen('ctax_label_'));
        $result[$agent_key] = (string) get_post_meta($post_id, key: $meta_key, single: true);
    }

    return $result;
}

/**
 * Read rewrite-related postmeta.
 *
 * @param int $post_id
 * @return array<string, mixed>
 */
function ctax_read_rewrite(int $post_id): array
{
    $enabled = '1' === (string) get_post_meta($post_id, key: 'ctax_rewrite', single: true);

    $use_custom_slug = '1' === (string) get_post_meta($post_id, key: 'ctax_use_custom_rewrite_slug', single: true);
    $custom_slug = (string) get_post_meta($post_id, key: 'ctax_rewrite_custom_slug', single: true);
    $rewrite_slug = $use_custom_slug && $custom_slug !== '' ? $custom_slug : '';

    $with_front = '1' === (string) get_post_meta($post_id, key: 'ctax_with_front', single: true);
    $hierarchical_urls = '1' === (string) get_post_meta($post_id, key: 'ctax_hierarchical_urls', single: true);

    $ep_mask_enabled = '1' === (string) get_post_meta($post_id, key: 'ctax_ep_mask', single: true);
    $ep_mask = $ep_mask_enabled
        ? (string) get_post_meta($post_id, key: 'ctax_ep_mask_custom', single: true)
        : 'EP_NONE';

    $query_var = '1' === (string) get_post_meta($post_id, key: 'ctax_query_var', single: true);
    $query_var_string = (string) get_post_meta($post_id, key: 'ctax_query_var_string', single: true);

    return [
        'enabled' => $enabled,
        'slug' => $rewrite_slug,
        'with_front' => $with_front,
        'hierarchical_urls' => $hierarchical_urls,
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
function ctax_read_serialized_array(int $post_id, string $meta_key, array $default): array
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
 * Return the ordered list of all ctax_label_* postmeta keys.
 *
 * ASE stores 26 label keys (verified against the source ctax-form-fields.php).
 *
 * @return list<string>
 */
function ctax_label_meta_keys(): array
{
    return [
        'ctax_label_search_items',
        'ctax_label_popular_items',
        'ctax_label_all_items',
        'ctax_label_parent_item',
        'ctax_label_parent_item_colon',
        'ctax_label_name_field_description',
        'ctax_label_slug_field_description',
        'ctax_label_parent_field_description',
        'ctax_label_desc_field_description',
        'ctax_label_edit_item',
        'ctax_label_view_item',
        'ctax_label_update_item',
        'ctax_label_add_new_item',
        'ctax_label_new_item_name',
        'ctax_label_separate_items_with_commas',
        'ctax_label_add_or_remove_items',
        'ctax_label_choose_from_most_used',
        'ctax_label_not_found',
        'ctax_label_no_terms',
        'ctax_label_filter_by_item',
        'ctax_label_items_list_navigation',
        'ctax_label_items_list',
        'ctax_label_most_used',
        'ctax_label_back_to_items',
        'ctax_label_item_link',
        'ctax_label_item_link_description',
    ];
}
