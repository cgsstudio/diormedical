<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use MB_Relationships_API;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-connect-objects', [
    'label' => __('Connect Meta Box Objects', domain: 'novamira-pro'),
    'description' => __(
        'Creates a connection between two objects via an MB Relationships type. Pass relationship_id, from_id (the origin object ID), and to_id (the destination object ID). The connection is persistent (written to the MB relationships table). Calling this on an already-connected pair is a no-op (idempotent).',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'relationship_id' => [
                'type' => 'string',
                'description' => 'The MB Relationships type id.',
            ],
            'from_id' => [
                'type' => ['integer', 'string'],
                'description' => 'ID of the "from" object.',
            ],
            'to_id' => [
                'type' => ['integer', 'string'],
                'description' => 'ID of the "to" object.',
            ],
        ],
        'required' => ['relationship_id', 'from_id', 'to_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'connected' => ['type' => 'boolean'],
            'relationship_id' => ['type' => 'string'],
            'from_id' => ['type' => ['integer', 'string']],
            'to_id' => ['type' => ['integer', 'string']],
        ],
        'required' => ['connected', 'relationship_id', 'from_id', 'to_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_connect_objects',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a persistent MB Relationships connection. Pass the relationship type id plus the from and to object IDs. Idempotent: connecting an already-connected pair succeeds without duplicating. Verify the relationship type exists with metabox-list-relationships before connecting.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{relationship_id: string, from_id: int|string, to_id: int|string} $args
 * @return array{connected: bool, relationship_id: string, from_id: int|string, to_id: int|string}|WP_Error
 */
function metabox_connect_objects(array $args): array|WP_Error
{
    $guard = mb_relationships_guard();
    if ($guard !== null) {
        return $guard;
    }

    $relationship_id = $args['relationship_id'];
    if ($relationship_id === '') {
        return new WP_Error('metabox_invalid_input', __('relationship_id is required.', domain: 'novamira-pro'));
    }

    $settings = MB_Relationships_API::get_relationship_settings($relationship_id);
    if (!is_array($settings) || $settings === []) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: relationship id. */
            __('Relationship "%s" not found.', domain: 'novamira-pro'),
            $relationship_id,
        ));
    }

    $from_id = $args['from_id'];
    $to_id = $args['to_id'];

    $result = MB_Relationships_API::add($from_id, $to_id, $relationship_id);

    return [
        'connected' => $result,
        'relationship_id' => $relationship_id,
        'from_id' => $from_id,
        'to_id' => $to_id,
    ];
}
