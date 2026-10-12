<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraBlocks;

use WP_Error;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-blocks-list-popups', [
    'label' => __('List Spectra Blocks Popups', domain: 'novamira-pro'),
    'description' => __(
        'Enumerate Spectra Blocks Popup Builder posts (`spectra-blocks-popup` CPT) as compact records: id, title, status, popup_type (spectra-blocks-popup-type meta; `unset` until first configured), enabled (bool), repetition (number), modified. Filters: status (subset of publish, draft, private, pending, future; default publish+draft+private — trash and auto-draft are never listable), limit (default 100, hard cap 500), offset. Progressive-disclosure: use novamira/spectra-blocks-get-popup for the full block markup of one popup. The migration-compat `spectra-popup` CPT is not listed here — check-setup reports its count.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-blocks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'status' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'private', 'pending', 'future'],
                ],
                'maxItems' => 5,
                'uniqueItems' => true,
                'description' => 'WP post_status values to include. Defaults to [publish, draft, private]. Trash and auto-draft are not accepted — trashed popups are only visible as a count in check-setup.',
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
                        'repetition' => ['type' => 'number'],
                        'modified' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'title', 'status', 'popup_type', 'enabled', 'repetition', 'modified'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraBlocks\\spectra_blocks_list_popups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact popup discovery. Use to pick the right popup id before a full get-popup read.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

const SPECTRA_BLOCKS_LIST_POPUPS_DEFAULT_LIMIT = 100;

const SPECTRA_BLOCKS_LIST_POPUPS_MAX_LIMIT = 500;

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_blocks_list_popups(array $input): array|WP_Error
{
    if (!spectra_blocks_plugin_available()) {
        return sb_not_active();
    }

    $limit = sb_coerce_int($input['limit'] ?? SPECTRA_BLOCKS_LIST_POPUPS_DEFAULT_LIMIT, minimum: 1);
    if ($limit > SPECTRA_BLOCKS_LIST_POPUPS_MAX_LIMIT) {
        $limit = SPECTRA_BLOCKS_LIST_POPUPS_MAX_LIMIT;
    }
    $offset = sb_coerce_int($input['offset'] ?? 0, minimum: 0);

    $statuses = sb_list_popups_statuses($input);
    if ($statuses instanceof WP_Error) {
        return $statuses;
    }

    if (!post_type_exists(SPECTRA_BLOCKS_POPUP_POST_TYPE)) {
        // Echo the caller's pagination so the response never lies about the
        // input it received — pagination debugging on a fresh install stays
        // straightforward.
        return [
            'popups' => [],
            'total' => 0,
            'limit' => $limit,
            'offset' => $offset,
            'truncated' => false,
        ];
    }

    $query = new WP_Query([
        'post_type' => SPECTRA_BLOCKS_POPUP_POST_TYPE,
        'post_status' => $statuses,
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'modified',
        'order' => 'DESC',
        'no_found_rows' => false,
        'update_post_term_cache' => false,
    ]);

    $popups = [];
    foreach ($query->posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        // Re-validate every row through the shared gate: the query is already
        // restricted to the eligible statuses and post type, so this only
        // drops rows that changed between the query and the read (e.g. a
        // concurrent trash) — the same rows get-popup would refuse.
        if (sb_post_or_error($post->ID) instanceof WP_Error) {
            continue;
        }
        $meta = sb_popup_meta_read($post->ID);
        $popups[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'popup_type' => $meta['type'],
            'enabled' => $meta['enabled'],
            'repetition' => $meta['repetition'],
            'modified' => $post->post_modified_gmt,
        ];
    }
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
 * Post statuses a caller may request. Trash and auto-draft are deliberately
 * absent: soft-deleted and never-saved rows are not listable through this
 * surface (check-setup exposes the trash count), matching the sb_post_or_error
 * gate that get-popup applies per row.
 *
 * @var list<string>
 */
const SPECTRA_BLOCKS_LIST_POPUPS_ALLOWED_STATUSES = ['publish', 'draft', 'private', 'pending', 'future'];

/**
 * Resolve the status filter, defaulting to the visible-content window
 * (publish + draft + private). Any status outside the whitelist — including
 * an explicit `trash` or `auto-draft` request — is rejected with
 * spectra_blocks_invalid_input rather than silently dropped, in addition to
 * the schema-level enum, so the guarantee holds even for callers that reach
 * the callback without schema validation.
 *
 * @param array<string, mixed> $input
 * @return list<string>|WP_Error
 */
function sb_list_popups_statuses(array $input): array|WP_Error
{
    $default = ['publish', 'draft', 'private'];
    /** @var mixed $raw */
    $raw = $input['status'] ?? null;
    if (!is_array($raw)) {
        return $default;
    }
    $filtered = [];
    /** @var mixed $status */
    foreach ($raw as $status) {
        if (!is_string($status) || $status === '') {
            continue;
        }
        if (!in_array($status, SPECTRA_BLOCKS_LIST_POPUPS_ALLOWED_STATUSES, strict: true)) {
            return sb_invalid_input(
                'status must be a subset of: ' . implode(', ', SPECTRA_BLOCKS_LIST_POPUPS_ALLOWED_STATUSES),
            );
        }
        $filtered[] = $status;
    }
    return $filtered !== [] ? $filtered : $default;
}
