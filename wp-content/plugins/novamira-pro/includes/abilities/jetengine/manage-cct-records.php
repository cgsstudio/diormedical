<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use Jet_Engine\Modules\Custom_Content_Types\DB as CctDB;
use Jet_Engine\Modules\Custom_Content_Types\Factory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ----- create -----

wp_register_ability('novamira/jetengine-create-cct-record', [
    'label' => __('Create JetEngine CCT Record', domain: 'novamira-pro'),
    'description' => __(
        'Insert a new record into a JetEngine CCT table. Returns the persisted record including the auto-assigned _ID. If the CCT has a key field and the supplied value collides with an existing row, returns cct_record_key_collision. Unknown field names in values return cct_invalid_field.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'cct_slug' => ['type' => 'string'],
            'values' => ['type' => 'object'],
        ],
        'required' => ['cct_slug', 'values'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'record' => ['type' => 'object'],
        ],
        'required' => ['record'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_create_cct_record',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => false],
    ],
]);

// ----- edit -----

wp_register_ability('novamira/jetengine-edit-cct-record', [
    'label' => __('Edit JetEngine CCT Record', domain: 'novamira-pro'),
    'description' => __(
        'Update specific fields of a CCT record (field-by-field merge — only keys present in values are written, others untouched). Selector: _ID or key_field_value. Returns the full merged record. Unknown field names in values return cct_invalid_field.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'cct_slug' => ['type' => 'string'],
            '_ID' => ['type' => 'integer'],
            'key_field_value' => ['type' => 'string'],
            'values' => ['type' => 'object'],
        ],
        'required' => ['cct_slug', 'values'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'record' => ['type' => 'object'],
        ],
        'required' => ['record'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_edit_cct_record',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => false],
    ],
]);

// ----- delete -----

wp_register_ability('novamira/jetengine-delete-cct-record', [
    'label' => __('Delete JetEngine CCT Record', domain: 'novamira-pro'),
    'description' => __(
        'Permanently remove a single CCT record. Selector: _ID or key_field_value. Returns the _ID of the deleted row.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'cct_slug' => ['type' => 'string'],
            '_ID' => ['type' => 'integer'],
            'key_field_value' => ['type' => 'string'],
        ],
        'required' => ['cct_slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            '_ID' => ['type' => 'integer'],
        ],
        'required' => ['deleted', '_ID'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_delete_cct_record',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => false, 'destructive' => true, 'idempotent' => false],
    ],
]);

// ----- file-private helpers (mcr_ prefix) -----

/**
 * Locate the name of the key field on a CCT factory, or null when none exists.
 */
function mcr_resolve_key_field(Factory $factory): ?string
{
    foreach ($factory->fields as $field) {
        if (!jetengine_is_key_field($field)) {
            continue;
        }

        $name = (string) ($field['name'] ?? '');
        return $name !== '' ? $name : null;
    }

    return null;
}

/**
 * Build the WHERE clause from input selectors, or return WP_Error on validation failure.
 * Prefers _ID when both selectors are present.
 *
 * @param array{cct_slug: string, _ID?: int, key_field_value?: string, values?: array<string, mixed>} $input
 * @return array<string, mixed>|WP_Error
 */
function mcr_build_where(array $input, Factory $factory): array|WP_Error
{
    if (array_key_exists('_ID', $input)) {
        return ['_ID' => (int) $input['_ID']];
    }

    if (!array_key_exists('key_field_value', $input)) {
        return new WP_Error('cct_record_selector_missing', __(
            'Provide _ID or key_field_value.',
            domain: 'novamira-pro',
        ));
    }

    $key_field = mcr_resolve_key_field($factory);
    if ($key_field === null) {
        return new WP_Error('cct_no_key_field', __(
            'CCT has no field flagged is_key_field; use _ID instead.',
            domain: 'novamira-pro',
        ));
    }

    return [$key_field => $input['key_field_value']];
}

/**
 * Build a list of known field names for a CCT factory (includes _ID).
 *
 * @return list<string>
 */
function mcr_known_fields(Factory $factory): array
{
    $known = ['_ID'];
    foreach ($factory->fields as $field) {
        $name = (string) ($field['name'] ?? '');
        if ($name !== '') {
            $known[] = $name;
        }
    }

    return $known;
}

/**
 * Validate that every key in $values is a known CCT field.
 * Returns the offending keys on failure.
 *
 * @param array<string, mixed> $values
 * @param list<string>         $known
 * @return null|WP_Error
 */
