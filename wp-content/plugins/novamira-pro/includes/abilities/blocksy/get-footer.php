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
 * Ability: structural snapshot of the Blocksy footer builder (+ per-item
 * settings drill-down). Kept as a separate ability from blocksy-get-header
 * because the storage models genuinely differ: the header stores per-device
 * placement trees, the footer stores per-row column lists.
 */

wp_register_ability('novamira/blocksy-get-footer', [
    'label' => __('Get Blocksy Footer', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Blocksy footer builder state (the footer_placements theme_mod, or the theme default when nothing is stored — Blocksy never persists defaults; the default ships two sections, type-1 and type-2). Without `item`: every section\'s structure as row (top-row/middle-row/bottom-row) → columns (1-based lists of placed item instance ids), plus which section currently renders (rendered_section) and which the customizer UI last edited (current_section). Per-item settings are NOT expanded here; pass `item` (an instance id from the structure, or a row id like bottom-row) to get that item\'s stored settings, its placements, and a summary of its available options (id/type/label/default/choices) for edit-builder-item.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'section' => [
                'type' => 'string',
                'description' => 'Section id (the default value ships type-1 and type-2). Omit to target the section that currently renders.',
            ],
            'item' => [
                'type' => 'string',
                'description' => 'Item instance id (e.g. copyright, menu) or row id (e.g. bottom-row) to drill into: returns its settings + option schema summary instead of the full structure.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'builder' => ['type' => 'string'],
            'stored' => [
                'type' => 'boolean',
                'description' => 'False when the site runs on the theme default (no usable footer_placements theme_mod is stored).',
            ],
            'stored_invalid' => [
                'type' => 'boolean',
                'description' => 'True when a footer_placements mod exists but is structurally unusable; the reported state is then the theme default and writes will refuse until it is repaired.',
            ],
            'current_section' => [
                'type' => 'string',
                'description' => 'The section the customizer UI last edited (bookkeeping only).',
            ],
            'rendered_section' => [
                'type' => 'string',
                'description' => 'The section that actually renders (via the blocksy:footer:current_section_id filter).',
            ],
            'sections' => [
                'type' => ['array', 'null'],
                'description' => 'Structure mode only (null in item mode): one entry per section with id, name, structure row → column lists of instance ids, items_with_settings, and section settings_keys. Oversized fields and lists are capped with explicit markers; see sections_omitted for section-level truncation.',
                'items' => ['type' => 'object'],
            ],
            'sections_omitted' => [
                'type' => 'integer',
                'description' => 'How many sections were omitted beyond the output cap (0 = every section is listed). Target an omitted section explicitly via the `section` input.',
            ],
            'item' => [
                'type' => ['object', 'null'],
                'description' => 'Item mode only: {id, item_type, registered, name, section, placed (location strings), has_settings, values, options, options_truncated}.',
            ],
        ],
        'required' => [
            'builder',
            'stored',
            'stored_invalid',
            'current_section',
            'rendered_section',
            'sections',
            'sections_omitted',
            'item',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_get_footer',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Start here before any footer builder change. Columns in the structure are 1-based lists per row; the add-builder-item `column` input uses the same numbering. Items absent from items_with_settings run entirely on option defaults. Only the rendered section shows on the site — the default value carries a spare type-2 section.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{section?: string, item?: string} $input
 * @return array<string, mixed>|WP_Error
 */
function blocksy_get_footer(array $input): array|WP_Error
{
    return bly_get_builder_snapshot('footer', $input);
}
