<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-create-variable', [
    'label' => __('Create Breakdance Variable', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Breakdance design variable. Required: `label` (human-readable name, e.g. "Brand Primary"), `value` (the literal value, color hex, length, etc.), `type` (Breakdance variable kind, e.g. "color", "size", "typography"). Optional: `id` (defaults to a slug derived from `label`), `cssVariableName` (defaults to a slug too), `collection` (group the variable belongs to). The variable is appended to the global variables JSON blob and the response returns the full new variable.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'label' => ['type' => 'string', 'description' => 'Display name shown in the builder UI.'],
            'value' => [
                'type' => ['string', 'number', 'boolean', 'array', 'object', 'null'],
                'description' => 'Variable value. Type depends on `type` (string for color/size, object for typography, …).',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Breakdance variable kind: `color`, `size`, `typography`, `shadow`, …',
            ],
            'id' => ['type' => 'string', 'description' => 'Stable id. Defaults to a slug derived from `label`.'],
            'cssVariableName' => [
                'type' => 'string',
                'description' => 'CSS variable name (no leading --). Defaults to a slug derived from `label`.',
            ],
            'collection' => [
                'type' => 'string',
                'description' => 'Collection (group) the variable belongs to.',
                'default' => '',
            ],
            'dynamicData' => [
                'type' => ['string', 'object', 'null'],
                'description' => 'Optional dynamic-data binding for the value.',
            ],
        ],
        'required' => ['label', 'value', 'type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'variable' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_create_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Variable `id` and `cssVariableName` must be unique across the project. Picking explicit values is recommended so other tools can refer to the variable; otherwise we derive both from the `label`.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_create_variable(array $input): array|WP_Error
{
    $bail = bd_create_variable_validate($input);
    if ($bail !== null) {
        return $bail;
    }
    /** @var array<string, mixed>|WP_Error $result */
    return bd_with_lock('variables', static fn(): array|WP_Error => bd_create_variable_locked($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bd_create_variable_locked(array $input): array|WP_Error
{
    /** @var string $raw_label */
    $raw_label = $input['label'];
    /** @var string $raw_type */
    $raw_type = $input['type'];
    $label = trim($raw_label);
    $id = bd_pick_string(input: $input, key: 'id', fallback: bd_make_id($label));
    $css_name = bd_pick_string(input: $input, key: 'cssVariableName', fallback: bd_make_id($label));

    $variables = bd_read_variables();
    if (bd_find_variable($variables, $id) !== null) {
        return bd_error('bd_invalid_input', sprintf(
            __('A variable with id `%s` already exists.', domain: 'novamira-pro'),
            $id,
        ));
    }
    if (bd_find_variable_by_css_name($variables, $css_name) !== null) {
        return bd_error('bd_invalid_input', sprintf(
            __('A variable with cssVariableName `%s` already exists.', domain: 'novamira-pro'),
            $css_name,
        ));
    }

    $new_variable = [
        'id' => $id,
        'cssVariableName' => $css_name,
        'label' => $label,
        'value' => $input['value'] ?? null,
        'type' => $raw_type,
        'collection' => isset($input['collection']) && is_string($input['collection']) ? $input['collection'] : '',
        'dynamicData' => $input['dynamicData'] ?? null,
    ];

    $variables[] = $new_variable;
    bd_write_variables($variables);

    return ['success' => true, 'variable' => $new_variable];
}

/**
 * Validate the create-variable inputs. Returns a WP_Error to bail with,
 * or null when inputs look usable.
 *
 * @param array<string, mixed> $input
 */
function bd_create_variable_validate(array $input): ?WP_Error
{
    if (!isset($input['label']) || !is_string($input['label']) || trim($input['label']) === '') {
        return bd_error('bd_invalid_input', __(
            '`label` is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    if (!array_key_exists('value', $input)) {
        return bd_error('bd_invalid_input', __('`value` is required.', domain: 'novamira-pro'));
    }
    if (!isset($input['type']) || !is_string($input['type']) || trim($input['type']) === '') {
        return bd_error('bd_invalid_input', __(
            '`type` is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Pull a trimmed non-empty string out of `$input[$key]`, or fall back
 * to the provided default. Cleaner than repeating the isset+is_string+
 * trim chain at every site.
 *
 * @param array<string, mixed> $input
 */
function bd_pick_string(array $input, string $key, string $fallback): string
{
    if (!isset($input[$key]) || !is_string($input[$key])) {
        return $fallback;
    }
    $trimmed = trim($input[$key]);
    return $trimmed !== '' ? $trimmed : $fallback;
}
