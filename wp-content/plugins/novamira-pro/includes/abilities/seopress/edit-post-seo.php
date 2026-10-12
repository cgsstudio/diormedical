<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-edit-post-seo', [
    'label' => __('Edit Post SEO (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Updates the SeoPress metadata for a single post or page. Send ONLY the fields to change — it is a partial merge, and nested blocks (robots, facebook, twitter, redirect) merge field-by-field. CLEARING semantics: because SeoPress stores "no override" as an ABSENT row, passing an empty string (or false / empty array / 0) for a field DELETES it so the post inherits the global / per-post-type default — it never stores a blank. Fields: title (_seopress_titles_title, may contain %%dynamic_variables%%), description (_seopress_titles_desc — SeoPress stores it single-line, collapsing newlines to spaces on save), canonical (URL), robots {noindex, nofollow, nosnippet, noimageindex as booleans — true sets the override, false clears it; the key name is the NEGATIVE so noindex=true means "do not index"; breadcrumbs_hide is Pro-written and IGNORED on write}, target_keywords (a list of strings — SeoPress keeps ONE comma-joined row, so the whole list is rewritten and a keyword may NOT contain a comma (it would split into two; rejected with seopress_invalid_input); [] clears it), facebook / twitter {title, description, image, image_id} (clearing image also clears its attachment-id/width/height companions), redirect {enabled (bool), type (one of 301/302/307; 0 clears), value (URL), logged_status (both | only_logged_in | only_not_logged_in)}. All provided fields are validated FIRST and rejected together (seopress_invalid_input, 400) before anything is written. Identify the post by post_id (alias: id). Returns the full re-read SEO.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'title' => ['type' => 'string', 'description' => 'Meta title template. Empty string clears the override.'],
            'description' => [
                'type' => 'string',
                'description' => 'Meta description. SeoPress stores it single-line (newlines collapse to spaces). Empty string clears the override.',
            ],
            'canonical' => ['type' => 'string', 'description' => 'Canonical URL. Empty string clears it.'],
            'robots' => [
                'type' => 'object',
                'properties' => [
                    'noindex' => ['type' => 'boolean'],
                    'nofollow' => ['type' => 'boolean'],
                    'nosnippet' => ['type' => 'boolean'],
                    'noimageindex' => ['type' => 'boolean'],
                    'breadcrumbs_hide' => [
                        'type' => 'boolean',
                        'description' => 'Pro-written and read-only; accepted for round-tripping but ignored on write.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'target_keywords' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Content-analysis keywords. Stored as one comma-joined row, so a keyword may not contain a comma; [] clears it.',
            ],
            'facebook' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image' => ['type' => 'string'],
                    'image_id' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
            'twitter' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image' => ['type' => 'string'],
                    'image_id' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
            'redirect' => [
                'type' => 'object',
                'properties' => [
                    'enabled' => ['type' => 'boolean'],
                    'type' => [
                        'type' => 'integer',
                        'description' => 'Redirect status code: 301, 302 or 307. 0 clears the redirect type.',
                    ],
                    'value' => ['type' => 'string'],
                    'logged_status' => [
                        'type' => 'string',
                        'description' => 'Audience: both | only_logged_in | only_not_logged_in.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'seo' => ['type' => 'object'],
        ],
        'required' => ['post_id', 'seo'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_edit_post_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial merge — only the fields you send change. Clearing a field (empty string, false, [], or 0) DELETES the SeoPress override so the post inherits the global / per-post-type default; it never stores a blank. robots flags are NEGATIVE-sense booleans (noindex=true means do-not-index; false clears the override, not "index"); breadcrumbs_hide is ignored. target_keywords replaces the whole comma-joined list. Read first with seopress-get-post-seo when unsure; the returned shape can be edited and passed straight back.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{post_id: int, seo: array<string, mixed>}|WP_Error
 */
function seopress_edit_post_seo(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }

    $post = sp_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $invalid = sp_validate_post_seo_input($args);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    $id = $post->ID;
    $set = static fn(string $key, mixed $value): mixed => update_post_meta($id, meta_key: $key, meta_value: $value);
    $del = static fn(string $key): bool => delete_post_meta($id, meta_key: $key);
    sp_apply_seo($set, $del, $args);

    return ['post_id' => $id, 'seo' => sp_post_seo($post)];
}
