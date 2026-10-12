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
 * Ability: edit a field on a Voxel post type (partial). The field type and key
 * are immutable.
 */

wp_register_ability('novamira/voxel-edit-field', [
    'label' => __('Edit Voxel Field', domain: 'novamira-pro'),
    'description' => __(
        'Update a field on a Voxel post type. Change its label, required flag, description, and type-specific props. The field type and key cannot be changed (that would corrupt stored values). Only the values you provide are changed.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string', 'description' => 'The post type key.'],
            'field_key' => ['type' => 'string', 'description' => 'The field key to edit.'],
            'label' => ['type' => 'string', 'description' => 'New label.'],
            'required' => ['type' => 'boolean', 'description' => 'New required flag.'],
            'description' => ['type' => 'string', 'description' => 'New help text.'],
            'props' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Type-specific props to merge (excluding type/key).',
            ],
        ],
        'required' => ['post_type', 'field_key'],
        'additionalProperties' => false,
    ],
    'output_schema' => vx_field_op_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_edit_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial field update; type and key are immutable. Provided props are merged into the field config.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_edit_field(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $pt_key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $field_key = is_string($input['field_key'] ?? null) ? $input['field_key'] : '';

    $blank = vx_reject_blank_labels($input, ['label']);
    if ($blank instanceof WP_Error) {
        return $blank;
    }

    return vx_with_lock('post_types', static function () use ($input, $pt_key, $field_key): array|WP_Error {
        $all = vx_read_post_types_config();
        $config = vx_post_type_config_or_error($all, $pt_key);
        if ($config instanceof WP_Error) {
            return $config;
        }
        $fields = vx_config_field_list($config);
        $index = vx_find_field_index($fields, $field_key);
        if ($index === null) {
            return vx_not_found('field', $field_key);
        }

        $fields[$index] = vx_merge_field_edit($fields[$index], $input);
        $config['fields'] = array_values($fields);
        $all[$pt_key] = $config;
        vx_write_post_types_config($all);

        return vx_field_op_result($pt_key, $field_key);
    });
}

/**
 * Merge a partial edit into a stored field config. The `type` and `key` props
 * are immutable and are never overwritten.
 *
 * @param array<array-key, mixed> $field
 * @param array<array-key, mixed> $input
 * @return array<array-key, mixed>
 */
function vx_merge_field_edit(array $field, array $input): array
{
    if (isset($input['label']) && is_string($input['label'])) {
        $field['label'] = sanitize_text_field($input['label']);
    }
    if (isset($input['description']) && is_string($input['description'])) {
        $field['description'] = $input['description'];
    }
    if (array_key_exists('required', $input)) {
        $field['required'] = $input['required'] === true;
    }
    if (isset($input['props']) && is_array($input['props'])) {
        // Same reserved-key filter as add-field: label/type/key/required/
        // description never come raw from props (props.label is a stored-XSS
        // sink otherwise — it echoes unescaped in the Voxel admin).
        vx_apply_field_props($field, $input['props']);
    }
    return $field;
}
