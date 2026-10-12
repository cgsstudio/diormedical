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

wp_register_ability('novamira/dynamic-shortcodes-create-power-shortcode', [
    'label' => __('Create Power Shortcode', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Power Shortcode. The code is syntax-checked (linted) before saving: invalid Dynamic Shortcode syntax is rejected with invalid_shortcode_syntax and the specific lint errors, so a broken definition is never stored. Fails when a Power Shortcode with the same name already exists — returns {exists: true, name, current_code, current_description} so the agent can decide whether to edit it via edit-power-shortcode.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'The identifier you later reference as {power:<name>}. Must start with a letter, then letters, digits, "_" or "-". Convention: lowercase-with-hyphens (kebab-case), e.g. "welcome-banner" — underscores and uppercase are allowed but kebab-case is preferred. Pick a descriptive, unique name and call list-power-shortcodes first: create never overwrites — on a name clash it returns {exists:true} and you must use edit-power-shortcode to change the existing one.',
            ],
            'code' => ['type' => 'string', 'minLength' => 1],
            'description' => ['type' => 'string'],
        ],
        'required' => ['name', 'code'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'exists' => ['type' => 'boolean'],
            'name' => ['type' => 'string'],
            'current_code' => ['type' => 'string'],
            'current_description' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_create_power_shortcode',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => dsh_make_instructions(
                'Creates a NEW Power Shortcode. On name collision returns the existing entry without saving — call edit-power-shortcode if you want to change it.',
            ),
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{name: string, code: string, description?: string} $input
 * @return array{success: true, name: string}|array{exists: true, name: string, current_code: string, current_description: string}|WP_Error
 */
function dynamic_shortcodes_create_power_shortcode(array $input): array|WP_Error
{
    $name = $input['name'];
    $code = $input['code'];
    $description = $input['description'] ?? '';

    $name_error = dsh_invalid_power_name($name);
    if ($name_error !== null) {
        return new WP_Error('invalid_name', $name_error);
    }

    $invalid = dsh_first_invalid_utf8(['name' => $name, 'code' => $code, 'description' => $description]);
    if ($invalid !== null) {
        return new WP_Error('invalid_encoding', sprintf(
            "The '%s' field contains invalid UTF-8 and cannot be stored.",
            $invalid,
        ));
    }

    $manager = Plugin::instance()->shortcodes_manager;

    $lint_error = dsh_lint_code_or_error($code, $name);
    if ($lint_error !== null) {
        return $lint_error;
    }

    dsh_power_lock_acquire();
    try {
        $existing = $manager->get_power_shortcodes();

        // @mago-expect analysis:mixed-assignment
        foreach ($existing as $item) {
            if (is_array($item) && ($item['name'] ?? null) === $name) {
                return [
                    'exists' => true,
                    'name' => $name,
                    'current_code' => (string) ($item['code'] ?? ''),
                    'current_description' => (string) ($item['description'] ?? ''),
                ];
            }
        }

        $existing[] = [
            'name' => $name,
            'code' => $code,
            'description' => $description,
        ];

        $res = $manager->save_power_shortcodes($existing);
        if ($res !== true) {
            return new WP_Error('save_failed', sprintf("Failed to save Power Shortcode '%s'.", $name), [
                'errors' => $res,
            ]);
        }

        return [
            'success' => true,
            'name' => $name,
        ];
    } finally {
        dsh_power_lock_release();
    }
}
