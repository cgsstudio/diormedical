<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-set-typography-preset', [
    'label' => __('Apply OceanWP Typography Preset', domain: 'novamira-pro'),
    'description' => __(
        'Applies a curated typography preset in one call: body typography (`ocean_body_typography`), headings typography (`ocean_headings_typography`), primary brand color (`ocean_primary_color`), and link color (`ocean_links_color`). Each top-level field is optional — only those passed are written. IMPORTANT: typography fields (`body`, `headings`) FULLY REPLACE the existing typography theme_mod — sub-keys you omit are dropped, NOT merged. Read the current typography with `oceanwp-get-settings` and re-pass every sub-key you want to keep. Typography sub-keys: font-family, font-weight, font-size, font-size-tablet, font-size-mobile, line-height, letter-spacing, text-transform, font-style.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'body' => [
                'type' => 'object',
                'description' => 'Body typography object (font-family, font-weight, font-size, line-height, etc.). REPLACES the existing typography blob — omitted sub-keys are dropped, not merged. Must contain at least one sub-key.',
                'additionalProperties' => true,
                'minProperties' => 1,
            ],
            'headings' => [
                'type' => 'object',
                'description' => 'Headings typography object (applies to h1–h6 unless individually overridden). REPLACES the existing typography blob — omitted sub-keys are dropped, not merged. Must contain at least one sub-key.',
                'additionalProperties' => true,
                'minProperties' => 1,
            ],
            'primary_color' => [
                'type' => 'string',
                'description' => 'Primary brand color (CSS color string — hex / rgb / rgba / hsl / named).',
            ],
            'links_color' => [
                'type' => 'string',
                'description' => 'Default link color (CSS color string).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unchanged' => ['type' => 'array', 'items' => ['type' => 'string']],
            'values' => [
                'type' => 'object',
                'description' => 'The post-write value of every preset field that was accepted.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['persisted', 'changed', 'unchanged', 'values'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_set_typography_preset',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to apply a coherent typography + brand color preset in one call. Internally maps `body` -> `ocean_body_typography`, `headings` -> `ocean_headings_typography`, `primary_color` -> `ocean_primary_color`, `links_color` -> `ocean_links_color`. For h1/h2 overrides, follow up with `oceanwp-set-customizer-settings` on `ocean_h1_typography` / `ocean_h2_typography`.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{body?: array<string, mixed>, headings?: array<string, mixed>, primary_color?: string, links_color?: string} $input
 * @return array{
 *   persisted: bool,
 *   changed: list<string>,
 *   unchanged: list<string>,
 *   values: array<string, mixed>|\stdClass
 * }|WP_Error
 */
function oceanwp_set_typography_preset(array $input): array|WP_Error
{
    $detected = ow_detect_setup();
    if (!$detected['active'] || !$detected['min_satisfied']) {
        return ow_not_active();
    }
    $aliases = [
        'body' => 'ocean_body_typography',
        'headings' => 'ocean_headings_typography',
        'primary_color' => 'ocean_primary_color',
        'links_color' => 'ocean_links_color',
    ];
    $alias_for = array_flip($aliases);
    /** @var array<string, mixed> $forwarded */
    $forwarded = [];
    foreach ($aliases as $short => $theme_mod_name) {
        if (!array_key_exists($short, $input)) {
            continue;
        }
        $forwarded[$theme_mod_name] = $input[$short];
    }
    if ($forwarded === []) {
        return ow_invalid_input(__(
            'No preset fields supplied. Pass at least one of: body, headings, primary_color, links_color.',
            domain: 'novamira-pro',
        ));
    }

    // Pre-validate BEFORE delegating so a rejection does not leave any
    // already-validated field persisted to theme_mods_oceanwp. See audit
    // round 1 HIGH finding. ow_format_alias_rejections aggregates every
    // rejection into a single user-facing message via the public aliases
    // (M#11 + M#13 in one helper, shared with set-header-config).
    $rejections = ow_prevalidate_customizer_writes($forwarded);
    if ($rejections !== []) {
        return ow_format_alias_rejections(
            $rejections,
            $alias_for,
            __('Typography preset rejected —', domain: 'novamira-pro'),
        );
    }

    $result = oceanwp_set_customizer_settings(['settings' => $forwarded]);
    if ($result instanceof WP_Error) {
        return $result;
    }
    // Surface preset aliases (`body`, `headings`, `primary_color`,
    // `links_color`) on the success path so the response vocabulary
    // matches the input schema's. Audit round 2 M#7.
    return ow_translate_result_to_aliases($result, $alias_for);
}
