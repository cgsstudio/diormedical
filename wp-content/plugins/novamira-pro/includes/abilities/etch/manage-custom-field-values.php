<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;
use WP_REST_Request;

/**
 * Abilities: read, set and delete the VALUES of Etch native custom fields on a
 * single post — the per-post data behind the {etch.<key>} bindings, as opposed
 * to the field DEFINITIONS (the etch_cfs groups) managed in
 * manage-custom-fields.php.
 *
 * These dispatch to Etch's own CustomFieldValues REST routes
 * (etch-api/cms/post/<id>/field-values, added in Etch 1.5.0), so Etch resolves
 * each key against the field groups ASSIGNED to the post, validates the value
 * by field type, and writes BOTH the value meta and the marker meta
 * (_<key> = etch_field_group_<gid>) that makes {etch.<key>} resolve in dynamic
 * content. Registered only when Etch is >=
 * NOVAMIRA_PRO_ETCH_CF_VALUES_MIN_VERSION — the loader gates the require on
 * etch_supports_field_values(); on older Etch these abilities do not exist, and
 * the existing definition abilities keep working unchanged.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Dispatch an internal REST request to an Etch field-values route and return
 * the decoded data array, or a WP_Error. rest_do_request does not catch
 * Throwables a route handler may raise, so wrap it (same guard as the query
 * abilities).
 *
 * @param array<string, mixed> $body JSON body for PUT requests (empty otherwise).
 * @return array<array-key, mixed>|WP_Error
 */
function etch_cf_values_dispatch(string $method, string $route, array $body = []): array|WP_Error
{
    $request = new WP_REST_Request($method, $route);
    if ($body !== []) {
        $encoded = wp_json_encode($body);
        if (!is_string($encoded)) {
            return etch_error('etch_invalid_input', __(
                'The request body could not be encoded.',
                domain: 'novamira-pro',
            ));
        }
        $request->set_body($encoded);
        $request->set_header('Content-Type', value: 'application/json');
    }

    try {
        $response = rest_do_request($request);
    } catch (\Throwable $e) {
        return etch_error('etch_cf_value_failed', $e->getMessage());
    }

    if ($response->is_error()) {
        $error = $response->as_error();
        $message = $error instanceof WP_Error
            ? $error->get_error_message()
            : __('The custom field value request was rejected by Etch.', domain: 'novamira-pro');

        return etch_error('etch_cf_value_rejected', $message);
    }

    /** @var mixed $data */
    $data = $response->get_data();

    return is_array($data) ? $data : [];
}

/**
 * Flatten Etch's nested { groups: { gid: { label, fields: { key: {...} } } } }
 * payload into a compact list of { key, label, type, group_id, value }.
 *
 * @param array<array-key, mixed> $data
 * @return list<array{key: string, label: string, type: string, group_id: string, value: mixed}>
 */
function etch_cf_values_flatten(array $data): array
{
    /** @var mixed $groups */
    $groups = $data['groups'] ?? [];
    if (!is_array($groups)) {
        return [];
    }

    $out = [];
    foreach (array_keys($groups) as $gid) {
        /** @var mixed $group */
        $group = $groups[$gid];
        if (!is_array($group)) {
            continue;
        }
        /** @var mixed $fields */
        $fields = $group['fields'] ?? [];
        if (!is_array($fields)) {
            continue;
        }
        foreach (array_keys($fields) as $key) {
            /** @var mixed $field */
            $field = $fields[$key];
            if (is_array($field)) {
                $out[] = etch_cf_value_entry((string) $gid, $field);
            }
        }
    }

    return $out;
}

/**
 * @param array<array-key, mixed> $field
 * @return array{key: string, label: string, type: string, group_id: string, value: mixed}
 */
function etch_cf_value_entry(string $group_id, array $field): array
{
    /** @var mixed $key */
    $key = $field['key'] ?? '';
    /** @var mixed $label */
    $label = $field['label'] ?? '';
    /** @var mixed $type */
    $type = $field['type'] ?? '';

    return [
        'key' => is_string($key) ? $key : '',
        'label' => is_string($label) ? $label : '',
        'type' => is_string($type) ? $type : '',
        'group_id' => $group_id,
        'value' => $field['value'] ?? null,
    ];
}

/**
 * Validate and return a positive post id from input.
 *
 * @param array<array-key, mixed> $input
 * @return int|WP_Error
 */
function etch_cf_value_post_id(array $input): int|WP_Error
{
    /** @var mixed $raw */
    $raw = $input['post_id'] ?? null;
    $post_id = 0;
    if (is_int($raw)) {
        $post_id = $raw;
    }
    if (is_string($raw) && ctype_digit($raw)) {
        $post_id = (int) $raw;
    }
    if ($post_id <= 0) {
        return etch_error('etch_invalid_input', __('A positive "post_id" is required.', domain: 'novamira-pro'));
    }

    return $post_id;
}

/**
 * Restrict a flattened field list to a caller-supplied set of keys.
 *
 * @param list<array{key: string, label: string, type: string, group_id: string, value: mixed}> $fields
 * @return list<array{key: string, label: string, type: string, group_id: string, value: mixed}>
 */
