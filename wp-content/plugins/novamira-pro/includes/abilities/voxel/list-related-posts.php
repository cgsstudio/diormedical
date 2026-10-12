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
 * Ability: read a post's relation graph rows (read-only).
 *
 * Voxel's post-relation fields persist their edges in the `{prefix}voxel_relations`
 * table (parent_id, child_id, relation_key, order). Which column holds the post
 * that owns the relation field depends on the field's relation_type: has_one and
 * has_many write the owner as parent_id and its targets as child_id, while
 * belongs_to_one and belongs_to_many write the owner as child_id and its targets
 * as parent_id (theme source 1.7.8.3, post-relation-field/update.php). The
 * relation_key is the field's key, or its custom_key when the field enables
 * use_custom_key (post-relation-field.php get_relation_key()). This surface only
 * reads the graph — creating or removing an edge goes through the post-relation
 * field value on create-post/edit-post.
 */

wp_register_ability('novamira/voxel-list-related-posts', [
    'label' => __('List Voxel Related Posts', domain: 'novamira-pro'),
    'description' => __(
        'List the relation-graph rows of a Voxel post, as persisted by its post-relation fields. Pass the post id; optionally narrow by relation_key (the post-relation field key, or the field\'s custom_key when it enables use_custom_key) and direction: "outgoing" (rows where this post is the parent), "incoming" (rows where this post is the child), or "both" (default). Which side owns the relation field depends on its relation_type: has_one/has_many store the owner as parent, so its selected posts are its outgoing rows; belongs_to_one/belongs_to_many store the owner as child, so its selected posts are its incoming rows. Each row carries the related post id, title, post type, the relation key, the direction, and the stored order. Read-only; rows are returned regardless of the related post\'s status (the surface is admin-gated).',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The subject post id.'],
            'relation_key' => [
                'type' => 'string',
                'description' => 'Only rows of this relation key (the post-relation field key, or its custom_key when the field enables use_custom_key).',
            ],
            'direction' => [
                'type' => 'string',
                'enum' => ['outgoing', 'incoming', 'both'],
                'default' => 'both',
                'description' => '"outgoing" = rows where this post is the parent; "incoming" = rows where it is the child. Which side the field owner sits on depends on the field\'s relation_type.',
            ],
            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 50, max 200).'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip.'],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'relations' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
            'pagination' => [
                'type' => 'object',
                'properties' => ['limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
                'required' => ['limit', 'offset'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['relations', 'count', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_related_posts',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only relation graph. For has_one/has_many fields the owner\'s targets are its outgoing rows; for belongs_to_one/belongs_to_many fields the owner is stored as the child, so its targets are its incoming rows. Voxel\'s standard relations table has ON DELETE CASCADE foreign keys on both post columns, so deleting a post normally removes its rows too; a row whose title and post_type read as null is unexpected — the null handling is defensive, for non-standard setups (foreign keys missing or disabled) or a deletion racing the read. To change relations, write the post-relation field value via voxel-create-post/voxel-edit-post — there is no relation write here.',
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
function voxel_list_related_posts(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $post_id = vx_coerce_int($input['post_id'] ?? null);
    if ($post_id === null || $post_id <= 0) {
        return vx_invalid(__('A positive post id is required.', domain: 'novamira-pro'));
    }
    $post = vx_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $table = vx_custom_table_or_error('voxel_relations');
    if ($table instanceof WP_Error) {
        return $table;
    }

    $direction = is_string($input['direction'] ?? null) ? $input['direction'] : 'both';
    if (!in_array($direction, ['outgoing', 'incoming', 'both'], strict: true)) {
        $direction = 'both';
    }
    $limit = vx_clamp_int($input['limit'] ?? null, min: 1, max: 200, default: 50);
    $offset = max(0, vx_clamp_int($input['offset'] ?? null, min: 0, max: PHP_INT_MAX, default: 0));

    $relations = vx_query_relations($table, [
        'post_id' => $post_id,
        'direction' => $direction,
        'relation_key' => is_string($input['relation_key'] ?? null) ? trim($input['relation_key']) : '',
        'limit' => $limit,
        'offset' => $offset,
    ]);
    if ($relations instanceof WP_Error) {
        return $relations;
    }

    return [
        'relations' => $relations,
        'count' => count($relations),
        'pagination' => ['limit' => $limit, 'offset' => $offset],
    ];
}

/**
 * Read the relation rows for a post in one or both directions, fully prepared.
 * A failed query is a WP_Error — an empty list always means "no relations",
 * never a swallowed SQL error.
 *
 * @param array{post_id: int, direction: string, relation_key: string, limit: int, offset: int} $q
 * @return list<array{post: array{id: int, title: string|null, post_type: string|null}, relation_key: string, direction: string, order: int}>|WP_Error
 */
function vx_query_relations(string $table, array $q): array|WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    $selects = [];
    $params = [];
    foreach (['outgoing', 'incoming'] as $dir) {
        if ($q['direction'] !== 'both' && $q['direction'] !== $dir) {
            continue;
        }
        [$dir_sql, $dir_params] = vx_relation_direction_select($table, $dir, $q['post_id'], $q['relation_key']);
        $selects[] = $dir_sql;
        $params = [...$params, ...$dir_params];
    }

    $sql =
        implode(' UNION ALL ', $selects)
        . ' ORDER BY relation_key ASC, direction ASC, `order` ASC, related_id ASC LIMIT %d OFFSET %d';
    $params[] = $q['limit'];
    $params[] = $q['offset'];

    // @mago-expect analyzer:possibly-invalid-argument
    $prepared = $wpdb->prepare($sql, ...$params);
    if (!is_string($prepared)) {
        return vx_operation_failed(__('Could not build the relations query.', domain: 'novamira-pro'));
    }
    /** @var mixed $rows */
    $rows = $wpdb->get_results($prepared, 'ARRAY_A');
    $db_error = vx_db_last_error();
    if ($db_error !== '' || !is_array($rows)) {
        return vx_operation_failed(sprintf(
            /* translators: %s database error summary. */
            __('The relations query failed (%s); no rows were read.', domain: 'novamira-pro'),
            $db_error !== '' ? $db_error : __('no database error reported', domain: 'novamira-pro'),
        ));
    }

    return vx_map_relation_rows($rows);
}

/**
 * Map raw relation rows onto related-post references. Rows whose related id is
 * not a positive integer are skipped, never surfaced as a fake post id 0.
 *
 * @param array<array-key, mixed> $rows
 * @return list<array{post: array{id: int, title: string|null, post_type: string|null}, relation_key: string, direction: string, order: int}>
 */
function vx_map_relation_rows(array $rows): array
{
    $out = [];
    /** @var mixed $row */
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $related_id = vx_coerce_int($row['related_id'] ?? null);
        if ($related_id === null || $related_id <= 0) {
            continue;
        }
        $out[] = [
            'post' => vx_related_post_ref($related_id),
            'relation_key' => is_string($row['relation_key'] ?? null) ? $row['relation_key'] : '',
            'direction' => ($row['direction'] ?? '') === 'incoming' ? 'incoming' : 'outgoing',
            'order' => vx_coerce_int($row['order'] ?? null) ?? 0,
        ];
    }
    return $out;
}

/**
 * One direction's SELECT branch plus its prepare() params. The direction label
 * is one of two fixed literals; the subject id is a typed int and the optional
 * relation key goes through a %s placeholder.
 *
 * @return array{0: string, 1: list<int|string>}
 */
function vx_relation_direction_select(string $table, string $dir, int $post_id, string $relation_key): array
{
    $outgoing = $dir === 'outgoing';
    $subject_col = $outgoing ? 'parent_id' : 'child_id';
    $related_col = $outgoing ? 'child_id' : 'parent_id';
    $sql =
        "SELECT '{$dir}' AS direction, {$related_col} AS related_id, relation_key, `order` "
        . "FROM {$table} WHERE {$subject_col} = %d";
    $params = [$post_id];
    if ($relation_key !== '') {
        $sql .= ' AND relation_key = %s';
        $params[] = $relation_key;
    }
    return [$sql, $params];
}
