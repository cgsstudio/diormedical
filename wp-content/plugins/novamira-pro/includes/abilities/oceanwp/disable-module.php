<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-disable-module', [
    'label' => __('Disable Ocean Extra Module', domain: 'novamira-pro'),
    'description' => __(
        'Disables one Ocean Extra module by writing `false` into the matching `oceanwp_modules[<slug>]` option entry. The actual visible effect takes hold on the next request after Ocean Extra reloads its module manifest. Use `oceanwp-list-modules` to discover valid slugs. REQUIRES Ocean Extra.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => [
                'type' => 'string',
                'description' => 'Module slug from `oceanwp-list-modules`.',
            ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string'],
            'persisted' => ['type' => 'boolean'],
            'previous_state' => ['type' => 'boolean'],
        ],
        'required' => ['module', 'persisted', 'previous_state'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_disable_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent disable. Writes `oceanwp_modules[<slug>] = false` and reports `persisted: false` when the module was already disabled.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{module?: string} $input
 * @return array{module: string, persisted: bool, previous_state: bool}|WP_Error
 */
function oceanwp_disable_module(array $input): array|WP_Error
{
    return oceanwp_module_set_state($input, mode: 'disable');
}
