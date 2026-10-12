<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use ET\Builder\Packages\GlobalData\GlobalPreset;
use WP_Error;

/**
 * Abilities: list, read, apply, create, edit, delete, and set-default for
 * Divi 5 global module presets.
 *
 * A preset is a reusable, named styling bundle for one module type. This
 * surface lets an agent discover presets, attach one to a module
 * (attrs.modulePreset), author new ones, edit or delete them, and choose which
 * preset auto-applies to newly inserted modules of a type. Writes go through
 * GlobalPreset::save_data(), which persists the shared
 * `builder_global_presets_d5` option (preset attrs use the same shape as a
 * module's attrs, exactly what novamira/divi-edit-module merges).
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_DIVI_LIST_PRESETS_DEFAULT_LIMIT = 500;

wp_register_ability('novamira/divi-list-global-presets', [
    'label' => __('List Divi Global Presets', domain: 'novamira-pro'),
    'description' => __(
        'Lists the global module presets defined on this site (authored in the Divi Visual Builder), as compact rows: module, id, name, is_default, custom_id_hazard. The "id" is what you pass to novamira/divi-apply-global-preset. A row with custom_id_hazard=true means this Divi emits the preset\'s custom attribute named "id" on every module using it; the result includes a warning because reuse can create duplicate DOM ids. Filter by "module" (with or without the "divi/" prefix). An empty list means no presets have been created yet.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'module' => ['type' => 'string', 'description' => 'Restrict to one module type, e.g. "divi/text".'],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NOVAMIRA_DIVI_LIST_PRESETS_DEFAULT_LIMIT,
                'description' => 'Max rows (default and max 500).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'presets' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_global_presets',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover existing presets before applying one. Presets are per module type — a divi/text preset only applies to divi/text modules. Treat custom_id_hazard=true as unsafe for reuse: remove the preset custom id and set a unique ID on each module instead.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-get-global-preset', [
    'label' => __('Get Divi Global Preset', domain: 'novamira-pro'),
    'description' => __(
        'Returns one global module preset in full: its module, id, name, and the styling attributes it applies. Identify it by "module" and "preset_id" (from novamira/divi-list-global-presets). custom_id_hazard=true and an accompanying warning mean this Divi emits the preset\'s custom attribute named "id" on every module using it, so reuse can create duplicate DOM ids.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string', 'description' => 'Module type, e.g. "divi/text".'],
            'preset_id' => ['type' => 'string', 'description' => 'Preset id (the "id" from divi-list-global-presets).'],
            'id' => ['type' => 'string', 'description' => 'Alias for "preset_id".'],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string'],
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'attrs' => ['type' => 'object'],
            'custom_id_hazard' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_get_global_preset',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inspect a preset\'s styling before applying it. If custom_id_hazard is true, remove the preset custom id and set a unique ID on each module instead of reusing it.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-apply-global-preset', [
    'label' => __('Apply Divi Global Preset', domain: 'novamira-pro'),
    'description' => __(
        'Attaches an existing global preset to the module at "address" (sets attrs.modulePreset). The preset must belong to that module\'s type — a divi/text preset only applies to a divi/text module. If this Divi emits a preset custom attribute named "id" on every module using it, this write remains allowed for legitimate one-module use but returns a warning about duplicate DOM ids. Remove the preset custom id and set unique module IDs with novamira/divi-edit-module when reusing the preset. Use novamira/divi-list-global-presets to find a preset_id. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Module address, e.g. "0/0/0/0".'],
            'preset_id' => [
                'type' => 'string',
                'description' => 'Preset id to attach (the "id" from divi-list-global-presets).',
            ],
            'id' => ['type' => 'string', 'description' => 'Alias for "preset_id".'],
        ],
        'required' => ['address'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
            'preset_id' => ['type' => 'string'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_apply_global_preset',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Attach a reusable preset to a module for consistent styling. The preset and the target module must be the same module type. If the result warns about a custom id, do not reuse that id; set a unique ID on each module instead.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-create-global-preset', [
    'label' => __('Create Divi Global Preset', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new reusable global preset (a named styling bundle) for one module type and returns its "preset_id". "attrs" uses the same shape as novamira/divi-edit-module — the module\'s attributes object, e.g. {"module":{"decoration":{"spacing":{"desktop":{"value":{"padding":{"top":"40px"}}}}}}}. If this Divi emits a custom attribute named "id" under module.decoration.attributes on every module using the preset, creation remains allowed for legitimate one-module use but returns a warning about duplicate DOM ids. Keep reusable presets free of custom ids and set unique module IDs with novamira/divi-edit-module instead. Attach the preset with novamira/divi-apply-global-preset, or make it the type\'s default for new modules with novamira/divi-set-default-preset. Discover attr paths with novamira/divi-get-style-schema.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => [
                'type' => 'string',
                'description' => 'Module type the preset is for, e.g. "divi/text" (the "divi/" prefix is optional).',
            ],
            'name' => [
                'type' => 'string',
                'description' => 'Display name (optional; auto-generated like "Text 1" when omitted).',
            ],
            'attrs' => [
                'type' => 'object',
                'description' => 'The styling attributes, shaped like a module\'s attrs (same as divi-edit-module).',
            ],
        ],
        'required' => ['module', 'attrs'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string'],
            'preset_id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_create_global_preset',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Author a reusable preset once, then apply it to many modules of the same type for consistent, maintainable styling. Build "attrs" the same way you would for divi-edit-module. Do not put a custom id in a reusable preset; set each module\'s unique ID separately.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/divi-edit-global-preset', [
    'label' => __('Edit Divi Global Preset', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing global preset in place. Identify it by "module" and "preset_id" (from novamira/divi-list-global-presets). Pass "name" to rename and/or "attrs" to change its styling — "attrs" is deep-merged into the preset\'s existing attributes (nested objects merge; lists and scalars replace), same shape and semantics as novamira/divi-edit-module. Editing a preset restyles every module it is applied to at once. If the merged preset contains a custom attribute named "id" under module.decoration.attributes and this Divi emits it on every module using the preset, the result warns that reuse can create duplicate DOM ids; remove it and set unique module IDs instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string', 'description' => 'Module type, e.g. "divi/text".'],
            'preset_id' => ['type' => 'string', 'description' => 'Preset id (from divi-list-global-presets).'],
            'id' => ['type' => 'string', 'description' => 'Alias for "preset_id".'],
            'name' => ['type' => 'string', 'description' => 'New display name (optional).'],
            'attrs' => [
                'type' => 'object',
                'description' => 'Partial styling attributes to deep-merge into the preset (optional).',
            ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string'],
            'preset_id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_edit_global_preset',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update of a preset. Provide "name" and/or "attrs"; "attrs" merges into the existing styling. Editing a preset restyles every module it is applied to. Do not keep a custom id in a reusable preset; set each module\'s unique ID separately.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-delete-global-preset', [
    'label' => __('Delete Divi Global Preset', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes a global preset. Identify it by "module" and "preset_id" (from novamira/divi-list-global-presets). Modules that referenced it keep their own attributes and simply stop inheriting the preset; if the deleted preset was the type\'s default, the default is cleared.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string', 'description' => 'Module type, e.g. "divi/text".'],
            'preset_id' => ['type' => 'string', 'description' => 'Preset id to delete.'],
            'id' => ['type' => 'string', 'description' => 'Alias for "preset_id".'],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string'],
            'preset_id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_delete_global_preset',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanently removes a preset definition. Modules keep their inlined attributes; they just no longer inherit from the preset.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/divi-set-default-preset', [
    'label' => __('Set Divi Default Preset', domain: 'novamira-pro'),
    'description' => __(
        'Sets which preset is the default for a module type — the preset automatically applied to every newly inserted module of that type. Identify it by "module" and "preset_id" (from novamira/divi-list-global-presets). This is how you establish on-brand defaults (e.g. every new Button starts as your brand button). It does not restyle modules that already exist. If this Divi emits a preset custom attribute named "id" on every module using it, setting the preset as the default remains allowed but returns a warning because new modules can receive duplicate DOM ids; remove the preset custom id and set unique module IDs instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string', 'description' => 'Module type, e.g. "divi/button".'],
            'preset_id' => ['type' => 'string', 'description' => 'Preset id to make the default.'],
            'id' => ['type' => 'string', 'description' => 'Alias for "preset_id".'],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string'],
            'default_preset_id' => ['type' => 'string'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_set_default_preset',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Point a module type\'s default at an existing preset so new modules of that type start on-brand. Does not change modules that already exist. Never make a preset carrying a custom id the reusable default; set unique IDs on modules individually.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function divi_list_global_presets(array $input): array
{
    $filter = divi_string_arg($input, key: 'module');
    if ($filter !== '') {
        $filter = divi_normalize_module_name($filter);
    }
    $limit = divi_int_arg_or_null($input, key: 'limit') ?? NOVAMIRA_DIVI_LIST_PRESETS_DEFAULT_LIMIT;
    $limit = max(1, min($limit, NOVAMIRA_DIVI_LIST_PRESETS_DEFAULT_LIMIT));

    $rows = divi_collect_preset_rows(divi_global_module_presets(), $filter);
    $total = count($rows);
    $returned = array_slice($rows, offset: 0, length: $limit);
    $has_custom_id_hazard = false;
    foreach ($rows as $row) {
        if (!$row['custom_id_hazard']) {
            continue;
        }
        $has_custom_id_hazard = true;
        break;
    }

    return [
        'presets' => $returned,
        'total' => $total,
        'returned' => count($returned),
        'truncated' => $total > count($returned),
        'warnings' => $has_custom_id_hazard ? [divi_preset_custom_id_warning()] : [],
    ];
}

/**
 * Read the preset id from either `preset_id` or its `id` alias (the field name
 * divi-list-global-presets emits), trimmed.
 *
 * @param array<string, mixed> $input
 */
