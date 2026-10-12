<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-get-submission', [
    'label' => __('Get a Ninja Forms Submission', domain: 'novamira-pro'),
    'description' => __(
        'Fetch one Ninja Forms submission by id with its decoded field answers. Required: form_id, submission_id (the submission MUST belong to form_id, else ninja_forms_submission_not_found). Optional: fields (a whitelist of field keys — only those answers are returned); max_field_bytes (per-answer byte cap, default 8192, 0 disables — large textarea / file values are truncated with a marker and a `full_size`). No-value display fields (submit / html / hr / note) are omitted. Returns {form_id, submission_id, seq_num, status, date, answers: [{field_id, field_key, label, value, truncated, full_size}]}.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'submission_id' => ['type' => 'integer', 'minimum' => 1],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional whitelist of field keys to include in answers[]. Default: all.',
                'default' => [],
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 8192,
                'description' => 'Per-answer byte cap. Default 8192. Set 0 to disable.',
            ],
        ],
        'required' => ['form_id', 'submission_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'submission_id' => ['type' => 'integer'],
            'seq_num' => ['type' => 'integer'],
            'status' => ['type' => 'string'],
            'date' => ['type' => 'string'],
            'answers' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
        'required' => ['form_id', 'submission_id', 'seq_num', 'status', 'date', 'answers'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_get_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use `fields` to fetch only the answers you need — each answer counts against the agent context. Large values are byte-capped (raise max_field_bytes, or 0 for full). Find ids via ninja-forms-list-submissions. Capability: manage_options.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int, submission_id?: int, fields?: list<string>, max_field_bytes?: int} $input
 *
 * @return array<string, mixed>|WP_Error
 */
function ninja_forms_get_submission(array $input): array|WP_Error
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

    $submission = ninja_forms_submission_or_error($input['submission_id'] ?? null, $form_id);
    if ($submission instanceof WP_Error) {
        return $submission;
    }
    $submission_id = ninja_forms_pos_int($input['submission_id'] ?? null);

    $whitelist = ninja_forms_submission_field_whitelist($input['fields'] ?? null);
    $max_field_bytes = ninja_forms_resolve_max_field_bytes($input);

    // @mago-expect analysis:mixed-assignment
    $post = get_post($submission_id);
    $status = $post instanceof \WP_Post ? ninja_forms_submission_status_label($post->post_status) : 'active';
    $date = $post instanceof \WP_Post ? $post->post_date : '';

    return [
        'form_id' => $form_id,
        'submission_id' => $submission_id,
        'seq_num' => ninja_forms_non_neg_int(get_post_meta($submission_id, key: '_seq_num', single: true)),
        'status' => $status,
        'date' => $date,
        'answers' => ninja_forms_submission_answers($form_id, $submission_id, $whitelist, $max_field_bytes),
    ];
}

/**
 * Normalise the optional `fields` whitelist to a set of field keys, or null
 * when no (usable) whitelist was supplied (return every answer).
 *
 * @return array<string, true>|null
 */
function ninja_forms_submission_field_whitelist(mixed $raw): ?array
{
    if (!is_array($raw)) {
        return null;
    }
    $set = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $value) {
        $key = ninja_forms_str($value);
        if ($key !== '') {
            $set[$key] = true;
        }
    }
    return $set === [] ? null : $set;
}

/**
 * Build the decoded answer rows for a submission: one per input field that
 * stored a value, byte-capped. Structural fields (no answer) are skipped, as
 * are fields excluded by the `$whitelist`.
 *
 * @param array<string, true>|null $whitelist
 *
 * @return list<array<string, mixed>>
 */
function ninja_forms_submission_answers(
    int $form_id,
    int $submission_id,
    ?array $whitelist,
    int $max_field_bytes,
): array {
    // Ninja's no-value display fields. (`repeater` is NOT here — it stores
    // submitted child values; `heading`/`spacer` are not Ninja types.)
    $skip_types = ['submit', 'html', 'hr', 'note'];
    $answers = [];
    foreach (\Ninja_Forms()->form($form_id)->get_fields() as $field) {
        $settings = $field->get_settings();
        $type = ninja_forms_str($settings['type'] ?? '');
        if (in_array($type, $skip_types, strict: true)) {
            continue;
        }
        $key = ninja_forms_str($settings['key'] ?? '');
        if ($whitelist !== null && !isset($whitelist[$key])) {
            continue;
        }
        $field_id = (int) $field->get_id();
        // @mago-expect analysis:mixed-assignment
        $raw = get_post_meta($submission_id, key: '_field_' . $field_id, single: true);
        $value = ninja_forms_stringify_value($raw);
        $capped = ninja_forms_truncate_field($value, $max_field_bytes);
        $answers[] = [
            'field_id' => $field_id,
            'field_key' => $key,
            'label' => ninja_forms_str($settings['label'] ?? ''),
            'value' => $capped['value'],
            'truncated' => $capped['truncated'],
            'full_size' => $capped['original_bytes'],
        ];
    }
    return $answers;
}
