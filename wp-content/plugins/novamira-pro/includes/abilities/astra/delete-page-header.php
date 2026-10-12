<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/astra-delete-page-header', [
    'label' => __('Delete Astra Page Header', domain: 'novamira-pro'),
    'description' => __(
        'Removes an astra_adv_header post. Default behaviour: send to trash (recoverable from /wp-admin/edit.php?post_type=astra_adv_header&post_status=trash). Pass force=true to permanently delete via wp_delete_post. A second call on an already-trashed id returns astra_not_found — use that to detect idempotent completion. Requires the Astra Pro Addon with the advanced-headers module active.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Post id of the page header to delete.'],
            'force' => [
                'type' => 'boolean',
                'description' => 'When true, permanently delete via wp_delete_post($id, true). When false (default), wp_trash_post.',
                'default' => false,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean', 'description' => 'True when permanently deleted, false when trashed.'],
            'force' => ['type' => 'boolean'],
            'status_after' => [
                'type' => 'string',
                'description' => '"trash" after soft-delete, "deleted" after force-delete.',
            ],
        ],
        'required' => ['id', 'deleted', 'force', 'status_after'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_delete_page_header',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default delete sends the page header to the trash (recoverable from wp-admin). Use force=true only when the user has confirmed permanent removal — there is no undo via the abilities for that path.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_delete_page_header(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }
    if (!astra_addon_available()) {
        return astra_addon_required_error('advanced-headers');
    }
    if (!astra_module_active('advanced-headers')) {
        return astra_module_disabled_error('advanced-headers');
    }
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'manage_options capability required.', ['status' => 403]);
    }

    $id = astra_coerce_int($input['id'] ?? 0);
    $post = astra_page_header_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $force = astra_coerce_bool($input['force'] ?? false);

    if ($force) {
        $result = wp_delete_post($id, force_delete: true);
        if ($result === false || $result === null) {
            return astra_error('astra_invalid_input', message: 'wp_delete_post failed.');
        }
        return ['id' => $id, 'deleted' => true, 'force' => true, 'status_after' => 'deleted'];
    }

    $result = wp_trash_post($id);
    if ($result === false || $result === null) {
        return astra_error('astra_invalid_input', message: 'wp_trash_post failed.');
    }

    /** @var WP_Post|null $after */
    $after = get_post($id);
    $status_after = $after instanceof WP_Post ? $after->post_status : 'trash';

    return ['id' => $id, 'deleted' => false, 'force' => false, 'status_after' => $status_after];
}
