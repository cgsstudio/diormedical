<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicShortcodes;

use DynamicShortcodes\Plugin;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-shortcodes-delete-power-shortcode', [
    'label' => __('Delete Power Shortcode', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Power Shortcode by name. Returns the deleted code and description so the action is reversible by the agent. Returns not_found if no Power Shortcode matches.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'The {power:<name>} identifier of the Power Shortcode to delete. Returns not_found if no Power Shortcode has this name.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'name' => ['type' => 'string'],
            'deleted_code' => ['type' => 'string'],
            'deleted_description' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_delete_power_shortcode',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => dsh_make_instructions(
                'Deletes a Power Shortcode permanently. The returned `deleted_code` is the only way to restore it — keep it if you might need to undo.',
            ),
            'readonly' => false,
            'destructive' => true,
            // Re-deleting a missing entry returns not_found (not the same success),
            // so the call is not idempotent — matches the sibling edit ability.
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{name: string} $input
 * @return array{success: true, name: string, deleted_code: string, deleted_description: string}|WP_Error
 */
function dynamic_shortcodes_delete_power_shortcode(array $input): array|WP_Error
{
    $name = $input['name'];
    $manager = Plugin::instance()->shortcodes_manager;

    dsh_power_lock_acquire();
    try {
        $existing = $manager->get_power_shortcodes();

        $deleted_code = '';
        $deleted_description = '';
        $remaining = [];
        $found = false;

        // @mago-expect analysis:mixed-assignment
        foreach ($existing as $item) {
            if (is_array($item) && ($item['name'] ?? null) === $name) {
                $deleted_code = (string) ($item['code'] ?? '');
                $deleted_description = (string) ($item['description'] ?? '');
                $found = true;
                continue;
            }
            $remaining[] = $item;
        }

        if (!$found) {
            return new WP_Error('not_found', sprintf("Power Shortcode '%s' does not exist.", $name));
        }

        $res = $manager->save_power_shortcodes($remaining);
        if ($res !== true) {
            return new WP_Error(
                'save_failed',
                sprintf("Failed to save Power Shortcodes list after deleting '%s'.", $name),
                ['errors' => $res],
            );
        }

        return [
            'success' => true,
            'name' => $name,
            'deleted_code' => $deleted_code,
            'deleted_description' => $deleted_description,
        ];
    } finally {
        dsh_power_lock_release();
    }
}
