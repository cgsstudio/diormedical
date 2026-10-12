<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-list-submissions', [
    'label' => __('List Ninja Forms Submissions', domain: 'novamira-pro'),
    'description' => __(
        'List the submissions of a Ninja Forms form, newest first. Submissions are `nf_sub` posts. Required: form_id. Optional: status ("active" = live submissions [default], "trashed", or "any"); per_page (default 25, max 100); page (1-based). Returns COMPACT rows {id, seq_num, status, date, form_id} with NO field values — call ninja-forms-get-submission for the decoded answers of one. Includes a pagination block {page, per_page, total, total_pages}.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'trashed', 'any'],
                'default' => 'active',
                'description' => 'Which submissions to return. Default "active".',
            ],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'submissions' => ['type' => 'array', 'items' => ['type' => 'object']],
            'pagination' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'submissions', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_list_submissions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact rows only — no field values (keeps the agent context small). Use ninja-forms-get-submission for the answers of one. Default status is "active"; pass status="trashed"/"any" to see trashed rows. Capability: manage_options.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int, status?: string, per_page?: int, page?: int} $input
 *
 * @return array{form_id: int, submissions: list<array<string, mixed>>, pagination: array<string, int>}|WP_Error
 */
function ninja_forms_list_submissions(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form = ninja_forms_form_or_error($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $form_id = (int) $form->get_id();

    $status = ninja_forms_str($input['status'] ?? 'active');
    if (!in_array($status, ['active', 'trashed', 'any'], strict: true)) {
        $status = 'active';
    }
    $per_page = ninja_forms_pos_int($input['per_page'] ?? 25);
    $per_page = $per_page > 0 ? min(100, $per_page) : 25;
    $page = ninja_forms_pos_int($input['page'] ?? 1);
    $page = max(1, $page);

    $query = new WP_Query([
        'post_type' => 'nf_sub',
        'post_status' => ninja_forms_submission_status_filter($status),
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => false,
        'ignore_sticky_posts' => true,
        'meta_query' => [['key' => '_form_id', 'value' => (string) $form_id]],
    ]);

    $submissions = [];
    foreach ($query->posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        $submissions[] = [
            'id' => (int) $post->ID,
            'seq_num' => ninja_forms_non_neg_int(get_post_meta($post->ID, key: '_seq_num', single: true)),
            'status' => ninja_forms_submission_status_label($post->post_status),
            'date' => $post->post_date,
            'form_id' => $form_id,
        ];
    }

    $total = (int) $query->found_posts;
    return [
        'form_id' => $form_id,
        'submissions' => $submissions,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
        ],
    ];
}
