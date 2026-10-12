<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

use WP_Post;
use WPCF7_ContactForm;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-list-forms', [
    'label' => __('List Contact Form 7 Forms', domain: 'novamira-pro'),
    'description' => __(
        'Lists Contact Form 7 forms with optional filters. Response is a compact summary per form — id, title, slug, status, locale, hash, tag_count, modified — WITHOUT the heavy properties (`form` template, `mail`, `mail_2`, `messages`, `additional_settings`) so listings stay small. Call cf7-get-form with a specific id to read the full body. Filters: `status` (publish|draft|pending|private|trash — defaults to publish+draft, trash must be requested explicitly), `locale` (e.g. "it" or "en_US"), `search` (case-insensitive substring against title and slug). Pagination: `limit` (default 50, hard cap 500 enforced by the schema — out-of-range values are rejected at the REST/MCP layer; direct PHP callers are clamped defensively), `offset` (default 0, must be ≥0). `total` in the response always reflects the unpaginated match count so the agent knows whether to keep paging. NOTE: under WPML or Polylang the query is scoped to the current admin language by the plugins\' own `pre_get_posts` filters — call check-setup first to detect `wpml_active`/`polylang_active` if cross-language enumeration matters.',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'status' => [
                'type' => 'string',
                'description' => 'Restrict to a single CPT post_status. Omit to include publish + draft (trash is always excluded).',
                'enum' => ['publish', 'draft', 'pending', 'private', 'trash'],
            ],
            'locale' => [
                'type' => 'string',
                'description' => 'Restrict to forms whose locale matches exactly (e.g. "it" or "en_US").',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match against the form title and slug. Use when the user asks for a form by name ("the contact form on the home page") rather than by id.',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of form summaries to return. Default 50, hard cap 500.',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 50,
            ],
            'offset' => [
                'type' => 'integer',
                'description' => 'Number of matching forms to skip before returning records (for paging). Default 0.',
                'minimum' => 0,
                'default' => 0,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'forms' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['forms', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_list_forms',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use filters (status, locale, search) to keep the list focused. The summary omits heavy properties for size — fetch the body via cf7-get-form only for forms you actually want to read or edit.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{status?: string, locale?: string, search?: string, limit?: int, offset?: int} $input
 * @return array{forms: list<array<string, mixed>>, total: int}
 */
function cf7_list_forms(array $input): array
{
    // Schema validation has already clamped min/max; apply defensive
    // caps for direct PHP callers.
    $limit = isset($input['limit']) ? max(1, min(500, $input['limit'])) : 50;
    $offset = isset($input['offset']) ? max(0, $input['offset']) : 0;
    $status_filter = $input['status'] ?? '';
    $locale_filter = $input['locale'] ?? '';
    $search = isset($input['search']) ? strtolower(trim($input['search'])) : '';

    // Defense-in-depth: if a direct PHP caller bypasses the schema enum
    // (round 2 audit L2-A2 / L3-A2), reject any unrecognised status —
    // otherwise WP_Query silently drops the post_status clause and the
    // call returns every form including trash.
    $allowed_status = ['publish', 'draft', 'pending', 'private', 'trash'];
    if ($status_filter !== '' && !in_array($status_filter, $allowed_status, strict: true)) {
        return ['forms' => [], 'total' => 0];
    }

    // Two-stage strategy (round 2 audit L4-A2):
    //   stage 1 — pull only lightweight WP_Post records (no postmeta
    //     blob) via get_posts(), pushing post_status / locale filters
    //     down to MySQL via WHERE + meta_query.
    //   stage 2 — apply the search filter in PHP on title + slug.
    //   stage 3 — hydrate full WPCF7_ContactForm objects only for the
    //     paginated window. Avoids loading every form's full property
    //     bag into memory on installs with hundreds / thousands of forms.
    $post_args = [
        'post_type' => 'wpcf7_contact_form',
        'post_status' => $status_filter !== '' ? $status_filter : ['publish', 'draft'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'orderby' => 'date',
        'order' => 'DESC',
    ];
    if ($locale_filter !== '') {
        $post_args['meta_query'] = [
            ['key' => '_locale', 'value' => $locale_filter, 'compare' => '='],
        ];
    }

    /** @var list<WP_Post> $candidates */
    $candidates = get_posts($post_args);

    $matched_ids = [];
    foreach ($candidates as $post) {
        if ($search !== '') {
            $haystack = strtolower($post->post_title . "\n" . $post->post_name);
            if (!str_contains($haystack, $search)) {
                continue;
            }
        }
        $matched_ids[] = $post->ID;
    }

    $total = count($matched_ids);
    $page_ids = array_slice($matched_ids, $offset, $limit);
    $out = [];
    foreach ($page_ids as $id) {
        $form = WPCF7_ContactForm::get_instance($id);
        if ($form === null) {
            continue;
        }
        $out[] = cf7_form_to_compact($form);
    }

    return ['forms' => $out, 'total' => $total];
}
