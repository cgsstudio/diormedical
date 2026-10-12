<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WP_Error;
use WPSEO_Redirect;
use WPSEO_Redirect_Manager;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/yoast-delete-redirect', [
    'label' => __('Delete Redirect (Yoast Premium)', domain: 'novamira-pro'),
    'description' => __(
        'Deletes the Yoast SEO Premium redirect with the given origin. Goes through Yoast\'s redirect manager so the export caches are regenerated. Errors if no redirect matches the origin. Requires Yoast SEO Premium.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'origin' => ['type' => 'string', 'description' => 'The origin of the redirect to delete.'],
        ],
        'required' => ['origin'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'origin' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
        'required' => ['origin', 'deleted'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_delete_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanently remove a redirect by origin. Errors if none matches. List first with yoast-list-redirects if unsure. Premium-only.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{origin?: string} $args
 * @return array{origin: string, deleted: bool}|WP_Error
 */
function yoast_delete_redirect(array $args): array|WP_Error
{
    if (!yoast_detect_setup()['active']) {
        return yoast_not_active();
    }
    if (!yoast_redirect_premium()) {
        return yoast_redirect_not_available();
    }

    $origin = trim($args['origin'] ?? '');
    if ($origin === '') {
        return new WP_Error('yoast_invalid_redirect', 'origin is required', ['status' => 400]);
    }

    $current = yoast_find_redirect($origin);
    if (!$current instanceof WPSEO_Redirect) {
        return new WP_Error('yoast_redirect_not_found', "no redirect found for origin: {$origin}", ['status' => 404]);
    }

    // Honour the manager's return (false on a concurrent no-op) and report the
    // canonical origin actually removed, not the raw input.
    $deleted = (new WPSEO_Redirect_Manager($current->get_format()))->delete_redirects([$current]);

    return ['origin' => $current->get_origin(), 'deleted' => $deleted === true];
}
