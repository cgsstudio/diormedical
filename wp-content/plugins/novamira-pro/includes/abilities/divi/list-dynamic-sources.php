<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentOptions;

/**
 * Ability: List the available Divi 5 dynamic-content sources.
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_DIVI_LIST_SOURCES_DEFAULT_LIMIT = 300;

wp_register_ability('novamira/divi-list-dynamic-sources', [
    'label' => __('List Divi Dynamic Sources', domain: 'novamira-pro'),
    'description' => __(
        'Lists the dynamic-content sources Divi 5 can bind module fields to (post_title, post_excerpt, post_featured_image, post_meta_key, site_title, product_*, ACF fields, …). The set is contextual: pass "post" to scope it to that post\'s type (e.g. to surface its ACF fields or WooCommerce product sources). By default returns compact rows (id, label, group); pass a single "source" id to get its settings fields, including compact defaults, select options, and show_if/show_on conditions when Divi provides them. The "id" is the value you pass as "source" to novamira/divi-apply-dynamic-content. Filter by "group" or "search".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'source' => [
                'type' => 'string',
                'description' => 'A single source id to return in full (with its settings fields).',
            ],
            'group' => ['type' => 'string', 'description' => 'Restrict to one group label (e.g. "Default", "Post").'],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring matched against id and label.',
            ],
            'post' => ['type' => 'integer', 'description' => 'Scope the source list to this post\'s context.'],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NOVAMIRA_DIVI_LIST_SOURCES_DEFAULT_LIMIT,
                'description' => 'Max rows to return (default and max 300).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'sources' => ['type' => 'array', 'items' => ['type' => 'object']],
            'source' => ['type' => 'object', 'description' => 'Present when a single "source" was requested.'],
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_dynamic_sources',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover dynamic sources before binding. Use the compact list to find a source id, then call with source=<id> to see its settings fields. Pass post=<id> so context-specific sources (ACF, WooCommerce) show up.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|\WP_Error
 */
function divi_list_dynamic_sources(array $input)
{
    if (!class_exists(DynamicContentOptions::class)) {
        return divi_error(
            'unavailable',
            message: 'The Divi dynamic-content options registry is not available.',
            status: 500,
        );
    }

    $post = divi_int_arg_or_null($input, key: 'post');
    $options = DynamicContentOptions::get_options((int) $post, 'edit');

    $requested = divi_string_arg($input, key: 'source');
    if ($requested !== '') {
        if (!array_key_exists($requested, $options)) {
            return divi_error(
                'unknown_source',
                message: sprintf('Unknown dynamic source "%s". Omit "source" to list the available ids.', $requested),
                status: 404,
            );
        }
        return ['source' => divi_dynamic_source_detail($requested, $options[$requested])];
    }

    $group = divi_string_arg($input, key: 'group');
    $search = mb_strtolower(divi_string_arg($input, key: 'search'));
    $limit = divi_int_arg_or_null($input, key: 'limit') ?? NOVAMIRA_DIVI_LIST_SOURCES_DEFAULT_LIMIT;
    $limit = max(1, min($limit, NOVAMIRA_DIVI_LIST_SOURCES_DEFAULT_LIMIT));

    $rows = [];
    foreach ($options as $id => $option) {
        $row = divi_dynamic_source_summary($id, $option);
        if ($group !== '' && $row['group'] !== $group) {
            continue;
        }
        if ($search !== '' && !str_contains(mb_strtolower($row['id'] . ' ' . $row['label']), $search)) {
            continue;
        }
        $rows[] = $row;
    }

    $total = count($rows);
    $returned = array_slice($rows, offset: 0, length: $limit);

    return [
        'sources' => $returned,
        'total' => $total,
        'returned' => count($returned),
        'truncated' => $total > count($returned),
    ];
}

/**
 * Compact row for one dynamic-content option.
 *
 * @param array<string, mixed> $option
 * @return array{id: string, label: string, group: string}
 */
function divi_dynamic_source_summary(string $id, array $option): array
{
    return [
        'id' => is_string($option['id'] ?? null) ? $option['id'] : $id,
        'label' => is_string($option['label'] ?? null) ? $option['label'] : $id,
        'group' => is_string($option['group'] ?? null) ? $option['group'] : '',
    ];
}

/**
 * Full detail for one dynamic-content option, including its settings fields
 * (label, type, and compact configuration metadata), so the agent knows what
 * to pass in `settings`.
 *
 * @param array<string, mixed> $option
 * @return array<string, mixed>
 */
function divi_dynamic_source_detail(string $id, array $option): array
{
    return [
        'id' => is_string($option['id'] ?? null) ? $option['id'] : $id,
        'label' => is_string($option['label'] ?? null) ? $option['label'] : $id,
        'type' => is_string($option['type'] ?? null) ? $option['type'] : 'text',
        'group' => is_string($option['group'] ?? null) ? $option['group'] : '',
        'fields' => divi_dynamic_source_fields($option),
    ];
}

/**
 * The settings fields of a dynamic-content option, reduced to the values an
 * agent needs to configure them. The compact list mode never calls this.
 *
 * @param array<string, mixed> $option
 * @return array<string, array<string, mixed>>
 */
function divi_dynamic_source_fields(array $option): array
{
    $fields = [];
    $raw_fields = is_array($option['fields'] ?? null) ? $option['fields'] : [];
    /** @var mixed $field */
    foreach ($raw_fields as $key => $field) {
        if (!is_string($key) || !is_array($field)) {
            continue;
        }
        $compact = [
            'label' => is_string($field['label'] ?? null) ? $field['label'] : $key,
            'type' => is_string($field['type'] ?? null) ? $field['type'] : 'text',
        ];
        foreach (['default', 'options', 'show_if', 'show_on'] as $metadata_key) {
            if (!array_key_exists($metadata_key, $field)) {
                continue;
            }
            $compact[$metadata_key] = $field[$metadata_key];
        }
        $fields[$key] = $compact;
    }
    return $fields;
}
