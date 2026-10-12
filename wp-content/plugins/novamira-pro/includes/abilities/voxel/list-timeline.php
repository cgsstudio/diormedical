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
 * Ability: list approved Voxel timeline statuses (read-only).
 */

wp_register_ability('novamira/voxel-list-timeline', [
    'label' => __('List Voxel Timeline', domain: 'novamira-pro'),
    'description' => __(
        'List Voxel timeline statuses (activity posts, reviews and post-wall entries) as compact rows: id, author, post, feed, content (truncated), review score, like and reply counts, date. Filter by post_id, user_id or feed (post_reviews, post_wall, post_timeline, user_timeline). Read-only, and only APPROVED public statuses are returned (pending/unmoderated ones are never shown).',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'Only statuses attached to this post.'],
            'user_id' => ['type' => 'integer', 'description' => 'Only statuses authored by this user.'],
            'feed' => [
                'type' => 'string',
                'enum' => ['post_reviews', 'post_wall', 'post_timeline', 'user_timeline'],
                'description' => 'Only statuses in this feed.',
            ],
            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 200).'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip (for paging).'],
            'max_field_bytes' => [
                'type' => 'integer',
                'description' => 'Per-field content cap (default 4096, max 65536).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'statuses' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
            'pagination' => [
                'type' => 'object',
                'properties' => ['limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
                'required' => ['limit', 'offset'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['statuses', 'count', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_timeline',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Approved public statuses only. No moderation, no writes.',
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
function voxel_list_timeline(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $limit = vx_clamp_int($input['limit'] ?? null, min: 1, max: 200, default: 25);
    $offset = max(0, vx_clamp_int($input['offset'] ?? null, min: 0, max: PHP_INT_MAX, default: 0));
    $max_bytes = vx_resolve_max_field_bytes(vx_coerce_int($input['max_field_bytes'] ?? null));

    // Approved-only, strict: never surfaces pending/unmoderated content, and never
    // widens to a "current user" identity (which is ambiguous server-side).
    $args = [
        'moderation' => 1,
        'moderation_strict' => true,
        'limit' => $limit,
        'offset' => $offset,
        'order_by' => 'created_at',
        'order' => 'desc',
    ];
    $post_id = vx_coerce_int($input['post_id'] ?? null);
    if ($post_id !== null && $post_id > 0) {
        $args['post_id'] = $post_id;
    }
    $user_id = vx_coerce_int($input['user_id'] ?? null);
    if ($user_id !== null && $user_id > 0) {
        $args['user_id'] = $user_id;
    }
    if (isset($input['feed']) && is_string($input['feed']) && $input['feed'] !== '') {
        $args['feed'] = $input['feed'];
    }

    $result = \Voxel\Timeline\Status::query($args);
    $rows = [];
    foreach ($result['items'] as $status) {
        $rows[] = vx_status_row($status, $max_bytes);
    }

    return [
        'statuses' => $rows,
        'count' => count($rows),
        'pagination' => ['limit' => $limit, 'offset' => $offset],
    ];
}
