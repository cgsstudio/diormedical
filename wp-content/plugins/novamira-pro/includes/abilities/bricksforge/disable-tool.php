<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricksforge-disable-tool', [
    'label' => __('Disable Bricksforge Tool', domain: 'novamira-pro'),
    'description' => __(
        'Deactivates one Bricksforge tool (feature module) by numeric id or slug. Writes the upstream brf_activated_tools option preserving its flat integer-array shape. Tool settings in brf_tool_settings and stored data (e.g. Pro Forms submissions) are NOT deleted — re-enabling restores the tool as it was. Already-inactive tools are a no-op. Tools the installed Bricksforge does not provide can still be disabled, which removes a stored id that has no effect.',
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
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_disable_tool',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Disabling a tool turns its frontend/editor features off site-wide until re-enabled; data and settings persist.',
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
function bricksforge_disable_tool(array $input): array|WP_Error
{
    $id = brf_resolve_tool_id($input);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $changed = brf_with_lock('tools', static function () use ($id): bool {
        $rows = brf_raw_tool_rows();
        $kept = [];
        $removed = false;
        /** @var mixed $row */
        foreach ($rows as $row) {
            // Drop entries numerically equal to the id (5, "5", 5.0) and keep
            // everything else verbatim. A boolean `true` entry loosely
            // activates EVERY tool upstream — removing it here would silently
            // deactivate all the others, so it stays (and the response warns
            // that the tool still counts as active).
            if (brf_int_or_null($row) === $id) {
                $removed = true;
                continue;
            }
            /** @var mixed */
            $kept[] = $row;
        }
        if ($removed) {
            brf_update_option('brf_activated_tools', $kept);
        }

        return $removed;
    });
    if ($changed instanceof WP_Error) {
        return $changed;
    }

    $still_active = brf_tool_active($id);

    $tool = brf_tool_map()[$id];

    $response = [
        'id' => $id,
        'slug' => $tool['slug'],
        'name' => $tool['name'],
        'active' => $still_active,
        'changed' => $changed,
    ];
    if ($still_active) {
        $response = brf_append_warning(
            $response,
            warning: 'The tool still counts as active: brf_activated_tools contains a non-numeric entry (for example true) that Bricksforge\'s loose check matches for every tool. Inspect the option manually if this is unexpected.',
        );
    }

    return $response;
}
