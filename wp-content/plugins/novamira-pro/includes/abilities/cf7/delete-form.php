<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-delete-form', [
    'label' => __('Delete Contact Form 7 Form', domain: 'novamira-pro'),
    'description' => __(
        'Permanently removes a Contact Form 7 form by id. Force-delete by default — the form does NOT go to trash, it is gone. There is no undo via the abilities. Any page or post that embeds the form\'s shortcode ([contact-form-7 id="<id>"]) will start rendering the CF7 "this contact form does not exist" message after the delete. Set `force` to false to trash the form instead (it can then be restored from wp-admin → Forms → Trash).',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the form to delete.'],
            'force' => [
                'type' => 'boolean',
                'description' => 'When true (default), the form is removed permanently. When false, the form is sent to trash and can be restored. Trashing is reversible; force-delete is not.',
                'default' => true,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'deleted_id' => ['type' => 'integer'],
            'trashed' => ['type' => 'boolean'],
            'error' => ['type' => 'string'],
            'error_code' => ['type' => 'string'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Non-fatal notices about a successful delete (e.g. a post-delete listener threw). The form was still deleted.',
            ],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_delete_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Confirm with the user before force-deleting — there is no undo via the abilities, and any page embedding the form\'s shortcode will start rendering CF7\'s "this contact form does not exist" message. Prefer `force: false` (trash) when in doubt — trashed forms can be restored from wp-admin → Contact → Contact Forms → Trash.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function cf7_delete_form(array $input): array
{
    if (!isset($input['id']) || !is_int($input['id']) || $input['id'] <= 0) {
        return [
            'success' => false,
            'error' => 'id must be a positive integer.',
            'error_code' => 'cf7_invalid_input',
        ];
    }
    $id = $input['id'];
    // Strict bool only: `force: "true"` (string) used to bypass the
    // guard in direct PHP callers (round 2 audit L2-A7) — only the JSON
    // boolean values are accepted.
    $force = isset($input['force']) && is_bool($input['force']) ? $input['force'] : true;

    $form = cf7_form_or_error($id);
    if ($form instanceof WP_Error) {
        return cf7_failure_from_wp_error($form);
    }

    if ($force) {
        $deleted = wp_delete_post($id, force_delete: true);
        if ($deleted === false || $deleted === null) {
            // Distinguish "raced-by-concurrent-delete" from
            // "DB-actually-failed" so the agent gets a stable signal
            // (round 2 audit L2-A7). If get_post() now returns null
            // the form was already gone by the time wp_delete_post ran.
            if (get_post($id) === null) {
                return [
                    'success' => false,
                    'error' => sprintf('Contact Form 7 form with id %d not found.', $id),
                    'error_code' => 'cf7_form_not_found',
                ];
            }
            return [
                'success' => false,
                'error' => 'CF7 form delete failed (wp_delete_post returned false).',
                'error_code' => 'cf7_delete_failed',
            ];
        }
        // Parity with WPCF7_ContactForm::delete() — fire the hook so
        // third-party plugins (audit log, CRM sync) that listen on the
        // CF7 delete lifecycle still see the event (round 1 audit P7).
        // Wrapped in try/catch: a misbehaving listener must NOT mask the
        // fact that wp_delete_post already succeeded — otherwise the agent
        // sees a Throwable, retries, and gets cf7_form_not_found (round 2
        // audit L2-A7 / L3-A7 / L5-A7).
        $response = ['success' => true, 'deleted_id' => $id, 'trashed' => false];
        try {
            do_action('wpcf7_after_delete', $form);
        } catch (\Throwable $hook_error) {
            // The delete succeeded; a downstream listener threw. Log the
            // full detail for a developer and warn the agent so it can tell
            // the user the post-delete side effects may not have run.
            error_log(sprintf(
                'novamira-pro: wpcf7_after_delete listener threw after force-delete of form %d: %s',
                $id,
                $hook_error->getMessage(),
            ));
            $response = cf7_append_warning($response, sprintf(
                'The form was deleted, but a post-delete listener (e.g. audit log, CRM sync) threw, so downstream systems may not have been notified: %s',
                $hook_error->getMessage(),
            ));
        }
        return $response;
    }

    $trashed = wp_trash_post($id);
    if ($trashed === false || $trashed === null) {
        return [
            'success' => false,
            'error' => 'CF7 form trash failed (wp_trash_post returned false).',
            'error_code' => 'cf7_delete_failed',
        ];
    }
    return ['success' => true, 'deleted_id' => $id, 'trashed' => true];
}
