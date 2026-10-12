<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Get a single ASE field group with progressive disclosure on fields.
 */
wp_register_ability('novamira/ase-get-field-group', [
    'label' => __('Get ASE Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single ASE CFT field group by its key (slug). Default shape: key, title, fields_count, location, show_in_rest_api. Pass include_fields: true to also expand the full fields[] array. A group with 30 repeater sub-fields would otherwise burn 5-10K tokens on every introspection — only request fields when you need them.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'The field group key (slug / post_name), e.g. "group_homepage_hero".',
            ],
            'include_fields' => [
                'type' => 'boolean',
                'description' => 'When true, expands the full fields[] array. Default false (omits fields[], returns fields_count only).',
                'default' => false,
            ],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'fields_count' => ['type' => 'integer'],
            'location' => ['type' => 'object'],
            'show_in_rest_api' => ['type' => 'boolean'],
            'source' => ['type' => 'string'],
            'editable' => ['type' => 'boolean'],
            'fields' => [
                'type' => 'array',
                'description' => 'Full field definitions. Only present when include_fields: true.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_get_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns compact metadata by default. Pass include_fields: true to expand fields[] — only do this when you need to inspect or edit individual field definitions.',
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
function ase_get_field_group(array $input): array
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
        'Enable the "Custom Field Types" module in ASE settings.',
        domain: 'novamira-pro',
    ));
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    /** @var mixed $raw_key */
    $raw_key = $input['key'] ?? '';
    $key = is_string($raw_key) ? trim($raw_key) : '';
    if ($key === '') {
        return ['error' => __('key is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    $post = ase_get_field_group_by_key($key);
    if ($post === null) {
        return [
            'error' => sprintf(__('Field group "%s" not found.', domain: 'novamira-pro'), $key),
            'code' => 'ase_field_group_not_found',
        ];
    }

    $include_fields = ($input['include_fields'] ?? false) === true;

    if ($include_fields) {
        return ase_build_field_group_shape_with_fields($post->ID, $post->post_name, $post->post_title);
    }

    return ase_build_field_group_shape_compact($post->ID, $post->post_name, $post->post_title);
}

/**
 * Build the compact agent-facing shape for a field group (no fields[] expansion).
 *
 * @param int    $post_id The post ID of the asenha_cfgroup CPT.
 * @param string $key     The field group slug (post_name).
 * @param string $title   The field group title.
 * @return array<string, mixed>
 */
function ase_build_field_group_shape_compact(int $post_id, string $key, string $title): array
{
    $fields = ase_fg_read_fields($post_id);
    $rules = ase_fg_read_rules($post_id);
    $extras = ase_fg_read_extras($post_id);
    $show_in_rest_api =
        ($extras['show_in_rest_api'] ?? null) !== null
        && $extras['show_in_rest_api'] !== 0
        && $extras['show_in_rest_api'] !== '';

    return [
        'key' => $key,
        'title' => $title,
        'fields_count' => count($fields),
        'location' => ase_normalize_location($rules),
        'show_in_rest_api' => $show_in_rest_api,
        'source' => 'db',
        'editable' => true,
    ];
}

/**
 * Build the full agent-facing shape for a field group including fields[] expansion.
 *
 * @param int    $post_id The post ID of the asenha_cfgroup CPT.
 * @param string $key     The field group slug (post_name).
 * @param string $title   The field group title.
 * @return array<string, mixed>
 */
function ase_build_field_group_shape_with_fields(int $post_id, string $key, string $title): array
{
    $shape = ase_build_field_group_shape_compact($post_id, $key, $title);
    $shape['fields'] = array_values(ase_fg_read_fields($post_id));

    return $shape;
}

/**
 * Read and deserialize the cfgroup_fields postmeta for a field group.
 *
 * @param int $post_id
 * @return array<mixed>
 */
function ase_fg_read_fields(int $post_id): array
{
    return ase_fg_read_postmeta($post_id, meta_key: 'cfgroup_fields');
}

/**
 * Read and deserialize the cfgroup_rules postmeta for a field group.
 *
 * @param int $post_id
 * @return array<mixed>
 */
function ase_fg_read_rules(int $post_id): array
{
    return ase_fg_read_postmeta($post_id, meta_key: 'cfgroup_rules');
}

/**
 * Read and deserialize the cfgroup_extras postmeta for a field group.
 *
 * @param int $post_id
 * @return array<mixed>
 */
function ase_fg_read_extras(int $post_id): array
{
    return ase_fg_read_postmeta($post_id, meta_key: 'cfgroup_extras');
}

/**
 * Read and maybe_unserialize a single postmeta value, returning an array.
 *
 * get_post_meta with single:true returns false when the key does not exist,
 * or the raw meta value (string for serialized data). maybe_unserialize
 * requires a string, so non-string values fall through to the empty-array
 * fallback.
 *
 * @param int    $post_id
 * @param string $meta_key
 * @return array<mixed>
 */
function ase_fg_read_postmeta(int $post_id, string $meta_key): array
{
    /** @var mixed $raw */
    $raw = get_post_meta($post_id, key: $meta_key, single: true);

    // WordPress auto-deserializes serialized meta values, so $raw may already
    // be an array. Accept both a serialized string and a pre-unserialized array.
    if (is_array($raw)) {
        return $raw;
    }

    if (!is_string($raw)) {
        return [];
    }

    /** @var mixed $value */
    $value = maybe_unserialize($raw);

    return is_array($value) ? $value : [];
}

/**
 * Normalize cfgroup_rules into the agent-facing location structure.
 *
 * ASE stores each rule type as ['operator' => '...', 'values' => [...]] when set.
 * We extract values only and normalize missing keys to empty arrays.
 *
 * @param array<mixed> $rules maybe_unserialize'd cfgroup_rules value.
 * @return array<string, mixed>
 */
function ase_normalize_location(array $rules): array
{
    /** @var mixed $raw_placement */
    $raw_placement = $rules['placement']['values'] ?? '';

    /** @var list<string> $placement */
    $placement = is_string($raw_placement) && $raw_placement !== '' ? [$raw_placement] : [];

    return [
        'placement' => $placement,
        'post_types' => ase_fg_extract_rule_values('post_types', $rules),
        'post_formats' => ase_fg_extract_rule_values('post_formats', $rules),
        'post_ids' => ase_fg_extract_rule_values('post_ids', $rules),
        'page_templates' => ase_fg_extract_rule_values('page_templates', $rules),
        'user_roles' => ase_fg_extract_rule_values('user_roles', $rules),
        'term_ids' => ase_fg_extract_rule_values('term_ids', $rules),
        'taxonomies' => ase_fg_extract_rule_values('taxonomies', $rules),
        'options_pages' => ase_fg_extract_rule_values('options_pages', $rules),
    ];
}

/**
 * Extract the `values` array from a single cfgroup_rules rule entry.
 *
 * @param string       $key  Rule key (e.g. 'post_types').
 * @param array<mixed> $r    The full cfgroup_rules array.
 * @return list<string>
 */
function ase_fg_extract_rule_values(string $key, array $r): array
{
    /** @var mixed $v */
    $v = $r[$key]['values'] ?? [];

    return is_array($v) ? array_values(array_filter(array_map('strval', $v))) : [];
}
