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

wp_register_ability('novamira/metabox-disconnect-objects', [
    'label' => __('Disconnect Meta Box Objects', domain: 'novamira-pro'),
    'description' => __(
        'Removes a connection between two objects in an MB Relationships type. Pass relationship_id, from_id, and to_id. Idempotent: disconnecting a pair that is not currently connected returns disconnected=false without error.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'relationship_id' => ['type' => 'string', 'description' => 'The MB Relationships type id.'],
            'from_id' => ['type' => ['integer', 'string'], 'description' => 'ID of the "from" object.'],
            'to_id' => ['type' => ['integer', 'string'], 'description' => 'ID of the "to" object.'],
        ],
        'required' => ['relationship_id', 'from_id', 'to_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'disconnected' => ['type' => 'boolean'],
            'relationship_id' => ['type' => 'string'],
            'from_id' => ['type' => ['integer', 'string']],
            'to_id' => ['type' => ['integer', 'string']],
        ],
        'required' => ['disconnected', 'relationship_id', 'from_id', 'to_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_disconnect_objects',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes a persistent MB Relationships connection. Idempotent: if the pair is not connected, returns disconnected=false without error.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{relationship_id: string, from_id: int|string, to_id: int|string} $args
 * @return array{disconnected: bool, relationship_id: string, from_id: int|string, to_id: int|string}|WP_Error
 */
function metabox_disconnect_objects(array $args): array|WP_Error
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

    $result = MB_Relationships_API::delete($from_id, $to_id, $relationship_id);

    return [
        'disconnected' => $result,
        'relationship_id' => $relationship_id,
        'from_id' => $from_id,
        'to_id' => $to_id,
    ];
}
