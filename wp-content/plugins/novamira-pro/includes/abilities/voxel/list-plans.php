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
 * Ability: list Voxel membership plans (read-only, compact rows).
 */

wp_register_ability('novamira/voxel-list-plans', [
    'label' => __('List Voxel Plans', domain: 'novamira-pro'),
    'description' => __(
        'List the Voxel membership plans as compact rows: key, label, whether it is archived, and the roles it supports. Read-only. Pricing details (which are linked to Stripe) are omitted here; read one plan with novamira/voxel-get-plan and include "pricing" to see them.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'include_archived' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include archived plans (default false).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plans' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
        ],
        'required' => ['plans', 'count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_plans',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Archived plans hidden unless include_archived=true.',
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
function voxel_list_plans(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $include_archived = ($input['include_archived'] ?? false) === true;

    /** @var mixed $plans */
    $plans = \Voxel\get('plans');
    $rows = [];
    if (is_array($plans)) {
        /** @var mixed $plan */
        foreach ($plans as $plan) {
            if (!is_array($plan)) {
                continue;
            }
            if (!$include_archived && ($plan['archived'] ?? false) === true) {
                continue;
            }
            $rows[] = vx_plan_row($plan);
        }
    }

    return ['plans' => $rows, 'count' => count($rows)];
}
