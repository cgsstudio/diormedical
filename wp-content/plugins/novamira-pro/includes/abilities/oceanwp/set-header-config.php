<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-set-header-config', [
    'label' => __('Set OceanWP Header Configuration', domain: 'novamira-pro'),
    'description' => __(
        'Narrow writer for the OceanWP header subdomain: header `style` (8-value enum — minimal, transparent, top, full_screen, center, medium, vertical, custom), header `height` in pixels, `transparent` boolean, and `custom_template` Library template id (only effective when style is "custom"). Partial merge — pass only the fields you want to change. Same lock + allowlist enforcement as `oceanwp-set-customizer-settings`.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'style' => [
                'type' => 'string',
                'description' => 'Header layout. "custom" requires `custom_template` to be set to a Library template id.',
                'enum' => ['minimal', 'transparent', 'top', 'full_screen', 'center', 'medium', 'vertical', 'custom'],
            ],
            'height' => [
                'type' => 'integer',
                'description' => 'Header height in pixels.',
                'minimum' => 0,
                'maximum' => 800,
            ],
            'transparent' => [
                'type' => 'boolean',
                'description' => 'Make the page header transparent (most useful with `style: transparent`).',
            ],
            'custom_template' => [
                'type' => 'integer',
                'description' => 'Library (`oceanwp_library`) post id used as the custom header — only effective with `style: custom`. Requires Ocean Extra.',
                'minimum' => 0,
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
                'description' => 'The post-write value of every setting that was accepted.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['persisted', 'changed', 'unchanged', 'values'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_set_header_config',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Sharp header writer. Internally maps `style` -> `ocean_header_style`, `height` -> `ocean_header_height`, `transparent` -> `ocean_has_transparent_header`, `custom_template` -> `ocean_custom_header_template`. Use this instead of `oceanwp-set-customizer-settings` for header changes — the alias schema is tighter and shorter to discover.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Cross-field + Ocean-Extra-gating pre-checks for the header wrapper.
 * Returns null when the input is consistent, or a WP_Error describing the
 * first inconsistency. Extracted from `oceanwp_set_header_config` to keep
 * that function's cyclomatic complexity under the mago lint threshold.
 *
 * Audit round 1 references: M#9 (Ocean Extra gate on custom_template) and
 * M#10 (style=custom requires custom_template).
 *
 * @param array<string, mixed> $input
 */
function ow_header_consistency_check(array $input): ?WP_Error
{
    if (($input['style'] ?? null) === 'custom') {
        $candidate = array_key_exists('custom_template', $input)
            ? ow_coerce_int_or_null($input['custom_template'])
            : null;
        $existing = ow_coerce_int_or_null(ow_theme_mod('ocean_custom_header_template')) ?? 0;
        if (($candidate === null || $candidate <= 0) && $existing <= 0) {
            return ow_invalid_input(__(
                'Header style "custom" requires a `custom_template` Library template id. Either pass `custom_template` in this call, or set it separately first.',
                domain: 'novamira-pro',
            ));
        }
    }
    if (array_key_exists('custom_template', $input)) {
        $oe = ow_detect_ocean_extra();
        if (!$oe['active'] || !$oe['min_satisfied']) {
            return ow_requires_ocean_extra();
        }
    }
    return null;
}

/**
 * @param array{style?: string, height?: int, transparent?: bool, custom_template?: int} $input
 * @return array{
 *   persisted: bool,
 *   changed: list<string>,
 *   unchanged: list<string>,
 *   values: array<string, mixed>|\stdClass
 * }|WP_Error
 */
function oceanwp_set_header_config(array $input): array|WP_Error
{
    $detected = ow_detect_setup();
    if (!$detected['active'] || !$detected['min_satisfied']) {
        return ow_not_active();
    }
    $aliases = [
        'style' => 'ocean_header_style',
        'height' => 'ocean_header_height',
        'transparent' => 'ocean_has_transparent_header',
        'custom_template' => 'ocean_custom_header_template',
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
            'No header fields supplied. Pass at least one of: style, height, transparent, custom_template.',
            domain: 'novamira-pro',
        ));
    }

    $consistency = ow_header_consistency_check($input);
    if ($consistency !== null) {
        return $consistency;
    }

    // Pre-validate BEFORE delegating so a rejection on field N does not leave
    // fields 0..N-1 already persisted in theme_mods_oceanwp. See audit round
    // 1 HIGH finding. ow_format_alias_rejections aggregates every rejection
    // into a single message with the public aliases the caller actually used.
    $rejections = ow_prevalidate_customizer_writes($forwarded);
    if ($rejections !== []) {
        return ow_format_alias_rejections(
            $rejections,
            $alias_for,
            __('Header configuration rejected —', domain: 'novamira-pro'),
        );
    }

    $result = oceanwp_set_customizer_settings(['settings' => $forwarded]);
    if ($result instanceof WP_Error) {
        return $result;
    }
    // Surface aliases (`style`, `height`, `transparent`, `custom_template`)
    // rather than the internal `ocean_*` theme_mod names on every success-
    // path output. Audit round 2 M#7.
    return ow_translate_result_to_aliases($result, $alias_for);
}
