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
 * Ability: read one Voxel term with its Voxel metas.
 */

wp_register_ability('novamira/voxel-get-term', [
    'label' => __('Get Voxel Term', domain: 'novamira-pro'),
    'description' => __(
        'Read one term of a Voxel taxonomy in full: id, taxonomy, name, slug, description, parent, ancestor ids, order, and its Voxel extras (icon, color, image attachment id, geo area) plus the cached per-post-type publish counts.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'The term id (must belong to a Voxel taxonomy).',
            ],
        ],
        'required' => ['term_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => vx_term_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_term',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Full term read. For the term list use novamira/voxel-list-terms.',
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
function voxel_get_term(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $term_id = vx_coerce_int($input['term_id'] ?? null) ?? 0;
    $term = vx_term_or_error($term_id);
    if ($term instanceof WP_Error) {
        return $term;
    }

    return vx_term_full($term);
}
