<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-create-variable', [
    'label' => __('Create Oxygen Variable', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Oxygen design variable. Required: `label` (human-readable name, e.g. "Brand Primary"), `value` (the literal value — a color hex, a length, a number, etc.), `type` (variable kind — one of `text`, `number`, `unit`, `color`, `font_family`, `image_url`; there is NO `size`/`typography`/`shadow` kind, those render broken). Optional: `id` (defaults to a slug derived from `label`), `cssVariableName` (defaults to a slug too; must be a valid CSS custom-property identifier), `collection` (group the variable belongs to). The variable is appended to the global variables JSON blob and the response returns the full new variable.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'label' => ['type' => 'string', 'description' => 'Display name shown in the builder UI.'],
            'value' => [
                'type' => ['string', 'number', 'boolean', 'array', 'object', 'null'],
                'description' => 'Variable value. Type depends on `type` (string for color/size, object for typography, ...).',
            ],
            'type' => [
                'type' => 'string',
                'enum' => ['text', 'number', 'unit', 'color', 'font_family', 'image_url'],
                'description' => 'Variable kind. Must be one Oxygen actually decodes: `text`, `number`, `unit`, `color`, `font_family`, `image_url`. (There is no `typography`/`shadow`/`size` variable kind — those render broken.) The `value` shape must suit the type: a scalar for `text`/`number`/`unit`; a color string or `{value: "..."}` for `color`; a URL string or `{url: "..."}` for `image_url`.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_create_variable',
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
function oxygen_create_variable(array $input): array|WP_Error
{
    $bail = ox_create_variable_validate($input);
    if ($bail !== null) {
        return $bail;
    }
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_lock('variables', static fn(): array|WP_Error => ox_create_variable_locked($input));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_create_variable_locked(array $input): array|WP_Error
{
    /** @var string $raw_label */
    $raw_label = $input['label'];
    /** @var string $raw_type */
    $raw_type = $input['type'];
    $label = trim($raw_label);
    $id = ox_pick_string(input: $input, key: 'id', fallback: ox_make_id($label));
    $css_name = ox_pick_string(input: $input, key: 'cssVariableName', fallback: ox_make_id($label));

    $variables = ox_read_variables();
    if (ox_find_variable($variables, $id) !== null) {
        return ox_error('oxygen_invalid_input', sprintf(
            __('A variable with id `%s` already exists.', domain: 'novamira-pro'),
            $id,
        ));
    }
    if (ox_find_variable_by_css_name($variables, $css_name) !== null) {
        return ox_error('oxygen_invalid_input', sprintf(
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
    $written = ox_write_variables($variables);
    if ($written instanceof WP_Error) {
        return $written;
    }
    // Observed-state: re-read the option and confirm the variable landed AS
    // REQUESTED, field-by-field — not merely that a row with this id exists. A
    // storage hook could keep the id while altering the fields, or block the
    // write entirely; either way an existence-only check would report a
    // successful creation. Return the OBSERVED row on success.
    $hit = ox_find_variable(ox_read_variables(), $id);
    if ($hit === null) {
        return ox_error('oxygen_write_not_applied', sprintf(
            __('Variable `%s` did not persist; a storage hook may have blocked the write.', domain: 'novamira-pro'),
            $id,
        ));
    }
    [, $observed] = $hit;
    $mismatch = ox_variable_row_mismatch($new_variable, $observed);
    if ($mismatch !== null) {
        return ox_error('oxygen_write_not_applied', sprintf(
            /* translators: 1: variable id, 2: field name */
            __(
                'Variable `%1$s` did not persist as requested: the `%2$s` field differs from what was written (a storage hook may have altered it).',
                domain: 'novamira-pro',
            ),
            $id,
            $mismatch,
        ));
    }
    return ['success' => true, 'variable' => $observed];
}

/**
 * Validate the create-variable inputs. Returns a WP_Error to bail with,
 * or null when inputs look usable.
 *
 * @param array<string, mixed> $input
 */
function ox_create_variable_validate(array $input): ?WP_Error
{
    $required_error = ox_create_variable_required_error($input);
    if ($required_error instanceof WP_Error) {
        return $required_error;
    }
    // An explicit cssVariableName must be a usable custom-property identifier —
    // Oxygen renders it as --<name>, so `bad name` produces `--bad name: …`,
    // which the browser drops. The slugified default is always safe.
    if (
        isset($input['cssVariableName'])
        && is_string($input['cssVariableName'])
        && !ox_valid_css_variable_name(trim($input['cssVariableName']))
    ) {
        return ox_error('oxygen_invalid_input', __(
            '`cssVariableName` must be a valid CSS custom-property identifier: letters, digits, `_` or `-`, no spaces and no leading `--` (Oxygen renders it as --<name>).',
            domain: 'novamira-pro',
        ));
    }
    // Reject a literal `{}` in any string field — Oxygen's native saver rewrites
    // `{}` to `null`, so the value cannot round-trip (see helper).
    if (ox_variable_has_literal_braces([
        'id' => $input['id'] ?? null,
        'label' => $input['label'] ?? null,
        'collection' => $input['collection'] ?? null,
        'value' => $input['value'] ?? null,
    ])) {
        return ox_variable_braces_error();
    }
    // Fail hard on a type Oxygen cannot decode, or a value shape that would
    // render `--<name>: Array` — validated against the native macro contract.
    // (Presence + string-ness already ensured by ox_create_variable_required_error.)
    $type = is_string($input['type'] ?? null) ? trim($input['type']) : '';
    return ox_validate_variable_type_value($type, $input['value'] ?? null);
}

/**
 * Presence/type checks for the three required create-variable fields (`label`,
 * `value`, `type`). Extracted to keep ox_create_variable_validate under the
 * cyclomatic-complexity budget. Returns a WP_Error, or null.
 *
 * @param array<string, mixed> $input
 */
function ox_create_variable_required_error(array $input): ?WP_Error
{
    if (!isset($input['label']) || !is_string($input['label']) || trim($input['label']) === '') {
        return ox_error('oxygen_invalid_input', __(
            '`label` is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    if (!array_key_exists('value', $input)) {
        return ox_error('oxygen_invalid_input', __('`value` is required.', domain: 'novamira-pro'));
    }
    if (!isset($input['type']) || !is_string($input['type']) || trim($input['type']) === '') {
        return ox_error('oxygen_invalid_input', __(
            '`type` is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}
