<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Edit the query parameters of an existing loop.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-edit-loop', [
    'label' => __('Edit Divi Loop', domain: 'novamira-pro'),
    'description' => __(
        'Updates the query parameters of an existing loop on the module at "address" without re-enabling it or changing its loop id. Pass any of query_type, sub_types, order_by, order, per_page, offset — only the provided ones change. Use novamira/divi-enable-loop to first turn a loop on. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Module address, e.g. "0/0/0".'],
            'query_type' => [
                'type' => 'string',
                'enum' => ['post_types', 'post_taxonomies', 'terms', 'users', 'user_roles', 'menus'],
                'description' => 'What to loop over.',
            ],
            'sub_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Query subtypes, per query_type.',
            ],
            'order_by' => ['type' => 'string', 'description' => 'WP_Query orderby.'],
            'order' => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'description' => 'Sort direction.'],
            'per_page' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'description' => 'Results per page (max 100).',
            ],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Result offset.'],
        ],
        'required' => ['address'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_edit_loop',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Adjust a live loop\'s query. Only the parameters you pass change; the loop stays enabled with its existing loop id.',
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
function divi_edit_loop(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    if (trim($address) === '') {
        return divi_error('invalid_input', message: 'An "address" is required.');
    }

    if (array_key_exists('sub_types', $input) && !is_array($input['sub_types'])) {
        return divi_error('invalid_input', message: '"sub_types" must be an array of strings.');
    }

    $node = divi_loop_node_or_error($post, $address);
    if ($node instanceof WP_Error) {
        return $node;
    }

    /** @var mixed $existing */
    $existing = $node['attrs']['module']['advanced']['loop']['desktop']['value'] ?? null;
    if (!is_array($existing)) {
        return divi_error(
            'no_loop',
            message: 'This module has no loop to edit. Enable one with novamira/divi-enable-loop.',
            status: 422,
        );
    }

    $partial = divi_loop_partial_from_input($input);
    if ($partial === []) {
        return divi_error('invalid_input', message: 'Provide at least one loop parameter to change.');
    }

    $result = divi_set_field_value($post, $address, field: 'module.advanced.loop', breakpoint: 'desktop', write: [
        'value' => $partial,
    ]);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return ['post_id' => $post->ID, 'address' => $address];
}
