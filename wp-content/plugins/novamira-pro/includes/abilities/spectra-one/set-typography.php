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
 * Ability: update Spectra One typography settings in the `swt_theme_options`
 * bag the theme's Customizer writes to. Wrapper on top of the theme-native
 * `spectra-one/update-typography`: strict allowlist against the
 * theme.json presets, explicit body / headings scope, stable WP_Error
 * codes, lock-protected read-modify-write on the option row.
 */

wp_register_ability('novamira/spectra-one-set-typography', [
    'label' => __('Set Spectra One Typography', domain: 'novamira-pro'),
    'description' => __(
        'Updates the Spectra One typography options bag (`swt_theme_options`) with new body and/or headings settings. Every value is validated against the theme.json preset allowlist reported by `novamira/spectra-one-get-typography-settings` — font_family must reference one of the bundled family slugs, font_weight one of the 9 declared weights, line_height one of the 5 declared line-heights. Body font_size accepts a responsive object with `desktop / tablet / mobile` numeric strings + a `desktop_unit` (defaults to px). Every field is optional — pass only what you want to change. Destructive: overwrites the existing values on the passed keys; the write is lock-protected and flushes the theme_json cache.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'body' => [
                'type' => 'object',
                'properties' => [
                    'font_family' => ['type' => 'string', 'maxLength' => 100],
                    'font_weight' => ['type' => 'string', 'maxLength' => 30],
                    'line_height' => ['type' => 'string', 'maxLength' => 30],
                    'font_size' => [
                        'type' => 'object',
                        'properties' => [
                            'desktop' => ['type' => 'string', 'maxLength' => 20],
                            'tablet' => ['type' => 'string', 'maxLength' => 20],
                            'mobile' => ['type' => 'string', 'maxLength' => 20],
                            'desktop_unit' => [
                                'type' => 'string',
                                'enum' => ['px', 'rem', 'em', '%'],
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'additionalProperties' => false,
            ],
            'headings' => [
                'type' => 'object',
                'properties' => [
                    'font_family' => ['type' => 'string', 'maxLength' => 100],
                    'font_weight' => ['type' => 'string', 'maxLength' => 30],
                    'line_height' => ['type' => 'string', 'maxLength' => 30],
                ],
                'additionalProperties' => false,
            ],
        ],
        'additionalProperties' => false,
        'minProperties' => 1,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'updated_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'The swt_theme_options keys that were actually mutated by this call.',
            ],
            'body' => [
                'type' => 'object',
                'properties' => [
                    'font_family' => ['type' => 'string'],
                    'font_weight' => ['type' => 'string'],
                    'line_height' => ['type' => 'string'],
                ],
                'required' => ['font_family', 'font_weight', 'line_height'],
                'additionalProperties' => false,
            ],
            'headings' => [
                'type' => 'object',
                'properties' => [
                    'font_family' => ['type' => 'string'],
                    'font_weight' => ['type' => 'string'],
                    'line_height' => ['type' => 'string'],
                ],
                'required' => ['font_family', 'font_weight', 'line_height'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['updated_keys', 'body', 'headings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_set_typography',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial patch — pass only the keys you want to change. Preset slug allowlists come from novamira/spectra-one-get-typography-settings.presets; values outside those lists are refused with spectra_one_unknown_setting. The write is idempotent — re-running with the same input yields the same state.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{body?: array<string, mixed>, headings?: array<string, mixed>} $input
 * @return array{
 *     updated_keys: list<string>,
 *     body: array{font_family: string, font_weight: string, line_height: string},
 *     headings: array{font_family: string, font_weight: string, line_height: string},
 * }|WP_Error
 */
function spectra_one_set_typography(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $theme_json = function_exists('Swt\\get_theme_json') ? \Swt\get_theme_json() : [];
    $family_slugs = so_preset_slugs($theme_json, ['settings', 'typography', 'fontFamilies']);
    $weight_slugs = so_preset_slugs($theme_json, ['settings', 'custom', 'fontWeight']);
    $lineheight_slugs = so_preset_slugs($theme_json, ['settings', 'custom', 'lineHeight']);

    return so_with_design_lock(
        static fn(): array|WP_Error => so_write_typography_options(
            $input,
            $family_slugs,
            $weight_slugs,
            $lineheight_slugs,
        ),
    );
}

/**
 * @param array{body?: array<string, mixed>, headings?: array<string, mixed>} $input
 * @param list<string> $family_slugs
 * @param list<string> $weight_slugs
 * @param list<string> $lineheight_slugs
 * @return array{
 *     updated_keys: list<string>,
 *     body: array{font_family: string, font_weight: string, line_height: string},
 *     headings: array{font_family: string, font_weight: string, line_height: string},
 * }|WP_Error
 */
function so_write_typography_options(
    array $input,
    array $family_slugs,
    array $weight_slugs,
    array $lineheight_slugs,
): array|WP_Error {
    /** @var mixed $raw_options */
    $raw_options = get_option('swt_theme_options', default_value: []);
    $options = is_array($raw_options) ? $raw_options : [];

    /** @var list<string> $updated_keys */
    $updated_keys = [];

    $body = $input['body'] ?? [];
    if ($body !== []) {
        $result = so_apply_typography_scope($options, $body, [
            'font_family' => ['body-font-family', $family_slugs],
            'font_weight' => ['body-font-weight', $weight_slugs],
            'line_height' => ['body-line-height', $lineheight_slugs],
        ]);
        if ($result instanceof WP_Error) {
            return $result;
        }
        $options = $result['options'];
        foreach ($result['updated'] as $k) {
            $updated_keys[] = $k;
        }

        if (isset($body['font_size']) && is_array($body['font_size'])) {
            /** @var mixed $current_size_raw */
            $current_size_raw = $options['font-size-body'] ?? [];
            $current_size = is_array($current_size_raw) ? $current_size_raw : [];
            $new_size = so_merge_font_size($current_size, $body['font_size']);
            if ($current_size !== $new_size) {
                $options['font-size-body'] = $new_size;
                $updated_keys[] = 'font-size-body';
            }
        }
    }

    $headings = $input['headings'] ?? [];
    if ($headings !== []) {
        $result = so_apply_typography_scope($options, $headings, [
            'font_family' => ['headings-font-family', $family_slugs],
            'font_weight' => ['headings-font-weight', $weight_slugs],
            'line_height' => ['headings-line-height', $lineheight_slugs],
        ]);
        if ($result instanceof WP_Error) {
            return $result;
        }
        $options = $result['options'];
        foreach ($result['updated'] as $k) {
            $updated_keys[] = $k;
        }
    }

    update_option('swt_theme_options', $options, autoload: true);
    so_flush_swt_theme_options_cache();

    return [
        'updated_keys' => $updated_keys,
        'body' => [
            'font_family' => so_string_at($options, ['body-font-family']),
            'font_weight' => so_string_at($options, ['body-font-weight']),
            'line_height' => so_string_at($options, ['body-line-height']),
        ],
        'headings' => [
            'font_family' => so_string_at($options, ['headings-font-family']),
            'font_weight' => so_string_at($options, ['headings-font-weight']),
            'line_height' => so_string_at($options, ['headings-line-height']),
        ],
    ];
}
