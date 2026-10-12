<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-check-setup', [
    'label' => __('Check Breakdance Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Breakdance runtime state in one call: plugin availability/version, license mode with its detection source, and separate AI capability/runtime signals. `ai.ai_supported` reports the Breakdance capability constant; `ai.native_ai_surface_loaded` reports whether the native MCP/AI ability surface is actually loaded. Use this BEFORE any other Breakdance ability. Every other call is gated on `plugin.active && plugin.min_satisfied`. `license.mode` is informative, not a hard gate. A canonical `free` result comes from Breakdance\'s initialized runtime. When the fallback reports `free`, verify the license with the user before avoiding Pro-only elements and features.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plugin' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'license' => [
                'type' => 'object',
                'properties' => [
                    'has_key' => ['type' => 'boolean'],
                    'mode' => ['type' => 'string', 'enum' => ['free', 'pro']],
                    'status' => ['type' => 'string'],
                    'detection_source' => [
                        'type' => 'string',
                        'enum' => ['canonical', 'fallback'],
                        'description' => 'Whether mode came from Breakdance\'s initialized runtime or the cached-option fallback.',
                    ],
                ],
                'required' => ['has_key', 'mode', 'detection_source'],
                'additionalProperties' => false,
            ],
            'ai' => [
                'type' => 'object',
                'properties' => [
                    'ai_supported' => [
                        'type' => 'boolean',
                        'description' => 'Whether Breakdance declares AI support; this alone does not mean native AI abilities are loaded.',
                    ],
                    'native_ai_surface_loaded' => [
                        'type' => 'boolean',
                        'description' => 'Whether the native Breakdance MCP/AI ability namespace function is loaded.',
                    ],
                ],
                'required' => ['ai_supported', 'native_ai_surface_loaded'],
                'additionalProperties' => false,
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Non-fatal integration warnings the caller must follow.',
            ],
        ],
        'required' => ['plugin', 'license', 'ai', 'warnings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always the first call on a Breakdance site. Surface plugin.min_satisfied=false before continuing. Surface a canonical license.mode=free before using Pro features. If the fallback reports free, tell the user license detection reported free and verify with them before avoiding Pro features. `ai_supported` is capability-only. When `native_ai_surface_loaded` is true, choose one AI surface for the page and do not mix it with Novamira abilities.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function breakdance_check_setup(): array
{
    $version = bd_version();
    $active = $version !== '' && function_exists('Breakdance\\Data\\get_global_option');
    $license_key = bd_license_key();
    $validity = bd_license_validity();
    $license_detection = bd_license_mode_detection();
    $warnings = [];
    $ai_supported = defined('__BREAKDANCE_AI_SUPPORTED__') && constant('__BREAKDANCE_AI_SUPPORTED__') === true;
    $native_ai_surface_loaded = function_exists('breakdance_mcp_ability_namespace');
    if ($native_ai_surface_loaded) {
        $warnings[] =
            'Breakdance exposes a second, native AI ability surface. Do not mix native Breakdance AI '
            . 'abilities with novamira/breakdance-* abilities on the same page; choose one surface for the workflow.';
    }
    if (!$native_ai_surface_loaded && $ai_supported) {
        $warnings[] = 'INFO — Breakdance reports AI support, but its native AI ability surface is not loaded in this runtime.';
    }
    $status = '';
    if (
        is_array($validity)
        && isset($validity['edd_key_info']['license'])
        && is_string($validity['edd_key_info']['license'])
    ) {
        $status = $validity['edd_key_info']['license'];
    }
    return [
        'plugin' => [
            'active' => $active,
            'version' => $version,
            'min_supported' => NOVAMIRA_PRO_BREAKDANCE_MIN_VERSION,
            'min_satisfied' => bd_plugin_available(),
        ],
        'license' => [
            'has_key' => $license_key !== '',
            'mode' => $license_detection['mode'],
            'status' => $status,
            'detection_source' => $license_detection['source'],
        ],
        'ai' => [
            'ai_supported' => $ai_supported,
            'native_ai_surface_loaded' => $native_ai_surface_loaded,
        ],
        'warnings' => $warnings,
    ];
}
