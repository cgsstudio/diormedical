<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-list-modules', [
    'label' => __('List Beaver Builder Modules', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Beaver Builder modules and aliases registered in this install. Rows are compact: slug (module or alias key), module (underlying module slug), name, category, group, enabled, alias, and description. Filter by category, group, search, and include_disabled. Use this before adding content so the agent picks real native modules instead of raw HTML. This is the catalog (slugs only); fetch a single module settings fields with get-module before writing its node settings.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'description' => 'Case-insensitive category filter, e.g. Basic, Media, Actions, Posts.',
            ],
            'group' => ['type' => 'string', 'description' => 'Case-insensitive module group filter, e.g. standard.'],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match against slug, module, name, category, and description.',
            ],
            'include_disabled' => [
                'type' => 'boolean',
                'description' => 'Include modules disabled in Beaver Builder settings. Defaults to false.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Maximum rows to return. Defaults to 50; hard cap 500.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'modules' => ['type' => 'array', 'items' => ['type' => 'object']],
            'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
            'groups' => ['type' => 'array', 'items' => ['type' => 'string']],
            'total' => ['type' => 'integer'],
            'catalogue_total' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_list_modules',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to discover the exact Beaver Builder module slug before building a page. Prefer native modules from this list; do not fake page sections, layout wrappers, CSS injection, visual styling, or content blocks with a raw HTML module unless the user explicitly asked for code.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function beaver_builder_list_modules(array $input): array
{
    $include_disabled = bb_bool_arg($input['include_disabled'] ?? false);
    $catalog = bb_module_catalog(scope: $include_disabled ? 'all' : 'enabled');
    $vocabulary = bb_module_vocabulary($catalog);
    $filters = bb_module_input_filters($input);
    $limit = bb_int_arg(input: $input, key: 'limit', default: BB_DEFAULT_LIST_LIMIT, min: 1, max: BB_MAX_LIST_LIMIT);
    $rows = bb_filter_module_rows(catalog: $catalog, filters: $filters);

    $total = count($rows);
    $truncated = $total > $limit;
    if ($truncated) {
        $rows = array_slice(array: $rows, offset: 0, length: $limit);
    }

    return [
        'modules' => $rows,
        'categories' => $vocabulary['categories'],
        'groups' => $vocabulary['groups'],
        'total' => $total,
        'catalogue_total' => count($catalog),
        'truncated' => $truncated,
    ];
}

/**
 * @param list<array<string, mixed>> $catalog
 * @return array{categories: list<string>, groups: list<string>}
 */
function bb_module_vocabulary(array $catalog): array
{
    /** @var array<string, true> $categories */
    $categories = [];
    /** @var array<string, true> $groups */
    $groups = [];
    foreach ($catalog as $row) {
        if (isset($row['category']) && is_string($row['category']) && $row['category'] !== '') {
            $categories[$row['category']] = true;
        }
        if (!isset($row['group']) || !is_string($row['group']) || $row['group'] === '') {
            continue;
        }
        foreach (explode(',', $row['group']) as $group) {
            if ($group === '') {
                continue;
            }
            $groups[$group] = true;
        }
    }
    return ['categories' => array_keys($categories), 'groups' => array_keys($groups)];
}

/**
 * @param array<string, mixed> $input
 * @return array{category: string, group: string, search: string}
 */
function bb_module_input_filters(array $input): array
{
    return [
        'category' => isset($input['category']) && is_string($input['category']) ? $input['category'] : '',
        'group' => isset($input['group']) && is_string($input['group']) ? $input['group'] : '',
        'search' => isset($input['search']) && is_string($input['search']) ? $input['search'] : '',
    ];
}

/**
 * @param list<array<string, mixed>> $catalog
 * @param array{category: string, group: string, search: string} $filters
 * @return list<array<string, mixed>>
 */
function bb_filter_module_rows(array $catalog, array $filters): array
{
    $rows = [];
    foreach ($catalog as $row) {
        if (!bb_module_row_matches(row: $row, filters: $filters)) {
            continue;
        }
        $rows[] = $row;
    }
    return $rows;
}

/**
 * @param array<string, mixed> $row
 * @param array{category: string, group: string, search: string} $filters
 */
function bb_module_row_matches(array $row, array $filters): bool
{
    if ($filters['category'] !== '' && strcasecmp((string) $row['category'], $filters['category']) !== 0) {
        return false;
    }
    if (
        $filters['group'] !== ''
        && stripos(',' . (string) $row['group'] . ',', ',' . $filters['group'] . ',') === false
    ) {
        return false;
    }
    if ($filters['search'] === '') {
        return true;
    }

    foreach (['slug', 'module', 'name', 'category', 'description'] as $key) {
        if (stripos((string) ($row[$key] ?? ''), $filters['search']) !== false) {
            return true;
        }
    }
    return false;
}
