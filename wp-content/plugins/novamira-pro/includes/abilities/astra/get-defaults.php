<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: return the full Astra defaults map as a type-reference for agents.
 */

wp_register_ability('novamira/astra-get-defaults', [
    'label' => __('Get Astra Defaults', domain: 'novamira-pro'),
    'description' => __(
        'Returns the complete `Astra_Theme_Options::defaults()` map — every key Astra recognises and its factory default value. Use as a schema reference before calling astra-get-settings or astra-edit-settings: the default value\'s PHP type signals the type each key expects (string, integer, boolean, array). Large values are truncated at 2 048 bytes with a suffix. Call with no input.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'defaults' => [
                'type' => 'object',
                'description' => 'Map of setting key -> default value. Value type mirrors the expected input type for astra-edit-settings.',
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['defaults', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_defaults',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use as the schema reference for astra-get-settings and astra-edit-settings. The default value type for each key signals the type the setter expects (integer for content-width, string for site-layout, array for logo-width). Response can be large — only call when you need to discover key names.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{defaults: array<array-key, mixed>|\stdClass, total: int}
 */
function astra_get_defaults(): array
{
    if (!class_exists('Astra_Theme_Options')) {
        return ['defaults' => new \stdClass(), 'total' => 0];
    }

    $raw = \Astra_Theme_Options::defaults();
    $out = [];
    /** @var mixed $v */
    foreach ($raw as $k => $v) {
        $out[$k] = astra_cap_bytes($v);
    }

    return ['defaults' => astra_object_or_map($out), 'total' => count($out)];
}
