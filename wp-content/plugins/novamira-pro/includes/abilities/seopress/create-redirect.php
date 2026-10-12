<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-create-redirect', [
    'label' => __('Create Redirect (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new SeoPress Pro redirect (a seopress_404 custom post type — redirects and monitored 404s share it) (requires SeoPress Pro). REQUIRED: origin (the source path like /old-page/ or a regex pattern — stored as the post_title). Optional: destination (target URL — required in practice for a 301/302/307, omit for a 410/451 "gone" status), type (HTTP status, default 301, must be one of 301, 302, 307, 410, 451), enabled (default true), enabled_regex (origin is a regex, default false — NOTE SeoPress matches the regex against the request path WITH its leading slash, so anchor as "^/old-(.*)" or leave it unanchored; a start-anchored "^old-(.*)" never fires), logged_status (audience: both | only_logged_in | only_not_logged_in, default both), param (query-parameter mode: exact_match | without_param | with_ignored_param, default exact_match), category_ids (a list of seopress_404_cat term ids). A redirect only FIRES when it is enabled AND has a destination AND a valid type AND a logged_status — all defaulted here so a minimal {origin, destination} call works. NOT idempotent: every call mints a fresh redirect with a new auto-generated id, so re-running creates a duplicate — pre-check with list-redirects (search by origin) before creating. All provided fields are validated FIRST (seopress_invalid_input, 400) before anything is written. Returns the compact redirect row {id, origin, destination, type, enabled, enabled_regex, logged_status, param, count, last_hit}.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'origin' => [
                'type' => 'string',
                'description' => 'REQUIRED. The source path (e.g. /old-page/) or regex pattern. Normalised before storage exactly as SeoPress does — a leading slash and any scheme/host are stripped (so "/old-page/" is stored and matched as "old-page/") because the front-end matcher looks the title up slash-stripped; an origin that normalises to empty (e.g. "/") is rejected.',
            ],
            'destination' => [
                'type' => 'string',
                'description' => 'Target URL. Required in practice for 301/302/307; omit for a 410/451 "gone" status.',
            ],
            'type' => [
                'type' => 'integer',
                'description' => 'HTTP status: 301, 302, 307, 410 or 451. Default 301. 410/451 are "gone" (no destination needed).',
            ],
            'enabled' => ['type' => 'boolean', 'description' => 'Whether the redirect fires. Default true.'],
            'enabled_regex' => [
                'type' => 'boolean',
                'description' => 'Treat origin as a regex pattern. Default false. SeoPress matches it against the request path WITH a leading slash, so anchor as "^/old-…" (or leave unanchored); "^old-…" never fires.',
            ],
            'logged_status' => [
                'type' => 'string',
                'description' => 'Audience: both | only_logged_in | only_not_logged_in. Default both (required for the redirect to fire).',
            ],
            'param' => [
                'type' => 'string',
                'description' => 'Query-parameter mode: exact_match | without_param | with_ignored_param. Default exact_match.',
            ],
            'category_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => 'seopress_404_cat term ids to attach.',
            ],
        ],
        'required' => ['origin'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'redirect' => ['type' => 'object'],
        ],
        'required' => ['redirect'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_create_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Create a SeoPress Pro redirect (the seopress_404 CPT). origin is REQUIRED (the source path/regex). type defaults 301 (set 410/451 for "gone", no destination needed); enabled, logged_status (both) and param (exact_match) are defaulted so a redirect actually fires. NOT idempotent — each call mints a NEW id, so re-running duplicates; pre-check with list-redirects (search by origin) first. Validates all fields before writing.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{redirect: array<string, mixed>}|WP_Error
 */
function seopress_create_redirect(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }
    if (!sp_redirect_supported()) {
        return sp_pro_required('Redirects');
    }

    $invalid = sp_validate_redirect_input($args, require_origin: 'require');
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    $defaults = sp_redirect_create_defaults($args);

    $new_id = wp_insert_post([
        'post_type' => 'seopress_404',
        'post_title' => sp_redirect_normalize_origin((string) $defaults['origin']),
        'post_status' => 'publish',
    ], wp_error: true);
    if ($new_id instanceof WP_Error) {
        return $new_id;
    }
    $id = sp_int($new_id);
    if ($id <= 0) {
        return new WP_Error('seopress_create_failed', 'Failed to create the redirect', ['status' => 500]);
    }

    $set = static fn(string $key, mixed $value): mixed => update_post_meta($id, meta_key: $key, meta_value: $value);
    $del = static fn(string $key): bool => delete_post_meta($id, meta_key: $key);
    sp_apply_redirect_fields($set, $del, $defaults);

    $ids = sp_redirect_category_id_list($defaults);
    if ($ids !== []) {
        wp_set_post_terms($id, $ids, taxonomy: 'seopress_404_cat');
    }

    /** @var \WP_Post|null $post */
    $post = get_post($id);
    if (!$post instanceof \WP_Post) {
        return new WP_Error('seopress_create_failed', 'Failed to read back the redirect', ['status' => 500]);
    }
    return ['redirect' => sp_redirect_row($post)];
}

/**
 * Layer the create-time defaults under the provided fields so a minimal {origin}
 * call still yields a redirect that fires: type 301, enabled, logged_status both,
 * param exact_match (enabled_regex stays false — its absence already means off).
 * A provided value always wins. Mirrors the SeoPress Pro REST create defaults.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function sp_redirect_create_defaults(array $args): array
{
    return $args
    + [
        'type' => 301,
        'enabled' => true,
        'logged_status' => 'both',
        'param' => 'exact_match',
    ];
}
