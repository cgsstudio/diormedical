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
 * Ability: read one Voxel notification with its rendered subject (read-only).
 */

wp_register_ability('novamira/voxel-get-notification', [
    'label' => __('Get Voxel Notification', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel notification by id: recipient, event type, seen flag, date, the rendered subject line (the same text the user sees), and the link it points to. Read-only. The subject describes the event and never contains private message contents.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'notification_id' => ['type' => 'integer', 'description' => 'The notification id.'],
            'max_field_bytes' => ['type' => 'integer', 'description' => 'Subject cap (default 4096, max 65536).'],
        ],
        'required' => ['notification_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'user_id' => ['type' => 'integer'],
            'type' => ['type' => 'string'],
            'seen' => ['type' => 'boolean'],
            'created_at' => ['type' => ['string', 'null']],
            'subject' => ['type' => ['object', 'null'], 'additionalProperties' => true],
            'links_to' => ['type' => ['string', 'null']],
        ],
        'required' => ['id', 'user_id', 'type', 'seen', 'created_at', 'subject', 'links_to'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_notification',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Renders the notification subject.',
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
function voxel_get_notification(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $notification_id = vx_coerce_int($input['notification_id'] ?? null);
    if ($notification_id === null || $notification_id <= 0) {
        return vx_invalid(__('A valid notification id is required.', domain: 'novamira-pro'));
    }
    $max_bytes = vx_resolve_max_field_bytes(vx_coerce_int($input['max_field_bytes'] ?? null));

    $notification = \Voxel\Notification::get($notification_id);
    if ($notification === null) {
        return vx_not_found('notification', (string) $notification_id);
    }

    return vx_notification_full($notification, $max_bytes);
}
