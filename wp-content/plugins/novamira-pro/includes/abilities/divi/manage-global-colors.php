<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use ET\Builder\Packages\GlobalData\GlobalData;
use WP_Error;

/**
 * Abilities: list, create, edit, and delete Divi 5 global colors (the brand
 * palette).
 *
 * A global color is a named, reusable color: define your brand colors once and
 * bind any module's color field to one via `var(--<gcid>)`, so a palette change
 * restyles the whole site. Custom colors are stored as `gcid-<uuid>` in the
 * `et_global_data` option (`global_colors`); the five built-in theme colors
 * (primary/secondary/heading/body/link) live in the Customizer and are editable
 * but not deletable. All writes go through `GlobalData::set_global_colors()`.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-list-global-colors', [
    'label' => __('List Divi Global Colors', domain: 'novamira-pro'),
    'description' => __(
        'Lists this site\'s Divi global colors (the brand palette) as rows: id (a "gcid-…"), color, status, named (true for the five built-in theme colors — primary, secondary, heading, body, link), and var (the CSS value to bind with, e.g. "var(--gcid-…)"). Bind a module color field to a global color by setting it to that var() value with novamira/divi-edit-module. Only the five named colors means no custom colors exist yet.',
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
            'colors' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_global_colors',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover the brand palette before creating colors or binding modules to them. Bind via a module color field = the row\'s "var" value.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-create-global-color', [
    'label' => __('Create Divi Global Color', domain: 'novamira-pro'),
    'description' => __(
        'Adds a new custom color to the brand palette and returns its id (a "gcid-…") and the "var(--gcid-…)" you bind with. Pass "color" as any CSS color (hex, rgb(a), hsl, …). Bind a module to it by setting a color field to the returned var() value via novamira/divi-edit-module — then a palette change restyles every bound element at once.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'color' => ['type' => 'string', 'description' => 'CSS color value, e.g. "#2ea3f2", "rgba(0,0,0,.5)".'],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'inactive', 'temporary'],
                'description' => 'Palette status (default "active").',
            ],
        ],
        'required' => ['color'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'color' => ['type' => 'string'],
            'var' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_create_global_color',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Define a brand color once, then bind modules to its "var(--gcid-…)" value instead of hardcoding the same hex everywhere.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/divi-edit-global-color', [
    'label' => __('Edit Divi Global Color', domain: 'novamira-pro'),
    'description' => __(
        'Changes a global color\'s value and/or status by id (a "gcid-…" from novamira/divi-list-global-colors). Works for custom palette colors and for the five built-in theme colors (primary/secondary/heading/body/link) — editing a named color updates it site-wide. Every element bound to the color updates at once.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Color id, e.g. "gcid-…" (from divi-list-global-colors).'],
            'color' => ['type' => 'string', 'description' => 'New CSS color value (optional).'],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'inactive', 'temporary'],
                'description' => 'New status (optional).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'color' => ['type' => 'string'],
            'var' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_edit_global_color',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update — provide "color" and/or "status". Editing a global color restyles every element bound to it.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-delete-global-color', [
    'label' => __('Delete Divi Global Color', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a custom global color by id (a "gcid-…"). The five built-in theme colors (primary/secondary/heading/body/link) cannot be deleted — edit their value instead. Elements still bound to a deleted color keep the last value Divi resolved for it.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Custom color id to delete, e.g. "gcid-…".'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_delete_global_color',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes a custom brand color. Built-in theme colors are not deletable — edit them instead.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * The global-color palette as a map of id => data array (customizer + custom),
 * or [] when the Divi 5 global-data API is unavailable.
 *
 * @return array<string, array<array-key, mixed>>
 */
function divi_global_colors_map(): array
{
    if (!class_exists(GlobalData::class)) {
        return [];
    }
    $out = [];
    /** @var mixed $item */
    foreach (GlobalData::get_global_colors() as $id => $item) {
        if (!is_array($item)) {
            continue;
        }
        // The 5 built-in "customizer" colors are re-merged by Divi from a
        // per-request static cache that set_global_colors() never resets, so a
        // same-request read-back is stale — and a subsequent whole-map write
        // would persist that stale value, reverting an earlier edit. Read each
        // named color's value straight from its Customizer option so the map is
        // always fresh.
        $fresh = divi_named_color_value($id);
        if ($fresh !== null) {
            $item['color'] = $fresh;
        }
        $out[$id] = $item;
    }
    return $out;
}

/**
 * The current value of a built-in "customizer" color, read from its backing
 * Customizer option (bypassing Divi's stale per-request static cache), or null
 * when `$id` is not one of the five named colors.
 */
function divi_named_color_value(string $id): ?string
{
    if (!class_exists(GlobalData::class) || !isset(GlobalData::$customizer_colors[$id])) {
        return null;
    }
    /** @var array<string, mixed> $spec */
    $spec = GlobalData::$customizer_colors[$id];
    $option = is_string($spec['option_name'] ?? null) ? $spec['option_name'] : '';
    $default = is_string($spec['default'] ?? null) ? $spec['default'] : '';
    if ($option === '' || !function_exists('et_get_option')) {
        return null;
    }
    /** @var mixed $value */
    $value = et_get_option($option, $default);
    return is_string($value) ? $value : $default;
}

