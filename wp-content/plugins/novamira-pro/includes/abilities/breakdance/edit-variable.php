<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-edit-variable', [
    'label' => __('Edit Breakdance Variable', domain: 'novamira-pro'),
    'description' => __(
        'Updates an existing Breakdance design variable identified by `id`. Use list-variables first to discover the id. Only the fields you pass are touched, omitted fields are kept as-is. `id` itself is immutable; to rename a variable, delete-variable then create-variable. `label`, `type` and `cssVariableName` cannot be set to empty strings, and `cssVariableName` must remain unique across the project (collisions are rejected).',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variable id to edit.'],
            'label' => ['type' => 'string'],
            'value' => ['type' => ['string', 'number', 'boolean', 'array', 'object', 'null']],
            'type' => ['type' => 'string'],
            'cssVariableName' => ['type' => 'string'],
            'collection' => ['type' => 'string'],
            'dynamicData' => ['type' => ['string', 'object', 'null']],
        ],
        'required' => ['id'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_edit_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use list-variables first to confirm the id. Edits are immediate, there is no draft state for variables.',
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
function breakdance_edit_variable(array $input): array|WP_Error
{
    if (!isset($input['id']) || !is_string($input['id']) || trim($input['id']) === '') {
        return bd_error('bd_invalid_input', __('`id` is required.', domain: 'novamira-pro'));
    }
    /** @var array<string, mixed>|WP_Error $result */
    return bd_with_lock('variables', static fn(): array|WP_Error => bd_edit_variable_locked($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bd_edit_variable_locked(array $input): array|WP_Error
{
    /** @var string $id_raw */
    $id_raw = $input['id'];
    $id = trim($id_raw);
    $variables = bd_read_variables();
    $hit = bd_find_variable($variables, $id);
    if ($hit === null) {
        return bd_error('bd_not_found', sprintf(__('Variable with id `%s` not found.', domain: 'novamira-pro'), $id));
    }
    [$index, $variable] = $hit;

    $bail = bd_edit_variable_validate_input($input, $variable, $variables);
    if ($bail !== null) {
        return $bail;
    }

    foreach (['label', 'value', 'type', 'cssVariableName', 'collection', 'dynamicData'] as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $variable[$field] = $input[$field];
    }
    $variables[$index] = $variable;
    bd_write_variables($variables);

    return ['success' => true, 'variable' => $variable];
}

/**
 * Per-field validation for the edit-variable patch. The schema permits
 * any value type to support partial updates, but the variable shape
 * cannot survive a blank label / type / cssVariableName, and a CSS
 * name collision would shadow another variable at render time.
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $variable existing record being edited
 * @param list<array<string, mixed>> $variables full list (for collision checks)
 */
function bd_edit_variable_validate_input(array $input, array $variable, array $variables): ?WP_Error
{
    foreach (['label', 'type', 'cssVariableName'] as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        if (!is_string($input[$field]) || trim($input[$field]) === '') {
            return bd_error('bd_invalid_input', sprintf(
                __('`%s` must be a non-empty string when present.', domain: 'novamira-pro'),
                $field,
            ));
        }
    }
    if (array_key_exists('collection', $input) && !is_string($input['collection'])) {
        return bd_error('bd_invalid_input', __('`collection` must be a string when present.', domain: 'novamira-pro'));
    }
    if (
        array_key_exists('cssVariableName', $input)
        && is_string($input['cssVariableName'])
        && (string) $variable['cssVariableName'] !== $input['cssVariableName']
        && bd_find_variable_by_css_name($variables, $input['cssVariableName']) !== null
    ) {
        return bd_error('bd_invalid_input', sprintf(
            __('A variable with cssVariableName `%s` already exists.', domain: 'novamira-pro'),
            $input['cssVariableName'],
        ));
    }
    return null;
}
