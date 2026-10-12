<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-set-settings', [
    'label' => __('Set GeneratePress Settings', domain: 'novamira-pro'),
    'description' => __(
        'Patches the `generate_settings` option array — merge semantics, only the keys present in `settings` are touched, omitted keys keep their current value. Keys unknown to `generate_get_defaults()` (and the color / font / spacing default sets) are written anyway but surface in the `warnings` field of the response — this preserves the ability to set keys added by GP Premium modules or child themes (via the `generate_option_defaults` filter) that an agent might not see in the static defaults reference. The write is destructive (overwrites prior values for the listed keys); pair with generatepress-get-settings to read existing values before patching.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Map of setting key -> new value. Only the keys you list are modified — omitted keys keep their current value. Type per key follows the theme: container_width is int, layout_setting is string, etc. Call generatepress-get-defaults for the type reference.',
            ],
        ],
        'required' => ['settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'updated' => [
                'type' => 'object',
                'description' => 'Map of key -> new value as written. Use to verify the merge result without a follow-up get-settings round-trip.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Per-key warnings. Currently: `unknown_key:<name>` for keys not present in the merged defaults set (the write still happened, but the key is not registered in the theme\'s known defaults — verify it is one of the add-on / child-theme keys you meant to set).',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_set_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge semantics — only fields present in `settings` are updated. The integration is permissive on unknown keys (writes succeed, warning surfaces) because GP Premium modules and child themes add keys via the `generate_option_defaults` filter that the static defaults reference would not show. To strict-check before writing, call generatepress-get-defaults first and intersect your patch with its keys.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{settings: array<string, mixed>} $input
 * @return array<string, mixed>
 */
function generatepress_set_settings(array $input): array
{
    $patch = $input['settings'];
    if ($patch === []) {
        return ['success' => false, 'error' => '`settings` must be a non-empty object.'];
    }
    // Reject sequential arrays (`[1,2,3]`) and string-keys containing NUL
    // bytes — both would pollute `generate_settings` with garbage keys
    // that no GP reader can find. The settings option is a string-keyed
    // map; if any key is non-string or NUL-tainted, surface a clean
    // gp_invalid_input error instead of silently merging junk.
    /** @var list<int|string> $keys */
    $keys = array_keys($patch);
    foreach ($keys as $key) {
        if (is_int($key) || str_contains($key, "\0")) {
            return [
                'success' => false,
                'error' => '`settings` must be a string-keyed object — sequential arrays or NUL-byte keys are rejected.',
            ];
        }
    }

    $partition = gp_partition_known_keys($patch);

    /** @var mixed $existing_raw */
    $existing_raw = get_option('generate_settings', []);
    /** @var array<string, mixed> $existing */
    $existing = is_array($existing_raw) ? $existing_raw : [];
    $merged = array_merge($existing, $patch);

    $ok = update_option('generate_settings', $merged);
    // update_option returns false when the new value equals the existing one
    // (no-op write) — that is not an error condition for the agent.
    if (!$ok && $merged !== $existing) {
        return ['success' => false, 'error' => 'update_option(generate_settings) failed.'];
    }
    // Direct update_option from PHP does NOT trigger GP's customize_save_after
    // invalidation — flush the cache so the next pageload regenerates inline
    // CSS from the new values.
    if ($merged !== $existing) {
        gp_invalidate_dynamic_css_cache();
    }

    $result = [
        'success' => true,
        'updated' => $patch,
    ];
    if ($partition['unknown'] !== []) {
        $result['warnings'] = array_map(static fn(string $k): string => sprintf(
            'unknown_key:%s',
            $k,
        ), $partition['unknown']);
    }
    return $result;
}
