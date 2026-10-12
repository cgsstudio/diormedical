<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-delete-element', [
    'label' => __('Delete GeneratePress Element', domain: 'novamira-pro'),
    'description' => __(
        'Removes a `gp_elements` post. Default behaviour: send it to the trash (recoverable from the WP admin trash bin). Pass `force=true` to hard-delete (irrecoverable via the abilities). Use the trash-then-restore flow whenever possible — the user can recover the element from /wp-admin/edit.php?post_type=gp_elements&post_status=trash without a database backup. Returns WP_Error when the id is not a `gp_elements` post.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'force' => [
                'type' => 'boolean',
                'description' => 'When true, permanently delete via wp_delete_post($id, true). When false (default), wp_trash_post($id).',
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
            'id' => ['type' => 'integer'],
            'mode' => ['type' => 'string', 'enum' => ['trashed', 'force_deleted']],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_delete_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default delete sends the element to the trash (recoverable). Use `force=true` only when the user has confirmed permanent removal — there is no undo via the abilities for that path.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id: int, force?: bool} $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_delete_element(array $input): array|WP_Error
{
    $id = gp_coerce_int($input['id']);
    if ($id <= 0) {
        return gp_error('gp_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    if (gp_load_element($id) === null) {
        return gp_error('gp_not_found', sprintf(
            __('gp_elements post with id %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    $force = gp_coerce_bool($input['force'] ?? false);
    if ($force) {
        $r = wp_delete_post($id, force_delete: true);
        if ($r === false || $r === null) {
            return ['success' => false, 'error' => 'wp_delete_post failed.'];
        }
        return ['success' => true, 'id' => $id, 'mode' => 'force_deleted'];
    }

    $r = wp_trash_post($id);
    if ($r === false || $r === null) {
        return ['success' => false, 'error' => 'wp_trash_post failed.'];
    }
    return ['success' => true, 'id' => $id, 'mode' => 'trashed'];
}
