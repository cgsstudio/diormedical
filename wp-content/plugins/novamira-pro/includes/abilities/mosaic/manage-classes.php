<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Mosaic Classes (root records of Mosaic's Class system). Storage, naming and
 * delete semantics are documented in class-model.php; every write goes through
 * Mosaic's own managers.
 */

// ---------------------------------------------------------------------------
// get-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-get-class', [
    'label' => __('Get Mosaic Class', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Mosaic Class in full: id, theme_id, name, label, emitted_name (the class name Mosaic renders), legacy_class_name (an older flat name some migrated Classes still render as an extra alias; empty for most), ordering, revision, favored_variants, `sub_classes` (the nested sub-class tree, each with id, name and emitted_name) and `data` (the decoded record, whose `states` hold the per-state, per-breakpoint styles). `favored_variants` only controls which elements Mosaic\'s editor suggests the Class for; it has no effect on rendering. Returns `{class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Class UUID — get one from mosaic-list-classes.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['class' => ['type' => 'object']],
        'required' => ['class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a Class\'s styles here before changing them with mosaic-set-class-style. Its sub classes are managed with the mosaic-*-sub-class abilities.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// create-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-create-class', [
    'label' => __('Create Mosaic Class', domain: 'novamira-pro'),
    'description' => __(
        'Creates a Class in Mosaic\'s Class system through Mosaic\'s own class manager. `name` becomes the class name Mosaic renders: Mosaic lowercases it, turns spaces and other separators into `-` (e.g. "Card Large" → `card-large`) and, when another Class already uses that name, appends `-2`, `-3`… — so read the returned `name` / `emitted_name` rather than assuming your input. A Class name must start with a letter and cannot contain `--`; a leading `m-` is reserved for Mosaic and stripped. Optional `label` is the display text Mosaic\'s editor shows instead of the name. Add styles with mosaic-set-class-style and sub classes with mosaic-create-sub-class, then attach it to elements with the `classes` input of mosaic-add-element / mosaic-edit-element. Returns `{class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string', 'description' => 'Theme UUID.'],
            'name' => [
                'type' => 'string',
                'description' => 'Class name, e.g. `card` or "Card Large" (max 191 characters).',
            ],
            'label' => [
                'type' => 'string',
                'description' => 'Optional display label for Mosaic\'s editor (max 191 characters).',
            ],
        ],
        'required' => ['theme_id', 'name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['class' => ['type' => 'object']],
        'required' => ['class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_create_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use the returned emitted_name or id with the `classes` input of mosaic-add-element / mosaic-edit-element. Never put a Mosaic Class name into `css_classes`.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// edit-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-edit-class', [
    'label' => __('Edit Mosaic Class', domain: 'novamira-pro'),
    'description' => __(
        'Renames or relabels a Mosaic Class through Mosaic\'s own class manager. Editable: `name` (same rules and sanitizing as mosaic-create-class; a rename also changes the emitted names of all its sub classes) and `label` (`""` clears it). Elements keep their attachment because they reference the Class by UUID, so they pick up the new class name on the next render. Styles are left untouched. Returns the stored `{class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Class UUID.'],
            'name' => ['type' => 'string', 'description' => 'New Class name. Pass only the fields you want to change.'],
            'label' => ['type' => 'string', 'description' => 'New display label; `""` clears it.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['class' => ['type' => 'object']],
        'required' => ['class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the returned name / emitted_name: Mosaic may sanitize or dedupe a new name.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// delete-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-delete-class', [
    'label' => __('Delete Mosaic Class', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes a Mosaic Class together with all of its sub classes, the way Mosaic\'s editor does: the records are removed and Mosaic detaches the Class from every element in the theme that had it attached. Returns `{id, deleted, deleted_ids}`; `deleted` is false when the Class did not exist.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Class UUID.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => mo_class_delete_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_delete_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent. Elements that used the Class lose its styling; there is no trash to restore it from.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// set-class-style
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-set-class-style', [
    'label' => __('Set Mosaic Class Style', domain: 'novamira-pro'),
    'description' => __(
        'Sets one validated CSS property on one state and breakpoint of a Mosaic Class. The value is merged into `data.states[state][breakpoint_id]`; unrelated states, breakpoints and properties are preserved. `state` defaults to `hover` and accepts Mosaic state IDs (`normal` aliases `&`). `breakpoint_id` accepts a Mosaic breakpoint UUID or the `base`, `tablet`, `mobile` aliases. `property` accepts Mosaic\'s camelCase key or its CSS kebab-case form. The write goes through Mosaic\'s own style validator and class manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => mo_class_style_input_schema('Class UUID — get one from mosaic-list-classes.'),
    'output_schema' => mo_class_style_output_schema('class'),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_set_class_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'One call per property and breakpoint. Prefer var(--token) values from mosaic-list-variables over literals.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Callbacks
// ---------------------------------------------------------------------------

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_get_class(array $input): array|WP_Error
{
    return mo_get_class_record('class', record_key: 'class', input: $input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_create_class(array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    $err = $name === ''
        ? mo_error('mo_invalid_input', __('theme_id and name are required.', domain: 'novamira-pro'))
        : mo_require_publish_theme($theme_id);
    if ($err !== null) {
        return $err;
    }
    return mo_create_class_record(
        [
            'kind' => 'class',
            'theme_id' => $theme_id,
            'name' => $name,
            'label' => is_string($input['label'] ?? null) ? trim($input['label']) : '',
            'parent_type' => '',
            'parent_id' => '',
        ],
        record_key: 'class',
        ability: 'novamira/mosaic-create-class',
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_class(array $input): array|WP_Error
{
    return mo_edit_class_record('class', record_key: 'class', ability: 'novamira/mosaic-edit-class', input: $input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_delete_class(array $input): array|WP_Error
{
    return mo_delete_class_record('class', ability: 'novamira/mosaic-delete-class', input: $input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_set_class_style(array $input): array|WP_Error
{
    return mo_set_class_style('class', record_key: 'class', ability: 'novamira/mosaic-set-class-style', input: $input);
}
