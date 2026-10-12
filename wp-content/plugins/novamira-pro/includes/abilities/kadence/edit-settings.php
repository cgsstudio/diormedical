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
 * Ability: patch one or more Kadence theme setting values (partial / merge
 * update — only the keys you pass are changed, the rest stay).
 */

wp_register_ability('novamira/kadence-edit-settings', [
    'label' => __('Edit Kadence Settings', domain: 'novamira-pro'),
    'description' => __(
        'Patches Kadence theme setting values. Partial / merge update — only the keys passed in `settings` are changed; every other setting keeps its current value. Each pair writes through the storage strategy Kadence is configured for (theme_mod or option — see kadence-check-setup.storage.type). Unknown keys (not in Kadence\'s defaults map on this install) are NOT written and surface under `unknown_keys` in the response. Type mismatches between the input value and the registered default (e.g. passing a string where the default is an array) are surfaced under `warnings[]` and the write still happens — Kadence accepts both shapes for some keys, so the warning is informational. Reads after this call return the new values immediately.',
        domain: 'novamira-pro',
    ),
    'category' => 'kadence',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Map of `{setting_key: new_value}`. Each value follows the type of the setting\'s default — call kadence-get-settings or kadence-list-customizer-settings(with_defaults=true) first if you don\'t already know the shape.',
                'additionalProperties' => true,
                'minProperties' => 1,
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
                'description' => 'Map of the keys actually written, with the value Kadence resolves them to after the patch. Reading via kadence-get-settings right after returns the same values.',
                'additionalProperties' => [
                    'type' => ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'],
                ],
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys you tried to write that are not in Kadence\'s defaults map. These were NOT persisted. Surface to the user as typos or plugin-gated keys.',
            ],
            'warnings' => [
                'type' => 'array',
                'description' => 'Per-key warnings. `type_mismatch` (write still happened — Kadence may accept the shape) when the input value\'s type does not match the registered default\'s type. `non_persistable_type` (write was REFUSED) when the input is an object, resource, or closure that cannot round-trip through wp_options serialization.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'code' => ['type' => 'string', 'enum' => ['type_mismatch', 'non_persistable_type']],
                        'expected_type' => ['type' => 'string'],
                        'actual_type' => ['type' => 'string'],
                    ],
                    'required' => ['key', 'code', 'expected_type', 'actual_type'],
                ],
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Kadence\\kadence_edit_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Patches one or more theme settings in a single call. Storage path is automatic — Kadence\'s configured `storage.type` (theme_mod or option) decides where the value lands; the agent does not need to handle both. Unknown keys are refused (not silently absorbed) so typos do not become invisible. Reading via kadence-get-settings right after returns the new values immediately (the Options Component static cache is invalidated as part of the write).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{settings: array<string, mixed>} $input
 * @return array<string, mixed>|WP_Error
 */
function kadence_edit_settings(array $input): array|WP_Error
{
    if (!kadence_theme_available()) {
        return kd_error('kadence_theme_inactive', __(
            'Kadence theme is not active or does not meet the minimum supported version.',
            domain: 'novamira-pro',
        ));
    }

    // Defensive: REST validator declares `settings: object` but a
    // list-shaped or scalar value can slip through (audited via the
    // Round 1 codebase-audit probes). A list collapses string-looking
    // keys to int on the PHP side, which then trips the output
    // validator when we leak ints into unknown_keys[]. The PHP cast to
    // string at the foreach below handles the int-key case; this guard
    // rejects the non-array case cleanly.
    $writes = kd_coerce_array($input['settings']);
    if ($writes === null) {
        return kd_error('kadence_invalid_input', __('settings must be an object.', domain: 'novamira-pro'));
    }

    $storage = kd_storage_meta();
    $defaults = kd_load_defaults();

    $accepted = [];
    $unknown = [];
    $warnings = [];

    /** @var mixed $value */
    foreach ($writes as $key => $value) {
        $key = (string) $key;
        if (!array_key_exists($key, $defaults)) {
            $unknown[] = $key;
            continue;
        }
        $expected_type = kd_value_type($defaults[$key]);
        $actual_type = kd_value_type($value);
        // Non-persistable input types (object/resource/closure) round-trip
        // through wp_options serialization in surprising ways: stdClass
        // serializes and persists silently, resource becomes int 0, Closure
        // fatals via `serialize()`. Refuse the write rather than corrupt
        // the bag (Round 2 audit B18).
        if ($actual_type === 'object' || $actual_type === 'resource') {
            $warnings[] = [
                'key' => $key,
                'code' => 'non_persistable_type',
                'expected_type' => $expected_type,
                'actual_type' => $actual_type,
            ];
            continue;
        }
        if ($expected_type !== $actual_type && $expected_type !== 'null') {
            $warnings[] = [
                'key' => $key,
                'code' => 'type_mismatch',
                'expected_type' => $expected_type,
                'actual_type' => $actual_type,
            ];
        }
        $accepted[$key] = $value;
    }

    // Atomic batch under a cross-request lock — Round 2 audit B4 found
    // 87% silent data loss on parallel REST calls without locking.
    $updated = kd_apply_writes($storage, $accepted);
    kd_invalidate_options_cache();

    $result = ['success' => true, 'updated' => $updated];
    if ($unknown !== []) {
        $result['unknown_keys'] = $unknown;
    }
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }
    return $result;
}

