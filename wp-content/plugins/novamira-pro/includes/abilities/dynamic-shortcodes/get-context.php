<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicShortcodes;

use DynamicShortcodes\Plugin;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const DYNAMIC_SHORTCODES_GET_CONTEXT_DEFAULT_LIMIT = 100;

const DYNAMIC_SHORTCODES_GET_CONTEXT_MAX_LIMIT = 500;

wp_register_ability('novamira/dynamic-shortcodes-get-context', [
    'label' => __('Get Shortcode Context', domain: 'novamira-pro'),
    'description' => __(
        'Returns the list of fields available on this site for a given Dynamic Shortcode type (e.g. "post", "acf", "user", "term", "query", "woo"). Each field carries a name and its possible result types. Use this to discover real fields instead of guessing from documentation. Paginated — pass `limit`/`offset` for paging and `name_pattern` for a case-insensitive substring filter; the response includes `total` and `has_more`.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'The Dynamic Shortcode type (e.g. "post", "acf", "user", "term", "query", "woo").',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'Optional post ID to set as context before fetching context-dependent fields.',
            ],
            'name_pattern' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring filter on field names. Applied before pagination.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Maximum number of fields to return. Default 100, max 500.',
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Pagination cursor — how many fields to skip from the start of the (filtered) list.',
            ],
        ],
        'required' => ['type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string'],
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'result_types' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'Total fields available after filter (before pagination).',
            ],
            'limit' => ['type' => 'integer', 'description' => 'Effective limit applied.'],
            'offset' => ['type' => 'integer', 'description' => 'Effective offset applied.'],
            'has_more' => ['type' => 'boolean', 'description' => 'True when more fields exist beyond this page.'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_get_context',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => dsh_make_instructions(
                'Returns the real fields available on this site for the requested type. Only works for types in `accepted_types` from check-setup. Returns WP_Error("unsupported_type") for unknown or unavailable types. Default page size is 100; raise `limit` (max 500) or use `name_pattern` to narrow the response when working with rich field configurations.',
            ),
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Set postdata context when a post_id is provided. Returns true when it actually
 * switched the global $post, so the caller knows to restore it afterwards.
 */
function gc_maybe_setup_post(?int $post_id): bool
{
    if ($post_id === null) {
        return false;
    }

    /** @var \WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post instanceof \WP_Post) {
        return false;
    }

    // phpcs:ignore WordPress.WP.GlobalVariablesOverride
    $GLOBALS['post'] = $post;
    setup_postdata($post);

    return true;
}

/**
 * Fetch raw composer-format fields for $type, or return WP_Error on failure.
 *
 * @return list<array<string, mixed>>|WP_Error
 */
function gc_fetch_fields(Plugin $plugin, string $type): array|WP_Error
{
    try {
        /** @var list<array<string, mixed>> */
        return $plugin->library_manager->get_fields([
            'type' => $type,
            'format' => 'composer',
        ]);
    } catch (\Throwable) {
        return new WP_Error('unsupported_type', sprintf(
            "Type '%s' is not supported on this site or its plugin dependency is not active. Available types: %s",
            $type,
            implode(', ', $plugin->demo_manager->get_types_accepted()),
        ));
    }
}

/**
 * Normalize a single raw composer field to {name, result_types}.
 *
 * @param array<string, mixed> $field
 * @return array{name: string, result_types: list<string>}
 */
function gc_normalize_field(array $field): array
{
    $name = '';
    if (isset($field['args']) && is_array($field['args']) && isset($field['args'][0])) {
        $name = (string) $field['args'][0];
    }

    $result_types = [];
    if (
        isset($field['force_result_types'])
        && is_array($field['force_result_types'])
        && $field['force_result_types'] !== []
    ) {
        $result_types = array_values(array_map(
            static fn(mixed $v): string => (string) $v,
            $field['force_result_types'],
        ));
    }

    return ['name' => $name, 'result_types' => $result_types];
}

/**
 * @param list<array<string, mixed>> $fields
 * @return list<array{name: string, result_types: list<string>}>
 */
function gc_normalize_fields(array $fields): array
{
    return array_values(array_map(static fn(array $f): array => gc_normalize_field($f), $fields));
}

/**
 * Apply an optional case-insensitive substring filter on field names.
 *
 * @param list<array{name: string, result_types: list<string>}> $all
 * @return list<array{name: string, result_types: list<string>}>
 */
function gc_filter_fields(array $all, string $pattern): array
{
    if ($pattern === '') {
        return $all;
    }

    $needle = strtolower($pattern);

    return array_values(array_filter($all, static fn(array $f): bool => str_contains(strtolower($f['name']), $needle)));
}

/**
 * @param array{
 *   type: string,
 *   post_id?: int,
 *   name_pattern?: string,
 *   limit?: int,
 *   offset?: int
 * } $input
 * @return array{
 *   type: string,
 *   fields: list<array{name: string, result_types: list<string>}>,
 *   total: int,
 *   limit: int,
 *   offset: int,
 *   has_more: bool
 * }|WP_Error
 */
function dynamic_shortcodes_get_context(array $input): array|WP_Error
{
    $type = $input['type'];
    $plugin = Plugin::instance();

    $post_id = isset($input['post_id']) ? (int) $input['post_id'] : null;
    /** @var mixed $prev_post */
    $prev_post = $GLOBALS['post'] ?? null;
    $switched = gc_maybe_setup_post($post_id);

    $raw = gc_fetch_fields($plugin, $type);

    if ($switched) {
        dsh_restore_post($prev_post);
    }

    if ($raw instanceof WP_Error) {
        return $raw;
    }

    $all = gc_normalize_fields($raw);
    $all = gc_filter_fields($all, $input['name_pattern'] ?? '');

    $total = count($all);
    $limit = isset($input['limit'])
        ? min(max(1, (int) $input['limit']), DYNAMIC_SHORTCODES_GET_CONTEXT_MAX_LIMIT)
        : DYNAMIC_SHORTCODES_GET_CONTEXT_DEFAULT_LIMIT;
    $offset = isset($input['offset']) ? max(0, (int) $input['offset']) : 0;

    $page = array_slice($all, $offset, $limit);
    $has_more = ($offset + count($page)) < $total;

    return [
        'type' => $type,
        'fields' => $page,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => $has_more,
    ];
}
