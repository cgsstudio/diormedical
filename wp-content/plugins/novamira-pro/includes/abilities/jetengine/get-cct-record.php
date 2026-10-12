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

wp_register_ability('novamira/jetengine-get-cct-record', [
    'label' => __('Get JetEngine CCT Record', domain: 'novamira-pro'),
    'description' => __(
        'Fetch a single CCT record by _ID (auto-increment primary key) or by key_field_value (the value of the field marked is_key_field). At least one of the two selectors is required. If the CCT has no key field, only _ID is accepted.',
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
            'record' => ['type' => 'object'],
        ],
        'required' => ['record'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_get_cct_record',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

/**
 * Locate the name of the field flagged is_key_field on a CCT factory, or null when none exists.
 */
function gcr_resolve_key_field(Factory $factory): ?string
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
 * @param array{cct_slug: string, _ID?: int, key_field_value?: string} $input
 * @return array{record: array<string, mixed>}|WP_Error
 */
function jetengine_get_cct_record(array $input): array|WP_Error
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

    $where = gcr_build_where($input, $factory);
    if ($where instanceof WP_Error) {
        return $where;
    }

    $rows = $raw_db->query($where, 1, 0);
    if ($rows === []) {
        return new WP_Error('cct_record_not_found', __('Record not found.', domain: 'novamira-pro'));
    }

    $row = array_values($rows)[0];
    if (isset($row['_ID'])) {
        $row['_ID'] = (int) $row['_ID'];
    }

    return ['record' => $row];
}

/**
 * Build the where clause from input selectors, or return WP_Error on validation failure.
 * Prefers _ID when both selectors are present.
 *
 * @param array{cct_slug: string, _ID?: int, key_field_value?: string} $input
 * @return array<string, mixed>|WP_Error
 */
function gcr_build_where(array $input, Factory $factory): array|WP_Error
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

    $key_field = gcr_resolve_key_field($factory);
    if ($key_field === null) {
        return new WP_Error('cct_no_key_field', __(
            'CCT has no field flagged is_key_field; use _ID instead.',
            domain: 'novamira-pro',
        ));
    }

    return [$key_field => $input['key_field_value']];
}
