<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;
use WP_REST_Request;

/**
 * Abilities: read Etch's saved queries (the etch_queries option) and discover
 * the dynamic data a source yields — the {...} field paths an agent can bind in
 * dynamic elements / loops. The discovery reuses Etch's own resolver by
 * dispatching its REST routes internally (etch-api/queries/wp-query|wp-terms|
 * wp-users via get_dynamic_data), so the resolved fields include Etch custom
 * fields plus any ACF / JetEngine / Meta Box bridges Etch exposes. Read-only.
 */

if (!defined('ABSPATH')) {
    exit();
}

/** Items sampled when discovering fields. */
const ETCH_QUERY_SAMPLE_ITEMS = 3;

/** Default and maximum items returned by a preview. */
const ETCH_QUERY_DEFAULT_ITEMS = 5;

const ETCH_QUERY_MAX_ITEMS = 10;

/** Caps on the flattened field set so the output stays bounded. */
const ETCH_QUERY_MAX_FIELDS = 200;

const ETCH_QUERY_MAX_FIELD_DEPTH = 6;

/** Per-value truncation: short for the field catalogue, longer for previews. */
const ETCH_QUERY_SAMPLE_BYTES = 120;

const ETCH_QUERY_VALUE_BYTES = 2_048;

/**
 * Raw WP_Post DB columns that Etch's wp-query resolver serializes alongside its
 * friendly dynamic-data fields (post_title -> title, post_name -> slug, ...).
 * They are not bindable Etch paths and only duplicate the friendly ones, yet
 * they consume the ETCH_QUERY_MAX_FIELDS budget and can push useful namespaces
 * (meta.*, etch.*) past the cap — so field discovery drops them.
 */
const ETCH_RAW_POST_COLUMNS = [
    'ID',
    'post_author',
    'post_date',
    'post_date_gmt',
    'post_content',
    'post_title',
    'post_excerpt',
    'post_status',
    'comment_status',
    'ping_status',
    'post_password',
    'post_name',
    'to_ping',
    'pinged',
    'post_modified',
    'post_modified_gmt',
    'post_content_filtered',
    'post_parent',
    'guid',
    'menu_order',
    'post_type',
    'post_mime_type',
    'comment_count',
    'filter',
];

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/**
 * Map a source name to the Etch discovery REST route, or null if unknown.
 */
function etch_query_route_for_source(string $source): ?string
{
    $map = [
        'post' => '/etch-api/queries/wp-query',
        'term' => '/etch-api/queries/wp-terms',
        'user' => '/etch-api/queries/wp-users',
    ];

    return $map[$source] ?? null;
}

/**
 * Run a source query through Etch's own resolver (internal REST dispatch) and
 * return its raw { data, total?, pages? } payload, or a WP_Error.
 *
 * @param array<array-key, mixed> $query_args
 * @return array<array-key, mixed>|WP_Error
 */
function etch_query_dispatch(string $source, array $query_args): array|WP_Error
{
    $route = etch_query_route_for_source($source);
    if ($route === null) {
        return etch_error('etch_invalid_input', __(
            '"source" must be one of: post, term, user.',
            domain: 'novamira-pro',
        ));
    }

    $encoded = wp_json_encode($query_args, flags: 0, depth: ETCH_MAX_ATTRS_DEPTH);
    if (!is_string($encoded)) {
        return etch_error('etch_invalid_input', __('"query_args" could not be encoded.', domain: 'novamira-pro'));
    }

    $request = new WP_REST_Request(method: 'GET', route: $route);
    $request->set_query_params(['query_args' => $encoded]);

    // rest_do_request does not catch Throwables the route handler may raise —
    // e.g. a non-numeric "number"/"paged" arg flows into WP_User_Query
    // arithmetic and throws a TypeError. Translate any such failure into a
    // clean WP_Error instead of letting it surface as a callback exception.
    try {
        $response = rest_do_request($request);
    } catch (\Throwable $e) {
        return etch_error('etch_query_failed', $e->getMessage());
    }
    if ($response->is_error()) {
        $error = $response->as_error();
        $message = $error instanceof WP_Error
            ? $error->get_error_message()
            : __('The query could not be run.', domain: 'novamira-pro');

        return etch_error('etch_query_failed', $message);
    }

    /** @var mixed $data */
    $data = $response->get_data();
    if (!is_array($data)) {
        return etch_error('etch_query_failed', __(
            'The query returned an unexpected response.',
            domain: 'novamira-pro',
        ));
    }

    return $data;
}

