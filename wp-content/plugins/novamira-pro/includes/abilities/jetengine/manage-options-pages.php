<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ----- create -----

wp_register_ability('novamira/jetengine-create-options-page', [
    'label' => __('Create JetEngine Options Page', domain: 'novamira-pro'),
    'description' => __(
        'Create a JetEngine options page record. Accepts the same shape returned by get-options-page (without editable/source). Idempotent on identical payloads. Conflicting id returns options_page_id_exists.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Options page slug (e.g. "site-settings"). Use only lowercase letters, digits, and hyphens: JetEngine\'s admin URL routing rejects underscores, so an id like "site_settings" persists correctly but can only be edited via this ability, not through the JetEngine admin UI.',
            ],
            'name' => ['type' => 'string'],
            'menu_name' => ['type' => 'string'],
            'menu_position' => ['type' => 'integer'],
            'parent' => ['type' => 'string'],
            'icon' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'hide_field_names' => ['type' => 'boolean'],
            'meta_fields' => ['type' => 'array'],
        ],
        'required' => ['id', 'name', 'meta_fields'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'created' => ['type' => 'boolean'],
        ],
        'required' => ['id', 'created'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_create_options_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => true],
    ],
]);

// ----- edit -----

wp_register_ability('novamira/jetengine-edit-options-page', [
    'label' => __('Edit JetEngine Options Page', domain: 'novamira-pro'),
    'description' => __(
        'Update a JetEngine options page. Top-level keys merge; meta_fields replaces atomically when provided. PHP-registered records refuse with options_page_not_editable.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Options page slug (e.g. "site-settings"). Use only lowercase letters, digits, and hyphens: JetEngine\'s admin URL routing rejects underscores, so an id like "site_settings" persists correctly but can only be edited via this ability, not through the JetEngine admin UI.',
            ],
            'name' => ['type' => 'string'],
            'menu_name' => ['type' => 'string'],
            'menu_position' => ['type' => 'integer'],
            'parent' => ['type' => 'string'],
            'icon' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'hide_field_names' => ['type' => 'boolean'],
            'meta_fields' => ['type' => 'array'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
        ],
        'required' => ['id'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_edit_options_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => true],
    ],
]);

// ----- delete -----

wp_register_ability('novamira/jetengine-delete-options-page', [
    'label' => __('Delete JetEngine Options Page', domain: 'novamira-pro'),
    'description' => __(
        'Permanently remove a JetEngine options page by id. PHP-registered records refuse with options_page_not_editable.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'string']],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'id' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'id'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_delete_options_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => true, 'idempotent' => true],
    ],
]);

// ----- helpers (file-private) -----

/**
 * Resolve base scalar values from an existing record or defaults.
 *
 * @param array<string, mixed>|null $existing Normalised record without editable/source.
 * @param string                    $fallback_name Name from input to use when creating.
 * @return array<string, mixed>
 */
