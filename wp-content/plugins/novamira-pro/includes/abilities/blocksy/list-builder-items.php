<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate the item types available to the Blocksy header or
 * footer builder.
 */

wp_register_ability('novamira/blocksy-list-builder-items', [
    'label' => __('List Blocksy Builder Items', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates the item types available to the Blocksy header or footer builder (builder=header|footer): the theme\'s own items (logo, menu, search, button, text, socials, copyright, widget areas, …) plus everything Blocksy Companion registers (free: account; Pro premium: contacts, content-block, divider, language-switcher, search-input, …). Each row reports where the item comes from (source, with pro=true for Companion Pro premium items), whether it is a row container (primary=true — rows hold items and cannot themselves be placed or deleted), whether extra instances are allowed and how many (clone + clone_limit — the customizer\'s own cap of (clone||1)+1 total instances; instances is the current count in the section), whether it is currently usable (enabled — e.g. cart needs WooCommerce), its placement rules (devices, allowed_in, excluded_from — row ids it may or may not sit in), whether it has editable options, and every instance currently placed with its locations. Items an inactive Companion/Pro would provide are not listed — they are simply not registered.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'builder' => [
                'type' => 'string',
                'enum' => ['header', 'footer'],
                'description' => 'Which builder to enumerate items for.',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'Section id to report placements against (default: the section that currently renders).',
            ],
        ],
        'required' => ['builder'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'builder' => ['type' => 'string'],
            'section' => [
                'type' => 'string',
                'description' => 'The section the placed_instances columns were computed against.',
            ],
            'count' => [
                'type' => 'integer',
                'description' => 'Total registered item types (before the output cap).',
            ],
            'items' => [
                'type' => 'array',
                'description' => 'One row per registered item type (capped — see items_omitted): {id, name, source (theme|companion|companion-pro|other), pro, primary, clone, clone_limit (max TOTAL instances per section, the customizer\'s (clone||1)+1 formula), instances (current count in the section), enabled, devices, allowed_in, excluded_from, has_options, placed_instances: [{id, locations}] (capped, marker entry when truncated)}.',
                'items' => ['type' => 'object'],
            ],
            'items_omitted' => [
                'type' => 'integer',
                'description' => 'How many registered item types were omitted beyond the output cap (0 = the list is complete).',
            ],
        ],
        'required' => ['builder', 'section', 'count', 'items', 'items_omitted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_list_builder_items',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use before add-builder-item: pick an id with enabled=true, respect devices/allowed_in/excluded_from (row ids), and check instances against clone_limit — a non-clone item already placed in the target tree cannot be added again, and clone items cap at clone_limit total instances. Location strings read device:row:placement (header) or row:colN (footer).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{builder?: string, section?: string} $input
 * @return array{builder: string, section: string, count: int, items: list<array<string, mixed>>, items_omitted: int}|WP_Error
 */
// Validation-dense agent surface over untyped configs/trees — the branch
// count is the checks themselves, not tangled logic.
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:halstead
function blocksy_list_builder_items(array $input): array|WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }

    $builder = bly_builder_input($input['builder'] ?? null);
    if ($builder instanceof WP_Error) {
        return $builder;
    }
    $section_id = isset($input['section']) && trim($input['section']) !== '' ? trim($input['section']) : null;

    $registered = bly_builder_registered_items($builder);
    if ($registered instanceof WP_Error) {
        return $registered;
    }

    $effective = bly_builder_effective_value($builder);
    if ($effective instanceof WP_Error) {
        return $effective;
    }
    $found = bly_builder_find_section($builder, $effective['value'], $section_id);
    if ($found instanceof WP_Error) {
        return $found;
    }
    $section = $found['section'];
    $section_label = isset($section['id']) && is_string($section['id']) ? $section['id'] : '';

    // Instance ids placed in the section, grouped by their item type — so
    // clone instances (button~a1b2…) report under `button`.
    $placed_by_type = [];
    foreach (bly_builder_placed_ids($builder, $section) as $instance) {
        $placed_by_type[bly_original_item_id($instance)][] = $instance;
    }

    $items = [];
    $omitted = 0;
    foreach ($registered as $row) {
        if (count($items) >= BLY_BUILDER_MAX_ITEM_TYPES) {
            $omitted++;
            continue;
        }
        $config = $row['config'];
        $source = bly_builder_item_source($row['path']);
        $clone_limit = bly_builder_clone_limit($config);
        $type_instances = bly_builder_type_instances($builder, $section, $row['id']);

        $instances = [];
        foreach ($placed_by_type[$row['id']] ?? [] as $instance) {
            if (count($instances) >= BLY_BUILDER_MAX_PLACED_INSTANCES) {
                $instances[] = [
                    'id' => sprintf(
                        '[+%d more instances omitted beyond the %d-entry cap]',
                        count($placed_by_type[$row['id']]) - BLY_BUILDER_MAX_PLACED_INSTANCES,
                        BLY_BUILDER_MAX_PLACED_INSTANCES,
                    ),
                    'locations' => [],
                ];
                break;
            }
            $instances[] = [
                'id' => $instance,
                'locations' => bly_builder_location_strings($builder, $section, $instance),
            ];
        }

        /** @var mixed $name_raw */
        $name_raw = $config['name'] ?? null;
        /** @var mixed $allowed_raw */
        $allowed_raw = $config['allowed_in'] ?? [];
        /** @var mixed $excluded_raw */
        $excluded_raw = $config['excluded_from'] ?? [];

        $items[] = [
            'id' => $row['id'],
            'name' => is_string($name_raw) ? $name_raw : $row['id'],
            'source' => $source,
            'pro' => $source === 'companion-pro',
            'primary' => $row['is_primary'],
            'clone' => $clone_limit > 1,
            'clone_limit' => $clone_limit,
            'instances' => count($type_instances),
            'enabled' => bly_builder_item_enabled($config),
            'devices' => $builder === 'header' ? bly_builder_item_devices($config) : null,
            'allowed_in' => is_array($allowed_raw) && $allowed_raw !== [] ? bly_object_or_map($allowed_raw) : null,
            'excluded_from' => is_array($excluded_raw) && $excluded_raw !== [] ? array_values($excluded_raw) : null,
            'has_options' => is_readable($row['path'] . '/options.php'),
            'placed_instances' => $instances,
        ];
    }

    return [
        'builder' => $builder,
        'section' => $section_label,
        'count' => count($registered),
        'items' => $items,
        'items_omitted' => $omitted,
    ];
}