/**
 * Build the WP query args for a source, merging an optional caller override.
 *
 * @param array<array-key, mixed> $input
 * @return array<array-key, mixed>|WP_Error
 */
function etch_build_query_args(string $source, array $input, int $limit): array|WP_Error
{
    $base = etch_source_base_args($source, $input, $limit);
    if ($base instanceof WP_Error) {
        return $base;
    }

    if (!isset($input['query_args'])) {
        return $base;
    }

    /** @var mixed $override */
    $override = $input['query_args'];
    if (!is_array($override)) {
        return etch_error('etch_invalid_input', __('"query_args" must be an object.', domain: 'novamira-pro'));
    }

    $encoded = wp_json_encode($override, flags: 0, depth: ETCH_MAX_ATTRS_DEPTH);
    if (!is_string($encoded) || strlen($encoded) > ETCH_MAX_ATTRS_BYTES) {
        return etch_error('etch_args_too_large', __(
            '"query_args" is too large or too deeply nested.',
            domain: 'novamira-pro',
        ));
    }

    return array_merge($base, $override);
}

/**
 * Default args per source. Each is overridable via query_args.
 *
 * @param array<array-key, mixed> $input
 * @return array<array-key, mixed>|WP_Error
 */
function etch_source_base_args(string $source, array $input, int $limit): array|WP_Error
{
    if ($source === 'post') {
        /** @var mixed $post_type */
        $post_type = $input['post_type'] ?? 'post';
        if (!is_string($post_type) || $post_type === '') {
            return etch_error('etch_invalid_input', __(
                '"post_type" must be a non-empty string.',
                domain: 'novamira-pro',
            ));
        }

        return ['post_type' => $post_type, 'posts_per_page' => $limit, 'post_status' => 'publish'];
    }

    if ($source === 'term') {
        /** @var mixed $taxonomy */
        $taxonomy = $input['taxonomy'] ?? null;
        if (!is_string($taxonomy) || $taxonomy === '') {
            return etch_error('etch_invalid_input', __(
                'source "term" requires a non-empty "taxonomy".',
                domain: 'novamira-pro',
            ));
        }

        return ['taxonomy' => $taxonomy, 'number' => $limit, 'hide_empty' => false];
    }

    if ($source === 'user') {
        return ['number' => $limit];
    }

    return etch_error('etch_invalid_input', __('"source" must be one of: post, term, user.', domain: 'novamira-pro'));
}

/**
 * The runtime type label for a resolved value.
 */
function etch_value_type(mixed $value): string
{
    if (is_bool($value)) {
        return 'boolean';
    }
    if (is_int($value)) {
        return 'integer';
    }
    if (is_float($value)) {
        return 'number';
    }
    if (is_string($value)) {
        return 'string';
    }
    if ($value === null) {
        return 'null';
    }
    if (is_array($value)) {
        return 'array';
    }

    return 'object';
}

/**
 * A truncated, printable sample of a resolved value.
 */
function etch_value_sample(mixed $value, int $max_bytes): string
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($value === null) {
        return 'null';
    }
    if (is_array($value)) {
        return '(array)';
    }
    if (is_scalar($value)) {
        $string = (string) $value;
        if (strlen($string) > $max_bytes) {
            // Byte-truncation can split a multibyte char; scrub invalid UTF-8 so
            // json_encode of the whole response never fails on the trailing
            // partial byte (which would surface as a 500/empty over REST/MCP).
            return wp_check_invalid_utf8(substr($string, offset: 0, length: $max_bytes), strip: true) . '...';
        }

        // Also scrub binary / invalid-UTF-8 values that fit under the cap.
        return wp_check_invalid_utf8($string, strip: true);
    }

    return '(object)';
}

