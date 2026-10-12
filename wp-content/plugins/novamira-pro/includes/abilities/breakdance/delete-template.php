<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-delete-template', [
    'label' => __('Delete Breakdance Template', domain: 'novamira-pro'),
    'description' => __(
        'Removes a Breakdance template. Default behaviour: trash (recoverable from the WP admin trash bin for each of the five template CPTs). Pass `force=true` to hard-delete (irrecoverable). Use trash whenever possible, there is no abilities-side restore flow. Deletion does NOT cascade-clean references to this template id from OTHER templates: `parentId` pointers in child templates, `ruleGroups` rules whose `value` matches this id, and `_breakdance_data` element trees that embed a now-deleted global block all stay as dangling pointers (silently broken). Inspect dependents with list-templates and get-template before force-deleting.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'post_id' => [
                'type' => 'integer',
                'description' => 'Alias of `id`, matching the element abilities. `id` wins when both are passed.',
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'When true, permanently delete via wp_delete_post($id, true). Default false (trash).',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['id']],
            ['required' => ['post_id']],
        ],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_delete_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default delete sends the template to the trash (recoverable). Use `force=true` only when the user has confirmed permanent removal. A second call on an already-trashed/deleted id returns `bd_not_found`. Restore from trash via WP admin if you need to undo a default delete.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{id?: int, post_id?: int, force?: bool} $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_delete_template(array $input): array|WP_Error
{
    $id = bd_template_id_arg($input);
    if ($id <= 0) {
        return bd_error('bd_invalid_input', __('id (or post_id) must be a positive integer.', domain: 'novamira-pro'));
    }
    if (bd_load_template($id) === null) {
        return bd_error('bd_not_found', sprintf(
            __('Breakdance template with id %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    $force = bd_coerce_bool($input['force'] ?? false);
    if ($force) {
        $r = wp_delete_post($id, force_delete: true);
        if ($r === false || $r === null) {
            return ['success' => false, 'error' => 'wp_delete_post failed.'];
        }
        $mode = bd_verify_delete_outcome($id, force: true);
        if ($mode instanceof WP_Error) {
            return $mode;
        }
        return ['success' => true, 'id' => $id, 'mode' => $mode];
    }

    $r = wp_trash_post($id);
    if ($r === false || $r === null) {
        return ['success' => false, 'error' => 'wp_trash_post failed.'];
    }
    $mode = bd_verify_delete_outcome($id, force: false);
    if ($mode instanceof WP_Error) {
        return $mode;
    }
    return ['success' => true, 'id' => $id, 'mode' => $mode];
}
