<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

/**
 * Ability: List the available Divi 5 module types.
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_DIVI_LIST_MODULES_DEFAULT_LIMIT = 500;

wp_register_ability('novamira/divi-list-modules', [
    'label' => __('List Divi Modules', domain: 'novamira-pro'),
    'description' => __(
        'Lists the available Divi module types from the module registry. Returns compact rows: name, title, category ("structure" | "module" | "child-module" | "fullwidth-module"), plus loop_capable (true when the module accepts a query loop via novamira/divi-enable-loop — note divi/blog runs its own query and is not flagged), raw_html (only on the code modules) and children (only on containers that host named child modules). Use novamira/divi-get-module-schema for a single module\'s settable fields. Nesting follows Divi\'s own rule, which reads the parent\'s ATTRIBUTES as well as its name: the root takes divi/section; a regular section takes divi/row > divi/column > content and fullwidth modules, and an ordinary divi/column also takes divi/group or a nested divi/row; a fullwidth section (module.advanced.type = "fullwidth") takes content and fullwidth modules directly, with no row; a column marked module.advanced.specialtyColumns takes divi/row-inner > divi/column-inner; divi/column-inner takes content and fullwidth modules plus divi/group; divi/group takes content and fullwidth modules, another divi/group or a divi/row. A divi/post-filter-item stays inside a divi/post-filter: as its direct child or, where the site\'s Divi accepts it, deeper inside a module nested in the filter that takes content modules (e.g. post-filter > text > post-filter-item); a subtree holding such items cannot be moved out of its filter unless the destination is inside another post filter. A placement Divi rejects is refused, except a shape earlier content may already carry, which is saved with a warning. A SPECIALTY section (module.advanced.type = "specialty") is not supported here: Divi lays it out with direct divi/column children whose rendered widths depend on effective sizing these abilities cannot resolve. Placing a divi/column DIRECTLY in one is refused by set-content, which validates the whole tree you supply, and by add-module, move-module and non-Global apply-library-item, which validate the inserted subtree against its immediate destination. Those checks see the immediate parent only, so no other path guarantees the specialty ancestor is examined: applying a GLOBAL library item skips that validation entirely, and edits to the column itself, deletes, moving it out, the loop / dynamic-content / preset / interaction / display-condition writers and global variable edits may all succeed with no specialty-related warning. What does still fire: an insert is graded against its own immediate parent, so an illegal child there is refused and a tolerated one warns, and an attrs write that changes a section type or a column\'s inner-row flag is still reported. A divi/row in a specialty section saves with a warning. Rule of thumb: a page containing a specialty section is not safe to edit through these abilities — use the Visual Builder. Modules flagged raw_html (divi/code, divi/fullwidth-code) hold raw HTML and should be avoided in favor of building native modules. Filter by category or a name/title search to keep the response small.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'enum' => ['structure', 'module', 'child-module', 'fullwidth-module'],
                'description' => 'Restrict to one module category.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring matched against the module name and title.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NOVAMIRA_DIVI_LIST_MODULES_DEFAULT_LIMIT,
                'description' => 'Maximum number of modules to return (default and max 500).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'modules' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
            ],
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_modules',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover module types before building. Respect nesting, which depends on the parent\'s attrs as well as its name: root > divi/section > divi/row > divi/column > content and fullwidth modules, and an ordinary column also takes divi/group or a nested divi/row; a fullwidth section (module.advanced.type = "fullwidth") holds content and fullwidth modules directly with no row; a column carrying module.advanced.specialtyColumns holds divi/row-inner > divi/column-inner. A specialty section (type = "specialty") is not supported: set-content, add-module, move-module and non-Global apply-library-item refuse a divi/column placed directly in one, but they check the immediate parent only, so no other path guarantees the specialty ancestor is seen and many may succeed without a specialty-related warning. Rule of thumb: a page containing a specialty section is not safe to edit here — use the Visual Builder. Prefer native modules over the raw_html-flagged code modules.',
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
function divi_list_modules(array $input): array
{
    $category = is_string($input['category'] ?? null) ? $input['category'] : null;
    $search = is_string($input['search'] ?? null) ? strtolower(trim($input['search'])) : '';
    // Accept an int or a numeric string ("3") alike — the schema's integer type
    // lets WP coerce numeric strings through, so honor them here too.
    $requested = divi_int_arg_or_null($input, key: 'limit');
    $limit = $requested !== null
        ? max(1, min($requested, NOVAMIRA_DIVI_LIST_MODULES_DEFAULT_LIMIT))
        : NOVAMIRA_DIVI_LIST_MODULES_DEFAULT_LIMIT;

    $rows = [];
    foreach (divi_all_modules_metadata() as $meta) {
        $summary = divi_module_summary($meta);

        if ($category !== null && $summary['category'] !== $category) {
            continue;
        }
        if ($search !== '') {
            $haystack = strtolower((string) $summary['name'] . ' ' . (string) $summary['title']);
            if (!str_contains($haystack, $search)) {
                continue;
            }
        }

        $rows[] = $summary;
    }

    $total = count($rows);
    $returned = array_slice($rows, offset: 0, length: $limit);

    return [
        'modules' => $returned,
        'total' => $total,
        'returned' => count($returned),
        'truncated' => $total > count($returned),
    ];
}
