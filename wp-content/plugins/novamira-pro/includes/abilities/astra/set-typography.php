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
 * Ability: merge-patch typography settings inside `astra-settings`.
 */

wp_register_ability('novamira/astra-set-typography', [
    'label' => __('Set Astra Typography', domain: 'novamira-pro'),
    'description' => __(
        'Merge-patches typography keys inside `astra-settings` — body font, headings (global + H1–H6), and size arrays. Partitions input against the typography-specific allowlist (~35 keys); unknown and type-mismatched keys are NOT written and surface in the response. Set strict=true to return 400 on any refused key. Writes run under a settings lock. Responsive size keys (font-size-body, font-size-h1, …) must be sent as arrays with desktop/tablet/mobile/desktop-unit/tablet-unit/mobile-unit.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'typography' => [
                'type' => 'object',
                'description' => 'Map of typography key => value. Targets only the typography allowlist. Use astra-get-defaults to inspect valid keys.',
                'additionalProperties' => true,
                'minProperties' => 1,
            ],
            'strict' => [
                'type' => 'boolean',
                'description' => 'When true, any unknown or mismatched key returns 400 instead of a warning.',
                'default' => false,
            ],
        ],
        'required' => ['typography'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'written' => [
                'type' => 'object',
                'description' => 'Typography keys actually persisted.',
                'additionalProperties' => true,
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys not in the typography allowlist — NOT written.',
            ],
            'type_mismatch' => [
                'type' => 'array',
                'description' => 'Keys with wrong value type — NOT written.',
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
        ],
        'required' => ['written', 'unknown_keys', 'type_mismatch', 'total_written'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_set_typography',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Targets only typography keys — for non-typography settings use astra-edit-settings. Font family keys accept Google Font names (e.g. "Roboto") or "inherit". Responsive size keys must be arrays. Writes are locked and the CSS cache is invalidated after each call.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{typography: array<string, mixed>, strict?: bool} $input
 * @return array<string, mixed>|WP_Error
 */
function astra_set_typography(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    $raw_patch = astra_coerce_array($input['typography']);
    if ($raw_patch === null) {
        return astra_invalid_input_error('typography must be an object.');
    }

    $strict = astra_coerce_bool($input['strict'] ?? false);

    $partitioned = astra_partition_settings_input($raw_patch, astra_typography_settings_allowlist());
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

    if ($known !== []) {
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
    ];
}
