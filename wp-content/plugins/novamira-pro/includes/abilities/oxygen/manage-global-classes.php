<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Global classes ("CSS selectors" in the Soflyy engine's terminology)
 * are reusable style sets you can attach to elements. Each entry has
 * native shape `{id,name,type,properties,children,collection}`. The stable
 * `id` is the binding key stored on elements; `name` is the emitted CSS class
 * token (or custom selector text).
 */

// list-global-classes -------------------------------------------------------

wp_register_ability('novamira/oxygen-list-global-classes', [
    'label' => __('List Oxygen Global Classes', domain: 'novamira-pro'),
    'description' => __(
        'Lists Oxygen 6 native selectors from `oxy_selectors_json_string`. Each compact row includes stable `id`, emitted `name`, `type`, `collection`, and `property_count`. Elements bind selector ids at `properties.meta.classes`; use apply-global-class rather than writing that list manually.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_list_global_classes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to discover stable selector ids before get/edit/apply. Names are CSS output; ids are persisted element bindings.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// get-global-class ----------------------------------------------------------

wp_register_ability('novamira/oxygen-get-global-class', [
    'label' => __('Get Oxygen Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Returns a native Oxygen selector by stable `id` (preferred) or `name`: `{id,name,type,collection,properties,children}`. Use get-global-class-schema before constructing properties.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Stable Oxygen selector id. Preferred.'],
            'name' => ['type' => 'string', 'description' => 'Compatibility lookup by emitted class/selector name.'],
        ],
        'anyOf' => [['required' => ['id']], ['required' => ['name']]],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_get_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Selector properties have their own live schema; call oxygen-get-global-class-schema. They are not element `design.*` properties.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// get-global-class-schema ---------------------------------------------------

wp_register_ability('novamira/oxygen-get-global-class-schema', [
    'label' => __('Get Oxygen Global Class Schema', domain: 'novamira-pro'),
    'description' => __(
        'Discovers the property vocabulary Oxygen 6 native selectors actually render. With no arguments returns compact top-level groups; pass `group` or `search` to receive control paths. Every control ships a `sample`: a complete, ready-to-send `properties` fragment that is verified to emit CSS, including any companion the renderer requires (gap and flex alignment only render inside `display:flex`) — copy the sample and change its values rather than assembling a path by hand. Paths are rooted at `properties.*` and follow Oxygen mode, which differs from Breakdance mode on the compound controls: padding and margin live under `spacing.spacing.*`, radius under `borders.border_radius`, gap carries `row` and `column`, alignment lives under `layout.flex_align`, and raw CSS under `custom_css.custom_css` (where `:selector` is replaced with the class). Also returns the currently registered breakpoint ids.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'group' => ['type' => 'string'],
            'search' => ['type' => 'string'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'groups' => ['type' => 'array'],
            'controls' => ['type' => 'array'],
            'breakpoints' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['breakpoints', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_get_global_class_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once, then submit only paths returned here. The write abilities reject unknown paths before Oxygen can silently ignore them.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// create-global-class -------------------------------------------------------

wp_register_ability('novamira/oxygen-create-global-class', [
    'label' => __('Create Oxygen Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Creates a native Oxygen selector. `name` is the emitted CSS token, while Oxygen generates a separate stable `id` used by element bindings. `collection` defaults to `Novamira`. Properties must use paths discovered by get-global-class-schema; element-style `design.*` input is rejected before write. A literal empty block `{}` anywhere in the row is rejected because Oxygen\'s native saver rewrites it to `null`.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => ['class', 'custom'], 'default' => 'class'],
            'collection' => ['type' => 'string', 'default' => 'Novamira'],
            'properties' => [
                'type' => 'object',
                'description' => 'Native selector property tree from get-global-class-schema. Defaults to `{}`.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'class' => ['type' => 'object'],
            'verification' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_create_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Names must be unique. The ability validates against the live selector schema, writes both selector/collection stores, reads both back, and render-checks changed CSS.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

// edit-global-class ---------------------------------------------------------

wp_register_ability('novamira/oxygen-edit-global-class', [
    'label' => __('Edit Oxygen Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Patches a native Oxygen selector, identified by stable `id` (preferred) or compatibility `name`. The id/name remain immutable. `properties` is shallow-merged at its top level; pass null for a top-level property group to remove it. Unknown property paths and literal `{}` substrings that Oxygen\'s saver would rewrite are rejected before saving.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string', 'description' => 'Compatibility locator; id is preferred.'],
            'type' => ['type' => 'string', 'enum' => ['class', 'custom']],
            'collection' => ['type' => 'string'],
            'properties' => ['type' => 'object', 'description' => 'Shallow-merged on top of the stored properties.'],
        ],
        'anyOf' => [['required' => ['id']], ['required' => ['name']]],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'class' => ['type' => 'object'],
            'verification' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_edit_global_class',
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

wp_register_ability('novamira/oxygen-delete-global-class', [
    'label' => __('Delete Oxygen Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a native Oxygen selector by stable `id` (preferred) or name. Existing elements retain the now-dangling selector id at `properties.meta.classes` and therefore lose its styles.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
        ],
        'anyOf' => [['required' => ['id']], ['required' => ['name']]],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'deleted_id' => ['type' => 'string'],
            'deleted_name' => ['type' => 'string'],
            'verification' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_delete_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'No undo. Use list-global-classes to find the stable id.',
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
function oxygen_list_global_classes(array $input): array
{
    $classes = ox_read_global_classes();
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
                'id' => isset($c['id']) && is_string($c['id']) ? $c['id'] : '',
                'name' => isset($c['name']) && is_string($c['name']) ? $c['name'] : '',
                'type' => isset($c['type']) && is_string($c['type']) ? $c['type'] : 'class',
                'collection' => isset($c['collection']) && is_string($c['collection']) ? $c['collection'] : '',
                'property_count' => count($props),
            ];
        },
        $classes,
    );
    return ['classes' => $rows, 'total' => count($rows)];
}

/**
 * Progressive discovery for the native selector control tree.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function oxygen_get_global_class_schema(array $input): array
{
    $controls = ox_oxygen_selector_controls();
    $breakpoints = ox_selector_breakpoint_ids();
    $group = ox_schema_string_arg($input, key: 'group');
    $search = ox_schema_string_arg($input, key: 'search');
    if ($group === null && $search === null) {
        $rows = ox_selector_control_groups($controls);
        return ['groups' => $rows, 'breakpoints' => $breakpoints, 'total' => count($rows)];
    }
    $rows = ox_selector_controls_filtered($controls, $group, $search);
    return ['controls' => $rows, 'breakpoints' => $breakpoints, 'total' => count($rows)];
}

/** @return list<string> */
function ox_selector_breakpoint_ids(): array
{
    if (!function_exists('Breakdance\\Config\\Breakpoints\\get_breakpoints')) {
        return [];
    }
    /** @var mixed $registered */
    $registered = \Breakdance\Config\Breakpoints\get_breakpoints();
    if (!is_array($registered)) {
        return [];
    }
    $ids = [];
    /** @var mixed $breakpoint */
    foreach ($registered as $breakpoint) {
        if (!is_array($breakpoint) || !isset($breakpoint['id']) || !is_string($breakpoint['id'])) {
            continue;
        }
        $ids[] = $breakpoint['id'];
    }
    return $ids;
}

/**
 * @param list<array{path: string, label: string, css: string, sample: array<string, mixed>}> $controls
 * @return list<array<string, mixed>>
 */
function ox_selector_control_groups(array $controls): array
{
    $counts = [];
    foreach ($controls as $control) {
        $slug = ox_selector_control_group($control['path']);
        $counts[$slug] = ($counts[$slug] ?? 0) + 1;
    }
    $rows = [];
    foreach ($counts as $slug => $count) {
        $rows[] = ['tab' => 'properties', 'group' => $slug, 'path' => 'properties.' . $slug, 'count' => $count];
    }
    return $rows;
}

/**
 * @param list<array{path: string, label: string, css: string, sample: array<string, mixed>}> $controls
 * @return list<array<string, mixed>>
 */
function ox_selector_controls_filtered(array $controls, ?string $group, ?string $search): array
{
    $rows = [];
    foreach ($controls as $control) {
        if ($group !== null && ox_selector_control_group($control['path']) !== $group) {
            continue;
        }
        if ($search !== null && !str_contains(strtolower($control['path']), strtolower($search))) {
            continue;
        }
        $rows[] = [
            'path' => $control['path'],
            'label' => $control['label'],
            'css' => $control['css'],
            'sample' => $control['sample'],
        ];
    }
    return $rows;
}

/** The group slug of a selector control path (`properties.<group>....`). */
function ox_selector_control_group(string $path): string
{
    $segments = explode('.', $path);
    return $segments[1] ?? '';
}

/**
 * Resolve a selector locator (`id` wins, `name` is the compatibility alias).
 *
 * @param list<array<string, mixed>> $classes
 * @param array<string, mixed>       $input
 * @return array{0: int, 1: array<string, mixed>}|WP_Error
 */
function ox_global_class_from_input(array $classes, array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? trim($input['id']) : '';
    if ($id !== '') {
        return (
            ox_find_global_class_by_id($classes, $id) ?? ox_error('oxygen_not_found', sprintf(
                __('Global class id `%s` not found.', domain: 'novamira-pro'),
                $id,
            ))
        );
    }
    $name = isset($input['name']) && is_string($input['name']) ? trim($input['name']) : '';
    if ($name === '') {
        return ox_error('oxygen_invalid_input', __('`id` or `name` is required.', domain: 'novamira-pro'));
    }
    return (
        ox_find_global_class($classes, $name) ?? ox_error('oxygen_not_found', sprintf(
            __('Global class `%s` not found.', domain: 'novamira-pro'),
            $name,
        ))
    );
}

/**
 * Compact render receipt for one observed native selector.
 *
 * @param array<string, mixed> $class
 * @return array{persisted: true, css_bytes: int, css_fingerprint: string}|WP_Error
 */
function ox_global_class_verification(array $class): array|WP_Error
{
    $css = ox_render_global_class_rows([$class]);
    if ($css instanceof WP_Error) {
        return $css;
    }
    return [
        'persisted' => true,
        'css_bytes' => strlen($css),
        'css_fingerprint' => ox_state_fingerprint($css),
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_get_global_class(array $input): array|WP_Error
{
    $classes = ox_read_global_classes();
    $hit = ox_global_class_from_input($classes, $input);
    if ($hit instanceof WP_Error) {
        return $hit;
    }
    [, $class] = $hit;
    return ['success' => true, 'class' => $class];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_create_global_class(array $input): array|WP_Error
{
    $prepared = ox_prepare_global_class_create($input);
    if ($prepared instanceof WP_Error) {
        return $prepared;
    }
    return ox_with_lock('global_classes', static fn(): array|WP_Error => ox_create_global_class_locked($prepared));
}

/**
 * @param array<string, mixed> $input
 * @return array{name: string, type: string, collection: string, properties: array<string, mixed>}|WP_Error
 */
function ox_prepare_global_class_create(array $input): array|WP_Error
{
    if (!isset($input['name']) || !is_string($input['name']) || trim($input['name']) === '') {
        return ox_error('oxygen_invalid_input', __('`name` is required.', domain: 'novamira-pro'));
    }
    $name = trim($input['name']);
    $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : 'class';
    if (!in_array($type, ['class', 'custom'], strict: true)) {
        return ox_error('oxygen_invalid_input', __('`type` must be `class` or `custom`.', domain: 'novamira-pro'));
    }
    $collection = isset($input['collection']) && is_string($input['collection'])
        ? trim($input['collection'])
        : 'Novamira';
    /** @var array<string, mixed> $properties */
    $properties = isset($input['properties']) && is_array($input['properties']) ? $input['properties'] : [];
    return ['name' => $name, 'type' => $type, 'collection' => $collection, 'properties' => $properties];
}

/**
 * @param array{name: string, type: string, collection: string, properties: array<string, mixed>} $prepared
 * @return array<string, mixed>|WP_Error
 */
function ox_create_global_class_locked(array $prepared): array|WP_Error
{
    $classes = ox_read_global_classes();
    if (ox_find_global_class($classes, $prepared['name']) !== null) {
        return ox_error('oxygen_invalid_input', sprintf(
            __('A global class with name `%s` already exists.', domain: 'novamira-pro'),
            $prepared['name'],
        ));
    }
    do {
        $id = ox_make_global_class_id();
    } while (ox_find_global_class_by_id($classes, $id) !== null);
    $new_class = [
        'id' => $id,
        'name' => $prepared['name'],
        'type' => $prepared['type'],
        'properties' => $prepared['properties'],
        'children' => [],
        'collection' => $prepared['collection'],
    ];
    if (ox_selector_has_literal_braces($new_class)) {
        return ox_selector_braces_error();
    }
    $classes[] = $new_class;
    $written = ox_write_global_classes($classes);
    if ($written instanceof WP_Error) {
        return $written;
    }
    $observed = ox_find_global_class_by_id(ox_read_global_classes(), $id);
    if ($observed === null) {
        return ox_error('oxygen_write_not_applied', __(
            'The selector disappeared after its verified write.',
            domain: 'novamira-pro',
        ));
    }
    $verification = ox_global_class_verification($observed[1]);
    if ($verification instanceof WP_Error) {
        return $verification;
    }
    return ['success' => true, 'class' => $observed[1], 'verification' => $verification];
}

/**
 * Apply an edit payload to one selector row before the transactional write.
 *
 * @param array<string, mixed> $class
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_prepare_global_class_edit(array $class, array $input): array|WP_Error
{
    if (
        isset($input['type'])
        && is_string($input['type'])
        && in_array($input['type'], ['class', 'custom'], strict: true)
    ) {
        $class['type'] = $input['type'];
    }
    if (isset($input['collection']) && is_string($input['collection'])) {
        $class['collection'] = trim($input['collection']);
    }
    if (isset($input['properties']) && is_array($input['properties'])) {
        /** @var array<string, mixed> $existing */
        $existing = isset($class['properties']) && is_array($class['properties']) ? $class['properties'] : [];
        /** @var array<string, mixed> $patch */
        $patch = $input['properties'];
        $merged = array_merge($existing, $patch);
        /** @var mixed $value */
        foreach ($patch as $key => $value) {
            if ($value !== null) {
                continue;
            }
            unset($merged[$key]);
        }
        $class['properties'] = $merged;
    }
    if (ox_selector_has_literal_braces($class)) {
        return ox_selector_braces_error();
    }
    return $class;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_edit_global_class(array $input): array|WP_Error
{
    return ox_with_lock('global_classes', static function () use ($input): array|WP_Error {
        $classes = ox_read_global_classes();
        $hit = ox_global_class_from_input($classes, $input);
        if ($hit instanceof WP_Error) {
            return $hit;
        }
        [$index, $class] = $hit;

        $prepared = ox_prepare_global_class_edit($class, $input);
        if ($prepared instanceof WP_Error) {
            return $prepared;
        }
        $class = $prepared;

        $classes[$index] = $class;
        $written = ox_write_global_classes($classes);
        if ($written instanceof WP_Error) {
            return $written;
        }

        $id = (string) $class['id'];
        $observed = ox_find_global_class_by_id(ox_read_global_classes(), $id);
        if ($observed === null) {
            return ox_error('oxygen_write_not_applied', __(
                'The selector disappeared after its verified edit.',
                domain: 'novamira-pro',
            ));
        }
        $verification = ox_global_class_verification($observed[1]);
        if ($verification instanceof WP_Error) {
            return $verification;
        }
        return ['success' => true, 'class' => $observed[1], 'verification' => $verification];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_delete_global_class(array $input): array|WP_Error
{
    return ox_with_lock('global_classes', static function () use ($input): array|WP_Error {
        $classes = ox_read_global_classes();
        $hit = ox_global_class_from_input($classes, $input);
        if ($hit instanceof WP_Error) {
            return $hit;
        }
        [$index, $class] = $hit;
        $id = isset($class['id']) && is_string($class['id']) ? $class['id'] : '';
        $name = isset($class['name']) && is_string($class['name']) ? $class['name'] : '';
        array_splice(array: $classes, offset: $index, length: 1);
        $written = ox_write_global_classes($classes);
        if ($written instanceof WP_Error) {
            return $written;
        }

        $removed = ox_find_global_class_by_id(ox_read_global_classes(), $id) === null;
        if (!$removed) {
            return ox_error('oxygen_write_not_applied', __(
                'The selector still exists after deletion.',
                domain: 'novamira-pro',
            ));
        }
        return [
            'success' => true,
            'deleted_id' => $id,
            'deleted_name' => $name,
            'verification' => ['persisted_removed' => true],
        ];
    });
}
