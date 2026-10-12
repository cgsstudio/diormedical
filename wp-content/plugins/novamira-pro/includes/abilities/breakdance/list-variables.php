<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-list-variables', [
    'label' => __('List Breakdance Variables', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Breakdance design variable (custom property the builder generates into CSS vars). Each variable returns the full shape: `id`, `cssVariableName`, `label`, `type`, `collection`, `value` and `dynamicData`. The response also includes `collections`, the user-defined groupings variables belong to (e.g. "colors", "spacing"). Variables are stored as a single JSON blob option, so this call decodes the whole table on every read; it remains cheap because the option is small.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'collection' => [
                'type' => 'string',
                'description' => 'Optional collection filter: return only variables whose `collection` field matches.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'variables' => ['type' => 'array'],
            'collections' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['variables', 'collections', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_list_variables',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use `collection` to filter to the group you care about. Variables are referenced from CSS as `var(--<cssVariableName>)`, so a typical workflow is: list to find the id, then edit-variable to change the value.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function breakdance_list_variables(array $input): array
{
    $variables = bd_read_variables();
    if (isset($input['collection']) && is_string($input['collection']) && $input['collection'] !== '') {
        $filter = $input['collection'];
        $variables = array_values(array_filter(
            $variables,
            static fn(array $v): bool => isset($v['collection']) && (string) $v['collection'] === $filter,
        ));
    }
    return [
        'variables' => $variables,
        'collections' => bd_read_variable_collections(),
        'total' => count($variables),
    ];
}
