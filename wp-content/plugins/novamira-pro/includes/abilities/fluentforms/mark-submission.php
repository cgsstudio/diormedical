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

wp_register_ability('novamira/fluentforms-mark-submission', [
    'label' => __('Mark a Fluent Forms Submission', domain: 'novamira-pro'),
    'description' => __(
        'Mark one submission. Required: submission_id. Mutually exclusive — pass exactly ONE of: status (unread|read|spam|trashed) — sets the submission status; favourite (bool) — toggles the is_favourite flag. Returns the updated compact row. Idempotent: marking an already-read submission as read is a no-op success. Returns fluentforms_submission_not_found (404) for missing ids; fluentforms_invalid_input (400) when neither (or both) action is passed. Capability: fluentform_manage_entries.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'submission_id' => ['type' => 'integer', 'minimum' => 1],
            'status' => ['type' => 'string', 'enum' => ['unread', 'read', 'spam', 'trashed']],
            'favourite' => ['type' => 'boolean'],
        ],
        'required' => ['submission_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'submission' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['submission', 'changed'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_mark_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Marking is idempotent — re-marking a row already in the target state is a successful no-op (changed[] empty). Pass exactly one of status / favourite per call; the ability rejects calls with both or neither. Capability: fluentform_manage_entries.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{submission: array<string, mixed>, changed: list<string>}|WP_Error
 */
function fluentforms_mark_submission(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $sub = ff_submission_or_error($input['submission_id'] ?? null);
    if ($sub instanceof WP_Error) {
        return $sub;
    }
    $cap = ff_capability_or_error('fluentform_manage_entries', (int) $sub->form_id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $action = ff_mark_submission_resolve_action($input);
    if ($action instanceof WP_Error) {
        return $action;
    }
    $changed = ff_mark_submission_apply($sub, $action);
    if ($changed !== []) {
        try {
            $sub->save();
        } catch (Throwable $e) {
            return ff_operation_failed('Failed to save submission: ' . $e->getMessage());
        }
    }
    return ['submission' => ff_submission_row($sub), 'changed' => $changed];
}

/**
 * Resolve which action the caller asked for: status or favourite. Returns
 * a discriminated payload (string=status target, bool=favourite target),
 * or a WP_Error when neither / both were passed.
 *
 * @param array<string, mixed> $input
 */
function ff_mark_submission_resolve_action(array $input): string|bool|WP_Error
{
    $has_status = array_key_exists('status', $input) && is_string($input['status']);
    $has_fav = array_key_exists('favourite', $input) && is_bool($input['favourite']);
    if ($has_status === $has_fav) {
        return ff_invalid('Pass exactly one of `status` or `favourite` (not both, not neither).');
    }
    if ($has_status) {
        return $input['status'];
    }
    return $input['favourite'] === true;
}

/**
 * Apply the resolved action onto the submission model. Returns the list
 * of column names that actually changed (empty on a no-op).
 *
 * @return list<string>
 */
function ff_mark_submission_apply(\FluentForm\App\Models\Submission $sub, string|bool $action): array
{
    if (is_string($action)) {
        if ($sub->status === $action) {
            return [];
        }
        $sub->status = $action;
        return ['status'];
    }
    $target = $action ? 1 : 0;
    if ((int) $sub->is_favourite === $target) {
        return [];
    }
    $sub->is_favourite = $target;
    return ['is_favourite'];
}
