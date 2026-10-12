<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Abilities: ASE field group create / edit / delete (three abilities in one file).
 */

// ---------------------------------------------------------------------------
// create-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-create-field-group', [
    'label' => __('Create ASE Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ASE CFT field group. Provide a key (slug, lowercase letters/digits/hyphens/underscores, max 100 chars), title, optional fields[], location rules, and show_in_rest_api flag. Idempotent on identical re-create (returns created: false). Returns ase_field_group_exists when key exists with different data. Detects ase_field_name_collision when fields[] contains duplicate name values.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'Field group key/slug. Lowercase letters, digits, hyphens, underscores only. Max 100 chars.',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Human-readable title for the field group.',
            ],
            'fields' => [
                'type' => 'array',
                'description' => 'Optional array of field definitions. Defaults to [].',
            ],
            'location' => [
                'type' => 'object',
                'description' => 'Optional location rules (placement, post_types, taxonomies, etc.). Defaults to no rules.',
            ],
            'show_in_rest_api' => [
                'type' => 'boolean',
                'description' => 'Opt the field group into the REST API. Default false.',
            ],
        ],
        'required' => ['key', 'title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'fields_count' => ['type' => 'integer'],
            'location' => ['type' => 'object'],
            'show_in_rest_api' => ['type' => 'boolean'],
            'source' => ['type' => 'string'],
            'editable' => ['type' => 'boolean'],
            'fields' => ['type' => 'array'],
            'created' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_create_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
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
function ase_create_field_group(array $input): array
{
    $guard = ase_cft_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $raw_key */
    $raw_key = $input['key'] ?? '';
    $key = is_string($raw_key) ? trim($raw_key) : '';
    $key_error = fg_validate_key($key);
    if ($key_error !== null) {
        return ['error' => $key_error, 'code' => 'invalid_input'];
    }

    /** @var mixed $raw_title */
    $raw_title = $input['title'] ?? '';
    $title = is_string($raw_title) ? trim($raw_title) : '';
    if ($title === '') {
        return ['error' => __('title is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    /** @var mixed $raw_fields */
    $raw_fields = $input['fields'] ?? [];
    $fields = is_array($raw_fields) ? $raw_fields : [];

    $name_collision = fg_detect_field_name_collision($fields);
    if ($name_collision !== null) {
        return [
            'error' => sprintf(
                __('Duplicate field name(s) in fields[]: %s', domain: 'novamira-pro'),
                implode(', ', $name_collision),
            ),
            'code' => 'ase_field_name_collision',
            'duplicate_names' => $name_collision,
        ];
    }

    /** @var mixed $raw_location */
    $raw_location = $input['location'] ?? [];
    $location = is_array($raw_location) ? $raw_location : [];
    $rest_api_flag = ($input['show_in_rest_api'] ?? false) === true ? 1 : 0;

    $existing = ase_get_field_group_by_key($key);
    if ($existing !== null) {
        return fg_handle_existing_on_create(
            existing_id: $existing->ID,
            existing_key: $existing->post_name,
            existing_title: $existing->post_title,
            incoming: fg_build_incoming($title, $fields, $location, $rest_api_flag),
            key: $key,
        );
    }

    return fg_do_insert($key, $title, $fields, fg_default_extras($rest_api_flag), fg_location_to_rules($location));
}

/**
 * The full `cfgroup_extras` shape ASE writes. ASE's templates admin and
 * get_matching_groups expect `order` / `context` / `hide_editor` alongside
 * `show_in_rest_api`; storing only `show_in_rest_api` leaves the group in a
 * shape ASE does not fully recognise.
 *
 * @return array{order: string, context: string, hide_editor: string, show_in_rest_api: int}
 */
function fg_default_extras(int $rest_api_flag): array
{
    return [
        'order' => '0',
        'context' => 'normal',
        'hide_editor' => '0',
        'show_in_rest_api' => $rest_api_flag,
    ];
}

/**
 * Build the incoming data struct used for idempotency checks.
 *
 * @param string                       $title
 * @param array<mixed>                 $fields
 * @param array<mixed>                 $location
 * @param int                          $rest_api_flag  0 or 1, matching cfgroup_extras['show_in_rest_api'].
 * @return array{title: string, fields: array<mixed>, rules: array<mixed>, extras: array{order: string, context: string, hide_editor: string, show_in_rest_api: int}}
 */
function fg_build_incoming(string $title, array $fields, array $location, int $rest_api_flag): array
{
    return [
        'title' => $title,
        'fields' => $fields,
        'rules' => fg_location_to_rules($location),
        'extras' => fg_default_extras($rest_api_flag),
    ];
}

/**
 * Handle the case where a field group with the same key already exists on create.
 * Returns idempotent shape when data is identical, conflict error otherwise.
 *
 * @param int                                                             $existing_id
 * @param string                                                          $existing_key
 * @param string                                                          $existing_title
 * @param array{title: string, fields: array<mixed>, rules: array<mixed>, extras: array<mixed>} $incoming
 * @param string                                                          $key
 * @return array<string, mixed>
 */
function fg_handle_existing_on_create(
    int $existing_id,
    string $existing_key,
    string $existing_title,
    array $incoming,
    string $key,
): array {
    $existing_shape = ase_build_field_group_shape_with_fields($existing_id, $existing_key, $existing_title);

    if (fg_shapes_identical($existing_shape, $incoming)) {
        $existing_shape['created'] = false;
        return $existing_shape;
    }

    return [
        'error' => sprintf(
            __('A field group with key "%s" already exists with different data.', domain: 'novamira-pro'),
            $key,
        ),
        'code' => 'ase_field_group_exists',
    ];
}

/**
 * Insert a new field group post and its postmeta.
 *
 * @param string                                   $key
 * @param string                                   $title
 * @param array<mixed>                             $fields
 * @param array{order: string, context: string, hide_editor: string, show_in_rest_api: int} $extras
 * @param array<mixed>                             $rules
 * @return array<string, mixed>
 */
function fg_do_insert(string $key, string $title, array $fields, array $extras, array $rules): array
{
    $post_id = wp_insert_post(postarr: [
        'post_type' => 'asenha_cfgroup',
        'post_status' => 'publish',
        'post_name' => $key,
        'post_title' => $title,
        'post_content' => '',
        'post_content_filtered' => '',
        'post_excerpt' => '',
        'to_ping' => '',
        'pinged' => '',
    ], wp_error: true);

    if (is_wp_error($post_id)) {
        return ['error' => __('Failed to create field group.', domain: 'novamira-pro'), 'code' => 'ase_create_failed'];
    }

    $fields = fg_assign_field_ids($fields);
    update_post_meta($post_id, meta_key: 'cfgroup_fields', meta_value: $fields);
    update_post_meta($post_id, meta_key: 'cfgroup_rules', meta_value: $rules);
    update_post_meta($post_id, meta_key: 'cfgroup_extras', meta_value: $extras);
    fg_flush_cfgroup_cache();

    $shape = ase_build_field_group_shape_with_fields($post_id, $key, $title);
    $shape['created'] = true;

    return $shape;
}

// ---------------------------------------------------------------------------
// edit-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-edit-field-group', [
    'label' => __('Edit ASE Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ASE CFT field group by key. Provide any subset of: title, fields[], location, show_in_rest_api. Missing keys are left unchanged (merge). When fields[] is provided it replaces the stored array atomically. When location keys are provided each top-level key replaces the matching stored key; absent location keys are preserved. Returns the full updated shape including fields[]. Editing also REPAIRS the stored structure to ASE\'s canonical shape: passing fields[] normalises every field to ASE\'s full key set, and any edit that touches show_in_rest_api fills the complete cfgroup_extras keys (order, context, hide_editor). This lets you repair a field group whose stored data is incomplete (e.g. one created before this normalisation) by re-saving it through this ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'The field group key (slug) to edit.',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'New title. Omit to keep existing.',
            ],
            'fields' => [
                'type' => 'array',
                'description' => 'Full replacement fields[] array. When provided, atomically replaces all stored fields.',
            ],
            'location' => [
                'type' => 'object',
                'description' => 'Location rule changes. Each provided top-level key replaces the stored value; absent keys are preserved.',
            ],
            'show_in_rest_api' => [
                'type' => 'boolean',
                'description' => 'Update the REST API opt-in flag. Omit to keep existing.',
            ],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'fields_count' => ['type' => 'integer'],
            'location' => ['type' => 'object'],
            'show_in_rest_api' => ['type' => 'boolean'],
            'source' => ['type' => 'string'],
            'editable' => ['type' => 'boolean'],
            'fields' => ['type' => 'array'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_edit_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ase_edit_field_group(array $input): array
{
    $guard = ase_cft_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $raw_key */
    $raw_key = $input['key'] ?? '';
    $key = is_string($raw_key) ? trim($raw_key) : '';
    if ($key === '') {
        return ['error' => __('key is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    $post = ase_get_field_group_by_key($key);
    if ($post === null) {
        return [
            'error' => sprintf(__('Field group "%s" not found.', domain: 'novamira-pro'), $key),
            'code' => 'ase_field_group_not_found',
        ];
    }

    // Mirror the create-path collision check: ASE allows storing two CFT
    // fields with the same `name` in the same group, and the resulting
    // ambiguity silently breaks read-values. Refuse the edit when the
    // incoming fields[] would produce a duplicate.
    if (array_key_exists('fields', $input)) {
        /** @var mixed $raw_fields */
        $raw_fields = $input['fields'];
        $fields_for_check = is_array($raw_fields) ? $raw_fields : [];
        $name_collision = fg_detect_field_name_collision($fields_for_check);
        if ($name_collision !== null) {
            return [
                'error' => sprintf(
                    __('Duplicate field name(s) in fields[]: %s', domain: 'novamira-pro'),
                    implode(', ', $name_collision),
                ),
                'code' => 'ase_field_name_collision',
                'duplicate_names' => $name_collision,
            ];
        }
    }

    fg_apply_title_update($post->ID, $input);
    fg_apply_fields_update($post->ID, $input);
    fg_apply_location_update($post->ID, $input);
    fg_apply_rest_api_update($post->ID, $input);

    $updated_post = ase_get_field_group_by_key($key);
    if ($updated_post === null) {
        return [
            'error' => __('Failed to reload field group after edit.', domain: 'novamira-pro'),
            'code' => 'ase_reload_failed',
        ];
    }

    return ase_build_field_group_shape_with_fields(
        $updated_post->ID,
        $updated_post->post_name,
        $updated_post->post_title,
    );
}

/**
 * Apply a title update when the 'title' key is present in input.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function fg_apply_title_update(int $post_id, array $input): void
{
    if (!array_key_exists('title', $input)) {
        return;
    }

    /** @var mixed $raw_title */
    $raw_title = $input['title'];
    $new_title = is_string($raw_title) ? trim($raw_title) : '';
    if ($new_title === '') {
        return;
    }

    wp_update_post(['ID' => $post_id, 'post_title' => $new_title]);
}

/**
 * Replace cfgroup_fields atomically when the 'fields' key is present in input.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function fg_apply_fields_update(int $post_id, array $input): void
{
    if (!array_key_exists('fields', $input)) {
        return;
    }

    /** @var mixed $raw_fields */
    $raw_fields = $input['fields'];
    $new_fields = is_array($raw_fields) ? $raw_fields : [];

    // Preserve IDs from existing fields when a field with the same name already
    // exists. This keeps the foreign-key link in wp_asenha_cfgroup_values intact
    // so previously-written values survive a field group edit.
    $existing_ids = fg_build_existing_field_id_map($post_id);
    $new_fields = fg_restore_field_ids($new_fields, $existing_ids);

    $new_fields = fg_assign_field_ids($new_fields);
    update_post_meta($post_id, meta_key: 'cfgroup_fields', meta_value: $new_fields);
    fg_flush_cfgroup_cache();
}

/**
 * Build a name→id map from the fields currently stored for a field group.
 *
 * @param int $post_id Field group post ID.
 * @return array<string, int> Field name → field ID.
 */
function fg_build_existing_field_id_map(int $post_id): array
{
    $existing = ase_fg_read_fields($post_id);
    $map = [];

    /** @var mixed $ef */
    foreach ($existing as $ef) {
        if (!is_array($ef)) {
            continue;
        }

        /** @var mixed $ef_name_raw */
        $ef_name_raw = $ef['name'] ?? null;
        /** @var mixed $ef_id_raw */
        $ef_id_raw = $ef['id'] ?? null;
        if ($ef_name_raw !== null && is_string($ef_name_raw) && $ef_id_raw !== null) {
            $map[$ef_name_raw] = (int) $ef_id_raw;
        }
    }

    return $map;
}

/**
 * Copy stored field IDs from the existing-id map into the incoming fields array.
 *
 * Fields whose name matches an entry in $existing_ids receive its ID so that
 * the foreign-key link in wp_asenha_cfgroup_values is preserved across edits.
 * New fields (no match) are left without an ID and will be assigned one by
 * fg_assign_field_ids().
 *
 * @param array<mixed>       $fields       Incoming fields from input.
 * @param array<string, int> $existing_ids Name → ID map from fg_build_existing_field_id_map().
 * @return array<mixed>
 */
function fg_restore_field_ids(array $fields, array $existing_ids): array
{
    /** @var mixed $nf */
    foreach ($fields as $i => $nf) {
        if (!is_array($nf)) {
            continue;
        }

        /** @var mixed $name_raw */
        $name_raw = $nf['name'] ?? null;
        if ($name_raw === null || !is_string($name_raw)) {
            continue;
        }

        $preserved_id = $existing_ids[$name_raw] ?? null;
        if ($preserved_id !== null && $preserved_id > 0) {
            $nf['id'] = $preserved_id;
            $fields[$i] = $nf;
        }
    }

    return $fields;
}

/**
 * Merge location changes into cfgroup_rules when the 'location' key is present in input.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function fg_apply_location_update(int $post_id, array $input): void
{
    if (!array_key_exists('location', $input)) {
        return;
    }

    /** @var mixed $raw_location */
    $raw_location = $input['location'];
    $location_changes = is_array($raw_location) ? $raw_location : [];

    $current_rules = ase_fg_read_rules($post_id);
    $merged_rules = fg_merge_location_rules($current_rules, $location_changes);
    update_post_meta($post_id, meta_key: 'cfgroup_rules', meta_value: $merged_rules);
}

/**
 * Patch show_in_rest_api in cfgroup_extras when the key is present in input.
 *
 * @param int                  $post_id
 * @param array<string, mixed> $input
 */
function fg_apply_rest_api_update(int $post_id, array $input): void
{
    if (!array_key_exists('show_in_rest_api', $input)) {
        return;
    }

    $rest_flag = $input['show_in_rest_api'] === true ? 1 : 0;
    // Ensure the full extras shape ASE expects (order / context / hide_editor /
    // show_in_rest_api), repairing a group whose extras are missing keys without
    // overwriting values already set.
    $current_extras = array_merge(fg_default_extras($rest_flag), ase_fg_read_extras($post_id));
    $current_extras['show_in_rest_api'] = $rest_flag;
    update_post_meta($post_id, meta_key: 'cfgroup_extras', meta_value: $current_extras);
}

// ---------------------------------------------------------------------------
// delete-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/ase-delete-field-group', [
    'label' => __('Delete ASE Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an ASE CFT field group by key. This removes the field group definition only — existing stored field values on posts/terms are not deleted. Idempotent: returns deleted: true even if the group does not exist.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'The field group key (slug) to delete.',
            ],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_delete_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ase_delete_field_group(array $input): array
{
    $guard = ase_cft_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $raw_key */
    $raw_key = $input['key'] ?? '';
    $key = is_string($raw_key) ? trim($raw_key) : '';
    if ($key === '') {
        return ['error' => __('key is required.', domain: 'novamira-pro'), 'code' => 'invalid_input'];
    }

    $post = ase_get_field_group_by_key($key);
    if ($post === null) {
        return [
            'error' => sprintf(__('Field group with key "%s" not found.', domain: 'novamira-pro'), $key),
            'code' => 'ase_field_group_not_found',
        ];
    }

    wp_delete_post($post->ID, force_delete: true);

    return ['key' => $key, 'deleted' => true];
}

// ---------------------------------------------------------------------------
// Private helpers (fg_ prefix)
// ---------------------------------------------------------------------------

/**
 * Run all three standard CFT guards (runtime, pro, module) and return a
 * formatted error array on the first failure, or null when all pass.
 *
 * @return array<string, mixed>|null
 */
function ase_cft_guard(): ?array
{
    $guard = ase_runtime_guard();
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $guard = ase_pro_guard();
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $guard = ase_module_guard('custom_content_types', __(
        'Enable the "Custom Field Types" module in ASE settings.',
        domain: 'novamira-pro',
    ));
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    return null;
}

/**
 * Normalise every field to ASE's full cfgroup_fields shape and assign a numeric
 * ID to any field that lacks one.
 *
 * ASE stores a per-field numeric ID (the foreign key in
 * `wp_asenha_cfgroup_values`) AND a fixed set of keys per field; a field that
 * carries only `name`/`label`/`type` is saved without error but is unreadable
 * by `get_cf()` and not matched by `get_matching_groups()`. We therefore fill
 * the keys ASE's UI always writes (`notes`, `weight`, `options`, `column_width`,
 * `option_pages`, `parent_id`) with safe defaults and allocate the ID from
 * `admin_site_enhancements_extra['cfgroup_next_field_id']` — the same counter
 * ASE's own field_group->save() uses — writing the counter back when consumed.
 *
 * @param array<mixed> $fields Input fields array (each element is an array).
 * @return array<mixed> The fields completed to ASE's stored shape.
 */
function fg_assign_field_ids(array $fields): array
{
    if ($fields === []) {
        return $fields;
    }

    /** @var mixed $extra_raw */
    $extra_raw = get_option('admin_site_enhancements_extra', []);
    $extra = is_array($extra_raw) ? $extra_raw : [];
    /** @var mixed $raw_next_id */
    $raw_next_id = $extra['cfgroup_next_field_id'] ?? null;
    $next_id = $raw_next_id !== null ? (int) $raw_next_id : 1;
    // The counter must be at least 2 (1 is a sentinel/dummy in ASE's SQL).
    if ($next_id < 2) {
        $next_id = 2;
    }

    $allocated = false;
    $weight = 0;

    /** @var mixed $field */
    foreach ($fields as $i => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field = fg_complete_field_shape($field, $weight);
        $weight++;

        if ((int) $field['id'] <= 0) {
            $field['id'] = $next_id;
            $next_id++;
            $allocated = true;
        }

        $fields[$i] = $field;
    }

    if ($allocated) {
        $extra['cfgroup_next_field_id'] = $next_id;
        update_option('admin_site_enhancements_extra', $extra, autoload: true);
    }

    return $fields;
}

/**
 * Complete a single field to ASE's stored cfgroup_fields shape, filling the
 * keys ASE's UI always writes with safe defaults so the field is readable by
 * get_cf() and matchable by get_matching_groups(). `weight` is the field's
 * order index. `parent_id` defaults to 0 (top-level) — ASE's field_id_lookup
 * builds "$parent_id:$name", so a missing parent_id silently breaks the save.
 * The `id` is normalised to an int and left at 0 when absent; the caller
 * allocates a real id from the global counter.
 *
 * @param array<array-key, mixed> $field
 * @return array<array-key, mixed>
 */
function fg_complete_field_shape(array $field, int $weight): array
{
    /** @var mixed $raw_id */
    $raw_id = $field['id'] ?? null;
    $field['id'] = $raw_id !== null ? (int) $raw_id : 0;
    $field['parent_id'] = array_key_exists('parent_id', $field) ? $field['parent_id'] : 0;
    $field['notes'] = array_key_exists('notes', $field) ? $field['notes'] : '';
    $field['weight'] = $weight;
    $field['options'] = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
    $field['column_width'] = array_key_exists('column_width', $field) ? $field['column_width'] : '';
    $field['option_pages'] = isset($field['option_pages']) && is_array($field['option_pages'])
        ? $field['option_pages']
        : [];
    return $field;
}

/**
 * Flush ASE's in-process field-group cache.
 *
 * `cfgroup_field_group->load_field_groups()` memoizes the list of all field
 * groups (including their `cfgroup_fields` arrays with IDs) on first call and
 * never re-queries. When we insert or update a field group programmatically the
 * cache is stale, causing `get_cf()` and `update_cf()` to work against the old
 * field definitions. Clearing it forces a fresh DB read on the next access.
 *
 * @return void
 */
function fg_flush_cfgroup_cache(): void
{
    if (!function_exists('CFG')) {
        return;
    }

    // Reset the field_group cache so load_field_groups() re-queries the DB.
    \CFG()->field_group->cache = [];

    // Reset the api-level per-post and per-term get_fields() caches.
    \CFG()->api->cache = [];
    \CFG()->api->cache_for_term = [];
}

/**
 * Validate a field group key. Returns an error message string on failure, null on success.
 */
function fg_validate_key(string $key): ?string
{
    if ($key === '') {
        return __('key is required.', domain: 'novamira-pro');
    }

    if (strlen($key) > 100) {
        return __('key must be 100 characters or fewer.', domain: 'novamira-pro');
    }

    if (preg_match('/^[a-z0-9_-]+$/', $key) !== 1) {
        return __('key must contain only lowercase letters, digits, hyphens, and underscores.', domain: 'novamira-pro');
    }

    return null;
}

/**
 * Detect duplicate field names in a fields array.
 *
 * @param array<mixed> $fields
 * @return list<string>|null Offending names, or null when no collision.
 */
function fg_detect_field_name_collision(array $fields): ?array
{
    $names = [];
    $duplicates = [];

    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        /** @var mixed $name_raw */
        $name_raw = $field['name'] ?? null;
        $name = $name_raw !== null && is_string($name_raw) ? $name_raw : '';
        if ($name === '') {
            continue;
        }

        if (($names[$name] ?? null) !== null) {
            $duplicates[$name] = true;
            continue;
        }

        $names[$name] = true;
    }

    return $duplicates !== [] ? array_keys($duplicates) : null;
}

/**
 * Convert an agent-facing location array into the cfgroup_rules storage format.
 *
 * The agent provides flat arrays per key (e.g. `post_types: ['post','page']`).
 * ASE stores each rule type as `['operator' => 'IN', 'values' => [...]]`.
 *
 * Special case for taxonomy-terms placement without an explicit taxonomies list:
 * ASE's get_matching_groups_for_taxonomy() skips field groups that have no
 * 'taxonomies' rule at all (treating them as incomplete), so a group with
 * placement='taxonomy-terms' but no taxonomy restriction would never be matched —
 * neither for read (get_cf) nor for write (save_fields_for_term). To make
 * catch-all groups work correctly, we store a synthetic taxonomies rule with
 * operator='!=' and an empty values list, which the ASE function interprets as
 * "applies to all registered taxonomies".
 *
 * @param array<mixed> $location Agent-provided location object.
 * @return array<mixed> cfgroup_rules storage format.
 */
function fg_location_to_rules(array $location): array
{
    if ($location === []) {
        return [];
    }

    $rules = [];

    /** @var mixed $raw_placement */
    $raw_placement = $location['placement'] ?? null;
    $resolved_placement = fg_resolve_placement($raw_placement);
    if ($resolved_placement !== null) {
        $rules['placement'] = $resolved_placement;
    }

    $rule_keys = [
        'post_types',
        'post_formats',
        'post_ids',
        'page_templates',
        'user_roles',
        'term_ids',
        'taxonomies',
        'options_pages',
    ];
    foreach ($rule_keys as $rk) {
        /** @var mixed $raw */
        $raw = $location[$rk] ?? null;
        if (!is_array($raw) || $raw === []) {
            continue;
        }

        $values = array_values(array_filter(array_map('strval', $raw)));
        if ($values !== []) {
            // ASE's matcher (get_matching_groups for posts, and the taxonomy
            // matcher) interprets the operator as '==' / '!=', never 'IN' — an
            // 'IN' operator silently matches nothing for taxonomies and over-
            // matches for posts, so the group never resolves correctly.
            $rules[$rk] = ['operator' => '==', 'values' => $values];
        }
    }

    // When placement is taxonomy-terms but no taxonomies rule was provided, inject
    // a catch-all rule (operator='!=', values=[]) so ASE's get_matching_groups_for_taxonomy()
    // matches this group for every registered taxonomy.
    $placement_value = $resolved_placement !== null ? $resolved_placement['values'] : '';
    if ($placement_value === 'taxonomy-terms' && !array_key_exists('taxonomies', $rules)) {
        $rules['taxonomies'] = ['operator' => '!=', 'values' => []];
    }

    return $rules;
}

/**
 * Merge agent-provided location changes into the existing cfgroup_rules array.
 *
 * Each top-level key in $changes replaces the corresponding key in $current.
 * Keys absent from $changes are preserved verbatim.
 *
 * @param array<mixed> $current  Existing cfgroup_rules from DB.
 * @param array<mixed> $changes  Agent-facing location object (agent-side shape).
 * @return array<mixed>          Updated cfgroup_rules.
 */
function fg_merge_location_rules(array $current, array $changes): array
{
    if ($changes === []) {
        return $current;
    }

    $incoming_rules = fg_location_to_rules($changes);

    $rule_key_map = [
        'placement' => 'placement',
        'post_types' => 'post_types',
        'post_formats' => 'post_formats',
        'post_ids' => 'post_ids',
        'page_templates' => 'page_templates',
        'user_roles' => 'user_roles',
        'term_ids' => 'term_ids',
        'taxonomies' => 'taxonomies',
        'options_pages' => 'options_pages',
    ];

    $merged = $current;
    foreach (array_keys($changes) as $ak) {
        /** @var mixed $rk_raw */
        $rk_raw = $rule_key_map[$ak] ?? null;
        if (!is_string($rk_raw)) {
            continue;
        }

        $rk = $rk_raw;
        if (!array_key_exists($rk, $incoming_rules)) {
            unset($merged[$rk]);
            continue;
        }

        $merged[$rk] = $incoming_rules[$rk];
    }

    return $merged;
}

/**
 * Normalize a fields array for identity comparison by removing ASE-internal
 * keys that are assigned on write and not supplied by the agent.
 *
 * @param array<mixed> $fields Raw fields array (stored or incoming).
 * @return list<array<string, mixed>> Sorted list of field arrays without internal keys.
 */
function fg_normalise_fields_for_compare(array $fields): array
{
    $internal_keys = ['id', 'weight', 'parent_id', 'column_width', 'option_pages', 'notes'];
    $result = [];

    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $normalised = [];
        /**
         * @var string $k
         * @var mixed  $v
         */
        foreach ($field as $k => $v) {
            if (in_array($k, $internal_keys, strict: true)) {
                continue;
            }

            $normalised[$k] = $v;
        }

        // `options` is auto-filled to [] for fields that do not use it (text,
        // textarea, …); normalise absent/non-array options to [] on both sides so
        // a field created from a minimal {name,label,type} payload compares equal
        // to its stored form (which always carries the key). Fields that DO use
        // options (select, radio, …) still compare their actual choices.
        $normalised['options'] = isset($normalised['options']) && is_array($normalised['options'])
            ? $normalised['options']
            : [];

        ksort($normalised);
        $result[] = $normalised;
    }

    return $result;
}

/**
 * Compare the persistent shape of an existing field group against incoming data
 * to decide whether a re-create is truly identical (idempotency case).
 *
 * @param array<string, mixed>                                            $existing_shape Full shape from ase_build_field_group_shape_with_fields().
 * @param array{title: string, fields: array<mixed>, rules: array<mixed>, extras: array<mixed>} $incoming
 * @return bool
 */
function fg_shapes_identical(array $existing_shape, array $incoming): bool
{
    if (($existing_shape['title'] ?? '') !== $incoming['title']) {
        return false;
    }

    $existing_rest =
        ($existing_shape['show_in_rest_api'] ?? null) !== null
        && $existing_shape['show_in_rest_api'] !== false
        && $existing_shape['show_in_rest_api'] !== 0
        && $existing_shape['show_in_rest_api'] !== '';

    $incoming_rest = ($incoming['extras']['show_in_rest_api'] ?? 0) !== 0;

    if ($existing_rest !== $incoming_rest) {
        return false;
    }

    /** @var mixed $existing_fields */
    $existing_fields = $existing_shape['fields'] ?? [];
    if (!is_array($existing_fields)) {
        return false;
    }

    // Strip ASE-internal keys (id, weight, parent_id, column_width, option_pages,
    // notes) from both sides before comparing. These are assigned by ASE when
    // storing and are not part of the agent-visible field definition.
    if (fg_normalise_fields_for_compare($existing_fields) !== fg_normalise_fields_for_compare($incoming['fields'])) {
        return false;
    }

    /** @var mixed $existing_location */
    $existing_location = $existing_shape['location'] ?? [];
    if (!is_array($existing_location)) {
        return false;
    }

    $incoming_location = ase_normalize_location($incoming['rules']);

    return serialize($existing_location) === serialize($incoming_location);
}

/**
 * Resolve the placement value from agent input into cfgroup_rules placement format.
 *
 * Returns null when no placement is specified.
 *
 * @param mixed $raw
 * @return array{operator: string, values: string}|null
 */
function fg_resolve_placement(mixed $raw): ?array
{
    if (is_array($raw) && $raw !== []) {
        return ['operator' => '==', 'values' => (string) $raw[0]];
    }

    if (is_string($raw) && $raw !== '') {
        return ['operator' => '==', 'values' => $raw];
    }

    return null;
}
