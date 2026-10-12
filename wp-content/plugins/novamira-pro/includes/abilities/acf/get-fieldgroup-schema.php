<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-get-fieldgroup-schema', [
    'label' => __('Get ACF Field Group Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full schema of a specific ACF field group in ACF native format: key, title, fields (with sub_fields for repeaters and layouts for flexible_content), location rules, position, conditional logic, plus a _meta block describing where it is defined and whether it can be edited.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string', 'pattern' => '^group_'],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_get_fieldgroup_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the full ACF-native payload for a field group. The same shape is accepted by acf-create-fieldgroup and acf-edit-fieldgroup, so an agent can fetch a schema, mutate it, and write it back symmetrically. Each field entry includes both name (used for friendly access) and key (globally unique, recommended for targeted writes). The group\'s post id is deliberately omitted: field groups are addressed by key, and passing a post id to a write would make ACF overwrite that post. Check _meta.editable before attempting edits.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{key: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acf_get_fieldgroup_schema(array $args): array|WP_Error
{
    if (!class_exists('ACF')) {
        return new WP_Error('acf_not_active', 'ACF is not active');
    }

    $key = $args['key'];
    if ($key === '') {
        return new WP_Error('field_group_not_found', 'key is required');
    }

    $group = acf_get_field_group($key);
    if (!is_array($group)) {
        return new WP_Error('field_group_not_found', "field group not found: {$key}", ['key' => $key]);
    }

    $fields = acf_get_fields($key);
    $group['fields'] = is_array($fields) ? $fields : [];

    // ACF injects the group's post id when it reads a group, but this ability
    // promises the returned object is accepted verbatim by acf-create-fieldgroup
    // and acf-edit-fieldgroup. A post id has no meaning in that contract — ACF
    // addresses field groups by `key` — and handing one back is what steered
    // agents into passing it to create, where a non-zero ID makes ACF overwrite
    // that post instead of inserting. Drop it so the round-trip claim holds.
    // Provenance stays available in the `_meta` block below.
    unset($group['ID']);

    /** @var mixed $local */
    $local = $group['local'] ?? null;
    $source = match ($local) {
        'php' => 'php',
        'json' => 'json',
        default => 'db',
    };
    /** @var mixed $local_file */
    $local_file = $group['local_file'] ?? null;
    $file = is_string($local_file) ? $local_file : null;

    $group['_meta'] = [
        'source' => $source,
        'editable' => $source !== 'php',
        'file' => $file,
    ];

    return $group;
}
