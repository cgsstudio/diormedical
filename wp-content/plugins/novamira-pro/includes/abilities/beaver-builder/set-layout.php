<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-set-layout', [
    'label' => __('Set Beaver Builder Layout', domain: 'novamira-pro'),
    'description' => __(
        'Replaces a Beaver Builder layout atomically. `nodes` is a flat list of row, column-group, column, and module nodes. Each node has {id?, type, parent?, position?, module?, settings?}. IDs are generated when omitted, but parents must reference explicit IDs. Module nodes must set `module` or `settings.type` to a real Beaver module slug discovered with list-modules. Defaults to writing both published and draft layouts and enables Beaver Builder for the post. The HTML (raw-code) module is gated: if any node in the tree is an HTML module the whole write is rejected unless you pass `allow_html_module: true`, which is only for a real code snippet the user explicitly asked to embed — never for layout, styling, CSS, or content.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id whose Beaver Builder layout should be replaced.'],
            'post_id' => ['type' => 'integer', 'description' => 'Alias for `post`.'],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'draft', 'both'],
                'description' => 'Target layout status. Defaults to both.',
            ],
            'nodes' => [
                'type' => 'array',
                'description' => 'Flat node list. Valid type values: row, column-group, column, module.',
                'items' => ['type' => 'object'],
            ],
            'allow_html_module' => [
                'type' => 'boolean',
                'description' => 'Opt-in required if the tree contains a raw-code HTML module. Defaults false. Set true ONLY for a code snippet the user explicitly asked to embed, never for layout, styling, CSS, or content.',
            ],
        ],
        'required' => ['nodes'],
        'anyOf' => [
            ['required' => ['post']],
            ['required' => ['post_id']],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'statuses' => ['type' => 'array', 'items' => ['type' => 'string']],
            'builder_enabled' => ['type' => 'boolean'],
            'node_counts' => ['type' => 'object'],
            'nodes' => ['type' => 'array', 'items' => ['type' => 'object']],
            'edit_url' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_set_layout',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this for full-page replacement. Build with real Beaver nodes: row -> column-group -> column -> module. Container modules that accept children, such as box, may sit directly under rows. The HTML (raw-code) module is enforced-gated: a tree containing any HTML module is rejected (the error names the offending node ids) unless you pass `allow_html_module: true`, which is only for a real code snippet the user explicitly asked to embed — never for page sections, cards, stats, layout wrappers, CSS injection, or visual styling. Re-read with get-layout after large writes if you need settings defaults Beaver added during save.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_set_layout(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['post'] ?? $input['post_id'] ?? null;
    $post = bb_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $post_error = bb_write_post_error($post);
    if ($post_error instanceof WP_Error) {
        return $post_error;
    }

    if (!is_array($input['nodes'] ?? null)) {
        return bb_error(code: 'beaver_builder_invalid_nodes', message: 'Pass `nodes` as an array.', data: [
            'status' => 400,
        ]);
    }

    /** @var list<array<string, mixed>> $nodes */
    $nodes = $input['nodes'];
    $data = bb_layout_data_from_input($nodes);
    if ($data instanceof WP_Error) {
        return $data;
    }

    if (!bb_bool_arg($input['allow_html_module'] ?? false)) {
        $html_node_ids = bb_html_module_node_ids($data);
        if ($html_node_ids !== []) {
            return bb_html_gate_error($html_node_ids);
        }
    }

    $statuses = bb_target_statuses($input);
    $locked = bb_with_layout_lock(post_id: $post->ID, fn: fn(): array|WP_Error => beaver_builder_set_layout_locked(
        post_id: $post->ID,
        data: $data,
        statuses: $statuses,
    ));
    if ($locked instanceof WP_Error) {
        return $locked;
    }

    return $locked;
}

/**
 * @param array<string, \stdClass> $data
 * @param list<string> $statuses
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_set_layout_locked(int $post_id, array $data, array $statuses): array|WP_Error
{
    $prepared = [];
    $response_data = [];
    foreach ($statuses as $status) {
        $status_data = bb_clone_layout_data($data);
        $old_data = bb_layout_data_for_write(post_id: $post_id, status: $status);
        $lifecycle = bb_call_module_lifecycle_hooks(post_id: $post_id, new_data: $status_data, old_data: $old_data);
        if ($lifecycle instanceof WP_Error) {
            return $lifecycle;
        }
        $validation = bb_validate_layout_data(data: $status_data);
        if ($validation instanceof WP_Error) {
            return $validation;
        }
        bb_reindex_layout_positions($status_data);
        $prepared[$status] = $status_data;
        $response_data = $status_data;
    }

    foreach ($prepared as $status => $status_data) {
        bb_save_layout_data(post_id: $post_id, data: $status_data, statuses: [$status]);
    }

    return bb_write_response(post_id: $post_id, data: $response_data, statuses: $statuses);
}
