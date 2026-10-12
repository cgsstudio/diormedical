<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Enable and configure a query loop on a module.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-enable-loop', [
    'label' => __('Enable Divi Loop', domain: 'novamira-pro'),
    'description' => __(
        'Enables a query loop on a loop-capable module so it repeats once per query result. Identify the module by "address" (from novamira/divi-get-content). Configure the query with query_type (see novamira/divi-list-loop-query-types), sub_types (e.g. post-type slugs ["post"]), order_by, order, per_page, offset. Only modules flagged loop_capable by novamira/divi-list-modules accept a loop (note: divi/blog runs its own query and is not loop-capable). Writes module.advanced.loop. Accepts the post id as "post" or "post_id".',
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
                'description' => 'What to loop over (default "post_types").',
            ],
            'sub_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Query subtypes (post-type slugs / taxonomy names / role slugs / menu ids), per query_type.',
            ],
            'order_by' => ['type' => 'string', 'description' => 'WP_Query orderby (default "date").'],
            'order' => [
                'type' => 'string',
                'enum' => ['ASC', 'DESC'],
                'description' => 'Sort direction (default "DESC").',
            ],
            'per_page' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'description' => 'Results per page (default 10, max 100).',
            ],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Result offset (default 0).'],
        ],
        'required' => ['address'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
            'loop_id' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_enable_loop',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Turn a module into a repeating loop. Check loop_capable via novamira/divi-list-modules first, and valid query_type/order_by via novamira/divi-list-loop-query-types. Inside the loop, bind fields to loop_* dynamic sources with novamira/divi-apply-dynamic-content.',
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
function divi_enable_loop(array $input)
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

    $loop_id = divi_generate_loop_id();
    $value = [
        'enable' => 'on',
        'loopId' => $loop_id,
        'queryType' => divi_string_arg($input, key: 'query_type', default: 'post_types'),
        'subTypes' => divi_loop_subtypes(divi_string_list_arg($input, key: 'sub_types')),
        'orderBy' => divi_string_arg($input, key: 'order_by', default: 'date'),
        'order' => divi_string_arg($input, key: 'order', default: 'DESC'),
        'postPerPage' => divi_clamp_per_page(divi_int_arg_or_null($input, key: 'per_page') ?? 10),
        'postOffset' => max(0, divi_int_arg_or_null($input, key: 'offset') ?? 0),
    ];

    // Replace (not merge) the loop leaf: enabling configures a fresh loop, so a
    // bare re-enable must not leave a previous run's subTypes behind.
    $result = divi_replace_field_value(
        $post,
        $address,
        field: 'module.advanced.loop',
        breakpoint: 'desktop',
        leaf_value: $value,
    );
    if ($result instanceof WP_Error) {
        return $result;
    }

    return ['post_id' => $post->ID, 'address' => $address, 'loop_id' => $loop_id];
}
