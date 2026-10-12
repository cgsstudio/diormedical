<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

if (!ff_pro_runtime_available()) {
    return;
}

wp_register_ability('novamira/fluentforms-get-quiz-report', [
    'label' => __('Get Fluent Forms quiz report', domain: 'novamira-pro'),
    'description' => __(
        "Aggregate quiz scoring across every submission of a Fluent Forms Pro quiz form. Returns {form_id, is_quiz, submissions_total, per_question: [{field_name, label, correct_count, wrong_count, accuracy_pct}], overall: {average_score, max_points, score_type}}. Useful for instructor / surveyor dashboards. Required: form_id. Sample size is the count of non-trashed submissions; pass status_filter to restrict (e.g. only 'read'). When the form is not a quiz, is_quiz=false and per_question is empty. Capability: fluentform_entries_viewer.",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'status_filter' => [
                'type' => 'string',
                'enum' => ['', 'unread', 'read', 'spam', 'trashed'],
                'description' => 'Optional submission status filter. Default: all non-trashed.',
                'default' => '',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'is_quiz' => ['type' => 'boolean'],
            'submissions_total' => ['type' => 'integer'],
            'per_question' => ['type' => 'array', 'items' => ['type' => 'object']],
            'overall' => [
                'type' => 'object',
                'properties' => [
                    'average_score' => ['type' => 'number'],
                    'max_points' => ['type' => 'integer'],
                    'score_type' => ['type' => 'string'],
                ],
                'required' => ['average_score', 'max_points', 'score_type'],
            ],
        ],
        'required' => ['form_id', 'is_quiz', 'submissions_total', 'per_question', 'overall'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_quiz_report',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Reads every matching submission once and scores it client-side. For very large datasets (>10k submissions) prefer narrowing via status_filter or split the form into sub-quizzes.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{
 *     form_id:int,
 *     is_quiz:bool,
 *     submissions_total:int,
 *     per_question:list<array<string, mixed>>,
 *     overall:array{average_score:float, max_points:int, score_type:string},
 * }|WP_Error
 */
function fluentforms_get_quiz_report(array $input): array|WP_Error
{
    $pro = ff_pro_required('quiz');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $form_id = isset($input['form_id']) && is_int($input['form_id']) && $input['form_id'] >= 1 ? $input['form_id'] : 0;
    if ($form_id <= 0) {
        return ff_invalid('form_id must be a positive integer.');
    }
    $cap = ff_capability_or_error('fluentform_entries_viewer', form_id: $form_id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    /** @var mixed $quiz_settings */
    $quiz_settings = ff_get_form_meta($form_id, key: '_quiz_settings');
    if (!is_array($quiz_settings)) {
        return ff_quiz_report_empty($form_id);
    }
    [$questions, $max_points] = ff_extract_quiz_questions($quiz_settings);
    if ($questions === []) {
        return ff_quiz_report_empty($form_id);
    }

    $status_filter = ff_input_str($input, key: 'status_filter');
    $submissions = ff_quiz_report_submissions($form_id, status_filter: $status_filter);

    $per_question = ff_quiz_score_per_question($questions, $submissions);
    $score_type = isset($quiz_settings['score_type']) && is_string($quiz_settings['score_type'])
        ? $quiz_settings['score_type']
        : 'total';
    return [
        'form_id' => $form_id,
        'is_quiz' => true,
        'submissions_total' => count($submissions),
        'per_question' => $per_question,
        'overall' => [
            'average_score' => ff_quiz_average_score($per_question, $submissions, $score_type, $max_points),
            'max_points' => $max_points,
            'score_type' => $score_type,
        ],
    ];
}

/**
 * Empty-quiz-report envelope. Used for non-quiz forms and quiz forms
 * with zero saved questions.
 *
 * @return array{form_id:int, is_quiz:bool, submissions_total:int, per_question:list<array<string,mixed>>, overall:array{average_score:float, max_points:int, score_type:string}}
 */
function ff_quiz_report_empty(int $form_id): array
{
    return [
        'form_id' => $form_id,
        'is_quiz' => false,
        'submissions_total' => 0,
        'per_question' => [],
        'overall' => ['average_score' => 0.0, 'max_points' => 0, 'score_type' => ''],
    ];
}

/**
 * Cap on the number of submissions scored in a single call. Beyond
 * this the report would dominate memory + wall-clock on busy forms and
 * the MCP turn would time out. Round-5 audit HIGH.
 */
const FF_QUIZ_REPORT_SUBMISSION_CAP = 5000;

/**
 * Load up to FF_QUIZ_REPORT_SUBMISSION_CAP submissions for the form
 * (most-recent-first). Optional status filter; passing '' (default)
 * excludes trashed but accepts unread/read/spam.
 *
 * @return list<\stdClass>
 */
function ff_quiz_report_submissions(int $form_id, string $status_filter): array
{
    if (!function_exists('wpFluent')) {
        return [];
    }
    $q = wpFluent()->table('fluentform_submissions')->where('form_id', $form_id);
    $q = $status_filter !== '' ? $q->where('status', $status_filter) : $q->where('status', '!=', 'trashed');
    return ff_rows_to_array($q->orderBy('id', direction: 'desc')->take(FF_QUIZ_REPORT_SUBMISSION_CAP)->get());
}

/**
 * For every question, count correct vs wrong across the submission set.
 * Round-5 audit HIGH: loops are now `for each submission { for each
 * question }` so json_decode runs O(S) times instead of O(Q × S). On a
 * 50-question / 5000-submission form that's 5k decodes instead of 250k.
 *
 * @param list<array<string, mixed>> $questions
 * @param list<\stdClass> $submissions
 *
 * @return list<array<string, mixed>>
 */
function ff_quiz_score_per_question(array $questions, array $submissions): array
{
    /** @var array<int, array{correct:int, wrong:int}> $totals */
    $totals = [];
    foreach (array_keys($questions) as $i) {
        $totals[$i] = ['correct' => 0, 'wrong' => 0];
    }
    foreach ($submissions as $sub) {
        $response = ff_quiz_decode_response($sub);
        foreach ($questions as $i => $question) {
            $field_name = isset($question['field_name']) && is_string($question['field_name'])
                ? $question['field_name']
                : '';
            if (!array_key_exists($field_name, $response)) {
                continue;
            }
            /** @var mixed $correct */
            $correct = $question['answer'] ?? null;
            if (ff_quiz_answers_match($response[$field_name], $correct)) {
                ++$totals[$i]['correct'];
                continue;
            }
            ++$totals[$i]['wrong'];
        }
    }
    $out = [];
    foreach ($questions as $i => $question) {
        $field_name = isset($question['field_name']) && is_string($question['field_name'])
            ? $question['field_name']
            : '';
        $correct_count = $totals[$i]['correct'];
        $wrong_count = $totals[$i]['wrong'];
        $answered = $correct_count + $wrong_count;
        $out[] = [
            'field_name' => $field_name,
            'label' => $question['label'] ?? $field_name,
            'correct_count' => $correct_count,
            'wrong_count' => $wrong_count,
            'accuracy_pct' => $answered > 0 ? round(num: ($correct_count / $answered) * 100, precision: 1) : 0.0,
        ];
    }
    return $out;
}

/**
 * Decode the response JSON from a submission row. Returns [] on parse
 * failure rather than throwing so the aggregator stays robust against
 * legacy / corrupted rows.
 *
 * @return array<array-key, mixed>
 */
function ff_quiz_decode_response(\stdClass $sub): array
{
    $raw = ff_row_str($sub, column: 'response');
    if ($raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($raw, associative: true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Compare a submitted answer with the correct answer. Strings are
 * compared case-insensitively + trimmed; arrays are compared as sorted
 * sets (multi-select); scalars by ==.
 */
function ff_quiz_answers_match(mixed $given, mixed $correct): bool
{
    if (is_array($given) && is_array($correct)) {
        $g = array_map(static fn($v): string => is_scalar($v) ? trim((string) $v) : '', $given);
        $c = array_map(static fn($v): string => is_scalar($v) ? trim((string) $v) : '', $correct);
        sort($g);
        sort($c);
        return $g === $c;
    }
    if (is_string($given) && is_string($correct)) {
        return strcasecmp(trim($given), trim($correct)) === 0;
    }
    if (is_scalar($given) && is_scalar($correct)) {
        return (string) $given === (string) $correct;
    }
    return false;
}

/**
 * Average score across the submission set. For score_type='percentage'
 * we return the percent of correct answers (0-100). For 'total' we
 * return the average points scored per submission.
 *
 * @param list<array<string, mixed>> $per_question
 * @param list<\stdClass> $submissions
 */
function ff_quiz_average_score(array $per_question, array $submissions, string $score_type, int $max_points): float
{
    $count = count($submissions);
    if ($count === 0) {
        return 0.0;
    }
    $total_correct = 0;
    foreach ($per_question as $q) {
        $total_correct += isset($q['correct_count']) && is_int($q['correct_count']) ? $q['correct_count'] : 0;
    }
    if ($score_type === 'percentage' && $max_points > 0 && count($per_question) > 0) {
        return round(num: ($total_correct / ($count * count($per_question))) * 100, precision: 1);
    }
    return round(num: $total_correct / $count, precision: 2);
}
