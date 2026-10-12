<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricksforge-check-setup', [
    'label' => __('Check Bricksforge Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Bricksforge environment: plugin active/version/minimum status, Bricks theme version, activated tools and custom elements (numeric IDs resolved to names; each tool also says whether the installed Bricksforge provides it), and issues. Run this before using other Bricksforge abilities. Bricksforge custom elements register as regular Bricks elements — compose them into pages with the novamira/bricks-* abilities.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => ['type' => 'boolean'],
            'version' => ['type' => 'string'],
            'min_satisfied' => ['type' => 'boolean'],
            'min_supported' => ['type' => 'string'],
            'bricks_theme_version' => ['type' => 'string'],
            'activated_tools' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'available' => ['type' => 'boolean'],
                    ],
                ],
            ],
            'activated_elements' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                    ],
                ],
            ],
            'unknown_tool_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'unknown_element_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'current_user_can_manage' => ['type' => 'boolean'],
            'issues' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run before using Bricksforge abilities. Key fields: min_satisfied, activated_tools, activated_elements (empty on fresh installs — Bricksforge then registers NO custom elements in Bricks), and issues.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function bricksforge_check_setup(): array
{
    $active = brf_plugin_active();
    $version = brf_version();
    $min_satisfied = $active && version_compare($version, NOVAMIRA_PRO_BRICKSFORGE_MIN_VERSION, operator: '>=');

    $tool_map = brf_tool_map();
    $element_map = brf_element_map();
    $tool_ids = brf_activated_tool_ids();
    $element_ids = brf_activated_element_ids();

    // Active = upstream's loose in_array on the raw option (brf_tool_active),
    // so float/boolean entries count exactly as Bricksforge counts them.
    $activated_tools = [];
    foreach ($tool_map as $id => $tool) {
        if (!brf_tool_active($id)) {
            continue;
        }
        $activated_tools[] = [
            'id' => $id,
            'slug' => $tool['slug'],
            'name' => $tool['name'],
            'available' => brf_tool_available($id),
        ];
    }
    $unknown_tool_ids = array_values(array_diff($tool_ids, array_keys($tool_map)));

    // Element 13 (API Query Builder Load More) auto-activates whenever tool 18
    // is active, regardless of the registry — mirror the rule list-custom-elements
    // and disable-element apply, so all three readonly abilities agree.
    if (brf_tool_active(18) && !in_array(13, $element_ids, strict: true)) {
        $element_ids[] = 13;
    }

    $activated_elements = [];
    $unknown_element_ids = [];
    foreach ($element_ids as $id) {
        if (!isset($element_map[$id])) {
            $unknown_element_ids[] = $id;
            continue;
        }
        $activated_elements[] = [
            'id' => $id,
            'slug' => $element_map[$id]['slug'],
            'name' => $element_map[$id]['name'],
        ];
    }

    $report = [
        'active' => $active,
        'version' => $version === '' ? 'unknown' : $version,
        'min_satisfied' => $min_satisfied,
        'min_supported' => NOVAMIRA_PRO_BRICKSFORGE_MIN_VERSION,
        'bricks_theme_version' => defined('BRICKS_VERSION') ? (string) constant('BRICKS_VERSION') : '',
        'activated_tools' => $activated_tools,
        'activated_elements' => $activated_elements,
        'unknown_tool_ids' => $unknown_tool_ids,
        'unknown_element_ids' => $unknown_element_ids,
        'current_user_can_manage' => current_user_can('manage_options'),
    ];
    $report['issues'] = brf_check_setup_issues($report);

    return $report;
}

/**
 * Derive the issues list from the assembled check-setup report.
 *
 * @param array<string, mixed> $report
 * @return list<string>
 */
function brf_check_setup_issues(array $report): array
{
    if (($report['active'] ?? false) !== true) {
        return [
            'Bricksforge is not active. It requires the Bricks theme (or a Bricks child theme) as the ACTIVE theme — with any other theme the plugin loads nothing even when the plugin itself is activated.',
        ];
    }

    return [...brf_version_issues($report), ...brf_registry_issues($report)];
}

/**
 * @param array<string, mixed> $report
 * @return list<string>
 */
function brf_version_issues(array $report): array
{
    if (($report['min_satisfied'] ?? false) === true) {
        return [];
    }

    $version = is_string($report['version'] ?? null) ? $report['version'] : 'unknown';

    return [
        sprintf(
            /* translators: 1: installed Bricksforge version, 2: minimum supported version. */
            __('Bricksforge %1$s is below the minimum supported version (%2$s).', domain: 'novamira-pro'),
            $version === '' ? 'unknown' : $version,
            NOVAMIRA_PRO_BRICKSFORGE_MIN_VERSION,
        ),
    ];
}

/**
 * @param array<string, mixed> $report
 * @return list<string>
 */
function brf_registry_issues(array $report): array
{
    $activated_tools = is_array($report['activated_tools'] ?? null) ? $report['activated_tools'] : [];
    $activated_elements = is_array($report['activated_elements'] ?? null) ? $report['activated_elements'] : [];
    $unknown_tool_ids = is_array($report['unknown_tool_ids'] ?? null) ? $report['unknown_tool_ids'] : [];
    $unknown_element_ids = is_array($report['unknown_element_ids'] ?? null) ? $report['unknown_element_ids'] : [];

    $issues = [];
    if ($activated_elements === [] && $unknown_element_ids === []) {
        $issues[] = 'No Bricksforge custom elements are activated (brf_activated_elements is empty — the default on fresh installs). Bricksforge registers NO elements in Bricks until they are enabled, so brf-* elements will not appear in bricks-list-elements. Enable them with novamira/bricksforge-enable-element (e.g. slug "pro-forms").';
    }

    $unavailable_tool_ids = brf_unavailable_tool_ids($activated_tools);
    if ($unavailable_tool_ids !== []) {
        $issues[] = sprintf(
            'Activated tool ID(s) %s are not provided by the installed Bricksforge, so they have no effect. Update Bricksforge to use them, or remove them with novamira/bricksforge-disable-tool.',
            brf_ids_to_string($unavailable_tool_ids),
        );
    }

    if ($unknown_tool_ids !== []) {
        $issues[] = sprintf(
            'Unknown activated tool ID(s) %s — likely a Bricksforge version newer than this integration. The tools work in Bricksforge; they are just not named here.',
            brf_ids_to_string($unknown_tool_ids),
        );
    }

    if ($unknown_element_ids !== []) {
        $issues[] = sprintf(
            'Unknown activated element ID(s) %s — likely a Bricksforge version newer than this integration.',
            brf_ids_to_string($unknown_element_ids),
        );
    }

    return $issues;
}

/**
 * The ids of the activated-tool rows flagged available=false.
 *
 * @param array<array-key, mixed> $activated_tools
 * @return list<int>
 */
function brf_unavailable_tool_ids(array $activated_tools): array
{
    $ids = [];
    /** @var mixed $tool */
    foreach ($activated_tools as $tool) {
        if (!is_array($tool) || ($tool['available'] ?? true) !== false || !is_int($tool['id'] ?? null)) {
            continue;
        }
        $ids[] = $tool['id'];
    }

    return $ids;
}

/**
 * @param array<array-key, mixed> $ids
 */
function brf_ids_to_string(array $ids): string
{
    $parts = [];
    /** @var mixed $id */
    foreach ($ids as $id) {
        if (!is_int($id)) {
            continue;
        }
        $parts[] = (string) $id;
    }

    return implode(', ', $parts);
}
