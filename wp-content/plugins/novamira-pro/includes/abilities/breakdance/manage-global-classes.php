<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Global classes ("CSS selectors" in Breakdance terminology) are
 * reusable style sets you can attach to elements. Each entry has
 * shape `{name, type: 'class'|'custom', properties: PropertiesData}`.
 * Class `name` acts as the primary key — it must be unique and is
 * referenced by elements that use the class.
 */

// list-global-classes -------------------------------------------------------

wp_register_ability('novamira/breakdance-list-global-classes', [
    'label' => __('List Breakdance Global Classes', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Breakdance global class (reusable CSS class) defined for the project. Each row is a compact summary: `name` (used as the id), `type` (`class` for normal CSS classes, `custom` for custom selectors), and `property_count`. To inspect the full `properties` blob of a specific class call `get-global-class`.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'enum' => ['class', 'custom'],
                'description' => 'Optional filter: return only entries of this kind.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'classes' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['classes', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_list_global_classes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to discover class names before calling get-global-class, edit-global-class or referencing the class on an element.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// get-global-class ----------------------------------------------------------

wp_register_ability('novamira/breakdance-get-global-class', [
    'label' => __('Get Breakdance Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full record of a single Breakdance global class identified by `name`: `name`, `type` (`class` for regular CSS classes or `custom` for raw selectors), and the entire `properties` blob (the CSS rules attached to the class). Call list-global-classes first to discover the available `name` values, and pair with edit-global-class to patch the `properties` shape returned here.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string', 'description' => 'Class name (without leading dot).']],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'class' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_get_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The `properties` shape matches what an element\'s `data.properties` carries (same builder property tree).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// create-global-class -------------------------------------------------------

wp_register_ability('novamira/breakdance-create-global-class', [
    'label' => __('Create Breakdance Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Breakdance global class. `name` is required and acts as the unique id; pick something CSS-safe (lowercase, dashes). `type` defaults to `class`; use `custom` for custom selectors. `properties` is the CSS rule tree the class applies (same shape as element properties); omit to start with an empty class you fill later via `edit-global-class`.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => ['class', 'custom'], 'default' => 'class'],
            'properties' => ['type' => 'object', 'description' => 'Initial CSS rule tree. Defaults to `{}`.'],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'class' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_create_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Names must be unique. The class is appended to the global classes JSON blob.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

// edit-global-class ---------------------------------------------------------

wp_register_ability('novamira/breakdance-edit-global-class', [
    'label' => __('Edit Breakdance Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Patches an existing Breakdance global class. Identified by `name`; the name itself is immutable here (delete + create to rename). `properties` is shallow-merged on top of the stored properties: keys you pass replace the corresponding key, keys you omit are preserved, pass `null` to wipe a key. `type` can be changed if needed.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => ['class', 'custom']],
            'properties' => ['type' => 'object', 'description' => 'Shallow-merged on top of the stored properties.'],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'class' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_edit_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Shallow merge only. For nested properties, get the full object first via get-global-class then pass it back modified.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

// delete-global-class -------------------------------------------------------

wp_register_ability('novamira/breakdance-delete-global-class', [
    'label' => __('Delete Breakdance Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Removes a Breakdance global class by `name`. Deletion is immediate and irreversible. Elements that referenced the class continue to carry its name in their properties; they simply lose the styles the class provided.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'deleted_name' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_delete_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'No undo. Use list-global-classes to find the exact name.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

// callbacks -----------------------------------------------------------------

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function breakdance_list_global_classes(array $input): array
{
    $classes = bd_read_global_classes();
    if (isset($input['type']) && is_string($input['type'])) {
        $filter = $input['type'];
        $classes = array_values(array_filter(
            $classes,
            static fn(array $c): bool => isset($c['type']) && (string) $c['type'] === $filter,
        ));
    }
    $rows = array_map(
        static function (array $c): array {
            /** @var array<string, mixed> $props */
            $props = isset($c['properties']) && is_array($c['properties']) ? $c['properties'] : [];
            return [
                'name' => isset($c['name']) && is_string($c['name']) ? $c['name'] : '',
                'type' => isset($c['type']) && is_string($c['type']) ? $c['type'] : 'class',
                'property_count' => count($props),
            ];
        },
        $classes,
    );
    return ['classes' => $rows, 'total' => count($rows)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_get_global_class(array $input): array|WP_Error
{
    if (!isset($input['name']) || !is_string($input['name']) || trim($input['name']) === '') {
        return bd_error('bd_invalid_input', __('`name` is required.', domain: 'novamira-pro'));
    }
    $name = trim($input['name']);
    $classes = bd_read_global_classes();
    $hit = bd_find_global_class($classes, $name);
    if ($hit === null) {
        return bd_error('bd_not_found', sprintf(__('Global class `%s` not found.', domain: 'novamira-pro'), $name));
    }
    [, $class] = $hit;
    return ['success' => true, 'class' => $class];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_create_global_class(array $input): array|WP_Error
{
    if (!isset($input['name']) || !is_string($input['name']) || trim($input['name']) === '') {
        return bd_error('bd_invalid_input', __('`name` is required.', domain: 'novamira-pro'));
    }
    $name = trim($input['name']);
    $type = isset($input['type'])
    && is_string($input['type'])
    && in_array($input['type'], ['class', 'custom'], strict: true)
        ? $input['type']
        : 'class';
    /** @var array<string, mixed> $properties */
    $properties = isset($input['properties']) && is_array($input['properties']) ? $input['properties'] : [];

    // Round 11 B15 — `breakdance_classes_json_string` is a read-modify-
    // write blob shared by all three mutation paths (create / edit /
    // delete). Parallel writers were losing ~22% of updates. Serialise
    // every mutation through `bd_with_lock('global_classes', …)` —
    // separate slot from variables and global_settings so the three
    // surfaces don't block each other.
    return bd_with_lock('global_classes', static function () use ($name, $type, $properties): array|WP_Error {
        $classes = bd_read_global_classes();
        if (bd_find_global_class($classes, $name) !== null) {
            return bd_error('bd_invalid_input', sprintf(
                __('A global class with name `%s` already exists.', domain: 'novamira-pro'),
                $name,
            ));
        }
        $new_class = ['name' => $name, 'type' => $type, 'properties' => $properties];
        $classes[] = $new_class;
        $write = bd_write_global_classes($classes);
        if ($write instanceof WP_Error) {
            return $write;
        }

        return ['success' => true, 'class' => $new_class];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_edit_global_class(array $input): array|WP_Error
{
    if (!isset($input['name']) || !is_string($input['name']) || trim($input['name']) === '') {
        return bd_error('bd_invalid_input', __('`name` is required.', domain: 'novamira-pro'));
    }
    $name = trim($input['name']);
    return bd_with_lock('global_classes', static function () use ($name, $input): array|WP_Error {
        $classes = bd_read_global_classes();
        $hit = bd_find_global_class($classes, $name);
        if ($hit === null) {
            return bd_error('bd_not_found', sprintf(__('Global class `%s` not found.', domain: 'novamira-pro'), $name));
        }
        [$index, $class] = $hit;

        if (
            isset($input['type'])
            && is_string($input['type'])
            && in_array($input['type'], ['class', 'custom'], strict: true)
        ) {
            $class['type'] = $input['type'];
        }
        if (isset($input['properties']) && is_array($input['properties'])) {
            /** @var array<string, mixed> $existing */
            $existing = isset($class['properties']) && is_array($class['properties']) ? $class['properties'] : [];
            /** @var array<string, mixed> $patch */
            $patch = $input['properties'];
            $merged = array_merge($existing, $patch);
            /** @var mixed $v */
            foreach ($patch as $k => $v) {
                if ($v !== null) {
                    continue;
                }

                unset($merged[$k]);
            }
            $class['properties'] = $merged;
        }

        $classes[$index] = $class;
        $write = bd_write_global_classes($classes);
        if ($write instanceof WP_Error) {
            return $write;
        }

        return ['success' => true, 'class' => $class];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_delete_global_class(array $input): array|WP_Error
{
    if (!isset($input['name']) || !is_string($input['name']) || trim($input['name']) === '') {
        return bd_error('bd_invalid_input', __('`name` is required.', domain: 'novamira-pro'));
    }
    $name = trim($input['name']);
    return bd_with_lock('global_classes', static function () use ($name): array|WP_Error {
        $classes = bd_read_global_classes();
        $hit = bd_find_global_class($classes, $name);
        if ($hit === null) {
            return bd_error('bd_not_found', sprintf(__('Global class `%s` not found.', domain: 'novamira-pro'), $name));
        }
        [$index] = $hit;
        array_splice(array: $classes, offset: $index, length: 1);
        $write = bd_write_global_classes($classes);
        if ($write instanceof WP_Error) {
            return $write;
        }

        return ['success' => true, 'deleted_name' => $name];
    });
}
