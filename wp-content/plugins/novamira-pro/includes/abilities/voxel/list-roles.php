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
 * Ability: list Voxel roles (read-only, compact rows).
 */

wp_register_ability('novamira/voxel-list-roles', [
    'label' => __('List Voxel Roles', domain: 'novamira-pro'),
    'description' => __(
        'List the Voxel membership roles as compact rows: key, label, and whether public registration is enabled for the role. Read-only. Use novamira/voxel-get-role for one role\'s full configuration.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'roles' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
        ],
        'required' => ['roles', 'count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_roles',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only list of the site\'s Voxel roles.',
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
function voxel_list_roles(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    /** @var mixed $roles */
    $roles = \Voxel\get('roles');
    $rows = [];
    if (is_array($roles)) {
        /** @var mixed $role */
        foreach ($roles as $role) {
            if (!is_array($role)) {
                continue;
            }
            $rows[] = vx_role_row($role);
        }
    }

    return ['roles' => $rows, 'count' => count($rows)];
}
