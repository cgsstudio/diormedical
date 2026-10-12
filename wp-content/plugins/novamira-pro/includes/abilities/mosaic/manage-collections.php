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
// get-collection
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-get-collection', [
    'label' => __('Get Mosaic Collection', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Mosaic collection by id, including the full decoded `data` blob. Collections are the design-token BUCKETS that hold variables — typically one per family (e.g. "Brand colors", "Spacings", "Typography"). The compact list lives at mosaic-list-collections; this ability returns the full record `{id, theme_id, ordering, status, revision, name, data}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Collection UUID — get one from mosaic-list-collections.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['collection' => ['type' => 'object']],
        'required' => ['collection'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_collection',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'A collection is a parent for variables. To list its variables, call mosaic-list-variables with this collection_id.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// create-collection
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-create-collection', [
    'label' => __('Create Mosaic Collection', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Mosaic collection under a theme. Routes through `CollectionsMResourceManager::restoreCollectionMResource` so the row carries valid ordering, defaults, and revision UUID. Use a collection per design-token family (e.g. "Brand colors", "Spacings"); variables created later with mosaic-create-variable nest under one. Returns the new collection record.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string', 'description' => 'Theme UUID.'],
            'name' => ['type' => 'string', 'description' => 'Display name shown in Mosaic\'s editor (max 191 chars).'],
        ],
        'required' => ['theme_id', 'name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['collection' => ['type' => 'object']],
        'required' => ['collection'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_create_collection',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Theme-scoped. After creating, populate with variables via mosaic-create-variable using this collection_id.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// edit-collection
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-edit-collection', [
    'label' => __('Edit Mosaic Collection', domain: 'novamira-pro'),
    'description' => __(
        'Renames an existing Mosaic collection through Mosaic\'s own collection manager. A variable that has no pinned `custom_property` emits a CSS name Mosaic derives from its collection and variable names, so renaming the collection changes those variables\' `css_variable`; variables with a pinned `custom_property` keep theirs.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Collection UUID.'],
            'name' => ['type' => 'string', 'description' => 'New display name (max 191 chars).'],
        ],
        'required' => ['id', 'name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['collection' => ['type' => 'object']],
        'required' => ['collection'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_collection',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Re-read mosaic-list-variables after a rename: variables without a pinned custom_property now emit a different css_variable.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// delete-collection
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-delete-collection', [
    'label' => __('Delete Mosaic Collection', domain: 'novamira-pro'),
    'description' => __(
        'Soft-deletes a Mosaic collection (status=delete). Variables under the collection are NOT deleted — they stay in the table pointing at the dead collection and stop emitting CSS, and they give up their CSS variable names so a new variable can reuse them. To fully retire a collection, delete its variables first via mosaic-delete-variable. Returns `{id, deleted}`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Collection UUID.'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_delete_collection',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Soft delete. Drain variables first if you intend a clean removal.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Callbacks
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_get_collection(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('collections', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: collection UUID. */
            __('Mosaic collection %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    return ['collection' => mo_present_collection_full($row)];
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_create_collection(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    if ($theme_id === '' || $name === '') {
        return mo_error('mo_invalid_input', __('theme_id and name are required.', domain: 'novamira-pro'));
    }
    $len_err = mo_validate_lengths(['name' => ['value' => $name, 'max' => 191]]);
    if ($len_err !== null) {
        return $len_err;
    }
    $theme = mo_load('themes', $theme_id);
    if ($theme === null || ($theme['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: theme UUID. */
            __('Mosaic theme %s not found.', domain: 'novamira-pro'),
            $theme_id,
        ));
    }
    return mo_with_lock("collection-write:{$theme_id}", static fn(): array|WP_Error => mo_create_collection_locked(
        $theme_id,
        $name,
    ));
}

/**
 * @return array<string, mixed>|WP_Error
 */
function mo_create_collection_locked(string $theme_id, string $name): array|WP_Error
{
    $theme = mo_load('themes', $theme_id);
    if ($theme === null || ($theme['status'] ?? '') !== 'publish') {
        return mo_concurrent_modification_error('theme', $theme_id);
    }
    $id = mo_facade_create_collection($theme_id, $name);
    if ($id instanceof WP_Error) {
        return $id;
    }
    $row = mo_load('collections', $id);
    if ($row === null) {
        return mo_error('mo_facade_failed', __(
            'Collection row not visible after facade write.',
            domain: 'novamira-pro',
        ));
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-create-collection',
        operation: 'create',
        entity: 'collection',
        entity_id: $id,
        context: ['theme_id' => $theme_id],
    );
    return ['collection' => mo_present_collection_full($row)];
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_collection(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    if ($id === '' || $name === '') {
        return mo_error('mo_invalid_input', __('id and name are required.', domain: 'novamira-pro'));
    }
    $len_err = mo_validate_lengths(['name' => ['value' => $name, 'max' => 191]]);
    if ($len_err !== null) {
        return $len_err;
    }
    $row = mo_load('collections', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: collection UUID. */
            __('Mosaic collection %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $theme_id = mo_str($row, key: 'themeID');
    return mo_with_lock("collection-write:{$theme_id}", static fn(): array|WP_Error => mo_apply_collection_edit_locked(
        $id,
        $name,
    ));
}

/**
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_collection_edit_locked(string $id, string $name): array|WP_Error
{
    $row = mo_load('collections', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_concurrent_modification_error('collection', $id);
    }
    // Through Mosaic's collection manager rather than a raw UPDATE. On the
    // current Mosaic a variable without a pinned custom property emits a name
    // derived from its collection's name, and CollectionMResource::sync()
    // marks every descendant variable for re-projection when the collection's
    // name claim changes (markCollectionVariableNamesDirtyIfNeeded()). A raw
    // UPDATE skipped that, leaving the variables' UNIQUE(themeID, emittedName)
    // claims stale.
    $written = mo_facade_editor_write(mo_str($row, key: 'themeID'), static function (object $editor) use (
        $id,
        $name,
    ): bool|WP_Error {
        $resource = mo_editor_resource(mo_editor_manager($editor, type: 'collection'), $id);
        if ($resource === null) {
            return mo_concurrent_modification_error('collection', $id);
        }
        return mo_store_resource_value($resource, property: 'name', value: $name) ?? true;
    });
    if ($written instanceof WP_Error) {
        return $written;
    }
    $fresh = mo_load('collections', $id);
    if ($fresh === null) {
        return mo_concurrent_modification_error('collection', $id);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-edit-collection',
        operation: 'edit',
        entity: 'collection',
        entity_id: $id,
        context: ['theme_id' => mo_str($fresh, key: 'themeID')],
    );
    return ['collection' => mo_present_collection_full($fresh)];
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_delete_collection(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('collections', $id);
    if ($row === null) {
        return ['id' => $id, 'deleted' => false];
    }
    if (($row['status'] ?? '') !== 'publish') {
        return ['id' => $id, 'deleted' => true];
    }
    $theme_id = mo_str($row, key: 'themeID');
    return mo_with_lock("collection-write:{$theme_id}", static fn(): array|WP_Error => mo_delete_collection_locked(
        $id,
    ));
}

/**
 * @return array<string, mixed>|WP_Error
 */
function mo_delete_collection_locked(string $id): array|WP_Error
{
    $row = mo_load('collections', $id);
    if ($row === null) {
        return ['id' => $id, 'deleted' => false];
    }
    if (($row['status'] ?? '') !== 'publish') {
        return ['id' => $id, 'deleted' => true];
    }
    // Deliberately a soft delete, not Mosaic's manager delete(): that path
    // REMOVES the collection row, and Mosaic's editor cascades to the
    // collection's modes, skins and variables on its own side before it
    // commits. Driven from PHP it leaves those children orphaned, and every
    // later editor load of the theme then throws "CollectionMResource
    // instance required" (verified on a live install). The soft-deleted row
    // keeps the children loadable.
    $old_revision = mo_str($row, key: 'revision');
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->update(
        mo_table('collections'),
        ['status' => 'delete', 'revision' => mo_uuid()],
        ['ID' => $id, 'status' => 'publish', 'revision' => $old_revision],
        ['%s', '%s'],
        ['%s', '%s', '%s'],
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __('Could not soft-delete the collection.', domain: 'novamira-pro'));
    }
    if ($ok === 0) {
        return mo_concurrent_modification_error('collection', $id);
    }
    mo_release_collection_variable_names(mo_str($row, key: 'themeID'), $id);
    mo_emit_changed(
        ability: 'novamira/mosaic-delete-collection',
        operation: 'delete',
        entity: 'collection',
        entity_id: $id,
        context: ['theme_id' => mo_str($row, key: 'themeID')],
    );
    return ['id' => $id, 'deleted' => true];
}

/**
 * @param  array<string, mixed> $row
 * @return array<string, mixed>
 */
function mo_present_collection_full(array $row): array
{
    $data = mo_decode_data($row['data'] ?? null);
    return [
        'id' => mo_str($row, key: 'ID'),
        'theme_id' => mo_str($row, key: 'themeID'),
        'ordering' => mo_str($row, key: 'ordering'),
        'status' => mo_str($row, key: 'status'),
        'revision' => mo_str($row, key: 'revision'),
        'name' => is_string($data['name'] ?? null) ? $data['name'] : '',
        'data' => $data,
    ];
}

/**
 * Release the `emittedName` claims of every variable under a collection that
 * was just soft-deleted, directly or through one of its groups. Those
 * variables left Mosaic's name space with their collection, and NULL is what
 * Mosaic's own projection targets for a record outside it
 * (EmittedNameProjection::targetName() :200-204). Without this the stale
 * claims sit in UNIQUE(themeID, emittedName), and the next Mosaic save that
 * gives another variable one of those names fails. NULL never collides — the
 * index admits any number of NULLs.
 */
function mo_release_collection_variable_names(string $theme_id, string $collection_id): void
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $variables = mo_table('collection_variables');
    $groups = mo_table('collection_groups');
    if (!mo_table_has_column('collection_variables', column: 'emittedName')) {
        return;
    }
    // @mago-expect analysis:possibly-invalid-argument
    $sql = (string) $wpdb->prepare(
        "UPDATE `{$variables}` SET `emittedName` = NULL WHERE `themeID` = %s AND (`parentID` = %s OR `parentID` IN (SELECT * FROM (SELECT `ID` FROM `{$groups}` WHERE `themeID` = %s AND `parentID` = %s) AS g))",
        $theme_id,
        $collection_id,
        $theme_id,
        $collection_id,
    );
    $wpdb->query($sql);
}

/**
 * Whether a Mosaic table has a column (the `emittedName` projection column only
 * exists on installs whose data model carries it).
 */
function mo_table_has_column(string $entity, string $column): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table($entity);
    // @mago-expect analysis:possibly-invalid-argument
    $sql = (string) $wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column);
    /** @var mixed $found */
    $found = $wpdb->get_var($sql);
    return is_string($found) && $found === $column;
}
