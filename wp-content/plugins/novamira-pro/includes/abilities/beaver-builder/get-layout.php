<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-get-layout', [
    'label' => __('Get Beaver Builder Layout', domain: 'novamira-pro'),
    'description' => __(
        'Reads one Beaver Builder layout from `_fl_builder_data` (published) or `_fl_builder_draft` (draft). Returns a compact flat node list with id, type, parent, position, children, module slug, global/dynamic flags, and optional settings. Accepts the post id as `post` or `post_id`; use include_settings sparingly because module settings can be large and are byte-capped.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id whose Beaver Builder layout to read.'],
            'post_id' => ['type' => 'integer', 'description' => 'Alias for `post`.'],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'draft'],
                'description' => 'Layout status to read. Defaults to published.',
            ],
            'include_settings' => [
                'type' => 'boolean',
                'description' => 'Include each node settings object. Defaults to false.',
            ],
            'include_layout_settings' => [
                'type' => 'boolean',
                'description' => 'Include page-level Beaver Builder layout settings. Defaults to false.',
            ],
            'max_nodes' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 2000,
                'description' => 'Maximum nodes to return. Defaults to 400.',
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Per-settings string byte cap when include_settings is true. Defaults to 2048; 0 disables.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'post_type' => ['type' => 'string'],
            'post_status' => ['type' => 'string'],
            'builder_enabled' => ['type' => 'boolean'],
            'status' => ['type' => 'string'],
            'node_counts' => ['type' => 'object'],
            'nodes' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total_nodes' => ['type' => 'integer'],
            'returned_nodes' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'layout_settings' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_get_layout',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use after list-layouts to inspect a page or template. The node id is Beaver Builder\'s stable node identifier; parent/children describe the tree. Re-read before later writes because the draft and published layouts can diverge.',
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
function beaver_builder_get_layout(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['post'] ?? $input['post_id'] ?? null;
    $post = bb_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $status = isset($input['status']) && $input['status'] === 'draft' ? 'draft' : 'published';
    $include_settings = bb_bool_arg($input['include_settings'] ?? false);
    $include_layout_settings = bb_bool_arg($input['include_layout_settings'] ?? false);
    $max_nodes = bb_int_arg(input: $input, key: 'max_nodes', default: 400, min: 1, max: 2000);
    $max_field_bytes = bb_int_arg(input: $input, key: 'max_field_bytes', default: 2048, min: 0, max: 1_000_000);

    $data = bb_layout_data(post_id: $post->ID, status: $status);
    $nodes = bb_layout_nodes(
        data: $data,
        settings_mode: $include_settings ? 'settings' : 'compact',
        max_nodes: $max_nodes,
        max_field_bytes: $max_field_bytes,
    );
    $result = [
        'post_id' => $post->ID,
        'post_type' => $post->post_type,
        'post_status' => $post->post_status,
        'builder_enabled' => bb_layout_enabled($post->ID),
        'status' => $status,
        'node_counts' => bb_layout_counts($data),
        'nodes' => $nodes,
        'total_nodes' => count($data),
        'returned_nodes' => count($nodes),
        'truncated' => count($data) > count($nodes),
    ];

    if ($include_layout_settings && class_exists('FLBuilderModel')) {
        /** @var mixed $settings */
        $settings = \FLBuilderModel::get_layout_settings($status, $post->ID);
        $jsonable = bb_jsonable(value: $settings, max_field_bytes: $max_field_bytes);
        $result['layout_settings'] = is_array($jsonable) ? (object) $jsonable : (object) [];
    }

    return $result;
}
