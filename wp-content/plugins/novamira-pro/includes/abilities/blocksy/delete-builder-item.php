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
 * Ability: remove a placed builder item.
 */

wp_register_ability('novamira/blocksy-delete-builder-item', [
    'label' => __('Delete Blocksy Builder Item', domain: 'novamira-pro'),
    'description' => __(
        'Removes a placed item from the Blocksy header or footer builder by instance id — like dragging it out in the customizer. Header items can be removed from one device tree only (`device`) or from both (default). Blocksy stores per-item settings inside the same theme_mod (each section\'s items list); when the last placement of an instance in the section is removed, its settings entry is cleaned up too — re-adding the item later starts from option defaults. Row containers (top-row/middle-row/bottom-row/offcanvas) always exist structurally and cannot be deleted; hide a row by emptying it. Deleting an id that is not placed returns bly_not_found. The write is atomic and runs Blocksy\'s dynamic-CSS refresh sequence.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'builder' => [
                'type' => 'string',
                'enum' => ['header', 'footer'],
                'description' => 'Which builder the item lives in.',
            ],
            'item' => [
                'type' => 'string',
                'description' => 'Item instance id to remove (e.g. text, button~a1b2…) as shown by the get abilities.',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'Section id (default: the section that currently renders).',
            ],
            'device' => [
                'type' => 'string',
                'enum' => ['desktop', 'mobile'],
                'description' => 'Header only: remove from this device tree only. Omit to remove from both (footer has a single tree).',
            ],
        ],
        'required' => ['builder', 'item'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'builder' => ['type' => 'string'],
            'section' => ['type' => 'string'],
            'item' => ['type' => 'string'],
            'removed_placements' => [
                'type' => 'integer',
                'description' => 'How many placement occurrences were removed.',
            ],
            'remaining_locations' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Locations the instance still occupies in the section (non-empty only for a device-scoped header removal).',
            ],
            'settings_removed' => [
                'type' => 'boolean',
                'description' => 'True when the instance\'s stored settings entry was cleaned up (no placements remained).',
            ],
        ],
        'required' => ['builder', 'section', 'item', 'removed_placements', 'remaining_locations', 'settings_removed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_delete_builder_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removing the last placement also removes the item\'s stored settings — re-adding starts from defaults. To keep the settings while hiding the item on one device, pass `device` so the other tree keeps it. A second delete of the same id returns bly_not_found.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{builder?: string, item?: string, section?: string, device?: string} $input
 * @return array<string, mixed>|WP_Error
 */
// Validation-dense write callback — the branch count is the checks.
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:halstead
function blocksy_delete_builder_item(array $input): array|WP_Error
{
    // Pin the storage identity as the VERY FIRST statements (see
    // blocksy_edit_option for the full rationale).
    $stylesheet = get_stylesheet();
    $template = get_template();
    $mods_key = 'theme_mods_' . $stylesheet;

    if (!blocksy_theme_available($template)) {
        return bly_theme_inactive_error();
    }

    $builder = bly_builder_input($input['builder'] ?? null);
    if ($builder instanceof WP_Error) {
        return $builder;
    }
    $instance = isset($input['item']) ? trim($input['item']) : '';
    if ($instance === '') {
        return bly_error(
            'bly_invalid_input',
            message: '`item` is required — an instance id from blocksy-get-header / blocksy-get-footer.',
        );
    }
    $section_id = isset($input['section']) && trim($input['section']) !== '' ? trim($input['section']) : null;

    $device = $input['device'] ?? null;
    if ($device !== null && $device !== 'desktop' && $device !== 'mobile') {
        return bly_error('bly_invalid_input', message: '`device` must be "desktop" or "mobile" when given.');
    }
    if ($device !== null && $builder === 'footer') {
        return bly_error(
            'bly_invalid_input',
            message: 'The footer has a single tree — `device` only applies to the header.',
        );
    }

    if (in_array(bly_original_item_id($instance), bly_builder_primary_ids($builder), strict: true)) {
        return bly_error('bly_invalid_input', sprintf(
            '"%s" is a row container — rows always exist in the builder structure and cannot be deleted. Empty the row (delete the items inside it) to hide it.',
            $instance,
        ));
    }

    // Gate 2 must be resolvable — fail fast before taking the lock.
    $index = bly_option_index_or_error();
    if ($index instanceof WP_Error) {
        return $index;
    }

    $result = [
        'section' => '',
        'removed' => 0,
        'remaining' => [],
        'settings_removed' => false,
    ];

    /** @var mixed $written */
    $written = bly_locked_mod_write(
        bly_builder_mod_id($builder),
        function (array $mods) use ($builder, $section_id, $instance, $device, &$result): mixed {
            $prepared = bly_builder_prepare_write_value($builder, $mods, $section_id);
            if ($prepared instanceof WP_Error) {
                return $prepared;
            }
            ['value' => $value, 'index' => $index, 'section' => $section] = $prepared;
            /** @var mixed $section_id_raw */
            $section_id_raw = $section['id'] ?? '';
            $section_label = is_string($section_id_raw) ? $section_id_raw : '';

            $removed = $builder === 'header'
                ? bly_header_remove($section, $instance, $device)
                : bly_footer_remove($section, $instance);
            if ($removed === 0) {
                return bly_error('bly_not_found', sprintf(
                    'Item "%s" is not placed in %s section "%s"%s — nothing to delete. Read blocksy-get-%s for the placed instance ids.',
                    $instance,
                    $builder,
                    $section_label,
                    $device !== null ? ' (' . $device . ' tree)' : '',
                    $builder,
                ));
            }

            $remaining = bly_builder_location_strings($builder, $section, $instance);
            if ($remaining === []) {
                $result['settings_removed'] = bly_builder_drop_item_values($section, $instance);
            }

            $result['removed'] = $removed;
            $result['remaining'] = $remaining;
            $result['section'] = $section_label;

            $value = bly_builder_replace_section($value, $index, $section);
            return bly_builder_gate2($builder, $value);
        },
        $stylesheet,
        $mods_key,
    );
    if ($written instanceof WP_Error) {
        return $written;
    }

    return [
        'builder' => $builder,
        'section' => $result['section'],
        'item' => $instance,
        'removed_placements' => $result['removed'],
        'remaining_locations' => $result['remaining'],
        'settings_removed' => $result['settings_removed'],
    ];
}
