<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Breakdance form submissions are stored as posts of the
 * `breakdance_form_res` CPT. Each post carries `_breakdance_form_id`,
 * `_breakdance_post_id`, `_breakdance_fields` (the submitted fields),
 * `_breakdance_ip`, `_breakdance_referer`, `_breakdance_user_agent`,
 * `_breakdance_user_id`, `_breakdance_form_actions`,
 * `_breakdance_uploads`. Oxygen and standalone Breakdance never load together,
 * but an import or builder replacement can leave Oxygen-owned rows in the same
 * database table. We expose three operations: list, get, delete, and identify
 * ownership through Breakdance's native form-id marker.
 */

// list-form-submissions -----------------------------------------------------

wp_register_ability('novamira/breakdance-list-form-submissions', [
    'label' => __('List Breakdance Form Submissions', domain: 'novamira-pro'),
    'description' => __(
        'Lists Breakdance-owned rows from the form-submissions CPT (`breakdance_form_res`). Oxygen and standalone Breakdance are mutually exclusive at runtime; the `_breakdance_form_id` marker only prevents residual or imported foreign rows from being exposed. Each row carries `id`, the source `form_id`, the host `post_id` (page that hosted the form), submission date, status, IP, referer, and the count of fields. Filter by `form_id` to scope to a single form. Use `get-form-submission` to read the full field values.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'description' => 'Optional. Restrict to submissions from this form.'],
            'limit' => ['type' => 'integer', 'description' => 'Maximum rows to return. Default 100.', 'default' => 100],
            'status' => [
                'type' => 'string',
                'enum' => ['unread', 'read', 'any'],
                'description' => 'Submission status filter. Breakdance stores submissions with `unread` (default on insert) and flips to `read` when an admin opens the row; `any` returns both. The `publish` / `draft` statuses used by ordinary CPTs do NOT apply here — passing them would silently return zero rows.',
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
        ],
        'required' => ['submissions', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_list_form_submissions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Submissions can contain sensitive data, only the row summary is returned here. Pair with get-form-submission to read field values for a specific id.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// get-form-submission -------------------------------------------------------

wp_register_ability('novamira/breakdance-get-form-submission', [
    'label' => __('Get Breakdance Form Submission', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full payload of a Breakdance form submission by id: `id`, `form_id`, the host `post_id`, submission date, status, `fields` (the actual submitted values), `ip`, `referer`, `user_agent`, `user_id`, `form_actions` (the actions Breakdance ran on submit, with their statuses), and `uploads` (attachment ids if the form accepted files).',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_get_form_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Contains the actual user-submitted data, treat the response as sensitive. Avoid logging or echoing raw `fields` content unless you need it.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// delete-form-submission ----------------------------------------------------

wp_register_ability('novamira/breakdance-delete-form-submission', [
    'label' => __('Delete Breakdance Form Submission', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Breakdance form submission. The `breakdance_form_res` CPT does not declare WordPress trash support, so even with `force=false` the submission is permanently removed at the WordPress layer. The response `mode` reflects what actually happened: `trashed` when WordPress kept the trash row, `force_deleted` when the row is gone. Uploads attached to the submission are NOT cleaned up; Breakdance treats those as separate media items.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'force' => ['type' => 'boolean', 'description' => 'Hard-delete instead of trashing.', 'default' => false],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_delete_form_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default is trash (recoverable). Force-delete only when you are sure the user does not need the data again.',
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
function bd_form_submission_summary(WP_Post $post): array
{
    /** @var mixed $fields_raw */
    $fields_raw = get_post_meta(post_id: $post->ID, key: '_breakdance_fields', single: true);
    $fields = is_array($fields_raw) ? $fields_raw : [];
    return [
        'id' => $post->ID,
        'form_id' => (int) get_post_meta(post_id: $post->ID, key: '_breakdance_form_id', single: true),
        'post_id' => (int) get_post_meta(post_id: $post->ID, key: '_breakdance_post_id', single: true),
        'submitted_at' => $post->post_date,
        'status' => $post->post_status,
        'ip' => (string) get_post_meta(post_id: $post->ID, key: '_breakdance_ip', single: true),
        'referer' => (string) get_post_meta(post_id: $post->ID, key: '_breakdance_referer', single: true),
        'field_count' => count($fields),
    ];
}

/**
 * The submissions CPT name is also used by Oxygen, although the two builders
 * are mutually exclusive at runtime. Require Breakdance's native marker so a
 * residual/imported foreign row cannot cross an ability boundary.
 */
function bd_form_submission_belongs_to_breakdance(WP_Post $post): bool
{
    return (
        $post->post_type === 'breakdance_form_res'
        && metadata_exists(meta_type: 'post', object_id: $post->ID, meta_key: '_breakdance_form_id')
    );
}

/**
 * @return array<string, mixed>
 */
function bd_form_submission_to_array(WP_Post $post): array
{
    $base = bd_form_submission_summary($post);
    /** @var mixed $fields_raw */
    $fields_raw = get_post_meta(post_id: $post->ID, key: '_breakdance_fields', single: true);
    /** @var mixed $actions_raw */
    $actions_raw = get_post_meta(post_id: $post->ID, key: '_breakdance_form_actions', single: true);
    /** @var mixed $uploads_raw */
    $uploads_raw = get_post_meta(post_id: $post->ID, key: '_breakdance_uploads', single: true);

    return array_merge($base, [
        'fields' => is_array($fields_raw) ? $fields_raw : [],
        'user_agent' => (string) get_post_meta(post_id: $post->ID, key: '_breakdance_user_agent', single: true),
        'user_id' => (int) get_post_meta(post_id: $post->ID, key: '_breakdance_user_id', single: true),
        'form_actions' => is_array($actions_raw) ? $actions_raw : [],
        'uploads' => is_array($uploads_raw) ? $uploads_raw : [],
    ]);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function breakdance_list_form_submissions(array $input): array
{
    $args = [
        'post_type' => 'breakdance_form_res',
        'numberposts' => min(500, max(1, bd_coerce_int($input['limit'] ?? 100))),
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => [[
            'key' => '_breakdance_form_id',
            'compare' => 'EXISTS',
        ]],
    ];

    $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : 'any';
    // Breakdance writes submissions with post_status `unread` and flips to
    // `read` once an admin opens them — never `publish` / `draft` (Round 11
    // B7 found the previous enum returned zero rows on every call).
    $args['post_status'] = $status === 'any' ? ['unread', 'read'] : [$status];

    if (isset($input['form_id'])) {
        $form_id = bd_coerce_int($input['form_id']);
        if ($form_id > 0) {
            $args['meta_query'][] = [
                'key' => '_breakdance_form_id',
                'value' => (string) $form_id,
                'compare' => '=',
            ];
        }
    }

    /** @var mixed $raw_posts */
    $raw_posts = get_posts($args);
    $posts = is_array($raw_posts) ? $raw_posts : [];

    $rows = [];
    /** @var mixed $post */
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        if (!bd_form_submission_belongs_to_breakdance($post)) {
            continue;
        }
        $rows[] = bd_form_submission_summary($post);
    }

    return ['submissions' => $rows, 'total' => count($rows)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_get_form_submission(array $input): array|WP_Error
{
    $id = bd_coerce_int($input['id'] ?? 0);
    if ($id <= 0) {
        return bd_error('bd_invalid_input', __('`id` must be a positive integer.', domain: 'novamira-pro'));
    }
    $post = bd_post_or_error($id, ['breakdance_form_res']);
    if ($post instanceof WP_Error) {
        return $post;
    }
    if (!bd_form_submission_belongs_to_breakdance($post)) {
        return bd_error('bd_not_found', sprintf(
            __('Breakdance form submission %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $max_field_bytes = max(1, bd_coerce_int($input['max_field_bytes'] ?? 65_536));
    $submission = bd_form_submission_to_array($post);
    $submission = bd_truncate_submission_strings($submission, $max_field_bytes);
    return ['success' => true, 'submission' => $submission];
}

/**
 * Walk a submission payload recursively and truncate every leaf
 * string longer than `$max_bytes`. Truncated values get a
 * `(truncated, N bytes)` suffix so the agent can see the cut. Non-
 * string leaves (ints, bools, arrays) are left intact.
 *
 * @param array<int|string, mixed> $payload
 * @return array<int|string, mixed>
 */
function bd_truncate_submission_strings(array $payload, int $max_bytes): array
{
    /** @var mixed $value */
    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            $payload[$key] = bd_truncate_submission_strings($value, $max_bytes);
            continue;
        }
        if (!is_string($value) || strlen($value) <= $max_bytes) {
            continue;
        }
        $payload[$key] = sprintf(
            '%s (truncated, %d bytes)',
            substr(string: $value, offset: 0, length: $max_bytes),
            strlen($value),
        );
    }
    return $payload;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_delete_form_submission(array $input): array|WP_Error
{
    $id = bd_coerce_int($input['id'] ?? 0);
    if ($id <= 0) {
        return bd_error('bd_invalid_input', __('`id` must be a positive integer.', domain: 'novamira-pro'));
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post || !bd_form_submission_belongs_to_breakdance($post)) {
        return bd_error('bd_not_found', sprintf(__('Form submission %d not found.', domain: 'novamira-pro'), $id));
    }
    $force = bd_coerce_bool($input['force'] ?? false);
    $result = wp_delete_post(post_id: $id, force_delete: $force);
    if ($result === false || $result === null) {
        return bd_error('bd_invalid_input', __('wp_delete_post failed.', domain: 'novamira-pro'));
    }
    // A truthy WP_Post return is only a declaration: pre_delete_post can
    // short-circuit while retaining the row. Read the final state back.
    $mode = bd_verify_delete_outcome($id, $force);
    if ($mode instanceof WP_Error) {
        return $mode;
    }
    return [
        'success' => true,
        'mode' => $mode,
        'deleted_id' => $id,
    ];
}
