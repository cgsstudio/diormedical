<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra\Pro;

use Novamira\Pro\Abilities\Spectra as Free;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-pro-check-setup', [
    'label' => __('Check Spectra Pro Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Spectra Pro (`spectra-pro/spectra-pro.php`) runtime state: plugin active, version, min-supported, min-satisfied, plus the Spectra Pro license status (distinct from Novamira Pro\'s own license — a site can have Novamira Pro active + Spectra Pro installed-but-unlicensed). Call this BEFORE any other `novamira/spectra-pro-*` ability. Every Pro ability additionally gates on `plugin.active && plugin.min_satisfied` at registration time (they are not loaded when the gate fails), so this call is only reached when Pro is at or above the supported floor.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
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
                    'active' => ['type' => 'boolean'],
                ],
                'required' => ['active'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['plugin', 'license'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\Pro\\spectra_pro_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'First call for any Spectra Pro flow. license.active=false does NOT prevent Pro abilities from running — it only tells you the plugin is unlicensed.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function spectra_pro_check_setup(): array
{
    $version = Free\spectra_pro_version();

    return [
        'plugin' => [
            'active' => $version !== '',
            'version' => $version,
            'min_supported' => NOVAMIRA_PRO_SPECTRA_PRO_MIN_VERSION,
            'min_satisfied' => Free\spectra_pro_active(),
        ],
        'license' => [
            'active' => spectra_pro_license_active(),
        ],
    ];
}