function mcr_validate_values(array $values, array $known): ?WP_Error
{
    $unknown = [];
    foreach (array_keys($values) as $key) {
        if (in_array($key, $known, strict: true)) {
            continue;
        }

        $unknown[] = $key;
    }

    if ($unknown === []) {
        return null;
    }

    return new WP_Error(
        'cct_invalid_field',
        __('One or more field names in values are not defined on this CCT.', domain: 'novamira-pro'),
        ['unknown_fields' => $unknown],
    );
}

// ----- callbacks -----

/**
 * @param array{cct_slug: string, values: array<string, mixed>} $input
 * @return array{record: array<string, mixed>}|WP_Error
 */
function jetengine_create_cct_record(array $input): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $input['cct_slug'];
    if ($slug === '') {
        return new WP_Error('invalid_input', __('cct_slug is required.', domain: 'novamira-pro'));
    }

    $factory = jetengine_cct_get_factory($slug);
    if ($factory === null) {
        return new WP_Error('cct_not_found', __('CCT not found.', domain: 'novamira-pro'));
    }

    /** @var mixed $raw_db */
    $raw_db = $factory->get_db();
    if (!$raw_db instanceof CctDB) {
        return new WP_Error('cct_db_unavailable', __('CCT DB layer unavailable.', domain: 'novamira-pro'));
    }

    $values = $input['values'];

    // _ID is auto-generated by the DB layer on insert. Accepting it in
    // create-values would either fail at insert time or worse, silently
    // pin the row id and break the auto-increment sequence. Reject early.
    if (array_key_exists('_ID', $values)) {
        return new WP_Error('cct_id_immutable', __(
            'The _ID field is auto-generated and cannot be set on create. Remove it from the payload.',
            domain: 'novamira-pro',
        ));
    }

    $writable_fields = array_values(array_filter(
        mcr_known_fields($factory),
        static fn(string $name): bool => $name !== '_ID',
    ));
    $field_error = mcr_validate_values($values, $writable_fields);
    if ($field_error !== null) {
        return $field_error;
    }

    $key_field = mcr_resolve_key_field($factory);
    if ($key_field !== null && array_key_exists($key_field, $values)) {
        $collision = $raw_db->query([$key_field => $values[$key_field]], 1, 0);
        if ($collision !== []) {
            return new WP_Error(
                'cct_record_key_collision',
                __('A record with this key value already exists.', domain: 'novamira-pro'),
                ['field' => $key_field, 'value' => $values[$key_field]],
            );
        }
    }

    $new_id = $raw_db->insert($values);
    if ($new_id === false) {
        return new WP_Error('cct_record_write_failed', __('Failed to insert record.', domain: 'novamira-pro'));
    }

    $rows = $raw_db->query(['_ID' => (int) $new_id], 1, 0);
    if ($rows === []) {
        return ['record' => array_merge(['_ID' => (int) $new_id], $values)];
    }

    $record = array_values($rows)[0];
    if (isset($record['_ID'])) {
        $record['_ID'] = (int) $record['_ID'];
    }

    return ['record' => $record];
}

/**
 * @param array{cct_slug: string, _ID?: int, key_field_value?: string, values: array<string, mixed>} $input
 * @return array{record: array<string, mixed>}|WP_Error
 */
function jetengine_edit_cct_record(array $input): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $input['cct_slug'];
    if ($slug === '') {
        return new WP_Error('invalid_input', __('cct_slug is required.', domain: 'novamira-pro'));
    }

    $factory = jetengine_cct_get_factory($slug);
    if ($factory === null) {
        return new WP_Error('cct_not_found', __('CCT not found.', domain: 'novamira-pro'));
    }

    /** @var mixed $raw_db */
    $raw_db = $factory->get_db();
    if (!$raw_db instanceof CctDB) {
        return new WP_Error('cct_db_unavailable', __('CCT DB layer unavailable.', domain: 'novamira-pro'));
    }

    $where = mcr_build_where($input, $factory);
    if ($where instanceof WP_Error) {
        return $where;
    }

    $existing = $raw_db->query($where, 1, 0);
    if ($existing === []) {
        return new WP_Error('cct_record_not_found', __('Record not found.', domain: 'novamira-pro'));
    }

    $values = $input['values'];

    $values_guard = mcr_guard_edit_values($values, $factory);
    if ($values_guard instanceof WP_Error) {
        return $values_guard;
    }

    $rekey_check = mcr_check_key_field_rekey($values, $existing[0], $factory, $raw_db);
    if ($rekey_check instanceof WP_Error) {
        return $rekey_check;
    }

    $raw_db->update($values, $where);

    $merge_where = $rekey_check !== null ? $rekey_check : $where;
    $merged_rows = $raw_db->query($merge_where, 1, 0);
    $record = $merged_rows !== [] ? array_values($merged_rows)[0] : [];
    if (isset($record['_ID'])) {
        $record['_ID'] = (int) $record['_ID'];
    }

    return ['record' => $record];
}