function divi_preset_id_arg(array $input): string
{
    $preset_id = trim(divi_string_arg($input, key: 'preset_id'));
    return $preset_id !== '' ? $preset_id : trim(divi_string_arg($input, key: 'id'));
}

/**
 * Flatten the module-preset store into compact rows, optionally filtered to one
 * module name.
 *
 * @param array<array-key, mixed> $module_presets
 * @return list<array{module: string, id: string, name: string, is_default: bool, custom_id_hazard: bool}>
 */
function divi_collect_preset_rows(array $module_presets, string $filter): array
{
    $rows = [];
    /** @var mixed $entry */
    foreach ($module_presets as $module_name => $entry) {
        if (!is_string($module_name) || !is_array($entry)) {
            continue;
        }
        if ($filter !== '' && $module_name !== $filter) {
            continue;
        }
        $default = is_string($entry['default'] ?? null) ? $entry['default'] : '';
        $items = is_array($entry['items'] ?? null) ? $entry['items'] : [];
        /** @var mixed $item */
        foreach ($items as $id => $item) {
            if (!is_string($id) || !is_array($item)) {
                continue;
            }
            $rows[] = [
                'module' => $module_name,
                'id' => $id,
                'name' => is_string($item['name'] ?? null) ? $item['name'] : $id,
                'is_default' => $id === $default,
                'custom_id_hazard' => divi_preset_item_has_custom_id_hazard($item),
            ];
        }
    }
    return $rows;
}

