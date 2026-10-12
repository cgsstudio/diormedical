<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WC_Product;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-list-products', [
    'label' => __('List Products', domain: 'novamira-pro'),
    'description' => __(
        'Returns a paginated list of WC products with a 15-field compact shape: id, name, slug, sku, status, type, stock_status, stock_quantity, manage_stock, price, regular_price, sale_price, featured, permalink, date_modified. Use `fields` to opt into extra sections per row: categories, tags, images, attributes, dimensions, meta_data, variations (id list), brands (only when WooCommerce Brands is active). Filters: search (title+sku), status, type, category (slug|id), tag (slug|id), featured, on_sale (true=only on-sale; false=exclude on-sale), stock_status, sku (exact), include[], exclude[], modified_after, modified_before. Paging: limit (default 20, max 200; clamped silently), offset. Ordering: orderby (date/title/sku/price/menu_order/popularity/rating), order (asc/desc).',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'search' => ['type' => 'string', 'description' => 'Substring match on title and sku.'],
            'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private']],
            'type' => ['type' => 'string', 'enum' => ['simple', 'variable', 'grouped', 'external']],
            'category' => ['type' => ['string', 'integer'], 'description' => 'Slug or term id.'],
            'tag' => ['type' => ['string', 'integer'], 'description' => 'Slug or term id.'],
            'featured' => ['type' => 'boolean'],
            'on_sale' => [
                'type' => 'boolean',
                'description' => 'true = only on-sale products; false = exclude on-sale products; omit for no filter.',
            ],
            'stock_status' => ['type' => 'string', 'enum' => ['instock', 'outofstock', 'onbackorder']],
            'sku' => ['type' => 'string', 'description' => 'Exact match.'],
            'include' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'exclude' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'modified_after' => ['type' => 'string', 'description' => 'ISO 8601, server timezone.'],
            'modified_before' => ['type' => 'string', 'description' => 'ISO 8601, server timezone.'],
            'limit' => [
                'type' => 'integer',
                'default' => 20,
                'description' => 'Default 20, max 200 (clamped silently).',
            ],
            'offset' => ['type' => 'integer', 'default' => 0],
            'orderby' => [
                'type' => 'string',
                'enum' => ['date', 'title', 'sku', 'price', 'menu_order', 'popularity', 'rating'],
                'default' => 'date',
            ],
            'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => [
                        'categories',
                        'tags',
                        'images',
                        'attributes',
                        'dimensions',
                        'meta_data',
                        'variations',
                        'brands',
                    ],
                ],
                'description' => 'Opt-in extra sections per row.',
            ],
        ],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'products' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
        'required' => ['products', 'total', 'returned', 'truncated'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_products',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'For typical "show me my products" calls, omit `fields`; the compact shape is enough. Only set fields when you need categories/images/etc. inline. To page beyond the first 200 results, increase offset.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Validate scalar and enum inputs for list-products before building query args.
 * Returns WP_Error on the first invalid field, null when all inputs are acceptable.
 *
 * @param array<string, mixed> $input
 * @param mixed                $limit_raw The raw limit value (already extracted from $input).
 */
function wc_validate_list_products_input(array $input, mixed $limit_raw): ?WP_Error
{
    if (!is_int($limit_raw) && !is_numeric($limit_raw)) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: actual type */
            __('`limit` must be an integer (got %1$s).', domain: 'novamira-pro'),
            gettype($limit_raw),
        ));
    }

    return (
        wc_validate_enum_field($input, field: 'order', allowed: ['asc', 'desc']) ?? wc_validate_enum_field(
            $input,
            field: 'orderby',
            allowed: ['date', 'title', 'sku', 'price', 'menu_order', 'popularity', 'rating'],
        ) ?? wc_validate_enum_field($input, field: 'status', allowed: [
            'publish',
            'draft',
            'pending',
            'private',
        ]) ?? wc_validate_scalar_string_fields($input, ['modified_after', 'modified_before', 'category', 'tag'])
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_list_products(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    // on_sale normalization runs first so that when on_sale:false merges
    // on-sale ids into exclude, wc_normalize_exclude can then collapse the
    // include+exclude pair into a narrowed include-only list (WC ignores
    // post__not_in when post__in is also set).
    $input = wc_normalize_on_sale($input);

    $input = wc_normalize_exclude($input);
    if (isset($input['include']) && $input['include'] === []) {
        return wc_empty_list((int) ($input['limit'] ?? 20));
    }

    /** @var mixed $limit_raw */
    $limit_raw = $input['limit'] ?? 20;
    $input_err = wc_validate_list_products_input($input, $limit_raw);
    if ($input_err !== null) {
        return $input_err;
    }

    $clamp = wc_clamp_limit((int) $limit_raw);
    $args = wc_build_list_args($input, $clamp['limit']);

    $products = new WP_Error();
    try {
        /** @var list<WC_Product>|WP_Error $products */
        $products = \wc_get_products($args);
    } catch (\Throwable) {
        remove_filter(
            'posts_orderby',
            callback: 'Novamira\\Pro\\Abilities\\Woocommerce\\wc_append_id_tiebreaker_once',
            priority: 10,
        );
        return wc_error('wc_internal_error', __('WooCommerce query failed unexpectedly.', domain: 'novamira-pro'));
    }
    if ($products instanceof WP_Error) {
        return wc_error('wc_invalid_input', $products->get_error_message());
    }

    $fields = isset($input['fields']) && is_array($input['fields'])
        ? array_values(array_map(
            static fn(mixed $v): string => (string) $v,
            array_filter($input['fields'], static fn(mixed $v): bool => is_string($v)),
        ))
        : [];

    $rows = array_values(array_map(static fn(WC_Product $p): array => $fields === []
        ? wc_compact_product($p)
        : wc_compact_product_with_fields($p, $fields), $products));

    try {
        $total = wc_count_filtered_products($args);
    } catch (\Throwable) {
        $total = count($rows);
    }

    return [
        'products' => $rows,
        'total' => $total,
        'returned' => count($rows),
        'truncated' => $clamp['truncated'],
    ];
}

/**
 * WordPress / WooCommerce ignores post__not_in when post__in is also set.
 * Pre-apply exclude against include so the semantics are always
 * "include AND NOT exclude" regardless of WC internal behaviour.
 *
 * When both include and exclude are present, removes excluded ids from include
 * and drops the exclude key (WC will see a narrowed include list only).
 * Sets include to [] as a sentinel when all ids are excluded; the caller must
 * short-circuit before passing this to wc_get_products.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wc_normalize_exclude(array $input): array
{
    if (
        !isset($input['include'], $input['exclude'])
        || !is_array($input['include'])
        || !is_array($input['exclude'])
        || $input['exclude'] === []
    ) {
        return $input;
    }

    $exclude_set = array_map('intval', $input['exclude']);
    $input['include'] = array_values(array_diff(array_map('intval', $input['include']), $exclude_set));
    unset($input['exclude']);

    return $input;
}

/**
 * Pre-filter on_sale at the data layer so limit/offset and total count are correct.
 *
 * When on_sale is true, replaces include with the intersection of the global
 * on-sale id list and the existing include list (if any). Sets include to []
 * as a sentinel when no on-sale ids survive; the caller must short-circuit.
 *
 * When on_sale is false, merges on-sale ids into exclude so they are omitted
 * from results. wc_normalize_exclude() is called AFTER this function so that
 * the on-sale ids merged into exclude are collapsed against include correctly
 * (WC ignores post__not_in when post__in is also set).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wc_normalize_on_sale(array $input): array
{
    if (!array_key_exists('on_sale', $input) || !is_bool($input['on_sale'])) {
        return $input;
    }

    $on_sale_ids = \wc_get_product_ids_on_sale();

    if ($input['on_sale'] === true) {
        if (isset($input['include']) && is_array($input['include']) && $input['include'] !== []) {
            $on_sale_ids = array_values(array_intersect($on_sale_ids, array_map('intval', $input['include'])));
        }
        $input['include'] = $on_sale_ids;
        return $input;
    }

    return wc_exclude_on_sale_ids($input, $on_sale_ids);
}

/**
 * on_sale === false branch: merge the on-sale ids into the exclude list so
 * they are omitted from results.
 *
 * @param array<string, mixed> $input
 * @param list<int>            $on_sale_ids
 * @return array<string, mixed>
 */
function wc_exclude_on_sale_ids(array $input, array $on_sale_ids): array
{
    if (!isset($input['exclude']) || !is_array($input['exclude'])) {
        $input['exclude'] = [];
    }
    $input['exclude'] = array_values(array_unique(array_merge(
        array_map('intval', $input['exclude']),
        array_map('intval', $on_sale_ids),
    )));

    return $input;
}

/**
 * Build a zero-result list response (used when a pre-filter eliminates all candidates).
 *
 * @return array<string, mixed>
 */
function wc_empty_list(int $limit): array
{
    return [
        'products' => [],
        'total' => 0,
        'returned' => 0,
        'truncated' => wc_clamp_limit($limit)['truncated'],
    ];
}

/**
 * Build the wc_get_products() args array from ability input.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wc_build_list_args(array $input, int $limit): array
{
    $orderby = (string) ($input['orderby'] ?? 'date');
    $order = strtoupper((string) ($input['order'] ?? 'desc'));

    // Column-based sorts: pass as array so WP_Query emits "ORDER BY col DIR, ID DIR"
    // as a deterministic tiebreaker. Without this, rows with identical sort-key
    // values produce non-deterministic ordering which breaks cursor pagination.
    $column_orderby_map = [
        'date' => 'date',
        'title' => 'title',
        'menu_order' => 'menu_order',
    ];

    $offset = max(0, (int) ($input['offset'] ?? 0));
    $filter_args = wc_build_filter_args($input);

    if (isset($column_orderby_map[$orderby])) {
        // Column-based sorts: WP_Query array form emits "ORDER BY col DIR, ID DIR".
        return array_merge([
            'limit' => $limit,
            'offset' => $offset,
            'orderby' => [$column_orderby_map[$orderby] => $order, 'ID' => $order],
            'return' => 'objects',
            'paginate' => false,
        ], $filter_args);
    }

    // price, popularity, rating, sku: WooCommerce routes these through
    // wc_product_meta_lookup or meta_query joins and writes the ORDER BY
    // string directly. Append ", {posts}.ID {order}" via posts_orderby
    // for a deterministic tiebreaker without disturbing the meta join.
    add_filter(
        'posts_orderby',
        callback: 'Novamira\\Pro\\Abilities\\Woocommerce\\wc_append_id_tiebreaker_once',
        priority: 10,
        accepted_args: 1,
    );

    return array_merge([
        'limit' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
        'return' => 'objects',
        'paginate' => false,
    ], $filter_args);
}

/**
 * Append ", {$wpdb->posts}.ID <dir>" to an ORDER BY clause exactly once per query.
 *
 * Registered on posts_orderby and removes itself on first invocation so it only
 * affects the immediately following WP_Query (the product list query).
 *
 * @param string $orderby Current ORDER BY clause produced by WP_Query.
 * @return string
 */
function wc_append_id_tiebreaker_once(string $orderby): string
{
    remove_filter(
        'posts_orderby',
        callback: 'Novamira\\Pro\\Abilities\\Woocommerce\\wc_append_id_tiebreaker_once',
        priority: 10,
    );

    global $wpdb;
    /** @var \wpdb $wpdb */

    if ($orderby === '') {
        return $orderby;
    }

    $posts_table = $wpdb->posts;

    // Avoid duplicate if ID tiebreaker is already present.
    if (preg_match('/\b' . preg_quote($posts_table, delimiter: '/') . '\.ID\b/i', $orderby)) {
        return $orderby;
    }

    $is_desc = (bool) preg_match('/\bDESC\b/i', $orderby);
    $dir = $is_desc ? 'DESC' : 'ASC';

    return $orderby . ', ' . $posts_table . '.ID ' . $dir;
}

/**
 * Build optional filter keys for wc_get_products() from ability input.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wc_build_filter_args(array $input): array
{
    return array_merge(wc_build_scalar_filter_args($input), wc_build_list_filter_args($input));
}

/**
 * Scalar filter keys: search, status, type, featured, stock_status, sku, category, tag.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wc_build_scalar_filter_args(array $input): array
{
    $args = [];

    if (isset($input['search']) && is_string($input['search']) && trim($input['search']) !== '') {
        $args['s'] = $input['search'];
    }
    if (isset($input['status'])) {
        $args['status'] = (string) $input['status'];
    }
    if (isset($input['type'])) {
        $args['type'] = (string) $input['type'];
    }
    if (isset($input['featured'])) {
        $args['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($input['stock_status'])) {
        $args['stock_status'] = (string) $input['stock_status'];
    }
    if (isset($input['sku']) && is_string($input['sku']) && trim($input['sku']) !== '') {
        $args['sku'] = $input['sku'];
    }
    if (isset($input['category'])) {
        $args['category'] = wc_resolve_term_slug_input((string) $input['category'], taxonomy: 'product_cat');
    }
    if (isset($input['tag'])) {
        $args['tag'] = wc_resolve_term_slug_input((string) $input['tag'], taxonomy: 'product_tag');
    }

    return $args;
}

/**
 * List/date filter keys: include, exclude, modified_after, modified_before.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wc_build_list_filter_args(array $input): array
{
    $args = [];

    if (isset($input['include']) && is_array($input['include'])) {
        $args['include'] = array_map(static fn(mixed $x): int => (int) $x, $input['include']);
    }
    if (isset($input['exclude']) && is_array($input['exclude'])) {
        $args['exclude'] = array_map(static fn(mixed $x): int => (int) $x, $input['exclude']);
    }
    if (isset($input['modified_after'])) {
        $args['date_modified'] = '>=' . (string) $input['modified_after'];
    }
    if (isset($input['modified_before'])) {
        $prefix = isset($args['date_modified']) ? $args['date_modified'] . ',' : '';
        $args['date_modified'] = $prefix . '<=' . (string) $input['modified_before'];
    }

    return $args;
}

/**
 * Coerce a term input (slug or numeric id string) into the slug wc_get_products expects.
 */
function wc_resolve_term_slug_input(string $input, string $taxonomy): string
{
    if (ctype_digit($input)) {
        $term = get_term((int) $input, $taxonomy);
        if ($term instanceof \WP_Term) {
            return $term->slug;
        }
        return '';
    }
    return $input;
}

/**
 * @param array<string, mixed> $args
 */
function wc_count_filtered_products(array $args): int
{
    $args['limit'] = -1;
    $args['offset'] = 0;
    $args['return'] = 'ids';
    $ids = \wc_get_products($args);
    return count($ids);
}
