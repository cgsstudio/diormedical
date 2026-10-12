<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-delete-variable', [
    'label' => __('Delete Oxygen Variable', domain: 'novamira-pro'),
    'description' => __(
        'Removes an Oxygen design variable by id. Deletion is immediate and irreversible. Any element using the variable continues to reference its CSS variable name; Oxygen falls back to the literal value at render time, but new edits will see the variable as missing.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_delete_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'There is no undo, and there is no reference check: list-variables enumerates the variables themselves, it does NOT reveal which elements reference this one (elements bind it as `var(--<cssVariableName>)` inside their design values, which this ability does not scan). After deletion those references become dangling and Oxygen renders the last-known literal value. Delete only when you are independently sure the variable is unused.',
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
function oxygen_delete_variable(array $input): array|WP_Error
{
    if (!isset($input['id']) || !is_string($input['id']) || trim($input['id']) === '') {
        return ox_error('oxygen_invalid_input', __('`id` is required.', domain: 'novamira-pro'));
    }
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_lock('variables', static fn(): array|WP_Error => ox_delete_variable_locked($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_delete_variable_locked(array $input): array|WP_Error
{
    /** @var string $id_raw */
    $id_raw = $input['id'];
    $id = trim($id_raw);
    $variables = ox_read_variables();
    $hit = ox_find_variable($variables, $id);
    if ($hit === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Variable with id `%s` not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    [$index] = $hit;
    array_splice(array: $variables, offset: $index, length: 1);
    $written = ox_write_variables($variables);
    if ($written instanceof WP_Error) {
        return $written;
    }
    // Observed-state: confirm the variable is actually gone from the option.
    if (ox_find_variable(ox_read_variables(), $id) !== null) {
        return ox_error('oxygen_write_not_applied', sprintf(
            __('Variable `%s` was not removed; a storage hook may have blocked the write.', domain: 'novamira-pro'),
            $id,
        ));
    }
    return ['success' => true, 'deleted_id' => $id];
}