/**
 * Reject _ID and unknown keys in the edit values payload.
 *
 * @param array<string, mixed> $values
 */
function mcr_guard_edit_values(array $values, Factory $factory): ?WP_Error
{
    if (array_key_exists('_ID', $values)) {
        return new WP_Error('cct_id_immutable', __(
            'The _ID field is the primary key and cannot be modified via values. Remove it from the payload.',
            domain: 'novamira-pro',
        ));
    }
    // mcr_known_fields includes _ID for read; for write validation we exclude it.
    $writable = array_values(array_filter(
        mcr_known_fields($factory),
        static fn(string $name): bool => $name !== '_ID',
    ));
    return mcr_validate_values($values, $writable);
}

/**
 * When the agent changes the CCT key field, the post-write re-query with the
 * old WHERE returns 0 rows even though the write succeeded. Detect that case
 * and return the new WHERE so the caller can re-query against it. Also
 * surface a collision when another record already holds the new key value.
 *
 * Returns the new WHERE clause when a key change is needed, null when no
 * change is being made, or WP_Error on collision.
 *
 * @param array<string, mixed> $values
 * @param array<string, mixed> $existing_row
 * @return array<string, mixed>|null|WP_Error
 */
function mcr_check_key_field_rekey(
    array $values,
    array $existing_row,
    Factory $factory,
    CctDB $raw_db,
): array|null|WP_Error {
    $key_field = mcr_resolve_key_field($factory);
    if ($key_field === null || !array_key_exists($key_field, $values)) {
        return null;
    }

    /** @var mixed $new_key_value */
    $new_key_value = $values[$key_field];
    /** @var mixed $old_key_value */
    $old_key_value = $existing_row[$key_field] ?? null;
    if ($new_key_value === $old_key_value) {
        return null;
    }

    $collision = $raw_db->query([$key_field => $new_key_value], 1, 0);
    if ($collision !== [] && (int) ($collision[0]['_ID'] ?? 0) !== (int) ($existing_row['_ID'] ?? 0)) {
        return new WP_Error('cct_key_collision', sprintf(
            /* translators: 1: key field name, 2: new value. */
            __(
                'Cannot change "%1$s" to "%2$s" — another record already has that key. Resolve the conflict first.',
                domain: 'novamira-pro',
            ),
            $key_field,
            (string) $new_key_value,
        ));
    }

    return [$key_field => $new_key_value];
}

/**
 * @param array{cct_slug: string, _ID?: int, key_field_value?: string} $input
 * @return array{deleted: bool, _ID: int}|WP_Error
 */
function jetengine_delete_cct_record(array $input): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $input['cct_slug'];
    if ($slug === '') {
        return new WP_Error('invalid_input', __('cct_slug is required.', domain: 'novamira-pro'));
    }

    $factory = jetengine_cct_get_factory($slug);
    if ($factory === null) {
        return new WP_Error('cct_not_found', __('CCT not found.', domain: 'novamira-pro'));
    }

    /** @var mixed $raw_db */
    $raw_db = $factory->get_db();
    if (!$raw_db instanceof CctDB) {
        return new WP_Error('cct_db_unavailable', __('CCT DB layer unavailable.', domain: 'novamira-pro'));
    }

    $where = mcr_build_where($input, $factory);
    if ($where instanceof WP_Error) {
        return $where;
    }

    $existing = $raw_db->query($where, 1, 0);
    if ($existing === []) {
        return new WP_Error('cct_record_not_found', __('Record not found.', domain: 'novamira-pro'));
    }

    $first = array_values($existing)[0];
    $deleted_id = (int) ($first['_ID'] ?? 0);

    $raw_db->delete($where);

    return ['deleted' => true, '_ID' => $deleted_id];
}
