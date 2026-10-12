<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicShortcodes;

use DynamicShortcodes\Plugin;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-shortcodes-list-power-shortcodes', [
    'label' => __('List Power Shortcodes', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Power Shortcode defined on this site (name, code, description).',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'power_shortcodes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'code' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_list_power_shortcodes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => dsh_make_instructions(
                'Lists the Power Shortcodes already defined on this site. Use before create-power-shortcode to avoid name collisions.',
            ),
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{power_shortcodes: list<array{name: string, code: string, description: string}>}
 */
function dynamic_shortcodes_list_power_shortcodes(): array
{
    $items = Plugin::instance()->shortcodes_manager->get_power_shortcodes();
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($items as $item) {
        // Defensive: the store is raw decoded JSON with no shape guarantee — a
        // malformed/partial row must not warn or break output_schema validation.
        if (!is_array($item)) {
            continue;
        }
        $out[] = [
            'name' => (string) ($item['name'] ?? ''),
            'code' => (string) ($item['code'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
        ];
    }
    return ['power_shortcodes' => $out];
}