/**
 * True when a preset attrs object contains a custom attribute named `id` in
 * the `module.decoration.attributes` plane. Attribute names are HTML
 * case-insensitive, and the plane can carry responsive/state wrappers, so
 * inspect it recursively rather than depending on one stored wrapper shape.
 *
 * @param array<array-key, mixed> $attrs
 */
function divi_preset_attrs_have_custom_id(array $attrs): bool
{
    /** @var mixed $attributes */
    $attributes = $attrs['module']['decoration']['attributes'] ?? null;
    return is_array($attributes) && divi_preset_attribute_plane_has_custom_id($attributes);
}

/**
 * @param array<array-key, mixed> $value
 */
function divi_preset_attribute_plane_has_custom_id(array $value): bool
{
    /** @var mixed $name */
    $name = $value['name'] ?? null;
    if (is_string($name) && strcasecmp(trim($name), 'id') === 0) {
        return true;
    }

    /** @var mixed $child */
    foreach ($value as $child) {
        if (is_array($child) && divi_preset_attribute_plane_has_custom_id($child)) {
            return true;
        }
    }
    return false;
}

/**
 * Check every attrs plane Divi stores and migrates on a preset item. Existing
 * presets can carry the custom id outside `attrs`, even though this ability's
 * create/edit inputs write `attrs` directly.
 *
 * @param array<array-key, mixed> $item
 */
