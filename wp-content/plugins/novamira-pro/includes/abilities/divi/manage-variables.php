<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use ET\Builder\Packages\GlobalData\GlobalData;
use WP_Error;

/**
 * Abilities: list, create, edit, and delete Divi 5 global variables (design
 * tokens).
 *
 * A variable is a named, reusable value — spacing, size, radius, a string, an
 * image URL, a link — referenced anywhere as `var(--<gvid>)`. Define a token
 * once and a change propagates everywhere it is used. Tokens are stored as
 * `gvid-<uuid>` under a type bucket in the `global_variables` product option
 * (`et_divi_global_variables`); writes go through
 * `GlobalData::set_global_variables()`, which is capability-gated.
 *
 * Covered types: numbers, strings, images, links. Color tokens are managed as
 * global colors (novamira/divi-*-global-color); font variables come from the
 * theme's typography and are read-only here.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-list-variables', [
    'label' => __('List Divi Variables', domain: 'novamira-pro'),
    'description' => __(
        'Lists this site\'s Divi global variables (design tokens) as rows: id (a "gvid-…"), type (numbers, strings, images, links, fonts), label, value, var (the CSS value to reference, e.g. "var(--gvid-…)"), and builtin (true for the theme\'s font variables, which are read-only here). Reference a token anywhere a CSS value is accepted by setting that field to the row\'s var() value with novamira/divi-edit-module.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'variables' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_variables',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover design tokens before creating them or referencing them. Reference a token by setting a module value to the row\'s "var" value.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-create-variable', [
    'label' => __('Create Divi Variable', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new design token of a given "type" (numbers, strings, images, or links) with a "label" and "value", and returns its id (a "gvid-…") and "var(--gvid-…)". Use numbers for sizes/spacing/radii (e.g. "16px", "1.5rem", "clamp(1rem,2vw,3rem)"), strings for arbitrary text, images for an image URL, links for a URL. Reference it by setting a module value to the returned var() value via novamira/divi-edit-module. For brand colors use novamira/divi-create-global-color instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'enum' => ['numbers', 'strings', 'images', 'links'],
                'description' => 'Token type.',
            ],
            'label' => ['type' => 'string', 'description' => 'Human-readable name, e.g. "Section spacing".'],
            'value' => [
                'type' => 'string',
                'description' => 'The token value, e.g. "16px", "clamp(1rem,2vw,3rem)", a URL.',
            ],
            'order' => ['type' => 'integer', 'description' => 'Display order within the type (optional).'],
        ],
        'required' => ['type', 'label', 'value'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'var' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_create_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Define a spacing/size/string/url token once, then reference its "var(--gvid-…)" across modules instead of repeating literal values.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/divi-edit-variable', [
    'label' => __('Edit Divi Variable', domain: 'novamira-pro'),
    'description' => __(
        'Changes a design token\'s "label", "value", and/or "order" by id (a "gvid-…" from novamira/divi-list-variables). The token\'s type is fixed (delete and recreate to change it). Everything referencing the token updates at once. Built-in font variables are not editable here.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variable id, e.g. "gvid-…".'],
            'label' => ['type' => 'string', 'description' => 'New label (optional).'],
            'value' => ['type' => 'string', 'description' => 'New value (optional).'],
            'order' => ['type' => 'integer', 'description' => 'New display order (optional).'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'var' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_edit_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update — provide "label", "value", and/or "order". Editing a token updates everything that references it.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-delete-variable', [
    'label' => __('Delete Divi Variable', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a design token by id (a "gvid-…"). Anything still referencing it falls back to the last value Divi resolved. Built-in font variables cannot be deleted.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variable id to delete, e.g. "gvid-…".'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_delete_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes a design token. Built-in font variables are not deletable.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * The token types this surface can create and manage. Colors are handled as
 * global colors; fonts come from the theme typography and are read-only here.
 *
 * @return list<string>
 */
function divi_variable_types(): array
{
    return ['numbers', 'strings', 'images', 'links'];
}

/**
 * `var(--<gvid>)` CSS value used to reference a token.
 */
function divi_variable_var(string $id): string
{
    return 'var(--' . $id . ')';
}

/**
 * Coerce a possibly-mixed stored order to a non-negative int (0 when absent or
 * non-numeric).
 */
function divi_int_or_zero(mixed $value): int
{
    if (is_int($value)) {
        return $value;
    }
    return is_string($value) && is_numeric($value) ? (int) $value : 0;
}

