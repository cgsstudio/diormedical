<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: Check Pods setup and report environment status.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/pods-check-setup', [
    'label' => __('Check Pods Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Pods environment: version, whether the API surface is loadable, the Advanced Content Types component (required by `pods-create-act`), the count of pods registered in the DB, and any actionable issues. Run this before any Pods write to confirm the runtime is ready.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'version' => ['type' => 'string'],
            'api_available' => ['type' => 'boolean'],
            'act_component_active' => ['type' => 'boolean'],
            'pod_counts' => [
                'type' => 'object',
                'description' => 'Number of pods registered per type (post_type, taxonomy, pod, settings, …).',
                'additionalProperties' => ['type' => 'integer'],
            ],
            'issues' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Actionable warnings (missing component, version too low, …).',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read `version`, `api_available`, and `act_component_active` first. If `act_component_active` is false the `pods-create-act` ability cannot run — surface the issue to the user instead of attempting it. The `pod_counts` map is a quick health signal: zero across all types usually means a fresh install where the user still needs to model their schema.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function pods_check_setup(): array
{
    $version = pods_version();
    $api_available = pods_min_runtime_available();
    $act_component_active = pods_act_runtime_available();

    /** @var array<string, int> $counts */
    $counts = [];
    if ($api_available) {
        foreach (NOVAMIRA_PODS_TYPES as $type) {
            /** @var int|array<int, mixed>|false $count */
            $count = pods_api()->load_pods(['type' => $type, 'count' => true]);
            $counts[$type] = is_int($count) ? $count : 0;
        }
    }

    $issues = [];
    if (!$api_available) {
        $issues[] = sprintf(
            'Pods is not loaded or its version is below the required %s. Activate the Pods plugin (3.x) and reload.',
            NOVAMIRA_PRO_PODS_MIN_VERSION,
        );
    }
    if ($api_available && !$act_component_active) {
        $issues[] = 'The Advanced Content Types component is not active — `pods-create-act` will fail. Enable it under Pods → Settings → Components if you need custom-table content types.';
    }

    return [
        'version' => $version,
        'api_available' => $api_available,
        'act_component_active' => $act_component_active,
        'pod_counts' => (object) $counts,
        'issues' => $issues,
    ];
}
