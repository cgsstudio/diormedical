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

wp_register_ability('novamira/aioseo-create-redirect', [
    'label' => __('Create Redirect (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Creates an All in One SEO redirect (requires AIOSEO Pro). source_url is the path/URL to redirect from; target_url is where it goes (required for 301/302/307; ignored for 410 Gone and 451). type is the HTTP status: 301 (permanent), 302/307 (temporary), 410 (gone), 451 (unavailable for legal reasons). regex treats source_url as a regular expression. Writes through AIOSEO\'s redirect model so the lookup cache and URL hashes stay correct. Fails if a redirect for the same source_url already exists (use aioseo-edit-redirect). Returns the created redirect.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'source_url' => ['type' => 'string'],
            'target_url' => ['type' => 'string'],
            'type' => ['type' => 'integer', 'enum' => [301, 302, 307, 410, 451], 'default' => 301],
            'regex' => ['type' => 'boolean'],
            'enabled' => ['type' => 'boolean'],
        ],
        'required' => ['source_url'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['redirect' => ['type' => 'object']],
        'required' => ['redirect'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_create_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Create an AIOSEO redirect (Pro). Provide source_url (+ target_url for 301/302/307). One redirect per source_url — duplicate sources are rejected (use edit-redirect). Goes through the model so the cache stays correct.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{source_url?: string, target_url?: string, type?: int, regex?: bool, enabled?: bool} $args
 * @return array{redirect: array<string, mixed>}|WP_Error
 */
function aioseo_create_redirect(array $args): array|WP_Error
{
    if (!aio_detect_setup()['active']) {
        return aio_not_active();
    }
    if (!aio_supports_redirects()) {
        return aio_pro_required('Redirects');
    }

    $source = is_string($args['source_url'] ?? null) ? trim($args['source_url']) : '';
    if ($source === '') {
        return new WP_Error('aioseo_invalid_redirect', 'source_url is required', ['status' => 400]);
    }

    $type = aio_int($args['type'] ?? 301);
    if (!in_array($type, aio_redirect_types(), strict: true)) {
        return new WP_Error('aioseo_invalid_redirect', 'type must be one of 301, 302, 307, 410, 451', [
            'status' => 400,
        ]);
    }

    $target = aiec_resolve_target($args, $type);
    if ($target instanceof WP_Error) {
        return $target;
    }

    $conflict = aiec_source_conflict($source, $args);
    if ($conflict instanceof WP_Error) {
        return $conflict;
    }

    $redirect = new Redirect();
    $redirect->source_url = $source;
    $redirect->target_url = $target;
    $redirect->type = $type;
    $redirect->regex = aio_bool($args['regex'] ?? false);
    $redirect->enabled = array_key_exists('enabled', $args) ? aio_bool($args['enabled']) : true;
    $redirect->save();

    // save() fails silently (id stays 0) if the row clashes with an existing
    // source the lookup above did not normalise the same way — surface it.
    if (aio_int($redirect->id) <= 0) {
        return new WP_Error(
            'aioseo_redirect_exists',
            'could not create the redirect; a conflicting source_url may already exist',
            ['status' => 409],
        );
    }

    return ['redirect' => aio_redirect_row($redirect)];
}

/**
 * Detect a source_url conflict for a new redirect, returning a 409 WP_Error
 * (naming the colliding id) or null when the source is free. A regex source is
 * deduped by its exact raw value among regex rows; a plain source uses AIOSEO's
 * normalising lookup. See aio_regex_redirect_id for why the two differ.
 *
 * @param array<string, mixed> $args
 */
function aiec_source_conflict(string $source, array $args): ?WP_Error
{
    if (!function_exists('aioseo')) {
        return null;
    }
    // A new row has no custom_rules. Hash it exactly as save() will and look the
    // hash up across ALL rows (the UNIQUE index is shared by plain + regex), so a
    // regex source that collides with a plain row of the same hash gets the clean
    // 409 here instead of a raw DB duplicate-key error from save().
    $hash = aio_bool($args['regex'] ?? false)
        ? aio_redirect_hash_regex($source, custom_rules: null)
        : aio_redirect_hash_plain($source, custom_rules: null);
    $conflict_id = aio_redirect_id_by_hash($hash, exclude_id: 0);
    if ($conflict_id <= 0) {
        return null;
    }
    return new WP_Error(
        'aioseo_redirect_exists',
        "a redirect for that source_url already exists (id {$conflict_id}); use aioseo-edit-redirect",
        ['status' => 409],
    );
}

/**
 * Resolve and validate the target URL for a new redirect of the given type:
 * a sanitized URL for a move (301/302/307, required), or '' for 410/451 which
 * have no destination. Sanitizing before the required-check rejects a non-URL.
 *
 * @param array<string, mixed> $args
 */
function aiec_resolve_target(array $args, int $type): string|WP_Error
{
    $target = is_string($args['target_url'] ?? null) ? aio_clean_url(trim($args['target_url'])) : '';
    if (in_array($type, [301, 302, 307], strict: true) && $target === '') {
        return new WP_Error(
            'aioseo_invalid_redirect',
            "target_url is required for a {$type} redirect and must be a valid URL",
            ['status' => 400],
        );
    }
    // 410 Gone / 451 have no destination; AIOSEO ignores any target at runtime,
    // so don't store one — keep the row consistent with the "ignored" contract.
    if (in_array($type, [410, 451], strict: true)) {
        return '';
    }
    return $target;
}