/**
 * Confirm the current user can persist variables — `set_global_variables()`
 * silently no-ops otherwise, so reject up front rather than report a false
 * success.
 */
function divi_variables_writable_or_error(): ?WP_Error
{
    if (!class_exists(GlobalData::class)) {
        return divi_error(
            'unavailable',
            message: 'The Divi 5 global-data API is unavailable on this site.',
            status: 422,
        );
    }
    if (!current_user_can('edit_theme_options')) {
        return divi_error(
            'forbidden',
            message: 'Managing Divi variables requires the edit_theme_options capability.',
            status: 403,
        );
    }
    if (!et_pb_is_allowed('variables_manager')) {
        return divi_error(
            'forbidden',
            message: 'The Divi Role Editor does not permit this user to manage variables (variables_manager).',
            status: 403,
        );
    }
    return null;
}

/**
 * Raw global-variables store as `type => id => item`, casting Divi's per-type
 * objects to arrays. Includes built-in font variables.
 *
 * @return array<string, array<string, array<array-key, mixed>>>
 */
function divi_global_variables_raw(): array
{
    if (!class_exists(GlobalData::class)) {
        return [];
    }
    $out = [];
    foreach (GlobalData::get_global_variables() as $type => $items) {
        $bucket = [];
        /** @var mixed $item */
        foreach (get_object_vars($items) as $id => $item) {
            if (!is_string($id) || !is_array($item)) {
                continue;
            }
            $bucket[$id] = $item;
        }
        $out[$type] = $bucket;
    }
    return $out;
}

/**
 * The writable custom tokens (`gvid-` ids only) for the managed types, each
 * reduced to its persistable fields. This is the payload base for every write,
 * so built-in font variables and the read-only `colors`/`allowedActions` data
 * are never written back.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */
/**
 * A label Divi's storage treats as empty and silently drops. Divi guards items
 * with `empty($item['label'])`, and PHP's empty() is true for both "" and the
 * string "0" — so a label that sanitizes to either would make Divi discard the
 * token while the ability reported success (a phantom id on create, a silent
 * delete on edit).
 */
function divi_variable_label_is_empty(string $label): bool
{
    $sanitized = sanitize_text_field(trim($label));
    return $sanitized === '' || $sanitized === '0';
}

/**
 * Persist the managed-variables map. `set_global_variables()` REPLACES the
 * whole stored blob, so we merge the managed buckets over the raw stored data:
 * every OTHER bucket (notably `fonts`, plus any future type) is carried through
 * verbatim, or a write would wipe it (e.g. imported font variables).
 *
 * @param array<string, array<string, array<string, mixed>>> $map
 */
function divi_persist_variables(array $map): void
{
    $merged = [];
    /** @var mixed $items */
    foreach (divi_global_variables_raw() as $type => $items) {
        if (in_array($type, divi_variable_types(), strict: true)) {
            continue;
        }
        $merged[$type] = $items;
    }
    foreach ($map as $type => $items) {
        $merged[$type] = $items;
    }
    GlobalData::set_global_variables($merged);
}

/**
 * Rebuild the managed-variables map (numbers, strings, images, links) as
 * normalized rows, keyed by type then id.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */
function divi_custom_variables_map(): array
{
    $raw = divi_global_variables_raw();
    $out = [];
    foreach (divi_variable_types() as $type) {
        $items = is_array($raw[$type] ?? null) ? $raw[$type] : [];
        $bucket = [];
        foreach ($items as $id => $item) {
            if (!str_starts_with($id, 'gvid-')) {
                continue;
            }
            $bucket[$id] = [
                'id' => $id,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'value' => is_string($item['value'] ?? null) ? $item['value'] : '',
                'order' => divi_int_or_zero($item['order'] ?? null),
                'status' => is_string($item['status'] ?? null) ? $item['status'] : 'active',
            ];
        }
        $out[$type] = $bucket;
    }
    return $out;
}

/**
 * The type bucket holding a variable id, or null when no managed token matches.
 *
 * @param array<string, array<string, array<string, mixed>>> $map
 */
