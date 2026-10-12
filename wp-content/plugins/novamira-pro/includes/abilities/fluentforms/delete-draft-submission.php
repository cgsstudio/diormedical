<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

if (!ff_pro_runtime_available()) {
    return;
}

wp_register_ability('novamira/fluentforms-delete-draft-submission', [
    'label' => __('Delete a Fluent Forms draft submission', domain: 'novamira-pro'),
    'description' => __(
        "Permanently delete one save-and-resume draft submission. Required: draft_id. There is NO trash — once removed the user's resume link returns a 404 on the FF resume flow. Returns fluentforms_draft_submission_not_found (404) for missing ids. Capability: fluentform_manage_entries (per-form when the draft's form_id is known).",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'draft_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['draft_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'draft_id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
        ],
        'required' => ['success', 'draft_id', 'form_id'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_delete_draft_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only, destructive — no trash. Capability: fluentform_manage_entries.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{success:bool, draft_id:int, form_id:int}|WP_Error
 */
function fluentforms_delete_draft_submission(array $input): array|WP_Error
{
    $pro = ff_pro_required('draft_submissions');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $draft = ff_draft_or_error($input['draft_id'] ?? null);
    if ($draft instanceof WP_Error) {
        return $draft;
    }
    $cap = ff_capability_or_error('fluentform_manage_entries', form_id: ff_row_int($draft, column: 'form_id'));
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $draft_id = ff_row_id($draft);
    $form_id = ff_row_int($draft, column: 'form_id');

    if (!function_exists('wpFluent')) {
        return ff_operation_failed('Fluent Forms query builder is unavailable.');
    }
    try {
        $deleted = wpFluent()->table('fluentform_draft_submissions')->where('id', $draft_id)->delete();
    } catch (Throwable $e) {
        return ff_operation_failed('Failed to delete draft: ' . $e->getMessage());
    }
    if ($deleted < 1) {
        return ff_operation_failed('Fluent Forms reported zero rows deleted for the draft.');
    }
    return [
        'success' => true,
        'draft_id' => $draft_id,
        'form_id' => $form_id,
    ];
}
