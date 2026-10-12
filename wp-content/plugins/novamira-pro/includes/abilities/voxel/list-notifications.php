<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: list a user's Voxel notifications (read-only, compact rows).
 */

wp_register_ability('novamira/voxel-list-notifications', [
    'label' => __('List Voxel Notifications', domain: 'novamira-pro'),
    'description' => __(
        'List a user\'s Voxel notifications as compact rows: id, event type, seen flag, date. Requires a user_id. Read-only. Use novamira/voxel-get-notification for one notification\'s rendered subject and link.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'user_id' => ['type' => 'integer', 'description' => 'The user whose notifications to list.'],
            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 200).'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip.'],
        ],
        'required' => ['user_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'notifications' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
            'unread_in_page' => ['type' => 'integer'],
            'pagination' => [
                'type' => 'object',
                'properties' => ['limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
                'required' => ['limit', 'offset'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['notifications', 'count', 'unread_in_page', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_notifications',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Requires user_id.',
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
function voxel_list_notifications(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $user_id = vx_coerce_int($input['user_id'] ?? null);
    if ($user_id === null || $user_id <= 0) {
        return vx_invalid(__('A valid user id is required.', domain: 'novamira-pro'));
    }
    if (!get_userdata($user_id) instanceof WP_User) {
        return vx_not_found('user', (string) $user_id);
    }

    $limit = vx_clamp_int($input['limit'] ?? null, min: 1, max: 200, default: 25);
    $offset = max(0, vx_clamp_int($input['offset'] ?? null, min: 0, max: PHP_INT_MAX, default: 0));

    $notifications = \Voxel\Notification::query([
        'user_id' => $user_id,
        'limit' => $limit,
        'offset' => $offset,
        'order_by' => 'created_at',
        'order' => 'desc',
    ]);
    $rows = [];
    $unread = 0;
    foreach ($notifications as $notification) {
        $row = vx_notification_row($notification);
        if ($row['seen'] !== true) {
            $unread++;
        }
        $rows[] = $row;
    }

    return [
        'notifications' => $rows,
        'count' => count($rows),
        'unread_in_page' => $unread,
        'pagination' => ['limit' => $limit, 'offset' => $offset],
    ];
}
