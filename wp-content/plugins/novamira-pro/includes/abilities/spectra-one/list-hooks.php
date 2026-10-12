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
 * Ability: enumerate Spectra One custom hooks (all `swt_*`).
 *
 * The theme exposes 11 filter hooks and zero action hooks (verified as of
 * 1.2.2). We serve them from a curated registry in helpers.php so audits
 * pin the surface at rest — new hooks introduced by a future theme
 * release become visible only after re-audit.
 */

wp_register_ability('novamira/spectra-one-list-hooks', [
    'label' => __('List Spectra One Hooks', domain: 'novamira-pro'),
    'description' => __(
        'Returns the 11 custom filter hooks the Spectra One theme exposes (prefix `swt_*`), each with name, type (all filters in 1.2.x), accepted parameters, default value, source file, and human-readable description. Optional `type` filter narrows the list to `action` (empty in 1.2.x), `filter`, or `all` (default). Read-only introspection — use before proposing customization snippets that hook into the theme.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'enum' => ['action', 'filter', 'all'],
                'default' => 'all',
                'description' => 'Filter by hook type. `all` (default) returns every hook.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'hooks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['action', 'filter']],
                        'parameters' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'default_value' => ['type' => 'string'],
                        'file' => ['type' => 'string'],
                        'description' => [
                            'type' => 'string',
                            'maxLength' => 200,
                            'description' => 'Human-readable hook description. Capped at 200 chars for progressive disclosure — the curated registry stays well under this ceiling; a future theme release that ships longer descriptions will need a re-audit.',
                        ],
                    ],
                    'required' => ['name', 'type', 'parameters', 'default_value', 'file', 'description'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer', 'minimum' => 0],
        ],
        'required' => ['hooks', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_list_hooks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Curated registry — reflects the surface documented against Spectra One 1.2.2. If a future theme release adds hooks, the theme-native `spectra-one/list-hooks` ability may surface them before this Novamira wrapper does.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type?: string} $input
 * @return array{hooks: list<array<string, mixed>>, total: int}|WP_Error
 */
function spectra_one_list_hooks(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $type_filter = $input['type'] ?? 'all';
    if (!in_array($type_filter, ['action', 'filter', 'all'], strict: true)) {
        return so_invalid_input(sprintf(
            /* translators: %s: invalid input value */
            __('Unknown hook type filter: %s', domain: 'novamira-pro'),
            $type_filter,
        ));
    }

    $hooks = so_hooks_registry();
    if ($type_filter !== 'all') {
        $hooks = array_values(array_filter($hooks, static fn(array $hook): bool => $hook['type'] === $type_filter));
    }

    return [
        'hooks' => $hooks,
        'total' => count($hooks),
    ];
}