function divi_preset_item_has_custom_id(array $item): bool
{
    foreach (['attrs', 'renderAttrs', 'styleAttrs'] as $key) {
        /** @var mixed $attrs */
        $attrs = $item[$key] ?? null;
        if (is_array($attrs) && divi_preset_attrs_have_custom_id($attrs)) {
            return true;
        }
    }
    return false;
}

/**
 * True when this Divi runtime emits preset-sourced custom id rows. The older
 * rendering guard is private, but method_exists() intentionally sees private
 * methods, making the guard's presence a direct capability probe.
 *
 * This assumes that the render path still invokes the guard whenever the
 * method exists. The one silent-failure direction is a future Divi retaining
 * the method but no longer calling it: this probe would report "guarded" and
 * suppress a real hazard. Every other drift direction fails noisily with a
 * conservative warning where no hazard is rendered, which is the safe
 * direction for this predicate.
 */
function divi_preset_custom_id_hazard_active(): bool
{
    return !method_exists('ET\\Builder\\Packages\\Module\\Module', '_remove_preset_sourced_id_attributes');
}

/**
 * @param array<array-key, mixed> $item
 */
function divi_preset_item_has_custom_id_hazard(array $item): bool
{
    return divi_preset_custom_id_hazard_active() && divi_preset_item_has_custom_id($item);
}

/**
 * A non-fatal warning: one-module use is legitimate, but the same preset id
 * row becomes an invalid duplicate as soon as the preset is reused.
 */
