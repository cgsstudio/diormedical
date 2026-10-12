<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-read-values', [
    'label' => __('Read Meta Box Values', domain: 'novamira-pro'),
    'description' => __(
        'Reads Meta Box field values from any target: post (target.type=post, target.id=<post_id>), term (type=term, id=<term_id>), user (type=user, id=<user_id>), comment (type=comment, id=<comment_id>), or settings page (type=settings, id=<settings_page_slug>). Pass fields[] to read specific field IDs; omit to read all Meta Box metadata on the target. Returns a values map keyed by field ID.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => [
                'type' => 'object',
                'properties' => [
                    'type' => [
                        'type' => 'string',
                        'enum' => ['post', 'term', 'user', 'comment', 'settings'],
                        'description' => 'Target object type.',
                    ],
                    'id' => [
                        'type' => ['integer', 'string'],
                        'description' => 'Target object ID or slug. For settings targets, pass the settings page slug.',
                    ],
                ],
                'required' => ['type', 'id'],
                'additionalProperties' => false,
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional list of field IDs to read. When omitted, all meta keys are returned.',
            ],
            'field_group_key' => [
                'type' => 'string',
                'description' => 'Optional: scope the read to fields belonging to a specific field group.',
            ],
        ],
        'required' => ['target'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => ['type' => 'object'],
            'values' => ['type' => 'object'],
        ],
        'required' => ['target', 'values'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_read_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reads Meta Box field values. For post targets pass target.id as the integer post ID. For settings targets pass the settings page slug as target.id. For term/user/comment targets pass the respective integer ID. fields[] filters to specific field IDs — use field IDs exactly as defined in the group (the id key in the field definition). When field_group_key is supplied, only fields belonging to that group are available.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     target: array{type: string, id: int|string},
 *     fields?: list<string>,
 *     field_group_key?: string,
 * } $args
 * @return array{target: array<string, mixed>, values: array<string, mixed>|\stdClass}|WP_Error
 */
function metabox_read_values(array $args): array|WP_Error
{
    $guard = mb_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $target = $args['target'];
    $normalized = mb_normalize_target($target);
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }

    $object_id = $normalized['object_id'];
    $object_type = $normalized['object_type'];

    $ext_guard = mb_target_extension_guard($object_type);
    if ($ext_guard !== null) {
        return $ext_guard;
    }

    $requested_fields = $args['fields'] ?? null;
    $group_key = $args['field_group_key'] ?? null;

    // If a field_group_key is supplied, resolve which field IDs belong to it.
    if ($group_key !== null && $requested_fields === null) {
        $group = mb_get_field_group($group_key);
        if ($group === null) {
            return new WP_Error('metabox_not_found', sprintf(
                /* translators: %s: field group key. */
                __('Field group "%s" not found.', domain: 'novamira-pro'),
                $group_key,
            ));
        }
        /** @var mixed $fields_raw */
        $fields_raw = $group['fields'] ?? [];
        $requested_fields = mb_extract_field_ids($fields_raw);
    }

    $rwmb_args = ['object_type' => $object_type];

    if ($requested_fields !== null) {
        $values = [];
        foreach ($requested_fields as $field_id) {
            if ($field_id === '') {
                continue;
            }
            /** @var mixed $field_val */
            $field_val = rwmb_meta($field_id, $rwmb_args, $object_id);
            $values[$field_id] = $field_val;
        }
        return [
            'target' => $target,
            'values' => $values === [] ? new \stdClass() : $values,
        ];
    }

    // Read all meta via get_metadata and filter by rwmb registered fields.
    $values = mb_read_all_meta($object_id, $object_type);

    return [
        'target' => $target,
        'values' => $values === [] ? new \stdClass() : $values,
    ];
}

/**
 * Extract flat list of field IDs from a fields array (skipping group sub-fields).
 *
 * Handles both field shapes that flow through here: raw `rwmb_meta_boxes`
 * filter rows carry the meta-key slug in `id` (and the human label in
 * `name`), while rows already translated by `mb_translate_fields_to_output`
 * — e.g. the `fields` of `mb_get_field_group()` — carry the slug in `name`
 * and have NO `id` key at all. Reading only `id` made every
 * `field_group_key`-scoped read resolve zero fields and return `values: {}`.
 *
 * @param mixed $fields_raw
 * @return list<string>
 */
function mb_extract_field_ids(mixed $fields_raw): array
{
    if (!is_array($fields_raw)) {
        return [];
    }
    $ids = [];
    /** @var mixed $field */
    foreach ($fields_raw as $field) {
        if (!is_array($field)) {
            continue;
        }
        /** @var mixed $id_raw */
        $id_raw = $field['id'] ?? $field['name'] ?? null;
        if (is_string($id_raw) && $id_raw !== '') {
            $ids[] = $id_raw;
        }
    }
    return $ids;
}

/**
 * Read all postmeta / termmeta / usermeta / commentmeta for a target by
 * cross-referencing with registered Meta Box field IDs so we don't return
 * internal WP meta keys.
 *
 * @return array<string, mixed>
 */
function mb_read_all_meta(int|string $object_id, string $object_type): array
{
    // Collect all registered field IDs from the rwmb_meta_boxes filter.
    /** @var mixed $registered */
    $registered = apply_filters('rwmb_meta_boxes', []);
    if (!is_array($registered)) {
        return [];
    }

    $rwmb_args = ['object_type' => $object_type];
    $values = [];

    /** @var mixed $box */
    foreach ($registered as $box) {
        if (!is_array($box)) {
            continue;
        }
        /** @var mixed $fields_raw */
        $fields_raw = $box['fields'] ?? [];
        $ids = mb_extract_field_ids($fields_raw);
        foreach ($ids as $field_id) {
            if (array_key_exists($field_id, $values)) {
                continue;
            }
            $values[$field_id] = rwmb_meta($field_id, $rwmb_args, $object_id);
        }
    }

    return $values;
}
