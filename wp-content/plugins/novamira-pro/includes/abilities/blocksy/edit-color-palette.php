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
 * Ability: partial (per-slot) edit of the Blocksy global color palette.
 */

wp_register_ability('novamira/blocksy-edit-color-palette', [
    'label' => __('Edit Blocksy Color Palette', domain: 'novamira-pro'),
    'description' => __(
        'Changes one or more slots of the Blocksy global color palette (the `colorPalette` theme_mod) by merging a {slot id → color} map onto the resolved current palette. Slots not mentioned keep their current value — stored or schema default (Blocksy never persists defaults, so on a fresh site unmentioned slots are written with their default color, which is a visual no-op). Slot ids come from blocksy-get-color-palette (color1…color8 on a stock install); unknown slots are rejected. Colors accept hex, rgb()/rgba()/hsl()/hsla(), var(--…) references, transparent and currentColor. Extra bookkeeping keys a legacy stored palette may carry are preserved verbatim. After the write, Blocksy\'s theme-mod cache and both dynamic-CSS caches (transient + CSS file) are refreshed automatically — every element bound to the slot\'s --theme-palette-color-N variable recolors on the next front-end render.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'colors' => [
                'type' => 'object',
                'description' => 'Map of palette slot id (e.g. color1) to the new color value. Only the slots present here change.',
                'additionalProperties' => ['type' => 'string'],
                'minProperties' => 1,
            ],
        ],
        'required' => ['colors'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'updated_slots' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Slot ids this call changed.',
            ],
            'colors' => [
                'type' => 'array',
                'description' => 'The full palette after the write.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'color' => ['type' => 'string'],
                        'css_variable' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'color', 'css_variable'],
                    'additionalProperties' => false,
                ],
            ],
            'omitted_slots' => [
                'type' => 'integer',
                'description' => 'Slot ids beyond the 24-slot response cap that were omitted from `colors` (0 normally; non-zero only when a legacy stored value carries extra slots). The write itself is never truncated.',
            ],
        ],
        'required' => ['updated_slots', 'colors', 'omitted_slots'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_edit_color_palette',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inspect the palette with blocksy-get-color-palette first and change the smallest set of slots that achieves the goal — derived globals (fontColor, linkColor, headings, buttons) reference the slots by CSS variable, so one slot edit can recolor many elements. The write is validated and atomic: on any error nothing is persisted.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{colors?: mixed} $input
 * @return array{
 *     updated_slots: list<string>,
 *     colors: list<array{id: string, color: string, css_variable: string}>,
 *     omitted_slots: int,
 * }|WP_Error
 */
function blocksy_edit_color_palette(array $input): array|WP_Error
{
    // Pin the storage identity as the VERY FIRST statements — before the
    // availability gate's filtered get_template() lookup and the
    // option-tree sync preload; see blocksy_edit_option().
    $stylesheet = get_stylesheet();
    $template = get_template();
    $mods_key = 'theme_mods_' . $stylesheet;

    if (!blocksy_theme_available($template)) {
        return bly_theme_inactive_error();
    }

    $edits = bly_palette_edits_or_error($input['colors'] ?? null);
    if ($edits instanceof WP_Error) {
        return $edits;
    }

    $index = bly_option_index_or_error();
    if ($index instanceof WP_Error) {
        return $index;
    }

    /** @var mixed $default_raw */
    $default_raw = $index['colorPalette']['option']['value'] ?? null;
    $default_entries = is_array($default_raw) ? $default_raw : [];
    if (bly_palette_color_map($default_entries) === []) {
        return bly_schema_error('the colorPalette definition carries no default color slots');
    }

    /** @var mixed $written */
    $written = bly_locked_mod_write(
        'colorPalette',
        static fn(array $mods): array|WP_Error => bly_merge_palette($default_entries, $mods, $edits),
        $stylesheet,
        $mods_key,
    );
    if ($written instanceof WP_Error) {
        return $written;
    }
    /** @var array<array-key, mixed> $written */

    $colors = [];
    foreach (bly_palette_color_map($written) as $slot_id => $color) {
        $colors[] = [
            'id' => $slot_id,
            'color' => $color,
            'css_variable' =>
                '--theme-palette-color-' . (string) preg_replace('/\D/', replacement: '', subject: $slot_id),
        ];
    }
    usort($colors, static fn(array $a, array $b): int => strnatcmp($a['id'], $b['id']));

    $updated = array_keys($edits);
    sort($updated, SORT_NATURAL);

    return [
        'updated_slots' => $updated,
        'colors' => array_slice($colors, offset: 0, length: BLY_MAX_PALETTE_SLOTS),
        // The response cap never truncates the WRITE — the persisted value
        // holds every slot; omitted_slots signals response-echo truncation.
        'omitted_slots' => max(0, count($colors) - BLY_MAX_PALETTE_SLOTS),
    ];
}

/**
 * Validate the raw `colors` input into a clean {colorN => color} edit map:
 * slot ids must look like palette slots (colorN) and every color must pass
 * the palette color whitelist. Slot EXISTENCE is checked later, inside the
 * write lock, against the fresh resolved palette.
 *
 * @return array<string, string>|WP_Error
 */
function bly_palette_edits_or_error(mixed $colors): array|WP_Error
{
    if (!is_array($colors) || $colors === []) {
        return bly_error(
            'bly_invalid_input',
            message: '`colors` is required and must be a non-empty object mapping slot ids (e.g. color1) to color strings.',
        );
    }

    $edits = [];
    /** @var mixed $color */
    foreach ($colors as $slot_id => $color) {
        $slot_id = (string) $slot_id;
        if (preg_match('/^color\d+$/', $slot_id) !== 1) {
            return bly_error('bly_invalid_input', sprintf(
                '"%s" is not a palette slot id — slots are named color1, color2, … (see blocksy-get-color-palette).',
                $slot_id,
            ));
        }
        if (!is_string($color) || !bly_valid_palette_color($color)) {
            return bly_error('bly_invalid_input', sprintf(
                'Slot "%s": "%s" is not a valid color. Accepted: hex (#rrggbb / #rrggbbaa), rgb()/rgba()/hsl()/hsla(), var(--…), transparent, currentColor.',
                $slot_id,
                is_scalar($color) ? (string) $color : gettype($color),
            ));
        }
        $edits[$slot_id] = trim($color);
    }
    return $edits;
}

/**
 * Build the complete colorPalette value to persist: schema default entries
 * first (full definition arrays, so unmentioned slots resolve exactly as
 * before), overlaid with every well-formed stored slot entry, carrying any
 * extra stored bookkeeping keys verbatim, then the requested edits applied
 * onto each target entry's `color` key (other entry keys preserved).
 * Unknown target slots — outside both the schema defaults and the stored
 * palette — abort the write.
 *
 * @param array<array-key, mixed> $default_entries
 * @param array<array-key, mixed> $mods Fresh stored mods (inside the lock).
 * @param array<string, string> $edits
 * @return array<array-key, mixed>|WP_Error
 */
function bly_merge_palette(array $default_entries, array $mods, array $edits): array|WP_Error
{
    /** @var mixed $stored_raw */
    $stored_raw = $mods['colorPalette'] ?? null;
    $stored = is_array($stored_raw) ? $stored_raw : [];

    $value = [];
    /** @var mixed $entry */
    foreach ($default_entries as $slot_id => $entry) {
        if (!is_string($slot_id) || preg_match('/^color\d+$/', $slot_id) !== 1 || !is_array($entry)) {
            continue;
        }
        $value[$slot_id] = $entry;
    }
    /** @var mixed $entry */
    foreach ($stored as $key => $entry) {
        if (!is_string($key) || preg_match('/^color\d+$/', $key) !== 1) {
            // Extra bookkeeping keys are carried verbatim.
            /** @var mixed */
            $value[$key] = $entry;
            continue;
        }
        // Malformed stored slots (non-array, missing color) keep the
        // schema-default entry seeded above instead of round-tripping.
        if (is_array($entry) && isset($entry['color']) && is_string($entry['color'])) {
            $value[$key] = $entry;
        }
    }

    foreach ($edits as $slot_id => $color) {
        if (!isset($value[$slot_id]) || !is_array($value[$slot_id])) {
            $known = array_keys(bly_palette_color_map($value));
            sort($known, SORT_NATURAL);
            return bly_error('bly_not_found', sprintf(
                'Palette slot "%s" does not exist on this site. Valid slots: %s.',
                $slot_id,
                implode(', ', $known),
            ));
        }
        $entry = $value[$slot_id];
        $entry['color'] = $color;
        $value[$slot_id] = $entry;
    }

    return $value;
}
