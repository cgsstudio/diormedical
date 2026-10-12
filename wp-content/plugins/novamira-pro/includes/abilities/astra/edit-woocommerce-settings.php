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
 * Ability: merge-patch WooCommerce module settings inside `astra-settings`.
 */

wp_register_ability('novamira/astra-edit-woocommerce-settings', [
    'label' => __('Edit Astra WooCommerce Settings', domain: 'novamira-pro'),
    'description' => __(
        'Merge-patches WooCommerce-specific styling keys inside the `astra-settings` option bag. Only keys in the WooCommerce module allowlist (~90 keys) are accepted — global Astra keys and unknown keys are refused and reported in `unknown_keys`. Type mismatches land in `type_mismatch`. Writes run under a MySQL advisory lock and invalidate the CSS cache automatically. Set strict=true to return a 400 error when any key is refused.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Map of {woocommerce_setting_key: new_value}. Call astra-get-woocommerce-settings(partition=all) to inspect valid keys and expected types.',
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
                'description' => 'Keys actually persisted with the values as sent.',
                'additionalProperties' => true,
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys not in the WooCommerce module allowlist — NOT written.',
            ],
            'type_mismatch' => [
                'type' => 'array',
                'description' => 'Keys whose input type did not match the declared type — NOT written.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_edit_woocommerce_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge-patches WooCommerce Astra module settings under a MySQL advisory lock. Only WooCommerce module keys are accepted — global Astra settings (body-font-family, theme-color, …) are refused as unknown_keys. Use astra-get-woocommerce-settings to discover valid keys. Unknown and type-mismatched keys are never written — inspect `unknown_keys` and `type_mismatch` before retrying. The CSS cache is invalidated automatically after each successful write.',
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
function astra_edit_woocommerce_settings(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!astra_addon_available()) {
        return astra_addon_required_error('woocommerce');
    }

    if (!astra_module_active('woocommerce')) {
        return astra_module_disabled_error('woocommerce');
    }

    if (!\Novamira\Pro\Abilities\Woocommerce\wc_min_runtime_available()) {
        return astra_error(
            'astra_woocommerce_inactive',
            message: 'WooCommerce plugin is not active or below minimum version.',
        );
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

    $partitioned = astra_partition_woo_settings_input($raw_patch);
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
