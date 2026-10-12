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

wp_register_ability('novamira/metabox-list-relationships', [
    'label' => __('List Meta Box Relationships', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight overview of all registered MB Relationships definitions: id, from (object type and post types), to (object type and post types), source (php — all relationships are PHP-registered via MB_Relationships_API::register()). Use id with metabox-get-relationship for the full settings payload.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'relationships' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'from_type' => ['type' => 'string'],
                        'to_type' => ['type' => 'string'],
                        'source' => ['type' => 'string', 'const' => 'php'],
                    ],
                    'required' => ['id', 'from_type', 'to_type', 'source'],
                ],
            ],
        ],
        'required' => ['relationships'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_list_relationships',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists all MB Relationships definitions. All relationships are PHP-registered (source=php) — they are defined via MB_Relationships_API::register() in PHP code. Use id with metabox-get-relationship for the full settings, or with metabox-list-connected-objects to enumerate actual connections.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{relationships: list<array<string, mixed>>}|WP_Error
 */
function metabox_list_relationships(): array|WP_Error
{
    $guard = mb_relationships_guard();
    if ($guard !== null) {
        return $guard;
    }

    $all_settings = MB_Relationships_API::get_all_relationships_settings();
    $out = [];

    foreach ($all_settings as $id => $settings) {
        /** @var mixed $settings */
        if (!is_array($settings)) {
            continue;
        }
        /** @var array<string, mixed> $settings */
        $out[] = mb_relationship_settings_to_overview($id, $settings);
    }

    return ['relationships' => $out];
}

/**
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function mb_relationship_settings_to_overview(string $id, array $settings): array
{
    $from = mb_extract_relationship_side($settings, side: 'from');
    $to = mb_extract_relationship_side($settings, side: 'to');

    return [
        'id' => $id,
        'from_type' => $from,
        'to_type' => $to,
        'source' => 'php',
    ];
}

/**
 * Extract object_type from the 'from' or 'to' side of a relationship settings array.
 */
function mb_extract_relationship_side(mixed $settings, string $side): string
{
    if (!is_array($settings)) {
        return 'post';
    }

    /** @var mixed $side_data */
    $side_data = $settings[$side] ?? null;
    if (is_string($side_data)) {
        return $side_data;
    }
    if (is_array($side_data)) {
        /** @var mixed $ot */
        $ot = $side_data['object_type'] ?? null;
        if (is_string($ot) && $ot !== '') {
            return $ot;
        }
        /** @var mixed $pt */
        $pt = $side_data['post_types'] ?? null;
        if (is_array($pt) && count($pt) > 0) {
            $pt_strings = array_values(array_filter($pt, callback: 'is_string'));
            return 'post:' . implode(',', $pt_strings);
        }
    }

    return 'post';
}
