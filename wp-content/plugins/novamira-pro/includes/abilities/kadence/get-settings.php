<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Kadence;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read current values for one or more Kadence theme settings.
 */

wp_register_ability('novamira/kadence-get-settings', [
    'label' => __('Get Kadence Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads the current values (defaults merged with any user overrides) for an explicit list of Kadence theme setting keys. Resolves through Kadence\'s Options Component so the response reflects whatever the user has changed in the Customizer — not just the bare theme_mods. Keys that do not exist on this install (typos, plugin-gated settings) are listed under `unknown_keys` for surfacing rather than silently dropped. Discover available keys via kadence-list-customizer-settings; settings are also returned with their inferred type so the agent can compose a sound kadence-edit-settings payload without a separate type lookup.',
        domain: 'novamira-pro',
    ),
    'category' => 'kadence',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'minItems' => 1,
                'description' => 'List of setting keys to read. Use kadence-list-customizer-settings (with `area` + `with_defaults=true`) to discover keys ahead of time. Pass 1 to ~50 keys per call; very large requests waste tokens — narrow to what you actually need.',
            ],
        ],
        'required' => ['keys'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'array',
                'description' => 'Per-key records. Each entry is `{key, type, value}` — `type` mirrors kadence-list-customizer-settings, `value` is the current value Kadence resolves (overrides merged with defaults).',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'type' => [
                            'type' => 'string',
                            'enum' => ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'],
                        ],
                        'value' => [
                            'type' => ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'],
                            'description' => 'The current value (post-override).',
                        ],
                    ],
                    'required' => ['key', 'type', 'value'],
                ],
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys you requested that are not in Kadence\'s defaults map on this install. Surface to the user — usually a typo or a key that belongs to a plugin / module that is not active.',
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['settings', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Kadence\\kadence_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass an explicit `keys[]` list — discover keys via kadence-list-customizer-settings(area=…) first when in doubt. Returns the post-override (merged) value for each key, plus the inferred type so the agent can compose a kadence-edit-settings payload immediately. Unknown keys are surfaced under `unknown_keys` rather than silently dropped — treat them as typos to confirm with the user.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{keys: list<string>} $input
 * @return array<string, mixed>|WP_Error
 */
function kadence_get_settings(array $input): array|WP_Error
{
    if (!kadence_theme_available()) {
        return kd_error('kadence_theme_inactive', __(
            'Kadence theme is not active or does not meet the minimum supported version.',
            domain: 'novamira-pro',
        ));
    }

    // Defensive: the REST validator declares `keys: array<string>` but
    // empirically lets a non-array scalar slip through (audited via the
    // Round 1 codebase-audit probes). kd_coerce_array() preserves the
    // runtime guard while keeping the static analyzer satisfied.
    $keys = kd_coerce_array($input['keys']);
    if ($keys === null) {
        return kd_error('kadence_invalid_input', __('keys must be an array of strings.', domain: 'novamira-pro'));
    }

    $defaults = kd_load_defaults();
    $tags = kadence_theme_tags();

    $settings = [];
    $unknown = [];

    /** @var mixed $key */
    foreach ($keys as $key) {
        if (!is_string($key)) {
            continue;
        }
        if (!array_key_exists($key, $defaults)) {
            $unknown[] = $key;
            continue;
        }
        // Use the Options Component's `option()` template tag — it
        // transparently handles both storage modes (theme_mod vs option)
        // and applies the same fallback-to-default chain the theme uses
        // at render time, so the returned value is what the page will
        // actually see.
        /** @var mixed $value */
        $value = $tags !== null ? $tags->option($key) : $defaults[$key] ?? null;
        $settings[] = [
            'key' => $key,
            'type' => kd_value_type($value),
            'value' => $value,
        ];
    }

    $result = [
        'settings' => $settings,
        'total' => count($settings),
    ];
    if ($unknown !== []) {
        $result['unknown_keys'] = $unknown;
    }
    return $result;
}
