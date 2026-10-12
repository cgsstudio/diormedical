<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-list-form-blocks', [
    'label' => __('List Spectra Form Blocks', domain: 'novamira-pro'),
    'description' => __(
        'Locate posts / pages that contain at least one Spectra `uagb/forms` block. Returns compact records (id, title, status, post_type, form_count) so an agent can pick a target for edit-block via novamira/gutenberg-*. Bounded LIKE query with a hard 500-row cap and a `truncated` flag — the ability never scans the entire posts table on production stores. UAGB Free does NOT persist form submissions; use novamira/execute-php + the `uagb_form_success` action hook for that surface.',
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
            'post_type' => [
                'type' => 'string',
                'description' => 'Restrict to one post type. Default: any public post_type.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Row cap (default 100, hard cap 500).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'posts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'post_type' => ['type' => 'string'],
                        'form_count' => ['type' => 'integer'],
                    ],
                    'required' => ['id', 'title', 'status', 'post_type', 'form_count'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
        'required' => ['posts', 'total', 'truncated'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_list_form_blocks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Bounded scan (LIKE + LIMIT 500). form_count counts uagb/forms markers in each post.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/** Default row cap for the list-form-blocks scan. */
const SPECTRA_LIST_FORM_BLOCKS_DEFAULT_LIMIT = 100;

/** Hard upper bound on the row cap — the bounded LIKE query is planned around this. */
const SPECTRA_LIST_FORM_BLOCKS_MAX_LIMIT = 500;

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_list_form_blocks(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $limit = spectra_coerce_int($input['limit'] ?? SPECTRA_LIST_FORM_BLOCKS_DEFAULT_LIMIT, minimum: 1);
    if ($limit > SPECTRA_LIST_FORM_BLOCKS_MAX_LIMIT) {
        $limit = SPECTRA_LIST_FORM_BLOCKS_MAX_LIMIT;
    }

    $statuses = spectra_list_form_blocks_statuses($input);
    $post_type = spectra_list_form_blocks_post_type($input);

    global $wpdb;
    /** @var \wpdb $wpdb */

    $rows = spectra_list_form_blocks_query($wpdb, $statuses, $post_type, $limit);
    $posts = [];
    foreach ($rows as $row) {
        /** @var mixed $id */
        $id = $row->ID ?? 0;
        /** @var mixed $title */
        $title = $row->post_title ?? '';
        /** @var mixed $status */
        $status = $row->post_status ?? '';
        /** @var mixed $pt */
        $pt = $row->post_type ?? '';
        /** @var mixed $content */
        $content = $row->post_content ?? '';
        $posts[] = [
            'id' => is_numeric($id) ? (int) $id : 0,
            'title' => is_string($title) ? $title : '',
            'status' => is_string($status) ? $status : '',
            'post_type' => is_string($pt) ? $pt : '',
            'form_count' => is_string($content) ? substr_count($content, needle: '<!-- wp:uagb/forms ') : 0,
        ];
    }

    return [
        'posts' => $posts,
        'total' => count($posts),
        'truncated' => count($posts) === $limit,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return list<string>
 */
function spectra_list_form_blocks_statuses(array $input): array
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
 * @param array<string, mixed> $input
 */
function spectra_list_form_blocks_post_type(array $input): string
{
    if (isset($input['post_type']) && is_string($input['post_type']) && $input['post_type'] !== '') {
        return $input['post_type'];
    }
    return '';
}

/**
 * Snapshot of every public post_type currently registered. Used as the
 * default filter for `list-form-blocks` so the scan skips revisions and
 * other internal CPTs — matches the description's "any public post_type"
 * contract.
 *
 * @return list<string>
 */
function spectra_public_post_types(): array
{
    $out = [];
    foreach (get_post_types(['public' => true], output: 'names') as $type) {
        if ($type === '') {
            continue;
        }
        $out[] = $type;
    }
    // Guard against a plugin having unregistered every public post type —
    // fall back to WP core defaults so the query is never emitted with an
    // empty `IN ()` clause.
    return $out !== [] ? $out : ['post', 'page'];
}

/**
 * Execute the bounded LIKE scan on `wp_posts`. Extracted so the callback
 * stays under the complexity budget and so the query is grep-visible in
 * a single place for future performance work.
 *
 * @param list<string> $statuses
 * @return list<object>
 */
function spectra_list_form_blocks_query(\wpdb $wpdb, array $statuses, string $post_type, int $limit): array
{
    // Placeholders scale with the status list — build once so the prepare
    // call has the right shape.
    $status_placeholders = implode(', ', array_fill(start_index: 0, count: count($statuses), value: '%s'));

    // When the caller didn't restrict `post_type`, restrict to public post
    // types only so the scan skips revisions, nav-menu items, oembed cache,
    // and other internal CPTs an agent has no reason to touch (matches the
    // "Default: any public post_type" contract in the input_schema).
    $post_type_values = $post_type !== '' ? [$post_type] : spectra_public_post_types();
    $post_type_placeholders = implode(', ', array_fill(start_index: 0, count: count($post_type_values), value: '%s'));

    $sql =
        "SELECT ID, post_title, post_status, post_type, post_content FROM {$wpdb->posts}"
        . " WHERE post_status IN ({$status_placeholders})"
        . " AND post_type IN ({$post_type_placeholders})"
        . ' AND post_content LIKE %s'
        . ' ORDER BY post_modified DESC'
        . ' LIMIT %d';

    $args = $statuses;
    foreach ($post_type_values as $pt) {
        $args[] = $pt;
    }
    $args[] = '%<!-- wp:uagb/forms %';
    $args[] = $limit;

    // Table name (`$wpdb->posts`) is a WP-internal identifier, not user input,
    // so its interpolation into the literal SELECT stays safe. Every dynamic
    // value goes through prepare placeholders.
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare($sql, $args);
    if (!is_string($prepared)) {
        return [];
    }
    /** @var list<object>|null $rows */
    $rows = $wpdb->get_results($prepared);
    return is_array($rows) ? $rows : [];
}
