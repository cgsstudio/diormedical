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

wp_register_ability('novamira/yoast-create-redirect', [
    'label' => __('Create Redirect (Yoast Premium)', domain: 'novamira-pro'),
    'description' => __(
        'Creates a Yoast SEO Premium redirect from origin to target. type is the HTTP code: 301 (permanent, default), 302 (found), 307 (temporary), 410 (content deleted — no target), 451 (unavailable for legal reasons — no target). format is plain (default) or regex. Goes through Yoast\'s redirect manager so the export caches are regenerated and the redirect actually fires — never write the option directly. Fails if a redirect for the same origin already exists (edit it instead). Requires Yoast SEO Premium.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'origin' => [
                'type' => 'string',
                'description' => 'The source URL/path being redirected (or a regex when format is regex).',
            ],
            'target' => ['type' => 'string', 'description' => 'The destination. Omit for 410/451.'],
            'type' => ['type' => 'integer', 'enum' => [301, 302, 307, 410, 451], 'default' => 301],
            'format' => ['type' => 'string', 'enum' => ['plain', 'regex'], 'default' => 'plain'],
        ],
        'required' => ['origin'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'redirect' => ['type' => 'object'],
            'created' => ['type' => 'boolean'],
        ],
        'required' => ['redirect', 'created'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_create_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Add a redirect. 301 is the default and the right choice for moved content. 410/451 take no target. Use format regex only for pattern-based rules. If the origin already has a redirect, this errors — use yoast-edit-redirect. Premium-only.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{origin?: string, target?: string, type?: int, format?: string} $args
 * @return array{redirect: array<string, mixed>, created: bool}|WP_Error
 */
function yoast_create_redirect(array $args): array|WP_Error
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
    $target = trim($args['target'] ?? '');
    $type = yoast_int($args['type'] ?? 301);
    $format = ($args['format'] ?? 'plain') === 'regex' ? 'regex' : 'plain';

    $invalid = yoast_redirect_validate($type, $target);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    $redirect = new WPSEO_Redirect($origin, $target, $type, $format);
    $guard = yoast_redirect_guard($redirect, $origin, $target, current_origin: null);
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    // Honour the manager's return: add() refuses (returns false) when the origin
    // is already taken in any format, in which case nothing is persisted.
    $created = (new WPSEO_Redirect_Manager($format))->create_redirect($redirect);
    if ($created !== true) {
        return new WP_Error(
            'yoast_redirect_failed',
            'the redirect could not be created (the origin may already be in use)',
            [
                'status' => 422,
            ],
        );
    }

    // Report what was actually persisted (Yoast normalises the origin and drops
    // poisoned rows on save), not the raw input.
    $stored = yoast_find_redirect($redirect->get_origin());
    if (!$stored instanceof WPSEO_Redirect) {
        return new WP_Error('yoast_redirect_failed', 'the redirect could not be persisted', ['status' => 422]);
    }

    return ['redirect' => yoast_redirect_to_row($stored), 'created' => true];
}
