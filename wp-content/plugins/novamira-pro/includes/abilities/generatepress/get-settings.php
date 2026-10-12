<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-get-settings', [
    'label' => __('Get GeneratePress Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads keys from the `generate_settings` option array (e.g. container_width, layout_setting, header_layout_setting, primary_nav_position_setting, colors, fonts). Use `keys` to pull only what you need — leaving it empty returns the FULL set (heavy: ~80 keys on a default install, more with Premium modules). Reads go through the theme\'s canonical accessor `generate_get_option()` so defaults + the `generate_option` filter are applied — never reads `get_option(\'generate_settings\')` raw. To see what keys exist without reading values, call generatepress-get-defaults.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'List of setting keys to read. RECOMMENDED — pulling only the keys you need keeps responses lean. Leave empty to dump everything from the merged defaults set (heavy).',
            ],
            'include_defaults' => [
                'type' => 'boolean',
                'description' => 'When true, the response includes keys that are not user-customised (resolved to their default value). When false (default), only keys explicitly set in `generate_settings` come back.',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Map of setting key -> value. Value type depends on the key (string, int, bool, array). Use generatepress-get-defaults to know the expected type per key.',
            ],
            'total' => ['type' => 'integer', 'description' => 'Number of keys returned.'],
        ],
        'required' => ['settings', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Prefer the `keys` whitelist for routine reads. Leaving keys empty returns ~80+ entries which inflates context. Common shortcuts: ["container_width","content_layout_setting","layout_setting"] for layout intent, ["text_color","background_color"] for colors, ["font_body","font_heading_1"] for typography.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{keys?: list<string>, include_defaults?: bool} $input
 * @return array{settings: array<string, mixed>, total: int}
 */
function generatepress_get_settings(array $input): array
{
    $requested_keys = isset($input['keys']) ? array_values(array_map('strval', $input['keys'])) : [];
    $include_defaults = gp_coerce_bool($input['include_defaults'] ?? false);

    /** @var mixed $stored_raw */
    $stored_raw = get_option('generate_settings', []);
    /** @var array<string, mixed> $stored */
    $stored = is_array($stored_raw) ? $stored_raw : [];

    $defaults = gp_known_defaults();

    $source = $requested_keys !== []
        ? $requested_keys
        : array_map('strval', array_keys($include_defaults ? array_merge($defaults, $stored) : $stored));

    $out = [];
    foreach ($source as $key) {
        if (!$include_defaults && !array_key_exists($key, $stored) && $requested_keys === []) {
            continue;
        }
        // `gp_read_setting` uses `generate_get_option`, which only returns
        // values whose key is in the theme's defaults set. Unknown keys
        // stored permissively by set-settings would come back as null —
        // breaking the write→read roundtrip. Fall back to the raw stored
        // value when the canonical accessor returns null but the key IS
        // in the DB.
        /** @var mixed $value */
        $value = gp_read_setting($key);
        if ($value === null && array_key_exists($key, $stored)) {
            /** @var mixed $value */
            $value = $stored[$key];
        }
        $out[$key] = $value;
    }

    return ['settings' => $out, 'total' => count($out)];
}
