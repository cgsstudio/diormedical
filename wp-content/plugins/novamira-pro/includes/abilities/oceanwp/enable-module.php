<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-enable-module', [
    'label' => __('Enable Ocean Extra Module', domain: 'novamira-pro'),
    'description' => __(
        'Enables one Ocean Extra module by writing `1` into the matching `oceanwp_modules[<slug>]` option entry. The actual visible effect takes hold on the next request after Ocean Extra reloads its module manifest. Use `oceanwp-list-modules` to discover valid slugs. REQUIRES Ocean Extra.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => [
                'type' => 'string',
                'description' => 'Module slug from `oceanwp-list-modules` (e.g. `sticky_header`, `side_panel`, `cookie_notice`).',
            ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string'],
            'persisted' => ['type' => 'boolean'],
            'previous_state' => ['type' => 'boolean'],
        ],
        'required' => ['module', 'persisted', 'previous_state'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_enable_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent enable. Writes `oceanwp_modules[<slug>] = true` and reports `persisted: false` when the module was already enabled.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{module?: string} $input
 * @return array{module: string, persisted: bool, previous_state: bool}|WP_Error
 */
function oceanwp_enable_module(array $input): array|WP_Error
{
    return oceanwp_module_set_state($input, mode: 'enable');
}

/**
 * Shared toggle implementation used by both `enable-module` and
 * `disable-module`. The `mode` param is a string ('enable' | 'disable')
 * rather than a bool flag so mago's `no-boolean-flag-parameter` rule
 * doesn't fire — it also reads more clearly at call sites.
 *
 * @param array{module?: string} $input
 * @return array{module: string, persisted: bool, previous_state: bool}|WP_Error
 */
function oceanwp_module_set_state(array $input, string $mode): array|WP_Error
{
    $gate = ow_assert_ocean_extra_available();
    if ($gate !== null) {
        return $gate;
    }
    $module = ow_coerce_string_or_null($input['module'] ?? null);
    if ($module === null) {
        return ow_invalid_input(__('Input `module` is required.', domain: 'novamira-pro'));
    }
    $catalogue = ow_ocean_extra_modules();
    if (!isset($catalogue[$module])) {
        return ow_unknown_module($module, array_keys($catalogue));
    }
    $target = $mode === 'enable';

    // Lock-guarded read-modify-write on the `oceanwp_modules` option blob.
    // Without the mutex, two parallel enable-module / disable-module calls
    // can both read the same baseline and the second update_option silently
    // overwrites the first one's change to an unrelated slug — see audit
    // round 2 HIGH#2 / HIGH#3.
    return ow_with_oceanwp_modules_lock(static function () use ($module, $target): array|WP_Error {
        /** @var mixed $current */
        $current = get_option('oceanwp_modules', default_value: []);
        /** @var array<string, mixed> $modules */
        $modules = is_array($current) ? $current : [];
        $previous_state = isset($modules[$module]) && ow_coerce_bool($modules[$module]);
        if ($previous_state === $target) {
            return ['module' => $module, 'persisted' => false, 'previous_state' => $previous_state];
        }
        $modules[$module] = $target;
        // Capture the update_option return so a silently-blocked write
        // (cancelling filter / DB error) surfaces as a 500 instead of a
        // misleading `persisted: true`. Audit round 2 M#12/M#13/M#15.
        if (!update_option('oceanwp_modules', $modules, autoload: false)) {
            return ow_persist_failed('oceanwp_modules');
        }
        return ['module' => $module, 'persisted' => true, 'previous_state' => $previous_state];
    });
}
