<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-check-setup', [
    'label' => __('Check JetEngine Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the JetEngine environment: whether JetEngine is active, the version, whether the minimum version (3.7) is satisfied, whether the current user can manage JetEngine, whether the CCT module is enabled, whether JetEngine\'s own MCP server is enabled at the WordPress level, and counts of registered meta boxes, options pages, and CCT types. Use BEFORE any other JetEngine ability to verify the runtime is ready.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
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
            'version' => ['type' => ['string', 'null']],
            'min_satisfied' => ['type' => 'boolean'],
            'current_user_can_manage' => ['type' => 'boolean'],
            'cct_module_active' => ['type' => 'boolean'],
            'jetengine_mcp_enabled' => ['type' => 'boolean'],
            'registered_meta_boxes_count' => ['type' => 'integer'],
            'registered_options_pages_count' => ['type' => 'integer'],
            'registered_ccts_count' => ['type' => 'integer'],
        ],
        'required' => [
            'active',
            'version',
            'min_satisfied',
            'current_user_can_manage',
            'cct_module_active',
            'jetengine_mcp_enabled',
            'registered_meta_boxes_count',
            'registered_options_pages_count',
            'registered_ccts_count',
        ],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first before any other JetEngine ability. (Note: if this ability is missing from your tool list, JetEngine is not active or below the supported minimum — there is nothing for the agent to do.) cct_module_active gates the 8 CCT abilities (5 type + 5 record); when false, those abilities are not callable. jetengine_mcp_enabled is informational: when true and the agent does not see add-meta-box (or other JetEngine-native tools) in its own tool list, it indicates JetEngine\'s MCP server is enabled at the WordPress level but not configured in the AI client; the agent may suggest the user add it for entities Novamira Pro does not cover (queries, listings, relations, glossaries).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     min_satisfied: bool,
 *     current_user_can_manage: bool,
 *     cct_module_active: bool,
 *     jetengine_mcp_enabled: bool,
 *     registered_meta_boxes_count: int,
 *     registered_options_pages_count: int,
 *     registered_ccts_count: int,
 * }
 */
function jetengine_check_setup(): array
{
    $base = jetengine_detect_setup();

    $meta_boxes_count = 0;
    $options_pages_count = 0;
    $ccts_count = 0;

    if ($base['active'] && function_exists('jet_engine')) {
        $mb_data = jet_engine()->meta_boxes->data ?? null;
        if ($mb_data !== null) {
            $meta_boxes_count = count($mb_data->get_items());
        }

        $op_data = jet_engine()->options_pages->data ?? null;
        if ($op_data !== null) {
            $options_pages_count = count($op_data->get_items());
        }

        if ($base['cct_module_active']) {
            $module = jetengine_get_cct_module();
            if ($module !== null) {
                $types = $module->manager->get_content_types();
                $ccts_count = is_array($types) ? count($types) : 0;
            }
        }
    }

    return [
        'active' => $base['active'],
        'version' => $base['version'],
        'min_satisfied' => $base['min_satisfied'],
        'current_user_can_manage' => $base['can_manage'],
        'cct_module_active' => $base['cct_module_active'],
        'jetengine_mcp_enabled' => $base['mcp_enabled'],
        'registered_meta_boxes_count' => $meta_boxes_count,
        'registered_options_pages_count' => $options_pages_count,
        'registered_ccts_count' => $ccts_count,
    ];
}
