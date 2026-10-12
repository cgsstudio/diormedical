<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-list-fieldgroups', [
    'label' => __('List ACF Field Groups', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight overview of all ACF field groups: key, id, title, active flag, source (db/json/php), editable flag, fields count, and a one-line location summary. Use to discover what is available before fetching a full schema.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'filter' => [
                'type' => 'object',
                'properties' => [
                    'post_type' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field_groups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'id' => ['type' => ['integer', 'null']],
                        'title' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'source' => ['type' => 'string', 'enum' => ['db', 'json', 'php']],
                        'editable' => ['type' => 'boolean'],
                        'fields_count' => ['type' => 'integer'],
                        'location_summary' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'required' => ['field_groups'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_list_fieldgroups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to discover field groups available on the site. The output is intentionally minimal; use novamira/acf-get-fieldgroup-schema with the returned key to read the full schema. editable=false marks PHP-registered groups, which cannot be modified at runtime. active=false marks dormant groups whose location rules are not evaluated.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{filter?: array{post_type?: string}} $args
 * @return array{field_groups: list<array<string, mixed>>}|WP_Error
 */
function acf_list_fieldgroups(array $args): array|WP_Error
{
    if (!class_exists('ACF')) {
        return new WP_Error('acf_not_active', 'ACF is not active');
    }

    $post_type = $args['filter']['post_type'] ?? null;
    $filter_args = is_string($post_type) ? ['post_type' => $post_type] : [];

    $groups = acf_get_field_groups($filter_args === [] ? false : $filter_args);
    $out = [];

    foreach ($groups as $g) {
        $entry = acf_fieldgroup_overview($g);
        if ($entry !== null) {
            $out[] = $entry;
        }
    }

    return ['field_groups' => $out];
}

/**
 * @return array<string, mixed>|null
 */
function acf_fieldgroup_overview(mixed $g): ?array
{
    if (!is_array($g)) {
        return null;
    }
    /** @var mixed $key */
    $key = $g['key'] ?? null;
    if (!is_string($key) || $key === '') {
        return null;
    }

    /** @var mixed $local */
    $local = $g['local'] ?? null;
    $source = match ($local) {
        'php' => 'php',
        'json' => 'json',
        default => 'db',
    };
    $fields = acf_get_fields($key);
    $count = is_array($fields) ? count($fields) : 0;

    /** @var mixed $id */
    $id = $g['ID'] ?? null;
    /** @var mixed $title */
    $title = $g['title'] ?? null;
    /** @var mixed $active */
    $active = $g['active'] ?? null;

    return [
        'key' => $key,
        'id' => is_int($id) ? $id : null,
        'title' => is_string($title) ? $title : '',
        'active' => $active === null ? true : (bool) (is_scalar($active) ? $active : false),
        'source' => $source,
        'editable' => $source !== 'php',
        'fields_count' => $count,
        'location_summary' => acf_summarize_location($g['location'] ?? []),
    ];
}

function acf_summarize_location(mixed $location): string
{
    if (!is_array($location) || $location === []) {
        return '';
    }
    $or_parts = [];
    /** @var mixed $and_group */
    foreach ($location as $and_group) {
        $and_parts = acf_summarize_and_group($and_group);
        if ($and_parts !== '') {
            $or_parts[] = $and_parts;
        }
    }
    return implode(' OR ', $or_parts);
}

function acf_summarize_and_group(mixed $and_group): string
{
    if (!is_array($and_group)) {
        return '';
    }
    $parts = [];
    /** @var mixed $rule */
    foreach ($and_group as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        /** @var mixed $param_raw */
        $param_raw = $rule['param'] ?? null;
        /** @var mixed $op_raw */
        $op_raw = $rule['operator'] ?? null;
        /** @var mixed $value_raw */
        $value_raw = $rule['value'] ?? null;
        $param = is_string($param_raw) ? $param_raw : '?';
        $op = is_string($op_raw) ? $op_raw : '==';
        $value = $value_raw === null ? '?' : (string) $value_raw;
        $parts[] = "{$param} {$op} {$value}";
    }
    return implode(' AND ', $parts);
}
