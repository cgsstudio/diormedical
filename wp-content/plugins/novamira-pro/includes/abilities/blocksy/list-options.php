<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/** Default page size for blocksy-list-options. */
const BLY_LIST_OPTIONS_DEFAULT_LIMIT = 50;

/** Hard page-size cap for blocksy-list-options. */
const BLY_LIST_OPTIONS_MAX_LIMIT = 200;

/** Overview label for options whose breadcrumb resolved empty. */
const BLY_ROOT_SECTION_LABEL = '(root)';

/**
 * Ability: progressive-disclosure listing of the Blocksy customizer options.
 */

wp_register_ability('novamira/blocksy-list-options', [
    'label' => __('List Blocksy Options', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates the Blocksy customizer option schema (the definitions under the theme\'s inc/options/, the same tree the customizer registers as theme_mods). Progressive disclosure: called with no filters it returns only the section overview ({section, count} rows); pass `section` and/or `search` to get actual option rows {id, label, type, section, storage}. `storage` says where the value lives — `theme_mod` (the theme_mods row, almost every option) or `option` (the id\'s own wp_options row, declared setting.type=option — e.g. the WooCommerce settings Blocksy re-exposes and wp_page_for_privacy_policy), the same field blocksy-get-option / blocksy-edit-option report. Rows are schema metadata only — read values (stored-or-default resolved) with blocksy-get-option. Extension-contributed options are included when Blocksy Companion is active.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'section' => [
                'type' => 'string',
                'description' => 'Filter to one TOP-LEVEL section name exactly as returned by the overview call (e.g. "General", "Colors", "Single Post"). Nested breadcrumb paths are not accepted; use "(root)" for options the overview groups under that bucket.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match against option id and label.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => BLY_LIST_OPTIONS_MAX_LIMIT,
                'default' => BLY_LIST_OPTIONS_DEFAULT_LIMIT,
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'sections' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'section' => ['type' => 'string'],
                        'count' => ['type' => 'integer'],
                    ],
                    'required' => ['section', 'count'],
                    'additionalProperties' => false,
                ],
                'description' => 'Top-level section overview. Populated on unfiltered calls; empty when section/search filters are active.',
            ],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'section' => ['type' => 'string'],
                        'storage' => [
                            'type' => 'string',
                            'enum' => [BLY_STORAGE_THEME_MOD, BLY_STORAGE_OPTION],
                            'description' => 'Where the value lives: `theme_mod` or `option` (own wp_options row) — consistent with blocksy-get-option / blocksy-edit-option.',
                        ],
                    ],
                    'required' => ['id', 'label', 'type', 'section', 'storage'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer', 'description' => 'Total matching options before pagination.'],
            'has_more' => ['type' => 'boolean'],
        ],
        'required' => ['sections', 'items', 'total', 'has_more'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_list_options',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discovery flow: 1) call with no input to get the section overview; 2) call with section=<name> (or search=<term>) to list the option ids in the area you need; 3) call blocksy-get-option id=<id> to read the effective value and defaults. Do not try to page through the whole catalogue — filter first, the full schema is ~800 options.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{section?: string, search?: string, limit?: int, offset?: int} $input
 * @return array{
 *     sections: list<array{section: string, count: int}>,
 *     items: list<array{id: string, label: string, type: string, section: string, storage: string}>,
 *     total: int,
 *     has_more: bool,
 * }|WP_Error
 */
function blocksy_list_options(array $input): array|WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }

    $section = isset($input['section']) ? trim($input['section']) : '';
    $search = isset($input['search']) ? trim($input['search']) : '';
    $limit = bly_list_clamp_int(
        $input['limit'] ?? BLY_LIST_OPTIONS_DEFAULT_LIMIT,
        fallback: BLY_LIST_OPTIONS_DEFAULT_LIMIT,
        min: 1,
        max: BLY_LIST_OPTIONS_MAX_LIMIT,
    );
    $offset = bly_list_clamp_int($input['offset'] ?? 0, fallback: 0, min: 0, max: PHP_INT_MAX);

    $index = bly_option_index_or_error();
    if ($index instanceof WP_Error) {
        return $index;
    }

    // Progressive-disclosure root: no filters → section overview only.
    if ($section === '' && $search === '') {
        return [
            'sections' => bly_section_overview($index),
            'items' => [],
            'total' => count($index),
            'has_more' => false,
        ];
    }

    // The overview labels breadcrumb-less options "(root)"; map the filter
    // back to the internal empty-section value so that bucket is reachable.
    $section_match = $section === BLY_ROOT_SECTION_LABEL ? '' : $section;

    $matches = [];
    foreach ($index as $row) {
        if ($section !== '' && bly_top_section($row['section']) !== $section_match) {
            continue;
        }
        if ($search !== '' && !bly_row_matches_search($row, $search)) {
            continue;
        }
        $matches[] = [
            'id' => $row['id'],
            'label' => $row['label'],
            'type' => $row['type'],
            'section' => $row['section'],
            'storage' => bly_option_storage($row),
        ];
    }

    $total = count($matches);
    $page = array_slice($matches, $offset, $limit);

    return [
        'sections' => [],
        'items' => array_values($page),
        'total' => $total,
        'has_more' => ($offset + $limit) < $total,
    ];
}

/**
 * Clamp a REST-supplied numeric input to int within [$min, $max]; non-numeric
 * values fall back to $fallback.
 */
function bly_list_clamp_int(mixed $value, int $fallback, int $min, int $max): int
{
    if (is_int($value)) {
        return max($min, min($max, $value));
    }
    if (is_string($value) && ctype_digit($value)) {
        return max($min, min($max, (int) $value));
    }
    return $fallback;
}

/**
 * The first breadcrumb element of a section path ('' stays '').
 */
function bly_top_section(string $section_path): string
{
    if ($section_path === '') {
        return '';
    }
    $parts = explode(' / ', $section_path);
    return $parts[0];
}

/**
 * Distinct top-level sections with option counts, in tree order.
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @return list<array{section: string, count: int}>
 */
function bly_section_overview(array $index): array
{
    $counts = [];
    foreach ($index as $row) {
        $top = bly_top_section($row['section']);
        if ($top === '') {
            $top = BLY_ROOT_SECTION_LABEL;
        }
        $counts[$top] = ($counts[$top] ?? 0) + 1;
    }
    $out = [];
    foreach ($counts as $name => $count) {
        $out[] = ['section' => $name, 'count' => $count];
    }
    return $out;
}

/**
 * Case-insensitive substring match against the row id and label.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 */
function bly_row_matches_search(array $row, string $search): bool
{
    return stripos($row['id'], $search) !== false || stripos($row['label'], $search) !== false;
}
