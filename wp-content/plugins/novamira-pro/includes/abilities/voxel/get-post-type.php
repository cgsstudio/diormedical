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
 * Ability: read one Voxel post type. Returns a compact summary always, and any
 * of fields / filters / order / templates / settings on demand (progressive
 * disclosure).
 */

wp_register_ability('novamira/voxel-get-post-type', [
    'label' => __('Get Voxel Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Read the structure of one Voxel post type. Returns a compact summary always (key, labels, public flag, description, field and filter counts). Pass include with any of "fields", "filters", "order", "templates", "settings" to add those sections inline: fields and filters return compact rows (use novamira/voxel-get-field for one field\'s full definition), order returns the sort options, templates returns the assigned Elementor template ids, and settings returns the raw post type settings.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'description' => 'The Voxel post type key, e.g. "places". See novamira/voxel-list-post-types.',
            ],
            'include' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['fields', 'filters', 'order', 'templates', 'settings'],
                ],
                'default' => [],
                'description' => 'Optional sections to embed. Omit for the summary only.',
            ],
        ],
        'required' => ['post_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
            'public' => ['type' => 'boolean'],
            'description' => ['type' => 'string'],
            'fields_count' => ['type' => 'integer'],
            'filters_count' => ['type' => 'integer'],
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'required' => ['type' => 'boolean'],
                    ],
                    'required' => ['key', 'type', 'label', 'required'],
                    'additionalProperties' => false,
                ],
            ],
            'filters' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                    'required' => ['key', 'type', 'label'],
                    'additionalProperties' => false,
                ],
            ],
            'order' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                    'required' => ['key', 'label'],
                    'additionalProperties' => false,
                ],
            ],
            'templates' => [
                'type' => 'object',
                'description' => 'Map of template slug (single, card, archive, form, ...) to the assigned template post id.',
                'additionalProperties' => ['type' => ['integer', 'null']],
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Raw Voxel post type settings slice.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['key', 'label', 'singular', 'plural', 'public', 'description', 'fields_count', 'filters_count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Summary is always returned; sections are opt-in via include to keep the response small. fields/filters are compact rows — call novamira/voxel-get-field for one field\'s full props.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_get_post_type(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $post_type = vx_post_type_or_error($key);
    if ($post_type instanceof WP_Error) {
        return $post_type;
    }

    $include = vx_coerce_string_list($input['include'] ?? []);

    $out = vx_post_type_row($post_type);
    $out['description'] = $post_type->get_description();

    return array_merge($out, vx_post_type_sections($post_type, $key, $include));
}

/**
 * Build the optional include sections for a post type.
 *
 * @param \Voxel\Post_Type $post_type
 * @param list<string> $include
 * @return array<string, mixed>
 */
function vx_post_type_sections(object $post_type, string $key, array $include): array
{
    $sections = [];

    if (in_array('fields', $include, strict: true)) {
        $sections['fields'] = vx_project_field_rows($post_type->get_fields());
    }
    if (in_array('filters', $include, strict: true)) {
        $sections['filters'] = vx_project_filter_rows($post_type->get_filters());
    }
    if (in_array('order', $include, strict: true)) {
        $sections['order'] = vx_read_order_rows($key);
    }
    if (in_array('templates', $include, strict: true)) {
        $sections['templates'] = $post_type->get_templates();
    }
    if (in_array('settings', $include, strict: true)) {
        /** @var mixed $settings */
        $settings = \Voxel\get('post_types.' . $key . '.settings');
        $sections['settings'] = is_array($settings) ? $settings : [];
    }

    return $sections;
}

/**
 * @param array<array-key, \Voxel\Post_Types\Fields\Base_Post_Field> $fields
 * @return list<array{key: string, type: string, label: string, required: bool}>
 */
function vx_project_field_rows(array $fields): array
{
    $rows = [];
    foreach ($fields as $field) {
        $rows[] = vx_field_row($field);
    }
    return $rows;
}

/**
 * @param array<array-key, \Voxel\Post_Types\Filters\Base_Filter> $filters
 * @return list<array{key: string, type: string, label: string}>
 */
function vx_project_filter_rows(array $filters): array
{
    $rows = [];
    foreach ($filters as $filter) {
        $rows[] = vx_filter_row($filter);
    }
    return $rows;
}

/**
 * Read the sort/order options for a post type as compact {key, label} rows.
 *
 * @return list<array{key: string, label: string}>
 */
function vx_read_order_rows(string $key): array
{
    /** @var mixed $order */
    $order = \Voxel\get('post_types.' . $key . '.search.order');
    if (!is_array($order)) {
        return [];
    }
    $rows = [];
    /** @var mixed $entry */
    foreach ($order as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $rows[] = [
            'key' => isset($entry['key']) && is_string($entry['key']) ? $entry['key'] : '',
            'label' => isset($entry['label']) && is_string($entry['label']) ? $entry['label'] : '',
        ];
    }
    return $rows;
}
