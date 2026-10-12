<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-delete-template', [
    'label' => __('Delete Oxygen Template', domain: 'novamira-pro'),
    'description' => __(
        'Removes an Oxygen template. Default behaviour: trash. NOTE: the template CPTs register with `show_ui: false`, so there is NO WordPress-admin trash bin for them — a trashed template is recoverable only from Oxygen\'s own Templates manager (Oxygen → Templates, which lists trashed rows and can restore them) or via wp-cli/code (`wp post untrash <id>`). Pass `force=true` to hard-delete (irrecoverable). Use trash whenever possible — there is no abilities-side restore flow. Deletion does NOT cascade-clean references to this template id from OTHER templates: `parentId` pointers, `ruleGroups` rules whose `value` matches this id, and `_oxygen_data` element trees that embed a now-deleted global block all stay as dangling pointers (silently broken). Inspect dependents with list-templates and get-template before force-deleting.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_delete_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default delete sends the template to the trash (recoverable). Use `force=true` only when the user has confirmed permanent removal. A second call on an already-trashed/deleted id returns `oxygen_not_found`. To undo a default delete, restore from Oxygen\'s own Templates manager or `wp post untrash <id>` — the template CPTs have no WP-admin UI, so the regular WP trash screen does not show them.',
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
function oxygen_delete_template(array $input): array|WP_Error
{
    $id = ox_template_id_arg($input);
    if ($id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'id (or post_id) must be a positive integer.',
            domain: 'novamira-pro',
        ));
    }
    if (ox_load_template($id) === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Oxygen template with id %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    $force = ox_coerce_bool($input['force'] ?? false);
    $r = $force ? wp_delete_post($id, force_delete: true) : wp_trash_post($id);
    if ($r === false || $r === null) {
        return ox_error(
            'oxygen_invalid_input',
            $force
                ? __('wp_delete_post failed.', domain: 'novamira-pro')
                : __('wp_trash_post failed.', domain: 'novamira-pro'),
        );
    }
    // Verify the FINAL state, not the truthy return — a pre_trash_post /
    // pre_delete_post short-circuit reports success while leaving the row in
    // place. `ox_verify_delete_outcome` reads the row back and returns the mode
    // that actually happened, or oxygen_write_not_applied when nothing changed.
    $mode = ox_verify_delete_outcome($id, $force);
    if ($mode instanceof WP_Error) {
        return $mode;
    }
    return ['success' => true, 'id' => $id, 'mode' => $mode];
}
