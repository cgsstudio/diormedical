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
 * Ability: list approved replies for a Voxel timeline status (read-only).
 */

wp_register_ability('novamira/voxel-list-replies', [
    'label' => __('List Voxel Replies', domain: 'novamira-pro'),
    'description' => __(
        'List the approved replies (comments) on a Voxel timeline status as compact rows: id, status, parent, author, content (truncated), like and reply counts, date. Pass a status_id, and optionally a parent_id to list replies to a specific reply. Read-only; only APPROVED replies are returned.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'status_id' => ['type' => 'integer', 'description' => 'The parent status id.'],
            'parent_id' => ['type' => 'integer', 'description' => 'Only replies to this reply (nested thread).'],
            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 200).'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip.'],
            'max_field_bytes' => ['type' => 'integer', 'description' => 'Content cap (default 4096, max 65536).'],
        ],
        'required' => ['status_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'replies' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
            'pagination' => [
                'type' => 'object',
                'properties' => ['limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
                'required' => ['limit', 'offset'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['replies', 'count', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_replies',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Approved replies only.',
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
function voxel_list_replies(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $status_id = vx_coerce_int($input['status_id'] ?? null);
    if ($status_id === null || $status_id <= 0) {
        return vx_invalid(__('A valid status id is required.', domain: 'novamira-pro'));
    }

    $limit = vx_clamp_int($input['limit'] ?? null, min: 1, max: 200, default: 25);
    $offset = max(0, vx_clamp_int($input['offset'] ?? null, min: 0, max: PHP_INT_MAX, default: 0));
    $max_bytes = vx_resolve_max_field_bytes(vx_coerce_int($input['max_field_bytes'] ?? null));

    $args = [
        'status_id' => $status_id,
        'moderation' => 1,
        'moderation_strict' => true,
        'limit' => $limit,
        'offset' => $offset,
        'order_by' => 'created_at',
        'order' => 'desc',
    ];
    $parent_id = vx_coerce_int($input['parent_id'] ?? null);
    if ($parent_id !== null && $parent_id > 0) {
        $args['parent_id'] = $parent_id;
    }

    $result = \Voxel\Timeline\Reply::query($args);
    $rows = [];
    foreach ($result['items'] as $reply) {
        $rows[] = vx_reply_row($reply, $max_bytes);
    }

    return [
        'replies' => $rows,
        'count' => count($rows),
        'pagination' => ['limit' => $limit, 'offset' => $offset],
    ];
}
