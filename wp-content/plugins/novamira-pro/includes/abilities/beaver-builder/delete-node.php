<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-delete-node', [
    'label' => __('Delete Beaver Builder Node', domain: 'novamira-pro'),
    'description' => __('Deletes one Beaver Builder node and its descendant subtree.', domain: 'novamira-pro'),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer'],
            'post_id' => ['type' => 'integer'],
            'node_id' => ['type' => 'string'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_delete_node',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Deletes the full subtree rooted at node_id. Re-read get-layout before another structural write because sibling positions are compacted.',
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
function beaver_builder_delete_node(array $input): array|WP_Error
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
    if ($node_id === '') {
        return bb_error(code: 'beaver_builder_invalid_node_id', message: 'Pass a non-empty node_id.', data: [
            'status' => 400,
        ]);
    }

    $statuses = bb_target_statuses($input);
    $locked = bb_with_layout_lock(post_id: $post->ID, fn: fn(): array|WP_Error => beaver_builder_delete_node_locked(
        post_id: $post->ID,
        node_id: $node_id,
        statuses: $statuses,
    ));
    if ($locked instanceof WP_Error) {
        return $locked;
    }

    return $locked;
}

/**
 * @param list<string> $statuses
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_delete_node_locked(int $post_id, string $node_id, array $statuses): array|WP_Error
{
    $prepared = [];
    $response_data = [];
    $deleted = [];
    foreach ($statuses as $status) {
        $old_data = bb_layout_data_for_write(post_id: $post_id, status: $status);
        $data = bb_clone_layout_data($old_data);
        if (!isset($data[$node_id])) {
            return bb_error(
                code: 'beaver_builder_node_not_found',
                message: sprintf('Node `%s` does not exist in the `%s` layout.', $node_id, $status),
                data: ['status' => 404],
            );
        }

        $ids = array_merge([$node_id], bb_descendant_node_ids(data: $data, node_id: $node_id));
        foreach ($ids as $delete_id) {
            unset($data[$delete_id]);
            $deleted[$delete_id] = true;
        }
        bb_reindex_layout_positions($data);
        $validation = bb_validate_layout_data(data: $data, preserved_modules: bb_existing_module_ids($old_data));
        if ($validation instanceof WP_Error) {
            return $validation;
        }
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

    $response = bb_write_response(post_id: $post_id, data: $response_data, statuses: $statuses);
    $response['deleted_node_ids'] = array_keys($deleted);
    return $response;
}
