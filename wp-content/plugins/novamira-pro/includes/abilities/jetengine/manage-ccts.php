<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use Jet_Engine\Modules\Custom_Content_Types\Data as CctData;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ----- create -----

wp_register_ability('novamira/jetengine-create-cct', [
    'label' => __('Create JetEngine CCT', domain: 'novamira-pro'),
    'description' => __(
        'Create a new JetEngine Custom Content Type and its dedicated DB table (wp_jet_cct_<slug>). Payload mirrors the get-cct shape (without editable/source/records_count). Idempotent on identical payloads — same slug + identical payload returns existing record with created=false. Conflicting slug returns cct_slug_exists.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'is_admin' => ['type' => 'boolean'],
            'admin_capability' => ['type' => 'string'],
            'cap_type' => ['type' => 'string'],
            'db_field_data' => ['type' => 'array'],
        ],
        'required' => ['slug', 'name', 'db_field_data'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'created' => ['type' => 'boolean'],
            'table' => ['type' => 'string'],
        ],
        'required' => ['slug', 'created', 'table'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_create_cct',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => true],
    ],
]);

// ----- edit -----

wp_register_ability('novamira/jetengine-edit-cct', [
    'label' => __('Edit JetEngine CCT', domain: 'novamira-pro'),
    'description' => __(
        'Update an existing JetEngine CCT. Top-level keys merge; db_field_data replaces atomically when provided. Schema-migration constraints: renaming a field (cct_field_rename_not_supported), changing its SQL type (cct_field_type_change_not_supported), or toggling is_key_field (cct_key_field_change_not_supported) are all rejected before any write. Adding or removing fields is allowed (ALTER TABLE ADD/DROP). PHP-registered CCTs return cct_not_editable.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'is_admin' => ['type' => 'boolean'],
            'admin_capability' => ['type' => 'string'],
            'cap_type' => ['type' => 'string'],
            'db_field_data' => ['type' => 'array'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'fields_diff' => [
                'type' => 'object',
                'properties' => [
                    'added' => ['type' => 'array'],
                    'removed' => ['type' => 'array'],
                ],
            ],
        ],
        'required' => ['slug'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_edit_cct',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => false],
    ],
]);

// ----- delete -----

wp_register_ability('novamira/jetengine-delete-cct', [
    'label' => __('Delete JetEngine CCT', domain: 'novamira-pro'),
    'description' => __(
        'Permanently remove a JetEngine CCT and its dedicated DB table. Refuses with cct_has_content when records exist and force is not true. With force=true, drops the table along with the type definition. PHP-registered CCTs return cct_not_editable.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'force' => ['type' => 'boolean', 'default' => false],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'slug' => ['type' => 'string'],
            'records_dropped' => ['type' => 'integer'],
        ],
        'required' => ['deleted', 'slug', 'records_dropped'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_delete_cct',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => true, 'idempotent' => false],
    ],
]);

// ----- private helpers -----

/**
 * Resolve the CCT data layer from the module. Returns null when unavailable.
 */
function cct_data_layer(): ?CctData
{
    $module = jetengine_get_cct_module();
    if ($module === null) {
        return null;
    }

    /** @var mixed $data */
    $data = $module->manager->data ?? null;
    return $data instanceof CctData ? $data : null;
}

/**
 * Find the numeric DB row ID for a CCT by its slug.
 * Returns null when not found in the database.
 */
function cct_find_db_id(string $slug): ?int
{
    $data = cct_data_layer();
    if ($data === null) {
        return null;
    }

    $rows = $data->get_items();
    foreach ($rows as $row) {
        if ((string) ($row['slug'] ?? '') === $slug) {
            return isset($row['id']) ? (int) $row['id'] : null;
        }
    }

    return null;
}

/**
 * Merge a single nullable string scalar from input onto the existing value or default.
 *
 * @param array<string, mixed>      $input
 * @param array<string, mixed>|null $existing
 */
function cct_merge_string(array $input, ?array $existing, string $key, string $default): string
{
    if (array_key_exists($key, $input)) {
        return (string) $input[$key];
    }

    return (string) ($existing !== null ? $existing[$key] ?? $default : $default);
}

/**
 * Resolve the labels array from input, falling back to existing labels.
 *
 * @param array<string, mixed>      $input
 * @param array<string, mixed>|null $existing
 * @return array<string, mixed>
 */
function cct_resolve_labels(array $input, ?array $existing): array
{
    /** @var array<string, mixed> $labels */
    $labels = [];
    if ($existing !== null && is_array($existing['labels'] ?? null)) {
        /** @var array<string, mixed> $labels */
        $labels = $existing['labels'];
    }

    if (array_key_exists('labels', $input) && is_array($input['labels'])) {
        /** @var array<string, mixed> $labels */
        $labels = $input['labels'];
    }

    return $labels;
}

/**
 * Resolve the scalar CCT fields from input, falling back to $existing then defaults.
 *
 * @param array<string, mixed>      $input
 * @param array<string, mixed>|null $existing
 * @return array{slug: string, name: string, labels: array<string, mixed>, is_admin: bool, admin_capability: string, cap_type: string}
 */
function cct_resolve_scalars(array $input, ?array $existing): array
{
    $slug = cct_merge_string($input, $existing, key: 'slug', default: '');
    $name = cct_merge_string($input, $existing, key: 'name', default: $slug);
    $labels = cct_resolve_labels($input, $existing);
    $admin_capability = cct_merge_string($input, $existing, key: 'admin_capability', default: 'manage_options');
    $cap_type = cct_merge_string($input, $existing, key: 'cap_type', default: 'post');

    $is_admin_default = $existing !== null && isset($existing['is_admin'])
        ? filter_var($existing['is_admin'], FILTER_VALIDATE_BOOLEAN)
        : true;
    $is_admin = array_key_exists('is_admin', $input)
        ? filter_var($input['is_admin'], FILTER_VALIDATE_BOOLEAN)
        : $is_admin_default;

    return [
        'slug' => $slug,
        'name' => $name,
        'labels' => $labels,
        'is_admin' => $is_admin,
        'admin_capability' => $admin_capability,
        'cap_type' => $cap_type,
    ];
}

/**
 * Build the JetEngine args array from resolved scalar fields.
 *
 * For create (no existing args): emits the JetEngine defaults so a freshly
 * registered CCT looks like one created through JetEngine's UI.
 *
 * For edit (existing args present): preserves every JetEngine arg the agent
 * isn't explicitly touching — position, icon, has_single, create_index, the
 * full set of REST flags, related-post-type fields, custom capabilities,
 * and any future JetEngine settings — and overrides only the five scalar
 * keys the ability surfaces. Without this, a round-trip get → edit would
 * silently reset every unexposed setting back to the defaults below.
 *
 * @param array{slug: string, name: string, labels: array<string, mixed>, is_admin: bool, admin_capability: string, cap_type: string} $scalars
 * @param array<string, mixed>|null $existing_args Existing factory args from the DB row, when editing.
 * @return array<string, mixed>
 */
function cct_build_args(array $scalars, ?array $existing_args = null): array
{
    $defaults = [
        'position' => '-1',
        'icon' => 'dashicons-list-view',
        'capability' => $scalars['admin_capability'],
        'has_single' => false,
        'create_index' => false,
        'rest_get_enabled' => false,
        'rest_put_enabled' => false,
        'rest_post_enabled' => false,
        'rest_delete_enabled' => false,
        'hide_field_names' => false,
        'related_post_type' => '',
        'related_post_type_title' => '',
        'related_post_type_content' => '',
        'rest_get_access' => '',
        'rest_put_access' => 'edit_posts',
        'rest_post_access' => 'edit_posts',
        'rest_delete_access' => 'edit_posts',
    ];

    $base = is_array($existing_args) ? $existing_args : $defaults;

    return array_merge($base, [
        'slug' => $scalars['slug'],
        'name' => $scalars['name'],
        'is_admin' => $scalars['is_admin'],
        'admin_capability' => $scalars['admin_capability'],
        'cap_type' => $scalars['cap_type'],
    ]);
}

/**
 * Drop the JetEngine in-request DB query cache for the CCT type table. Without
 * this, get_items() returns the pre-write snapshot for the rest of the request.
 */
function cct_invalidate_data_cache(CctData $data): void
{
    /** @var mixed $db */
    $db = $data->db ?? null;
    if (is_object($db) && property_exists($db, 'query_cache')) {
        /** @phpstan-ignore-next-line property is a dynamic JetEngine wrapper. */
        $db->query_cache = [];
    }
}

/**
 * Build the DB-layer row payload for a CCT.
 *
 * JetEngine stores CCT type definitions in `wp_jet_post_types` with these
 * serialized columns: slug (text), status (text), labels (longtext), args
 * (longtext), meta_fields (longtext). The `args` column holds the full CCT
 * configuration array; `meta_fields` holds the user-defined field definitions.
 *
 * @param array<string, mixed>      $input    Agent-facing input.
 * @param array<string, mixed>|null $existing Existing get-cct output (without read-only keys) for merge on edit.
 * @return array<string, mixed>
 */
function cct_build_row_payload(array $input, ?array $existing = null): array
{
    $scalars = cct_resolve_scalars($input, $existing);

    /** @var list<array<string, mixed>> $db_field_data */
    $db_field_data = is_array($existing['db_field_data'] ?? null) ? $existing['db_field_data'] : [];
    if (array_key_exists('db_field_data', $input) && is_array($input['db_field_data'])) {
        $db_field_data = array_values($input['db_field_data']);
    }

    // For edit, fetch the full existing args from the factory so unexposed
    // JetEngine settings (position, icon, has_single, REST flags, related-CPT,
    // ...) survive the round-trip instead of being reset to cct_build_args
    // defaults. For create, $existing is null and defaults are emitted.
    $existing_args = null;
    if ($existing !== null) {
        $factory = jetengine_cct_get_factory($scalars['slug']);
        if ($factory !== null) {
            $existing_args = $factory->args;
        }
    }

    return [
        'slug' => $scalars['slug'],
        'status' => 'content-type',
        'labels' => $scalars['labels'],
        'args' => cct_build_args($scalars, $existing_args),
        'meta_fields' => $db_field_data,
    ];
}

/**
 * Validate that a CCT slug contains only lowercase alphanumerics, underscores,
 * or hyphens.
 */
function cct_validate_slug(string $slug): bool
{
    return $slug !== '' && preg_match('/^[a-z0-9_-]+$/', $slug) === 1;
}

/**
 * Deep-compare two arrays for structural equality without PHP's loose `==`.
 *
 * Both arrays are serialized to a canonical string form and then compared
 * with `===`, which is safe for all scalar types and nested arrays/objects.
 *
 * @param array<string, mixed> $a
 * @param array<string, mixed> $b
 */
function cct_arrays_equal(array $a, array $b): bool
{
    return serialize($a) === serialize($b);
}

/**
 * Validate schema-migration constraints when db_field_data changes.
 *
 * Returns a WP_Error on the first violation, or null when the diff is safe.
 *
 * @param list<array<string, mixed>> $old_fields
 * @param list<array<string, mixed>> $new_fields
 */
function cct_validate_field_diff(array $old_fields, array $new_fields): ?WP_Error
{
    $diff = jetengine_diff_cct_fields($old_fields, $new_fields);

    if ($diff['type_changed'] !== []) {
        return new WP_Error(
            'cct_field_type_change_not_supported',
            __('Changing the SQL type of an existing field is not supported.', domain: 'novamira-pro'),
            ['fields' => $diff['type_changed']],
        );
    }

    if ($diff['key_field_changed'] !== []) {
        return new WP_Error(
            'cct_key_field_change_not_supported',
            __('Toggling is_key_field on an existing field is not supported.', domain: 'novamira-pro'),
            ['fields' => $diff['key_field_changed']],
        );
    }

    // Detect rename: a removed name paired with an added name. Both removed
    // and added being non-empty signals a rename attempt rather than a plain
    // add-then-remove.
    if ($diff['removed'] !== [] && $diff['added'] !== []) {
        return new WP_Error(
            'cct_field_rename_not_supported',
            __(
                'Renaming a field is not supported. Add the new field, migrate data via novamira/run-php, then remove the old field.',
                domain: 'novamira-pro',
            ),
            ['removed' => $diff['removed'], 'added' => $diff['added']],
        );
    }

    return null;
}

// ----- callbacks -----

/**
 * @param array<string, mixed> $input
 * @return array{slug: string, created: bool, table: string}|WP_Error
 */
function jetengine_create_cct(array $input): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = (string) ($input['slug'] ?? '');
    if (!cct_validate_slug($slug)) {
        return new WP_Error('invalid_input', __(
            'slug must be lowercase alphanumeric with underscores or hyphens.',
            domain: 'novamira-pro',
        ));
    }

    $existing_source = jetengine_cct_source($slug);

    if ($existing_source === 'php') {
        return new WP_Error('cct_not_editable', __(
            'CCT is registered via PHP and cannot be created at runtime.',
            domain: 'novamira-pro',
        ));
    }

    if ($existing_source === 'db') {
        return cct_handle_create_idempotency($slug, $input);
    }

    $data = cct_data_layer();
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine CCT data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $payload = cct_build_row_payload($input);

    $row_id = $data->update_item_in_db($payload);
    if ($row_id === false) {
        return new WP_Error('jetengine_write_failed', __(
            'Failed to persist the CCT type definition.',
            domain: 'novamira-pro',
        ));
    }

    // Trigger JetEngine's table-creation flow. after_item_update() creates
    // the wp_jet_cct_<slug> table when it does not yet exist.
    $payload['id'] = (int) $row_id;
    $data->after_item_update($payload, true);

    cct_invalidate_data_cache($data);

    return ['slug' => $slug, 'created' => true, 'table' => jetengine_cct_table_name($slug)];
}