/**
 * Flatten a resolved item into dot-path field descriptors (bounded).
 *
 * @param list<array{path: string, type: string, sample: string}> $out
 */
function etch_flatten_fields(mixed $value, string $prefix, array &$out, int $depth, int $sample_bytes): void
{
    if (count($out) >= ETCH_QUERY_MAX_FIELDS) {
        return;
    }

    if (is_array($value)) {
        etch_flatten_array($value, $prefix, $out, $depth, $sample_bytes);

        return;
    }

    $out[] = [
        'path' => $prefix,
        'type' => etch_value_type($value),
        'sample' => etch_value_sample($value, $sample_bytes),
    ];
}

/**
 * Flatten an array value: assoc keys recurse; lists reveal their first element.
 *
 * @param array<array-key, mixed> $value
 * @param list<array{path: string, type: string, sample: string}> $out
 */
function etch_flatten_array(array $value, string $prefix, array &$out, int $depth, int $sample_bytes): void
{
    if ($value === []) {
        $out[] = ['path' => $prefix, 'type' => 'array', 'sample' => '[]'];

        return;
    }

    if ($depth > ETCH_QUERY_MAX_FIELD_DEPTH) {
        $out[] = ['path' => $prefix, 'type' => 'array', 'sample' => '(nested)'];

        return;
    }

    if (etch_is_list($value)) {
        /** @var mixed $first */
        $first = $value[0] ?? null;
        $out[] = [
            'path' => $prefix,
            'type' => 'array[' . count($value) . ']',
            'sample' => etch_value_sample($first, $sample_bytes),
        ];
        etch_flatten_fields($first, $prefix . '[]', $out, $depth + 1, $sample_bytes);

        return;
    }

    foreach (array_keys($value) as $key) {
        if (count($out) >= ETCH_QUERY_MAX_FIELDS) {
            return;
        }
        /** @var mixed $child */
        $child = $value[$key];
        $path = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
        etch_flatten_fields($child, $path, $out, $depth + 1, $sample_bytes);
    }
}

// ---------------------------------------------------------------------------
// list-queries
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-queries', [
    'label' => __('List Etch Queries', domain: 'novamira-pro'),
    'description' => __(
        'Lists the saved queries Etch stores in the etch_queries option (reusable data sources). Returns a compact entry per query { key, name, type }. Fetch the full definition with novamira/etch-get-query. To discover the dynamic fields a query yields, use novamira/etch-list-dynamic-fields instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'count' => ['type' => 'integer'],
            'queries' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_queries',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Saved query presets only. The etch_queries store is empty by default; most dynamic-data work uses novamira/etch-list-dynamic-fields (field discovery) and the loop abilities instead.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, queries: list<array{key: string, name: string, type: string}>}|WP_Error
 */
function etch_list_queries(): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $queries = etch_option_array('etch_queries');

    $out = [];
    foreach (array_keys($queries) as $key) {
        if (!is_string($key)) {
            continue;
        }
        /** @var mixed $entry */
        $entry = $queries[$key];

        $name = '';
        $type = '';
        if (is_array($entry)) {
            /** @var mixed $raw_name */
            $raw_name = $entry['name'] ?? $entry['label'] ?? null;
            $name = is_string($raw_name) ? $raw_name : '';
            /** @var mixed $raw_type */
            $raw_type = $entry['type'] ?? null;
            $type = is_string($raw_type) ? $raw_type : '';
        }

        $out[] = ['key' => $key, 'name' => $name, 'type' => $type];
    }

    return ['count' => count($out), 'queries' => $out];
}

// ---------------------------------------------------------------------------
// get-query
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-query', [
    'label' => __('Get Etch Query', domain: 'novamira-pro'),
    'description' => __(
        'Returns one saved Etch query in full by its key: { key, query } where query is the stored definition object. Use novamira/etch-list-queries to discover the keys.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string', 'description' => 'The saved query key.'],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'query' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_query',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The query definition shape is Etch-internal and free-form; read it before relying on its fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{key: string, query: array<array-key, mixed>}|WP_Error
 */
