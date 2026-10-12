<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: update Spectra One global theme options — the `swt_theme_options`
 * flags the theme's Customizer surfaces (scroll-to-top toggle + related
 * global flags). Wrapper on top of the theme-native
 * `spectra-one/update-theme-settings` with a strict input allowlist.
 */

wp_register_ability('novamira/spectra-one-set-theme-settings', [
    'label' => __('Set Spectra One Theme Settings', domain: 'novamira-pro'),
    'description' => __(
        'Updates one or more global theme options in the `swt_theme_options` bag. The `options` object accepts `scroll_top` (boolean — enables / disables the "back to top" button the theme renders on the frontend). Additional flags land here as later releases expose them. Every field is optional; pass only what you want to change. Destructive: overwrites the existing option value; the write is lock-protected.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'options' => [
                'type' => 'object',
                'properties' => [
                    'scroll_top' => ['type' => 'boolean'],
                ],
                'additionalProperties' => false,
                'minProperties' => 1,
            ],
        ],
        'required' => ['options'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'updated_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'options' => [
                'type' => 'object',
                'properties' => [
                    'scroll_top' => ['type' => 'boolean'],
                ],
                'required' => ['scroll_top'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['updated_keys', 'options'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_set_theme_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial patch on swt_theme_options. Use novamira/spectra-one-get-theme-settings to read the current state first.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{options: array{scroll_top?: bool}} $input
 * @return array{updated_keys: list<string>, options: array{scroll_top: bool}}|WP_Error
 */
function spectra_one_set_theme_settings(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $requested = $input['options'];

    return so_with_design_lock(static function () use ($requested): array {
        /** @var mixed $raw_options */
        $raw_options = get_option('swt_theme_options', default_value: []);
        $options = is_array($raw_options) ? $raw_options : [];

        /** @var list<string> $updated_keys */
        $updated_keys = [];

        if (array_key_exists('scroll_top', $requested)) {
            $new_bool = $requested['scroll_top'];
            /** @var mixed $old_raw */
            $old_raw = array_key_exists('scroll_top', $options) ? $options['scroll_top'] : false;
            $old_bool = filter_var($old_raw, FILTER_VALIDATE_BOOLEAN);
            if ($old_bool !== $new_bool) {
                $options['scroll_top'] = $new_bool;
                $updated_keys[] = 'scroll_top';
            }
        }

        update_option('swt_theme_options', $options, autoload: true);
        so_flush_swt_theme_options_cache();

        /** @var mixed $current_raw */
        $current_raw = array_key_exists('scroll_top', $options) ? $options['scroll_top'] : false;

        return [
            'updated_keys' => $updated_keys,
            'options' => [
                'scroll_top' => filter_var($current_raw, FILTER_VALIDATE_BOOLEAN),
            ],
        ];
    });
}
