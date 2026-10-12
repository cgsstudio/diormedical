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
 * Ability: read a user's current Voxel membership (read-only).
 */

wp_register_ability('novamira/voxel-get-user-membership', [
    'label' => __('Get Voxel User Membership', domain: 'novamira-pro'),
    'description' => __(
        'Read a user\'s current Voxel membership: the membership type (default, order or subscription), whether it is active, the active plan (key and label), and the linking order id when the plan came from an order. Read-only (it never writes and never contacts Stripe). Membership is scoped to the site\'s current payments mode (test or live), so the value reflects that mode.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'user_id' => ['type' => 'integer', 'description' => 'The WordPress user id.'],
        ],
        'required' => ['user_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'user_id' => ['type' => 'integer'],
            'type' => ['type' => 'string'],
            'is_active' => ['type' => 'boolean'],
            'plan_key' => ['type' => ['string', 'null']],
            'plan_label' => ['type' => ['string', 'null']],
            'test_mode' => ['type' => 'boolean'],
            'order_id' => ['type' => ['integer', 'null']],
        ],
        'required' => ['user_id', 'type', 'is_active', 'plan_key', 'plan_label', 'test_mode', 'order_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_user_membership',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Mode-scoped (reads voxel:plan or voxel:test_plan for the current mode).',
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
function voxel_get_user_membership(array $input): array|WP_Error
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

    $voxel_user = \Voxel\User::get($user_id);
    if ($voxel_user === null) {
        return vx_not_found('user', (string) $user_id);
    }

    return vx_membership_view($voxel_user, $user_id);
}
