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
 * Ability: remove a field from a Voxel post type.
 */

wp_register_ability('novamira/voxel-delete-field', [
    'label' => __('Delete Voxel Field', domain: 'novamira-pro'),
    'description' => __(
        'Remove a field from a Voxel post type\'s field list. Existing posts keep the underlying stored value in the database (it just stops being used); this ability does not migrate or clean up that data. Use novamira/voxel-get-post-type include=["fields"] to see field keys.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string', 'description' => 'The post type key.'],
            'field_key' => ['type' => 'string', 'description' => 'The field key to remove.'],
        ],
        'required' => ['post_type', 'field_key'],
        'additionalProperties' => false,
    ],
    'output_schema' => vx_field_op_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_delete_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes the field from the config only; orphaned post meta is not cleaned up. The affected `field` in the result is null. Refuses to delete a "product" field, which would change the post type\'s price-index schema.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_delete_field(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $pt_key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $field_key = is_string($input['field_key'] ?? null) ? $input['field_key'] : '';

    return vx_with_lock('post_types', static function () use ($pt_key, $field_key): array|WP_Error {
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

        // Deleting a product field is a schema change in disguise: Voxel decides a
        // post type has a price index table by looking for exactly this field
        // (Index_Table::has_price_index_table), so removing it strands that table
        // on disk with nothing referencing it. This surface cannot create a
        // product field, cannot recreate one, and cannot rebuild the price index —
        // so it has no business removing one either. add-field refuses the type;
        // this is the missing half of that rule.
        if (($fields[$index]['type'] ?? null) === 'product') {
            return vx_invalid(__(
                'Product fields cannot be deleted by this ability: removing one changes the post type\'s price-index schema, which only the Voxel admin can rebuild.',
                domain: 'novamira-pro',
            ));
        }

        array_splice($fields, offset: $index, length: 1);
        $config['fields'] = array_values($fields);
        $all[$pt_key] = $config;
        vx_write_post_types_config($all);

        return vx_field_op_result($pt_key, field_key: null);
    });
}