/**
 * Handle the idempotency check when a DB record with the same slug already
 * exists on a create call.
 *
 * Returns existing (created=false) on identical payload, cct_slug_exists otherwise.
 *
 * @param array<string, mixed> $input
 * @return array{slug: string, created: bool, table: string}|WP_Error
 */
function cct_handle_create_idempotency(string $slug, array $input): array|WP_Error
{
    $existing = jetengine_get_cct(['slug' => $slug]);
    if ($existing instanceof WP_Error) {
        return $existing;
    }

    $existing_clean = $existing;
    unset($existing_clean['editable'], $existing_clean['source'], $existing_clean['records_count']);

    $candidate = cct_build_row_payload($input);
    $scalars = cct_resolve_scalars($input, existing: null);
    $candidate_flat = [
        'slug' => $candidate['slug'],
        'name' => $scalars['name'],
        'labels' => $candidate['labels'],
        'is_admin' => $scalars['is_admin'],
        'admin_capability' => $scalars['admin_capability'],
        'cap_type' => $scalars['cap_type'],
        'db_field_data' => cct_extract_field_list($candidate['meta_fields'] ?? []),
    ];

    if (cct_arrays_equal($candidate_flat, $existing_clean)) {
        return ['slug' => $slug, 'created' => false, 'table' => jetengine_cct_table_name($slug)];
    }

    return new WP_Error(
        'cct_slug_exists',
        __('A CCT with this slug already exists. Use jetengine-edit-cct to modify it.', domain: 'novamira-pro'),
        ['slug' => $slug],
    );
}

