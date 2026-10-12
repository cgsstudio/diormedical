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
 * Ability: read the follow relation from one party to another (read-only).
 */

wp_register_ability('novamira/voxel-get-follow-status', [
    'label' => __('Get Voxel Follow Status', domain: 'novamira-pro'),
    'description' => __(
        'Read the follow relation from one party to another: does from (a user or post) follow to (a user or post)? Returns the status: accepted, requested, blocked, or none. Read-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'from_type' => ['type' => 'string', 'enum' => ['user', 'post'], 'description' => 'The follower type.'],
            'from_id' => ['type' => 'integer', 'description' => 'The follower id.'],
            'to_type' => ['type' => 'string', 'enum' => ['user', 'post'], 'description' => 'The followed type.'],
            'to_id' => ['type' => 'integer', 'description' => 'The followed id.'],
        ],
        'required' => ['from_type', 'from_id', 'to_type', 'to_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'from' => ['type' => 'object', 'additionalProperties' => true],
            'to' => ['type' => 'object', 'additionalProperties' => true],
            'status' => ['type' => 'string', 'enum' => ['accepted', 'requested', 'blocked', 'none']],
        ],
        'required' => ['from', 'to', 'status'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_follow_status',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only relation lookup.',
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
function voxel_get_follow_status(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $from_type = vx_normalize_party_type($input['from_type'] ?? null);
    $to_type = vx_normalize_party_type($input['to_type'] ?? null);
    $from_id = vx_coerce_int($input['from_id'] ?? null);
    $to_id = vx_coerce_int($input['to_id'] ?? null);
    if ($from_type === '' || $to_type === '' || $from_id === null || $from_id <= 0 || $to_id === null || $to_id <= 0) {
        return vx_invalid(__('Valid from/to type (user/post) and ids are required.', domain: 'novamira-pro'));
    }

    $subject = $from_type === 'post' ? \Voxel\Post::get($from_id) : \Voxel\User::get($from_id);
    if ($subject === null) {
        return vx_not_found($from_type, (string) $from_id);
    }

    $status = vx_coerce_int($subject->get_follow_status($to_type, $to_id));

    return [
        'from' => vx_party_ref($from_type, $from_id),
        'to' => vx_party_ref($to_type, $to_id),
        'status' => vx_follow_status_label($status),
    ];
}
