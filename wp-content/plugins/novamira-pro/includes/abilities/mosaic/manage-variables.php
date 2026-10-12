<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// get-variable
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-get-variable', [
    'label' => __('Get Mosaic Variable', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full record for one Mosaic collection variable (design token), including the structured `data` blob with `type`, `customProperty`, `propertyGroups`, `modesData`, and `skinsData`. `skinsData` carries the per-skin × per-mode value the renderer emits at front-end render time — read it here, and write it with `mosaic-set-variable-value` (color / length / n-length types only). Returns `{variable}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variable UUID — get one from mosaic-list-variables.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['variable' => ['type' => 'object']],
        'required' => ['variable'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to inspect the per-skin / per-mode value graph designers set in Mosaic\'s editor.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// create-variable
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-create-variable', [
    'label' => __('Create Mosaic Variable', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Mosaic design-token variable under an existing collection. Routes through `CollectionVariablesMResourceManager::restoreCollectionVariableMResource`, which dispatches to the sub-factory matching `type` and auto-seeds `skinsData` with Mosaic\'s default per-type value (e.g. `#FFF` for color, `0px` for length). Inputs: `collection_id` (parent — get one from mosaic-list-collections), `name` (label), `custom_property` (the CSS custom property emitted at render time — e.g. `--brand-primary`; must start with `--`), `type` (one of `color` / `length` / `n-length` / `font` / `background` / `box-shadow` / `text-shadow`). Mosaic may normalize the custom property or suffix it (`--x` → `--x-2`) when another variable already emits that name; the stored values are returned, and `css_variable` is the name to reference. After creating, set a real value with `mosaic-set-variable-value` (color / length / n-length types only — `font` / `background` / `box-shadow` / `text-shadow` values still need Mosaic\'s editor). mosaic-get-variable reports Mosaic\'s own type name (`color`, `lengthPercentage`, `numberLengthPercentage`, `fontFamily`, `imageGradient`, `boxShadow`, `textShadow`).',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'collection_id' => ['type' => 'string', 'description' => 'Collection UUID — the variable\'s parent.'],
            'name' => ['type' => 'string', 'description' => 'Display name shown in Mosaic\'s editor (max 191 chars).'],
            'custom_property' => [
                'type' => 'string',
                'description' => 'CSS custom property name emitted at render time (e.g. `--brand-primary`). Must start with `--` and use only ASCII letters / digits / dashes / underscores.',
            ],
            'type' => [
                'type' => 'string',
                'enum' => ['color', 'length', 'n-length', 'font', 'background', 'box-shadow', 'text-shadow'],
                'description' => 'Variable kind. `color` for hex / rgb / named colors. `length` for spacings / sizes (px, rem, em, %). `n-length` for a length or a bare unitless number (e.g. line-height). `font` for font-family stacks. `background` for background layers (images / gradients) — it emits one custom property per background longhand (`<css_variable>-background-image`, `-background-position`, `-background-size`, `-background-repeat`, `-background-attachment`, `-background-blend-mode`, `-background-clip`), never a bare `<css_variable>`. `box-shadow` and `text-shadow` for shadow lists. Mosaic\'s sub-factory seeds the default per-type value into `skinsData`.',
            ],
        ],
        'required' => ['collection_id', 'name', 'custom_property', 'type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['variable' => ['type' => 'object']],
        'required' => ['variable'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_create_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'After create, the default value is Mosaic\'s built-in for the type. Set a real value with mosaic-set-variable-value (color / length / n-length), or open the variable in Mosaic\'s editor for font / background / box-shadow / text-shadow types.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// edit-variable
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-edit-variable', [
    'label' => __('Edit Mosaic Variable', domain: 'novamira-pro'),
    'description' => __(
        'Updates the identity fields of an existing Mosaic variable through Mosaic\'s own variable manager. Editable: `name`, `custom_property`. Mosaic normalizes the custom property and resolves name collisions (`--x` → `--x-2`), so read the returned `custom_property` / `css_variable`. The per-skin × per-mode value graph (`data.skinsData`, `data.modesData`) is preserved. Renaming `custom_property` changes the emitted CSS custom property on the front-end immediately.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variable UUID.'],
            'name' => ['type' => 'string', 'description' => 'New display name (max 191 chars).'],
            'custom_property' => [
                'type' => 'string',
                'description' => 'New CSS custom property name (must start with `--`).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['variable' => ['type' => 'object']],
        'required' => ['variable'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass only the fields you want to change. The value graph under data.skinsData is preserved.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// delete-variable
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-delete-variable', [
    'label' => __('Delete Mosaic Variable', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes a Mosaic variable through Mosaic\'s own delete path: the record is removed and Mosaic detaches it from the element and class styles that referenced it, the same way its editor does. Styles that write the custom property by name as literal text (e.g. `var(--brand)` in an inline style) are not rewritten. Returns `{id, deleted}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variable UUID.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'string'], 'deleted' => ['type' => 'boolean']],
        'required' => ['id', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_delete_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent. Check where the variable is used first; inline styles that name its custom property as text keep the dangling reference.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// set-variable-value
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-set-variable-value', [
    'label' => __('Set Mosaic Variable Value', domain: 'novamira-pro'),
    'description' => __(
        'Sets the value of an existing Mosaic design-token variable for one skin × mode combination (`data.skinsData[skin][mode].value`), preserving every other skin/mode value and the variable\'s identity fields (name, custom_property, type). Supports `color` variables (hex `#f00`/`#ff0000`/`#ff0000cc`, CSS named colors, `rgb()`/`rgba()`/`hsl()`/`hsla()`/`oklab()`/`oklch()`/`color()`/`color-mix()`/`var()`) and `length` / `n-length` variables (a number + CSS unit like `16px`/`1.5rem`/`50%`, or `calc()`/`clamp()`/`min()`/`max()`/`var()`; `n-length` additionally allows a bare unitless number). `font`, `background`, `box-shadow` and `text-shadow` variables hold a structured value that this ability does not write — open them in Mosaic\'s editor. `skin` / `mode` accept either the row UUID or the exact display name (e.g. "Dark") and default to the collection\'s first published skin/mode, which covers the common case of a design system with only one skin and one mode.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Variable UUID — get one from mosaic-list-variables.'],
            'value' => [
                'type' => 'string',
                'description' => 'The literal CSS value to store, matching the variable\'s type: a color (hex / named / rgb() / hsl() / ...) or a length (e.g. "16px", "1.5rem", "50%", or calc()/clamp()/min()/max()/var()).',
            ],
            'skin' => [
                'type' => 'string',
                'description' => 'Skin UUID or exact display name (e.g. "Default"). Omit to target the collection\'s first published skin.',
            ],
            'mode' => [
                'type' => 'string',
                'description' => 'Mode UUID or exact display name (e.g. "Light", "Dark", "Default"). Omit to target the collection\'s first published mode.',
            ],
        ],
        'required' => ['id', 'value'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['variable' => ['type' => 'object']],
        'required' => ['variable'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_set_variable_value',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Only color and length / n-length variables are supported today. Omit skin/mode to target the design system\'s only skin+mode (the common case with no light/dark variants yet); pass the display name (e.g. mode="Dark") when the collection has more than one.',
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
 * Map an agent-facing variable `type` to the Mosaic-native sub-factory
 * type string. Kept here so the agent-facing enum stays short and
 * readable while Mosaic's internal names (camelCase, sometimes long)
 * remain authoritative on persistence.
 */
function mo_variable_type_to_mosaic(string $type): string
{
    return match ($type) {
        'length' => 'lengthPercentage',
        // Fixed alongside the mosaic-set-variable-value ability: Mosaic's own
        // CollectionTypesConst::COLLECTION_VARIABLE_TYPES has no `nLengthPercentage`
        // / `font` sub-factory — the prior mapping made `type=n-length` and
        // `type=font` create() calls ALWAYS fail (CollectionVariableMResourceFactory::
        // getSubFactory() throws "SubFactory is not defined", swallowed by
        // restoreCollectionVariableMResource()'s catch, surfacing as mo_facade_failed).
        'n-length' => 'numberLengthPercentage',
        'font' => 'fontFamily',
        // Mosaic has no `stacked` sub-factory: each layered type is its own.
        'background' => 'imageGradient',
        'box-shadow' => 'boxShadow',
        'text-shadow' => 'textShadow',
        default => $type, // color passes through verbatim
    };
}

/**
 * Inverse of {@see mo_variable_type_to_mosaic()} — used to phrase agent-
 * facing messages in the short public vocabulary (color/length/n-length/
 * font/background/box-shadow/text-shadow) instead of Mosaic's internal type strings.
 */
function mo_variable_type_from_mosaic(string $mosaic_type): string
{
    return match ($mosaic_type) {
        'lengthPercentage' => 'length',
        'numberLengthPercentage' => 'n-length',
        'fontFamily' => 'font',
        'imageGradient' => 'background',
        'boxShadow' => 'box-shadow',
        'textShadow' => 'text-shadow',
        default => $mosaic_type, // color passes through
    };
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_get_variable(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('collection_variables', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: variable UUID. */
            __('Mosaic variable %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    return ['variable' => mo_present_variable_full($row)];
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_create_variable(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $params = mo_extract_create_variable_params($input);
    if ($params instanceof WP_Error) {
        return $params;
    }
    $collection = mo_load('collections', $params['collection_id']);
    if ($collection === null || ($collection['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: collection UUID. */
            __('Mosaic collection %s not found.', domain: 'novamira-pro'),
            $params['collection_id'],
        ));
    }
    $theme_id = mo_str($collection, key: 'themeID');
    return mo_with_lock("variable-write:{$theme_id}", static fn(): array|WP_Error => mo_create_variable_locked(
        $theme_id,
        $params['collection_id'],
        $params['name'],
        $params['custom_property'],
        mo_variable_type_to_mosaic($params['type']),
    ));
}

/**
 * Validate every required create-variable input. Extracted so
 * {@see mosaic_create_variable()} stays under the cyclomatic-complexity
 * threshold.
 *
 * @param  array<string, mixed> $input
 * @return array{collection_id:string, name:string, custom_property:string, type:string}|WP_Error
 */
function mo_extract_create_variable_params(array $input): array|WP_Error
{
    $collection_id = mo_norm_uuid($input['collection_id'] ?? null);
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    $custom_property = is_string($input['custom_property'] ?? null) ? trim($input['custom_property']) : '';
    $type = is_string($input['type'] ?? null) ? $input['type'] : '';
    if ($collection_id === '' || $name === '' || $custom_property === '' || $type === '') {
        return mo_error('mo_invalid_input', __(
            'collection_id, name, custom_property, and type are required.',
            domain: 'novamira-pro',
        ));
    }
    if (!in_array(
        $type,
        ['color', 'length', 'n-length', 'font', 'background', 'box-shadow', 'text-shadow'],
        strict: true,
    )) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: rejected variable type. */
            __(
                'type %s is not supported. Use color / length / n-length / font / background / box-shadow / text-shadow.',
                domain: 'novamira-pro',
            ),
            $type,
        ));
    }
    $len_err = mo_validate_lengths(['name' => ['value' => $name, 'max' => 191]]);
    if ($len_err !== null) {
        return $len_err;
    }
    if (!preg_match('/^--[A-Za-z0-9_-]+$/', $custom_property)) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: rejected custom property name. */
            __(
                'custom_property %s must start with `--` and contain only letters / digits / dashes / underscores.',
                domain: 'novamira-pro',
            ),
            $custom_property,
        ));
    }
    return [
        'collection_id' => $collection_id,
        'name' => $name,
        'custom_property' => $custom_property,
        'type' => $type,
    ];
}

/**
 * @return array<string, mixed>|WP_Error
 */
function mo_create_variable_locked(
    string $theme_id,
    string $collection_id,
    string $name,
    string $custom_property,
    string $mosaic_type,
): array|WP_Error {
    $collection = mo_load('collections', $collection_id);
    if ($collection === null || ($collection['status'] ?? '') !== 'publish') {
        return mo_concurrent_modification_error('collection', $collection_id);
    }
    $id = mo_facade_create_variable($theme_id, $collection_id, $name, $custom_property, $mosaic_type);
    if ($id instanceof WP_Error) {
        return $id;
    }
    $row = mo_load('collection_variables', $id);
    if ($row === null) {
        return mo_error('mo_facade_failed', __('Variable row not visible after facade write.', domain: 'novamira-pro'));
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-create-variable',
        operation: 'create',
        entity: 'variable',
        entity_id: $id,
        context: ['theme_id' => $theme_id, 'collection_id' => $collection_id],
    );
    return ['variable' => mo_present_variable_full($row)];
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_variable(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $patch = mo_build_variable_edit_patch($input);
    if ($patch instanceof WP_Error) {
        return $patch;
    }
    if ($patch === []) {
        return mo_error('mo_invalid_input', __(
            'At least one of name / custom_property must be supplied.',
            domain: 'novamira-pro',
        ));
    }
    $row = mo_load('collection_variables', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: variable UUID. */
            __('Mosaic variable %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $theme_id = mo_str($row, key: 'themeID');
    return mo_with_lock("variable-write:{$theme_id}", static fn(): array|WP_Error => mo_apply_variable_edit_locked(
        $id,
        $patch,
    ));
}

/**
 * @param  array<string, string> $patch
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_variable_edit_locked(string $id, array $patch): array|WP_Error
{
    $row = mo_load('collection_variables', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_concurrent_modification_error('variable', $id);
    }
    // Through Mosaic's variable manager rather than a raw UPDATE: on the
    // current Mosaic the manager canonicalizes the pinned custom property
    // (ValidatorCollectionVariableCustomProperty), dedupes it against every
    // other variable (CollectionVariablesMResourceManager::
    // incrementCollidingCustomPropertyNames()) and re-projects the row's
    // `emittedName` claim. A raw UPDATE left that UNIQUE(themeID, emittedName)
    // claim stale, so a later Mosaic editor save could collide with it.
    $written = mo_facade_editor_write(mo_str($row, key: 'themeID'), static function (object $editor) use (
        $id,
        $patch,
    ): bool|WP_Error {
        $resource = mo_editor_resource(mo_editor_manager($editor, type: 'collectionVariable'), $id);
        if ($resource === null) {
            return mo_concurrent_modification_error('variable', $id);
        }
        $fields = ['name' => 'name', 'custom_property' => 'customProperty'];
        foreach ($fields as $input_key => $property) {
            if (!isset($patch[$input_key])) {
                continue;
            }
            $err = mo_store_resource_value($resource, $property, $patch[$input_key]);
            if ($err !== null) {
                return $err;
            }
        }
        return true;
    });
    if ($written instanceof WP_Error) {
        return $written;
    }
    $fresh = mo_load('collection_variables', $id);
    if ($fresh === null) {
        return mo_concurrent_modification_error('variable', $id);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-edit-variable',
        operation: 'edit',
        entity: 'variable',
        entity_id: $id,
        context: ['theme_id' => mo_str($fresh, key: 'themeID')],
    );
    return ['variable' => mo_present_variable_full($fresh)];
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, string>|WP_Error
 */
function mo_build_variable_edit_patch(array $input): array|WP_Error
{
    $patch = [];
    if (array_key_exists('name', $input)) {
        if (!is_string($input['name'])) {
            return mo_error('mo_invalid_input', __('name must be a string.', domain: 'novamira-pro'));
        }
        $name = trim($input['name']);
        if ($name === '') {
            return mo_error('mo_invalid_input', __('name cannot be empty.', domain: 'novamira-pro'));
        }
        $len_err = mo_validate_lengths(['name' => ['value' => $name, 'max' => 191]]);
        if ($len_err !== null) {
            return $len_err;
        }
        $patch['name'] = $name;
    }
    if (array_key_exists('custom_property', $input)) {
        if (!is_string($input['custom_property'])) {
            return mo_error('mo_invalid_input', __('custom_property must be a string.', domain: 'novamira-pro'));
        }
        $custom_property = trim($input['custom_property']);
        if (!preg_match('/^--[A-Za-z0-9_-]+$/', $custom_property)) {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: %s: rejected custom property name. */
                __(
                    'custom_property %s must start with `--` and contain only letters / digits / dashes / underscores.',
                    domain: 'novamira-pro',
                ),
                $custom_property,
            ));
        }
        $patch['custom_property'] = $custom_property;
    }
    return $patch;
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_delete_variable(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('collection_variables', $id);
    if ($row === null) {
        return ['id' => $id, 'deleted' => false];
    }
    if (($row['status'] ?? '') !== 'publish') {
        return ['id' => $id, 'deleted' => true];
    }
    $theme_id = mo_str($row, key: 'themeID');
    return mo_with_lock("variable-write:{$theme_id}", static fn(): array|WP_Error => mo_delete_variable_locked($id));
}

/**
 * @return array<string, mixed>|WP_Error
 */
function mo_delete_variable_locked(string $id): array|WP_Error
{
    $row = mo_load('collection_variables', $id);
    if ($row === null) {
        return ['id' => $id, 'deleted' => false];
    }
    if (($row['status'] ?? '') !== 'publish') {
        return ['id' => $id, 'deleted' => true];
    }
    // Mosaic's own delete path (the one its editor commit takes): delete()
    // flips `currentStatus`, the variable detacher is told about it
    // (CollectionVariableMResourceAbstract::doCommit()), pushToDB() removes
    // the row and releases its emittedName claim, and the post-commit detach
    // pass rewrites the element / class styles that referenced it.
    $written = mo_facade_editor_delete(mo_str($row, key: 'themeID'), static function (object $editor) use (
        $id,
    ): bool|WP_Error {
        $resource = mo_editor_resource(mo_editor_manager($editor, type: 'collectionVariable'), $id);
        if ($resource === null) {
            return mo_concurrent_modification_error('variable', $id);
        }
        mo_call([$resource, 'delete']);
        $detacher = mo_editor_detacher($editor, type: 'collectionVariableDetacher');
        if ($detacher !== null) {
            mo_call([$detacher, 'onMResourceDeleted'], $resource);
        }
        return true;
    });
    if ($written instanceof WP_Error) {
        return $written;
    }
    $after = mo_load('collection_variables', $id);
    if ($after !== null && ($after['status'] ?? '') === 'publish') {
        return mo_error('mo_write_failed', __('Mosaic did not delete the variable.', domain: 'novamira-pro'));
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-delete-variable',
        operation: 'delete',
        entity: 'variable',
        entity_id: $id,
        context: ['theme_id' => mo_str($row, key: 'themeID')],
    );
    return ['id' => $id, 'deleted' => true];
}

/**
 * @param  array<string, mixed> $row
 * @return array<string, mixed>
 */
function mo_present_variable_full(array $row): array
{
    $data = mo_decode_data($row['data'] ?? null);
    return [
        'id' => mo_str($row, key: 'ID'),
        'theme_id' => mo_str($row, key: 'themeID'),
        'collection_id' => mo_str($row, key: 'parentID'),
        'parent_type' => mo_str($row, key: 'parentType'),
        'ordering' => mo_str($row, key: 'ordering'),
        'status' => mo_str($row, key: 'status'),
        'revision' => mo_str($row, key: 'revision'),
        'name' => is_string($data['name'] ?? null) ? $data['name'] : '',
        'custom_property' => is_string($data['customProperty'] ?? null) ? $data['customProperty'] : '',
        'css_variable' => mo_variable_css_name($row, $data),
        'type' => is_string($data['type'] ?? null) ? $data['type'] : '',
        'data' => $data,
    ];
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_set_variable_value(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $params = mo_extract_set_variable_value_params($input);
    if ($params instanceof WP_Error) {
        return $params;
    }
    $row = mo_load('collection_variables', $params['id']);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: variable UUID. */
            __('Mosaic variable %s not found.', domain: 'novamira-pro'),
            $params['id'],
        ));
    }
    $data = mo_decode_data($row['data'] ?? null);
    $mosaic_type = is_string($data['type'] ?? null) ? $data['type'] : '';
    $value_error = mo_validate_variable_value_for_type($mosaic_type, $params['value']);
    if ($value_error !== null) {
        return mo_error('mo_invalid_input', $value_error);
    }
    if (mo_str($row, key: 'parentType') !== 'collection') {
        return mo_error('mo_invalid_input', __(
            'This variable is not parented by a collection — mosaic-set-variable-value only supports collection variables today.',
            domain: 'novamira-pro',
        ));
    }
    $collection_id = mo_str($row, key: 'parentID');
    $skin_id = mo_resolve_collection_child_id('collection_skins', $collection_id, $params['skin'], label: 'skin');
    if ($skin_id instanceof WP_Error) {
        return $skin_id;
    }
    $mode_id = mo_resolve_collection_child_id('collection_modes', $collection_id, $params['mode'], label: 'mode');
    if ($mode_id instanceof WP_Error) {
        return $mode_id;
    }
    $theme_id = mo_str($row, key: 'themeID');
    return mo_with_lock("variable-write:{$theme_id}", static fn(): array|WP_Error => mo_apply_variable_value_locked(
        $params['id'],
        $skin_id,
        $mode_id,
        $params['value'],
    ));
}

/**
 * Validate required set-variable-value inputs. Extracted so {@see
 * mosaic_set_variable_value()} stays under the cyclomatic-complexity
 * threshold, mirroring {@see mo_extract_create_variable_params()}.
 *
 * @param  array<string, mixed> $input
 * @return array{id:string, value:string, skin:string, mode:string}|WP_Error
 */
function mo_extract_set_variable_value_params(array $input): array|WP_Error
{
    $id = mo_norm_uuid($input['id'] ?? null);
    $value = is_string($input['value'] ?? null) ? trim($input['value']) : '';
    if ($id === '' || $value === '') {
        return mo_error('mo_invalid_input', __('id and value are required.', domain: 'novamira-pro'));
    }
    $len_err = mo_validate_lengths(['value' => ['value' => $value, 'max' => 2000]]);
    if ($len_err !== null) {
        return $len_err;
    }
    return [
        'id' => $id,
        'value' => $value,
        'skin' => is_string($input['skin'] ?? null) ? trim($input['skin']) : '',
        'mode' => is_string($input['mode'] ?? null) ? trim($input['mode']) : '',
    ];
}

/**
 * Per-type syntactic check for a variable value, dispatched on Mosaic's
 * internal `data.type` string (e.g. `lengthPercentage`, not the agent-
 * facing `length`). Mirrors Elementor's `var_validate_value_for_type()`
 * dispatch shape. `font` / layered types (`fontFamily`, `imageGradient`,
 * `boxShadow`, `textShadow`) hold a structured object value this ability
 * doesn't write — see includes/skills/mosaic-build-page/SKILL.md.
 */
function mo_validate_variable_value_for_type(string $mosaic_type, string $value): ?string
{
    return match ($mosaic_type) {
        'color' => mo_validate_variable_color_value($value),
        'lengthPercentage', 'numberLengthPercentage' => mo_validate_variable_length_value($mosaic_type, $value),
        default => sprintf(
            /* translators: %s: agent-facing variable type (font / background / box-shadow / text-shadow). */
            __(
                'Variable type "%s" has a structured value shape (fonts, gradients, shadows) that mosaic-set-variable-value does not support yet. Open the variable in Mosaic\'s editor to set it.',
                domain: 'novamira-pro',
            ),
            mo_variable_type_from_mosaic($mosaic_type),
        ),
    };
}

/**
 * Find the `)` that closes the `(` at `$open_pos`, accounting for nested
 * parens. Shared by the color-function and length/calc-function grammar
 * validators below. Returns null when the string ends before the parens
 * balance back to zero (truncated / malformed input).
 */
function mo_find_matching_paren(string $s, int $open_pos): ?int
{
    $depth = 0;
    $len = strlen($s);
    for ($i = $open_pos; $i < $len; $i++) {
        if ($s[$i] === '(') {
            $depth++;
            continue;
        }
        if ($s[$i] !== ')') {
            continue;
        }
        $depth--;
        if ($depth === 0) {
            return $i;
        }
    }
    return null;
}

/**
 * Split a function-call argument string on top-level commas, respecting
 * nested parens (so a nested `rgb(...)` or `var(...)` argument doesn't
 * get sliced in half). Used wherever a function takes a comma-separated
 * argument list: `color-mix()`, `min()`/`max()`/`clamp()`, `var()`'s
 * name + fallback.
 *
 * @return list<string>
 */
function mo_split_top_level_commas(string $args): array
{
    $parts = [];
    $depth = 0;
    $current = '';
    $len = strlen($args);
    for ($i = 0; $i < $len; $i++) {
        $char = $args[$i];
        if ($char === '(') {
            $depth++;
        }
        if ($char === ')') {
            $depth--;
        }
        if ($char === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $char;
    }
    $parts[] = trim($current);
    return $parts;
}

/**
 * Split a modern CSS Color/Values-4 "space syntax" argument list into
 * its whitespace-separated components and an optional trailing `/ alpha`
 * (e.g. `"0 0 0 / 50%"` -> `[['0', '0', '0'], '50%']`). Used by
 * `rgb()`/`hsl()`/`oklab()`/`oklch()`/`color()`, all of which share this
 * shape.
 *
 * @return array{0: list<string>, 1: ?string}
 */
function mo_split_slash_alpha(string $args): array
{
    $main = $args;
    $alpha = null;
    if (str_contains($args, '/')) {
        $pieces = explode('/', $args, limit: 2);
        $main = trim($pieces[0]);
        $alpha = trim($pieces[1]);
    }
    $split = preg_split('/\s+/', trim($main));
    $components = array_values(array_filter($split !== false ? $split : [], static fn(string $s): bool => $s !== ''));
    return [$components, $alpha];
}

/**
 * Bare CSS `<number>` token: optional sign, integer/decimal/leading-dot
 * mantissa, optional scientific-notation exponent (`1e3`, `2.5e-2`).
 */
function mo_is_css_number(string $value): bool
{
    return preg_match('/^-?(?:\d+\.\d+|\.\d+|\d+)(?:[eE][+-]?\d+)?$/', trim($value)) === 1;
}

/**
 * Bare CSS `<percentage>` token: a {@see mo_is_css_number()} mantissa
 * immediately followed by `%`.
 */
function mo_is_css_percentage(string $value): bool
{
    $value = trim($value);
    if (!str_ends_with($value, '%')) {
        return false;
    }
    return mo_is_css_number(substr($value, offset: 0, length: -1));
}

/**
 * Numeric value of a `<number>` or `<percentage>` token (the `%` sign is
 * stripped, NOT divided by 100 — callers compare against 0-100 bounds
 * for percentages and 0-1 / 0-255 bounds for plain numbers). Every call
 * site first confirms the token matches {@see mo_is_css_number()} /
 * {@see mo_is_css_percentage()}, so the cast below is always on an
 * already-validated numeric string — mago can't see that precondition
 * across the function boundary, hence the suppression.
 */
function mo_numeric_value(string $value): float
{
    $value = trim($value);
    // @mago-expect analysis:invalid-type-cast
    return (float) (str_ends_with($value, '%') ? substr($value, offset: 0, length: -1) : $value);
}

/**
 * Standard CSS Color Level 4 named colors Mosaic's own variable-value
 * validator accepts (`Mosaic\Helpers\Color\ColorMap::getColorMap()`),
 * plus the two CSS-wide keyword colors it layers on top
 * (`Mosaic\Helpers\Color\ColorKeywords::getColorKeywords()`). Kept as a
 * literal list rather than introspected from Mosaic at runtime — this
 * ability writes `collection_variables.data` directly via `$wpdb` and
 * must validate correctly even under static analysis, where Mosaic's
 * classes are never loaded (see the `mo_call()` / `mo_new()` analyzer-
 * blinding wrappers elsewhere in this specialization). A top-level
 * `const` (not a function body) so mago's per-function halstead-volume
 * check doesn't score the ~150-entry literal against a function.
 */
const MO_VARIABLE_COLOR_NAMED_VALUES = [
    'aliceblue',
    'antiquewhite',
    'aqua',
    'aquamarine',
    'azure',
    'beige',
    'bisque',
    'black',
    'blanchedalmond',
    'blue',
    'blueviolet',
    'brown',
    'burlywood',
    'cadetblue',
    'chartreuse',
    'chocolate',
    'coral',
    'cornflowerblue',
    'cornsilk',
    'crimson',
    'cyan',
    'darkblue',
    'darkcyan',
    'darkgoldenrod',
    'darkgray',
    'darkgrey',
    'darkgreen',
    'darkkhaki',
    'darkmagenta',
    'darkolivegreen',
    'darkorange',
    'darkorchid',
    'darkred',
    'darksalmon',
    'darkseagreen',
    'darkslateblue',
    'darkslategray',
    'darkslategrey',
    'darkturquoise',
    'darkviolet',
    'deeppink',
    'deepskyblue',
    'dimgray',
    'dimgrey',
    'dodgerblue',
    'firebrick',
    'floralwhite',
    'forestgreen',
    'fuchsia',
    'gainsboro',
    'ghostwhite',
    'gold',
    'goldenrod',
    'gray',
    'grey',
    'green',
    'greenyellow',
    'honeydew',
    'hotpink',
    'indianred',
    'indigo',
    'ivory',
    'khaki',
    'lavender',
    'lavenderblush',
    'lawngreen',
    'lemonchiffon',
    'lightblue',
    'lightcoral',
    'lightcyan',
    'lightgoldenrodyellow',
    'lightgray',
    'lightgrey',
    'lightgreen',
    'lightpink',
    'lightsalmon',
    'lightseagreen',
    'lightskyblue',
    'lightslategray',
    'lightslategrey',
    'lightsteelblue',
    'lightyellow',
    'lime',
    'limegreen',
    'linen',
    'magenta',
    'maroon',
    'mediumaquamarine',
    'mediumblue',
    'mediumorchid',
    'mediumpurple',
    'mediumseagreen',
    'mediumslateblue',
    'mediumspringgreen',
    'mediumturquoise',
    'mediumvioletred',
    'midnightblue',
    'mintcream',
    'mistyrose',
    'moccasin',
    'navajowhite',
    'navy',
    'oldlace',
    'olive',
    'olivedrab',
    'orange',
    'orangered',
    'orchid',
    'palegoldenrod',
    'palegreen',
    'paleturquoise',
    'palevioletred',
    'papayawhip',
    'peachpuff',
    'peru',
    'pink',
    'plum',
    'powderblue',
    'purple',
    'rebeccapurple',
    'red',
    'rosybrown',
    'royalblue',
    'saddlebrown',
    'salmon',
    'sandybrown',
    'seagreen',
    'seashell',
    'sienna',
    'silver',
    'skyblue',
    'slateblue',
    'slategray',
    'slategrey',
    'snow',
    'springgreen',
    'steelblue',
    'tan',
    'teal',
    'thistle',
    'tomato',
    'turquoise',
    'violet',
    'wheat',
    'white',
    'whitesmoke',
    'yellow',
    'yellowgreen',
    'transparent',
    'currentcolor',
];

/**
 * @return list<string>
 */
function mo_variable_color_named_values(): array
{
    return MO_VARIABLE_COLOR_NAMED_VALUES;
}

/**
 * CSS color FUNCTION names Mosaic's variable-value validator recognises
 * (`Mosaic\Validators\Helper\CSSValue\TokenValidator\CSSTokenValidatorColor`).
 * Each function's ARGUMENTS are validated against real CSS Color Level 4
 * component grammar by {@see mo_validate_color_function_value()} — not
 * just the function name and balanced parens — so garbage like
 * `rgb(banana)` or a semicolon-breakout attempt is rejected before it
 * ever reaches persistence.
 *
 * @return list<string>
 */
function mo_variable_color_functions(): array
{
    return ['rgb', 'rgba', 'hsl', 'hsla', 'oklab', 'oklch', 'color', 'color-mix', 'var'];
}

/**
 * Validate a design-token color value. Accepts a hex string (3/4/6/8
 * hex digits — 4 and 8 include an alpha channel), a CSS Level-4 named
 * color, or one of {@see mo_variable_color_functions()} with its
 * arguments validated per the real CSS grammar for that function (see
 * {@see mo_validate_color_function_value()}). A bare `;` anywhere in the
 * value is rejected outright — a custom-property value is never allowed
 * to contain a declaration separator, so this closes off any
 * semicolon-breakout attempt regardless of which branch below would
 * otherwise have accepted the rest of the string.
 */
function mo_validate_variable_color_value(string $value): ?string
{
    if (str_contains($value, ';')) {
        return mo_css_generic_color_error($value);
    }
    if (preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) === 1) {
        return null;
    }
    if (in_array(strtolower($value), mo_variable_color_named_values(), strict: true)) {
        return null;
    }
    if (str_contains($value, '(')) {
        return mo_validate_color_function_value($value);
    }
    return mo_css_generic_color_error($value);
}

function mo_css_generic_color_error(string $value): string
{
    return sprintf(
        /* translators: %s: rejected color value. */
        __(
            'Color value "%s" is not recognised. Use a hex string (#f00 / #ff0000 / #ff0000cc), a CSS named color (e.g. "cornflowerblue"), or a color function (rgb()/rgba()/hsl()/hsla()/oklab()/oklch()/color()/color-mix()/var()).',
            domain: 'novamira-pro',
        ),
        $value,
    );
}

function mo_css_function_arg_error(string $function_label, string $args): string
{
    return sprintf(
        /* translators: 1: the function name (e.g. "rgb()/rgba()"), 2: the rejected argument string. */
        __('%1$s arguments "%2$s" are not valid CSS.', domain: 'novamira-pro'),
        $function_label,
        $args,
    );
}

/**
 * Dispatch a `<fn>(<args>)` color value to its per-function argument
 * grammar. Requires the ENTIRE value to be exactly one function call —
 * the closing paren found for the first `(` must be the last character
 * of the string — so trailing content after a balanced call (e.g.
 * `"rgb(0,0,0))evil"` or `"rgb(0,0,0)calc(1)"`) is rejected here even
 * though the paren *count* balances.
 */
function mo_validate_color_function_value(string $value): ?string
{
    $matches = [];
    if (preg_match('/^([a-zA-Z-]+)\((.*)\)$/s', $value, $matches) !== 1) {
        return mo_css_generic_color_error($value);
    }
    $name = strtolower($matches[1]);
    if (!in_array($name, mo_variable_color_functions(), strict: true)) {
        return mo_css_generic_color_error($value);
    }
    $open_pos = strpos($value, needle: '(');
    if ($open_pos === false) {
        return mo_css_generic_color_error($value);
    }
    $close_pos = mo_find_matching_paren($value, $open_pos);
    if ($close_pos === null || $close_pos !== (strlen($value) - 1)) {
        return mo_css_generic_color_error($value);
    }
    $args = substr($value, $open_pos + 1, $close_pos - $open_pos - 1);
    return match ($name) {
        'var' => mo_validate_var_reference($args),
        'rgb', 'rgba' => mo_validate_rgb_args($args),
        'hsl', 'hsla' => mo_validate_hsl_args($args),
        'oklab' => mo_validate_oklab_args($args),
        'oklch' => mo_validate_oklch_args($args),
        'color' => mo_validate_color_function_args($args),
        'color-mix' => mo_validate_color_mix_args($args),
        default => mo_css_generic_color_error($value),
    };
}

/**
 * Validate the content of a `var(...)` reference — used both as a
 * top-level color/length value and nested inside `calc()`-family
 * expressions. The first argument must be a valid CSS custom-property
 * identifier (`--name`); a fallback (anything after the first comma) is
 * accepted but scanned for characters that could break out of a CSS
 * declaration (`;{}<>` / backslash / quotes) — semicolons are already
 * rejected upstream for the top-level value, but `var()` can also appear
 * nested inside `calc()` where this is the only guard.
 */
function mo_validate_var_reference(string $args): ?string
{
    $parts = mo_split_top_level_commas($args);
    $name = trim($parts[0] ?? '');
    if (preg_match('/^--[a-zA-Z_][a-zA-Z0-9_-]*$/', $name) !== 1) {
        return sprintf(
            /* translators: %s: the rejected var() reference name. */
            __('`var()` must reference a custom property like "--brand-primary" (got "%s").', domain: 'novamira-pro'),
            $name,
        );
    }
    if (count($parts) > 1) {
        $fallback = implode(',', array_slice($parts, offset: 1));
        if (preg_match('/[;{}<>\\\\"\']/', $fallback) === 1) {
            return __('`var()` fallback contains disallowed characters.', domain: 'novamira-pro');
        }
    }
    return null;
}

/**
 * `rgb()` / `rgba()` component: an integer 0-255, a percentage 0-100,
 * or the CSS Color 4 `none` keyword.
 */
function mo_is_valid_rgb_component(string $value): bool
{
    $value = trim($value);
    if (strtolower($value) === 'none') {
        return true;
    }
    if (mo_is_css_percentage($value)) {
        $n = mo_numeric_value($value);
        return $n >= 0 && $n <= 100;
    }
    if (preg_match('/^\d+$/', $value) === 1) {
        $n = (int) $value;
        return $n >= 0 && $n <= 255;
    }
    return false;
}

/**
 * Alpha component shared by every color function: a number 0-1, a
 * percentage 0-100, or `none`.
 */
function mo_is_valid_alpha(string $value): bool
{
    $value = trim($value);
    if (strtolower($value) === 'none') {
        return true;
    }
    if (mo_is_css_percentage($value)) {
        $n = mo_numeric_value($value);
        return $n >= 0 && $n <= 100;
    }
    if (mo_is_css_number($value)) {
        $n = mo_numeric_value($value);
        return $n >= 0 && $n <= 1;
    }
    return false;
}

/**
 * `hsl()`/`hsla()` hue component: a bare number (degrees implied), a
 * number with an explicit angle unit (`deg`/`grad`/`rad`/`turn`), or
 * `none`. Mirrors Mosaic's `validatorHSLHue`.
 */
function mo_is_valid_hue(string $value): bool
{
    $value = trim($value);
    if (strtolower($value) === 'none') {
        return true;
    }
    return preg_match('/^-?(?:\d+\.\d+|\.\d+|\d+)(?:[eE][+-]?\d+)?(?:deg|grad|rad|turn)?$/i', $value) === 1;
}

/**
 * `hsl()`/`hsla()` saturation/lightness component: a percentage 0-100,
 * or `none`.
 */
function mo_is_valid_percentage_component(string $value): bool
{
    $value = trim($value);
    if (strtolower($value) === 'none') {
        return true;
    }
    if (!mo_is_css_percentage($value)) {
        return false;
    }
    $n = mo_numeric_value($value);
    return $n >= 0 && $n <= 100;
}

/**
 * `oklab()`/`oklch()` lightness component: a number 0-1, a percentage
 * 0-100, or `none`.
 */
function mo_is_valid_oklab_lightness(string $value): bool
{
    $value = trim($value);
    if (strtolower($value) === 'none') {
        return true;
    }
    if (mo_is_css_percentage($value)) {
        $n = mo_numeric_value($value);
        return $n >= 0 && $n <= 100;
    }
    if (mo_is_css_number($value)) {
        $n = mo_numeric_value($value);
        return $n >= 0 && $n <= 1;
    }
    return false;
}

/**
 * `oklab()` a/b axis component: a number -0.4..0.4, a percentage
 * -100%..100%, or `none`.
 */
function mo_is_valid_oklab_axis(string $value): bool
{
    $value = trim($value);
    if (strtolower($value) === 'none') {
        return true;
    }
    if (mo_is_css_percentage($value)) {
        $n = mo_numeric_value($value);
        return $n >= -100 && $n <= 100;
    }
    if (mo_is_css_number($value)) {
        $n = mo_numeric_value($value);
        return $n >= -0.4 && $n <= 0.4;
    }
    return false;
}

/**
 * `oklch()` chroma component: a non-negative number or percentage, or
 * `none`. Mosaic's own `validatorOklchChroma` has no upper bound.
 */
function mo_is_valid_oklch_chroma(string $value): bool
{
    $value = trim($value);
    if (strtolower($value) === 'none') {
        return true;
    }
    if (mo_is_css_percentage($value)) {
        return mo_numeric_value($value) >= 0;
    }
    if (mo_is_css_number($value)) {
        return mo_numeric_value($value) >= 0;
    }
    return false;
}

/**
 * `color()` component: a number 0-1, a percentage 0-100, or `none`.
 */
function mo_is_valid_color_space_component(string $value): bool
{
    $value = trim($value);
    if (strtolower($value) === 'none') {
        return true;
    }
    if (mo_is_css_percentage($value)) {
        $n = mo_numeric_value($value);
        return $n >= 0 && $n <= 100;
    }
    if (mo_is_css_number($value)) {
        $n = mo_numeric_value($value);
        return $n >= 0 && $n <= 1;
    }
    return false;
}

/**
 * Predicate color spaces accepted by `color()`.
 *
 * @return list<string>
 */
function mo_color_space_names(): array
{
    return ['srgb', 'srgb-linear', 'display-p3', 'a98-rgb', 'prophoto-rgb', 'rec2020', 'xyz', 'xyz-d50', 'xyz-d65'];
}

/**
 * Color spaces accepted by `color-mix()`'s `in <space>` clause — a
 * superset of {@see mo_color_space_names()} that also allows the
 * polar/cylindrical spaces (`hsl`, `hwb`, `lch`, `oklch`) and `lab`.
 *
 * @return list<string>
 */
function mo_color_mix_space_names(): array
{
    return [
        'srgb',
        'srgb-linear',
        'display-p3',
        'a98-rgb',
        'prophoto-rgb',
        'rec2020',
        'lab',
        'oklab',
        'xyz',
        'xyz-d50',
        'xyz-d65',
        'hsl',
        'hwb',
        'lch',
        'oklch',
    ];
}

function mo_validate_rgb_args(string $args): ?string
{
    $error = mo_css_function_arg_error('rgb()/rgba()', $args);
    $comma_parts = mo_split_top_level_commas($args);
    if (count($comma_parts) === 3 || count($comma_parts) === 4) {
        foreach (array_slice($comma_parts, offset: 0, length: 3) as $component) {
            if (!mo_is_valid_rgb_component($component)) {
                return $error;
            }
        }
        if (isset($comma_parts[3]) && !mo_is_valid_alpha($comma_parts[3])) {
            return $error;
        }
        return null;
    }
    [$components, $alpha] = mo_split_slash_alpha($args);
    if (count($components) !== 3) {
        return $error;
    }
    foreach ($components as $component) {
        if (!mo_is_valid_rgb_component($component)) {
            return $error;
        }
    }
    if ($alpha !== null && !mo_is_valid_alpha($alpha)) {
        return $error;
    }
    return null;
}

function mo_validate_hsl_args(string $args): ?string
{
    $error = mo_css_function_arg_error('hsl()/hsla()', $args);
    $comma_parts = mo_split_top_level_commas($args);
    if (count($comma_parts) === 3 || count($comma_parts) === 4) {
        if (
            !mo_is_valid_hue($comma_parts[0])
            || !mo_is_valid_percentage_component($comma_parts[1])
            || !mo_is_valid_percentage_component($comma_parts[2])
        ) {
            return $error;
        }
        if (isset($comma_parts[3]) && !mo_is_valid_alpha($comma_parts[3])) {
            return $error;
        }
        return null;
    }
    [$components, $alpha] = mo_split_slash_alpha($args);
    if (count($components) !== 3) {
        return $error;
    }
    if (
        !mo_is_valid_hue($components[0])
        || !mo_is_valid_percentage_component($components[1])
        || !mo_is_valid_percentage_component($components[2])
    ) {
        return $error;
    }
    if ($alpha !== null && !mo_is_valid_alpha($alpha)) {
        return $error;
    }
    return null;
}

function mo_validate_oklab_args(string $args): ?string
{
    $error = mo_css_function_arg_error('oklab()', $args);
    [$components, $alpha] = mo_split_slash_alpha($args);
    if (count($components) !== 3) {
        return $error;
    }
    if (
        !mo_is_valid_oklab_lightness($components[0])
        || !mo_is_valid_oklab_axis($components[1])
        || !mo_is_valid_oklab_axis($components[2])
    ) {
        return $error;
    }
    if ($alpha !== null && !mo_is_valid_alpha($alpha)) {
        return $error;
    }
    return null;
}

function mo_validate_oklch_args(string $args): ?string
{
    $error = mo_css_function_arg_error('oklch()', $args);
    [$components, $alpha] = mo_split_slash_alpha($args);
    if (count($components) !== 3) {
        return $error;
    }
    if (
        !mo_is_valid_oklab_lightness($components[0])
        || !mo_is_valid_oklch_chroma($components[1])
        || !mo_is_valid_hue($components[2])
    ) {
        return $error;
    }
    if ($alpha !== null && !mo_is_valid_alpha($alpha)) {
        return $error;
    }
    return null;
}

function mo_validate_color_function_args(string $args): ?string
{
    $error = mo_css_function_arg_error('color()', $args);
    [$components, $alpha] = mo_split_slash_alpha($args);
    if (count($components) !== 4) {
        return $error;
    }
    $space = strtolower(array_shift($components));
    if (!in_array($space, mo_color_space_names(), strict: true)) {
        return $error;
    }
    foreach ($components as $component) {
        if (!mo_is_valid_color_space_component($component)) {
            return $error;
        }
    }
    if ($alpha !== null && !mo_is_valid_alpha($alpha)) {
        return $error;
    }
    return null;
}

function mo_validate_color_mix_args(string $args): ?string
{
    $error = mo_css_function_arg_error('color-mix()', $args);
    $parts = mo_split_top_level_commas($args);
    if (count($parts) !== 3) {
        return $error;
    }
    $space_tokens = preg_split('/\s+/', trim($parts[0]));
    $space_tokens = $space_tokens !== false ? $space_tokens : [];
    if (
        count($space_tokens) !== 2
        || strtolower($space_tokens[0]) !== 'in'
        || !in_array(strtolower($space_tokens[1]), mo_color_mix_space_names(), strict: true)
    ) {
        return $error;
    }
    if (!mo_is_valid_color_mix_component($parts[1]) || !mo_is_valid_color_mix_component($parts[2])) {
        return $error;
    }
    return null;
}

/**
 * A `color-mix()` color argument: an optional trailing percentage —
 * bounds-checked to `[0%, 100%]` per CSS Color Level 5 §color-mix (a
 * color-interpolation-method weight outside that range is invalid) —
 * then a full color value validated recursively via {@see
 * mo_validate_variable_color_value()} — so a nested `rgb(...)` /
 * `var(...)` / hex / named color inside `color-mix()` gets the exact
 * same grammar rigor as a top-level value.
 */
function mo_is_valid_color_mix_component(string $component): bool
{
    $tokens = preg_split('/\s+/', trim($component));
    $tokens = $tokens !== false ? $tokens : [];
    if ($tokens === []) {
        return false;
    }
    $last = end($tokens);
    if (mo_is_css_percentage($last)) {
        $weight = mo_numeric_value($last);
        if ($weight < 0 || $weight > 100) {
            return false;
        }
        array_pop($tokens);
    }
    if ($tokens === []) {
        return false;
    }
    return mo_validate_variable_color_value(implode(' ', $tokens)) === null;
}

/**
 * CSS length/percentage unit suffixes Mosaic's `length` / `n-length`
 * variable-value validator accepts (`Mosaic\Validators\Validate\Style\
 * CSSDataTypes::CSSDataTypeLengthPercentage`) — the full CSS Values &
 * Units Level 4 set: relative (logical `rlh`/`ric`/…, viewport
 * `svh`/`lvh`/`dvh`/…, container-query `cqw`/`cqmin`/…) AND absolute
 * (`cm`, `mm`, `q`, `in`, `pt`, `pc`, `px`).
 *
 * @return list<string>
 */
function mo_variable_length_units(): array
{
    return [
        'px',
        'em',
        'cap',
        'ch',
        'ex',
        'ic',
        'lh',
        'rem',
        'rcap',
        'rch',
        'rex',
        'ric',
        'rlh',
        'vh',
        'svh',
        'lvh',
        'dvh',
        'vw',
        'svw',
        'lvw',
        'dvw',
        'vmax',
        'svmax',
        'lvmax',
        'dvmax',
        'vmin',
        'svmin',
        'lvmin',
        'dvmin',
        'vb',
        'svb',
        'lvb',
        'dvb',
        'vi',
        'svi',
        'lvi',
        'dvi',
        'cqw',
        'cqh',
        'cqi',
        'cqb',
        'cqmin',
        'cqmax',
        'cm',
        'mm',
        'q',
        'in',
        'pt',
        'pc',
        '%',
    ];
}

/**
 * `length` / `n-length` CSS function names accepted alongside a literal
 * dimension (`Mosaic\Validators\Validate\Style\CSSDataTypes::
 * CSSDataTypeLengthFunctions`). Each is validated against its real
 * arithmetic-expression grammar by {@see mo_calc_is_valid_function_call()}
 * — not just a balanced-parens check — so `calc(garbage)` is rejected.
 *
 * @return list<string>
 */
function mo_variable_length_functions(): array
{
    return ['var', 'calc', 'min', 'max', 'clamp'];
}

/**
 * `$mosaic_type` distinguishes `lengthPercentage` (Mosaic's `length` — a
 * unit is required) from `numberLengthPercentage` (`n-length` — a bare
 * unitless number is also valid, per `CSSDataTypes::CSSDataTypeNumber`).
 * Takes the type string (not a boolean flag) so the two shapes read as
 * distinct call sites at {@see mo_validate_variable_value_for_type()}.
 * A bare `;` anywhere is rejected outright, closing off semicolon
 * breakout the same way {@see mo_validate_variable_color_value()} does.
 */
function mo_validate_variable_length_value(string $mosaic_type, string $value): ?string
{
    if (str_contains($value, ';')) {
        return mo_css_length_error($value);
    }
    if (str_contains($value, '(')) {
        return mo_validate_length_function_value($mosaic_type, $value);
    }
    $matches = [];
    if (preg_match('/^-?(?:\d+\.\d+|\.\d+|\d+)(?:[eE][+-]?\d+)?([a-zA-Z%]*)$/', $value, $matches) !== 1) {
        return mo_css_length_error($value);
    }
    $unit = strtolower($matches[1]);
    if ($unit === '') {
        if ($mosaic_type === 'numberLengthPercentage') {
            return null;
        }
        return sprintf(
            /* translators: 1: rejected value, 2: comma-separated list of valid units. */
            __('Length value "%1$s" is missing a unit. Use one of: %2$s.', domain: 'novamira-pro'),
            $value,
            implode(', ', mo_variable_length_units()),
        );
    }
    if (!in_array($unit, mo_variable_length_units(), strict: true)) {
        return sprintf(
            /* translators: 1: rejected unit, 2: comma-separated list of valid units. */
            __('Length unit "%1$s" is not valid. Use one of: %2$s.', domain: 'novamira-pro'),
            $unit,
            implode(', ', mo_variable_length_units()),
        );
    }
    return null;
}

function mo_css_length_error(string $value): string
{
    return sprintf(
        /* translators: %s: rejected length value. */
        __(
            'Length value "%s" is not a recognised CSS dimension. Use a number + unit (e.g. "16px", "1.5rem", "50%%") or a function like calc()/clamp()/min()/max()/var().',
            domain: 'novamira-pro',
        ),
        $value,
    );
}

/**
 * Dispatch a `<fn>(<args>)` length value to the calc-family grammar
 * validator. Same "entire value must be exactly one balanced call"
 * requirement as {@see mo_validate_color_function_value()} — a closing
 * paren that doesn't land on the last character of the string (trailing
 * garbage after an otherwise-balanced call) is rejected. The resolved
 * dimensional type of the whole expression (see {@see
 * mo_calc_is_valid_sum()}) must also be one this variable's `$mosaic_type`
 * actually accepts — `calc(1)` (a bare `<number>`) is not a `<length>`,
 * so it's rejected for `lengthPercentage` even though `1` alone would be
 * a syntactically valid calc-value.
 */
function mo_validate_length_function_value(string $mosaic_type, string $value): ?string
{
    $matches = [];
    if (preg_match('/^([a-zA-Z-]+)\((.*)\)$/s', $value, $matches) !== 1) {
        return mo_css_length_error($value);
    }
    $name = strtolower($matches[1]);
    if (!in_array($name, mo_variable_length_functions(), strict: true)) {
        return mo_css_length_error($value);
    }
    $open_pos = strpos($value, needle: '(');
    if ($open_pos === false) {
        return mo_css_length_error($value);
    }
    $close_pos = mo_find_matching_paren($value, $open_pos);
    if ($close_pos === null || $close_pos !== (strlen($value) - 1)) {
        return mo_css_length_error($value);
    }
    $args = substr($value, $open_pos + 1, $close_pos - $open_pos - 1);
    $result_type = mo_calc_is_valid_function_call($name, $args);
    if ($result_type === null || !in_array($result_type, mo_calc_accepted_result_types($mosaic_type), strict: true)) {
        return mo_css_length_error($value);
    }
    return null;
}

/**
 * `var`/`calc`/`min`/`max`/`clamp` — the CSS function names a
 * `calc()`-family arithmetic expression may nest.
 */
const MO_CSS_CALC_FUNCTIONS = ['var', 'calc', 'min', 'max', 'clamp'];

/**
 * Dimensional "types" the calc grammar below tracks — a simplified
 * version of CSS Values & Units L4's calc type-checking: `number`,
 * `length`, `percentage`, `length-percentage` (the combined type a
 * length+percentage addition resolves to), and `unknown` (an unresolved
 * `var()` reference — it can't be checked further, so it's treated as
 * compatible with anything; CSS itself defers `var()` type-checking to
 * computed-value time).
 */

/**
 * Which calc() result types are acceptable as the FINAL value for a
 * variable of `$mosaic_type` — only checked at the top level (see {@see
 * mo_validate_length_function_value()}); nested sub-expressions
 * type-check against each other via {@see mo_calc_additive_result_type()}
 * etc. regardless of the outer variable's type. `numberLengthPercentage`
 * (`n-length`) additionally accepts a bare `number` result, matching the
 * literal-value rule in {@see mo_validate_variable_length_value()}.
 *
 * @return list<string>
 */
function mo_calc_accepted_result_types(string $mosaic_type): array
{
    $types = ['length', 'percentage', 'length-percentage', 'unknown'];
    if ($mosaic_type === 'numberLengthPercentage') {
        $types[] = 'number';
    }
    return $types;
}

/**
 * Result type of `<a> + <b>` / `<a> - <b>` inside calc(). `length` and
 * `percentage` are mutually compatible (CSS's combined
 * `<length-percentage>` type resolved at used-value time); a plain
 * `number` is NOT compatible with either — `calc(1px + 1)` is invalid
 * CSS. `unknown` (an unresolved `var()`) is compatible with anything.
 * Returns null when the two types cannot be added/subtracted.
 */
function mo_calc_additive_result_type(string $a, string $b): ?string
{
    if ($a === 'unknown') {
        return $b;
    }
    if ($b === 'unknown') {
        return $a;
    }
    if ($a === $b) {
        return $a;
    }
    $length_like = ['length', 'percentage', 'length-percentage'];
    if (in_array($a, $length_like, strict: true) && in_array($b, $length_like, strict: true)) {
        return 'length-percentage';
    }
    return null;
}

/**
 * Result type of `<a> * <b>` inside calc(): at least one side must be a
 * plain `number` (or `unknown`) — `calc(1px * 2px)` (length × length)
 * is invalid CSS. The result carries the OTHER side's type.
 */
function mo_calc_multiplicative_result_type(string $a, string $b): ?string
{
    if ($a === 'number') {
        return $b;
    }
    if ($b === 'number') {
        return $a;
    }
    if ($a === 'unknown' || $b === 'unknown') {
        return 'unknown';
    }
    return null;
}

/**
 * Result type of `<a> / <b>` inside calc(): the divisor must be a plain
 * `number` (or `unknown`) — `calc(1px / 1px)` is invalid CSS (would
 * yield a dimensionless number here, which this simplified grammar
 * doesn't track as a distinct "number" outcome). The result carries the
 * dividend's type.
 */
function mo_calc_division_result_type(string $a, string $b): ?string
{
    return $b === 'number' || $b === 'unknown' ? $a : null;
}

/**
 * Validate a full `calc()`-family arithmetic expression against its real
 * grammar AND its dimensional type-checking rules (recursive-descent,
 * not a balanced-parens heuristic):
 *
 *   calc-sum     := calc-product (('+' | '-') calc-product)*
 *   calc-product := calc-value (('*' | '/') calc-value)*
 *   calc-value   := <number><unit>? | '(' calc-sum ')' | <function>'(' args ')'
 *
 * where `<function>` is one of {@see MO_CSS_CALC_FUNCTIONS}. Requires
 * the ENTIRE (trimmed) `$expr` to parse as one calc-sum with nothing
 * left over — so `calc(garbage)`, `1px + `, or a stray semicolon are all
 * rejected rather than accepted on a partial/prefix match. Returns the
 * resolved dimensional type (`number`/`length`/`percentage`/
 * `length-percentage`/`unknown`) on success, or null.
 */
function mo_calc_is_valid_sum(string $expr): ?string
{
    $expr = trim($expr);
    if ($expr === '' || str_contains($expr, ';')) {
        return null;
    }
    $pos = 0;
    $type = mo_calc_parse_sum($expr, $pos);
    if ($type === null) {
        return null;
    }
    mo_calc_skip_whitespace($expr, $pos);
    return $pos === strlen($expr) ? $type : null;
}

/**
 * CSS Syntax Level 3 whitespace: space, tab, newline, carriage return, or
 * form feed. `mo_calc_skip_whitespace()` and the binary-operator
 * whitespace checks in {@see mo_calc_parse_sum()} both use this — a
 * single literal space char is not enough, e.g. `calc(1px\t+\t2px)`
 * (tab-separated) is valid CSS.
 */
function mo_calc_is_whitespace_char(string $char): bool
{
    return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r" || $char === "\f";
}

function mo_calc_skip_whitespace(string $s, int &$pos): void
{
    while ($pos < strlen($s) && mo_calc_is_whitespace_char($s[$pos])) {
        $pos++;
    }
}

/**
 * Look for a whitespace-bounded binary `+`/`-` starting at `$pos`.
 * Mandatory whitespace on BOTH sides per CSS Values & Units L4 — this
 * also disambiguates from a signed number immediately following (`1px +
 * -2px`: the `-2px` sign belongs to the number) and correctly rejects
 * the otherwise-ambiguous `1px -2px` (no operator recognised → the
 * trailing `-2px` is left unconsumed, which fails the caller's "entire
 * string consumed" check). Returns the position right after the
 * operator when found, or null when `$pos` isn't the start of a valid
 * binary +/- occurrence (no leading whitespace, no +/- there, or no
 * trailing whitespace) — extracted from {@see mo_calc_parse_sum()} to
 * keep that function's cyclomatic complexity down.
 */
function mo_calc_find_additive_operator(string $s, int $pos): ?int
{
    if ($pos >= strlen($s) || !mo_calc_is_whitespace_char($s[$pos])) {
        return null;
    }
    $operator_pos = $pos;
    mo_calc_skip_whitespace($s, $operator_pos);
    if ($operator_pos >= strlen($s) || $s[$operator_pos] !== '+' && $s[$operator_pos] !== '-') {
        return null;
    }
    $after_operator = $operator_pos + 1;
    if ($after_operator >= strlen($s) || !mo_calc_is_whitespace_char($s[$after_operator])) {
        return null;
    }
    return $after_operator;
}

function mo_calc_parse_sum(string $s, int &$pos): ?string
{
    $type = mo_calc_parse_product($s, $pos);
    if ($type === null) {
        return null;
    }
    while (true) {
        $after_operator = mo_calc_find_additive_operator($s, $pos);
        if ($after_operator === null) {
            return $type;
        }
        $pos = $after_operator;
        mo_calc_skip_whitespace($s, $pos);
        $rhs_type = mo_calc_parse_product($s, $pos);
        if ($rhs_type === null) {
            return null;
        }
        $type = mo_calc_additive_result_type($type, $rhs_type);
        if ($type === null) {
            return null;
        }
    }
}

/**
 * `*`/`/` don't require surrounding whitespace (unlike `+`/`-` — see
 * {@see mo_calc_parse_sum()}), matching CSS Values & Units L4.
 */
function mo_calc_parse_product(string $s, int &$pos): ?string
{
    $type = mo_calc_parse_value($s, $pos);
    if ($type === null) {
        return null;
    }
    while (true) {
        $operator_pos = $pos;
        mo_calc_skip_whitespace($s, $operator_pos);
        if ($operator_pos >= strlen($s) || $s[$operator_pos] !== '*' && $s[$operator_pos] !== '/') {
            return $type;
        }
        $operator = $s[$operator_pos];
        $pos = $operator_pos + 1;
        mo_calc_skip_whitespace($s, $pos);
        $rhs_type = mo_calc_parse_value($s, $pos);
        if ($rhs_type === null) {
            return null;
        }
        $type = $operator === '*'
            ? mo_calc_multiplicative_result_type($type, $rhs_type)
            : mo_calc_division_result_type($type, $rhs_type);
        if ($type === null) {
            return null;
        }
    }
}

function mo_calc_parse_value(string $s, int &$pos): ?string
{
    mo_calc_skip_whitespace($s, $pos);
    if ($pos >= strlen($s)) {
        return null;
    }
    if ($s[$pos] === '(') {
        return mo_calc_parse_parenthesised_sum($s, $pos);
    }
    $matches = [];
    if (preg_match('/\G[a-zA-Z-]+\(/', $s, $matches, offset: $pos) === 1) {
        return mo_calc_parse_function($s, $pos, $matches[0]);
    }
    return mo_calc_parse_number($s, $pos);
}

function mo_calc_parse_parenthesised_sum(string $s, int &$pos): ?string
{
    $close_pos = mo_find_matching_paren($s, $pos);
    if ($close_pos === null) {
        return null;
    }
    $inner = substr($s, $pos + 1, $close_pos - $pos - 1);
    $type = mo_calc_is_valid_sum($inner);
    if ($type === null) {
        return null;
    }
    $pos = $close_pos + 1;
    return $type;
}

function mo_calc_parse_function(string $s, int &$pos, string $opening): ?string
{
    $name = strtolower(substr($opening, offset: 0, length: -1));
    if (!in_array($name, MO_CSS_CALC_FUNCTIONS, strict: true)) {
        return null;
    }
    $open_pos = $pos + strlen($opening) - 1;
    $close_pos = mo_find_matching_paren($s, $open_pos);
    if ($close_pos === null) {
        return null;
    }
    $args = substr($s, $open_pos + 1, $close_pos - $open_pos - 1);
    $type = mo_calc_is_valid_function_call($name, $args);
    if ($type === null) {
        return null;
    }
    $pos = $close_pos + 1;
    return $type;
}

/**
 * Validate the arguments of one `calc()`-family function call, dispatched
 * by name, and return its resolved dimensional type. `var()` defers to
 * {@see mo_validate_var_reference()} and always resolves to `unknown`;
 * `calc()` requires its whole body to be one calc-sum (its type is that
 * sum's type); `min()`/`max()` require at least one calc-sum argument
 * each, all pairwise-compatible (combined the same way `+`/`-` combine
 * operand types); `clamp()` requires exactly three (min, value, max —
 * the real CSS `clamp()` arity), likewise pairwise-compatible.
 */
function mo_calc_is_valid_function_call(string $name, string $args): ?string
{
    if ($name === 'var') {
        return mo_validate_var_reference($args) === null ? 'unknown' : null;
    }
    if ($name === 'calc') {
        return mo_calc_is_valid_sum($args);
    }
    $arg_list = mo_split_top_level_commas($args);
    if ($name === 'clamp' && count($arg_list) !== 3) {
        return null;
    }
    if ($arg_list === [] || $arg_list === ['']) {
        return null;
    }
    $combined_type = mo_calc_is_valid_sum($arg_list[0]);
    if ($combined_type === null) {
        return null;
    }
    foreach (array_slice($arg_list, offset: 1) as $arg) {
        $arg_type = mo_calc_is_valid_sum($arg);
        if ($arg_type === null) {
            return null;
        }
        $combined_type = mo_calc_additive_result_type($combined_type, $arg_type);
        if ($combined_type === null) {
            return null;
        }
    }
    return $combined_type;
}

function mo_calc_parse_number(string $s, int &$pos): ?string
{
    $matches = [];
    if (preg_match('/\G-?(?:\d+\.\d+|\.\d+|\d+)(?:[eE][+-]?\d+)?(%|[a-zA-Z]+)?/', $s, $matches, offset: $pos) !== 1) {
        return null;
    }
    $unit = strtolower($matches[1] ?? '');
    if ($unit === '') {
        $pos += strlen($matches[0]);
        return 'number';
    }
    if ($unit === '%') {
        $pos += strlen($matches[0]);
        return 'percentage';
    }
    if (!in_array($unit, mo_variable_length_units(), strict: true)) {
        return null;
    }
    $pos += strlen($matches[0]);
    return 'length';
}

/**
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_variable_value_locked(string $id, string $skin_id, string $mode_id, string $value): array|WP_Error
{
    $row = mo_load('collection_variables', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_concurrent_modification_error('variable', $id);
    }
    $old_revision = mo_str($row, key: 'revision');
    /** @var array<string, mixed> $data */
    $data = mo_decode_data($row['data'] ?? null);
    /** @var array<string, mixed> $skins_data */
    $skins_data = is_array($data['skinsData'] ?? null) ? $data['skinsData'] : [];
    /** @var array<string, mixed> $skin_data */
    $skin_data = is_array($skins_data[$skin_id] ?? null) ? $skins_data[$skin_id] : [];
    $skin_data[$mode_id] = ['value' => $value];
    $skins_data[$skin_id] = $skin_data;
    $data['skinsData'] = $skins_data;

    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->update(
        mo_table('collection_variables'),
        ['data' => mo_encode_object_blob($data), 'revision' => mo_uuid()],
        ['ID' => $id, 'status' => 'publish', 'revision' => $old_revision],
        ['%s', '%s'],
        ['%s', '%s', '%s'],
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __('Could not update the Mosaic variable value.', domain: 'novamira-pro'));
    }
    if ($ok === 0) {
        return mo_concurrent_modification_error('variable', $id);
    }
    $fresh = mo_load('collection_variables', $id);
    if ($fresh === null) {
        return mo_concurrent_modification_error('variable', $id);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-set-variable-value',
        operation: 'set',
        entity: 'variable',
        entity_id: $id,
        context: ['theme_id' => mo_str($fresh, key: 'themeID')],
    );
    return ['variable' => mo_present_variable_full($fresh)];
}
