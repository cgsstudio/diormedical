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
// create-template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-create-template', [
    'label' => __('Create Mosaic Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates a brand-new Mosaic template that is fully renderable: the row is wired into Mosaic\'s MResource graph via the wizard\'s own facade (master linkage + root `template-internal` node), so the Mosaic editor opens it cleanly and the front-end renderer can paint it. Required: theme_id, name. Optional: assign (`auto` / `manual`, defaults to `manual`), path (defaults to `index.php`), conditions (only honored when `assign=auto`; defaults to `[]` which under `auto` would match every front-end page, so leave empty when assign=manual). The template starts with a `template-internal` root (Mosaic\'s canvas wrapper) but no inner section — call mosaic-add-element with parent_id = the new template_id to seed real content. The Mosaic wizard facade resets `path` to `index.php` regardless of the value supplied (Mosaic resolves path against a registered TemplatePath enum at render time, not as a filesystem path) — leave it default unless you have a registered TemplatePath ID to point at. Returns `{ template: {...} }` with id, name, theme_id, master_id (auto-resolved to the theme\'s first master), assign, path, ordering, status (`publish`), version, revision, modified_gmt, conditions.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string', 'description' => 'Theme UUID — get one from mosaic-list-themes.'],
            'name' => ['type' => 'string'],
            'assign' => ['type' => 'string', 'enum' => ['auto', 'manual'], 'default' => 'manual'],
            'path' => ['type' => 'string', 'default' => 'index.php'],
            'conditions' => [
                'type' => 'object',
                'default' => [],
                'description' => 'Visibility-rule list. Mosaic wire shape: a JSON ARRAY of OR-groups, each `{ruleRecords: [{ruleID, uuid, <ruleID>Options: {operator, settings, value}}]}`. Recognized `ruleID`s in Mosaic 1.0.3: `httpGet`, `httpCookie`, `userLoggedIn`, `userRole`, `userCapability`, `userFields`, `userMeta`, plus `post<Tax>` per public taxonomy (e.g. `postCategory`). Unknown ruleIDs silently never match. Empty `[]` (the default) matches every page under `assign=auto`; conditions are ignored entirely under `assign=manual`. There is NO `posts` / `archive` / `singular` / `front_page` rule at this layer — to bind a single post use mosaic-set-template-assign instead.',
            ],
        ],
        'required' => ['theme_id', 'name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['template' => ['type' => 'object']],
        'required' => ['template'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_create_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Routes through Mosaic\'s FinishWizardEditorInstance facade — same code path Mosaic\'s own wizard uses, so the template opens in the editor and renders on the front-end. After creating, add elements with mosaic-add-element. Pair with mosaic-set-template-assign to bind a single post to this template, or mosaic-edit-template to set `conditions` for broader binding.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// edit-template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-edit-template', [
    'label' => __('Edit Mosaic Template', domain: 'novamira-pro'),
    'description' => __(
        'Updates an existing Mosaic template by id. Supports patching name, assign (auto/manual), path, and conditions; pass only the fields you want to change. Conditions: see mosaic-create-template for the wire shape — a JSON ARRAY of OR-groups using a fixed `ruleID` vocabulary; only honored when `assign=auto`. The element tree is NOT touched (use mosaic-add/edit/delete-element). Returns the updated template.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'assign' => ['type' => 'string', 'enum' => ['auto', 'manual']],
            'path' => ['type' => 'string'],
            'conditions' => [
                'type' => 'object',
                'description' => 'See mosaic-create-template for the rule-list shape and supported `ruleID` vocabulary. Replaced wholesale on edit — fetch first and merge locally to patch a single rule. Only honored when `assign=auto`.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['template' => ['type' => 'object']],
        'required' => ['template'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Conditions is replaced wholesale — to patch a single rule, fetch the template first via mosaic-get-template, merge locally, and pass the full object back.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// delete-template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-delete-template', [
    'label' => __('Delete Mosaic Template', domain: 'novamira-pro'),
    'description' => __(
        'Soft-deletes a Mosaic template by id (status flips to `delete`). The element tree is left untouched — re-publishing the template directly via SQL would restore the canvas as it was. Returns the deleted template id.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'string']],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'string'], 'deleted' => ['type' => 'boolean']],
        'required' => ['id', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_delete_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Soft delete only — the template_assigns binding (if any) is NOT removed; call mosaic-clear-template-assign separately to detach the front-end binding.',
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
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_create_template(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    $name = isset($input['name']) && is_string($input['name']) ? $input['name'] : '';
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
    return mo_with_lock("template-write:{$theme_id}", static fn(): array|WP_Error => mo_insert_template_locked(
        $theme_id,
        $name,
        $input,
    ));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mo_insert_template_locked(string $theme_id, string $name, array $input): array|WP_Error
{
    // Re-verify the theme is still publish AFTER acquiring the lock —
    // closes a TOCTOU window where another request could soft-delete
    // the theme between the outer pre-check and this point, leaving the
    // newly-created template orphaned under a dead theme (audit C1 P3).
    $theme_row = mo_load('themes', $theme_id);
    if ($theme_row === null || ($theme_row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: theme UUID. */
            __('Mosaic theme %s was deleted while the request was waiting for the write lock.', domain: 'novamira-pro'),
            $theme_id,
        ));
    }
    $params = mo_extract_create_template_params($input);
    if ($params instanceof WP_Error) {
        return $params;
    }
    $conditions = $params['conditions'];

    // Route through Mosaic's MResource framework rather than a raw
    // wpdb->insert: the framework wires the template into the design-system
    // graph (master + root template-internal node) so Mosaic's editor and
    // front-end renderer both accept it. A raw INSERT would produce a row
    // with masterID='' and zero nodes — Mosaic crashes the next render with
    // "CollectionMResource instance required".
    $created = mo_facade_create_template($theme_id, $name, $params['assign'], $params['path'], $conditions);
    if ($created instanceof WP_Error) {
        return $created;
    }
    $template_id = $created['template_id'];
    $row = mo_patch_template_conditions_if_needed($template_id, $conditions);
    if ($row instanceof WP_Error) {
        return $row;
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-create-template',
        operation: 'create',
        entity: 'template',
        entity_id: $template_id,
        context: ['theme_id' => $theme_id],
    );
    return ['template' => mo_present_template($row)];
}

/**
 * Normalize the optional `assign` / `path` / `conditions` inputs and
 * apply the round-4 depth guard before the lock body runs. Extracted so
 * {@see mo_insert_template_locked()} stays under the cyclomatic
 * complexity threshold.
 *
 * @param array<string, mixed> $input
 * @return array{assign:string, path:string, conditions:array<string, mixed>}|WP_Error
 */
function mo_extract_create_template_params(array $input): array|WP_Error
{
    $assign = is_string($input['assign'] ?? null) && in_array($input['assign'], ['auto', 'manual'], strict: true)
        ? $input['assign']
        : 'manual';
    $path = is_string($input['path'] ?? null) && $input['path'] !== '' ? $input['path'] : 'index.php';
    /** @var array<string, mixed> $conditions */
    $conditions = is_array($input['conditions'] ?? null) ? $input['conditions'] : [];
    $depth_err = mo_validate_blob_depth($conditions, field: 'conditions');
    if ($depth_err !== null) {
        return $depth_err;
    }
    $bytes_err = mo_validate_blob_bytes($conditions, field: 'conditions');
    if ($bytes_err !== null) {
        return $bytes_err;
    }
    return ['assign' => $assign, 'path' => $path, 'conditions' => $conditions];
}

/**
 * Patch the conditions field on a freshly-created template if the caller
 * supplied a non-empty value. Mosaic's wizard helper doesn't write
 * conditions — TemplateRevisionRecord defaults the property to null —
 * so we persist the caller's JSON via direct wpdb so it survives the
 * facade round-trip. Returns the re-read row, or WP_Error.
 *
 * @param array<string, mixed> $conditions
 * @return array<string, mixed>|WP_Error
 */
function mo_patch_template_conditions_if_needed(string $template_id, array $conditions): array|WP_Error
{
    if ($conditions !== []) {
        global $wpdb;
        /** @var \wpdb $wpdb */
        $wpdb->update(
            mo_table('templates'),
            ['conditions' => mo_encode_array_blob($conditions), 'revision' => mo_uuid()],
            ['ID' => $template_id],
            ['%s', '%s'],
            ['%s'],
        );
    }
    $row = mo_load('templates', $template_id);
    if ($row === null) {
        return mo_error('mo_not_found', __('Created template could not be re-read.', domain: 'novamira-pro'));
    }
    return $row;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_template(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('templates', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: template UUID. */
            __('Mosaic template %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $theme_id = mo_str($row, key: 'themeID');
    return mo_with_lock("template-write:{$theme_id}", static fn(): array|WP_Error => mo_apply_template_edit_locked(
        $id,
        $input,
    ));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_template_edit_locked(string $id, array $input): array|WP_Error
{
    $updates = mo_build_template_edit_updates($input);
    if ($updates instanceof WP_Error) {
        return $updates;
    }
    if ($updates['fields'] === []) {
        return mo_error('mo_invalid_input', __(
            'At least one of name / assign / path / conditions must be supplied.',
            domain: 'novamira-pro',
        ));
    }
    // Round 8 H6 / H3: re-read inside the lock, capture revision, and
    // pin the UPDATE to (revision, status=publish). Without this guard
    // an external SQL writer that soft-deletes the template between the
    // outer pre-check and our UPDATE would have us silently mutate the
    // tombstoned row + return a success payload describing the dead
    // template.
    $row = mo_load('templates', $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_concurrent_modification_error('template', $id);
    }
    $old_revision = mo_str($row, key: 'revision');
    $fields = $updates['fields'];
    $formats = $updates['formats'];
    $fields['modified_gmt'] = mo_now_gmt();
    $formats[] = '%s';
    $fields['revision'] = mo_uuid();
    $formats[] = '%s';
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->update(
        mo_table('templates'),
        $fields,
        ['ID' => $id, 'status' => 'publish', 'revision' => $old_revision],
        $formats,
        ['%s', '%s', '%s'],
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __('Could not update the Mosaic template.', domain: 'novamira-pro'));
    }
    if ($ok === 0) {
        return mo_concurrent_modification_error('template', $id);
    }
    $fresh = mo_load('templates', $id);
    if ($fresh === null) {
        return mo_concurrent_modification_error('template', $id);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-edit-template',
        operation: 'edit',
        entity: 'template',
        entity_id: $id,
        context: ['theme_id' => mo_str($fresh, key: 'themeID')],
    );
    return ['template' => mo_present_template($fresh)];
}

/**
 * Validate edit-template inputs (length + blob depth/bytes) and build the
 * fields/formats arrays for the wpdb->update call. Extracted from
 * {@see mo_apply_template_edit_locked()} so the latter stays under the
 * mago halstead-effort threshold.
 *
 * @param array<string, mixed> $input
 * @return array{fields: array<string, string>, formats: list<string>}|WP_Error
 */
function mo_build_template_edit_updates(array $input): array|WP_Error
{
    $len_err = mo_validate_template_edit_lengths($input);
    if ($len_err !== null) {
        return $len_err;
    }
    $simple = mo_extract_template_simple_fields($input);
    if (is_array($input['conditions'] ?? null)) {
        /** @var array<string, mixed> $conditions */
        $conditions = $input['conditions'];
        $blob_err = mo_validate_template_conditions_blob($conditions);
        if ($blob_err !== null) {
            return $blob_err;
        }
        $simple['fields']['conditions'] = mo_encode_array_blob($conditions);
        $simple['formats'][] = '%s';
    }
    return $simple;
}

/**
 * Length-check the optional string fields on edit-template.
 *
 * @param array<string, mixed> $input
 */
function mo_validate_template_edit_lengths(array $input): ?WP_Error
{
    $checks = [];
    if (is_string($input['name'] ?? null)) {
        $checks['name'] = ['value' => $input['name'], 'max' => 191];
    }
    if (is_string($input['path'] ?? null)) {
        $checks['path'] = ['value' => $input['path'], 'max' => 191];
    }
    if ($checks === []) {
        return null;
    }
    return mo_validate_lengths($checks);
}

/**
 * Build the (fields, formats) tuple for name / assign / path edit-template
 * inputs. Conditions handled separately because they need blob validation.
 *
 * @param array<string, mixed> $input
 * @return array{fields: array<string, string>, formats: list<string>}
 */
function mo_extract_template_simple_fields(array $input): array
{
    $fields = [];
    $formats = [];
    if (is_string($input['name'] ?? null) && $input['name'] !== '') {
        $fields['name'] = $input['name'];
        $formats[] = '%s';
    }
    if (is_string($input['assign'] ?? null) && in_array($input['assign'], ['auto', 'manual'], strict: true)) {
        $fields['assign'] = $input['assign'];
        $formats[] = '%s';
    }
    if (is_string($input['path'] ?? null) && $input['path'] !== '') {
        $fields['path'] = $input['path'];
        $formats[] = '%s';
    }
    return ['fields' => $fields, 'formats' => $formats];
}

/**
 * Run depth + byte-size guards on a template `conditions` blob.
 *
 * @param array<string, mixed> $conditions
 */
function mo_validate_template_conditions_blob(array $conditions): ?WP_Error
{
    $depth_err = mo_validate_blob_depth($conditions, field: 'conditions');
    if ($depth_err !== null) {
        return $depth_err;
    }
    return mo_validate_blob_bytes($conditions, field: 'conditions');
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_delete_template(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('templates', $id);
    if ($row === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: template UUID. */
            __('Mosaic template %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    if (($row['status'] ?? '') !== 'publish') {
        return ['id' => $id, 'deleted' => true];
    }
    $theme_id = mo_str($row, key: 'themeID');
    return mo_with_lock("template-write:{$theme_id}", static function () use ($id, $theme_id): array|WP_Error {
        global $wpdb;
        /** @var \wpdb $wpdb */
        $ok = $wpdb->update(
            mo_table('templates'),
            ['status' => 'delete', 'modified_gmt' => mo_now_gmt(), 'revision' => mo_uuid()],
            ['ID' => $id, 'status' => 'publish'],
            ['%s', '%s', '%s'],
            ['%s', '%s'],
        );
        if ($ok === false) {
            return mo_error('mo_write_failed', __(
                'Could not soft-delete the Mosaic template.',
                domain: 'novamira-pro',
            ));
        }
        mo_emit_changed(
            ability: 'novamira/mosaic-delete-template',
            operation: 'delete',
            entity: 'template',
            entity_id: $id,
            context: ['theme_id' => $theme_id],
        );
        return ['id' => $id, 'deleted' => true];
    });
}
