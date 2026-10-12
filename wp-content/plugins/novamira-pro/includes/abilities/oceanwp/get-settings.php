<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-get-settings', [
    'label' => __('Get OceanWP Customizer Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads the live value of OceanWP Customizer settings from the agent-curated allowlist. By default returns the full allowlist; pass `panel` to filter to one subdomain or `settings: [...]` to fetch specific keys. Typography settings (font-family / font-weight / font-size / line-height / letter-spacing) are returned as decoded objects. Settings outside the allowlist are intentionally NOT returned by this ability — for ad-hoc reads of OceanWP theme_mods use `novamira/execute-php` with `get_theme_mod( "ocean_<key>" )`.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'panel' => [
                'type' => 'string',
                'description' => 'Panel id from `oceanwp-list-customizer-panels`. When set, only settings owned by that panel are returned. Mutually exclusive with `settings`.',
            ],
            'settings' => [
                'type' => 'array',
                'description' => 'Explicit list of allowlist setting names to read. Mutually exclusive with `panel`. Unknown setting names are dropped silently and reported in the `unknown` field.',
                'items' => ['type' => 'string'],
                'maxItems' => 200,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total_allowlisted' => [
                'type' => 'integer',
                'description' => 'Total settings in the agent-curated allowlist.',
            ],
            'returned' => ['type' => 'integer', 'description' => 'Number of settings actually returned.'],
            'unknown' => [
                'type' => 'array',
                'description' => 'Settings requested via `settings` that were not found in the allowlist.',
                'items' => ['type' => 'string'],
            ],
            'settings' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'panel' => ['type' => 'string'],
                        'type' => [
                            'type' => 'string',
                            'enum' => ['boolean', 'integer', 'string', 'enum', 'color', 'typography'],
                        ],
                        'value' => [
                            'type' => ['boolean', 'integer', 'string', 'array', 'object', 'null'],
                            'description' => 'Current value (theme_mod with OceanWP default fallback). Scalar for boolean/integer/string/enum/color; object for typography (JSON Schema "object" — associative); null when the setting has never been set and OceanWP has no default for it.',
                        ],
                    ],
                    'required' => ['name', 'panel', 'type', 'value'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total_allowlisted', 'returned', 'unknown', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Progressive disclosure: when neither `panel` nor `settings` is set, returns ALL allowlisted settings (compact). Set `panel` to filter to one subdomain, or pass `settings: [...]` for specific keys. The allowlist intentionally curates ~35 high-leverage settings out of OceanWP\'s ~1000 — use `novamira/execute-php` for any setting outside it.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{panel?: string, settings?: list<string>} $input
 * @return array{
 *   total_allowlisted: int,
 *   returned: int,
 *   unknown: list<string>,
 *   settings: list<array{name: string, panel: string, type: string, value: mixed}>
 * }|WP_Error
 */
function oceanwp_get_settings(array $input): array|WP_Error
{
    if (!\Novamira\Pro\Abilities\OceanWP\oceanwp_theme_available()) {
        return ow_not_active();
    }

    $allowlist = ow_customizer_allowlist();
    $panel = ow_coerce_string_or_null($input['panel'] ?? null);
    $requested = $input['settings'] ?? [];

    // Mutual exclusion is advertised in the input_schema description but the
    // WP abilities API has no built-in way to express dependentSchemas/oneOf
    // on optional siblings, so enforce it here. See audit round 1 M#1.
    if ($panel !== null && $requested !== []) {
        return ow_invalid_input(__(
            '`panel` and `settings` are mutually exclusive — pass only one.',
            domain: 'novamira-pro',
        ));
    }

    // Reject unknown panel ids with a useful diagnostic listing valid panel
    // names. Silent-empty-result was the previous behaviour and is impossible
    // to distinguish from "this panel legitimately has no allowlisted
    // settings". See audit round 1 M#3.
    if ($panel !== null) {
        $panel_catalog = ow_customizer_panel_catalog();
        if (!isset($panel_catalog[$panel])) {
            return ow_invalid_input(sprintf(
                /* translators: 1: the panel id passed by the caller, 2: comma-separated list of valid panels */
                __(
                    'Unknown panel "%1$s". Call `oceanwp-list-customizer-panels` to discover valid panel ids. Currently supported: %2$s.',
                    domain: 'novamira-pro',
                ),
                $panel,
                implode(', ', array_keys($panel_catalog)),
            ));
        }
    }

    [$names, $unknown] = ow_resolve_setting_query($requested, $panel, $allowlist);

    $settings = [];
    foreach ($names as $name) {
        $meta = $allowlist[$name];
        $value = $meta['type'] === 'typography' ? ow_theme_mod_typography($name) : ow_theme_mod($name);
        // Cap string field bytes at OW_FIELD_BYTES_DEFAULT (4 KiB) so a long
        // free-form value (e.g. ocean_footer_copyright_text containing HTML)
        // does not balloon the agent's context window on a `no-args` call.
        // See audit round 1 M#7. Callers who explicitly want the full value
        // for a single key can use `novamira/execute-php` with get_theme_mod.
        if (is_string($value)) {
            $value = ow_truncate_field($value, OW_FIELD_BYTES_DEFAULT);
        }
        $settings[] = [
            'name' => $name,
            'panel' => $meta['panel'],
            'type' => $meta['type'],
            'value' => $value,
        ];
    }

    return [
        'total_allowlisted' => count($allowlist),
        'returned' => count($settings),
        'unknown' => $unknown,
        'settings' => $settings,
    ];
}

/**
 * Resolves which allowlist setting names to read, given the input query.
 * Returns a tuple of [names, unknown]. `unknown` is populated only when the
 * caller passed an explicit `settings` list — panel-filtering mode never
 * surfaces unknown names because the source is the allowlist itself.
 *
 * @param list<string> $requested
 * @param array<string, array{type: string, panel: string, description: string, enum?: list<string>}> $allowlist
 * @return array{0: list<string>, 1: list<string>}
 */
function ow_resolve_setting_query(array $requested, ?string $panel, array $allowlist): array
{
    if ($requested !== []) {
        $names = [];
        $unknown = [];
        foreach ($requested as $name) {
            if ($name === '') {
                continue;
            }
            if (!isset($allowlist[$name])) {
                $unknown[] = $name;
                continue;
            }
            $names[] = $name;
        }
        return [$names, $unknown];
    }
    $names = [];
    foreach ($allowlist as $name => $meta) {
        if ($panel !== null && $meta['panel'] !== $panel) {
            continue;
        }
        $names[] = $name;
    }
    return [$names, []];
}
