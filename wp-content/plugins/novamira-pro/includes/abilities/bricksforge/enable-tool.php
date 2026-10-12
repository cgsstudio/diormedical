<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricksforge-enable-tool', [
    'label' => __('Enable Bricksforge Tool', domain: 'novamira-pro'),
    'description' => __(
        'Activates one Bricksforge tool (feature module) by numeric id or slug, e.g. popups, maintenance, form-submissions, email-designer. Writes the upstream brf_activated_tools option preserving its flat integer-array shape. Already-active tools are a no-op. A tool the installed Bricksforge does not provide (available=false in bricksforge-list-tools) is rejected with bricksforge_tool_unavailable instead of being stored as an ID Bricksforge would ignore. Pro Forms itself is custom element 5 (bricksforge-enable-element slug pro-forms), not a tool; form-submissions is the tool that stores its entries, and its submissions table is only created on the next wp-admin page load.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'The tool id from bricksforge-list-tools. Provide id or slug.',
            ],
            'slug' => [
                'type' => 'string',
                'description' => 'The tool slug from bricksforge-list-tools (e.g. "popups"). Provide id or slug.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
            'changed' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_enable_tool',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Target by slug when possible — ids are Bricksforge-internal numbers. changed=false means the tool was already active. global-classes = Global Classes Backups; global-classes-manager = Global Classes management. For GSAP timelines enable animation-panel (the panel tool alone only runs Events where animation-panel is available).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_enable_tool(array $input): array|WP_Error
{
    $id = brf_resolve_tool_id($input);
    if ($id instanceof WP_Error) {
        return $id;
    }

    if (!brf_tool_available($id)) {
        return brf_error(
            code: 'bricksforge_tool_unavailable',
            message: sprintf(
                'Tool %1$d (%2$s) is not provided by the installed Bricksforge, which ignores this id, so it was not enabled. Update Bricksforge to use it; bricksforge-list-tools shows available=false for such tools.',
                $id,
                brf_tool_map()[$id]['slug'],
            ),
            data: ['status' => 409],
        );
    }

    $changed = brf_with_lock('tools', static function () use ($id): bool {
        $rows = brf_raw_tool_rows();
        // Upstream checks activation with a loose in_array on the raw option;
        // match that, and append without touching existing entries.
        // @mago-expect lint:strict-behavior
        if (in_array($id, $rows)) {
            return false;
        }
        $rows[] = $id;
        brf_update_option('brf_activated_tools', $rows);

        return true;
    });
    if ($changed instanceof WP_Error) {
        return $changed;
    }

    $tool = brf_tool_map()[$id];

    return [
        'id' => $id,
        'slug' => $tool['slug'],
        'name' => $tool['name'],
        'active' => true,
        'changed' => $changed,
    ];
}
