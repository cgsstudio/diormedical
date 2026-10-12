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
 * Ability: list the followers of, or the accounts followed by, a user or post.
 */

wp_register_ability('novamira/voxel-list-followers', [
    'label' => __('List Voxel Followers', domain: 'novamira-pro'),
    'description' => __(
        'List the public follow graph for a user or post. Pass type (user or post) and id, and direction: "followers" for who follows it, or "following" for who/what it follows. Each row is a party reference (type, id, display name) plus the relation status. Read-only. Defaults to accepted (mutual/approved) relations; pass status to see requested or blocked.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string', 'enum' => ['user', 'post'], 'description' => 'The subject type.'],
            'id' => ['type' => 'integer', 'description' => 'The subject id (user id or post id).'],
            'direction' => [
                'type' => 'string',
                'enum' => ['followers', 'following'],
                'default' => 'followers',
                'description' => '"followers" = who follows the subject; "following" = who/what the subject follows.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['accepted', 'requested', 'blocked'],
                'default' => 'accepted',
                'description' => 'Relation status to include.',
            ],
            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 200).'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip.'],
        ],
        'required' => ['type', 'id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'parties' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'count' => ['type' => 'integer'],
            'pagination' => [
                'type' => 'object',
                'properties' => ['limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
                'required' => ['limit', 'offset'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['parties', 'count', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_followers',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only social graph. No follow/unfollow writes.',
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
function voxel_list_followers(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $type = vx_normalize_party_type($input['type'] ?? null);
    $id = vx_coerce_int($input['id'] ?? null);
    if ($type === '' || $id === null || $id <= 0) {
        return vx_invalid(__('A valid type (user/post) and id are required.', domain: 'novamira-pro'));
    }

    $limit = vx_clamp_int($input['limit'] ?? null, min: 1, max: 200, default: 25);
    $offset = max(0, vx_clamp_int($input['offset'] ?? null, min: 0, max: PHP_INT_MAX, default: 0));

    $parties = vx_query_followers([
        'type' => $type,
        'id' => $id,
        'direction' => ($input['direction'] ?? 'followers') === 'following' ? 'following' : 'followers',
        'status' => vx_follower_status_value(is_string($input['status'] ?? null) ? $input['status'] : 'accepted'),
        'limit' => $limit,
        'offset' => $offset,
    ]);

    return [
        'parties' => $parties,
        'count' => count($parties),
        'pagination' => ['limit' => $limit, 'offset' => $offset],
    ];
}

/** Map a follow-status label to its stored tinyint value. */
function vx_follower_status_value(string $label): int
{
    return match ($label) {
        'requested' => 0,
        'blocked' => -1,
        default => 1,
    };
}

/**
 * Read the follow graph rows for a subject in one direction. All inputs are an
 * enum ('user'/'post') or a typed int, so interpolation is injection-safe.
 *
 * @param array{type: string, id: int, direction: string, status: int, limit: int, offset: int} $q
 * @return list<array{type: string, id: int, display_name: string|null, status: string}>
 */
function vx_query_followers(array $q): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = "{$wpdb->prefix}voxel_followers";
    $following = $q['direction'] === 'following';
    $subject_type_col = $following ? 'follower_type' : 'object_type';
    $subject_id_col = $following ? 'follower_id' : 'object_id';
    $party_type_col = $following ? 'object_type' : 'follower_type';
    $party_id_col = $following ? 'object_id' : 'follower_id';

    $sql =
        "SELECT {$party_type_col} AS party_type, {$party_id_col} AS party_id, status FROM {$table} "
        . "WHERE {$subject_type_col} = '{$q['type']}' AND {$subject_id_col} = {$q['id']} AND status = {$q['status']} "
        . "LIMIT {$q['limit']} OFFSET {$q['offset']}";

    /** @var mixed $rows */
    $rows = $wpdb->get_results($sql, 'ARRAY_A');
    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    /** @var mixed $row */
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $party_type = ($row['party_type'] ?? '') === 'post' ? 'post' : 'user';
        $party_id = vx_coerce_int($row['party_id'] ?? null) ?? 0;
        $ref = vx_party_ref($party_type, $party_id);
        $ref['status'] = vx_follow_status_label(vx_coerce_int($row['status'] ?? null));
        $out[] = $ref;
    }
    return $out;
}
