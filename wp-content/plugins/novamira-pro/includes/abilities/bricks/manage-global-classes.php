<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Abilities: Bricks Global Classes — list / get / create / edit / delete /
 * apply. Global classes are reusable bundles of Bricks control settings
 * referenced by elements via "_cssGlobalClasses" (flat list of ids).
 * Categories resolved inline by name on create.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-global-classes', [
    'label' => __('List Bricks Global Classes', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Bricks global classes defined on this site as a LEAN index: each entry has id, name, and category id (when assigned). The full `settings` (Bricks control values) are omitted by default so the response stays small on sites with large class libraries. Read one class\'s settings on demand with `bricks-get-global-class`, or pass `include_settings: true` to inline settings for every class (can be very large). Optionally filter by category id.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'description' => 'Optional. Filter to classes in this category id.',
            ],
            'include_settings' => [
                'type' => 'boolean',
                'description' => 'Optional (default false). When true, each entry also carries its full `settings` object. Left off by default to keep the response small — prefer `bricks-get-global-class` to read one class\'s settings.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'classes' => ['type' => 'array'],
            'categories' => ['type' => 'array'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_global_classes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_list_global_classes(array $input): array
{
    /** @var mixed $raw_classes */
    $raw_classes = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
    $classes = is_array($raw_classes) ? $raw_classes : [];

    /** @var mixed $raw_categories */
    // Through the resolver, not straight off the current site: the write path
    // routes this option on a network configured to share it, and a list that
    // read the local row while create wrote the main-site one would have our
    // own two calls disagreeing.
    $raw_categories = bricks_routed_option_read(BRICKS_DB_GLOBAL_CLASSES_CATEGORIES, []);
    $categories = is_array($raw_categories) ? $raw_categories : [];

    /** @var mixed $filter */
    $filter = $input['category'] ?? null;
    if (is_string($filter) && $filter !== '') {
        $classes = array_values(array_filter(
            $classes,
            static fn(mixed $c): bool => is_array($c) && ($c['category'] ?? null) === $filter,
        ));
    }

    // Progressive disclosure: return a lean index (id, name, category) by
    // default; the per-class `settings` are large on real sites, so they are
    // inlined only on explicit opt-in. Read one class with bricks-get-global-class.
    $include_settings = ($input['include_settings'] ?? false) === true;
    $classes = array_map(static function (mixed $class) use ($include_settings): array {
        if (!is_array($class)) {
            return [];
        }
        $entry = [
            'id' => $class['id'] ?? null,
            'name' => $class['name'] ?? null,
        ];
        if (isset($class['category'])) {
            $entry['category'] = $class['category'];
        }
        if ($include_settings) {
            $entry['settings'] = $class['settings'] ?? new \stdClass();
        }
        return $entry;
    }, array_values($classes));

    return [
        'classes' => $classes,
        'categories' => array_values($categories),
    ];
}

wp_register_ability('novamira/bricks-get-global-class', [
    'label' => __('Get Bricks Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Bricks global class by its id. Returns id, name, settings, and category id when assigned.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the global class to read.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'class' => ['type' => 'object'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unresolved_variables' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_get_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_get_global_class(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }

    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
    $classes = is_array($raw) ? $raw : [];

    /** @var mixed $entry */
    foreach ($classes as $entry) {
        if (is_array($entry) && ($entry['id'] ?? null) === $id) {
            return ['class' => $entry];
        }
    }

    return bricks_error('not_found', sprintf('Global class "%s" not found.', $id));
}

wp_register_ability('novamira/bricks-create-global-class', [
    'label' => __('Create Bricks Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Bricks global class. Provide a name, a settings object (Bricks control values), and an optional category. Category accepts an existing category id OR a category name (creates the category on-the-fly if it does not exist). The class id is auto-generated (6-char bare id like "clshov"). Rejects if a class with the same name already exists. Settings shape: typed controls first (the shared style controls `_padding`, `_margin`, `_typography`, `_background`, `_border`, `_display`, flex/grid keys, …) discovered via `bricks-list-elements include_shared_controls=true`; `_cssCustom` is a last resort for things no typed control covers. Inside `_cssCustom` the `%root%` placeholder resolves server-side to the class selector (`.<name>`), mirroring the element-level convention — e.g. `_cssCustom: "%root% { ... }"`.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Class name. Must be unique across all global classes.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Bricks control values for this class. Prefer the typed shared style controls (`_padding`, `_margin`, `_typography`, `_background`, `_border`, `_display`, flex/grid keys, …) discovered via `bricks-list-elements include_shared_controls=true`; reserve `_cssCustom` for cases no typed control covers (complex selectors, pseudo-elements, container queries). Classes built with typed controls remain editable from the Bricks class panel; classes built around `_cssCustom` show as an opaque CSS blob.',
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Optional. Existing category id, or a name for a new category to create.',
            ],
        ],
        'required' => ['name', 'settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('global classes'),
            'class' => ['type' => 'object'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unresolved_variables' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_create_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

function bricks_create_global_class(array $input): array|WP_Error
{
    /** @var mixed $raw_name */
    $raw_name = $input['name'] ?? '';
    if (!is_string($raw_name) || trim($raw_name) === '') {
        return bricks_error('invalid_input', message: 'name is required.');
    }

    $name = preg_replace(pattern: '/\s+/', replacement: '-', subject: strtolower(trim($raw_name)));
    if (!is_string($name) || $name === '') {
        return bricks_error('invalid_input', message: 'invalid name.');
    }

    /** @var mixed $raw_settings */
    $raw_settings = $input['settings'] ?? null;
    if (!is_array($raw_settings)) {
        return bricks_error('invalid_input', message: 'settings is required and must be an object.');
    }

    /** @var mixed $raw_classes */
    $raw_classes = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
    $classes = is_array($raw_classes) ? $raw_classes : [];

    /** @var mixed $existing */
    foreach ($classes as $existing) {
        if (is_array($existing) && ($existing['name'] ?? null) === $name) {
            return bricks_error('duplicate_name', sprintf('name "%s" already exists.', $name));
        }
    }

    /** @var mixed $category_input */
    $category_input = $input['category'] ?? null;
    $category_id = is_string($category_input)
        ? bricks_resolve_category($category_input, BRICKS_DB_GLOBAL_CLASSES_CATEGORIES)
        : null;

    $class = [
        'id' => bricks_random_id(),
        'name' => $name,
        'settings' => bricks_resolve_global_class_root($raw_settings, $name),
    ];
    if ($category_id !== null) {
        $class['category'] = $category_id;
    }

    $classes[] = $class;
    $stored = gc_save_classes($classes);

    return gc_verified(gc_with_settings_notes(['class' => $class], $class['settings'], $name), $stored);
}

/**
 * Adds the settings advisories a global class deserves: font-family values
 * Bricks cannot render and var(--x) references that resolve to nothing.
 *
 * @param array<string, mixed>    $result
 * @param array<array-key, mixed> $settings
 * @return array<string, mixed>
 */
function gc_with_settings_notes(array $result, array $settings, string $name): array
{
    $warnings = bricks_font_family_warnings($settings, sprintf('Global class "%s"', $name));
    $unresolved = bricks_unresolved_variables($settings);
    if ($unresolved !== []) {
        $result['unresolved_variables'] = $unresolved;
        $warnings[] = bricks_unresolved_variables_warning($unresolved);
    }
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

wp_register_ability('novamira/bricks-edit-global-class', [
    'label' => __('Edit Bricks Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Bricks global class by id. Provide at least one of: name or settings. Settings are deep-merged by default: associative objects merge by key, while indexed arrays/lists and scalars replace the prior value wholesale. Pass replace=true to wipe and replace the full settings object. Settings shape: typed shared style controls (`_padding`, `_margin`, `_typography`, `_background`, `_border`, …) first; `_cssCustom` is a last resort. Inside `_cssCustom` the `%root%` placeholder resolves server-side to the class selector (`.<name>`). Note: renaming a class does not retroactively rewrite already-resolved class selectors inside its stored `_cssCustom`; re-pass `_cssCustom` with `%root%` if you rename and want the CSS to track the new name.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the global class to edit.'],
            'name' => ['type' => 'string', 'description' => 'New name for the class.'],
            'settings' => [
                'type' => 'object',
                'description' => 'New or merged Bricks control values. Prefer the typed shared style controls (`_padding`, `_margin`, `_typography`, `_background`, `_border`, `_display`, flex/grid keys, …) over raw `_cssCustom`; classes built around typed controls stay editable from the Bricks class panel.',
            ],
            'replace' => [
                'type' => 'boolean',
                'description' => 'When true, the settings object replaces the existing one entirely. Default false (merge).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('global classes'),
            'class' => ['type' => 'object'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unresolved_variables' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_edit_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_edit_global_class(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }

    /** @var mixed $new_name_raw */
    $new_name_raw = $input['name'] ?? null;
    /** @var mixed $new_settings */
    $new_settings = $input['settings'] ?? null;

    if ($new_name_raw === null && $new_settings === null) {
        return bricks_error('invalid_input', message: 'Pass at least one of name or settings.');
    }

    $replace = ($input['replace'] ?? null) === true;

    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
    $classes = is_array($raw) ? $raw : [];

    $name_resolution = gc_resolve_edit_name($new_name_raw, $classes, $id);
    if ($name_resolution instanceof WP_Error) {
        return $name_resolution;
    }
    $new_name = $name_resolution['name'];

    $resolved_settings = $replace
        ? gc_resolve_replace_settings($new_settings)
        : gc_resolve_merge_settings($classes, $id, $new_settings);
    $result = gc_find_and_patch($classes, $id, $new_name, $resolved_settings);
    if ($result === null) {
        return bricks_error('not_found', sprintf('Global class "%s" not found.', $id));
    }

    $stored = gc_save_classes($result['classes']);

    return gc_verified(gc_edit_response($result['entry'], $id), $stored);
}

/**
 * Response of a completed edit: the entry plus its settings advisories.
 *
 * @param array<array-key, mixed> $entry
 * @return array<string, mixed>
 */
function gc_edit_response(array $entry, string $id): array
{
    return gc_with_settings_notes(
        ['class' => $entry],
        is_array($entry['settings'] ?? null) ? $entry['settings'] : [],
        is_string($entry['name'] ?? null) ? $entry['name'] : $id,
    );
}

/**
 * Validates and normalizes a name passed to bricks-edit-global-class. Mirrors
 * the create-time normalization (lowercase + space-to-dash) and rejects names
 * already used by any other class id.
 *
 * Returns ['name' => null] when no name was passed (caller leaves name
 * unchanged), ['name' => '<normalized>'] on success, or a WP_Error on
 * validation failure.
 *
 * @param  mixed        $raw_name
 * @param  array<mixed> $classes
 * @return array{name: ?string}|WP_Error
 */
function gc_resolve_edit_name(mixed $raw_name, array $classes, string $current_id): array|WP_Error
{
    if ($raw_name === null) {
        return ['name' => null];
    }
    if (!is_string($raw_name) || trim($raw_name) === '') {
        return bricks_error('invalid_input', message: 'invalid name.');
    }
    $normalized = preg_replace(pattern: '/\s+/', replacement: '-', subject: strtolower(trim($raw_name)));
    if (!is_string($normalized) || $normalized === '') {
        return bricks_error('invalid_input', message: 'invalid name.');
    }
    /** @var mixed $existing */
    foreach ($classes as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        if (($existing['id'] ?? null) === $current_id) {
            continue;
        }
        if (($existing['name'] ?? null) === $normalized) {
            return bricks_error('duplicate_name', sprintf('name "%s" already exists on another class.', $normalized));
        }
    }
    return ['name' => $normalized];
}

/**
 * Finds a class by id in the classes array, applies name/settings patches, and
 * returns the updated classes array + patched entry, or null if not found.
 * Pass resolved_settings=null to leave settings unchanged.
 *
 * @param  array<mixed>                 $classes
 * @param  string                       $id
 * @param  mixed                        $new_name
 * @param  array<array-key, mixed>|null $resolved_settings
 * @return array{classes: array<mixed>, entry: array<array-key, mixed>}|null
 */
function gc_find_and_patch(array $classes, string $id, mixed $new_name, ?array $resolved_settings): ?array
{
    $class_keys = array_keys($classes);
    foreach ($class_keys as $ck) {
        /** @var mixed $entry */
        $entry = $classes[$ck];
        if (!is_array($entry) || ($entry['id'] ?? null) !== $id) {
            continue;
        }
        if (is_string($new_name) && $new_name !== '') {
            $entry['name'] = $new_name;
        }
        if ($resolved_settings !== null) {
            $entry['settings'] = $resolved_settings;
        }
        $final_name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
        $final_settings = is_array($entry['settings'] ?? null) ? $entry['settings'] : [];
        if ($final_name !== '') {
            $entry['settings'] = bricks_resolve_global_class_root($final_settings, $final_name);
        }
        $classes[$ck] = $entry;
        return ['classes' => $classes, 'entry' => $entry];
    }
    return null;
}

/**
 * Returns the new_settings array verbatim (replace mode), or null if not an array.
 *
 * @param  mixed                        $new_settings
 * @return array<array-key, mixed>|null
 */
function gc_resolve_replace_settings(mixed $new_settings): ?array
{
    return is_array($new_settings) ? $new_settings : null;
}

/**
 * Merges new_settings into the existing settings of the matching class entry.
 * Returns null when new_settings is not an array.
 *
 * @param  array<mixed>                 $classes
 * @param  string                       $id
 * @param  mixed                        $new_settings
 * @return array<array-key, mixed>|null
 */
function gc_resolve_merge_settings(array $classes, string $id, mixed $new_settings): ?array
{
    if (!is_array($new_settings)) {
        return null;
    }
    /** @var mixed $entry */
    foreach ($classes as $entry) {
        if (!is_array($entry) || ($entry['id'] ?? null) !== $id) {
            continue;
        }
        /** @var array<string, mixed> $existing */
        $existing = is_array($entry['settings'] ?? null) ? $entry['settings'] : [];
        /** @var array<string, mixed> $new_settings */
        return bricks_deep_merge($existing, $new_settings);
    }
    return $new_settings;
}

wp_register_ability('novamira/bricks-delete-global-class', [
    'label' => __('Delete Bricks Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Bricks global class by id. Hard delete — any element referencing this class via "_cssGlobalClasses" will lose the applied styles.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the global class to delete.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('global classes'),
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_delete_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

function bricks_delete_global_class(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }

    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
    $classes = is_array($raw) ? $raw : [];

    $filtered = array_values(array_filter(
        $classes,
        static fn(mixed $c): bool => !(is_array($c) && ($c['id'] ?? null) === $id),
    ));

    if (count($filtered) === count($classes)) {
        return bricks_error('not_found', sprintf('Global class "%s" not found.', $id));
    }

    $stored = gc_save_classes($filtered);

    return gc_verified(['deleted' => true], $stored);
}

/**
 * Adds the read-back verification of the classes option to a response.
 *
 * @param array<string, mixed>              $result
 * @param list<array<string, mixed>>|null   $stored the list gc_save_classes stored, null when the row was deleted
 * @return array<string, mixed>
 */
function gc_verified(array $result, ?array $stored): array
{
    return bricks_with_option_write_verification($result, BRICKS_DB_GLOBAL_CLASSES, $stored);
}

/**
 * Persists the classes list (deleting the option row when empty) and
 * bumps the ownership stamps the builder reads; returns the normalised
 * list that was stored, null when the row was deleted.
 *
 * @param array<array-key, mixed> $classes
 * @return list<array<string, mixed>>|null
 */
function gc_save_classes(array $classes): ?array
{
    /** @var list<array<string, mixed>> $classes */
    $classes = array_values(array_filter($classes, static fn(mixed $c): bool => is_array($c) && $c !== []));

    if ($classes === []) {
        delete_option(BRICKS_DB_GLOBAL_CLASSES);
        return null;
    }

    update_option(BRICKS_DB_GLOBAL_CLASSES, $classes);
    if (defined('BRICKS_DB_GLOBAL_CLASSES_TIMESTAMP')) {
        update_option(BRICKS_DB_GLOBAL_CLASSES_TIMESTAMP, time());
    }
    if (defined('BRICKS_DB_GLOBAL_CLASSES_USER')) {
        update_option(BRICKS_DB_GLOBAL_CLASSES_USER, get_current_user_id());
    }

    return $classes;
}

wp_register_ability('novamira/bricks-apply-global-class', [
    'label' => __('Apply Bricks Global Class to Element', domain: 'novamira-pro'),
    'description' => __(
        'Attaches an existing Bricks global class to an element on a page by appending its id to the element\'s "_cssGlobalClasses" array. Validates the class exists. Idempotent — safe to call if the class is already attached. Area defaults to "content"; use "header" or "footer" for those areas. Note on the CSS cascade: Bricks generates element-level CSS as ID-scoped rules (`#brxe-<id> { ... }`) and global class CSS as class-scoped rules (`.<className> { ... }`). ID specificity beats class specificity, so any property already set on the element wins over the same property in a global class — applying the class will look like a no-op for those properties. If the visual effect is missing after applying, clear the conflicting element-level setting (e.g. via `bricks-patch-elements`) so the class can take effect.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'class_id' => [
                'type' => 'string',
                'description' => 'The id of the global class to apply.',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'The post/page id containing the target element.',
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'The Bricks element id to attach the class to.',
            ],
            'area' => [
                'type' => 'string',
                'enum' => ['header', 'content', 'footer'],
                'description' => 'Which Bricks area to target. Default "content".',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['class_id', 'post_id', 'element_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'applied' => ['type' => 'boolean'],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Id of the WordPress revision created before this write, carrying the Bricks element meta of every area as it was — restorable from the Bricks builder\'s revision panel or WordPress. Null when revisions are disabled for the post type (a warning says so).',
            ],
            'was_already_attached' => ['type' => 'boolean'],
            'classes' => ['type' => 'array'],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified (kept for existing callers; it is no longer a literal true). Read verified instead.',
            ],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True only when the element tree read back from the database after the write equals the tree that was written. False means the stored content differs — the warnings say how.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'Number of elements the area holds in the database after the write (read back). Absent when the class was already attached and nothing was written.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => '"regenerated" when Bricks serves CSS from external files and the per-post CSS file was rewritten for this write; "inline" when Bricks serves CSS inline (nothing to regenerate); "skipped(reason)" when file mode is on but the file could not be written — the reason names the fix.',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a written value references var(--name) for a name that is neither a Bricks global variable, a palette colour nor a custom property declared in the site\'s custom CSS: those declarations are dropped by the browser until the token exists.',
            ],
            'warnings' => [
                'type' => 'array',
                'description' => 'Non-fatal validation warnings produced by the post-mutation element-tree check.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_apply_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_apply_global_class(array $input): array|WP_Error
{
    $ctx = gc_validate_apply_input($input);
    if ($ctx instanceof WP_Error) {
        return $ctx;
    }

    // The post's existence has to be established BEFORE the area is read:
    // get_post_meta() answers '' for a post that does not exist and for one
    // that simply has no Bricks content, so without this check an unknown
    // post_id was reported as "no elements found for this post/area" — a 409
    // telling the caller to add content to a post that is not there.
    if (!get_post($ctx['post_id']) instanceof WP_Post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $ctx['post_id']));
    }

    $raw_elements = bricks_area_elements_or_error(
        $ctx['post_id'],
        $ctx['area'],
        action: 'there is nothing to apply the class to',
    );
    if ($raw_elements instanceof WP_Error) {
        return $raw_elements;
    }
    if ($raw_elements === []) {
        return bricks_error('empty_area', sprintf(
            'The "%s" area on post %d holds no Bricks elements, so there is nothing to apply the class to. Add content with novamira/bricks-insert-content or novamira/bricks-set-content first.',
            $ctx['area'],
            $ctx['post_id'],
        ));
    }

    $patch = gc_attach_class_to_element($raw_elements, $ctx['element_id'], $ctx['class_id']);
    if ($patch === null) {
        return bricks_error('not_found', sprintf(
            'Element "%s" not found in %s area.',
            $ctx['element_id'],
            $ctx['area'],
        ));
    }

    /** @var list<array<string, mixed>> $elements_for_validation */
    $elements_for_validation = array_values(array_filter($patch['elements'], static fn(mixed $el): bool => is_array(
        $el,
    )));
    $validation = bricks_validate_elements($elements_for_validation);
    if (count($validation['errors']) > 0) {
        return bricks_write_validation_error($ctx['post_id'], $ctx['area'], $validation['errors']);
    }

    $response = [
        'applied' => true,
        'was_already_attached' => $patch['was_already_attached'],
        'classes' => $patch['classes'],
    ];
    if (!$patch['was_already_attached']) {
        // Attaching a class id never touches code: no re-signing.
        $commit = bricks_commit_area_write(
            $ctx['post_id'],
            $ctx['area'],
            $elements_for_validation,
            [],
            [
                'sign' => false,
                'force' => ($input['force'] ?? false) === true,
            ],
        );
        if ($commit instanceof WP_Error) {
            return $commit;
        }
        $response = bricks_with_write_verification($response, $commit);
        $validation['warnings'] = array_merge($validation['warnings'], $commit['warnings']);
    }
    if (count($validation['warnings']) > 0) {
        $response['warnings'] = $validation['warnings'];
    }

    return $response;
}

/**
 * Validates the input for bricks_apply_global_class and returns a context array
 * with resolved values, or a WP_Error on failure.
 *
 * @param  array<mixed> $input
 * @return array{class_id: string, post_id: int, element_id: string, area: string, meta_key: string}|WP_Error
 */
function gc_validate_apply_input(array $input): array|WP_Error
{
    /** @var mixed $class_id */
    $class_id = $input['class_id'] ?? '';
    if (!is_string($class_id) || $class_id === '') {
        return bricks_error('invalid_input', message: 'class_id is required.');
    }

    /** @var mixed $post_id_raw */
    $post_id_raw = $input['post_id'] ?? 0;
    $post_id = is_int($post_id_raw) ? $post_id_raw : 0;
    if ($post_id <= 0) {
        return bricks_error('invalid_input', message: 'post_id must be a positive integer.');
    }

    /** @var mixed $element_id */
    $element_id = $input['element_id'] ?? '';
    if (!is_string($element_id) || $element_id === '') {
        return bricks_error('invalid_input', message: 'element_id is required.');
    }

    /** @var mixed $area_raw */
    $area_raw = $input['area'] ?? 'content';
    $area = is_string($area_raw) ? $area_raw : 'content';

    $meta_key = gc_resolve_area_meta_key($area);
    if ($meta_key === null) {
        return bricks_error('invalid_input', message: 'area must be one of: header, content, footer.');
    }

    if (!gc_global_class_exists($class_id)) {
        return bricks_error('not_found', sprintf('Global class "%s" not found.', $class_id));
    }

    return [
        'class_id' => $class_id,
        'post_id' => $post_id,
        'element_id' => $element_id,
        'area' => $area,
        'meta_key' => $meta_key,
    ];
}

/**
 * Returns the Bricks post-meta key for the given area, or null if invalid.
 */
function gc_resolve_area_meta_key(string $area): ?string
{
    $meta_keys = [
        'header' => BRICKS_DB_PAGE_HEADER,
        'content' => BRICKS_DB_PAGE_CONTENT,
        'footer' => BRICKS_DB_PAGE_FOOTER,
    ];
    return $meta_keys[$area] ?? null;
}

/**
 * Returns true when a global class with the given id exists in the store.
 */
function gc_global_class_exists(string $class_id): bool
{
    /** @var mixed $raw_classes */
    $raw_classes = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
    $all_classes = is_array($raw_classes) ? $raw_classes : [];

    /** @var mixed $gc */
    foreach ($all_classes as $gc) {
        if (is_array($gc) && ($gc['id'] ?? null) === $class_id) {
            return true;
        }
    }
    return false;
}

/**
 * Finds the target element in the elements array and attaches the class id.
 * Returns null when the element is not found.
 * Returns array{elements: array<mixed>, classes: array<mixed>, was_already_attached: bool} on success.
 *
 * @param  array<mixed> $elements
 * @return array{elements: array<mixed>, classes: array<mixed>, was_already_attached: bool}|null
 */
function gc_attach_class_to_element(array $elements, string $element_id, string $class_id): ?array
{
    $element_keys = array_keys($elements);
    foreach ($element_keys as $ek) {
        /** @var mixed $element */
        $element = $elements[$ek];
        if (!is_array($element) || ($element['id'] ?? null) !== $element_id) {
            continue;
        }
        return gc_append_class_to_element($elements, $ek, $element, $class_id);
    }
    return null;
}

/**
 * Appends a class id to an element's settings._cssGlobalClasses array.
 * If already present, returns was_already_attached: true without modifying.
 *
 * @param  array<mixed>            $elements
 * @param  array-key               $ek
 * @param  array<array-key, mixed> $element
 * @return array{elements: array<mixed>, classes: array<mixed>, was_already_attached: bool}
 */
function gc_append_class_to_element(array $elements, mixed $ek, array $element, string $class_id): array
{
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    $existing = is_array($settings['_cssGlobalClasses'] ?? null) ? $settings['_cssGlobalClasses'] : [];

    if (in_array($class_id, $existing, strict: true)) {
        return ['elements' => $elements, 'classes' => $existing, 'was_already_attached' => true];
    }

    $existing[] = $class_id;
    $settings['_cssGlobalClasses'] = $existing;
    $element['settings'] = $settings;
    $elements[$ek] = $element;

    return ['elements' => $elements, 'classes' => $existing, 'was_already_attached' => false];
}
