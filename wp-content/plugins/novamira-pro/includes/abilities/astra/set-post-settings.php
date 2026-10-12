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
 * Ability: merge-patch per-post Astra display overrides.
 */

wp_register_ability('novamira/astra-set-post-settings', [
    'label' => __('Set Astra Post Settings', domain: 'novamira-pro'),
    'description' => __(
        'Merge-patches the per-post Astra display overrides for a given post. Only the keys you pass are changed; every other override keeps its current value. Accepts the 20 Astra meta keys (layout, header/footer display toggles, page element toggles, background). Unknown keys and enum violations are reported — set strict=true to fail-fast instead. Background keys (`ast-page-background-meta`, `ast-content-background-meta`) accept a responsive array; use `astra-set-post-background` for deep field validation.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to write overrides for.',
                'minimum' => 1,
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Map of {meta_key: value}. Toggle keys (header/footer/page display) accept "disabled" or "" (empty string = inherit global Customizer). Layout keys accept an enum — call astra-get-post-settings to see current values and valid options.',
                'additionalProperties' => true,
                'minProperties' => 1,
            ],
            'strict' => [
                'type' => 'boolean',
                'description' => 'When true, any unknown key or enum violation returns a 400 error instead of a per-key warning. Defaults to false.',
                'default' => false,
            ],
        ],
        'required' => ['post_id', 'settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'written' => [
                'type' => 'object',
                'description' => 'Keys actually persisted, with the values as sent.',
                'additionalProperties' => true,
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys not in the integration allowlist — NOT written.',
            ],
            'invalid_values' => [
                'type' => 'array',
                'description' => 'Allowlisted keys with enum violations or type errors — NOT written.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['key', 'reason'],
                ],
            ],
            'total_written' => ['type' => 'integer'],
        ],
        'required' => ['post_id', 'written', 'unknown_keys', 'invalid_values', 'total_written'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_set_post_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge-patches per-post Astra display overrides. Only the keys you pass are changed. Toggle keys accept "disabled" or "" (empty = inherit global). Use astra-clear-post-settings to bulk-revert keys to global. Use astra-set-post-background for the responsive background array fields.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_set_post_settings(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    $post_id = astra_coerce_int($input['post_id'] ?? 0);
    $post_or_err = astra_post_or_error($post_id);
    if ($post_or_err instanceof WP_Error) {
        return $post_or_err;
    }

    $settings = astra_coerce_array($input['settings'] ?? []) ?? [];
    $strict = astra_coerce_bool($input['strict'] ?? false);

    $partitioned = astra_partition_post_settings_input($settings);
    $known = $partitioned['known'];
    $unknown = $partitioned['unknown'];
    $invalid = $partitioned['invalid'];

    if ($strict && ($unknown !== [] || $invalid !== [])) {
        return astra_invalid_input_error(sprintf(
            'strict=true: %d unknown key(s), %d invalid value(s). Use astra-get-post-settings to inspect valid keys and accepted values.',
            count($unknown),
            count($invalid),
        ));
    }

    $written = [];
    /** @var mixed $value */
    foreach ($known as $key => $value) {
        update_post_meta($post_id, $key, $value);
        $written[$key] = $value;
    }

    if ($written !== []) {
        clean_post_cache($post_id);
    }

    return [
        'post_id' => $post_id,
        'written' => astra_object_or_map($written),
        'unknown_keys' => $unknown,
        'invalid_values' => $invalid,
        'total_written' => count($written),
    ];
}
