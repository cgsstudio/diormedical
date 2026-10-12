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
 * Ability: read one Voxel timeline status (read-only, approved only).
 */

wp_register_ability('novamira/voxel-get-status', [
    'label' => __('Get Voxel Status', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel timeline status by id: author, post, feed, content, review score, like and reply counts, date. Read-only, and only an APPROVED status is returned — a pending/unmoderated status reads as not found.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'status_id' => ['type' => 'integer', 'description' => 'The status id.'],
            'max_field_bytes' => ['type' => 'integer', 'description' => 'Content cap (default 4096, max 65536).'],
        ],
        'required' => ['status_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'author_id' => ['type' => ['integer', 'null']],
            'post_id' => ['type' => ['integer', 'null']],
            'feed' => ['type' => ['string', 'null']],
            'content' => ['type' => 'object', 'additionalProperties' => true],
            'review_score' => ['type' => ['number', 'null']],
            'like_count' => ['type' => 'integer'],
            'reply_count' => ['type' => 'integer'],
            'moderation' => ['type' => 'integer'],
            'created_at' => ['type' => ['string', 'null']],
        ],
        'required' => [
            'id',
            'author_id',
            'post_id',
            'feed',
            'content',
            'review_score',
            'like_count',
            'reply_count',
            'moderation',
            'created_at',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_status',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Approved status only; pending reads as not found.',
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
function voxel_get_status(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $status_id = vx_coerce_int($input['status_id'] ?? null);
    if ($status_id === null || $status_id <= 0) {
        return vx_invalid(__('A valid status id is required.', domain: 'novamira-pro'));
    }
    $max_bytes = vx_resolve_max_field_bytes(vx_coerce_int($input['max_field_bytes'] ?? null));

    // Fetch approved-only so a pending status is never disclosed by id.
    $result = \Voxel\Timeline\Status::query([
        'id' => $status_id,
        'moderation' => 1,
        'moderation_strict' => true,
        'limit' => 1,
    ]);
    $status = $result['items'][0] ?? null;
    if ($status === null) {
        return vx_not_found('status', (string) $status_id);
    }

    return vx_status_row($status, $max_bytes);
}
