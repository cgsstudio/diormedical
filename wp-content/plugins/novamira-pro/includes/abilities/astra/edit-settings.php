<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: merge-patch the `astra-settings` option bag.
 */

wp_register_ability('novamira/astra-edit-settings', [
    'label' => __('Edit Astra Settings', domain: 'novamira-pro'),
    'description' => __(
        'Merge-patches the `astra-settings` option bag. Only the keys you pass are changed; every other setting keeps its current value. Writes run under a MySQL advisory lock to prevent data loss from concurrent requests. Unknown keys (not in the ~200-key integration allowlist) and type-mismatched keys are NOT written — both surface in the response. Set strict=true to return a 400 error instead of warnings when any key is refused.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Map of {setting_key: new_value}. Call astra-get-defaults or astra-get-settings(include_defaults=true) to inspect valid keys and expected types. Responsive keys (font-size-h1, font-size-body, …) require an array value with desktop/tablet/mobile/desktop-unit/tablet-unit/mobile-unit structure.',
                'additionalProperties' => true,
                'minProperties' => 1,
            ],
            'strict' => [
                'type' => 'boolean',
                'description' => 'When true, any unknown or type-mismatched key returns a 400 error instead of a per-key warning. Defaults to false.',
                'default' => false,
            ],
        ],
        'required' => ['settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'written' => [
                'type' => 'object',
                'description' => 'Keys actually persisted, with the values as sent in.',
                'additionalProperties' => true,
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys not in the integration allowlist — NOT written.',
            ],
            'type_mismatch' => [
                'type' => 'array',
                'description' => 'Keys whose input type did not match the allowlist expected type — NOT written.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'expected' => ['type' => 'string'],
                        'got' => ['type' => 'string'],
                    ],
                    'required' => ['key', 'expected', 'got'],
                ],
            ],
            'total_written' => ['type' => 'integer'],
            'lock_supported' => ['type' => 'boolean'],
        ],
        'required' => ['written', 'unknown_keys', 'type_mismatch', 'total_written', 'lock_supported'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_edit_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge-patches `astra-settings` under a MySQL advisory lock. Unknown and type-mismatched keys are refused — inspect `unknown_keys` and `type_mismatch` in the response before retrying. Use strict=true to fail-fast on any refused key. Responsive keys (font-size-h1, font-size-body, …) must be sent as arrays ({desktop, tablet, mobile, desktop-unit, tablet-unit, mobile-unit}). The CSS cache is invalidated automatically after each write.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{settings: array<string, mixed>, strict?: bool} $input
 * @return array<string, mixed>|WP_Error
 */
function astra_edit_settings(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    $raw_patch = astra_coerce_array($input['settings']);
    if ($raw_patch === null) {
        return astra_invalid_input_error('settings must be an object.');
    }

    if ($raw_patch === []) {
        return astra_invalid_input_error('settings must have at least one property.');
    }

    $strict = astra_coerce_bool($input['strict'] ?? false);

    $partitioned = astra_partition_settings_input($raw_patch);
    $known = $partitioned['known'];
    $unknown = $partitioned['unknown'];
    $mismatched = $partitioned['mismatched'];

    if ($strict && ($unknown !== [] || $mismatched !== [])) {
        return astra_invalid_input_error(sprintf(
            'strict=true: %d unknown key(s), %d type mismatch(es). Resolve inputs or set strict=false.',
            count($unknown),
            count($mismatched),
        ));
    }

    $lock_supported = false;

    if ($known !== []) {
        $lock_supported = astra_settings_lock_supported();
        astra_with_settings_lock(static function () use ($known): void {
            /** @var mixed $bag_raw */
            $bag_raw = get_option('astra-settings', []);
            $bag = is_array($bag_raw) ? $bag_raw : [];
            /** @var mixed $value */
            foreach ($known as $key => $value) {
                $bag[$key] = $value;
            }
            update_option('astra-settings', $bag);
        });

        astra_invalidate_dynamic_css_cache();

        foreach (array_keys($known) as $key) {
            do_action('astra_after_update_option_' . $key, $known[$key]);
        }
    }

    return [
        'written' => astra_object_or_map($known),
        'unknown_keys' => $unknown,
        'type_mismatch' => $mismatched,
        'total_written' => count($known),
        'lock_supported' => $lock_supported,
    ];
}
