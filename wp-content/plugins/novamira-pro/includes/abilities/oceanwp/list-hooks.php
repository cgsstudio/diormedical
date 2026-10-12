<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-list-hooks', [
    'label' => __('List OceanWP Render Hooks', domain: 'novamira-pro'),
    'description' => __(
        'Returns the agent-curated catalogue of `ocean_*` action and filter hooks the theme exposes at render time (layout wrap, header, top bar, page header, main, content, footer, scroll-up). Use the returned hook names with `novamira/execute-php` snippets: `add_action()` to inject HTML/template at action hooks, `add_filter()` (often with `__return_false` / `__return_true`) to suppress or override layout regions. The full ~470 `ocean_*` hook surface lives in the theme source — this catalogue is the high-leverage subset.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'group' => [
                'type' => 'string',
                'description' => 'Filter to one hook group (layout, header, topbar, page, main, footer, general). Omit to return the full catalogue.',
                'enum' => ['layout', 'header', 'topbar', 'page', 'main', 'footer', 'general'],
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Filter by hook type — `action` (for `add_action`) or `filter` (for `add_filter`). Omit to return both.',
                'enum' => ['action', 'filter'],
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer', 'description' => 'Total hooks in the catalogue (before filtering).'],
            'returned' => ['type' => 'integer', 'description' => 'Hooks actually returned (after filtering).'],
            'groups' => [
                'type' => 'array',
                'description' => 'Distinct group values present in the catalogue (handy for follow-up filtered calls).',
                'items' => ['type' => 'string'],
            ],
            'hooks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Exact hook name as passed to add_action/add_filter.',
                        ],
                        'type' => ['type' => 'string', 'enum' => ['action', 'filter']],
                        'group' => ['type' => 'string', 'description' => 'Render region grouping.'],
                        'description' => ['type' => 'string', 'description' => 'One-line behavior summary.'],
                    ],
                    'required' => ['name', 'type', 'group', 'description'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'returned', 'groups', 'hooks'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_list_hooks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Static catalogue. Use the returned hook names directly with `novamira/execute-php` (e.g. add_filter("ocean_display_top_bar", "__return_false")). For hooks NOT in the catalogue, grep the theme source — they exist but were not promoted into the agent vocabulary.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{group?: string, type?: string} $input
 * @return array{
 *   total: int,
 *   returned: int,
 *   groups: list<string>,
 *   hooks: list<array{name: string, type: string, group: string, description: string}>
 * }
 */
function oceanwp_list_hooks(array $input): array
{
    $catalogue = ow_hook_catalog();
    $group_filter = ow_coerce_string_or_null($input['group'] ?? null);
    $type_filter = ow_coerce_string_or_null($input['type'] ?? null);

    $hooks = [];
    $groups = [];
    foreach ($catalogue as $entry) {
        if (!in_array($entry['group'], $groups, strict: true)) {
            $groups[] = $entry['group'];
        }
        if ($group_filter !== null && $entry['group'] !== $group_filter) {
            continue;
        }
        if ($type_filter !== null && $entry['type'] !== $type_filter) {
            continue;
        }
        $hooks[] = $entry;
    }

    return [
        'total' => count($catalogue),
        'returned' => count($hooks),
        'groups' => $groups,
        'hooks' => $hooks,
    ];
}