function divi_preset_custom_id_warning(): string
{
    return (
        'Custom ID hazard: a preset in this result contains a custom attribute named `id` under '
        . '`module.decoration.attributes`. A preset\'s custom `id` attribute is emitted on every module '
        . 'that uses the preset, so reusing it can create duplicate DOM ids. Remove `id` from the preset '
        . 'and give each module its own ID with `novamira/divi-edit-module` at '
        . '`module.advanced.htmlAttributes.desktop.value.id`.'
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_get_global_preset(array $input)
{
    $module = divi_normalize_module_name(divi_string_arg($input, key: 'module'));
    $preset_id = divi_preset_id_arg($input);
    if ($module === '' || $preset_id === '') {
        return divi_error('invalid_input', message: '"module" and "preset_id" are required.');
    }

    $presets = divi_global_module_presets();
    /** @var mixed $item */
    $item = $presets[$module]['items'][$preset_id] ?? null;
    if (!is_array($item)) {
        return divi_error(
            'not_found',
            message: sprintf(
                'No preset "%s" for module "%s". Use novamira/divi-list-global-presets.',
                $preset_id,
                $module,
            ),
            status: 404,
        );
    }

    $custom_id_hazard = divi_preset_item_has_custom_id_hazard($item);

    return [
        'module' => $module,
        'id' => $preset_id,
        'name' => is_string($item['name'] ?? null) ? $item['name'] : $preset_id,
        'attrs' => is_array($item['attrs'] ?? null) ? $item['attrs'] : [],
        'custom_id_hazard' => $custom_id_hazard,
        'warnings' => $custom_id_hazard ? [divi_preset_custom_id_warning()] : [],
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_apply_global_preset(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    $preset_id = divi_preset_id_arg($input);
    if ($address === '' || $preset_id === '') {
        return divi_error('invalid_input', message: '"address" and "preset_id" are required.');
    }

    $node = divi_tree_get(divi_parse_tree($post->post_content), $address);
    if ($node === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }

    $name = is_string($node['name'] ?? null) ? $node['name'] : '';
    $presets = divi_global_module_presets();
    /** @var mixed $item */
    $item = $presets[$name]['items'][$preset_id] ?? null;
    if (!is_array($item)) {
        return divi_error(
            'invalid_preset',
            message: sprintf('Preset "%s" is not a preset for module "%s".', $preset_id, $name),
            status: 422,
        );
    }

    $result = divi_merge_module_attrs($post, $address, ['modulePreset' => $preset_id]);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'post_id' => $post->ID,
        'address' => $address,
        'preset_id' => $preset_id,
        'warnings' => divi_preset_item_has_custom_id_hazard($item) ? [divi_preset_custom_id_warning()] : [],
    ];
}

/**
 * The full global-preset data array (the value GlobalPreset::save_data expects
 * back), or a WP_Error when the Divi 5 preset API is unavailable.
 *
 * @return array<array-key, mixed>|WP_Error
 */
function divi_global_preset_data()
{
    if (!class_exists(GlobalPreset::class)) {
        return divi_error(
            'unavailable',
            message: 'The Divi 5 global-preset API is unavailable on this site.',
            status: 422,
        );
    }
    return GlobalPreset::get_data();
}

/**
 * Generate a fresh RFC-4122 v4 UUID for a preset id, matching Divi's own id
 * format without depending on its internal generator.
 */
function divi_generate_preset_id(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, offset: 0, length: 8),
        substr($hex, offset: 8, length: 4),
        substr($hex, offset: 12, length: 4),
        substr($hex, offset: 16, length: 4),
        substr($hex, offset: 20, length: 12),
    );
}

/**
 * A human-friendly default preset name like "Text 1", derived from the module
 * slug and the number of existing presets, used when the caller omits a name.
 *
 * @param array<array-key, mixed> $items
 */
function divi_default_preset_name(array $items, string $module): string
{
    $label = ucwords(trim(str_replace(['divi/', '-'], ['', ' '], $module)));
    if ($label === '') {
        $label = 'Preset';
    }
    return $label . ' ' . (count($items) + 1);
}

/**
 * Validate and return the preset styling attrs from input: a non-empty object
 * (associative array) shaped like a module's attrs. Returns a WP_Error for a
 * missing, non-object, list, or empty value.
 *
 * @param array<string, mixed> $input
 * @return array<array-key, mixed>|WP_Error
 */
function divi_preset_attrs_or_error(array $input)
{
    /** @var mixed $attrs */
    $attrs = $input['attrs'] ?? null;
    if (!is_array($attrs) || $attrs === []) {
        return divi_error(
            'invalid_input',
            message: '"attrs" must be a non-empty styling object, e.g. {"module":{"decoration":{"spacing":{"desktop":{"value":{"padding":{"top":"40px"}}}}}}}.',
        );
    }
    if (divi_is_list($attrs)) {
        return divi_error('invalid_input', message: '"attrs" must be an object, not a list.');
    }
    // A preset is stored as a JSON-encoded blob and shipped in agent/REST
    // responses. Attrs that wp_json_encode can't handle (e.g. nested past JSON's
    // ~512-level depth cap) would persist a preset that later breaks every
    // response that reads it — refuse the write instead.
    if (wp_json_encode($attrs) === false) {
        return divi_error(
            'invalid_input',
            message: '"attrs" is nested too deeply or is otherwise not JSON-serializable; the write was refused to avoid storing an unreadable preset.',
            status: 422,
        );
    }
    return $attrs;
}

/**
 * The standard 404 for a preset that does not exist on a module type.
 */
function divi_preset_not_found(string $module, string $preset_id): WP_Error
{
    return divi_error(
        'not_found',
        message: sprintf('No preset "%s" for module "%s". Use novamira/divi-list-global-presets.', $preset_id, $module),
        status: 404,
    );
}

/**
 * The Divi builder version string, or '' when no version constant is available
 * (stored on a preset item as its `version`, mirroring Divi's own writer).
 */
function divi_builder_version(): string
{
    return \Novamira\Pro\novamira_pro_divi_builder_version();
}

/**
 * Normalize the requested module name and confirm it is a real Divi module.
 *
 * @param array<string, mixed> $input
 * @return string|WP_Error
 */
function divi_resolve_preset_module(array $input)
{
    $module = divi_normalize_module_name(divi_string_arg($input, key: 'module'));
    if ($module === '') {
        return divi_error('invalid_input', message: 'A "module" is required, e.g. "divi/text".');
    }
    if (divi_module_metadata($module) === null) {
        return divi_error(
            'unknown_module',
            message: sprintf('Unknown module "%s". Use novamira/divi-list-modules to discover valid names.', $module),
            status: 422,
        );
    }
    return $module;
}

/**
 * The stored entry for a module type — `['default' => string, 'items' =>
 * array]` — normalized with an empty default/items map when the type has no
 * presets yet.
 *
 * @param array<array-key, mixed> $data
 * @return array<array-key, mixed>
 */
function divi_preset_entry(array $data, string $module): array
{
    $modules = is_array($data['module'] ?? null) ? $data['module'] : [];
    $entry = is_array($modules[$module] ?? null) ? $modules[$module] : [];
    if (!is_string($entry['default'] ?? null)) {
        $entry['default'] = '';
    }
    if (!is_array($entry['items'] ?? null)) {
        $entry['items'] = [];
    }
    return $entry;
}

/**
 * Replace a module type's entry in the preset data and return updated data.
 *
 * @param array<array-key, mixed> $data
 * @param array<array-key, mixed> $entry
 * @return array<array-key, mixed>
 */
function divi_put_preset_entry(array $data, string $module, array $entry): array
{
    $modules = is_array($data['module'] ?? null) ? $data['module'] : [];
    $modules[$module] = $entry;
    $data['module'] = $modules;
    return $data;
}

/**
 * Remove a module type's entry entirely from the preset data.
 *
 * @param array<array-key, mixed> $data
 * @return array<array-key, mixed>
 */
function divi_remove_preset_module(array $data, string $module): array
{
    $modules = is_array($data['module'] ?? null) ? $data['module'] : [];
    unset($modules[$module]);
    $data['module'] = $modules;
    return $data;
}

/**
 * Resolve and validate the target of a preset mutation. Returns
 * `[module, preset_id, entry, items]` on success, or a WP_Error when the
 * module/preset_id are missing or the preset does not exist.
 *
 * @param array<array-key, mixed> $data
 * @param array<string, mixed> $input
 * @return array{0: string, 1: string, 2: array<array-key, mixed>, 3: array<array-key, mixed>}|WP_Error
 */
function divi_locate_preset(array $data, array $input)
{
    $module = divi_normalize_module_name(divi_string_arg($input, key: 'module'));
    $preset_id = divi_preset_id_arg($input);
    if ($module === '' || $preset_id === '') {
        return divi_error('invalid_input', message: '"module" and "preset_id" are required.');
    }

    $entry = divi_preset_entry($data, $module);
    $items = is_array($entry['items'] ?? null) ? $entry['items'] : [];
    if (!array_key_exists($preset_id, $items)) {
        return divi_preset_not_found($module, $preset_id);
    }
    return [$module, $preset_id, $entry, $items];
}

/**
 * Apply the requested `name`/`attrs` changes to a preset item and bump its
 * `updated` timestamp. Returns the updated item, or a WP_Error for bad attrs or
 * when neither field was provided.
 *
 * @param array<string, mixed> $input
 * @param array<array-key, mixed> $item
 * @return array<array-key, mixed>|WP_Error
 */
function divi_apply_preset_edits(array $input, array $item)
{
    $changed = false;

    $name = trim(divi_string_arg($input, key: 'name'));
    if ($name !== '') {
        $item['name'] = $name;
        $changed = true;
    }

    if (array_key_exists('attrs', $input)) {
        $attrs = divi_preset_attrs_or_error($input);
        if ($attrs instanceof WP_Error) {
            return $attrs;
        }
        $existing = is_array($item['attrs'] ?? null) ? $item['attrs'] : [];
        $item['attrs'] = divi_deep_merge($existing, $attrs);
        $changed = true;
    }

    if (!$changed) {
        return divi_error('invalid_input', message: 'Provide "name" and/or "attrs" to change.');
    }

    $item['updated'] = time();
    return $item;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_create_global_preset(array $input)
{
    $module = divi_resolve_preset_module($input);
    if ($module instanceof WP_Error) {
        return $module;
    }
    $attrs = divi_preset_attrs_or_error($input);
    if ($attrs instanceof WP_Error) {
        return $attrs;
    }

    return divi_with_global_lock('presets', static function () use ($input, $module, $attrs) {
        $data = divi_global_preset_data();
        if ($data instanceof WP_Error) {
            return $data;
        }
        return divi_create_global_preset_locked($input, $module, $attrs, $data);
    });
}

/**
 * @param array<string, mixed> $input
 * @param array<array-key, mixed> $attrs
 * @param array<array-key, mixed> $data
 * @return array<string, mixed>|WP_Error
 */
function divi_create_global_preset_locked(array $input, string $module, array $attrs, array $data)
{
    $entry = divi_preset_entry($data, $module);
    $items = is_array($entry['items'] ?? null) ? $entry['items'] : [];

    $id = divi_generate_preset_id();
    $name = trim(divi_string_arg($input, key: 'name'));
    if ($name === '') {
        $name = divi_default_preset_name($items, $module);
    }
    $now = time();
    $items[$id] = [
        'id' => $id,
        'name' => $name,
        'moduleName' => $module,
        'version' => divi_builder_version(),
        'type' => 'module',
        'created' => $now,
        'updated' => $now,
        'attrs' => $attrs,
    ];
    $entry['items'] = $items;
    $data = divi_put_preset_entry($data, $module, $entry);

    GlobalPreset::save_data($data);

    $warnings = [];
    if (divi_attrs_have_free_form_css($attrs)) {
        $warnings[] = divi_free_form_css_warning();
    }
    if (divi_preset_custom_id_hazard_active() && divi_preset_attrs_have_custom_id($attrs)) {
        $warnings[] = divi_preset_custom_id_warning();
    }

    return [
        'module' => $module,
        'preset_id' => $id,
        'name' => $name,
        'warnings' => $warnings,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_edit_global_preset(array $input)
{
    return divi_with_global_lock('presets', static function () use ($input) {
        $data = divi_global_preset_data();
        if ($data instanceof WP_Error) {
            return $data;
        }
        $located = divi_locate_preset($data, $input);
        if ($located instanceof WP_Error) {
            return $located;
        }
        [$module, $preset_id, $entry, $items] = $located;

        $item = divi_apply_preset_edits($input, is_array($items[$preset_id]) ? $items[$preset_id] : []);
        if ($item instanceof WP_Error) {
            return $item;
        }

        $items[$preset_id] = $item;
        $entry['items'] = $items;
        $data = divi_put_preset_entry($data, $module, $entry);

        GlobalPreset::save_data($data);

        /** @var mixed $patch */
        $patch = $input['attrs'] ?? null;
        $warnings = [];
        if (is_array($patch) && divi_attrs_have_free_form_css($patch)) {
            $warnings[] = divi_free_form_css_warning();
        }
        if (divi_preset_item_has_custom_id_hazard($item)) {
            $warnings[] = divi_preset_custom_id_warning();
        }

        return [
            'module' => $module,
            'preset_id' => $preset_id,
            'name' => is_string($item['name'] ?? null) ? $item['name'] : $preset_id,
            'warnings' => $warnings,
        ];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_delete_global_preset(array $input)
{
    return divi_with_global_lock('presets', static function () use ($input) {
        $data = divi_global_preset_data();
        if ($data instanceof WP_Error) {
            return $data;
        }
        $located = divi_locate_preset($data, $input);
        if ($located instanceof WP_Error) {
            return $located;
        }
        [$module, $preset_id, $entry, $items] = $located;

        unset($items[$preset_id]);

        if ($items === []) {
            $data = divi_remove_preset_module($data, $module);
        }
        if ($items !== []) {
            if (($entry['default'] ?? null) === $preset_id) {
                $entry['default'] = '';
            }
            $entry['items'] = $items;
            $data = divi_put_preset_entry($data, $module, $entry);
        }

        GlobalPreset::save_data($data);

        return ['module' => $module, 'preset_id' => $preset_id, 'deleted' => true];
    });
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_set_default_preset(array $input)
{
    return divi_with_global_lock('presets', static function () use ($input) {
        $data = divi_global_preset_data();
        if ($data instanceof WP_Error) {
            return $data;
        }
        $located = divi_locate_preset($data, $input);
        if ($located instanceof WP_Error) {
            return $located;
        }
        [$module, $preset_id, $entry, $items] = $located;

        $entry['default'] = $preset_id;
        $data = divi_put_preset_entry($data, $module, $entry);

        GlobalPreset::save_data($data);

        $item = is_array($items[$preset_id]) ? $items[$preset_id] : [];

        return [
            'module' => $module,
            'default_preset_id' => $preset_id,
            'warnings' => divi_preset_item_has_custom_id_hazard($item) ? [divi_preset_custom_id_warning()] : [],
        ];
    });
}