function etch_get_query(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $key */
    $key = $input['key'] ?? null;
    if (!is_string($key) || $key === '') {
        return etch_error('etch_invalid_input', __('A non-empty "key" is required.', domain: 'novamira-pro'));
    }

    $queries = etch_option_array('etch_queries');
    if (!isset($queries[$key])) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %s: query key. */
            __('Query "%s" was not found.', domain: 'novamira-pro'),
            $key,
        ));
    }

    /** @var mixed $entry */
    $entry = $queries[$key];

    return ['key' => $key, 'query' => is_array($entry) ? $entry : []];
}

// ---------------------------------------------------------------------------
// list-dynamic-fields
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-dynamic-fields', [
    'label' => __('List Etch Dynamic Fields', domain: 'novamira-pro'),
    'description' => __(
        'Discovers the dynamic data fields available for a source — the {...} expression paths you can bind in etch/dynamic-element, etch/dynamic-image, and loop children. Samples a few real items and returns a compact catalogue of dot-paths with their type and a truncated sample value (e.g. "title", "permalink", "meta.subtitle", "etch.author_bio", "featured_image.url"). source is "post" (default; pick the post_type), "term" (give a taxonomy), or "user". Resolution reuses Etch\'s own engine, so it includes Etch custom fields and any ACF / JetEngine / Meta Box bridges. Use this before building dynamic content so you bind real paths instead of guessing. Output is capped; create some content of the type first if nothing is returned.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'source' => [
                'type' => 'string',
                'enum' => ['post', 'term', 'user'],
                'description' => 'What to introspect. Defaults to "post".',
            ],
            'post_type' => [
                'type' => 'string',
                'description' => 'Post type slug when source is "post". Defaults to "post".',
            ],
            'taxonomy' => [
                'type' => 'string',
                'description' => 'Taxonomy slug when source is "term" (required for terms).',
            ],
            'query_args' => [
                'type' => 'object',
                'description' => 'Optional WP_Query / WP_Term_Query / WP_User_Query args to narrow the sample (merged over the defaults).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'source' => ['type' => 'string'],
            'sampled' => ['type' => 'integer', 'description' => 'How many items were sampled (0 means none matched).'],
            'field_count' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean', 'description' => 'True when the field catalogue hit the cap.'],
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'The dot-path to bind, e.g. {meta.subtitle}.'],
                        'type' => ['type' => 'string'],
                        'sample' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_dynamic_fields',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Bind a returned path in an etch/dynamic-element/etch/dynamic-image attr or etch/text content as {path} (e.g. {meta.subtitle}). Inside a loop the same paths resolve per item. sampled:0 means no content of that type exists yet — create one to discover its fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{source: string, sampled: int, field_count: int, truncated: bool, fields: list<array{path: string, type: string, sample: string}>}|WP_Error
 */
function etch_list_dynamic_fields(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $source */
    $source = $input['source'] ?? 'post';
    if (!is_string($source)) {
        return etch_error('etch_invalid_input', __('"source" must be a string.', domain: 'novamira-pro'));
    }

    $args = etch_build_query_args($source, $input, ETCH_QUERY_SAMPLE_ITEMS);
    if ($args instanceof WP_Error) {
        return $args;
    }

    $result = etch_query_dispatch($source, $args);
    if ($result instanceof WP_Error) {
        return $result;
    }

    /** @var mixed $items */
    $items = $result['data'] ?? [];
    $items = is_array($items) ? $items : [];

    /** @var mixed $first */
    $first = $items[0] ?? null;
    $first = is_array($first) ? $first : [];

    // Etch's wp-query resolver prepends the raw WP_Post columns; drop them so
    // the bindable namespaces (meta.*, etch.*, permalink.*) are not truncated
    // out of the catalogue by the ETCH_QUERY_MAX_FIELDS cap.
    if ($source === 'post') {
        $first = array_diff_key($first, array_flip(ETCH_RAW_POST_COLUMNS));
    }

    $fields = [];
    etch_flatten_fields($first, prefix: '', out: $fields, depth: 1, sample_bytes: ETCH_QUERY_SAMPLE_BYTES);

    return [
        'source' => $source,
        'sampled' => count($items),
        'field_count' => count($fields),
        'truncated' => count($fields) >= ETCH_QUERY_MAX_FIELDS,
        'fields' => $fields,
    ];
}

