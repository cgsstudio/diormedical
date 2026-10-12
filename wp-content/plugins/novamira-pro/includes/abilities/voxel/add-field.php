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
 * Ability: add a field to a Voxel post type.
 */

wp_register_ability('novamira/voxel-add-field', [
    'label' => __('Add Voxel Field', domain: 'novamira-pro'),
    'description' => __(
        'Add a field to a Voxel post type. Provide the post type, the field type (e.g. text, number, select, switcher, taxonomy, location, image), a unique field key, and a label. Optionally set required, description, and a props object for type-specific settings (placeholder, options, min/max, etc.). The field is appended to the post type\'s field list. Use novamira/voxel-get-post-type include=["fields"] to see existing keys. Product fields are not supported here.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string', 'description' => 'The post type key.'],
            'type' => ['type' => 'string', 'description' => 'The field type (must be a registered Voxel field type).'],
            'key' => ['type' => 'string', 'description' => 'A unique field key (lowercase, hyphens/underscores).'],
            'label' => ['type' => 'string', 'description' => 'The field label.'],
            'required' => ['type' => 'boolean', 'description' => 'Whether the field is required.'],
            'description' => ['type' => 'string', 'description' => 'Help text.'],
            'props' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Type-specific props (placeholder, options, min, max, ...).',
            ],
        ],
        'required' => ['post_type', 'type', 'key', 'label'],
        'additionalProperties' => false,
    ],
    'output_schema' => vx_field_op_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_add_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The field key must be unique on the post type. Rejects unknown field types, the "product" type, and the reserved "product" key. Adding a field does not migrate existing posts.',
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
function voxel_add_field(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $pt_key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $type = is_string($input['type'] ?? null) ? $input['type'] : '';
    $field_key = is_string($input['key'] ?? null) ? $input['key'] : '';
    $label = is_string($input['label'] ?? null) ? sanitize_text_field($input['label']) : '';

    $invalid = vx_validate_add_field_input($type, $field_key, $label);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    return vx_with_lock('post_types', static function () use (
        $input,
        $pt_key,
        $type,
        $field_key,
        $label,
    ): array|WP_Error {
        $all = vx_read_post_types_config();
        $config = vx_post_type_config_or_error($all, $pt_key);
        if ($config instanceof WP_Error) {
            return $config;
        }
        $fields = vx_config_field_list($config);
        if (vx_find_field_index($fields, $field_key) !== null) {
            return vx_invalid(sprintf(
                /* translators: %s field key. */
                __('A field with key "%s" already exists on this post type.', domain: 'novamira-pro'),
                $field_key,
            ));
        }

        $fields[] = vx_build_field_config($type, $field_key, $label, $input);
        $config['fields'] = array_values($fields);
        $all[$pt_key] = $config;
        vx_write_post_types_config($all);

        return vx_field_op_result($pt_key, $field_key);
    });
}

/**
 * Validate the field type, key, and label before taking the config lock.
 * Rejects the reserved `product` type and key, unknown types, malformed keys
 * (rather than silently coercing them), and an empty label.
 */
function vx_validate_add_field_input(string $type, string $field_key, string $label): ?WP_Error
{
    if ($type === 'product') {
        return vx_invalid(__('Product fields are not supported by this ability.', domain: 'novamira-pro'));
    }
    // Blocking the type is not enough: the key alone is load-bearing. Voxel looks
    // for a field keyed `product` to decide whether a post type has a price index,
    // so occupying that key with any other field type would permanently prevent a
    // real product field from being added in the Voxel admin.
    if (in_array($field_key, vx_reserved_field_keys(), strict: true)) {
        return vx_invalid(sprintf(
            /* translators: %s field key. */
            __('The field key "%s" is reserved by Voxel and cannot be used by this ability.', domain: 'novamira-pro'),
            $field_key,
        ));
    }
    if (!vx_field_type_exists($type)) {
        return vx_invalid(sprintf(
            /* translators: %s field type. */
            __('"%s" is not a registered field type.', domain: 'novamira-pro'),
            $type,
        ));
    }
    // Reject malformed keys instead of silently coercing them (sanitize_key would
    // turn "Bad Key!" into "badkey" and store the field under a key the caller
    // never asked for). Matches create-post-type / create-taxonomy validation.
    if ($field_key === '' || sanitize_key($field_key) !== $field_key) {
        return vx_invalid(__(
            'Field key must be lowercase letters, numbers, hyphens and underscores only.',
            domain: 'novamira-pro',
        ));
    }
    if ($label === '') {
        return vx_invalid(__('A field label is required.', domain: 'novamira-pro'));
    }

    return null;
}
