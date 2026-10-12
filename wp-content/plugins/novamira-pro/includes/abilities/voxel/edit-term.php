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
 * Ability: edit a term of a Voxel taxonomy — partial / merge update.
 */

wp_register_ability('novamira/voxel-edit-term', [
    'label' => __('Edit Voxel Term', domain: 'novamira-pro'),
    'description' => __(
        'Update a term of a Voxel taxonomy. This is a partial update: only the name, slug, parent, description and Voxel extras (icon, color, image, area, order) you provide are changed. An empty icon/color/image/area clears that extra. Returns the updated term.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => vx_term_edit_input_schema(),
    'output_schema' => vx_term_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_edit_term',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update — omit what you do not want to change. parent must be an existing term in the same taxonomy. A term cannot be its own parent.',
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
function voxel_edit_term(array $input): array|WP_Error
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

    $wp_term = get_term($term_id);
    if (!$wp_term instanceof \WP_Term) {
        return vx_not_found('term', (string) $term_id);
    }
    $taxonomy = $wp_term->taxonomy;

    $parent_in = array_key_exists('parent', $input) ? vx_coerce_int($input['parent']) : null;
    if ($parent_in !== null && $parent_in === $term_id) {
        return vx_invalid(__('A term cannot be its own parent.', domain: 'novamira-pro'));
    }

    $args = vx_term_core_args($input, $taxonomy);
    if ($args instanceof WP_Error) {
        return $args;
    }
    if (isset($input['name']) && is_string($input['name']) && $input['name'] !== '') {
        $args['name'] = $input['name'];
    }

    if ($args !== []) {
        // @mago-expect analysis:possibly-invalid-argument
        $res = wp_update_term($term_id, $taxonomy, $args);
        if ($res instanceof WP_Error) {
            return vx_map_term_core_error($res);
        }
    }

    vx_write_term_extras($term_id, $input);
    vx_bump_taxonomy_version($taxonomy);

    return vx_term_write_result($term_id);
}