function op_scalar_defaults(?array $existing, string $fallback_name): array
{
    return [
        'name' => (string) ($existing['name'] ?? $fallback_name),
        'menu_name' => (string) ($existing['menu_name'] ?? $existing['name'] ?? $fallback_name),
        'menu_position' => $existing['menu_position'] ?? null,
        'parent' => $existing['parent'] ?? null,
        'icon' => $existing['icon'] ?? null,
        'capability' => $existing['capability'] ?? null,
        'hide_field_names' => filter_var($existing['hide_field_names'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];
}

/**
 * Merge scalar fields from $input onto the base values derived from $existing.
 *
 * Returns the resolved scalar tuple: [name, menu_name, menu_position, parent,
 * icon, capability, hide_field_names].
 *
 * @param array<string, mixed>      $input
 * @param array<string, mixed>|null $existing Normalised record without editable/source.
 * @return array<string, mixed>
 */
function op_merge_scalar_fields(array $input, ?array $existing): array
{
    $base = op_scalar_defaults($existing, (string) ($input['name'] ?? ''));

    // Override simple nullable scalar fields directly from input.
    foreach (['menu_position', 'parent', 'icon', 'capability'] as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }

        $base[$key] = $input[$key];
    }

    if (array_key_exists('hide_field_names', $input)) {
        $base['hide_field_names'] = filter_var($input['hide_field_names'], FILTER_VALIDATE_BOOLEAN);
    }

    if (array_key_exists('name', $input)) {
        $base['name'] = (string) $input['name'];
        // On create without explicit menu_name, mirror name into menu_name.
        if ($existing === null && !array_key_exists('menu_name', $input)) {
            $base['menu_name'] = $base['name'];
        }
    }

    if (array_key_exists('menu_name', $input)) {
        $base['menu_name'] = (string) $input['menu_name'];
    }

    return $base;
}

/**
 * Build the JetEngine args array from resolved scalar fields.
 *
 * For create: emits the JetEngine defaults for storage_type/option_prefix/
 * autoload_option so a freshly registered page mirrors what the UI creates.
 *
 * For edit ($existing_args present): preserves every JetEngine arg the
 * ability does not surface (custom storage_type, option_prefix overrides,
 * autoload tweaks, and any future settings) and overrides only the five
 * scalar keys the ability exposes. Without this, round-trip get → edit
 * silently resets every unexposed setting back to the defaults below.
 *
 * @param array<string, mixed> $scalars
 * @param array<array-key, mixed>|null $existing_args
 * @return array<array-key, mixed>
 */
function op_build_args(array $scalars, ?array $existing_args = null): array
{
    $defaults = [
        'storage_type' => 'default',
        'option_prefix' => true,
        'autoload_option' => true,
    ];
    $base = is_array($existing_args) ? $existing_args : $defaults;

    return array_merge($base, [
        'position' => $scalars['menu_position'] !== null ? (int) $scalars['menu_position'] : '',
        'parent' => $scalars['parent'] !== null ? (string) $scalars['parent'] : '',
        'icon' => $scalars['icon'] !== null ? (string) $scalars['icon'] : 'dashicons-admin-generic',
        'capability' => $scalars['capability'] !== null ? (string) $scalars['capability'] : 'manage_options',
        'hide_field_names' => $scalars['hide_field_names'],
    ]);
}

/**
 * Build the DB-layer payload from the flat agent-facing input.
 *
 * JetEngine stores name/menu_name in the serialized `labels` column,
 * and position/parent/icon/capability/hide_field_names in the serialized
 * `args` column. This helper maps the flat agent input back to that structure.
 * When $existing is non-null it is used as the merge base.
 *
 * @param array<string, mixed>      $input    Agent-facing flat input.
 * @param array<string, mixed>|null $existing Normalised record without editable/source.
 * @return array<string, mixed>
 */
function op_build_db_payload(array $input, ?array $existing = null): array
{
    $scalars = op_merge_scalar_fields($input, $existing);

    /** @var list<array<string, mixed>> $meta_fields */
    $meta_fields = $existing['meta_fields'] ?? [];
    if (array_key_exists('meta_fields', $input)) {
        /** @var mixed $raw_fields */
        $raw_fields = $input['meta_fields'];
        /** @var list<array<string, mixed>> $meta_fields */
        $meta_fields = array_values(is_array($raw_fields) ? $raw_fields : []);
    }

    // For edit: fetch full existing args from the DB row so unexposed
    // JetEngine settings (storage_type, option_prefix, autoload_option, any
    // future fields) survive the round-trip. For create: defaults are
    // emitted by op_build_args.
    $existing_args = null;
    if ($existing !== null) {
        /** @var mixed $slug_raw */
        $slug_raw = $scalars['id'] ?? $existing['id'] ?? '';
        $existing_args = op_fetch_existing_args(is_string($slug_raw) ? $slug_raw : '');
    }

    return [
        'id' => (string) ($input['id'] ?? $existing['id'] ?? ''),
        'slug' => (string) ($input['id'] ?? $existing['id'] ?? ''),
        'status' => 'page',
        'labels' => ['name' => $scalars['name'], 'menu_name' => $scalars['menu_name']],
        'args' => op_build_args($scalars, $existing_args),
        'meta_fields' => $meta_fields,
    ];
}

/**
 * Read the existing serialized `args` blob for an options page directly from
 * the wp_jet_post_types table. Used by edit to preserve unexposed settings.
 *
 * @return array<array-key, mixed>|null
 */
function op_fetch_existing_args(string $slug): ?array
{
    if ($slug === '') {
        return null;
    }
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return null;
    }
    /** @var string $sql */
    $sql = $wpdb->prepare(
        'SELECT args FROM %i WHERE slug = %s AND status = %s LIMIT 1',
        $wpdb->prefix . 'jet_post_types',
        $slug,
        'page',
    );
    /** @var mixed $raw */
    $raw = $wpdb->get_var($sql);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    /** @var mixed $unserialized */
    $unserialized = maybe_unserialize($raw);
    return is_array($unserialized) ? $unserialized : null;
}

