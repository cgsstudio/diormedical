<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-breakpoints', [
    'label' => __('List Bricks Breakpoints', domain: 'novamira-pro'),
    'description' => __(
        'Lists the breakpoints in effect on this site (Bricks defaults, or the custom set when custom breakpoints are enabled): key, label, width in px, which one is the base, and the exact settings suffix to use per breakpoint. Responsive values are written as `<key>:<breakpoint>` (e.g. `_padding:tablet_portrait`); the base breakpoint takes the plain key with no suffix. Read this before writing any responsive setting instead of assuming widths.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'breakpoints' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'width' => [
                            'type' => 'integer',
                            'description' => 'Max-width (desktop-first) or min-width (mobile-first) in px for the media query.',
                        ],
                        'base' => [
                            'type' => 'boolean',
                            'description' => 'True for the base breakpoint: its values are written without a suffix.',
                        ],
                        'settings_suffix' => [
                            'type' => 'string',
                            'description' => 'Empty for the base breakpoint, otherwise ":<key>" to append to a setting key.',
                        ],
                        'custom' => [
                            'type' => 'boolean',
                            'description' => 'True for a breakpoint added in Bricks > Settings (custom set).',
                        ],
                    ],
                ],
            ],
            'base_key' => ['type' => 'string'],
            'mobile_first' => [
                'type' => 'boolean',
                'description' => 'Whether the site is set up mobile-first: the narrowest width is the base and the other breakpoints apply from their width upwards (min-width rules).',
            ],
            'custom_breakpoints_enabled' => [
                'type' => 'boolean',
                'description' => 'Whether the site uses its own breakpoint set instead of the Bricks defaults.',
            ],
            'example' => [
                'type' => 'string',
                'description' => 'One responsive settings key written for the first non-base breakpoint.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_breakpoints',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The suffix is the breakpoint key, not its width: `_padding:mobile_landscape`, never `_padding:767`. Every element control and every global-class setting accepts the same suffixes; theme styles do not. When custom breakpoints are enabled the keys can differ from the defaults, so never hard-code tablet_portrait / mobile_landscape / mobile_portrait without reading this list first.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_list_breakpoints(array $input): array
{
    $rows = bricks_ensure_base_row(array_map(static fn(array $breakpoint): array => bricks_breakpoint_row(
        $breakpoint,
    ), bricks_effective_breakpoints()));
    $base_key = 'desktop';
    $first_non_base = null;
    foreach ($rows as $row) {
        if ($row['base']) {
            $base_key = $row['key'];
            continue;
        }
        $first_non_base ??= $row['key'];
    }

    return [
        'breakpoints' => $rows,
        'base_key' => $base_key,
        'mobile_first' => \Bricks\Breakpoints::$is_mobile_first === true,
        'custom_breakpoints_enabled' => bricks_custom_breakpoints_enabled(),
        'example' => $first_non_base === null ? '_padding' : '_padding:' . $first_non_base,
    ];
}

/**
 * One response row for a stored breakpoint entry.
 *
 * @param array<string, mixed> $breakpoint
 * @return array{key: string, label: string, width: int, base: bool, settings_suffix: string, custom: bool}
 */
function bricks_breakpoint_row(array $breakpoint): array
{
    $key = (string) ($breakpoint['key'] ?? '');
    $is_base = ($breakpoint['base'] ?? false) !== false;

    return [
        'key' => $key,
        'label' => (string) ($breakpoint['label'] ?? $key),
        'width' => (int) ($breakpoint['width'] ?? 0),
        'base' => $is_base,
        'settings_suffix' => $is_base ? '' : ':' . $key,
        'custom' => ($breakpoint['custom'] ?? false) !== false,
    ];
}

/**
 * Bricks falls back to "desktop" as the base when no entry is flagged; the
 * rows say so explicitly.
 *
 * @param list<array{key: string, label: string, width: int, base: bool, settings_suffix: string, custom: bool}> $rows
 * @return list<array{key: string, label: string, width: int, base: bool, settings_suffix: string, custom: bool}>
 */
function bricks_ensure_base_row(array $rows): array
{
    if (in_array(true, array_column($rows, 'base'), strict: true)) {
        return $rows;
    }
    foreach ($rows as $index => $row) {
        if ($row['key'] !== 'desktop') {
            continue;
        }
        $rows[$index]['base'] = true;
        $rows[$index]['settings_suffix'] = '';
    }

    return $rows;
}

/**
 * The breakpoint list Bricks applies: its runtime list when initialised,
 * otherwise the stored custom set (when enabled) or the Bricks defaults.
 *
 * @return list<array<string, mixed>>
 */
function bricks_effective_breakpoints(): array
{
    /** @var mixed $runtime */
    $runtime = \Bricks\Breakpoints::$breakpoints;
    if (is_array($runtime) && $runtime !== []) {
        return bricks_breakpoint_rows($runtime);
    }
    if (bricks_custom_breakpoints_enabled()) {
        /** @var mixed $stored */
        $stored = get_option(BRICKS_DB_BREAKPOINTS, default_value: false);
        if (is_array($stored) && $stored !== []) {
            return bricks_breakpoint_rows($stored);
        }
    }
    if (method_exists(\Bricks\Breakpoints::class, 'get_default_breakpoints')) {
        /** @var mixed $defaults */
        $defaults = \Bricks\Breakpoints::get_default_breakpoints();
        if (is_array($defaults)) {
            return bricks_breakpoint_rows($defaults);
        }
    }

    return [];
}

/**
 * Whether the site uses its own breakpoint set (the `customBreakpoints`
 * global setting), which is when the stored breakpoints option applies.
 */
function bricks_custom_breakpoints_enabled(): bool
{
    /** @var mixed $flag */
    $flag = \Bricks\Database::get_setting('customBreakpoints', false);

    return $flag !== false && $flag !== '' && $flag !== null;
}

/**
 * @param array<array-key, mixed> $raw
 * @return list<array<string, mixed>>
 */
function bricks_breakpoint_rows(array $raw): array
{
    $rows = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_array($entry) || !is_string($entry['key'] ?? null)) {
            continue;
        }
        $rows[] = bricks_string_keyed($entry);
    }

    return $rows;
}

/**
 * @param array<array-key, mixed> $entry
 * @return array<string, mixed>
 */
function bricks_string_keyed(array $entry): array
{
    /** @var array<string, mixed> $row */
    $row = [];
    /** @var mixed $value */
    foreach ($entry as $field => $value) {
        $row[(string) $field] = $value;
    }

    return $row;
}
