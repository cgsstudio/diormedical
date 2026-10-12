<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use Jet_Engine\Modules\Custom_Content_Types\DB as CctDB;
use Jet_Engine\Modules\Custom_Content_Types\Factory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-list-cct-records', [
    'label' => __('List JetEngine CCT Records', domain: 'novamira-pro'),
    'description' => __(
        'Page through records of a JetEngine Custom Content Type. Default limit 50, max 200. Optional `where` filter accepts an object of field_name→value for simple equality (ANDed across keys). orderby defaults to _ID; order accepts asc|desc.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'cct_slug' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            'where' => ['type' => 'object', 'additionalProperties' => true],
            'orderby' => ['type' => 'string', 'default' => '_ID'],
            'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
        ],
        'required' => ['cct_slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'items' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
            'has_more' => ['type' => 'boolean'],
            'next_offset' => ['type' => ['integer', 'null']],
        ],
        'required' => ['items', 'total', 'has_more', 'next_offset'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_list_cct_records',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

/**
 * Build a whitelist of known field names for a CCT factory.
 * Always includes '_ID' (the auto-increment primary key).
 *
 * @return list<string>
 */
function lcr_known_fields(Factory $factory): array
{
    $known = ['_ID'];
    foreach ($factory->fields as $field) {
        $name = (string) ($field['name'] ?? '');
        if ($name !== '') {
            $known[] = $name;
        }
    }

    return $known;
}

/**
 * Validate and normalise the `where` input into a field→value map.
 * Returns WP_Error when an unknown key is encountered.
 *
 * @param array<string, mixed> $raw_where
 * @param list<string>         $known_fields
 * @return array<string, mixed>|WP_Error
 */
function lcr_validate_where(array $raw_where, array $known_fields): array|WP_Error
{
    $out = [];
    /** @var mixed $value */
    foreach ($raw_where as $key => $value) {
        if (!in_array($key, $known_fields, strict: true)) {
            return new WP_Error('cct_invalid_where_key', sprintf(
                /* translators: %s: the invalid where key. */
                __('Unknown where key: %s. Must be _ID or a defined CCT field name.', domain: 'novamira-pro'),
                $key,
            ));
        }

        $out[$key] = $value;
    }

    return $out;
}

/**
 * Resolve validated query parameters from input, or return WP_Error on validation failure.
 *
 * @param array<string, mixed> $input
 * @param list<string>         $known_fields
 * @return array{limit: int, offset: int, orderby: string, order: string, where_args: array<string, mixed>}|WP_Error
 */
function lcr_resolve_query_params(array $input, array $known_fields): array|WP_Error
{
    $limit = max(1, min(200, (int) ($input['limit'] ?? 50)));
    $offset = max(0, (int) ($input['offset'] ?? 0));
    $orderby = (string) ($input['orderby'] ?? '_ID');
    $order = strtolower((string) ($input['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

    if (!in_array($orderby, $known_fields, strict: true)) {
        return new WP_Error('cct_invalid_orderby', sprintf(
            /* translators: %s: the invalid orderby value. */
            __('Unknown orderby field: %s. Must be _ID or a defined CCT field name.', domain: 'novamira-pro'),
            $orderby,
        ));
    }

    /** @var array<string, mixed> $raw_where */
    $raw_where = is_array($input['where'] ?? null) ? $input['where'] : [];
    $where_args = lcr_validate_where($raw_where, $known_fields);
    if ($where_args instanceof WP_Error) {
        return $where_args;
    }

    return [
        'limit' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
        'where_args' => $where_args,
    ];
}

/**
 * @param array{cct_slug: string, limit?: int, offset?: int, where?: array<string, mixed>, orderby?: string, order?: 'asc'|'desc'} $input
 * @return array{items: list<array<string, mixed>>, total: int, has_more: bool, next_offset: int|null}|WP_Error
 */
function jetengine_list_cct_records(array $input): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $input['cct_slug'];
    if ($slug === '') {
        return new WP_Error('invalid_input', __('cct_slug is required.', domain: 'novamira-pro'));
    }

    $factory = jetengine_cct_get_factory($slug);
    if ($factory === null) {
        return new WP_Error('cct_not_found', __('CCT not found.', domain: 'novamira-pro'));
    }

    $params = lcr_resolve_query_params($input, lcr_known_fields($factory));
    if ($params instanceof WP_Error) {
        return $params;
    }

    /** @var mixed $raw_db */
    $raw_db = $factory->get_db();
    if (!$raw_db instanceof CctDB) {
        return new WP_Error('cct_db_unavailable', __('CCT DB layer unavailable.', domain: 'novamira-pro'));
    }

    /** @var list<array<string, string>> $order_clause */
    $order_clause = [['orderby' => $params['orderby'], 'order' => $params['order']]];

    $items = $raw_db->query($params['where_args'], $params['limit'], $params['offset'], $order_clause);

    $total = (int) $raw_db->count($params['where_args']);
    $count = count($items);
    $has_more = ($params['offset'] + $count) < $total;

    return [
        'items' => $items,
        'total' => $total,
        'has_more' => $has_more,
        'next_offset' => $has_more ? $params['offset'] + $count : null,
    ];
}
