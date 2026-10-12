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
 * Ability: edit one placed builder item's settings (merge semantics).
 */

wp_register_ability('novamira/blocksy-edit-builder-item', [
    'label' => __('Edit Blocksy Builder Item', domain: 'novamira-pro'),
    'description' => __(
        'Edits the settings of one placed Blocksy header/footer builder item (or of a row container like middle-row — rows store their settings the same way) by instance id, with MERGE semantics: only the keys you pass change, every other stored setting of that item and every other item survive. Valid setting keys come from the item\'s own option schema — read blocksy-get-header / blocksy-get-footer with `item` for the key list (id/type/label/default/choices); unknown keys are rejected. Each value passes the same two validation gates as blocksy-edit-option (schema-derived typed checks with ranges, unit grammar and CSS-injection refusal, then Blocksy\'s own per-control sanitizer), and the whole builder value additionally passes Blocksy\'s builder sanitizer, which HTML-sanitizes text/copyright content. The write is atomic and runs Blocksy\'s dynamic-CSS refresh sequence — header/footer changes reach the generated CSS immediately.',
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
                'description' => 'Item instance id (e.g. logo, text, button~a1b2…) or row id (e.g. middle-row) as shown by the get abilities.',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'Section id (default: the section that currently renders).',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Partial map of setting key → new value. Keys must exist in the item\'s option schema; values must match each option\'s shape.',
            ],
        ],
        'required' => ['builder', 'item', 'settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'builder' => ['type' => 'string'],
            'section' => ['type' => 'string'],
            'item' => ['type' => 'string'],
            'changed_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'The setting keys this call wrote (after validation/coercion).',
            ],
            'values' => [
                'type' => 'object',
                'description' => 'The item\'s full stored settings after the merge (oversized values replaced with a marker).',
            ],
            'previous_values' => [
                'type' => ['object', 'null'],
                'description' => 'The item\'s stored settings before the merge; null when the item had no settings entry yet (it ran on defaults).',
            ],
            'previously_had_settings' => ['type' => 'boolean'],
        ],
        'required' => [
            'builder',
            'section',
            'item',
            'changed_keys',
            'values',
            'previous_values',
            'previously_had_settings',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_edit_builder_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the item first (blocksy-get-header / blocksy-get-footer with `item`) to learn its valid setting keys, shapes and choices. On any validation error nothing is persisted. A plain instance id shown in both header device trees is ONE shared instance — editing it changes both.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{builder?: string, item?: string, section?: string, settings?: mixed} $input
 * @return array<string, mixed>|WP_Error
 */
// Validation-dense write callback (input gates + per-key schema checks
// + locked compute) — the branch count is the checks themselves.
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:halstead
// @mago-expect lint:kan-defect
function blocksy_edit_builder_item(array $input): array|WP_Error
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

    /** @var mixed $settings_raw */
    $settings_raw = $input['settings'] ?? null;
    if ($settings_raw instanceof \stdClass) {
        $settings_raw = get_object_vars($settings_raw);
    }
    if (!is_array($settings_raw) || $settings_raw === [] || bly_array_is_list($settings_raw)) {
        return bly_error('bly_invalid_input', message: '`settings` must be a non-empty object of setting key → value.');
    }
    if (count($settings_raw) > BLY_BUILDER_MAX_EDIT_KEYS) {
        return bly_error('bly_invalid_input', sprintf(
            '`settings` carries %d keys, above the %d-key cap per call.',
            count($settings_raw),
            BLY_BUILDER_MAX_EDIT_KEYS,
        ));
    }

    // The item's option schema (gate 1 reference) — the type must be
    // registered, or its settings cannot be validated at all.
    $type = bly_original_item_id($instance);
    $registered = bly_builder_registered_items($builder);
    if ($registered instanceof WP_Error) {
        return $registered;
    }
    if (!isset($registered[$type])) {
        return bly_error('bly_not_found', sprintf(
            'Item type "%s" is not registered for the %s builder (its providing plugin may be inactive) — its settings cannot be validated, so the edit is refused.',
            $type,
            $builder,
        ));
    }

    // Resolves gate 2 AND preloads the customizer helpers the item option
    // loader assumes — must precede bly_builder_item_option_index().
    $main_index = bly_option_index_or_error();
    if ($main_index instanceof WP_Error) {
        return $main_index;
    }
    $option_index = bly_builder_item_option_index($builder, $type, $registered[$type]['path']);
    if ($option_index instanceof WP_Error) {
        return $option_index;
    }
    if ($option_index === []) {
        return bly_error('bly_invalid_input', sprintf('Item "%s" declares no editable options.', $type));
    }

    $validated = [];
    /** @var mixed $candidate */
    foreach ($settings_raw as $key => $candidate) {
        $key = (string) $key;
        if (!isset($option_index[$key])) {
            $known = array_slice(array_keys($option_index), offset: 0, length: 40);
            return bly_error('bly_not_found', sprintf(
                'Setting "%s" does not exist on item "%s". Valid keys%s: %s.',
                $key,
                $type,
                count($option_index) > 40 ? sprintf(' (first 40 of %d)', count($option_index)) : '',
                implode(', ', $known),
            ));
        }
        /** @var mixed $clean */
        $clean = bly_validate_option_value($option_index[$key], $candidate);
        if ($clean instanceof WP_Error) {
            return $clean;
        }
        // HTML-bearing (wp-editor) controls get Blocksy's own
        // capability-aware HTML sanitization HERE, normalized by the
        // item's BASE TYPE: the theme's builder sanitizer matches literal
        // instance ids only ('text', plus a 'copyright_text' key that
        // never matches the real 'copyright' id), so clone instances and
        // the footer copyright would otherwise persist unsanitized HTML
        // that renders through a bare do_shortcode().
        if ($option_index[$key]['type'] === 'wp-editor' && is_string($clean)) {
            $clean = bly_builder_sanitize_html_leaf($key, $clean);
            if ($clean instanceof WP_Error) {
                return $clean;
            }
        }
        /** @var mixed */
        $validated[$key] = $clean;
    }

    $result = [
        'section' => '',
        'values' => [],
        'previous_values' => null,
        'previously_had_settings' => false,
    ];

    $item_path = $registered[$type]['path'];
    /** @var mixed $written */
    $written = bly_locked_mod_write(
        bly_builder_mod_id($builder),
        function (array $mods) use (
            $builder,
            $type,
            $item_path,
            $section_id,
            $instance,
            $option_index,
            $validated,
            &$result,
        ): mixed {
            $prepared = bly_builder_prepare_write_value($builder, $mods, $section_id);
            if ($prepared instanceof WP_Error) {
                return $prepared;
            }
            ['value' => $value, 'index' => $index, 'section' => $section] = $prepared;

            $is_primary = in_array($instance, bly_builder_primary_ids($builder), strict: true);
            $existing = bly_builder_item_values($section, $instance);
            if (
                !$is_primary
                && $existing === null
                && bly_builder_location_strings($builder, $section, $instance) === []
            ) {
                /** @var mixed $section_id_raw */
                $section_id_raw = $section['id'] ?? '?';
                return bly_error('bly_not_found', sprintf(
                    'Item "%s" is not placed in %s section "%s" — place it with blocksy-add-builder-item first, or check the instance id against blocksy-get-%s.',
                    $instance,
                    $builder,
                    is_string($section_id_raw) ? $section_id_raw : '?',
                    $builder,
                ));
            }

            $result['previously_had_settings'] = $existing !== null;
            $result['previous_values'] = $existing;
            $merged = array_merge($existing ?? [], $validated);
            bly_builder_set_item_values($section, $instance, $merged);
            $result['values'] = $merged;
            /** @var mixed $section_id_raw */
            $section_id_raw = $section['id'] ?? '';
            $result['section'] = is_string($section_id_raw) ? $section_id_raw : '';

            $value = bly_builder_replace_section($value, $index, $section);
            $sanitized = bly_builder_gate2($builder, $value);
            if ($sanitized instanceof WP_Error) {
                return $sanitized;
            }
            // Report what actually persists: gate 2 may HTML-sanitize
            // text/copyright content inside the value.
            /** @var mixed $sanitized_section */
            $sanitized_section = $sanitized['sections'][$index] ?? null;
            if (is_array($sanitized_section)) {
                $result['values'] = bly_builder_item_values($sanitized_section, $instance) ?? $merged;
            }
            return $sanitized;
        },
        $stylesheet,
        $mods_key,
        // Settings whose choice vocabulary is DYNAMIC (the menu picker
        // above all) are re-resolved from a fresh load of the item's
        // options.php and re-checked against the FINAL builder value —
        // after the pre_set filter window, immediately before the
        // persist tail; static ones cost nothing. See
        // bly_builder_recheck_dynamic_settings.
        static function (mixed $final) use (
            $builder,
            $type,
            $item_path,
            $option_index,
            $validated,
            $instance,
            &$result,
        ): ?WP_Error {
            // $result['section'] is the section the compute step resolved
            // (by reference: it is filled during compute, before this runs).
            return bly_builder_recheck_dynamic_settings(
                $builder,
                $type,
                $item_path,
                $option_index,
                bly_builder_final_item_values($final, $result['section'], $instance, $validated),
            );
        },
    );
    if ($written instanceof WP_Error) {
        return $written;
    }

    $merged_values = $result['values'];
    $previous_values = $result['previous_values'];
    return [
        'builder' => $builder,
        'section' => $result['section'],
        'item' => $instance,
        'changed_keys' => array_map('strval', array_keys($validated)),
        'values' => bly_object_or_map(bly_cap_map_values($merged_values)),
        'previous_values' => $previous_values === null ? null : bly_object_or_map(bly_cap_map_values($previous_values)),
        'previously_had_settings' => $result['previously_had_settings'],
    ];
}
