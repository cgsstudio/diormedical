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

wp_register_ability('novamira/yoast-edit-redirect', [
    'label' => __('Edit Redirect (Yoast Premium)', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Yoast SEO Premium redirect, identified by its current `origin`. Optionally change the destination (target), the HTTP type (301/302/307/410/451), and/or the origin itself (new_origin). The redirect\'s format (plain/regex) is preserved; to change format, delete and recreate. Goes through Yoast\'s redirect manager so the export caches are regenerated. Requires Yoast SEO Premium.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'origin' => ['type' => 'string', 'description' => 'The current origin identifying the redirect to edit.'],
            'new_origin' => ['type' => 'string', 'description' => 'Optional new origin (renames the redirect).'],
            'target' => ['type' => 'string', 'description' => 'Optional new destination.'],
            'type' => [
                'type' => 'integer',
                'enum' => [301, 302, 307, 410, 451],
                'description' => 'Optional new HTTP type.',
            ],
        ],
        'required' => ['origin'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'redirect' => ['type' => 'object'],
            'updated' => ['type' => 'boolean'],
        ],
        'required' => ['redirect', 'updated'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_edit_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Change a redirect found by origin. Only the fields you send change; format is fixed (delete + create to switch plain/regex). Errors if no redirect matches origin. Premium-only.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{origin?: string, new_origin?: string, target?: string, type?: int} $args
 * @return array{redirect: array<string, mixed>, updated: bool}|WP_Error
 */
function yoast_edit_redirect(array $args): array|WP_Error
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

    $format = $current->get_format();
    $new_origin_in = trim($args['new_origin'] ?? '');
    $new_origin = $new_origin_in !== '' ? $new_origin_in : $origin;
    $target = array_key_exists('target', $args) ? trim($args['target']) : $current->get_target();
    $type = array_key_exists('type', $args) ? yoast_int($args['type']) : $current->get_type();

    $invalid = yoast_redirect_validate($type, $target);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    $new = new WPSEO_Redirect($new_origin, $target, $type, $format);
    $guard = yoast_redirect_guard($new, $new_origin, $target, $current->get_origin());
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $updated = (new WPSEO_Redirect_Manager($format))->update_redirect($current, $new);
    if ($updated !== true) {
        return new WP_Error('yoast_redirect_failed', 'the redirect could not be updated', ['status' => 422]);
    }

    // Report what was actually persisted, not the raw input.
    $stored = yoast_find_redirect($new->get_origin());
    if (!$stored instanceof WPSEO_Redirect) {
        return new WP_Error('yoast_redirect_failed', 'the redirect could not be persisted', ['status' => 422]);
    }

    return ['redirect' => yoast_redirect_to_row($stored), 'updated' => true];
}
