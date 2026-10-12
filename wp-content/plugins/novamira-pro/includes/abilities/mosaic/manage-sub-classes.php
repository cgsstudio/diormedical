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
 * Sub classes of Mosaic Classes (`<prefix>mosaic_universal_sub_classes`). A sub
 * class hangs under a Class or under another sub class, to any depth, and
 * renders as `<class>--<sub>--<sub>`. See class-model.php.
 */

// ---------------------------------------------------------------------------
// get-sub-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-get-sub-class', [
    'label' => __('Get Mosaic Sub Class', domain: 'novamira-pro'),
    'description' => __(
        'Returns one sub class of a Mosaic Class in full: id, theme_id, class_id (its root Class), parent_id and parent_type (`class` or `sub_class`), name, label, emitted_name (the compound class name Mosaic renders, e.g. `card--large`), legacy_class_name, ordering, revision, `sub_classes` (its nested children) and `data` (the decoded record with its `states`). Returns `{sub_class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Sub class UUID — find it in the `sub_classes` tree of mosaic-get-class.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['sub_class' => ['type' => 'object']],
        'required' => ['sub_class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_sub_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Attach a sub class to an element with the `classes` input (its UUID or emitted_name); it replaces whichever entry of the same Class the element had.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// create-sub-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-create-sub-class', [
    'label' => __('Create Mosaic Sub Class', domain: 'novamira-pro'),
    'description' => __(
        'Creates a sub class under a Mosaic Class or under one of its sub classes (nesting has no depth limit), through Mosaic\'s own manager. The sub class renders as the compound `<class>--<sub class>` name and carries its own styles on top of its parents\'. `name` is sanitized like a Class name (lowercase, separators become `-`) but may start with a digit; a name already used by a sibling gets `-2`, `-3`… — read the returned `name` / `emitted_name`. Optional `label` is editor display text. Returns `{sub_class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'parent_id' => [
                'type' => 'string',
                'description' => 'UUID of the Class, or of the sub class, to create it under.',
            ],
            'name' => ['type' => 'string', 'description' => 'Sub class name, e.g. `large` (max 191 characters).'],
            'label' => ['type' => 'string', 'description' => 'Optional display label for Mosaic\'s editor.'],
        ],
        'required' => ['parent_id', 'name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['sub_class' => ['type' => 'object']],
        'required' => ['sub_class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_create_sub_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Style it with mosaic-set-sub-class-style; attach it with the `classes` input of mosaic-add-element / mosaic-edit-element.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// edit-sub-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-edit-sub-class', [
    'label' => __('Edit Mosaic Sub Class', domain: 'novamira-pro'),
    'description' => __(
        'Renames or relabels a sub class of a Mosaic Class through Mosaic\'s own manager. Editable: `name` (sanitized and deduped like mosaic-create-sub-class; nested sub classes follow the new compound name) and `label` (`""` clears it). Elements keep their attachment because they reference it by UUID. Returns the stored `{sub_class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Sub class UUID.'],
            'name' => ['type' => 'string', 'description' => 'New name. Pass only the fields you want to change.'],
            'label' => ['type' => 'string', 'description' => 'New display label; `""` clears it.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['sub_class' => ['type' => 'object']],
        'required' => ['sub_class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_sub_class',
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
// delete-sub-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-delete-sub-class', [
    'label' => __('Delete Mosaic Sub Class', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes a sub class of a Mosaic Class together with the sub classes nested under it, the way Mosaic\'s editor does: the records are removed and elements that had one of them attached fall back to the nearest surviving parent (the parent sub class, or the Class itself). Returns `{id, deleted, deleted_ids}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Sub class UUID.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => mo_class_delete_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_delete_sub_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent; there is no trash to restore it from.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// set-sub-class-style
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-set-sub-class-style', [
    'label' => __('Set Mosaic Sub Class Style', domain: 'novamira-pro'),
    'description' => __(
        'Sets one validated CSS property on one state and breakpoint of a sub class of a Mosaic Class. Same contract as mosaic-set-class-style: merged into `data.states[state][breakpoint_id]`, `state` defaults to `hover` (`normal` aliases `&`), `breakpoint_id` accepts a UUID or `base` / `tablet` / `mobile`, `property` accepts camelCase or kebab-case, validated by Mosaic.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => mo_class_style_input_schema('Sub class UUID.'),
    'output_schema' => mo_class_style_output_schema('sub_class'),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_set_sub_class_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'One call per property and breakpoint. Only set what differs from the parent Class — the sub class renders on top of it.',
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
function mosaic_get_sub_class(array $input): array|WP_Error
{
    return mo_get_class_record('sub_class', record_key: 'sub_class', input: $input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_create_sub_class(array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $parent_id = mo_norm_uuid($input['parent_id'] ?? null);
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    if ($parent_id === '' || $name === '') {
        return mo_error('mo_invalid_input', __('parent_id and name are required.', domain: 'novamira-pro'));
    }
    $parent = mo_resolve_sub_class_parent($parent_id);
    if ($parent instanceof WP_Error) {
        return $parent;
    }
    return mo_create_class_record(
        [
            'kind' => 'sub_class',
            'theme_id' => $parent['theme_id'],
            'name' => $name,
            'label' => is_string($input['label'] ?? null) ? trim($input['label']) : '',
            'parent_type' => $parent['parent_type'],
            'parent_id' => $parent_id,
        ],
        record_key: 'sub_class',
        ability: 'novamira/mosaic-create-sub-class',
    );
}

/**
 * A sub class parent is either a published Class or a published, non-orphaned
 * sub class. Mosaic's manager names these parent types `universalClass` /
 * `universalSubClass` (UniversalSubClassesMResourceManager::getParentMResource()).
 *
 * @return array{theme_id:string, parent_type:string}|WP_Error
 */
function mo_resolve_sub_class_parent(string $parent_id): array|WP_Error
{
    $class = mo_load('universal_classes', $parent_id);
    if ($class !== null && ($class['status'] ?? '') === 'publish') {
        return ['theme_id' => mo_str($class, key: 'themeID'), 'parent_type' => 'universalClass'];
    }
    $sub = mo_load('universal_sub_classes', $parent_id);
    if ($sub !== null && ($sub['status'] ?? '') === 'publish') {
        $theme_id = mo_str($sub, key: 'themeID');
        if (isset(mo_class_index($theme_id)['sub_classes'][$parent_id])) {
            return ['theme_id' => $theme_id, 'parent_type' => 'universalSubClass'];
        }
    }
    return mo_error('mo_not_found', sprintf(
        /* translators: %s: parent UUID. */
        __('parent_id %s is not a Mosaic Class or sub class.', domain: 'novamira-pro'),
        $parent_id,
    ));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_sub_class(array $input): array|WP_Error
{
    return mo_edit_class_record(
        'sub_class',
        record_key: 'sub_class',
        ability: 'novamira/mosaic-edit-sub-class',
        input: $input,
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_delete_sub_class(array $input): array|WP_Error
{
    return mo_delete_class_record('sub_class', ability: 'novamira/mosaic-delete-sub-class', input: $input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_set_sub_class_style(array $input): array|WP_Error
{
    return mo_set_class_style(
        'sub_class',
        record_key: 'sub_class',
        ability: 'novamira/mosaic-set-sub-class-style',
        input: $input,
    );
}