/**
 * Normalise an args array back to the flat agent-facing nullable fields
 * for idempotency comparison.
 *
 * @param array<string, mixed> $args
 * @return array{menu_position: int|null, parent: string|null, icon: string|null, capability: string|null, hide_field_names: bool}
 */
function op_normalise_args_for_compare(array $args): array
{
    return [
        'menu_position' => $args['position'] !== '' ? (int) $args['position'] : null,
        'parent' => $args['parent'] !== '' ? (string) $args['parent'] : null,
        'icon' => $args['icon'] !== '' && $args['icon'] !== 'dashicons-admin-generic' ? (string) $args['icon'] : null,
        'capability' =>
            $args['capability'] !== '' && $args['capability'] !== 'manage_options'
                ? (string) $args['capability']
                : null,
        'hide_field_names' => filter_var($args['hide_field_names'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];
}

/**
 * Build a normalised comparable snapshot from a DB-layer payload (create path).
 *
 * @param string               $id
 * @param array<string, mixed> $payload Return value of op_build_db_payload.
 * @return array<string, mixed>
 */
function op_comparable_from_payload(string $id, array $payload): array
{
    /** @var array<string, mixed> $labels */
    $labels = is_array($payload['labels']) ? $payload['labels'] : [];
    /** @var array<string, mixed> $args */
    $args = is_array($payload['args']) ? $payload['args'] : [];
    $flat = op_normalise_args_for_compare($args);

    return [
        'id' => $id,
        'name' => (string) ($labels['name'] ?? ''),
        'menu_name' => (string) ($labels['menu_name'] ?? ''),
        'menu_position' => $flat['menu_position'],
        'parent' => $flat['parent'],
        'icon' => $flat['icon'],
        'capability' => $flat['capability'],
        'hide_field_names' => $flat['hide_field_names'],
        'meta_fields' => is_array($payload['meta_fields']) ? array_values($payload['meta_fields']) : [],
    ];
}

/**
 * Build a normalised comparable snapshot from a get-options-page record (existing path).
 *
 * @param array<string, mixed> $record Return value of jetengine_get_options_page.
 * @return array<string, mixed>
 */
function op_comparable_from_record(array $record): array
{
    return [
        'id' => (string) ($record['id'] ?? ''),
        'name' => (string) ($record['name'] ?? ''),
        'menu_name' => (string) ($record['menu_name'] ?? ''),
        'menu_position' => $record['menu_position'],
        'parent' => $record['parent'],
        'icon' => $record['icon'],
        'capability' => $record['capability'],
        'hide_field_names' => filter_var($record['hide_field_names'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'meta_fields' => is_array($record['meta_fields']) ? array_values($record['meta_fields']) : [],
    ];
}

/**
 * Handle idempotency check when a DB record with the same id already exists on create.
 *
 * Returns existing (created=false) on identical payload, or options_page_id_exists.
 *
 * @param string               $id
 * @param array<string, mixed> $input
 * @return array{id: string, created: bool}|WP_Error
 */
function op_handle_create_idempotency(string $id, array $input): array|WP_Error
{
    $existing = jetengine_get_options_page(['id' => $id]);
    if ($existing instanceof WP_Error) {
        return $existing;
    }

    $candidate = op_build_db_payload($input);
    if (op_comparable_from_payload($id, $candidate) === op_comparable_from_record($existing)) {
        return ['id' => $id, 'created' => false];
    }

    return new WP_Error(
        'options_page_id_exists',
        __(
            'An options page with this id already exists. Use jetengine-edit-options-page to modify it.',
            domain: 'novamira-pro',
        ),
        ['id' => $id],
    );
}

// ----- callbacks -----

/**
 * @param array<string, mixed> $input
 * @return array{id: string, created: bool}|WP_Error
 */
function jetengine_create_options_page(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = (string) ($input['id'] ?? '');
    if ($id === '') {
        return new WP_Error('invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $existing_source = jetengine_options_page_source($id);

    if ($existing_source === 'php') {
        return new WP_Error('options_page_not_editable', __(
            'Options page is registered via PHP and cannot be created or modified at runtime.',
            domain: 'novamira-pro',
        ));
    }

    if ($existing_source === 'db') {
        return op_handle_create_idempotency($id, $input);
    }

    $data = jet_engine()->options_pages->data ?? null;
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine options-pages data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $payload = op_build_db_payload($input);
    // Strip the agent-facing id string: the DB row uses an auto-increment bigint.
    // Passing no 'id' key triggers an INSERT in Jet_Engine_DB::update().
    unset($payload['id']);
    $written = $data->update_item_in_db($payload);
    if ($written === false) {
        return new WP_Error('jetengine_write_failed', __(
            'Failed to persist the options page.',
            domain: 'novamira-pro',
        ));
    }

    jetengine_invalidate_raw_cache($data);

    return ['id' => $id, 'created' => true];
}

/**
 * @param array<string, mixed> $input
 * @return array{id: string}|WP_Error
 */
function jetengine_edit_options_page(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = (string) ($input['id'] ?? '');
    if ($id === '') {
        return new WP_Error('invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $source = jetengine_options_page_source($id);
    if ($source === null) {
        return new WP_Error('options_page_not_found', __('Options page not found.', domain: 'novamira-pro'));
    }

    if ($source === 'php') {
        return new WP_Error('options_page_not_editable', __(
            'Options page is registered via PHP. Edit the source file via novamira/edit-file.',
            domain: 'novamira-pro',
        ));
    }

    $existing = jetengine_get_options_page(['id' => $id]);
    if ($existing instanceof WP_Error) {
        return $existing;
    }

    $data = jet_engine()->options_pages->data ?? null;
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine options-pages data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $existing_clean = $existing;
    unset($existing_clean['editable'], $existing_clean['source']);

    $payload = op_build_db_payload($input, $existing_clean);
    // Look up the numeric DB primary-key id from the slug; Jet_Engine_DB::update()
    // requires the integer id to perform an UPDATE rather than an INSERT.
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return new WP_Error('jetengine_unavailable', __('wpdb is unavailable.', domain: 'novamira-pro'));
    }
    /** @var string $op_id_sql */
    $op_id_sql = $wpdb->prepare(
        'SELECT id FROM %i WHERE slug = %s AND status = %s LIMIT 1',
        $wpdb->prefix . 'jet_post_types',
        $id,
        'page',
    );
    $numeric_id = $wpdb->get_var($op_id_sql);
    if ($numeric_id === null) {
        return new WP_Error('options_page_not_found', __('Options page not found.', domain: 'novamira-pro'));
    }
    $payload['id'] = (int) $numeric_id;

    $written = $data->update_item_in_db($payload);
    if ($written === false) {
        return new WP_Error('jetengine_write_failed', __(
            'Failed to persist the options page.',
            domain: 'novamira-pro',
        ));
    }

    jetengine_invalidate_raw_cache($data);

    return ['id' => $id];
}

/**
 * @param array{id: string} $input
 * @return array{deleted: bool, id: string}|WP_Error
 */
function jetengine_delete_options_page(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $input['id'];
    if ($id === '') {
        return new WP_Error('invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $source = jetengine_options_page_source($id);
    if ($source === null) {
        return new WP_Error('options_page_not_found', __('Options page not found.', domain: 'novamira-pro'));
    }

    if ($source === 'php') {
        return new WP_Error('options_page_not_editable', __(
            'Options page is registered via PHP. Edit the source file via novamira/edit-file.',
            domain: 'novamira-pro',
        ));
    }

    $data = jet_engine()->options_pages->data ?? null;
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine options-pages data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    foreach ($data->get_items() as $row) {
        if ((string) ($row['slug'] ?? '') !== $id) {
            continue;
        }

        $data->db->delete($data->table, ['id' => (int) $row['id']], ['%d']);
        break;
    }

    jetengine_invalidate_raw_cache($data);

    return ['deleted' => true, 'id' => $id];
}