/**
 * Applies a batch of writes atomically under the storage-bag lock.
 * Both Kadence storage strategies ultimately read-modify-write a shared
 * wp_options row — `kadence_settings` under `option` storage, or
 * `theme_mods_<stylesheet>` under `theme_mod`. Doing one read + N
 * mutations + one write per call (rather than N independent RMW cycles)
 * eliminates the within-call race window, and the surrounding GET_LOCK
 * (see kd_with_options_lock) eliminates the cross-request race that
 * caused ~87% data loss on parallel REST calls (Round 2 audit B4).
 *
 * @param array{type: string, option_name: string} $storage
 * @param array<string, mixed> $writes
 * @return array<string, mixed> The same keys passed in, returned for
 *     the caller to assemble the `updated` response field. (Echoing
 *     input is correct here: Kadence's value-shape tolerance means we
 *     don't normalise — what the agent sent is what the storage now
 *     holds.)
 */
function kd_apply_writes(array $storage, array $writes): array
{
    if ($writes === []) {
        return [];
    }
    if ($storage['type'] === 'option') {
        $option_name = $storage['option_name'];
        kd_with_options_lock($option_name, static function () use ($option_name, $writes): void {
            /** @var mixed $bag */
            $bag = get_option($option_name, []);
            if (!is_array($bag)) {
                $bag = [];
            }
            /** @var mixed $value */
            foreach ($writes as $key => $value) {
                $bag[$key] = $value;
            }
            update_option($option_name, $bag, autoload: true);
        });
        return $writes;
    }
    // theme_mod storage: theme_mods_<stylesheet> is itself a bag option
    // and `set_theme_mod()` does its own per-call RMW (see WP core). We
    // wrap the whole batch in the same lock so concurrent REST calls
    // serialise on our side; per-key `pre_set_theme_mod_<name>` filters
    // still fire normally inside set_theme_mod().
    $theme_mod_option = 'theme_mods_' . get_stylesheet();
    kd_with_options_lock($theme_mod_option, static function () use ($writes): void {
        /** @var mixed $value */
        foreach ($writes as $key => $value) {
            set_theme_mod($key, $value);
        }
    });
    return $writes;
}

/**
 * Invalidates the Options Component's static option cache so a subsequent
 * `get_options()` call re-reads from storage. Without this, edit-settings
 * writes only show up in the next request lifecycle. Touches a small set
 * of protected statics via reflection — Kadence does not expose a public
 * invalidation API.
 *
 * Round 1 cleared `$options` only; Round 2 audit B3 found that a
 * `kadence_theme_option_type` filter flip mid-request leaves the cached
 * `$opt_type` / `$opt_name` stale, so subsequent writes can land in the
 * wrong store. Clear the storage-meta cache too. Round 2 audit B9 noted
 * the same statics leak across blogs within a single multisite request;
 * clearing them on every edit-settings call narrows that window in
 * practice (a full fix would also hook `switch_blog`).
 */
function kd_invalidate_options_cache(): void
{
    if (!class_exists('Kadence\\Options\\Component')) {
        return;
    }
    $r = new \ReflectionClass('Kadence\\Options\\Component');
    foreach (['options', 'opt_type', 'opt_name'] as $prop) {
        if (!$r->hasProperty($prop)) {
            continue;
        }
        $r->getProperty($prop)->setValue(null, null);
    }
}
