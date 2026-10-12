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

wp_register_ability('novamira/dynamic-shortcodes-edit-power-shortcode', [
    'label' => __('Edit Power Shortcode', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Power Shortcode by name. When a new code is provided it is syntax-checked (linted) before saving: invalid Dynamic Shortcode syntax is rejected with invalid_shortcode_syntax and the specific lint errors, leaving the stored definition untouched. Returns the previous code and description so the agent can diff. Fails with not_found when no Power Shortcode matches the name.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'The {power:<name>} identifier of the Power Shortcode to edit. Must match an existing one (see list-power-shortcodes); returns not_found otherwise. This is a partial update — pass only the fields you want to change.',
            ],
            'code' => ['type' => 'string'],
            'description' => ['type' => 'string'],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'name' => ['type' => 'string'],
            'previous_code' => ['type' => 'string'],
            'previous_description' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_edit_power_shortcode',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => dsh_make_instructions(
                'Edits an existing Power Shortcode. Partial update — pass only the fields you want to change. Use create-power-shortcode for new ones.',
            ),
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{name: string, code?: string, description?: string} $input
 * @return array{success: true, name: string, previous_code: string, previous_description: string}|WP_Error
 */
function dynamic_shortcodes_edit_power_shortcode(array $input): array|WP_Error
{
    $name = $input['name'];

    $to_check = [];
    if (isset($input['code'])) {
        $to_check['code'] = $input['code'];
    }
    if (isset($input['description'])) {
        $to_check['description'] = $input['description'];
    }
    $invalid = dsh_first_invalid_utf8($to_check);
    if ($invalid !== null) {
        return new WP_Error('invalid_encoding', sprintf(
            "The '%s' field contains invalid UTF-8 and cannot be stored.",
            $invalid,
        ));
    }

    $manager = Plugin::instance()->shortcodes_manager;

    if (isset($input['code'])) {
        $lint_error = dsh_lint_code_or_error($input['code'], $name);
        if ($lint_error !== null) {
            return $lint_error;
        }
    }

    dsh_power_lock_acquire();
    try {
        $applied = dsh_apply_power_edit($manager->get_power_shortcodes(), $name, $input);
        if ($applied === null) {
            return new WP_Error('not_found', sprintf(
                "Power Shortcode '%s' does not exist. Use create-power-shortcode for new ones.",
                $name,
            ));
        }

        $res = $manager->save_power_shortcodes($applied['list']);
        if ($res !== true) {
            return new WP_Error('save_failed', sprintf("Failed to save Power Shortcode '%s'.", $name), [
                'errors' => $res,
            ]);
        }

        return [
            'success' => true,
            'name' => $name,
            'previous_code' => $applied['previous_code'],
            'previous_description' => $applied['previous_description'],
        ];
    } finally {
        dsh_power_lock_release();
    }
}
