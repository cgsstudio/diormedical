<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-set-customizer-settings', [
    'label' => __('Set OceanWP Customizer Settings', domain: 'novamira-pro'),
    'description' => __(
        'Partial-merge writer for the agent-curated allowlist of OceanWP Customizer settings (~40 high-leverage theme_mods covering layout, typography, colors, header, blog, footer, performance, WooCommerce features). Pass an object whose keys are theme_mod names and whose values are the new values; any key NOT in the allowlist is rejected in the `rejected` array without aborting the rest of the write. Values that already match the current theme_mod are reported in `unchanged` so the agent can tell when a write was a no-op. The whole operation runs inside a per-blog lock so concurrent calls cannot interleave reads/writes against `theme_mods_oceanwp`.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Map of theme_mod name (e.g. `ocean_primary_color`) to its new value. See `oceanwp-get-settings` for the available keys + types.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => [
                'type' => 'boolean',
                'description' => 'True when at least one value actually changed (i.e. `changed` is non-empty).',
            ],
            'changed' => [
                'type' => 'array',
                'description' => 'Setting names whose value differed from the prior theme_mod and was written.',
                'items' => ['type' => 'string'],
            ],
            'unchanged' => [
                'type' => 'array',
                'description' => 'Setting names whose new value matched the prior theme_mod — write skipped.',
                'items' => ['type' => 'string'],
            ],
            'rejected' => [
                'type' => 'array',
                'description' => 'Inputs rejected without writing — either not in the allowlist or failed type validation.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'reason'],
                    'additionalProperties' => false,
                ],
            ],
            'values' => [
                'type' => 'object',
                'description' => 'The post-write value of every setting that was accepted (whether changed or unchanged). Keyed by setting name.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['persisted', 'changed', 'unchanged', 'rejected', 'values'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_set_customizer_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial merge — only the keys you pass are touched. Always call `oceanwp-get-settings` first to learn the allowlist + current values; an unknown key is rejected but does not abort the rest. For settings outside the allowlist use `novamira/execute-php` with `set_theme_mod( "ocean_<key>", $value )`.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{settings?: mixed} $input
 * @return array{
 *   persisted: bool,
 *   changed: list<string>,
 *   unchanged: list<string>,
 *   rejected: list<array{name: string, reason: string}>,
 *   values: array<string, mixed>|\stdClass
 * }|WP_Error
 */
// @mago-expect lint:halstead
function oceanwp_set_customizer_settings(array $input): array|WP_Error
{
    $detected = ow_detect_setup();
    if (!$detected['active'] || !$detected['min_satisfied']) {
        return ow_not_active();
    }
    if (!isset($input['settings']) || !is_array($input['settings']) || $input['settings'] === []) {
        return ow_invalid_input(__(
            'Input "settings" must be a non-empty object mapping setting name to new value.',
            domain: 'novamira-pro',
        ));
    }
    /** @var array<int|string, mixed> $raw_settings */
    $raw_settings = $input['settings'];
    $allowlist = ow_customizer_allowlist();

    /** @var array<string, bool|int|float|string|array<string, scalar|null>> $candidates */
    $candidates = [];
    $rejected = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw_settings as $name => $value) {
        if (!is_string($name)) {
            $rejected[] = ['name' => '<non-string-key>', 'reason' => 'Setting name must be a string.'];
            continue;
        }
        if (!isset($allowlist[$name])) {
            $rejected[] = ['name' => $name, 'reason' => 'Setting is not in the agent allowlist.'];
            continue;
        }
        $normalized = ow_validate_setting_value($name, $value, $allowlist[$name]);
        if ($normalized instanceof WP_Error) {
            $rejected[] = ['name' => $name, 'reason' => $normalized->get_error_message()];
            continue;
        }
        $candidates[$name] = $normalized;
    }

    if ($candidates === []) {
        return [
            'persisted' => false,
            'changed' => [],
            'unchanged' => [],
            'rejected' => $rejected,
            // Empty PHP array JSON-encodes as `[]`; the schema declares
            // `values: type:object`, so we emit `{}` on the wire when the
            // map is empty. See audit round 3 F2.
            'values' => ow_object_or_map([]),
        ];
    }

    /** @var array{changed: list<string>, unchanged: list<string>, values: array<string, mixed>}|WP_Error $locked */
    $locked = ow_with_theme_mods_lock(static function () use ($candidates, $allowlist): array {
        $changed = [];
        $unchanged = [];
        $values = [];
        foreach ($candidates as $name => $normalized) {
            $meta = $allowlist[$name];
            $current = $meta['type'] === 'typography' ? ow_theme_mod_typography($name) : ow_theme_mod($name);
            $values[$name] = $normalized;
            if ($current === $normalized) {
                $unchanged[] = $name;
                continue;
            }
            ow_persist_setting($name, $normalized);
            $changed[] = $name;
        }
        return ['changed' => $changed, 'unchanged' => $unchanged, 'values' => $values];
    });

    if ($locked instanceof WP_Error) {
        return $locked;
    }

    return [
        'persisted' => $locked['changed'] !== [],
        'changed' => $locked['changed'],
        'unchanged' => $locked['unchanged'],
        'rejected' => $rejected,
        'values' => $locked['values'],
    ];
}