function divi_find_variable_type(array $map, string $id): ?string
{
    foreach ($map as $type => $items) {
        if (array_key_exists($id, $items)) {
            return $type;
        }
    }
    return null;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function divi_list_variables(array $input): array
{
    $rows = [];
    foreach (divi_global_variables_raw() as $type => $items) {
        foreach ($items as $id => $item) {
            $rows[] = [
                'id' => $id,
                'type' => $type,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'value' => is_string($item['value'] ?? null) ? $item['value'] : '',
                'builtin' => !str_starts_with($id, 'gvid-'),
                'var' => divi_variable_var($id),
            ];
        }
    }
    return ['variables' => $rows, 'total' => count($rows)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_create_variable(array $input)
{
    $guard = divi_variables_writable_or_error();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $type = trim(divi_string_arg($input, key: 'type'));
    if (!in_array($type, divi_variable_types(), strict: true)) {
        return divi_error(
            'invalid_input',
            message: 'A "type" of numbers, strings, images, or links is required. For brand colors use novamira/divi-create-global-color; font variables come from theme typography.',
        );
    }
    $label = trim(divi_string_arg($input, key: 'label'));
    if (divi_variable_label_is_empty($label)) {
        return divi_error(
            'invalid_input',
            message: 'A "label" is required and cannot be "0" or only HTML/markup — Divi treats those as empty and would silently drop the token.',
        );
    }
    $value = trim(divi_string_arg($input, key: 'value'));
    if ($value === '') {
        return divi_error(
            'invalid_input',
            message: 'A "value" is required, e.g. "16px", "1.5rem", or "clamp(1rem,2vw,3rem)".',
        );
    }

    return divi_with_global_lock('variables', static function () use ($type, $label, $value, $input) {
        $map = divi_custom_variables_map();
        $bucket = is_array($map[$type] ?? null) ? $map[$type] : [];
        $order = divi_int_arg_or_null($input, key: 'order') ?? (count($bucket) + 1);
        $id = 'gvid-' . divi_generate_uuid();
        $bucket[$id] = ['id' => $id, 'label' => $label, 'value' => $value, 'order' => $order, 'status' => 'active'];
        $map[$type] = $bucket;

        divi_persist_variables($map);

        return ['id' => $id, 'type' => $type, 'var' => divi_variable_var($id)];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_edit_variable(array $input)
{
    $guard = divi_variables_writable_or_error();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $id = trim(divi_string_arg($input, key: 'id'));
    if ($id === '') {
        return divi_error(
            'invalid_input',
            message: 'An "id" is required (a "gvid-…" from novamira/divi-list-variables).',
        );
    }

    return divi_with_global_lock('variables', static function () use ($id, $input) {
        $map = divi_custom_variables_map();
        $type = divi_find_variable_type($map, $id);
        if ($type === null) {
            return divi_error(
                'not_found',
                message: sprintf(
                    'No editable variable "%s". Use novamira/divi-list-variables (built-in font variables are read-only).',
                    $id,
                ),
                status: 404,
            );
        }

        $item = $map[$type][$id];
        $changed = false;

        if (array_key_exists('label', $input)) {
            $label = trim(divi_string_arg($input, key: 'label'));
            if (divi_variable_label_is_empty($label)) {
                return divi_error(
                    'invalid_input',
                    message: 'A new "label" cannot be "0" or only HTML/markup — Divi treats those as empty and would silently drop the token.',
                );
            }
            $item['label'] = $label;
            $changed = true;
        }
        if (array_key_exists('value', $input)) {
            $value = trim(divi_string_arg($input, key: 'value'));
            if ($value === '') {
                return divi_error('invalid_input', message: '"value" cannot be empty.');
            }
            $item['value'] = $value;
            $changed = true;
        }
        $order = divi_int_arg_or_null($input, key: 'order');
        if ($order !== null) {
            $item['order'] = $order;
            $changed = true;
        }
        if (!$changed) {
            return divi_error('invalid_input', message: 'Provide "label", "value", and/or "order" to change.');
        }

        $map[$type][$id] = $item;
        divi_persist_variables($map);

        return ['id' => $id, 'type' => $type, 'var' => divi_variable_var($id)];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_delete_variable(array $input)
{
    $guard = divi_variables_writable_or_error();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $id = trim(divi_string_arg($input, key: 'id'));
    if ($id === '') {
        return divi_error('invalid_input', message: 'An "id" is required (a "gvid-…").');
    }

    return divi_with_global_lock('variables', static function () use ($id) {
        $map = divi_custom_variables_map();
        $type = divi_find_variable_type($map, $id);
        if ($type === null) {
            return divi_error(
                'not_found',
                message: sprintf(
                    'No deletable variable "%s". Use novamira/divi-list-variables (built-in font variables can\'t be deleted).',
                    $id,
                ),
                status: 404,
            );
        }

        unset($map[$type][$id]);
        divi_persist_variables($map);

        return ['id' => $id, 'deleted' => true];
    });
}
