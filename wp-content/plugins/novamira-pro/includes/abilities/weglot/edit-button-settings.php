<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-edit-button-settings', [
    'label' => __('Edit Weglot Button (Language Switcher) Settings', domain: 'novamira-pro'),
    'description' => __(
        'Partial-update for the language switcher button stored under `custom_settings.button_style`: only the fields included in the input are touched, everything else is preserved. Use weglot-get-settings (section=button) first to read the current shape. Booleans: full_name (long vs ISO code label), with_name (show label), is_dropdown (dropdown vs flat list), with_flags (show country flags). flag_type accepts the four styles Weglot defines: "rectangle_mat", "shiny", "circle", "square" (see Helper_Flag_Type). The numeric aliases Weglot maps internally (0-3) are NOT accepted here — the schema enum rejects them. custom_css overrides the bundled switcher CSS.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'full_name' => ['type' => 'boolean'],
            'with_name' => ['type' => 'boolean'],
            'is_dropdown' => ['type' => 'boolean'],
            'with_flags' => ['type' => 'boolean'],
            'flag_type' => [
                'type' => 'string',
                'enum' => ['rectangle_mat', 'shiny', 'circle', 'square'],
            ],
            'custom_css' => ['type' => 'string'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
            'button' => [
                'type' => 'object',
                'properties' => [
                    'full_name' => ['type' => 'boolean'],
                    'with_name' => ['type' => 'boolean'],
                    'is_dropdown' => ['type' => 'boolean'],
                    'with_flags' => ['type' => 'boolean'],
                    'flag_type' => ['type' => ['string', 'null']],
                    'custom_css' => ['type' => 'string'],
                ],
                'required' => ['full_name', 'with_name', 'is_dropdown', 'with_flags', 'flag_type', 'custom_css'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['persisted', 'changed', 'button'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_edit_button_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update — omit a field to keep its current value. Returns a `changed` list with the names of the fields that differ from the previous state. Passing an empty input returns `persisted: false` with `changed: []`.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param  array{full_name?: bool, with_name?: bool, is_dropdown?: bool, with_flags?: bool, flag_type?: string, custom_css?: string} $input
 * @return array{persisted: bool, changed: list<string>, button: array{full_name: bool, with_name: bool, is_dropdown: bool, with_flags: bool, flag_type: string|null, custom_css: string}}|WP_Error
 */
function weglot_edit_button_settings(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }
    if ($input === []) {
        // No-op: empty input → return current shape without locking.
        // Resolved, not raw: the local option carries no button settings on a
        // cloud-driven install, so reading it returned the schema defaults
        // (every flag true) instead of the style actually in force — the same
        // blindness the read surface was fixed for, left behind in this branch.
        $options = wg_options_resolved();
        return [
            'persisted' => false,
            'changed' => [],
            'button' => wg_button_view_from_options($options),
        ];
    }

    return wg_write_options_delta(['custom_settings'], static function (array $options) use ($input): array {
        $custom = is_array($options['custom_settings'] ?? null) ? $options['custom_settings'] : [];
        $button = is_array($custom['button_style'] ?? null) ? $custom['button_style'] : [];

        [$button, $changed] = wg_button_merge_partial($button, $input);

        $custom['button_style'] = $button;
        $options['custom_settings'] = $custom;

        return [
            $options,
            [
                'persisted' => $changed !== [],
                'changed' => $changed,
                'button' => wg_button_view_from_options($options),
            ],
        ];
    });
}

/**
 * Apply a partial-update of the button-style cluster, returning the new shape
 * and the list of fields that actually changed.
 *
 * @param  array<array-key, mixed> $button
 * @param  array{full_name?: bool, with_name?: bool, is_dropdown?: bool, with_flags?: bool, flag_type?: string, custom_css?: string} $input
 * @return array{0: array<array-key, mixed>, 1: list<string>}
 */
function wg_button_merge_partial(array $button, array $input): array
{
    $changed = [];
    foreach (['full_name', 'with_name', 'is_dropdown', 'with_flags'] as $bool_field) {
        if (!array_key_exists($bool_field, $input)) {
            continue;
        }
        $new = wg_coerce_bool($input[$bool_field]);
        if (wg_coerce_bool($button[$bool_field] ?? null) !== $new || !array_key_exists($bool_field, $button)) {
            $button[$bool_field] = $new;
            $changed[] = $bool_field;
        }
    }
    foreach (['flag_type', 'custom_css'] as $string_field) {
        if (!array_key_exists($string_field, $input)) {
            continue;
        }
        $new = $input[$string_field];
        if (($button[$string_field] ?? null) !== $new) {
            $button[$string_field] = $new;
            $changed[] = $string_field;
        }
    }
    return [$button, $changed];
}

/**
 * @param  array<string, mixed> $options
 * @return array{full_name: bool, with_name: bool, is_dropdown: bool, with_flags: bool, flag_type: string|null, custom_css: string}
 */
function wg_button_view_from_options(array $options): array
{
    $custom = is_array($options['custom_settings'] ?? null) ? $options['custom_settings'] : [];
    $button = is_array($custom['button_style'] ?? null) ? $custom['button_style'] : [];

    /** @var mixed $flag_type_raw */
    $flag_type_raw = $button['flag_type'] ?? null;
    /** @var mixed $custom_css_raw */
    $custom_css_raw = $button['custom_css'] ?? '';

    return [
        'full_name' => wg_coerce_bool_true_default($button['full_name'] ?? null),
        'with_name' => wg_coerce_bool_true_default($button['with_name'] ?? null),
        'is_dropdown' => wg_coerce_bool_true_default($button['is_dropdown'] ?? null),
        'with_flags' => wg_coerce_bool_true_default($button['with_flags'] ?? null),
        'flag_type' => is_string($flag_type_raw) || is_int($flag_type_raw) ? (string) $flag_type_raw : null,
        'custom_css' => is_string($custom_css_raw) ? $custom_css_raw : '',
    ];
}
