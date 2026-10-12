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

wp_register_ability('novamira/metabox-list-connected-objects', [
    'label' => __('List Meta Box Connected Objects', domain: 'novamira-pro'),
    'description' => __(
        'Returns a paginated list of objects connected via an MB Relationships type. Pass relationship_id plus one of from_id or to_id to query from/to connections; or pass both to check if a specific pair is connected. Results include object IDs (and post titles for posts). Pagination: limit (default 25, max 200) and offset.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'relationship_id' => ['type' => 'string', 'description' => 'The MB Relationships type id.'],
            'from_id' => [
                'type' => ['integer', 'string'],
                'description' => 'When provided, returns objects connected FROM this id (i.e. the "to" objects for this "from").',
            ],
            'to_id' => [
                'type' => ['integer', 'string'],
                'description' => 'When provided, returns objects connected TO this id (i.e. the "from" objects for this "to").',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of results. Default 25, max 200.',
                'default' => 25,
                'maximum' => 200,
            ],
            'offset' => [
                'type' => 'integer',
                'description' => 'Offset for pagination. Default 0.',
                'default' => 0,
            ],
        ],
        'required' => ['relationship_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'relationship_id' => ['type' => 'string'],
            'objects' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'ID' => ['type' => 'integer'],
                        'post_title' => ['type' => 'string'],
                        'post_type' => ['type' => 'string'],
                    ],
                ],
            ],
            'total_in_page' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'has_more' => ['type' => 'boolean'],
        ],
        'required' => ['relationship_id', 'objects', 'total_in_page', 'limit', 'offset'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_list_connected_objects',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists objects connected via an MB Relationships type. Pass from_id to find objects connected from a specific source. Pass to_id to find objects connected to a specific destination. Pass both to test a specific connection. Pagination via limit (default 25) and offset.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     relationship_id: string,
 *     from_id?: int|string,
 *     to_id?: int|string,
 *     limit?: int,
 *     offset?: int,
 * } $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_list_connected_objects(array $args): array|WP_Error
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

    $limit = max(1, min(200, (int) ($args['limit'] ?? 25)));
    $offset = max(0, (int) ($args['offset'] ?? 0));

    $from_id = $args['from_id'] ?? null;
    $to_id = $args['to_id'] ?? null;

    $raw = mb_fetch_connected_raw($relationship_id, $from_id, $to_id, $limit, $offset);

    $has_more = count($raw) > $limit;
    $page = array_slice($raw, offset: 0, length: $limit);

    $objects = [];
    /** @var mixed $item */
    foreach ($page as $item) {
        $objects[] = mb_connected_item_to_array($item);
    }
    $objects = array_values(array_filter($objects));

    return [
        'relationship_id' => $relationship_id,
        'objects' => $objects,
        'total_in_page' => count($objects),
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => $has_more,
    ];
}

/**
 * Fetch raw connected objects for a relationship query.
 *
 * @param int|string|null $from_id
 * @param int|string|null $to_id
 * @return list<mixed>
 */
function mb_fetch_connected_raw(
    string $relationship_id,
    int|string|null $from_id,
    int|string|null $to_id,
    int $limit,
    int $offset,
): array {
    $query_args = [
        'id' => $relationship_id,
        'posts_per_page' => $limit + 1,
        'offset' => $offset,
    ];

    if ($from_id !== null && $to_id !== null) {
        $query_args['from'] = $from_id;
        $raw = MB_Relationships_API::get_connected($query_args);
        return array_values(array_filter($raw, static function (mixed $item) use ($to_id): bool {
            if (!is_object($item)) {
                return false;
            }
            /** @var mixed $item_id */
            $item_id = property_exists($item, 'ID') ? $item->ID : null;
            return $item_id !== null && (int) $item_id === (int) $to_id;
        }));
    }

    if ($from_id !== null) {
        $query_args['from'] = $from_id;
    }

    if ($from_id === null && $to_id !== null) {
        $query_args['to'] = $to_id;
    }

    return array_values(MB_Relationships_API::get_connected($query_args));
}

/**
 * Convert a raw connected item to the output array shape.
 *
 * @return array<string, mixed>|null
 */
function mb_connected_item_to_array(mixed $item): ?array
{
    if (is_object($item) && property_exists($item, 'ID')) {
        $entry = ['ID' => (int) $item->ID];
        if (property_exists($item, 'post_title')) {
            $entry['post_title'] = (string) $item->post_title;
        }
        if (property_exists($item, 'post_type')) {
            $entry['post_type'] = (string) $item->post_type;
        }
        return $entry;
    }
    if (is_int($item) || is_string($item)) {
        return ['ID' => (int) $item];
    }
    return null;
}
