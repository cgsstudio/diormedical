<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-get-field-group-schema', [
    'label' => __('Get Meta Box Field Group Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the schema of a single Meta Box field group identified by key. By default returns identity (key, title, source, post_types, context, priority) plus field_count — pass include_fields=true to inflate the fields[] array. Works for both PHP-registered and DB-stored groups. Use key from metabox-list-field-groups.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'The field group key (matches the id field in the Meta Box config). Required.',
            ],
            'include_fields' => [
                'type' => 'boolean',
                'description' => 'When true, inflates the fields[] array with the full field definitions. Defaults to false.',
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
            'source' => ['type' => 'string', 'enum' => ['php', 'db']],
            'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'context' => ['type' => 'string'],
            'priority' => ['type' => 'string'],
            'field_count' => ['type' => 'integer'],
            'fields' => [
                'type' => 'array',
                'description' => 'Present when include_fields=true.',
                'items' => ['type' => 'object'],
            ],
        ],
        'required' => ['key', 'title', 'source', 'post_types', 'field_count'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_get_field_group_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetch the schema for one field group. By default returns a lightweight identity snapshot (key, title, source, post_types, context, priority, field_count). Pass include_fields=true to get the full fields[] array — do this only when you need to inspect or mutate individual fields, as large groups can have 100+ field definitions. source=php means read-only; source=db means editable via metabox-edit-field-group.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{key: string, include_fields?: bool} $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_get_field_group_schema(array $args): array|WP_Error
{
    $guard = mb_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $key = $args['key'];
    if ($key === '') {
        return new WP_Error('metabox_invalid_input', __('key is required.', domain: 'novamira-pro'));
    }

    $group = mb_get_field_group($key);
    if ($group === null) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: field group key. */
            __('Field group "%s" not found.', domain: 'novamira-pro'),
            $key,
        ));
    }

    $include_fields = $args['include_fields'] ?? false;

    if ($include_fields) {
        return mb_build_fg_schema_with_fields($key, $group);
    }

    return mb_build_fg_schema_identity($key, $group);
}

/**
 * Build the identity (lightweight) output for a field group schema response.
 *
 * @param array<array-key, mixed> $group
 * @return array<string, mixed>
 */
function mb_build_fg_schema_identity(string $key, array $group): array
{
    $post_types = mb_normalize_post_types_list($group['post_types'] ?? []);

    /** @var mixed $fields_raw */
    $fields_raw = $group['fields'] ?? [];
    /** @var mixed $context_raw */
    $context_raw = $group['context'] ?? 'normal';
    /** @var mixed $priority_raw */
    $priority_raw = $group['priority'] ?? 'high';

    return [
        'key' => $key,
        'title' => (string) ($group['title'] ?? ''),
        'source' => (string) ($group['source'] ?? 'php'),
        'post_types' => $post_types,
        'context' => is_string($context_raw) ? $context_raw : 'normal',
        'priority' => is_string($priority_raw) ? $priority_raw : 'high',
        'field_count' => is_array($fields_raw) ? count($fields_raw) : 0,
    ];
}

/**
 * Build the full output (including fields[]) for a field group schema response.
 *
 * @param array<array-key, mixed> $group
 * @return array<string, mixed>
 */
function mb_build_fg_schema_with_fields(string $key, array $group): array
{
    /** @var mixed $fields_raw */
    $fields_raw = $group['fields'] ?? [];
    /** @var list<array<string, mixed>> $fields */
    $fields = is_array($fields_raw) ? $fields_raw : [];

    $out = mb_build_fg_schema_identity($key, $group);
    $out['fields'] = $fields;
    return $out;
}

/**
 * Normalize a raw post_types value to a clean list<string>.
 *
 * @param mixed $raw
 * @return list<string>
 */
function mb_normalize_post_types_list(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    return array_values(array_filter(
        array_map(static fn(mixed $pt): string => is_string($pt) ? $pt : '', $raw),
        static fn(string $pt): bool => $pt !== '',
    ));
}
