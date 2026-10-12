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

wp_register_ability('novamira/fluentforms-list-quiz-questions', [
    'label' => __('List Fluent Forms quiz questions', domain: 'novamira-pro'),
    'description' => __(
        'List the quiz-enabled questions on a Fluent Forms Pro quiz form. Each entry returns {field_name, label, type, answer (correct value), points, weight}. Reads the _quiz_settings form_meta key (set when an admin enables Quiz on a form via Settings → Quiz Settings). Returns is_quiz=false and an empty list when the form has no quiz settings. Required: form_id. Capability: fluentform_settings_manager (falls back to forms_manager).',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'is_quiz' => ['type' => 'boolean'],
            'questions' => ['type' => 'array', 'items' => ['type' => 'object']],
            'score_type' => ['type' => 'string'],
            'max_points' => ['type' => 'integer'],
        ],
        'required' => ['form_id', 'is_quiz', 'questions', 'score_type', 'max_points'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_list_quiz_questions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Returns is_quiz=false when the form has no quiz settings. score_type is "total" (sum) or "percentage" (percent).',
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
 *     questions:list<array<string, mixed>>,
 *     score_type:string,
 *     max_points:int,
 * }|WP_Error
 */
function fluentforms_list_quiz_questions(array $input): array|WP_Error
{
    $pro = ff_pro_required('quiz');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $form = ff_settings_load_form_for_read($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $form_id = (int) $form->id;

    /** @var mixed $quiz_settings */
    $quiz_settings = ff_get_form_meta($form_id, key: '_quiz_settings');
    if (!is_array($quiz_settings)) {
        return [
            'form_id' => $form_id,
            'is_quiz' => false,
            'questions' => [],
            'score_type' => '',
            'max_points' => 0,
        ];
    }

    [$questions, $max_points] = ff_extract_quiz_questions($quiz_settings);
    return [
        'form_id' => $form_id,
        'is_quiz' => true,
        'questions' => $questions,
        'score_type' => isset($quiz_settings['score_type']) && is_string($quiz_settings['score_type'])
            ? $quiz_settings['score_type']
            : 'total',
        'max_points' => $max_points,
    ];
}

// Sprint-5 quiz helpers — ff_extract_quiz_questions + ff_quiz_question_points
// moved to helpers.php so get-quiz-report can use them without depending on
// the load order of this file (round-5 audit HIGH: hidden cross-file dep).