// ---------------------------------------------------------------------------
// get-query-preview
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-query-preview', [
    'label' => __('Preview Etch Query Results', domain: 'novamira-pro'),
    'description' => __(
        'Runs a source query through Etch\'s resolver and returns a small sample of real items with their resolved dynamic data, so you can see actual values (not just field names). source is "post" (pick post_type), "term" (give a taxonomy), or "user"; limit caps the items (default 5, max 10). Each item is a flattened { path: value } map of its dynamic fields (values truncated). Pair with novamira/etch-list-dynamic-fields, which returns the field catalogue without the data.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'source' => [
                'type' => 'string',
                'enum' => ['post', 'term', 'user'],
                'description' => 'What to query. Defaults to "post".',
            ],
            'post_type' => ['type' => 'string', 'description' => 'Post type slug when source is "post".'],
            'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug when source is "term".'],
            'query_args' => ['type' => 'object', 'description' => 'Optional query args merged over the defaults.'],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => ETCH_QUERY_MAX_ITEMS,
                'description' => 'Max items to return (default 5, max 10).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'source' => ['type' => 'string'],
            'total' => ['type' => 'integer', 'description' => 'Total matches the query reports.'],
            'returned' => ['type' => 'integer'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'fields' => [
                            'type' => 'object',
                            'description' => 'Flattened { path: value } of the item\'s dynamic data.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_query_preview',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only preview. Values are truncated; use it to confirm what a {path} resolves to before binding it. For just the field names use novamira/etch-list-dynamic-fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{source: string, total: int, returned: int, items: list<array{fields: array<string, string>}>}|WP_Error
 */
function etch_get_query_preview(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $source */
    $source = $input['source'] ?? 'post';
    if (!is_string($source)) {
        return etch_error('etch_invalid_input', __('"source" must be a string.', domain: 'novamira-pro'));
    }

    $limit = isset($input['limit']) ? (int) $input['limit'] : ETCH_QUERY_DEFAULT_ITEMS;
    $limit = max(1, min($limit, ETCH_QUERY_MAX_ITEMS));

    $args = etch_build_query_args($source, $input, $limit);
    if ($args instanceof WP_Error) {
        return $args;
    }

    $result = etch_query_dispatch($source, $args);
    if ($result instanceof WP_Error) {
        return $result;
    }

    /** @var mixed $raw_items */
    $raw_items = $result['data'] ?? [];
    $raw_items = is_array($raw_items) ? $raw_items : [];

    $items = [];
    foreach (array_keys($raw_items) as $index) {
        if (count($items) >= $limit) {
            break;
        }
        /** @var mixed $item */
        $item = $raw_items[$index];
        if (!is_array($item)) {
            continue;
        }

        // Same de-noise as field discovery: drop the raw WP_Post columns Etch's
        // resolver serializes, so meta.* / etch.* are not truncated out of the
        // preview by the field cap (see ETCH_RAW_POST_COLUMNS).
        if ($source === 'post') {
            $item = array_diff_key($item, array_flip(ETCH_RAW_POST_COLUMNS));
        }

        $fields = [];
        etch_flatten_fields($item, prefix: '', out: $fields, depth: 1, sample_bytes: ETCH_QUERY_VALUE_BYTES);

        $map = [];
        foreach ($fields as $field) {
            $map[$field['path']] = $field['sample'];
        }

        $items[] = ['fields' => $map];
    }

    /** @var mixed $total */
    $total = $result['total'] ?? null;

    return [
        'source' => $source,
        'total' => is_int($total) ? $total : count($items),
        'returned' => count($items),
        'items' => $items,
    ];
}
