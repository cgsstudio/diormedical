<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Oxygen form submissions are stored as posts of the
 * `breakdance_form_res` CPT — this CPT name is NOT mode-branched, so it
 * is `breakdance_form_res` in Oxygen mode too. Its METADATA, however, IS
 * mode-prefixed: Oxygen writes `_oxygen_fields`, `_oxygen_form_id`,
 * `_oxygen_post_id`, `_oxygen_ip`, `_oxygen_referer`, `_oxygen_user_agent`,
 * `_oxygen_user_id`, `_oxygen_form_actions`, `_oxygen_uploads`. We derive
 * every metadata key from `ox_meta_prefix()` so reads/filters see the
 * submissions the Oxygen forms engine actually stored. Because a database can
 * retain standalone-Breakdance rows after a builder switch, every operation
 * also requires the mode-specific `_oxygen_form_id` marker. This prevents an
 * Oxygen ability from exposing or deleting a Breakdance-owned row that happens
 * to share the CPT. Three operations: list, get, delete.
 */

// list-form-submissions -----------------------------------------------------

wp_register_ability('novamira/oxygen-list-form-submissions', [
    'label' => __('List Oxygen Form Submissions', domain: 'novamira-pro'),
    'description' => __(
        'Lists Oxygen-owned rows from the shared form-submissions CPT (`breakdance_form_res`). Ownership is proven by the mode-specific `_oxygen_form_id` metadata marker, so retained standalone-Breakdance submissions are never exposed. Each row carries `id`, the source `form_id`, the host `post_id`, submission date, status, IP, referer, and the count of fields. Filter by `form_id` to scope to a single form.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'description' => 'Optional. Restrict to submissions from this form.'],
            'limit' => [
                'type' => 'integer',
                'description' => 'Rows per page. Default 100. Server clamps to 500. Combine with `page` to walk older submissions.',
                'default' => 100,
            ],
            'page' => [
                'type' => 'integer',
                'description' => '1-indexed page number for pagination (each page holds `limit` rows, newest first). Default 1. The response reports `total`, `total_pages` and `truncated` so you can page through the full history.',
                'default' => 1,
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['unread', 'read', 'any'],
                'description' => 'Submission status filter. Oxygen stores submissions with `unread` (default on insert) and flips to `read` when an admin opens the row; `any` returns both. The `publish` / `draft` statuses used by ordinary CPTs do NOT apply here — passing them would silently return zero rows.',
                'default' => 'any',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'submissions' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
            'page' => ['type' => 'integer'],
            'per_page' => ['type' => 'integer'],
            'total_pages' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
        ],
        'required' => ['submissions', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_list_form_submissions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Submissions can contain sensitive data — only the row summary is returned here. Pair with get-form-submission to read field values for a specific id.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// get-form-submission -------------------------------------------------------

wp_register_ability('novamira/oxygen-get-form-submission', [
    'label' => __('Get Oxygen Form Submission', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full payload of an Oxygen form submission by id: `id`, `form_id`, the host `post_id`, submission date, status, `fields` (the actual submitted values), `ip`, `referer`, `user_agent`, `user_id`, `form_actions` (the actions run on submit, with their statuses), and `uploads` (the files attached to the submission, as a safe public shape — `fieldId`, public `url`, `type`; the absolute server-side filesystem path each FormFile also carries is stripped). Applies progressive disclosure: every leaf string is capped at `max_field_bytes` (64 KiB by default) with a `(truncated, N bytes)` marker, cut on a UTF-8 boundary.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'max_field_bytes' => [
                'type' => 'integer',
                'description' => 'Per-string truncation cap on every value in `fields` (and every leaf string inside `form_actions` / `uploads`). When a value is longer than the cap, it is truncated and a `(truncated, N bytes)` marker is appended. Default 65_536 (64 KiB); raise it if you really need the full content.',
                'default' => 65_536,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'submission' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_get_form_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Contains the actual user-submitted data — treat the response as sensitive. Avoid logging or echoing raw `fields` content unless you need it.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// delete-form-submission ----------------------------------------------------

wp_register_ability('novamira/oxygen-delete-form-submission', [
    'label' => __('Delete Oxygen Form Submission', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an Oxygen form submission. With the default `force=false` the row is TRASHED (recoverable) via an explicit trash call, and its uploaded files are preserved. With `force=true` the row is permanently deleted and Oxygen\'s before_delete_post hook also removes the submission\'s uploaded files. The response `mode` reflects what actually happened: `trashed` or `force_deleted`.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'force' => [
                'type' => 'boolean',
                'description' => 'Permanently delete (and remove the uploaded files) instead of trashing. Default false = trash, recoverable.',
                'default' => false,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'mode' => ['type' => 'string'],
            'deleted_id' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_delete_form_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default is trash — recoverable from the WP admin trash (verified: this CPT keeps the trashed row on Oxygen 6). Force-delete only when the user does not need the data again. The `mode` field reports what actually happened, verified from the row\'s final state — a delete blocked by another plugin returns oxygen_write_not_applied, not a false success.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

// callbacks -----------------------------------------------------------------

/**
 * Compact summary row used by list-form-submissions.
 *
 * @return array<string, mixed>
 */
function ox_form_submission_summary(WP_Post $post): array
{
    $prefix = ox_meta_prefix();
    /** @var mixed $fields_raw */
    $fields_raw = get_post_meta(post_id: $post->ID, key: $prefix . 'fields', single: true);
    $fields = is_array($fields_raw) ? $fields_raw : [];
    return [
        'id' => $post->ID,
        'form_id' => (int) get_post_meta(post_id: $post->ID, key: $prefix . 'form_id', single: true),
        'post_id' => (int) get_post_meta(post_id: $post->ID, key: $prefix . 'post_id', single: true),
        'submitted_at' => $post->post_date,
        'status' => $post->post_status,
        'ip' => (string) get_post_meta(post_id: $post->ID, key: $prefix . 'ip', single: true),
        'referer' => (string) get_post_meta(post_id: $post->ID, key: $prefix . 'referer', single: true),
        'field_count' => count($fields),
    ];
}

/**
 * The submissions CPT is shared by name between both Soflyy modes, but its
 * metadata is not. Treat the native mode-specific form-id key as the ownership
 * marker so a stale Breakdance row cannot cross the Oxygen ability boundary.
 */
function ox_form_submission_belongs_to_oxygen(WP_Post $post): bool
{
    return (
        $post->post_type === 'breakdance_form_res'
        && metadata_exists('post', $post->ID, ox_meta_prefix() . 'form_id')
    );
}

/**
 * Recursively strip absolute filesystem paths from an uploads structure.
 * Oxygen stores each FormFile with server-side path keys (`path`, `tmp_name`,
 * `basedir`, …) next to the public `url`; those must never leak through the
 * ability. Everything else (fieldId, name, url, size, type) is kept.
 *
 * @param mixed $data
 * @return mixed
 */
function ox_redact_upload_paths(mixed $data): mixed
{
    if (!is_array($data)) {
        return $data;
    }
    $sensitive = ['path', 'tmp_name', 'tmpname', 'basedir', 'dir', 'file', 'filepath', 'absolutepath', 'fullpath'];
    $out = [];
    /** @var mixed $value */
    foreach ($data as $key => $value) {
        if (is_string($key) && in_array(strtolower($key), $sensitive, strict: true)) {
            continue;
        }
        $out[$key] = ox_redact_upload_paths($value);
    }
    return $out;
}

/**
 * @return array<string, mixed>
 */
function ox_form_submission_to_array(WP_Post $post): array
{
    $prefix = ox_meta_prefix();
    $base = ox_form_submission_summary($post);
    /** @var mixed $fields_raw */
    $fields_raw = get_post_meta(post_id: $post->ID, key: $prefix . 'fields', single: true);
    /** @var mixed $actions_raw */
    $actions_raw = get_post_meta(post_id: $post->ID, key: $prefix . 'form_actions', single: true);
    /** @var mixed $uploads_raw */
    $uploads_raw = get_post_meta(post_id: $post->ID, key: $prefix . 'uploads', single: true);

    return array_merge($base, [
        'fields' => is_array($fields_raw) ? $fields_raw : [],
        'user_agent' => (string) get_post_meta(post_id: $post->ID, key: $prefix . 'user_agent', single: true),
        'user_id' => (int) get_post_meta(post_id: $post->ID, key: $prefix . 'user_id', single: true),
        'form_actions' => is_array($actions_raw) ? $actions_raw : [],
        // Project the uploads to a safe public shape — the raw FormFile entries
        // carry absolute server-side filesystem paths that must not leak.
        'uploads' => is_array($uploads_raw) ? ox_redact_upload_paths($uploads_raw) : [],
    ]);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function oxygen_list_form_submissions(array $input): array
{
    $per_page = min(500, max(1, ox_coerce_int($input['limit'] ?? 100)));
    $page = max(1, ox_coerce_int($input['page'] ?? 1));
    $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : 'any';

    $args = [
        'post_type' => 'breakdance_form_res',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC',
        // Submissions are written with post_status `unread` and flip to `read`
        // once an admin opens them — never `publish` / `draft` (the ordinary
        // CPT enum would silently return zero rows).
        'post_status' => $status === 'any' ? ['unread', 'read'] : [$status],
        // `breakdance_form_res` is not mode-branched. Require Oxygen's native
        // metadata marker so legacy/retained Breakdance rows remain invisible.
        'meta_query' => [[
            'key' => ox_meta_prefix() . 'form_id',
            'compare' => 'EXISTS',
        ]],
    ];

    if (isset($input['form_id'])) {
        $form_id = ox_coerce_int($input['form_id']);
        if ($form_id > 0) {
            $args['meta_query'][] = [
                'key' => ox_meta_prefix() . 'form_id',
                'value' => (string) $form_id,
                'compare' => '=',
            ];
        }
    }

    // WP_Query (not get_posts) so `found_posts` gives the DB-level total across
    // ALL pages — without it a caller can only ever reach the newest `limit`
    // rows and older history is unreachable.
    $query = new \WP_Query($args);
    $rows = [];
    /** @var mixed $post */
    foreach ($query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $rows[] = ox_form_submission_summary($post);
    }

    $total = (int) $query->found_posts;
    $total_pages = (int) ceil($total / $per_page);
    return [
        'submissions' => $rows,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'truncated' => $page < $total_pages,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_get_form_submission(array $input): array|WP_Error
{
    $id = ox_coerce_int($input['id'] ?? 0);
    if ($id <= 0) {
        return ox_error('oxygen_invalid_input', __('`id` must be a positive integer.', domain: 'novamira-pro'));
    }
    $post = ox_post_or_error($id, ['breakdance_form_res']);
    if ($post instanceof WP_Error) {
        return $post;
    }
    if (!ox_form_submission_belongs_to_oxygen($post)) {
        return ox_error('oxygen_not_found', sprintf(
            __('Oxygen form submission %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $max_field_bytes = max(1, ox_coerce_int($input['max_field_bytes'] ?? 65_536));
    $submission = ox_form_submission_to_array($post);
    $submission = ox_truncate_submission_strings($submission, $max_field_bytes);
    return ['success' => true, 'submission' => $submission];
}

/**
 * Walk a submission payload recursively and truncate every leaf string
 * longer than `$max_bytes`. Truncated values get a
 * `(truncated, N bytes)` suffix so the agent can see the cut. Non-
 * string leaves (ints, bools, arrays) are left intact.
 *
 * @param array<int|string, mixed> $payload
 * @return array<int|string, mixed>
 */
function ox_truncate_submission_strings(array $payload, int $max_bytes): array
{
    /** @var mixed $value */
    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            $payload[$key] = ox_truncate_submission_strings($value, $max_bytes);
            continue;
        }
        if (!is_string($value) || strlen($value) <= $max_bytes) {
            continue;
        }
        // mb_strcut (NOT substr) so the byte cap never splits a multibyte
        // character — a lone continuation byte would corrupt the JSON encode.
        $payload[$key] = sprintf(
            '%s (truncated, %d bytes)',
            mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8'),
            strlen($value),
        );
    }
    return $payload;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_delete_form_submission(array $input): array|WP_Error
{
    $id = ox_coerce_int($input['id'] ?? 0);
    if ($id <= 0) {
        return ox_error('oxygen_invalid_input', __('`id` must be a positive integer.', domain: 'novamira-pro'));
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post || !ox_form_submission_belongs_to_oxygen($post)) {
        return ox_error('oxygen_not_found', sprintf(
            __('Oxygen form submission %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $force = ox_coerce_bool($input['force'] ?? false);
    // Honour the safe default: `wp_delete_post($id, false)` only auto-trashes
    // core post/page types, so on this CPT it would HARD-delete the row AND
    // (via the engine's before_delete_post hook) the uploaded files. Call
    // wp_trash_post() explicitly for the non-force path so the row and its
    // attachments are recoverable; force=true still hard-deletes.
    $result = $force ? wp_delete_post(post_id: $id, force_delete: true) : wp_trash_post($id);
    if ($result === false || $result === null) {
        return ox_error('oxygen_invalid_input', __('Deleting the submission failed.', domain: 'novamira-pro'));
    }
    // Verify the FINAL state, not the truthy return — a pre_trash_post /
    // pre_delete_post short-circuit reports success while leaving the row.
    $mode = ox_verify_delete_outcome($id, $force);
    if ($mode instanceof WP_Error) {
        return $mode;
    }
    return [
        'success' => true,
        'mode' => $mode,
        'deleted_id' => $id,
    ];
}