/**
 * Normalise a raw db_field_data value (mixed from array access) to a typed list.
 *
 * @param mixed $raw
 * @return list<array<string, mixed>>
 */
function cct_extract_field_list(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    /** @var list<array<string, mixed>> */
    return array_values(array_filter($raw, callback: 'is_array'));
}

/**
 * Validate field diff and return the fields_diff summary, or WP_Error on constraint violation.
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $existing_clean
 * @return array{added: list<string>, removed: list<string>}|WP_Error
 */
function cct_check_field_changes(array $input, array $existing_clean): array|WP_Error
{
    $new_fields = cct_extract_field_list($input['db_field_data'] ?? []);
    $old_fields = cct_extract_field_list($existing_clean['db_field_data'] ?? []);

    $constraint_error = cct_validate_field_diff($old_fields, $new_fields);
    if ($constraint_error !== null) {
        return $constraint_error;
    }

    $diff = jetengine_diff_cct_fields($old_fields, $new_fields);
    return ['added' => $diff['added'], 'removed' => $diff['removed']];
}

/**
 * Persist an edit payload to JetEngine's CCT data layer (UPDATE + ALTER TABLE).
 * Returns WP_Error on failure.
 *
 * @param array<string, mixed> $payload Merged DB payload including 'id'.
 * @param CctData              $data    CCT data layer instance.
 * @return true|WP_Error
 */
