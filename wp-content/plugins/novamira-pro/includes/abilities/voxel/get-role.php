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
 * Ability: read one Voxel role (read-only, full config).
 */

wp_register_ability('novamira/voxel-get-role', [
    'label' => __('Get Voxel Role', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel role by key. Returns its settings block and its registration block (whether public sign-up is enabled, whether plans are shown at sign-up, what happens after registration). Read-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'role' => ['type' => 'string', 'description' => 'The role key (e.g. "subscriber").'],
        ],
        'required' => ['role'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => ['string', 'null']],
            'label' => ['type' => ['string', 'null']],
            'registration_enabled' => ['type' => 'boolean'],
            'settings' => ['type' => 'object', 'additionalProperties' => true],
            'registration' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => ['key', 'label', 'registration_enabled', 'settings', 'registration'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_role',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only role configuration.',
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
function voxel_get_role(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['role'] ?? null) ? $input['role'] : '';

    /** @var mixed $roles */
    $roles = \Voxel\get('roles');
    if (!is_array($roles) || !isset($roles[$key]) || !is_array($roles[$key])) {
        return vx_not_found('role', $key);
    }
    $role = $roles[$key];

    $row = vx_role_row($role);
    $row['settings'] = isset($role['settings']) && is_array($role['settings']) ? $role['settings'] : [];
    $row['registration'] = isset($role['registration']) && is_array($role['registration']) ? $role['registration'] : [];

    return $row;
}
