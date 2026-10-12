<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-write-values', [
    'label' => __('Write Meta Box Values', domain: 'novamira-pro'),
    'description' => __(
        'Writes a batch of Meta Box field values to a target (post, term, user, comment, or settings page). Pass target with type and id, and a values map keyed by field ID. For settings targets pass type=settings and id=<settings_page_slug>. Each value is written via rwmb_set_meta() for posts, or the appropriate meta update function for other object types. Optionally pass field_group_key (same as metabox-read-values) to fail fast when a values key does not belong to that group. Returns a results array with per-field status.',
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
                    ],
                    'id' => ['type' => ['integer', 'string']],
                ],
                'required' => ['type', 'id'],
                'additionalProperties' => false,
            ],
            'values' => [
                'type' => 'object',
                'description' => 'Map of field_id to value to write.',
            ],
            'field_group_key' => [
                'type' => 'string',
                'description' => 'Optional: validate that every key in `values` belongs to this field group BEFORE writing anything. Unknown field IDs fail the whole call instead of silently writing stray meta keys. Same key accepted by metabox-read-values.',
            ],
        ],
        'required' => ['target', 'values'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'results' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'field' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['ok', 'error']],
                        'error' => ['type' => 'string'],
                    ],
                    'required' => ['field', 'status'],
                ],
            ],
        ],
        'required' => ['results'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_write_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Writes Meta Box field values to a target. Use field IDs exactly as defined in the field group (the id key in the field definition). For post targets the write uses rwmb_set_meta() so all MB hooks fire. For settings targets the value is written to the option key registered by the settings page. Writes are per-field; a failure on one field does not block the remaining fields.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     target: array{type: string, id: int|string},
 *     values: array<string, mixed>,
 *     field_group_key?: string,
 * } $args
 * @return array{results: list<array<string, mixed>>}|WP_Error
 */
function metabox_write_values(array $args): array|WP_Error
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

    $values = $args['values'];
    if ($values === []) {
        return ['results' => []];
    }

    // Optional fail-fast scope check: with field_group_key, every key in
    // `values` must belong to the group — a typo'd field ID would otherwise
    // silently write a stray meta key and report "ok".
    $group_key = $args['field_group_key'] ?? null;
    if ($group_key !== null) {
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
        $known = mb_extract_field_ids($fields_raw);
        $unknown = array_diff(array_map('strval', array_keys($values)), $known);
        if ($unknown !== []) {
            return new WP_Error('metabox_invalid_input', sprintf(
                /* translators: 1: field group key, 2: comma-separated field IDs. */
                __('Fields not defined in group "%1$s": %2$s. Nothing was written.', domain: 'novamira-pro'),
                $group_key,
                implode(', ', $unknown),
            ));
        }
    }

    $results = [];
    /** @var mixed $value */
    foreach ($values as $field_id => $value) {
        if ($field_id === '') {
            $results[] = [
                'field' => $field_id,
                'status' => 'error',
                'error' => 'field ID must be a non-empty string',
            ];
            continue;
        }

        $write_result = mb_write_single_value($object_id, $object_type, $field_id, $value);
        if ($write_result instanceof WP_Error) {
            $results[] = ['field' => $field_id, 'status' => 'error', 'error' => $write_result->get_error_message()];
            continue;
        }

        $results[] = ['field' => $field_id, 'status' => 'ok'];
    }

    return ['results' => $results];
}

/**
 * Write a single value to the appropriate meta storage based on object type.
 *
 * @param int|string $object_id
 */
function mb_write_single_value(int|string $object_id, string $object_type, string $field_id, mixed $value): ?WP_Error
{
    if ($object_type === 'post') {
        // Try rwmb_set_meta first (the official write API — fires all MB hooks).
        // rwmb_set_meta silently no-ops when the field is not registered in the
        // current request (e.g. DB-stored groups only active in admin context).
        // Verify the write succeeded by reading back, then fall back to
        // update_post_meta which always works.
        rwmb_set_meta((int) $object_id, $field_id, $value);
        /** @var mixed $stored */
        $stored = rwmb_meta($field_id, ['object_type' => 'post'], $object_id);
        $target_scalar = !is_array($value) && !is_object($value);
        $target_match = $target_scalar
            ? (string) $stored === (string) $value
            : serialize($stored) === serialize($value);
        if (!$target_match) {
            // Fallback: direct meta update (no MB hooks but reliable).
            update_post_meta((int) $object_id, $field_id, $value);
        }
        return null;
    }

    if ($object_type === 'term') {
        update_term_meta((int) $object_id, $field_id, $value);
        return null;
    }

    if ($object_type === 'user') {
        update_user_meta((int) $object_id, $field_id, $value);
        return null;
    }

    if ($object_type === 'comment') {
        update_comment_meta((int) $object_id, $field_id, $value);
        return null;
    }

    if ($object_type === 'setting') {
        // Settings pages store their values in a WP option keyed by option_name
        // (defaults to the settings page id). Get the full option, merge the
        // field value in, then update.
        $option_name = (string) $object_id;
        /** @var mixed $current */
        $current = get_option($option_name, []);
        $option = is_array($current) ? $current : [];
        $option[$field_id] = $value;
        update_option($option_name, $option);
        return null;
    }

    return new WP_Error('metabox_target_invalid', sprintf(
        /* translators: %s: object type. */
        __('Unsupported object_type "%s" for write.', domain: 'novamira-pro'),
        $object_type,
    ));
}
