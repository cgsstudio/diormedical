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
 * Ability: reorder the fields of a Voxel post type.
 */

wp_register_ability('novamira/voxel-reorder-fields', [
    'label' => __('Reorder Voxel Fields', domain: 'novamira-pro'),
    'description' => __(
        'Reorder a Voxel post type\'s fields. Provide the field keys in the desired order. Any existing field key you omit keeps its relative order after the ones you listed. Field order controls how the fields appear in the submission form and admin.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string', 'description' => 'The post type key.'],
            'order' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Field keys in the desired order.',
            ],
        ],
        'required' => ['post_type', 'order'],
        'additionalProperties' => false,
    ],
    'output_schema' => vx_field_op_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_reorder_fields',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Provide field keys in the new order; omitted keys are appended in their existing order. The result `fields` list reflects the new order.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_reorder_fields(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $pt_key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $order = vx_coerce_string_list($input['order'] ?? []);

    return vx_with_lock('post_types', static function () use ($pt_key, $order): array|WP_Error {
        $all = vx_read_post_types_config();
        $config = vx_post_type_config_or_error($all, $pt_key);
        if ($config instanceof WP_Error) {
            return $config;
        }
        $reordered = vx_reorder_field_list(vx_config_field_list($config), $order);

        $config['fields'] = $reordered;
        $all[$pt_key] = $config;
        vx_write_post_types_config($all);

        return vx_field_op_result($pt_key, field_key: null);
    });
}

/**
 * Reorder a field list by a list of field keys. Fields whose key is not present
 * in $order keep their original relative position, appended after the ordered
 * ones — so an incomplete order never drops fields.
 *
 * @param list<array<array-key, mixed>> $fields
 * @param list<string> $order
 * @return list<array<array-key, mixed>>
 */
function vx_reorder_field_list(array $fields, array $order): array
{
    // Map each key to the index of its FIRST occurrence (only keyed fields are
    // addressable by $order). Duplicate-keyed and keyless fields stay reachable
    // by index so they are never lost.
    $index_by_key = [];
    foreach ($fields as $i => $field) {
        $key = isset($field['key']) && is_string($field['key']) ? $field['key'] : '';
        if ($key !== '' && !isset($index_by_key[$key])) {
            $index_by_key[$key] = $i;
        }
    }

    $emitted = [];
    $reordered = [];
    foreach ($order as $key) {
        if (!isset($index_by_key[$key])) {
            continue;
        }
        $i = $index_by_key[$key];
        if (isset($emitted[$i])) {
            continue;
        }
        $emitted[$i] = true;
        $reordered[] = $fields[$i];
    }
    // Append every field not yet emitted, in original order. This includes
    // keyless and duplicate-keyed fields, so a partial or malformed order can
    // never drop a field (upholds the never-drops contract).
    foreach ($fields as $i => $field) {
        if (isset($emitted[$i])) {
            continue;
        }
        $reordered[] = $field;
    }

    return $reordered;
}
