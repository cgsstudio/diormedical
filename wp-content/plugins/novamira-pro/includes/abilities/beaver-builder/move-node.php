<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-move-node', [
    'label' => __('Move Beaver Builder Node', domain: 'novamira-pro'),
    'description' => __('Moves a Beaver Builder node to a new parent and sibling position.', domain: 'novamira-pro'),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer'],
            'post_id' => ['type' => 'integer'],
            'node_id' => ['type' => 'string'],
            'parent' => ['type' => ['string', 'null'], 'description' => 'New parent id; null for root rows.'],
            'parent_id' => ['type' => ['string', 'null'], 'description' => 'Alias for parent.'],
            'position' => ['type' => 'integer', 'description' => 'New sibling position. -1 appends.'],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'draft', 'both'],
                'description' => 'Defaults to both.',
            ],
        ],
        'required' => ['node_id'],
        'anyOf' => [
            ['required' => ['post']],
            ['required' => ['post_id']],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_move_node',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use get-layout immediately before moving. Valid structure is enforced, and moving a node into its own descendant is rejected. Sibling positions are compacted after the move.',
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
function beaver_builder_move_node(array $input): array|WP_Error
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

    $node_id = bb_string_arg($input['node_id'] ?? '');
    $parent = bb_nullable_string_arg($input['parent'] ?? $input['parent_id'] ?? null) ?? '';
    $position = is_int($input['position'] ?? null) ? $input['position'] : -1;
    if ($node_id === '') {
        return bb_error(code: 'beaver_builder_invalid_node_id', message: 'Pass a non-empty node_id.', data: [
            'status' => 400,
        ]);
    }

    $statuses = bb_target_statuses($input);
    $locked = bb_with_layout_lock(post_id: $post->ID, fn: fn(): array|WP_Error => beaver_builder_move_node_locked(
        post_id: $post->ID,
        node_id: $node_id,
        parent: $parent,
        position: $position,
        statuses: $statuses,
    ));
    if ($locked instanceof WP_Error) {
        return $locked;
    }

    $response = $locked;
    $response['moved_node_id'] = $node_id;
    return $response;
}

/**
 * @param list<string> $statuses
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_move_node_locked(
    int $post_id,
    string $node_id,
    string $parent,
    int $position,
    array $statuses,
): array|WP_Error {
    $prepared = [];
    $response_data = [];
    foreach ($statuses as $status) {
        $old_data = bb_layout_data_for_write(post_id: $post_id, status: $status);
        $data = bb_clone_layout_data($old_data);
        $error = bb_move_node_in_data(data: $data, move: [
            'node_id' => $node_id,
            'parent' => $parent,
            'position' => $position,
            'status' => $status,
            'preserved_modules' => bb_existing_module_ids($old_data),
        ]);
        if ($error instanceof WP_Error) {
            return $error;
        }
        $prepared[$status] = $data;
        $response_data = $data;
    }

    foreach ($prepared as $status => $data) {
        bb_save_layout_data(post_id: $post_id, data: $data, statuses: [$status]);
    }

    return bb_write_response(post_id: $post_id, data: $response_data, statuses: $statuses);
}

/**
 * @param array<string, \stdClass> $data
 * @param array{node_id: string, parent: string, position: int, status: string, preserved_modules: array<string, true>} $move
 */
function bb_move_node_in_data(array &$data, array $move): ?WP_Error
{
    $node_id = $move['node_id'];
    $parent = $move['parent'];
    $position = $move['position'];
    $status = $move['status'];
    if (!isset($data[$node_id])) {
        return bb_error(
            code: 'beaver_builder_node_not_found',
            message: sprintf('Node `%s` does not exist in the `%s` layout.', $node_id, $status),
            data: ['status' => 404],
        );
    }
    if ($parent !== '' && in_array($parent, bb_descendant_node_ids(data: $data, node_id: $node_id), strict: true)) {
        return bb_error(
            code: 'beaver_builder_invalid_parent',
            message: 'A node cannot be moved inside its own descendant.',
            data: ['status' => 400],
        );
    }

    $data[$node_id]->parent = $parent === '' ? null : $parent;
    bb_place_node_at_position(data: $data, node_id: $node_id, position: $position);
    $validation = bb_validate_layout_data(data: $data, preserved_modules: $move['preserved_modules']);
    if ($validation instanceof WP_Error) {
        return $validation;
    }
    bb_reindex_layout_positions($data);
    return null;
}
