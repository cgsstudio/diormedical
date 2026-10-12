<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-edit-term-seo', [
    'label' => __('Edit Term SEO (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Updates the SeoPress metadata for a single taxonomy term (category, tag, or custom-taxonomy term). Send ONLY the fields to change — it is a partial merge, and nested blocks (robots, facebook, twitter, redirect) merge field-by-field. CLEARING semantics: because SeoPress stores "no override" as an ABSENT row, passing an empty string (or false / empty array / 0) for a field DELETES it so the term inherits the per-taxonomy / global default — it never stores a blank. Fields: title (_seopress_titles_title, may contain %%dynamic_variables%%), description (_seopress_titles_desc — returned single-line: newlines collapse to spaces on read), canonical (URL), robots {noindex, nofollow, nosnippet, noimageindex as booleans — true sets the override, false clears it; the key name is the NEGATIVE so noindex=true means "do not index"; breadcrumbs_hide is Pro-written and IGNORED on write}, facebook / twitter {title, description, image, image_id} (clearing image also clears its attachment-id/width/height companions), redirect {enabled (bool), type (one of 301/302/307; 0 clears), value (URL), logged_status (both | only_logged_in | only_not_logged_in)}. There is NO target_keywords field — content-analysis keywords are post-only. All provided fields are validated FIRST and rejected together (seopress_invalid_input, 400) before anything is written. Term SEO is a FREE SeoPress feature (no Pro needed). Identify the term by term_id AND taxonomy (BOTH required). Returns the full re-read SEO.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy the term belongs to (e.g. category).'],
            'title' => ['type' => 'string', 'description' => 'Meta title template. Empty string clears the override.'],
            'description' => [
                'type' => 'string',
                'description' => 'Meta description. Returned single-line (newlines collapse to spaces on read). Empty string clears the override.',
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
        'required' => ['term_id', 'taxonomy'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string'],
            'seo' => ['type' => 'object'],
        ],
        'required' => ['term_id', 'taxonomy', 'seo'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_edit_term_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial merge — only the fields you send change. Clearing a field (empty string, false, [], or 0) DELETES the SeoPress override so the term inherits the per-taxonomy / global default; it never stores a blank. robots flags are NEGATIVE-sense booleans (noindex=true means do-not-index; false clears the override, not "index"); breadcrumbs_hide is ignored. There is no target_keywords field (post-only). Term SEO is free (no Pro needed). Both term_id and taxonomy are required. Read first with seopress-get-term-seo when unsure; the returned shape can be edited and passed straight back.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{term_id?: int|string, taxonomy?: string} $args
 * @return array{term_id: int, taxonomy: string, seo: array<string, mixed>}|WP_Error
 */
function seopress_edit_term_seo(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }

    $term = sp_resolve_term($args['term_id'] ?? null, $args['taxonomy'] ?? null);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $invalid = sp_validate_seo_input($args);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    $id = $term->term_id;
    $set = static fn(string $key, mixed $value): mixed => update_term_meta($id, meta_key: $key, meta_value: $value);
    $del = static fn(string $key): bool => delete_term_meta($id, meta_key: $key);
    sp_apply_seo($set, $del, $args);

    return ['term_id' => $id, 'taxonomy' => $term->taxonomy, 'seo' => sp_term_seo($term)];
}
