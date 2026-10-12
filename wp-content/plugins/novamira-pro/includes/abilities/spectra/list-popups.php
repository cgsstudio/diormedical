<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-list-popups', [
    'label' => __('List Spectra Popups', domain: 'novamira-pro'),
    'description' => __(
        'Enumerate Spectra Popup Builder posts (`spectra-popup` CPT) as compact records: id, title, status, popup_type (modal|slide-in|banner|…), enabled (bool), modified. Filters: status (WP post_status; default: publish+draft+private), enabled (bool), popup_type (exact match), search (title substring). Progressive-disclosure: use novamira/spectra-get-popup for full content and full meta (Sprint 2).',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'status' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'WP post_status values to include. Defaults to [publish, draft, private].',
            ],
            'enabled' => [
                'type' => 'boolean',
                'description' => 'When set, restricts to popups whose spectra-popup-enabled meta matches the boolean.',
            ],
            'popup_type' => [
                'type' => 'string',
                'description' => 'Exact match on the spectra-popup-type meta (e.g. `modal`).',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match on the popup title.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Max rows returned (default 100, hard cap 500 — progressive disclosure).',
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Skip N rows before returning results (default 0).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'popups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'popup_type' => ['type' => 'string'],
                        'enabled' => ['type' => 'boolean'],
                        'modified' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'title', 'status', 'popup_type', 'enabled', 'modified'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
        'required' => ['popups', 'total', 'limit', 'offset', 'truncated'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_list_popups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact popup discovery. Use before create/edit/delete popup operations to pick the right id.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

const SPECTRA_LIST_POPUPS_DEFAULT_LIMIT = 100;

const SPECTRA_LIST_POPUPS_MAX_LIMIT = 500;

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_list_popups(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $limit = spectra_coerce_int($input['limit'] ?? SPECTRA_LIST_POPUPS_DEFAULT_LIMIT, minimum: 1);
    if ($limit > SPECTRA_LIST_POPUPS_MAX_LIMIT) {
        $limit = SPECTRA_LIST_POPUPS_MAX_LIMIT;
    }
    $offset = spectra_coerce_int($input['offset'] ?? 0, minimum: 0);

    if (!post_type_exists(SPECTRA_POPUP_POST_TYPE)) {
        // Echo the caller's pagination so the response never lies about the
        // input it received — pagination debugging on a fresh install stays
        // straightforward.
        return spectra_list_popups_empty($limit, $offset);
    }

    $query = new WP_Query(spectra_list_popups_query_args($input, $limit, $offset));
    $popups = spectra_list_popups_records($query);
    $total = (int) $query->found_posts;

    return [
        'popups' => $popups,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'truncated' => $total > ($offset + $limit),
    ];
}

/**
 * Empty envelope for the CPT-not-registered early-exit. Kept out of the main
 * callback so the branch counts stay under the complexity budget, and takes
 * the caller-visible limit + offset so the response echoes back the exact
 * pagination the caller asked for.
 *
 * @return array<string, mixed>
 */
function spectra_list_popups_empty(int $limit, int $offset): array
{
    return [
        'popups' => [],
        'total' => 0,
        'limit' => $limit,
        'offset' => $offset,
        'truncated' => false,
    ];
}

/**
 * Build the `WP_Query` argument bag from the ability input. Delegates
 * meta filter construction to `spectra_list_popups_meta_query()` so this
 * function only knows about post-status + pagination + search.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function spectra_list_popups_query_args(array $input, int $limit, int $offset): array
{
    $args = [
        'post_type' => SPECTRA_POPUP_POST_TYPE,
        'post_status' => spectra_list_popups_statuses($input),
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'modified',
        'order' => 'DESC',
        'no_found_rows' => false,
        'update_post_term_cache' => false,
    ];
    $meta_query = spectra_list_popups_meta_query($input);
    if ($meta_query !== []) {
        $args['meta_query'] = $meta_query;
    }
    if (isset($input['search']) && is_string($input['search']) && $input['search'] !== '') {
        $args['s'] = $input['search'];
    }
    return $args;
}

/**
 * @param array<string, mixed> $input
 * @return list<string>
 */
function spectra_list_popups_statuses(array $input): array
{
    $default = ['publish', 'draft', 'private'];
    /** @var mixed $raw */
    $raw = $input['status'] ?? null;
    if (!is_array($raw)) {
        return $default;
    }
    $filtered = [];
    /** @var mixed $s */
    foreach ($raw as $s) {
        if (!is_string($s) || $s === '') {
            continue;
        }
        $filtered[] = $s;
    }
    return $filtered !== [] ? $filtered : $default;
}

/**
 * Build the `meta_query` bag from the enabled + popup_type filters.
 *
 * The `enabled` filter is a true round-trip against `sp_popup_enabled_flag`
 * (helpers.php): the reader accepts `'1' | 1 | true | 'on' | 'true'` as
 * truthy — anything else is falsy. Splitting the filter into two `IN`
 * sub-clauses (one for truthy, one for the falsy set that includes the
 * meta-missing case) means a `list-popups(enabled=X)` result never
 * disagrees with a subsequent `get-popup(id).enabled`.
 *
 * @param array<string, mixed> $input
 * @return list<array<array-key, mixed>>
 */
function spectra_list_popups_meta_query(array $input): array
{
    $meta_query = [];
    if (isset($input['enabled']) && is_bool($input['enabled'])) {
        $meta_query[] = $input['enabled']
            ? spectra_list_popups_enabled_truthy_clause()
            : spectra_list_popups_enabled_falsy_clause();
    }
    if (isset($input['popup_type']) && is_string($input['popup_type']) && $input['popup_type'] !== '') {
        $meta_query[] = [
            'key' => SPECTRA_POPUP_META_TYPE,
            'value' => $input['popup_type'],
            'compare' => '=',
        ];
    }
    return $meta_query;
}

/**
 * `enabled=true` filter — matches every truthy token the reader
 * (`sp_popup_enabled_flag`) accepts.
 *
 * @return array<string, mixed>
 */
function spectra_list_popups_enabled_truthy_clause(): array
{
    return [
        'key' => SPECTRA_POPUP_META_ENABLED,
        'value' => ['1', 1, 'on', 'true'],
        'compare' => 'IN',
    ];
}

/**
 * `enabled=false` filter — matches popups whose meta row is missing (fresh
 * popup, never toggled) OR whose stored value is one of the accepted falsy
 * tokens. Two clauses joined with `OR` so `WP_Query` emits a compound
 * `(m.meta_key IS NULL OR m.meta_value IN (…))` predicate.
 *
 * @return array<array-key, mixed>
 */
function spectra_list_popups_enabled_falsy_clause(): array
{
    return [
        'relation' => 'OR',
        [
            'key' => SPECTRA_POPUP_META_ENABLED,
            'compare' => 'NOT EXISTS',
        ],
        [
            'key' => SPECTRA_POPUP_META_ENABLED,
            'value' => ['', '0', 0, 'off', 'false'],
            'compare' => 'IN',
        ],
    ];
}

/**
 * Map `WP_Query` results into compact popup records.
 *
 * @return list<array<string, mixed>>
 */
function spectra_list_popups_records(WP_Query $query): array
{
    $popups = [];
    foreach ($query->posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        $meta = sp_popup_meta_read($post->ID);
        $popups[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'popup_type' => $meta['type'],
            'enabled' => $meta['enabled'],
            'modified' => $post->post_modified_gmt,
        ];
    }
    return $popups;
}
