<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-edit-variable', [
    'label' => __('Edit Oxygen Variable', domain: 'novamira-pro'),
    'description' => __(
        'Updates an existing Oxygen design variable identified by `id`. Use list-variables first to discover the id. Only the fields you pass are touched, omitted fields are kept as-is. `id` itself is immutable; to rename a variable, delete-variable then create-variable. `label`, `type` and `cssVariableName` cannot be set to empty strings, and `cssVariableName` must remain unique across the project (collisions are rejected).',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_edit_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use list-variables first to confirm the id. Edits are immediate — there is no draft state for variables.',
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
function oxygen_edit_variable(array $input): array|WP_Error
{
    if (!isset($input['id']) || !is_string($input['id']) || trim($input['id']) === '') {
        return ox_error('oxygen_invalid_input', __('`id` is required.', domain: 'novamira-pro'));
    }
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_lock('variables', static fn(): array|WP_Error => ox_edit_variable_locked($input));
}

/**
 * Apply the edit patch fields onto the existing row, then re-validate the
 * RESULTING type/value pair against Oxygen's contract (a partial update must not
 * leave the variable in a shape the renderer breaks on). Returns the patched
 * row, or a WP_Error when the new type/value pair is not renderable.
 *
 * @param array<string, mixed> $variable
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_apply_variable_patch(array $variable, array $input): array|WP_Error
{
    foreach (['label', 'value', 'type', 'cssVariableName', 'collection', 'dynamicData'] as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $variable[$field] = $input[$field];
    }
    if (!array_key_exists('type', $input) && !array_key_exists('value', $input)) {
        return $variable;
    }
    $type_check = ox_validate_variable_type_value(
        is_string($variable['type'] ?? null) ? $variable['type'] : '',
        $variable['value'] ?? null,
    );
    return $type_check instanceof WP_Error ? $type_check : $variable;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_edit_variable_locked(array $input): array|WP_Error
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
    [$index, $variable] = $hit;

    // Normalise cssVariableName to its trimmed form BEFORE both validation and
    // the write below, so a name with surrounding whitespace ("  x ") cannot
    // pass validation (which trims) yet persist untrimmed (a broken `-- x:` rule).
    if (isset($input['cssVariableName']) && is_string($input['cssVariableName'])) {
        $input['cssVariableName'] = trim($input['cssVariableName']);
    }

    $bail = ox_edit_variable_validate_input($input, $variable, $variables);
    if ($bail !== null) {
        return $bail;
    }

    $patched = ox_apply_variable_patch($variable, $input);
    if ($patched instanceof WP_Error) {
        return $patched;
    }
    $variable = $patched;
    $variables[$index] = $variable;
    $written = ox_write_variables($variables);
    if ($written instanceof WP_Error) {
        return $written;
    }
    // Observed-state: re-read and confirm the persisted row MATCHES the intended
    // one field-by-field, not merely that a row with this id still exists. A
    // storage hook can keep the id while reverting the value, so existence alone
    // would report a blocked edit as applied.
    $hit = ox_find_variable(ox_read_variables(), (string) $variable['id']);
    if ($hit === null) {
        return ox_error('oxygen_write_not_applied', sprintf(
            __('Variable `%s` did not persist; a storage hook may have blocked the write.', domain: 'novamira-pro'),
            (string) $variable['id'],
        ));
    }
    [, $observed] = $hit;
    $mismatch = ox_variable_row_mismatch($variable, $observed);
    if ($mismatch !== null) {
        return ox_error('oxygen_write_not_applied', sprintf(
            /* translators: 1: variable id, 2: field name */
            __(
                'Variable `%1$s` did not persist as requested: the `%2$s` field differs from what was written (a storage hook may have altered or reverted it).',
                domain: 'novamira-pro',
            ),
            (string) $variable['id'],
            $mismatch,
        ));
    }
    return ['success' => true, 'variable' => $observed];
}

/**
 * Per-field validation for the edit-variable patch. The schema permits
 * any value type to support partial updates, but the variable shape
 * cannot survive a blank label / type / cssVariableName, and a CSS name
 * collision would shadow another variable at render time.
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $variable existing record being edited
 * @param list<array<string, mixed>> $variables full list (for collision checks)
 */
function ox_edit_variable_validate_input(array $input, array $variable, array $variables): ?WP_Error
{
    foreach (['label', 'type', 'cssVariableName'] as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        if (!is_string($input[$field]) || trim($input[$field]) === '') {
            return ox_error('oxygen_invalid_input', sprintf(
                __('`%s` must be a non-empty string when present.', domain: 'novamira-pro'),
                $field,
            ));
        }
    }
    if (array_key_exists('collection', $input) && !is_string($input['collection'])) {
        return ox_error('oxygen_invalid_input', __(
            '`collection` must be a string when present.',
            domain: 'novamira-pro',
        ));
    }
    // Reject a literal `{}` in a patched string field — Oxygen's native saver
    // rewrites `{}` to `null`, so the value could not round-trip (the write would
    // then fail its observed-state check with a misleading "storage hook" error).
    if (ox_variable_has_literal_braces([
        'label' => $input['label'] ?? null,
        'collection' => $input['collection'] ?? null,
        'value' => $input['value'] ?? null,
    ])) {
        return ox_variable_braces_error();
    }
    return ox_edit_variable_css_error($input, $variable, $variables);
}

/**
 * Validate a patched `cssVariableName`: it must be a usable CSS custom-property
 * identifier and must not collide with another variable's name. Extracted so the
 * validator stays under the cyclomatic-complexity budget. Returns a WP_Error, or
 * null (including when the patch does not touch cssVariableName).
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $variable
 * @param list<array<string, mixed>> $variables
 */
function ox_edit_variable_css_error(array $input, array $variable, array $variables): ?WP_Error
{
    if (!array_key_exists('cssVariableName', $input) || !is_string($input['cssVariableName'])) {
        return null;
    }
    $css_name = $input['cssVariableName'];
    if (!ox_valid_css_variable_name(trim($css_name))) {
        return ox_error('oxygen_invalid_input', __(
            '`cssVariableName` must be a valid CSS custom-property identifier: letters, digits, `_` or `-`, no spaces and no leading `--` (Oxygen renders it as --<name>).',
            domain: 'novamira-pro',
        ));
    }
    if (
        (string) ($variable['cssVariableName'] ?? '') !== $css_name
        && ox_find_variable_by_css_name($variables, $css_name) !== null
    ) {
        return ox_error('oxygen_invalid_input', sprintf(
            __('A variable with cssVariableName `%s` already exists.', domain: 'novamira-pro'),
            $css_name,
        ));
    }
    return null;
}
