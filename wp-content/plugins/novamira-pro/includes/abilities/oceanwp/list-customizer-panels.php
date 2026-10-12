<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-list-customizer-panels', [
    'label' => __('List OceanWP Customizer Panels', domain: 'novamira-pro'),
    'description' => __(
        'Returns the static catalogue of OceanWP Customizer panels (typography, colors, general, header, blog, WooCommerce, …). Each entry reports the panel id, label, approximate settings count, a one-line description, the integration that must be active for the panel to apply (when conditional), and a resolved `active` flag based on the current install. Use this to discover which subdomain to target with `oceanwp-get-settings` (and Sprint-2 `oceanwp-set-*` abilities).',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'active_only' => [
                'type' => 'boolean',
                'description' => 'When true, omit panels whose conditional integration is not active on this site (e.g. WooCommerce, EDD, LearnDash). Default false (full catalogue).',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => [
                'type' => 'integer',
                'description' => 'Total panels in the static catalogue (before filtering).',
            ],
            'returned' => [
                'type' => 'integer',
                'description' => 'Panels actually returned (after `active_only` filtering).',
            ],
            'panels' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Stable panel identifier — pass to `oceanwp-get-settings.panel`.',
                        ],
                        'label' => ['type' => 'string', 'description' => 'Human-readable panel name.'],
                        'approx_settings' => [
                            'type' => 'integer',
                            'description' => 'Approximate number of settings the panel owns in OceanWP.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'One-line summary of what the panel controls.',
                        ],
                        'conditional_on' => [
                            'type' => 'string',
                            'description' => 'Integration key (woocommerce, edd, learndash, lifterlms) the panel requires; empty when always-on.',
                        ],
                        'active' => [
                            'type' => 'boolean',
                            'description' => 'Whether the panel is effective on this install (always true for unconditional panels; gated for conditional ones).',
                        ],
                    ],
                    'required' => ['id', 'label', 'approx_settings', 'description', 'conditional_on', 'active'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'returned', 'panels'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_list_customizer_panels',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Static catalogue — does not touch the database. Safe to call at any time. Use `active_only: true` to skip e.g. WooCommerce/EDD/LearnDash panels when those integrations are not present.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{active_only?: bool} $input
 * @return array{
 *   total: int,
 *   returned: int,
 *   panels: list<array{id: string, label: string, approx_settings: int, description: string, conditional_on: string, active: bool}>
 * }
 */
function oceanwp_list_customizer_panels(array $input): array
{
    $active_only = isset($input['active_only']) ? ow_coerce_bool($input['active_only']) : false;
    $catalogue = ow_customizer_panel_catalog();
    $integrations = ow_detect_integrations();
    $panels = [];
    foreach ($catalogue as $entry) {
        $conditional_on = $entry['conditional_on'];
        $integration_active = $conditional_on === '' ? true : $integrations[$conditional_on] ?? false;
        if ($active_only && !$integration_active) {
            continue;
        }
        $panels[] = [
            'id' => $entry['id'],
            'label' => $entry['label'],
            'approx_settings' => $entry['approx_settings'],
            'description' => $entry['description'],
            'conditional_on' => $conditional_on,
            'active' => $integration_active,
        ];
    }
    return [
        'total' => count($catalogue),
        'returned' => count($panels),
        'panels' => $panels,
    ];
}
