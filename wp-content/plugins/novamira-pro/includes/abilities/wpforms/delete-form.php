<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-delete-form', [
    'label' => __('Delete WPForms Form', domain: 'novamira-pro'),
    'description' => __(
        'Delete a WPForms form. Default behavior trashes the form (status flips to "trash" — restorable from the WPForms admin). Pass force=true to permanently delete the CPT row (irreversible). Returns the previous status so the caller can confirm what was removed.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'WPForms form id to delete.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When false (default) the form is trashed (restorable). When true the row is permanently deleted.',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
            'force' => ['type' => 'boolean'],
            'status_was' => ['type' => 'string'],
            'status_now' => ['type' => ['string', 'null']],
        ],
        'required' => ['id', 'deleted', 'force', 'status_was', 'status_now'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_delete_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Trashes by default. Pass force=true to permanently delete. status_now is null after a force delete (the row no longer exists), else "trash". A form already in trash is treated as a no-op when force=false.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, force?: bool} $input
 * @return array{id: int, deleted: bool, force: bool, status_was: string, status_now: string|null}|WP_Error
 */
function wpforms_delete_form(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $force = ($input['force'] ?? false) === true;
    $form = wpforms_delete_target($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap_error = wpforms_capability_or_error('wpforms_delete_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $status_was = $form->post_status;

    if (!$force && $status_was === 'trash') {
        // Already trashed — idempotent no-op.
        return [
            'id' => (int) $form->ID,
            'deleted' => true,
            'force' => false,
            'status_was' => $status_was,
            'status_now' => 'trash',
        ];
    }

    $result = $force ? wp_delete_post((int) $form->ID, force_delete: true) : wp_trash_post((int) $form->ID);
    if ($result === false || $result === null) {
        return new WP_Error('wpforms_delete_failed', 'WordPress refused to delete the form.', ['status' => 500]);
    }

    if ($force) {
        return [
            'id' => (int) $form->ID,
            'deleted' => true,
            'force' => true,
            'status_was' => $status_was,
            'status_now' => null,
        ];
    }

    // Read back to confirm — for CPTs that disable EMPTY_TRASH_DAYS the post
    // is permanently deleted even on a non-force call; status_now reflects that.
    /** @var \WP_Post|null $after */
    $after = get_post((int) $form->ID);
    return [
        'id' => (int) $form->ID,
        'deleted' => true,
        'force' => false,
        'status_was' => $status_was,
        'status_now' => $after instanceof \WP_Post ? $after->post_status : null,
    ];
}

/**
 * Resolve a form for deletion. wpforms_form_or_error() refuses a trashed form
 * (a sensible guard for read/edit paths), but delete must accept it so the
 * caller can force-delete or re-trash. Reproduces the type guard here without
 * the trash rejection.
 */
function wpforms_delete_target(int $form_id): WP_Post|WP_Error
{
    if ($form_id <= 0) {
        return wpforms_invalid_input('form_id must be a positive integer.');
    }
    /** @var WP_Post|null $post */
    $post = get_post($form_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'wpforms') {
        return new WP_Error('wpforms_form_not_found', "WPForms form not found: {$form_id}", ['status' => 404]);
    }
    return $post;
}