/**
 * Whether a palette entry is one of the five built-in Customizer theme colors
 * (editable site-wide, but not deletable).
 *
 * @param array<array-key, mixed> $item
 */
function divi_color_is_named(array $item): bool
{
    return ($item['folder'] ?? null) === 'customizer';
}

/**
 * Normalize the requested palette status to one of Divi's allowed values,
 * defaulting to "active".
 *
 * @param array<string, mixed> $input
 */
function divi_color_status_arg(array $input): string
{
    $status = trim(divi_string_arg($input, key: 'status'));
    return in_array($status, ['active', 'inactive', 'temporary'], strict: true) ? $status : 'active';
}

/**
 * Divi's ISO-8601 millisecond timestamp string for a color's `lastUpdated`.
 */
function divi_color_timestamp(): string
{
    return gmdate('Y-m-d\TH:i:s') . '.000Z';
}

/**
 * The `var(--<gcid>)` CSS value used to bind a module color field to a global
 * color.
 */
function divi_color_var(string $id): string
{
    return 'var(--' . $id . ')';
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function divi_list_global_colors(array $input): array
{
    $rows = [];
    foreach (divi_global_colors_map() as $id => $item) {
        $rows[] = [
            'id' => $id,
            'color' => is_string($item['color'] ?? null) ? $item['color'] : '',
            'status' => is_string($item['status'] ?? null) ? $item['status'] : 'active',
            'named' => divi_color_is_named($item),
            'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
            'var' => divi_color_var($id),
        ];
    }
    return ['colors' => $rows, 'total' => count($rows)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_create_global_color(array $input)
{
    if (!class_exists(GlobalData::class)) {
        return divi_error(
            'unavailable',
            message: 'The Divi 5 global-data API is unavailable on this site.',
            status: 422,
        );
    }
    $color = trim(divi_string_arg($input, key: 'color'));
    if ($color === '') {
        return divi_error('invalid_input', message: 'A "color" value is required, e.g. "#2ea3f2" or "rgba(0,0,0,.5)".');
    }

    return divi_with_global_lock('colors', static function () use ($color, $input) {
        $map = divi_global_colors_map();
        $id = 'gcid-' . divi_generate_uuid();
        $map[$id] = [
            'color' => $color,
            'status' => divi_color_status_arg($input),
            'lastUpdated' => divi_color_timestamp(),
            'usedInPosts' => [],
        ];
        GlobalData::set_global_colors($map);

        return ['id' => $id, 'color' => $color, 'var' => divi_color_var($id)];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_edit_global_color(array $input)
{
    if (!class_exists(GlobalData::class)) {
        return divi_error(
            'unavailable',
            message: 'The Divi 5 global-data API is unavailable on this site.',
            status: 422,
        );
    }
    $id = trim(divi_string_arg($input, key: 'id'));
    if ($id === '') {
        return divi_error(
            'invalid_input',
            message: 'An "id" is required (a "gcid-…" from novamira/divi-list-global-colors).',
        );
    }

    return divi_with_global_lock('colors', static function () use ($id, $input) {
        $map = divi_global_colors_map();
        if (!array_key_exists($id, $map)) {
            return divi_error(
                'not_found',
                message: sprintf('No global color "%s". Use novamira/divi-list-global-colors.', $id),
                status: 404,
            );
        }

        $item = $map[$id];
        $changed = false;

        $color = trim(divi_string_arg($input, key: 'color'));
        if ($color !== '') {
            $item['color'] = $color;
            $changed = true;
        }
        if (array_key_exists('status', $input)) {
            $item['status'] = divi_color_status_arg($input);
            $changed = true;
        }
        if (!$changed) {
            return divi_error('invalid_input', message: 'Provide "color" and/or "status" to change.');
        }

        $item['lastUpdated'] = divi_color_timestamp();
        $map[$id] = $item;
        GlobalData::set_global_colors($map);

        return [
            'id' => $id,
            'color' => is_string($item['color'] ?? null) ? $item['color'] : '',
            'var' => divi_color_var($id),
        ];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_delete_global_color(array $input)
{
    if (!class_exists(GlobalData::class)) {
        return divi_error(
            'unavailable',
            message: 'The Divi 5 global-data API is unavailable on this site.',
            status: 422,
        );
    }
    $id = trim(divi_string_arg($input, key: 'id'));
    if ($id === '') {
        return divi_error('invalid_input', message: 'An "id" is required (a "gcid-…").');
    }

    return divi_with_global_lock('colors', static function () use ($id) {
        $map = divi_global_colors_map();
        if (!array_key_exists($id, $map)) {
            return divi_error(
                'not_found',
                message: sprintf('No global color "%s". Use novamira/divi-list-global-colors.', $id),
                status: 404,
            );
        }
        if (divi_color_is_named($map[$id])) {
            return divi_error(
                'not_deletable',
                message: sprintf(
                    '"%s" is a built-in theme color and cannot be deleted; edit its value with novamira/divi-edit-global-color instead.',
                    $id,
                ),
                status: 422,
            );
        }

        unset($map[$id]);
        GlobalData::set_global_colors($map);

        return ['id' => $id, 'deleted' => true];
    });
}
