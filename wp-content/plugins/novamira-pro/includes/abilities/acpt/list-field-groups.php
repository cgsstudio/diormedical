<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-list-field-groups', [
    'label' => __('List ACPT Field Groups', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight list of ACPT meta groups (field groups). Each entry contains id, name, label, context, priority, and box_count. Optionally filter by belongs_to (customPostType|taxonomy|optionPage|user|comment) and find (the slug/name of the CPT, taxonomy, or option page). Use novamira/acpt-get-field-group-schema to fetch the full schema with fields for a single group.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'belongs_to' => [
                'type' => 'string',
                'enum' => ['customPostType', 'taxonomy', 'optionPage', 'user', 'comment'],
                'description' => 'Filter by entity type. Use the ACPT MetaTypes values: customPostType, taxonomy, optionPage, user, comment.',
            ],
            'find' => [
                'type' => 'string',
                'description' => 'Filter by entity slug/name (e.g. the CPT slug, taxonomy slug, or option page menu_slug). Required when belongs_to is customPostType, taxonomy, or optionPage.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field_groups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'label' => ['type' => ['string', 'null']],
                        'context' => ['type' => ['string', 'null']],
                        'priority' => ['type' => ['string', 'null']],
                        'box_count' => ['type' => 'integer'],
                    ],
                    'additionalProperties' => true,
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['field_groups', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_list_field_groups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns ACPT meta groups. Without filters returns all groups. Pass belongs_to + find to narrow to groups attached to a specific CPT / taxonomy / option page. Each group\'s box_count tells you how many meta boxes it contains. For field-level details call novamira/acpt-get-field-group-schema.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{belongs_to?: string, find?: string} $args
 * @return array{field_groups: list<array<string, mixed>>, total: int}|WP_Error
 */
function acpt_list_field_groups(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $belongs_to = $args['belongs_to'] ?? null;
    $find = $args['find'] ?? null;

    $query = [];
    if ($belongs_to !== null) {
        $query['belongsTo'] = $belongs_to;
    }
    if ($find !== null) {
        $query['find'] = $find;
    }

    $rows = \ACPT\Core\Repository\MetaRepository::get($query);
    $field_groups = [];
    foreach ($rows as $group) {
        $field_groups[] = [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'label' => $group->getLabel(),
            'context' => $group->getContext(),
            'priority' => $group->getPriority(),
            'box_count' => count($group->getBoxes()),
        ];
    }

    return ['field_groups' => $field_groups, 'total' => count($field_groups)];
}
