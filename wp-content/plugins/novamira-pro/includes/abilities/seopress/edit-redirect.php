<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-edit-redirect', [
    'label' => __('Edit Redirect (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Updates an existing SeoPress Pro redirect / monitored 404 (a seopress_404 custom post type) by id (requires SeoPress Pro). Send ONLY the fields to change — it is a partial merge. Identify the entry by id (the seopress_404 post id from list-redirects). Fields: origin (the source path or regex, stored as the post_title — cannot be blanked, a redirect always needs a source), destination (target URL; empty string clears it), type (HTTP status, must be one of 301, 302, 307, 410, 451; 0 clears the type and DEMOTES the entry back to a bare 404), enabled (bool; false stops it firing), enabled_regex (bool; origin is a regex), logged_status (both | only_logged_in | only_not_logged_in; empty clears it), param (exact_match | without_param | with_ignored_param; empty clears it), category_ids (a list of seopress_404_cat term ids — REPLACES the existing set; [] detaches all). This is also how a monitored 404 is PROMOTED to a redirect: give a bare 404 a type + destination (and it fires once enabled + logged_status are set). All provided fields are validated FIRST (seopress_invalid_input, 400) before anything is written. Returns the compact redirect row {id, origin, destination, type, enabled, enabled_regex, logged_status, param, count, last_hit}.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'The seopress_404 post id (from list-redirects).'],
            'origin' => [
                'type' => 'string',
                'description' => 'Source path or regex (the post_title). Normalised like create-redirect (leading slash / scheme / host stripped, so "/old/" stores as "old/"). Cannot be blanked — a redirect needs a source.',
            ],
            'destination' => ['type' => 'string', 'description' => 'Target URL. Empty string clears it.'],
            'type' => [
                'type' => 'integer',
                'description' => 'HTTP status: 301, 302, 307, 410 or 451. 0 clears the type (demotes to a bare 404).',
            ],
            'enabled' => ['type' => 'boolean', 'description' => 'Whether the redirect fires.'],
            'enabled_regex' => ['type' => 'boolean', 'description' => 'Treat origin as a regex pattern.'],
            'logged_status' => [
                'type' => 'string',
                'description' => 'Audience: both | only_logged_in | only_not_logged_in. Empty clears it.',
            ],
            'param' => [
                'type' => 'string',
                'description' => 'Query-parameter mode: exact_match | without_param | with_ignored_param. Empty clears it.',
            ],
            'category_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => 'seopress_404_cat term ids. REPLACES the existing set; [] detaches all.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'redirect' => ['type' => 'object'],
        ],
        'required' => ['redirect'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_edit_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial merge of one SeoPress Pro redirect / 404 (the seopress_404 CPT) by id. Send only changed fields. origin cannot be blanked. type 0 clears it (demotes to a bare 404); type/logged_status/param are whitelisted. PROMOTE a monitored 404 to a redirect by giving it a type + destination. category_ids REPLACES the set ([] detaches all). Validates before writing; re-running the same call is idempotent.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{redirect: array<string, mixed>}|WP_Error
 */
function seopress_edit_redirect(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }
    if (!sp_redirect_supported()) {
        return sp_pro_required('Redirects');
    }

    $post = sp_resolve_redirect($args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $invalid = sp_validate_redirect_input($args, require_origin: 'optional');
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    $id = $post->ID;

    if (array_key_exists('origin', $args) && is_string($args['origin'])) {
        wp_update_post(['ID' => $id, 'post_title' => sp_redirect_normalize_origin($args['origin'])]);
    }

    $set = static fn(string $key, mixed $value): mixed => update_post_meta($id, meta_key: $key, meta_value: $value);
    $del = static fn(string $key): bool => delete_post_meta($id, meta_key: $key);
    sp_apply_redirect_fields($set, $del, $args);

    if (array_key_exists('category_ids', $args)) {
        wp_set_post_terms($id, sp_redirect_category_id_list($args), taxonomy: 'seopress_404_cat');
    }

    /** @var \WP_Post|null $fresh */
    $fresh = get_post($id);
    if (!$fresh instanceof \WP_Post) {
        return new WP_Error('seopress_edit_failed', 'Failed to read back the redirect', ['status' => 500]);
    }
    return ['redirect' => sp_redirect_row($fresh)];
}
