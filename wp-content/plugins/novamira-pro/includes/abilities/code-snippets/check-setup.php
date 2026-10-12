<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-check-setup', [
    'label' => __('Check Code Snippets Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns a snapshot of the Code Snippets install: which variant is active (free or Pro), version, whether the minimum supported release is satisfied, the list of snippet types available on this install (free = php/html; Pro adds css/js/cond), and counts of total / active snippets. Call this once at the start of any session that will read or write snippets.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plugin_active' => ['type' => 'boolean'],
            'is_pro' => ['type' => 'boolean'],
            'assets_runtime_available' => [
                'type' => 'boolean',
                'description' => 'True when css / js snippets actually execute on this install (Pro build whose licence has been activated). When false, css / js rows may still be stored and edited but never run.',
            ],
            'version' => ['type' => 'string'],
            'min_required' => ['type' => 'string'],
            'min_satisfied' => ['type' => 'boolean'],
            'available_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'pro_only_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Types that exist in the catalogue but only work on a Pro install. Compare against `available_types` to know which are usable here.',
            ],
            'snippets_total' => ['type' => 'integer'],
            'snippets_active' => ['type' => 'integer'],
        ],
        'required' => [
            'plugin_active',
            'is_pro',
            'assets_runtime_available',
            'version',
            'min_required',
            'min_satisfied',
            'available_types',
            'pro_only_types',
            'snippets_total',
            'snippets_active',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once at the start of a Code Snippets session. If `plugin_active=false` or `min_satisfied=false`, do not call any other code-snippets ability — surface the gap to the user. Use `is_pro` / `available_types` to decide which types can be created and edited here (css / js / cond need the Pro build). Use `assets_runtime_available` to decide whether css / js snippets will actually EXECUTE on this install — when it is false they can be stored but never run, so do not promise the user a visible effect. `available_types` is the authoritative list for this site.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function code_snippets_check_setup(): array
{
    if (!cs_runtime_available()) {
        return [
            'plugin_active' => false,
            'is_pro' => false,
            'assets_runtime_available' => false,
            'version' => cs_version(),
            'min_required' => NOVAMIRA_PRO_CODE_SNIPPETS_MIN_VERSION,
            'min_satisfied' => false,
            'available_types' => [],
            'pro_only_types' => ['css', 'js', 'cond'],
            'snippets_total' => 0,
            'snippets_active' => 0,
        ];
    }

    $snippets = \Code_Snippets\get_snippets();
    $active = 0;
    foreach ($snippets as $s) {
        if (!$s->active) {
            continue;
        }
        $active++;
    }

    return [
        'plugin_active' => true,
        'is_pro' => cs_is_pro(),
        'assets_runtime_available' => cs_assets_runtime_available(),
        'version' => cs_version(),
        'min_required' => NOVAMIRA_PRO_CODE_SNIPPETS_MIN_VERSION,
        'min_satisfied' => true,
        'available_types' => cs_available_types(),
        'pro_only_types' => ['css', 'js', 'cond'],
        'snippets_total' => count($snippets),
        'snippets_active' => $active,
    ];
}
