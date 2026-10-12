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

wp_register_ability('novamira/metabox-get-relationship', [
    'label' => __('Get Meta Box Relationship', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full settings for a single MB Relationships definition identified by id. The payload includes from/to configuration (object_type, post_types, empty_message, meta_box settings), source (always php — relationships are PHP-registered). Pass include_objects=true to get the first page of connected object pairs (delegated to metabox-list-connected-objects internally).',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'The relationship id.',
            ],
            'include_objects' => [
                'type' => 'boolean',
                'description' => 'When true, fetches the first 25 connected object pairs.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'source' => ['type' => 'string', 'const' => 'php'],
            'settings' => ['type' => 'object'],
            'objects' => ['type' => 'array', 'description' => 'Present when include_objects=true.'],
        ],
        'required' => ['id', 'source', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_get_relationship',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetch the full settings for one MB Relationships definition. All relationships are source=php (PHP-registered via MB_Relationships_API::register()). Pass include_objects=true to get a sample of connected pairs. Use metabox-list-connected-objects for paginated queries.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id: string, include_objects?: bool} $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_get_relationship(array $args): array|WP_Error
{
    $guard = mb_relationships_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $args['id'];
    if ($id === '') {
        return new WP_Error('metabox_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $settings = MB_Relationships_API::get_relationship_settings($id);
    if (!is_array($settings) || $settings === []) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: relationship id. */
            __('Relationship "%s" not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    $result = [
        'id' => $id,
        'source' => 'php',
        'settings' => $settings,
    ];

    if ($args['include_objects'] ?? false) {
        $result['objects'] = MB_Relationships_API::get_connected(['id' => $id, 'posts_per_page' => 25]);
    }

    return $result;
}
