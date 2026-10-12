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
 * Ability: mark a Voxel notification seen or unseen (per-row flag flip).
 */

wp_register_ability('novamira/voxel-mark-notification', [
    'label' => __('Mark Voxel Notification', domain: 'novamira-pro'),
    'description' => __(
        'Mark one Voxel notification as seen or unseen. This flips only the seen flag on that single notification row; it changes nothing else and is fully reversible. Pass seen=true to mark it read, seen=false to mark it unread.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'notification_id' => ['type' => 'integer', 'description' => 'The notification id.'],
            'seen' => ['type' => 'boolean', 'description' => 'true = read, false = unread.'],
        ],
        'required' => ['notification_id', 'seen'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'seen' => ['type' => 'boolean'],
        ],
        'required' => ['id', 'seen'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_mark_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Flips only the seen flag on one row. Reversible.',
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
function voxel_mark_notification(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $notification_id = vx_coerce_int($input['notification_id'] ?? null);
    if ($notification_id === null || $notification_id <= 0) {
        return vx_invalid(__('A valid notification id is required.', domain: 'novamira-pro'));
    }
    // Coerce leniently: the Abilities API does not guarantee a real bool, so
    // seen=1 / "true" must mark read, not silently mark unread.
    $seen = vx_coerce_bool($input['seen'] ?? null);

    $notification = \Voxel\Notification::get($notification_id);
    if ($notification === null) {
        return vx_not_found('notification', (string) $notification_id);
    }

    $notification->update('seen', $seen ? 1 : 0);

    return ['id' => $notification_id, 'seen' => $seen];
}
