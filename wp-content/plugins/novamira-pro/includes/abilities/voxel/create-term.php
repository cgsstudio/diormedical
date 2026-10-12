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
 * Ability: create a term in a Voxel taxonomy, with its Voxel extras.
 */

wp_register_ability('novamira/voxel-create-term', [
    'label' => __('Create Voxel Term', domain: 'novamira-pro'),
    'description' => __(
        'Create a term in a Voxel taxonomy. Provide the taxonomy and a name; optionally a slug, parent term id (for hierarchical taxonomies), description, and Voxel extras: icon, color, image attachment id, geo area, and order. Returns the created term. The taxonomy cache is refreshed automatically.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => vx_term_create_input_schema(),
    'output_schema' => vx_term_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_create_term',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'name is required. parent must be an existing term in the same taxonomy (rejected on a non-hierarchical taxonomy). A duplicate NAME under the same parent is refused with voxel_invalid_input; a duplicate slug is made unique automatically.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_create_term(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['taxonomy'] ?? null) ? $input['taxonomy'] : '';
    $taxonomy = vx_taxonomy_or_error($key);
    if ($taxonomy instanceof WP_Error) {
        return $taxonomy;
    }

    $name = is_string($input['name'] ?? null) ? $input['name'] : '';
    if ($name === '') {
        return vx_invalid(__('A term name is required.', domain: 'novamira-pro'));
    }

    $args = vx_term_core_args($input, $key);
    if ($args instanceof WP_Error) {
        return $args;
    }

    // @mago-expect analysis:possibly-invalid-argument
    $res = wp_insert_term($name, $key, $args);
    if ($res instanceof WP_Error) {
        return vx_map_term_core_error($res);
    }
    $term_id = (int) $res['term_id'];
    if ($term_id <= 0) {
        return vx_operation_failed(__('The term could not be created.', domain: 'novamira-pro'));
    }

    vx_write_term_extras($term_id, $input);
    vx_bump_taxonomy_version($key);

    return vx_term_write_result($term_id);
}
