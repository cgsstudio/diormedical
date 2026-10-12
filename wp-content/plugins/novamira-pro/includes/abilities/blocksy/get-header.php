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
 * Ability: structural snapshot of the Blocksy header builder (+ per-item
 * settings drill-down).
 */

wp_register_ability('novamira/blocksy-get-header', [
    'label' => __('Get Blocksy Header', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Blocksy header builder state (the header_placements theme_mod, or the theme default when nothing is stored — Blocksy never persists defaults). Without `item`: every section\'s structure as {desktop, mobile} → row (top-row/middle-row/bottom-row/offcanvas) → placement (start/middle/end/start-middle/end-middle) → placed item instance ids, plus which section currently renders (rendered_section — with Companion Pro header conditions this can differ per page) and which the customizer UI last edited (current_section). Per-item settings are NOT expanded here; pass `item` (an instance id from the structure, or a row id like middle-row) to get that item\'s stored settings, its placements, and a summary of its available options (id/type/label/default/choices) for edit-builder-item.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'section' => [
                'type' => 'string',
                'description' => 'Section id (default type-1; Companion Pro custom headers use ct-custom-… ids). Omit to target the section that currently renders.',
            ],
            'item' => [
                'type' => 'string',
                'description' => 'Item instance id (e.g. logo, button~a1b2…) or row id (e.g. middle-row) to drill into: returns its settings + option schema summary instead of the full structure.',
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
                'description' => 'False when the site runs on the theme default (no usable header_placements theme_mod is stored).',
            ],
            'stored_invalid' => [
                'type' => 'boolean',
                'description' => 'True when a header_placements mod exists but is structurally unusable; the reported state is then the theme default and writes will refuse until it is repaired.',
            ],
            'current_section' => [
                'type' => 'string',
                'description' => 'The section the customizer UI last edited (bookkeeping only).',
            ],
            'rendered_section' => [
                'type' => 'string',
                'description' => 'The section that actually renders (via the blocksy:header:current_section_id filter — Companion Pro conditions hook it).',
            ],
            'sections' => [
                'type' => ['array', 'null'],
                'description' => 'Structure mode only (null in item mode): one entry per section with id, name, structure {desktop, mobile} → row → placement → instance ids, items_with_settings, and section settings_keys. Oversized fields and lists are capped with explicit markers; see sections_omitted for section-level truncation.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_get_header',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Start here before any header builder change. Instance ids like button~a1b2… are extra copies of a clone-enabled item; the part before ~ is the item type. An item id appearing in both desktop and mobile trees is ONE shared instance (same settings). Items absent from items_with_settings run entirely on option defaults.',
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
function blocksy_get_header(array $input): array|WP_Error
{
    return bly_get_builder_snapshot('header', $input);
}

/**
 * Shared implementation behind blocksy-get-header / blocksy-get-footer:
 * resolves the effective builder value and dispatches to structure or
 * item-detail mode.
 *
 * @param array{section?: string, item?: string} $input
 * @return array<string, mixed>|WP_Error
 */
// Two dispatch modes (structure vs item detail) over defensively-typed
// stored trees keep the branch count above the metric caps; splitting
// further would separate the envelope from the mode picking it.
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:halstead
function bly_get_builder_snapshot(string $builder, array $input): array|WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }

    $section_id = isset($input['section']) && trim($input['section']) !== '' ? trim($input['section']) : null;
    $item = isset($input['item']) && trim($input['item']) !== '' ? trim($input['item']) : null;

    $effective = bly_builder_effective_value($builder);
    if ($effective instanceof WP_Error) {
        return $effective;
    }
    $value = $effective['value'];

    /** @var mixed $current_raw */
    $current_raw = $value['current_section'] ?? null;
    $base = [
        'builder' => $builder,
        'stored' => $effective['stored'],
        'stored_invalid' => $effective['stored_invalid'],
        'current_section' => is_string($current_raw) && $current_raw !== '' ? $current_raw : 'type-1',
        'rendered_section' => bly_builder_rendered_section_id($builder, $value),
        'sections' => null,
        'sections_omitted' => 0,
        'item' => null,
    ];

    if ($item !== null) {
        $found = bly_builder_find_section($builder, $value, $section_id);
        if ($found instanceof WP_Error) {
            return $found;
        }
        $detail = bly_builder_item_detail($builder, $found['section'], $item);
        if ($detail instanceof WP_Error) {
            return $detail;
        }
        $base['item'] = $detail;
        return $base;
    }

    $sections = [];
    $omitted = 0;
    /** @var mixed $section */
    foreach (is_array($value['sections'] ?? null) ? $value['sections'] : [] as $section) {
        if (!is_array($section)) {
            continue;
        }
        if ($section_id !== null && ($section['id'] ?? null) !== $section_id) {
            continue;
        }
        if (count($sections) >= BLY_BUILDER_MAX_SECTIONS) {
            $omitted++;
            continue;
        }
        $sections[] = bly_builder_section_summary($builder, $section);
    }
    if ($section_id !== null && $sections === []) {
        return bly_builder_find_section($builder, $value, $section_id); // guaranteed WP_Error with the id list
    }
    $base['sections'] = $sections;
    $base['sections_omitted'] = $omitted;
    return $base;
}

/**
 * Compact one-section summary for the structure mode of the get abilities.
 *
 * @param array<array-key, mixed> $section
 * @return array<string, mixed>
 */
function bly_builder_section_summary(string $builder, array $section): array
{
    $settings_keys = [];
    foreach (array_keys(is_array($section['settings'] ?? null) ? $section['settings'] : []) as $key) {
        $settings_keys[] = (string) $key;
    }

    $with_settings = [];
    /** @var mixed $entry */
    foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $entry) {
        if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id'])) {
            continue;
        }
        $with_settings[] = $entry['id'];
    }

    // The structure runs through the same oversized-value guard as every
    // S1 read (bly_cap_value): a corrupted mega-tree becomes a marker
    // string instead of blowing the agent context; the id lists carry
    // explicit truncation markers. Legitimate sections sit far below the
    // caps.
    return [
        'id' => isset($section['id']) && is_string($section['id']) ? $section['id'] : '',
        'name' => isset($section['name']) && is_string($section['name']) ? $section['name'] : null,
        'structure' => bly_cap_value(
            $builder === 'header' ? bly_header_structure_summary($section) : bly_footer_structure_summary($section),
        ),
        'items_with_settings' => bly_builder_cap_string_list(
            $with_settings,
            BLY_BUILDER_MAX_SETTINGS_IDS,
            what: 'item ids',
        ),
        'settings_keys' => bly_builder_cap_string_list($settings_keys, BLY_BUILDER_MAX_SECTION_KEYS, what: 'keys'),
    ];
}