function cct_persist_edit(array $payload, CctData $data): bool|WP_Error
{
    $data->before_item_update($payload);

    $updated = $data->update_item_in_db($payload);
    if ($updated === false) {
        return new WP_Error('jetengine_write_failed', __(
            'Failed to update the CCT type definition.',
            domain: 'novamira-pro',
        ));
    }

    $data->after_item_update($payload, false);
    cct_invalidate_data_cache($data);
    return true;
}

/**
 * @param array<string, mixed> $input
 * @return array{slug: string, fields_diff?: array{added: list<string>, removed: list<string>}}|WP_Error
 */
function jetengine_edit_cct(array $input): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = (string) ($input['slug'] ?? '');
    if ($slug === '') {
        return new WP_Error('invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    $source = jetengine_cct_source($slug);
    if ($source === null) {
        return new WP_Error('cct_not_found', __('CCT not found.', domain: 'novamira-pro'));
    }

    if ($source === 'php') {
        return new WP_Error('cct_not_editable', __(
            'CCT is PHP-registered. Edit the source file via novamira/edit-file.',
            domain: 'novamira-pro',
        ));
    }

    $existing = jetengine_get_cct(['slug' => $slug]);
    if ($existing instanceof WP_Error) {
        return $existing;
    }

    $existing_clean = $existing;
    unset($existing_clean['editable'], $existing_clean['source'], $existing_clean['records_count']);

    $fields_diff = ['added' => [], 'removed' => []];
    if (array_key_exists('db_field_data', $input)) {
        $field_result = cct_check_field_changes($input, $existing_clean);
        if ($field_result instanceof WP_Error) {
            return $field_result;
        }

        $fields_diff = $field_result;
    }

    $data = cct_data_layer();
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine CCT data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $db_id = cct_find_db_id($slug);
    if ($db_id === null) {
        return new WP_Error('cct_not_found', __('CCT DB row not found.', domain: 'novamira-pro'));
    }

    $payload = cct_build_row_payload($input, $existing_clean);
    $payload['id'] = $db_id;

    $persisted = cct_persist_edit($payload, $data);
    if ($persisted instanceof WP_Error) {
        return $persisted;
    }

    return ['slug' => $slug, 'fields_diff' => $fields_diff];
}

/**
 * @param array{slug: string, force?: bool} $input
 * @return array{deleted: bool, slug: string, records_dropped: int}|WP_Error
 */
function jetengine_delete_cct(array $input): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $input['slug'];
    if ($slug === '') {
        return new WP_Error('invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    $source = jetengine_cct_source($slug);
    if ($source === null) {
        return new WP_Error('cct_not_found', __('CCT not found.', domain: 'novamira-pro'));
    }

    if ($source === 'php') {
        return new WP_Error('cct_not_editable', __(
            'CCT is PHP-registered. Edit the source file via novamira/edit-file.',
            domain: 'novamira-pro',
        ));
    }

    $force = isset($input['force']) && $input['force'];
    $count = jetengine_cct_records_count($slug);

    if ($count > 0 && !$force) {
        return new WP_Error(
            'cct_has_content',
            __('CCT has existing records. Pass force=true to drop them along with the type.', domain: 'novamira-pro'),
            ['records_count' => $count],
        );
    }

    $data = cct_data_layer();
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine CCT data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $db_id = cct_find_db_id($slug);
    if ($db_id === null) {
        return new WP_Error('cct_not_found', __('CCT DB row not found.', domain: 'novamira-pro'));
    }

    // before_item_delete() drops the wp_jet_cct_<slug> table.
    $data->before_item_delete($db_id);

    $data->db->delete('post_types', ['id' => $db_id], ['%d']);

    cct_invalidate_data_cache($data);

    return ['deleted' => true, 'slug' => $slug, 'records_dropped' => $force ? $count : 0];
}
