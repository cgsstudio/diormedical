<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-add-node', [
    'label' => __('Add Beaver Builder Node', domain: 'novamira-pro'),
    'description' => __(
        'Adds one row, column-group, column, or module node to an existing Beaver Builder layout. Use get-layout first to choose the parent id. Module nodes must set `module` or `settings.type`. Defaults to writing both published and draft layouts. The HTML (raw-code) module is gated: it is rejected unless you pass `allow_html_module: true`, which is only for a real code snippet the user explicitly asked to embed — never for layout, styling, CSS, or content.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer'],
            'post_id' => ['type' => 'integer'],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'draft', 'both'],
                'description' => 'Defaults to both.',
            ],
            'id' => ['type' => 'string', 'description' => 'Optional explicit Beaver node id. Generated when omitted.'],
            'type' => ['type' => 'string', 'enum' => ['row', 'column-group', 'column', 'module']],
            'parent' => ['type' => ['string', 'null'], 'description' => 'Parent node id; null/omitted for root rows.'],
            'parent_id' => ['type' => ['string', 'null'], 'description' => 'Alias for `parent`.'],
            'position' => ['type' => 'integer', 'description' => 'Sibling position. -1 or omitted appends.'],
            'module' => ['type' => 'string', 'description' => 'Module slug for module nodes.'],
            'settings' => ['type' => 'object', 'description' => 'Row/column/module settings object.'],
            'allow_html_module' => [
                'type' => 'boolean',
                'description' => 'Opt-in required to insert the raw-code HTML module. Defaults false. Set true ONLY for a code snippet the user explicitly asked to embed, never for layout, styling, CSS, or content.',
            ],
        ],
        'required' => ['type'],
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
            'added_node_id' => ['type' => 'string'],
            'statuses' => ['type' => 'array', 'items' => ['type' => 'string']],
            'node_counts' => ['type' => 'object'],
            'nodes' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_add_node',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Target one node insertion. Valid structure: row at root; column-group under row; column under row or column-group; module under column. Container modules that accept children, such as box, may sit directly under rows. For a whole section, prefer set-layout with a full row/column/native-module scaffold or call add-node for each structural node in order. The HTML (raw-code) module is enforced-gated: adding it is rejected unless you pass `allow_html_module: true`, which is only for a real code snippet the user explicitly asked to embed — never for layout, CSS, visual styling, or page content.',
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
function beaver_builder_add_node(array $input): array|WP_Error
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

    $node = bb_node_from_input($input);
    if ($node instanceof WP_Error) {
        return $node;
    }

    if (!bb_bool_arg($input['allow_html_module'] ?? false) && bb_is_html_module_node($node)) {
        return bb_html_gate_error([bb_property_string(object: $node, property: 'node')]);
    }

    $statuses = bb_target_statuses($input);
    $locked = bb_with_layout_lock(post_id: $post->ID, fn: fn(): array|WP_Error => beaver_builder_add_node_locked(
        post_id: $post->ID,
        node: $node,
        statuses: $statuses,
    ));
    if ($locked instanceof WP_Error) {
        return $locked;
    }

    $response = $locked;
    $response['added_node_id'] = bb_property_string(object: $node, property: 'node');
    return $response;
}

/**
 * @param list<string> $statuses
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_add_node_locked(int $post_id, \stdClass $node, array $statuses): array|WP_Error
{
    $prepared = [];
    $response_data = [];
    foreach ($statuses as $status) {
        $old_data = bb_layout_data_for_write(post_id: $post_id, status: $status);
        $data = bb_clone_layout_data($old_data);
        $to_add = bb_clone_node($node);
        $error = bb_append_or_insert_node(
            data: $data,
            node: $to_add,
            position: bb_property_int(object: $node, property: 'position'),
            preserved_modules: bb_existing_module_ids($old_data),
        );
        if ($error instanceof WP_Error) {
            return $error;
        }
        bb_normalize_column_sizes($data);
        $lifecycle = bb_call_module_lifecycle_hooks(post_id: $post_id, new_data: $data, old_data: $old_data);
        if ($lifecycle instanceof WP_Error) {
            return $lifecycle;
        }
        $prepared[$status] = $data;
        $response_data = $data;
    }

    foreach ($prepared as $status => $data) {
        bb_save_layout_data(post_id: $post_id, data: $data, statuses: [$status]);
    }

    return bb_write_response(post_id: $post_id, data: $response_data, statuses: $statuses);
}
