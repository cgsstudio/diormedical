<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-delete-variable', [
    'label' => __('Delete Breakdance Variable', domain: 'novamira-pro'),
    'description' => __(
        'Removes a Breakdance design variable by id. Deletion is immediate and irreversible. Any element using the variable continues to reference its CSS variable name. Breakdance falls back to the literal value at render time, but new edits will see the variable as missing.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'string']],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'deleted_id' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_delete_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'There is no undo. Confirm the variable is not referenced anywhere via list-variables before deleting.',
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
function breakdance_delete_variable(array $input): array|WP_Error
{
    if (!isset($input['id']) || !is_string($input['id']) || trim($input['id']) === '') {
        return bd_error('bd_invalid_input', __('`id` is required.', domain: 'novamira-pro'));
    }
    /** @var array<string, mixed>|WP_Error $result */
    return bd_with_lock('variables', static fn(): array|WP_Error => bd_delete_variable_locked($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bd_delete_variable_locked(array $input): array|WP_Error
{
    /** @var string $id_raw */
    $id_raw = $input['id'];
    $id = trim($id_raw);
    $variables = bd_read_variables();
    $hit = bd_find_variable($variables, $id);
    if ($hit === null) {
        return bd_error('bd_not_found', sprintf(__('Variable with id `%s` not found.', domain: 'novamira-pro'), $id));
    }
    [$index] = $hit;
    array_splice(array: $variables, offset: $index, length: 1);
    bd_write_variables($variables);

    return ['success' => true, 'deleted_id' => $id];
}
