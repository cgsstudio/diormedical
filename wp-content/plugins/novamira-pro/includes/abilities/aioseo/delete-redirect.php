<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use AIOSEO\Plugin\Pro\Redirects\Models\Redirect;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-delete-redirect', [
    'label' => __('Delete Redirect (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an All in One SEO redirect by id (requires AIOSEO Pro). Writes through AIOSEO\'s redirect model so the lookup cache stays correct. Find the id with aioseo-list-redirects. Returns the deleted id.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'id' => ['type' => 'integer'],
        ],
        'required' => ['deleted', 'id'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_delete_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Delete an AIOSEO redirect by id (Pro). Permanent. Find the id with list-redirects. Goes through the model so the cache stays correct.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{id?: int} $args
 * @return array{deleted: bool, id: int}|WP_Error
 */
function aioseo_delete_redirect(array $args): array|WP_Error
{
    if (!aio_detect_setup()['active']) {
        return aio_not_active();
    }
    if (!aio_supports_redirects()) {
        return aio_pro_required('Redirects');
    }

    $id = aio_int($args['id'] ?? 0);
    if ($id <= 0) {
        return new WP_Error('aioseo_invalid_redirect', 'id is required', ['status' => 400]);
    }
    $redirect = Redirect::getRedirectById($id);
    if (!$redirect->exists()) {
        return new WP_Error('aioseo_redirect_not_found', "redirect not found: {$id}", ['status' => 404]);
    }

    $redirect->delete();

    // Remove the orphaned hit-counter row — AIOSEO's model delete() leaves it,
    // so clean it up to avoid a stale aioseo_redirects_hits row.
    global $wpdb;
    /** @var \wpdb $wpdb */
    $wpdb->delete($wpdb->prefix . 'aioseo_redirects_hits', ['redirect_id' => $id], ['%d']);

    return ['deleted' => true, 'id' => $id];
}
