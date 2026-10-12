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
 * Ability: read one field's full definition on a Voxel post type.
 */

wp_register_ability('novamira/voxel-get-field', [
    'label' => __('Get Voxel Field', domain: 'novamira-pro'),
    'description' => __(
        'Read the full definition of one field on a Voxel post type: its type, label, whether it is required, and the complete raw properties (placeholder, min and max length, conditions, visibility rules, default value, and any type-specific settings). Use novamira/voxel-get-post-type with include "fields" first to see the available field keys.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'description' => 'The Voxel post type key, e.g. "places".',
            ],
            'field_key' => [
                'type' => 'string',
                'description' => 'The field key, e.g. "text-2". See novamira/voxel-get-post-type include=["fields"].',
            ],
        ],
        'required' => ['post_type', 'field_key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'required' => ['type' => 'boolean'],
            'props' => [
                'type' => 'object',
                'description' => 'The complete raw field definition array (Voxel field props).',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['post_type', 'key', 'type', 'label', 'required', 'props'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Full field definition. For just the list of fields, use novamira/voxel-get-post-type include=["fields"] which returns compact rows.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{post_type: string, key: string, type: string, label: string, required: bool, props: array<string, mixed>}|WP_Error
 */
function voxel_get_field(array $input): array|WP_Error
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

    $field_key = is_string($input['field_key'] ?? null) ? $input['field_key'] : '';
    $field = vx_field_or_error($post_type, $field_key);
    if ($field instanceof WP_Error) {
        return $field;
    }

    $props = $field->get_props();

    $row = vx_field_row($field);

    return [
        'post_type' => $key,
        'key' => $row['key'],
        'type' => $row['type'],
        'label' => $row['label'],
        'required' => $row['required'],
        'props' => $props,
    ];
}
