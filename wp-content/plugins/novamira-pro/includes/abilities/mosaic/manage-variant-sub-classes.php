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
 * Variant sub classes (`<prefix>mosaic_variant_sub_classes`): theme-scoped
 * refinements of a built-in Variant, nested to any depth, rendered as
 * `<variant class>--<sub>` (e.g. `m-button--primary`). An element selects one
 * through `style.variant = "<variantID>/<subClassID>"`. See class-model.php.
 */

// ---------------------------------------------------------------------------
// get-variant-sub-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-get-variant-sub-class', [
    'label' => __('Get Mosaic Variant Sub Class', domain: 'novamira-pro'),
    'description' => __(
        'Returns one variant sub class in full: id, theme_id, variant_id (the built-in Variant it refines), parent_id and parent_type (`variant` or `variant_sub_class`), name, label, emitted_name (e.g. `m-button--primary`), legacy_class_name, ordering, revision, `sub_classes` (its nested children) and `data` (the decoded record with its `states`). Returns `{variant_sub_class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Variant sub class UUID — find it with mosaic-list-variants.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['variant_sub_class' => ['type' => 'object']],
        'required' => ['variant_sub_class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_variant_sub_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Select it on an element with the `variant` input (its UUID or emitted_name).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// create-variant-sub-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-create-variant-sub-class', [
    'label' => __('Create Mosaic Variant Sub Class', domain: 'novamira-pro'),
    'description' => __(
        'Creates a variant sub class — a theme-level refinement of a built-in Variant such as "Primary" under Button — under a Variant or under one of its variant sub classes (no depth limit), through Mosaic\'s own manager. It renders as `<variant class>--<name>` (e.g. `m-button--primary`). `name` is sanitized (lowercase, separators become `-`) and a name already used by a sibling gets `-2`, `-3`… — read the returned `name` / `emitted_name`. Only Variants whose `can_have_sub_classes` is true in mosaic-list-variants accept one. Returns `{variant_sub_class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string', 'description' => 'Theme UUID.'],
            'parent_id' => [
                'type' => 'string',
                'description' => 'The Variant (UUID or class name such as `m-button`) or the variant sub class (UUID) to create it under.',
            ],
            'name' => ['type' => 'string', 'description' => 'Name, e.g. `primary` (max 191 characters).'],
            'label' => ['type' => 'string', 'description' => 'Optional display label for Mosaic\'s editor.'],
        ],
        'required' => ['theme_id', 'parent_id', 'name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['variant_sub_class' => ['type' => 'object']],
        'required' => ['variant_sub_class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_create_variant_sub_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Style it with mosaic-set-variant-sub-class-style, then select it on elements with the `variant` input.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// edit-variant-sub-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-edit-variant-sub-class', [
    'label' => __('Edit Mosaic Variant Sub Class', domain: 'novamira-pro'),
    'description' => __(
        'Renames or relabels a variant sub class through Mosaic\'s own manager. Editable: `name` (sanitized and deduped like mosaic-create-variant-sub-class) and `label` (`""` clears it). Elements keep their selection because they reference it by UUID. Returns the stored `{variant_sub_class}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variant sub class UUID.'],
            'name' => ['type' => 'string', 'description' => 'New name. Pass only the fields you want to change.'],
            'label' => ['type' => 'string', 'description' => 'New display label; `""` clears it.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['variant_sub_class' => ['type' => 'object']],
        'required' => ['variant_sub_class'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_variant_sub_class',
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
// delete-variant-sub-class
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-delete-variant-sub-class', [
    'label' => __('Delete Mosaic Variant Sub Class', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes a variant sub class together with the variant sub classes nested under it, the way Mosaic\'s editor does: the records are removed and elements that had one selected fall back to the nearest surviving parent (a parent variant sub class, or the plain Variant). Returns `{id, deleted, deleted_ids}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variant sub class UUID.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => mo_class_delete_output_schema(),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_delete_variant_sub_class',
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
// set-variant-sub-class-style
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-set-variant-sub-class-style', [
    'label' => __('Set Mosaic Variant Sub Class Style', domain: 'novamira-pro'),
    'description' => __(
        'Sets one validated CSS property on one state and breakpoint of a variant sub class. Same contract as mosaic-set-class-style: merged into `data.states[state][breakpoint_id]`, `state` defaults to `hover` (`normal` aliases `&`), `breakpoint_id` accepts a UUID or `base` / `tablet` / `mobile`, `property` accepts camelCase or kebab-case, validated by Mosaic.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => mo_class_style_input_schema('Variant sub class UUID.'),
    'output_schema' => mo_class_style_output_schema('variant_sub_class'),
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_set_variant_sub_class_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'One call per property and breakpoint. The sub class renders on top of its Variant\'s own styles.',
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
function mosaic_get_variant_sub_class(array $input): array|WP_Error
{
    return mo_get_class_record('variant_sub_class', record_key: 'variant_sub_class', input: $input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_create_variant_sub_class(array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    $parent_ref = is_string($input['parent_id'] ?? null) ? trim($input['parent_id']) : '';
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    $err =
        $parent_ref === '' || $name === ''
            ? mo_error('mo_invalid_input', __('theme_id, parent_id and name are required.', domain: 'novamira-pro'))
            : mo_require_publish_theme($theme_id);
    if ($err !== null) {
        return $err;
    }
    $parent = mo_resolve_variant_sub_class_parent($theme_id, $parent_ref);
    if ($parent instanceof WP_Error) {
        return $parent;
    }
    return mo_create_class_record(
        [
            'kind' => 'variant_sub_class',
            'theme_id' => $theme_id,
            'name' => $name,
            'label' => is_string($input['label'] ?? null) ? trim($input['label']) : '',
            'parent_type' => $parent['parent_type'],
            'parent_id' => $parent['parent_id'],
        ],
        record_key: 'variant_sub_class',
        ability: 'novamira/mosaic-create-variant-sub-class',
    );
}

/**
 * A variant sub class hangs under a catalog Variant that accepts sub classes
 * (parent type `variant`) or under a published variant sub class of the same
 * theme (parent type `variantSubClass`) —
 * VariantSubClassesMResourceManager::getParentMResource() :84-96.
 *
 * @return array{parent_type:string, parent_id:string}|WP_Error
 */
function mo_resolve_variant_sub_class_parent(string $theme_id, string $parent_ref): array|WP_Error
{
    $index = mo_class_index($theme_id);
    $resolved = mo_resolve_variant_ref($index, $parent_ref);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    if ($resolved['sub_class'] !== '') {
        return ['parent_type' => 'variantSubClass', 'parent_id' => $resolved['sub_class']];
    }
    $meta = mo_variant_catalog()[$resolved['variant']] ?? null;
    if ($meta === null || !mo_variant_accepts_sub_classes($meta)) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: variant reference. */
            __(
                'Variant `%s` cannot have sub classes in Mosaic. Pick a Variant whose `can_have_sub_classes` is true in mosaic-list-variants.',
                domain: 'novamira-pro',
            ),
            $parent_ref,
        ));
    }
    return ['parent_type' => 'variant', 'parent_id' => $resolved['variant']];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_variant_sub_class(array $input): array|WP_Error
{
    return mo_edit_class_record(
        'variant_sub_class',
        record_key: 'variant_sub_class',
        ability: 'novamira/mosaic-edit-variant-sub-class',
        input: $input,
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_delete_variant_sub_class(array $input): array|WP_Error
{
    return mo_delete_class_record(
        'variant_sub_class',
        ability: 'novamira/mosaic-delete-variant-sub-class',
        input: $input,
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_set_variant_sub_class_style(array $input): array|WP_Error
{
    return mo_set_class_style(
        'variant_sub_class',
        record_key: 'variant_sub_class',
        ability: 'novamira/mosaic-set-variant-sub-class-style',
        input: $input,
    );
}
