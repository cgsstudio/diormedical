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

wp_register_ability('novamira/acpt-read-values', [
    'label' => __('Read ACPT Values', domain: 'novamira-pro'),
    'description' => __(
        'Reads ACPT meta field values for a target (post, term, user, comment, or option-page). Requires target {type, id} and the meta group name. Pass fields[] as [{box_name, field_name}] pairs to read specific fields; omit to read all fields in all boxes of the group. Returns a values map nested by box_name → field_name → value. Target types: post (id=post_id int), term (id=term_id int), user (id=user_id int), comment (id=comment_id int), option-page (id=menu_slug string).',
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
                        'description' => 'Target object type.',
                    ],
                    'id' => [
                        'type' => ['integer', 'string'],
                        'description' => 'Target object ID. For option-page pass the menu_slug string.',
                    ],
                ],
                'required' => ['type', 'id'],
                'additionalProperties' => false,
            ],
            'group_name' => [
                'type' => 'string',
                'description' => 'The ACPT meta group name to read from.',
            ],
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'box_name' => ['type' => 'string'],
                        'field_name' => ['type' => 'string'],
                    ],
                    'required' => ['box_name', 'field_name'],
                    'additionalProperties' => false,
                ],
                'description' => 'Optional list of {box_name, field_name} pairs to read. When omitted, reads all fields in the group.',
            ],
        ],
        'required' => ['target', 'group_name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => ['type' => 'object'],
            'group_name' => ['type' => 'string'],
            'values' => ['type' => 'object'],
        ],
        'required' => ['target', 'group_name', 'values'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_read_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reads ACPT field values. Values are keyed by box_name → field_name. For post targets pass target.id as the integer post ID. For option-page targets pass the menu_slug string. Internally uses get_acpt_field() for individual fields or iterates the group schema when reading all fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{target: array{type: string, id: int|string}, group_name: string, fields?: list<array{box_name: string, field_name: string}>} $args
 * @return array{target: array<string, mixed>, group_name: string, values: array<string, mixed>|\stdClass}|WP_Error
 */
function acpt_read_values(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $target = $args['target'];
    $group_name = trim($args['group_name']);
    if ($group_name === '') {
        return new WP_Error('acpt_invalid_input', __('group_name is required.', domain: 'novamira-pro'));
    }

    $resolved = acpt_normalize_target($target);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }

    $belongs_to = $resolved['belongs_to'];
    $target_id = $resolved['id'];

    // Determine which fields to read.
    $requested_fields = $args['fields'] ?? null;

    if ($requested_fields === null) {
        $resolved_fields = acpt_resolve_all_fields_for_group($group_name);
        if ($resolved_fields instanceof WP_Error) {
            return $resolved_fields;
        }
        $requested_fields = $resolved_fields;
    }

    $values = acpt_collect_field_values($belongs_to, $target_id, $requested_fields);

    return [
        'target' => $target,
        'group_name' => $group_name,
        'values' => $values === [] ? new \stdClass() : $values,
    ];
}

/**
 * Resolve all {box_name, field_name} pairs for a given meta group name.
 *
 * @return list<array{box_name: string, field_name: string}>|WP_Error
 */
function acpt_resolve_all_fields_for_group(string $group_name): array|WP_Error
{
    $rows = \ACPT\Core\Repository\MetaRepository::get(['groupName' => $group_name]);
    if ($rows === []) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: group name. */
            __('ACPT field group "%s" not found.', domain: 'novamira-pro'),
            $group_name,
        ));
    }
    $fields = [];
    foreach ($rows[0]->getBoxes() as $box) {
        foreach ($box->getFields() as $field) {
            $fields[] = ['box_name' => $box->getName(), 'field_name' => $field->getName()];
        }
    }
    return $fields;
}

/**
 * Collect field values for a list of {box_name, field_name} pairs.
 *
 * @param list<array{box_name: string, field_name: string}> $requested_fields
 * @param int|string $target_id
 * @return array<string, array<string, mixed>>
 */
function acpt_collect_field_values(string $belongs_to, int|string $target_id, array $requested_fields): array
{
    $values = [];
    foreach ($requested_fields as $field_spec) {
        $box_name = $field_spec['box_name'];
        $field_name = $field_spec['field_name'];
        if ($box_name === '' || $field_name === '') {
            continue;
        }
        $get_args = acpt_build_read_args($belongs_to, $target_id, $box_name, $field_name);
        /** @var mixed $value */
        $value = acpt_read_single_field($get_args);
        if (!isset($values[$box_name])) {
            $values[$box_name] = [];
        }
        $values[$box_name][$field_name] = $value;
    }
    return $values;
}

/**
 * Build the args array for get_acpt_field() based on target type.
 *
 * @param int|string $target_id
 * @return array<string, mixed>
 */
function acpt_build_read_args(string $belongs_to, int|string $target_id, string $box_name, string $field_name): array
{
    return array_merge(
        ['box_name' => $box_name, 'field_name' => $field_name, 'return' => 'raw'],
        acpt_target_location_args($belongs_to, $target_id),
    );
}

/**
 * Call ACPT's get_acpt_field() with a try/catch.
 *
 * @param array<string, mixed> $get_args
 */
function acpt_read_single_field(array $get_args): mixed
{
    if (!function_exists('get_acpt_field')) {
        return null;
    }
    try {
        return get_acpt_field($get_args);
    } catch (Throwable) {
        return null;
    }
}
