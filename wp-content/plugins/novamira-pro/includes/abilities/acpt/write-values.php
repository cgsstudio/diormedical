<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-write-values', [
    'label' => __('Write ACPT Values', domain: 'novamira-pro'),
    'description' => __(
        'Writes ACPT meta field values for a target (post, term, user, comment, or option-page). Pass target {type, id}, and a values map: {box_name: {field_name: value}} using the box and field names exactly as stored (see novamira/acpt-get-field-group-schema). Each write uses save_acpt_meta_field_value() so ACPT hooks fire and type-specific shapes apply (call novamira/acpt-get-field-type-schema for the value shape of each type, e.g. Currency {amount, unit}). Returns a per-field results array with status ok|error. Target types: post (id=post_id int), term (id=term_id int), user (id=user_id int), comment (id=comment_id int), option-page (id=menu_slug string). Values are stored as native WordPress meta (post/term/user/comment meta, or options for option pages).',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => [
                'type' => 'object',
                'properties' => [
                    'type' => [
                        'type' => 'string',
                        'enum' => ['post', 'term', 'user', 'comment', 'option-page'],
                    ],
                    'id' => ['type' => ['integer', 'string']],
                ],
                'required' => ['type', 'id'],
                'additionalProperties' => false,
            ],
            'values' => [
                'type' => 'object',
                'description' => 'Nested map: {box_name: {field_name: value}}. Each entry writes one field.',
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
                        'box_name' => ['type' => 'string'],
                        'field_name' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['ok', 'error']],
                        'error' => ['type' => 'string'],
                    ],
                    'required' => ['box_name', 'field_name', 'status'],
                ],
            ],
        ],
        'required' => ['results'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_write_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Writes ACPT field values. Values are nested {box_name: {field_name: value}}. A failure on one field does not block the rest; an error means ACPT found no field under that box/field name or rejected the value shape for its type. For option-page targets pass the menu_slug string as target.id.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{target: array{type: string, id: int|string}, values: array<string, array<string, mixed>>} $args
 * @return array{results: list<array<string, mixed>>}|WP_Error
 */
function acpt_write_values(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $target = $args['target'];
    $resolved = acpt_normalize_target($target);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }

    $belongs_to = $resolved['belongs_to'];
    $target_id = $resolved['id'];

    $values_map = $args['values'];
    if ($values_map === []) {
        return ['results' => []];
    }

    $results = [];
    foreach ($values_map as $box_name => $field_map) {
        /** @var mixed $value */
        foreach ($field_map as $field_name => $value) {
            $write_args = acpt_build_write_args($belongs_to, $target_id, $box_name, $field_name, $value);
            $ok = acpt_write_single_field($write_args);
            if (!$ok) {
                $results[] = [
                    'box_name' => $box_name,
                    'field_name' => $field_name,
                    'status' => 'error',
                    'error' => 'save_acpt_meta_field_value returned false — field not found or value invalid',
                ];
                continue;
            }
            $results[] = ['box_name' => $box_name, 'field_name' => $field_name, 'status' => 'ok'];
        }
    }

    return ['results' => $results];
}

/**
 * Build the args array for save_acpt_meta_field_value() based on target type.
 *
 * @param int|string $target_id
 * @return array<string, mixed>
 */
function acpt_build_write_args(
    string $belongs_to,
    int|string $target_id,
    string $box_name,
    string $field_name,
    mixed $value,
): array {
    return array_merge(
        ['box_name' => $box_name, 'field_name' => $field_name, 'value' => $value],
        acpt_target_location_args($belongs_to, $target_id),
    );
}

/**
 * Call ACPT's save_acpt_meta_field_value() with a try/catch.
 *
 * @param array<string, mixed> $write_args
 */
function acpt_write_single_field(array $write_args): bool
{
    if (!function_exists('save_acpt_meta_field_value')) {
        return false;
    }
    try {
        return save_acpt_meta_field_value($write_args);
    } catch (Throwable) {
        return false;
    }
}
