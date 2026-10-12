<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicShortcodes;

use DynamicShortcodes\Core\Shortcodes\Composer;
use DynamicShortcodes\Plugin;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-shortcodes-compose-shortcode', [
    'label' => __('Compose Shortcode', domain: 'novamira-pro'),
    'description' => __(
        'Builds a syntactically correct Dynamic Shortcode string from a structured input (type, positional args, keyargs, filters, fallback) and lints it automatically. Returns {shortcode, valid, errors[]}. Use this when you want a guaranteed-shape output instead of hand-writing the string.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'The Dynamic Shortcode type, e.g. "post", "acf", "query".',
            ],
            'args' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Positional arguments (flat strings only; no nested shortcodes at the MVP).',
            ],
            'keyargs' => [
                'type' => 'object',
                'description' => 'Key-value arguments (after @). A null value renders the key as a flag.',
                'additionalProperties' => true,
            ],
            'filters' => [
                'type' => 'array',
                'description' => 'Filters to apply, in order.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['|', '|-', '||']],
                        'name' => ['type' => 'string'],
                        'args' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['type', 'name'],
                    'additionalProperties' => false,
                ],
            ],
            'fallback' => [
                'type' => 'string',
                'description' => 'Fallback value after `?`. A falsy value ("0" or an empty string) is treated as no fallback.',
            ],
        ],
        'required' => ['type', 'args'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'shortcode' => ['type' => 'string'],
            'valid' => ['type' => 'boolean'],
            'errors' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_compose_shortcode',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => dsh_make_instructions(
                'Builds a Dynamic Shortcode from structured input and lints the result. Prefer this when you have the parts (type, args, filters) cleanly available; for hand-written strings, use validate-shortcode directly.',
            ),
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *   type: string,
 *   args: list<string>,
 *   keyargs?: array<string, mixed>,
 *   filters?: list<array{type: string, name: string, args?: list<string>}>,
 *   fallback?: string
 * } $input
 * @return array{shortcode: string, valid: bool, errors: list<string>}|WP_Error
 */
function dynamic_shortcodes_compose_shortcode(array $input): array|WP_Error
{
    $tok = [
        'type' => $input['type'],
        'args' => $input['args'],
    ];
    if (isset($input['keyargs']) && $input['keyargs'] !== []) {
        $tok['keyargs'] = $input['keyargs'];
    }
    if (isset($input['filters']) && $input['filters'] !== []) {
        $tok['filters'] = $input['filters'];
    }
    if (isset($input['fallback'])) {
        $tok['fallback'] = $input['fallback'];
    }

    // The input validator enforces item types but not every container/value
    // shape the Composer assumes (e.g. a scalar `args`, or a malformed nested
    // keyarg/filter value object). Catch the resulting TypeError and return a
    // clean WP_Error instead of leaking an ability_callback_exception.
    try {
        $shortcode = Composer::compose_shortcode($tok);
    } catch (\Throwable) {
        return new WP_Error(
            'compose_failed',
            'Could not compose a shortcode from the given parts. Ensure `args` is an array of strings and that any nested keyarg/filter value is well-formed (a nested value object needs both "type" and "data").',
        );
    }

    $lint = Plugin::instance()->shortcodes_manager->lint($shortcode);

    return [
        'shortcode' => $shortcode,
        'valid' => $lint['valid'],
        'errors' => $lint['errors'],
    ];
}
