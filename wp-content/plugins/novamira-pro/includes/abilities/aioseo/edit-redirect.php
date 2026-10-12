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

wp_register_ability('novamira/aioseo-edit-redirect', [
    'label' => __('Edit Redirect (AIOSEO)', domain: 'novamira-pro'),
    'description' => __(
        'Updates an existing All in One SEO redirect by id (requires AIOSEO Pro). Send only the fields to change: source_url, target_url, type (301/302/307/410/451), regex, enabled. Writes through AIOSEO\'s redirect model so the lookup cache and URL hashes stay correct. Find the id with aioseo-list-redirects. Returns the updated redirect.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'source_url' => ['type' => 'string'],
            'target_url' => ['type' => 'string'],
            'type' => ['type' => 'integer', 'enum' => [301, 302, 307, 410, 451]],
            'regex' => ['type' => 'boolean'],
            'enabled' => ['type' => 'boolean'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['redirect' => ['type' => 'object']],
        'required' => ['redirect'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_edit_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Edit an AIOSEO redirect by id (Pro). Partial update — send only what changes. Find the id with list-redirects. Goes through the model so the cache stays correct.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id?: int, source_url?: string, target_url?: string, type?: int, regex?: bool, enabled?: bool} $args
 * @return array{redirect: array<string, mixed>}|WP_Error
 */
function aioseo_edit_redirect(array $args): array|WP_Error
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

    $invalid = aier_validate($redirect, $args);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    aier_apply($redirect, $args);

    // Only re-evaluate routing when this edit actually touched it (leave an
    // untouched target alone on an unrelated edit).
    $touched_routing = array_key_exists('type', $args) || array_key_exists('target_url', $args);

    // 410 Gone / 451 have no destination; AIOSEO ignores any target at runtime,
    // so clear it to keep the row consistent with the "ignored" contract.
    if ($touched_routing && in_array(aio_int($redirect->type), [410, 451], strict: true)) {
        $redirect->target_url = '';
    }

    // A move-type (301/302/307) must keep a target — e.g. switching a 410 to a
    // 301 without supplying target_url would leave it pointing nowhere.
    if (
        $touched_routing
        && in_array(aio_int($redirect->type), [301, 302, 307], strict: true)
        && aio_str($redirect->target_url) === ''
    ) {
        return new WP_Error('aioseo_invalid_redirect', 'a 301/302/307 redirect requires a target_url', [
            'status' => 400,
        ]);
    }

    $redirect->save();

    return ['redirect' => aio_redirect_row($redirect)];
}

/**
 * Validate the provided redirect edits (type enum + source uniqueness) without
 * writing. Returns a WP_Error, or null if clean.
 *
 * @param array<string, mixed> $args
 */
function aier_validate(Redirect $redirect, array $args): ?WP_Error
{
    if (array_key_exists('type', $args) && !in_array(aio_int($args['type']), aio_redirect_types(), strict: true)) {
        return new WP_Error('aioseo_invalid_redirect', 'type must be one of 301, 302, 307, 410, 451', [
            'status' => 400,
        ]);
    }
    // A source_url OR regex change alters the stored source_url_hash, so either
    // can collide. (A plain<->regex flip changes how AIOSEO hashes the same
    // source, and a regex source can share a plain row's hash.)
    $changes_source = array_key_exists('source_url', $args);
    $changes_regex = array_key_exists('regex', $args);
    if (!$changes_source && !$changes_regex) {
        return null;
    }
    $source = $changes_source ? trim((string) $args['source_url']) : aio_str($redirect->source_url);
    if ($changes_source && $source === '') {
        return new WP_Error('aioseo_invalid_redirect', 'source_url cannot be empty', ['status' => 400]);
    }
    if (!function_exists('aioseo')) {
        return null;
    }
    // Dedup by the source_url_hash AIOSEO will store on save (regex -> raw, plain
    // -> normalise+decode, custom_rules appended), excluding this row — so a
    // colliding source/regex change is caught here rather than failing silently
    // on the UNIQUE index while the ability reports success.
    $regex = $changes_regex ? aio_bool($args['regex']) : aio_bool($redirect->regex);
    /** @var mixed $rules */
    $rules = $redirect->custom_rules;
    $hash = $regex ? aio_redirect_hash_regex($source, $rules) : aio_redirect_hash_plain($source, $rules);
    $conflict_id = aio_redirect_id_by_hash($hash, aio_int($redirect->id));
    if ($conflict_id > 0) {
        return new WP_Error(
            'aioseo_redirect_exists',
            "another redirect already uses that source_url (id {$conflict_id})",
            ['status' => 409],
        );
    }
    return null;
}

/**
 * Apply the provided (already-validated) redirect edits to the model.
 *
 * @param array<string, mixed> $args
 */
function aier_apply(Redirect $redirect, array $args): void
{
    if (array_key_exists('type', $args)) {
        $redirect->type = aio_int($args['type']);
    }
    if (array_key_exists('source_url', $args)) {
        $redirect->source_url = trim((string) $args['source_url']);
    }
    if (array_key_exists('target_url', $args)) {
        // Sanitize as a URL (drops javascript:/data:).
        $redirect->target_url = aio_clean_url(trim((string) $args['target_url']));
    }
    if (array_key_exists('regex', $args)) {
        $redirect->regex = aio_bool($args['regex']);
    }
    if (array_key_exists('enabled', $args)) {
        $redirect->enabled = aio_bool($args['enabled']);
    }
}