function etch_cf_values_filter(array $fields, mixed $keys): array
{
    if (!is_array($keys) || $keys === []) {
        return $fields;
    }

    $want = [];
    foreach (array_keys($keys) as $i) {
        /** @var mixed $k */
        $k = $keys[$i];
        if (is_string($k)) {
            $want[$k] = true;
        }
    }
    if ($want === []) {
        return $fields;
    }

    return array_values(array_filter($fields, static fn(array $f): bool => isset($want[$f['key']])));
}

/**
 * The route base for a post's field-values endpoint.
 */
function etch_cf_values_route(int $post_id): string
{
    return '/etch-api/cms/post/' . $post_id . '/field-values';
}

// ---------------------------------------------------------------------------
// get-field-values
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-field-values', [
    'label' => __('Get Etch Field Values', domain: 'novamira-pro'),
    'description' => __(
        'Reads the values of the Etch custom fields applied to a post (the per-post data behind {etch.<key>} bindings). Returns a compact list of { key, label, type, group_id, value } for every field whose group is assigned to the post; pass keys to restrict it. Requires Etch 1.5.0+. Use novamira/etch-get-field-group for the field definitions, this for their stored values.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The post whose field values to read.'],
            'keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional: restrict the result to these field keys.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'group_id' => ['type' => 'string'],
                        'value' => ['description' => 'The stored value (string, number, boolean or null).'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_field_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Per-post custom field VALUES (not definitions). Only fields whose group is assigned to the post are returned. Etch 1.5.0+ only.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{post_id: int, fields: list<array{key: string, label: string, type: string, group_id: string, value: mixed}>}|WP_Error
 */
function etch_get_field_values(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post_id = etch_cf_value_post_id($input);
    if ($post_id instanceof WP_Error) {
        return $post_id;
    }

    $data = etch_cf_values_dispatch('GET', etch_cf_values_route($post_id));
    if ($data instanceof WP_Error) {
        return $data;
    }

    $fields = etch_cf_values_filter(etch_cf_values_flatten($data), $input['keys'] ?? null);

    return ['post_id' => $post_id, 'fields' => $fields];
}

// ---------------------------------------------------------------------------
// set-field-values
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-set-field-values', [
    'label' => __('Set Etch Field Values', domain: 'novamira-pro'),
    'description' => __(
        'Sets the values of Etch custom fields on a post in one call. Provide post_id and values ({ "<field key>": value, ... }). Etch resolves each key against the field groups assigned to the post, validates the value by field type, and writes both the value and the marker meta so {etch.<key>} resolves. A key not in any group assigned to the post is rejected. Requires Etch 1.5.0+. Returns the resulting field list.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The post to write the values on.'],
            'values' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Map of field key => value. Values are validated by Etch against the field type.',
            ],
        ],
        'required' => ['post_id', 'values'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'group_id' => ['type' => 'string'],
                        'value' => ['description' => 'The stored value after the write.'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_set_field_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Writes per-post values resolving as {etch.<key>}; the field group must be assigned to the post (by post type or post id) or the key is rejected (whole call, atomic). Etch sanitizes each value by type — an out-of-type value (e.g. a select value not in the list, a malformed date) is stored empty rather than rejected; only a non-canonical boolean rejects. Etch 1.5.0+ only.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{post_id: int, fields: list<array{key: string, label: string, type: string, group_id: string, value: mixed}>}|WP_Error
 */
function etch_set_field_values(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post_id = etch_cf_value_post_id($input);
    if ($post_id instanceof WP_Error) {
        return $post_id;
    }

    /** @var mixed $values */
    $values = $input['values'] ?? null;
    if (!is_array($values) || $values === []) {
        return etch_error('etch_invalid_input', __(
            'A non-empty "values" object ({ "<field key>": value }) is required.',
            domain: 'novamira-pro',
        ));
    }

    $data = etch_cf_values_dispatch('PUT', etch_cf_values_route($post_id), ['values' => $values]);
    if ($data instanceof WP_Error) {
        return $data;
    }

    return ['post_id' => $post_id, 'fields' => etch_cf_values_flatten($data)];
}

// ---------------------------------------------------------------------------
// delete-field-value
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-field-value', [
    'label' => __('Delete Etch Field Value', domain: 'novamira-pro'),
    'description' => __(
        'Removes the stored value of one Etch custom field on a post (both the value and the {etch.<key>} marker meta). The field DEFINITION is untouched. Provide post_id and key. Requires Etch 1.5.0+. Returns post_id, key and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'key' => ['type' => 'string', 'description' => 'The field key whose value to remove.'],
        ],
        'required' => ['post_id', 'key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'key' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_field_value',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE for the stored value only — the field definition stays. The group must be assigned to the post. Etch 1.5.0+ only.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{post_id: int, key: string, deleted: bool}|WP_Error
 */
function etch_delete_field_value(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post_id = etch_cf_value_post_id($input);
    if ($post_id instanceof WP_Error) {
        return $post_id;
    }

    $key = etch_cf_field_key_or_error($input['key'] ?? null);
    if ($key instanceof WP_Error) {
        return $key;
    }

    $data = etch_cf_values_dispatch('DELETE', etch_cf_values_route($post_id) . '/' . $key);
    if ($data instanceof WP_Error) {
        return $data;
    }

    return ['post_id' => $post_id, 'key' => $key, 'deleted' => true];
}
