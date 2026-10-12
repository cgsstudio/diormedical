<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-list-hooks', [
    'label' => __('List GeneratePress Hooks', domain: 'novamira-pro'),
    'description' => __(
        'Lists the GeneratePress action hooks available as injection points for Hook Elements (Group B). When GP Premium Elements is active the catalogue comes from `GeneratePress_Elements_Helper::get_available_hooks()` — the same list the admin UI uses, grouped by area (header, navigation, content, footer, woocommerce, …). When Elements is not active the response falls back to a small set of the core theme hooks an agent might still find useful as a reference. Use `group` to narrow the response.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'group' => [
                'type' => 'string',
                'description' => 'Filter to hooks in a single group (e.g. "header", "footer", "navigation", "content", "woocommerce"). The set of groups depends on which Premium modules are active — omit to receive every group.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'groups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'group' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'hooks' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'label' => ['type' => 'string'],
                                ],
                                'required' => ['name', 'label'],
                            ],
                        ],
                    ],
                    'required' => ['group', 'label', 'hooks'],
                ],
            ],
            'source' => [
                'type' => 'string',
                'enum' => ['gp_premium_elements', 'fallback_theme_hooks'],
                'description' => 'Where the hook list came from — `gp_premium_elements` when GP Premium Elements is active (authoritative), `fallback_theme_hooks` otherwise (a small hand-curated subset of theme core hooks).',
            ],
        ],
        'required' => ['groups', 'source'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_list_hooks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this before creating a Hook Element (Group B) so the agent picks a valid hook name. The `group` filter is the cheapest way to keep the response small. When the source is `fallback_theme_hooks`, GP Premium is not active — surface that to the user and stop, since Hook Elements (the consumer of this list) require Premium.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{group?: string} $input
 * @return array<string, mixed>
 */
function generatepress_list_hooks(array $input): array
{
    $group_filter = $input['group'] ?? '';

    $premium = gp_load_available_hooks();
    if ($premium !== []) {
        $groups = gp_reshape_premium_hooks($premium, $group_filter);
        return ['groups' => $groups, 'source' => 'gp_premium_elements'];
    }

    return [
        'groups' => gp_fallback_theme_hooks($group_filter),
        'source' => 'fallback_theme_hooks',
    ];
}

/**
 * Normalise one row of a `hooks` array into `[name, label]`. GP Premium
 * stores it as a numerically-indexed list (`['wp_head', …]`) but older
 * releases shipped a `[hook_name => label]` map — accept both.
 *
 * @param int|string $key
 * @param mixed $value
 * @return array{0: string, 1: string}
 */
function gp_normalise_hook_row($key, $value): array
{
    if (is_int($key)) {
        $name = is_string($value) ? $value : '';
        return [$name, $name];
    }
    $name = $key;
    $label = is_string($value) && $value !== '' ? $value : $name;
    return [$name, $label];
}

/**
 * GP Premium ships hooks as a map of group key -> ['name' => label, 'hooks' => [hook_name => hook_label]].
 * Reshape into the public array shape the ability returns.
 *
 * @param array<string, array<string, mixed>> $premium
 * @return list<array<string, mixed>>
 */
function gp_reshape_premium_hooks(array $premium, string $group_filter): array
{
    $out = [];
    foreach ($premium as $group_key => $group_data) {
        if ($group_filter !== '' && $group_key !== $group_filter) {
            continue;
        }
        // GP Premium's `GeneratePress_Elements_Helper::get_available_hooks`
        // returns each group's `hooks` field as a numerically-indexed list
        // of action names (`['wp_head', 'wp_body_open', …]`). Reshape with
        // the action name as both `name` and `label`. Also tolerate the
        // legacy `[hook_name => label]` map shape in case a future GP
        // release changes the layout.
        $hooks = is_array($group_data['hooks'] ?? null) ? $group_data['hooks'] : [];
        $reshape = [];
        /** @var mixed $value */
        foreach ($hooks as $key => $value) {
            [$name, $label] = gp_normalise_hook_row($key, $value);
            if ($name === '') {
                continue;
            }
            $reshape[] = ['name' => $name, 'label' => $label];
        }
        $out[] = [
            'group' => $group_key,
            'label' => (string) ($group_data['name'] ?? $group_key),
            'hooks' => $reshape,
        ];
    }
    return $out;
}

/**
 * Small fallback list of well-known theme action hooks for sites that
 * don't have GP Premium Elements active. Not exhaustive — meant to give
 * an agent a starting point so it can warn the user that Premium is
 * needed for the full surface.
 *
 * @return list<array<string, mixed>>
 */
function gp_fallback_theme_hooks(string $group_filter): array
{
    $groups = [
        'header' => [
            'label' => 'Header',
            'hooks' => [
                'generate_before_header' => 'Before Header',
                'generate_header' => 'Header',
                'generate_after_header' => 'After Header',
            ],
        ],
        'content' => [
            'label' => 'Content',
            'hooks' => [
                'generate_before_content' => 'Before Content',
                'generate_after_content' => 'After Content',
                'generate_before_entry_title' => 'Before Entry Title',
                'generate_after_entry_title' => 'After Entry Title',
            ],
        ],
        'footer' => [
            'label' => 'Footer',
            'hooks' => [
                'generate_before_footer' => 'Before Footer',
                'generate_footer' => 'Footer',
                'generate_after_footer' => 'After Footer',
            ],
        ],
    ];

    $out = [];
    foreach ($groups as $key => $data) {
        if ($group_filter !== '' && $key !== $group_filter) {
            continue;
        }
        $hooks = [];
        foreach ($data['hooks'] as $name => $label) {
            $hooks[] = ['name' => $name, 'label' => $label];
        }
        $out[] = [
            'group' => $key,
            'label' => $data['label'],
            'hooks' => $hooks,
        ];
    }
    return $out;
}