/**
 * Item-detail mode shared by both get abilities: placements, stored
 * settings, and the option-schema summary for one instance (or row) id.
 *
 * @param array<array-key, mixed> $section
 * @return array<string, mixed>|WP_Error
 */
function bly_builder_item_detail(string $builder, array $section, string $instance): array|WP_Error
{
    $type = bly_original_item_id($instance);
    $placed = bly_builder_location_strings($builder, $section, $instance);
    $is_primary = in_array($instance, bly_builder_primary_ids($builder), strict: true);
    $values = bly_builder_item_values($section, $instance);

    if ($placed === [] && !$is_primary && $values === null) {
        $section_label = isset($section['id']) && is_string($section['id']) ? $section['id'] : '?';
        return bly_error('bly_not_found', sprintf(
            'Item "%s" is not placed in %s section "%s" and holds no settings there. Read the structure (no `item` input) for the placed instance ids.',
            $instance,
            $builder,
            $section_label,
        ));
    }

    $registered = bly_builder_registered_items($builder);
    if ($registered instanceof WP_Error) {
        return $registered;
    }
    $registration = $registered[$type] ?? null;

    $options = [];
    $truncated = false;
    $name = null;
    if ($registration !== null) {
        // The item option loader assumes the customizer schema preload the
        // main index performs — resolve it first and propagate its failure.
        $main_index = bly_option_index_or_error();
        if ($main_index instanceof WP_Error) {
            return $main_index;
        }
        $option_index = bly_builder_item_option_index($builder, $registration['id'], $registration['path']);
        if ($option_index instanceof WP_Error) {
            return $option_index;
        }
        $options = bly_builder_item_option_summary($option_index);
        $truncated = count($option_index) > count($options);
        /** @var mixed $name_raw */
        $name_raw = $registration['config']['name'] ?? null;
        $name = is_string($name_raw) ? $name_raw : null;
    }

    $section_id = isset($section['id']) && is_string($section['id']) ? $section['id'] : '';
    return [
        'id' => $instance,
        'item_type' => $type,
        'registered' => $registration !== null,
        'name' => $name,
        'section' => $section_id,
        'placed' => $placed,
        'has_settings' => $values !== null,
        'values' => $values === null ? new \stdClass() : bly_object_or_map(bly_cap_map_values($values)),
        'options' => $options,
        'options_truncated' => $truncated,
    ];
}

/**
 * Cap each value of a settings map for agent-facing output (bly_cap_value
 * per entry, so one oversized value cannot hide its siblings).
 *
 * @param array<array-key, mixed> $values
 * @return array<array-key, mixed>
 */
function bly_cap_map_values(array $values): array
{
    $out = [];
    /** @var mixed $value */
    foreach ($values as $key => $value) {
        /** @var mixed */
        $out[$key] = bly_cap_value($value);
    }
    return $out;
}
