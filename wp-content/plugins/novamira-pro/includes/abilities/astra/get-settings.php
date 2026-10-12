<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read keys from the `astra-settings` option bag.
 */

wp_register_ability('novamira/astra-get-settings', [
    'label' => __('Get Astra Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads keys from the `astra-settings` option bag via the canonical `astra_get_option()` accessor (defaults + filters applied). Pass `keys` to pull only what you need and keep responses lean. Omit `keys` for a compact subset of ~30 common keys (layout, color, typography basics) or set `mode=full` for the complete bag. Unknown or non-allowlisted keys are returned in `unknown_keys` — write still reads the allowlist subset. Call astra-get-defaults to discover valid key names and their expected types.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Setting keys to read. RECOMMENDED — pull only the keys you need. Leave empty to use the `mode` default.',
            ],
            'include_defaults' => [
                'type' => 'boolean',
                'description' => 'When true, return values for every requested key even when the key is not explicitly stored (resolved to the Astra default). When false (default), only keys present in the stored `astra-settings` option are returned.',
                'default' => false,
            ],
            'mode' => [
                'type' => 'string',
                'enum' => ['compact', 'full'],
                'description' => 'Applies when `keys` is omitted. `compact` (default) returns ~30 common keys. `full` returns the complete merged settings bag — heavy on large installs.',
                'default' => 'compact',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'values' => [
                'type' => 'object',
                'description' => 'Map of key -> value. Values longer than 2 048 bytes are truncated with a suffix.',
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys from `keys` that are not in the integration allowlist.',
            ],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['values', 'unknown_keys'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Prefer the `keys` whitelist for routine reads — omitting it in `compact` mode returns ~30 keys which is still lean; `full` mode can return 100+ keys and inflates context. Common shortcuts: ["site-layout","content-layout","sidebar-layout"] for layout, ["theme-color","text-color","link-color"] for colors, ["body-font-family","body-font-size"] for typography.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{keys?: list<string>, include_defaults?: bool, mode?: string} $input
 * @return array{values: array<array-key, mixed>|\stdClass, unknown_keys: list<string>, warnings?: list<string>}
 */
function astra_get_settings(array $input): array
{
    $allowlist = astra_global_settings_allowlist();
    $include_defaults = astra_coerce_bool($input['include_defaults'] ?? false);
    $mode = $input['mode'] ?? 'compact';

    // --- Branch: keys list supplied by caller ---
    $requested_keys = astra_coerce_array($input['keys'] ?? null);
    if ($requested_keys !== null) {
        if ($include_defaults) {
            // @mago-expect analysis:possibly-invalid-argument
            return astra_gs_read_keys_with_defaults($requested_keys, $allowlist);
        }
        // @mago-expect analysis:possibly-invalid-argument
        return astra_gs_read_keys_stored($requested_keys, $allowlist);
    }

    // --- Branch: no keys — use mode ---
    if ($mode === 'full') {
        return astra_gs_read_full();
    }

    // compact: return the curated 30-key subset
    return $include_defaults
        ? astra_gs_read_compact_with_defaults($allowlist)
        : astra_gs_read_compact_stored($allowlist);
}

/**
 * Read a specific list of allowlisted keys — only values that are explicitly
 * stored in `astra-settings` (no defaults applied).
 *
 * @param list<mixed>           $requested_keys
 * @param array<string, string> $allowlist
 * @return array{values: array<array-key, mixed>|\stdClass, unknown_keys: list<string>}
 */
function astra_gs_read_keys_stored(array $requested_keys, array $allowlist): array
{
    /** @var mixed $raw_settings */
    $raw_settings = get_option('astra-settings', []);
    $raw_map = is_array($raw_settings) ? $raw_settings : [];

    $values = [];
    $unknown_keys = [];

    // @mago-expect analysis:mixed-assignment
    foreach ($requested_keys as $raw_key) {
        if (!is_string($raw_key)) {
            continue;
        }
        $key = astra_strip_nul_bytes($raw_key);
        if (!array_key_exists($key, $allowlist)) {
            $unknown_keys[] = $key;
            continue;
        }
        if (!array_key_exists($key, $raw_map)) {
            continue;
        }
        $values[$key] = astra_cap_bytes($raw_map[$key]);
    }

    return ['values' => astra_object_or_map($values), 'unknown_keys' => $unknown_keys];
}

/**
 * Read a specific list of allowlisted keys — values resolved through
 * `astra_read_setting()` so Astra defaults are applied for unset keys.
 *
 * @param list<mixed>           $requested_keys
 * @param array<string, string> $allowlist
 * @return array{values: array<array-key, mixed>|\stdClass, unknown_keys: list<string>}
 */
function astra_gs_read_keys_with_defaults(array $requested_keys, array $allowlist): array
{
    $values = [];
    $unknown_keys = [];

    // @mago-expect analysis:mixed-assignment
    foreach ($requested_keys as $raw_key) {
        if (!is_string($raw_key)) {
            continue;
        }
        $key = astra_strip_nul_bytes($raw_key);
        if (!array_key_exists($key, $allowlist)) {
            $unknown_keys[] = $key;
            continue;
        }
        $values[$key] = astra_cap_bytes(astra_read_setting($key));
    }

    return ['values' => astra_object_or_map($values), 'unknown_keys' => $unknown_keys];
}

/**
 * Return the full merged settings bag (astra_get_options) with per-value byte cap.
 *
 * @return array{values: array<array-key, mixed>|\stdClass, unknown_keys: list<string>}
 */
function astra_gs_read_full(): array
{
    $all = astra_read_settings_snapshot();
    $values = [];
    /** @var mixed $v */
    foreach ($all as $k => $v) {
        $values[$k] = astra_cap_bytes($v);
    }
    return ['values' => astra_object_or_map($values), 'unknown_keys' => []];
}

/**
 * The ~30 layout/color/typography keys that form the compact snapshot.
 *
 * @return list<string>
 */
function astra_gs_compact_keys(): array
{
    return [
        'site-layout',
        'content-layout',
        'site-content-layout',
        'sidebar-layout',
        'content-width',
        'theme-color',
        'text-color',
        'link-color',
        'headings-color',
        'background-color',
        'body-font-family',
        'body-font-size',
        'body-font-weight',
        'body-line-height',
        'font-family-heading',
        'font-family-h1',
        'font-size-h1',
        'font-family-h2',
        'font-size-h2',
        'font-family-h3',
        'font-size-h3',
        'header-main-layout',
        'header-bg-color',
        'site-title-color',
        'display-site-title',
        'footer-bg-color',
        'footer-color',
        'footer-layout',
        'button-bg-color',
        'button-color',
    ];
}

/**
 * Return the compact subset — only values explicitly stored in `astra-settings`.
 *
 * @param array<string, string> $allowlist
 * @return array{values: array<array-key, mixed>|\stdClass, unknown_keys: list<string>}
 */
function astra_gs_read_compact_stored(array $allowlist): array
{
    /** @var mixed $raw_settings */
    $raw_settings = get_option('astra-settings', []);
    $raw_map = is_array($raw_settings) ? $raw_settings : [];

    $values = [];
    foreach (astra_gs_compact_keys() as $key) {
        if (!array_key_exists($key, $allowlist)) {
            continue;
        }
        if (!array_key_exists($key, $raw_map)) {
            continue;
        }
        $values[$key] = astra_cap_bytes($raw_map[$key]);
    }

    return ['values' => astra_object_or_map($values), 'unknown_keys' => []];
}

/**
 * Return the compact subset — values resolved through `astra_read_setting()`
 * so Astra defaults are applied for unset keys.
 *
 * @param array<string, string> $allowlist
 * @return array{values: array<array-key, mixed>|\stdClass, unknown_keys: list<string>}
 */
function astra_gs_read_compact_with_defaults(array $allowlist): array
{
    $values = [];
    foreach (astra_gs_compact_keys() as $key) {
        if (!array_key_exists($key, $allowlist)) {
            continue;
        }
        $values[$key] = astra_cap_bytes(astra_read_setting($key));
    }

    return ['values' => astra_object_or_map($values), 'unknown_keys' => []];
}
