<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: delete a term from a Voxel taxonomy.
 */

wp_register_ability('novamira/voxel-delete-term', [
    'label' => __('Delete Voxel Term', domain: 'novamira-pro'),
    'description' => __(
        'Permanently delete a term from a Voxel taxonomy. Its term meta and the links from posts to it are removed by WordPress; any child terms are re-parented to the deleted term\'s parent. The taxonomy cache is refreshed. Only terms of a Voxel-managed taxonomy can be deleted here.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The term id to delete.'],
        ],
        'required' => ['term_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
        'required' => ['id', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_delete_term',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent — a term cannot be recovered. Child terms are re-parented, not deleted. Rejects terms of non-Voxel taxonomies.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{id: int, deleted: bool}|WP_Error
 */
function voxel_delete_term(array $input): array|WP_Error
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
    if (!$wp_term instanceof WP_Term) {
        return vx_not_found('term', (string) $term_id);
    }
    $taxonomy = $wp_term->taxonomy;

    $res = wp_delete_term($term_id, $taxonomy);
    if ($res instanceof WP_Error) {
        return vx_operation_failed($res->get_error_message());
    }
    vx_bump_taxonomy_version($taxonomy);

    return ['id' => $term_id, 'deleted' => $res === true];
}
