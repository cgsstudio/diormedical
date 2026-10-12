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
 * Ability: list the Voxel site-template areas and their assignments (read-only).
 */

wp_register_ability('novamira/voxel-list-templates', [
    'label' => __('List Voxel Templates', domain: 'novamira-pro'),
    'description' => __(
        'List the Voxel site-template areas (header, footer, timeline, inbox, orders, checkout, auth, 404, ...) as rows: area key, category, label, expected type (page or template), the currently assigned template id, and whether an id is assigned. Also returns the grouped custom templates. Read-only. Use novamira/voxel-apply-template to assign an existing template to an area.',
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
            'areas' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'custom' => ['type' => 'object', 'additionalProperties' => true],
            'count' => ['type' => 'integer'],
        ],
        'required' => ['areas', 'custom', 'count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only list of template areas + assignments.',
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
function voxel_list_templates(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $areas = [];
    foreach (\Voxel\get_base_templates() as $template) {
        $key = is_string($template['key'] ?? null) ? $template['key'] : '';
        $area = str_starts_with($key, 'templates.') ? substr($key, offset: 10) : $key;
        $current = vx_coerce_int($template['id'] ?? null);
        $areas[] = [
            'area' => $area,
            'category' => vx_str_or_null($template['category'] ?? null),
            'label' => vx_str_or_null($template['label'] ?? null),
            'type' => vx_str_or_null($template['type'] ?? null),
            'template_id' => $current,
            'assigned' => $current !== null && $current > 0,
        ];
    }

    /** @var mixed $custom */
    $custom = \Voxel\get_custom_templates();

    return ['areas' => $areas, 'custom' => is_array($custom) ? $custom : [], 'count' => count($areas)];
}
