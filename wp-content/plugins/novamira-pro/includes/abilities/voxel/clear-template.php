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
 * Ability: unassign a Voxel site-template area (without deleting the template).
 */

wp_register_ability('novamira/voxel-clear-template', [
    'label' => __('Clear Voxel Template', domain: 'novamira-pro'),
    'description' => __(
        'Unassign a Voxel site-template area, so no template is bound to it (the underlying template/page is NOT deleted). Provide the area key (from novamira/voxel-list-templates). Returns the previous assignment so the change can be reverted. Note: clearing an area means that part of the site has no template and may render nothing until reassigned.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'area' => [
                'type' => 'string',
                'description' => 'The template area key, e.g. "header" (see list-templates).',
            ],
        ],
        'required' => ['area'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'area' => ['type' => 'string'],
            'cleared' => ['type' => 'boolean'],
            'previous_template_id' => ['type' => ['integer', 'null']],
        ],
        'required' => ['area', 'cleared', 'previous_template_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_clear_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Unassigns a site area (does not delete the template). Visible site change; returns the previous id to revert.',
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
function voxel_clear_template(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $area = is_string($input['area'] ?? null) ? $input['area'] : '';
    if (vx_find_base_template($area) === null) {
        return vx_not_found('template area', $area);
    }

    return vx_with_lock('templates', static function () use ($area): array {
        $previous = vx_coerce_int(\Voxel\get('templates.' . $area));
        // Voxel represents an unassigned area as 0 (e.g. privacy_policy, configure_plan).
        \Voxel\set('templates.' . $area, value: 0);
        return ['area' => $area, 'cleared' => true, 'previous_template_id' => $previous];
    });
}
