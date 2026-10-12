<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicShortcodes;

use DynamicShortcodes\Plugin;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-shortcodes-validate-shortcode', [
    'label' => __('Validate Shortcode', domain: 'novamira-pro'),
    'description' => __(
        'Validates the syntax of a Dynamic Shortcode string without evaluating it. Returns {valid, errors[], shortcode}. Use this after generating or editing a shortcode and before calling evaluate-shortcode.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'shortcode' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'The Dynamic Shortcode string to validate, e.g. "{post:title}".',
            ],
        ],
        'required' => ['shortcode'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'valid' => ['type' => 'boolean'],
            'errors' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'shortcode' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_validate_shortcode',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => dsh_make_instructions(
                'Always validate after generating or editing a Dynamic Shortcode. If errors are reported, fix them and re-lint. Cap retries at 3 before asking the user.',
            ),
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{shortcode: string} $input
 * @return array{valid: bool, errors: list<string>, shortcode: string}
 */
function dynamic_shortcodes_validate_shortcode(array $input): array
{
    return Plugin::instance()->shortcodes_manager->lint($input['shortcode']);
}
