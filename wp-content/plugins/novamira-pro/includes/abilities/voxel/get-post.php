<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use Throwable;
use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read one Voxel post, optionally with its field values.
 */

wp_register_ability('novamira/voxel-get-post', [
    'label' => __('Get Voxel Post', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel post (listing): id, title, status, post type, author, slug, and creation and modified dates. Pass include "values" to also read the field values. Field values are capped per field: large values are truncated with a marker and the full byte size. Use max_field_bytes to change the cap, and fields to limit which field keys are read. Layout-only fields (ui-*) are skipped.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'The post id. Must belong to a Voxel-managed post type.',
            ],
            'include' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['values']],
                'default' => [],
                'description' => 'Pass ["values"] to embed field values. Omit for the summary only.',
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'default' => [],
                'description' => 'Optional field keys to limit the values read. Empty = all data fields.',
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 4096,
                'description' => 'Per-field byte cap for values (default 4096, minimum 1). Requests above the 65536 ceiling are clamped to it.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'post_type' => ['type' => 'string'],
            'author_id' => ['type' => 'integer'],
            'slug' => ['type' => 'string'],
            'date_gmt' => ['type' => 'string'],
            'modified_gmt' => ['type' => 'string'],
            'values' => [
                'type' => 'object',
                'description' => 'Map of field key to a value envelope. Present only when include contains "values".',
                'additionalProperties' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'read_ok' => ['type' => 'boolean'],
                        'value' => [
                            'type' => ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'],
                            'description' => 'The field value. Scalars for simple fields; a list for repeaters/galleries; an object (map) for structured fields like location and work-hours. Null when empty, unreadable, or omitted because it exceeds max_field_bytes.',
                        ],
                        'truncated' => ['type' => 'boolean'],
                        'full_size' => ['type' => 'integer'],
                    ],
                    'required' => ['type', 'read_ok', 'value', 'truncated', 'full_size'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['id', 'title', 'status', 'post_type', 'author_id', 'slug', 'date_gmt', 'modified_gmt'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_post',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Summary is always returned; field values are opt-in via include=["values"]. When a value is truncated, truncated=true and full_size gives the real byte size — raise max_field_bytes or read the raw meta via novamira/execute-php for the whole value.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_get_post(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $post_id = vx_coerce_int($input['post_id'] ?? null) ?? 0;
    $post = vx_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    /** @var mixed $wp_post */
    $wp_post = get_post($post_id);
    if (!$wp_post instanceof WP_Post) {
        return vx_not_found('post', (string) $post_id);
    }

    $out = [
        'id' => $wp_post->ID,
        'title' => $wp_post->post_title,
        'status' => $wp_post->post_status,
        'post_type' => (string) get_post_type($post_id),
        'author_id' => (int) $wp_post->post_author,
        'slug' => $wp_post->post_name,
        'date_gmt' => $wp_post->post_date_gmt,
        'modified_gmt' => $wp_post->post_modified_gmt,
    ];

    $include = vx_coerce_string_list($input['include'] ?? []);
    if (in_array('values', $include, strict: true)) {
        $max_bytes = vx_resolve_max_field_bytes($input['max_field_bytes'] ?? null);
        $only = vx_coerce_string_list($input['fields'] ?? []);
        $out['values'] = vx_read_post_values($post, $only, $max_bytes);
    }

    return $out;
}

/**
 * Read the field values of a Voxel post into per-field envelopes. Skips
 * layout-only fields (type prefix "ui-"). Each value read is isolated so a
 * single failing field never aborts the response.
 *
 * @param \Voxel\Post $post
 * @param list<string> $only field keys to restrict to (empty = all data fields)
 * @return array<string, array{type: string, read_ok: bool, value: mixed, truncated: bool, full_size: int}>
 */
function vx_read_post_values(object $post, array $only, int $max_bytes): array
{
    $out = [];
    foreach ($post->get_fields() as $field) {
        $type = $field->get_type();

        // Skip layout-only UI fields — they never hold a value.
        if (str_starts_with($type, 'ui-')) {
            continue;
        }
        $key = $field->get_key();
        if ($only !== [] && !in_array($key, $only, strict: true)) {
            continue;
        }

        $out[$key] = vx_field_value_envelope($field, $type, $max_bytes);
    }
    return $out;
}

/**
 * Read one field's value and wrap it in a size-capped envelope.
 *
 * @param \Voxel\Post_Types\Fields\Base_Post_Field $field
 * @return array{type: string, read_ok: bool, value: mixed, truncated: bool, full_size: int}
 */
function vx_field_value_envelope(object $field, string $type, int $max_bytes): array
{
    try {
        /** @var mixed $value */
        $value = $field->get_value();
        // Taxonomy get_value() returns \Voxel\Term objects that JSON-encode to
        // empty objects; expose the assigned term slugs instead (what create/edit
        // accept, so the value round-trips).
        if ($type === 'taxonomy') {
            $value = vx_terms_to_slugs($value);
        }
    } catch (Throwable) {
        return ['type' => $type, 'read_ok' => false, 'value' => null, 'truncated' => false, 'full_size' => 0];
    }

    $wrapped = vx_wrap_value($value, $max_bytes);
    return [
        'type' => $type,
        'read_ok' => true,
        'value' => $wrapped['value'],
        'truncated' => $wrapped['truncated'],
        'full_size' => $wrapped['full_size'],
    ];
}

/**
 * Convert a taxonomy field's value (a list of \Voxel\Term objects) to a plain
 * list of term slugs.
 *
 * @param mixed $value
 * @return list<string>
 */
function vx_terms_to_slugs(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $slugs = [];
    /** @var mixed $term */
    foreach ($value as $term) {
        if (!is_object($term) || !method_exists($term, 'get_slug')) {
            continue;
        }
        $slugs[] = (string) $term->get_slug();
    }
    return $slugs;
}

/**
 * Size-cap a raw field value. Strings are clipped in place; structured values
 * are measured as JSON and their body is omitted (value=null) when over the cap
 * rather than returning a corrupt half-serialized structure. Scalars pass through.
 *
 * @param mixed $value
 * @return array{value: mixed, truncated: bool, full_size: int}
 */
function vx_wrap_value(mixed $value, int $max_bytes): array
{
    if ($value === null) {
        return ['value' => null, 'truncated' => false, 'full_size' => 0];
    }
    if (is_string($value)) {
        return vx_truncate_field($value, $max_bytes);
    }

    $encoded = wp_json_encode($value);
    if ($encoded === false) {
        // Value cannot be represented as JSON (e.g. a resource); omit it rather
        // than embed something the outer serializer would choke on.
        return ['value' => null, 'truncated' => true, 'full_size' => 0];
    }
    $size = strlen($encoded);
    if (is_scalar($value) || $size <= $max_bytes) {
        return ['value' => $value, 'truncated' => false, 'full_size' => $size];
    }
    return ['value' => null, 'truncated' => true, 'full_size' => $size];
}
