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
 * Ability: read Astra Pro WooCommerce module settings from `astra-settings`.
 */

wp_register_ability('novamira/astra-get-woocommerce-settings', [
    'label' => __('Get Astra WooCommerce Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads WooCommerce-specific styling settings from the `astra-settings` option bag. Requires the Astra Pro Addon with the `woocommerce` module active and WooCommerce installed. Use the `partition` parameter to focus on a specific domain (shop_grid, product_styling, cart_style, checkout_layout, filter, quick_view) or omit it for all ~90 keys. Pass `keys` to request a specific subset — keys absent from the WooCommerce module allowlist surface in `unknown_keys`.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'partition' => [
                'type' => 'string',
                'enum' => [
                    'all',
                    'shop_grid',
                    'product_styling',
                    'cart_style',
                    'checkout_layout',
                    'filter',
                    'quick_view',
                ],
                'description' => 'Named domain slice to read. Defaults to all. When combined with `keys`, only keys present in BOTH the partition and the requested list are returned.',
                'default' => 'all',
            ],
            'keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional explicit key subset. When combined with `partition`, acts as a subset filter within the chosen partition — only keys that appear in both the partition and this list are returned. Keys valid in the WooCommerce allowlist but outside the requested partition are silently skipped (not surfaced in unknown_keys). Keys absent from the WooCommerce allowlist entirely are returned in unknown_keys.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'partition' => [
                'type' => 'string',
                'description' => 'Partition that was read (echoed back).',
            ],
            'values' => [
                'type' => 'object',
                'description' => 'key → current value map. String values are truncated at 2 KB with a trailing marker.',
                'additionalProperties' => true,
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys requested via the `keys` input that are not in the WooCommerce module allowlist.',
            ],
            'total_keys' => [
                'type' => 'integer',
                'description' => 'Number of keys returned in values.',
            ],
        ],
        'required' => ['partition', 'values', 'unknown_keys', 'total_keys'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_woocommerce_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reads Astra WooCommerce module settings. Requires astra-check-setup to confirm addon.active and modules.woocommerce. Use partition to narrow the read scope; use keys for a targeted subset. String values exceeding 2 KB are truncated — request a single key via keys[] if you need the full value.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Resolve the candidate key list for the given partition name.
 * Returns the list on success, or WP_Error when the partition name is unknown.
 *
 * @param array<string, string> $allowlist
 * @return list<string>|WP_Error
 */
function ast_woo_resolve_candidate_keys(string $partition, array $allowlist): array|WP_Error
{
    if ($partition === 'all') {
        return array_keys($allowlist);
    }
    $partition_keys = astra_woo_partition_keys($partition);
    if ($partition_keys === null) {
        return astra_invalid_input_error(sprintf(
            'Unknown partition "%s". Valid values: all, shop_grid, product_styling, cart_style, checkout_layout, filter, quick_view.',
            $partition,
        ));
    }
    return $partition_keys;
}

/**
 * Apply the optional caller-supplied key filter to the candidate set.
 *
 * Keys present in `$requested_keys` but absent from the full allowlist land
 * in `unknown`. Keys that are in the allowlist but not in the candidate set
 * (i.e. outside the requested partition) are silently skipped. When
 * `$requested_keys` is null the full candidate set is returned unchanged.
 *
 * @param list<string>                 $candidate_keys
 * @param array<array-key, mixed>|null $requested_keys
 * @param array<string, string>        $allowlist
 * @return array{selected: list<string>, unknown: list<string>}
 */
function ast_woo_filter_requested_keys(array $candidate_keys, ?array $requested_keys, array $allowlist): array
{
    if ($requested_keys === null) {
        return ['selected' => $candidate_keys, 'unknown' => []];
    }

    // @mago-expect analysis:mixed-assignment
    $candidate_index = array_flip($candidate_keys);
    $selected = [];
    $unknown = [];

    foreach ($requested_keys as $k) {
        if (!is_string($k)) {
            continue;
        }
        if (!array_key_exists($k, $allowlist)) {
            $unknown[] = $k;
            continue;
        }
        if (array_key_exists($k, $candidate_index)) {
            $selected[] = $k;
        }

        // Key is in the allowlist but not in the requested partition — silently skip.
    }

    return ['selected' => $selected, 'unknown' => $unknown];
}

/**
 * @param array{partition?: string, keys?: list<string>} $input
 * @return array<string, mixed>|WP_Error
 */
function astra_get_woocommerce_settings(array $input): array|WP_Error
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

    $partition = is_string($input['partition'] ?? null) ? $input['partition'] : 'all';
    $requested_keys = astra_coerce_array($input['keys'] ?? null);
    $allowlist = astra_woo_settings_allowlist();

    $candidate = ast_woo_resolve_candidate_keys($partition, $allowlist);
    if ($candidate instanceof WP_Error) {
        return $candidate;
    }

    $filtered = ast_woo_filter_requested_keys($candidate, $requested_keys, $allowlist);
    $selected_keys = $filtered['selected'];
    $unknown_keys = $filtered['unknown'];

    $values = [];
    foreach ($selected_keys as $key) {
        /** @var mixed $raw */
        $raw = astra_read_setting($key, default: '');
        $values[$key] = astra_cap_bytes($raw);
    }

    return [
        'partition' => $partition,
        'values' => astra_object_or_map($values),
        'unknown_keys' => $unknown_keys,
        'total_keys' => count($values),
    ];
}
