<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-get-post-schema', [
    'label' => __('Get Post Schema (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Reads the MANUAL structured-data (rich snippets) attached to a single post or page (requires SeoPress Pro). SeoPress Pro stores manual schemas as a list of rows on the post — each row has a type (one of articles, localbusiness, recipes, jobs, videos, events, products, softwareapp, services, review, faq, howto, courses, custom) plus its `_seopress_pro_rich_snippets_*` field values. Returns a LEAN view: { post_id, schemas: [ { index, type, fields } ], disabled_all, rendering_enabled }, where fields holds ONLY the POPULATED (non-empty) keys of each row — a type can have dozens of possible keys, so empty ones are omitted to keep the output small. Use list-schema-fields(type) to see every possible key for a type. index is the row\'s position (0-based) and is the handle set-post-schema-type / edit-post-schema address. disabled_all (read-only) is the per-post kill switch (_seopress_pro_rich_snippets_disable_all) that disables the AUTOMATIC/template schemas. rendering_enabled (read-only) reflects the SITE-WIDE rich-snippets feature (seopress_pro_option_name[seopress_rich_snippets_enable]): when false, SeoPress will NOT emit JSON-LD for these manual rows even though they are stored — enable the rich-snippets feature in SeoPress (or via execute-php) for the schema to actually render. schemas is [] when the post has no manual rows. This reads ONLY the manual schemas — not the automatic-template override. Identify the post by post_id (alias: id).',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'schemas' => ['type' => 'array'],
            'disabled_all' => ['type' => 'boolean'],
            'rendering_enabled' => ['type' => 'boolean'],
        ],
        'required' => ['post_id', 'schemas', 'disabled_all', 'rendering_enabled'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_get_post_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the per-post MANUAL rich-snippets rows. fields shows only the populated keys of each row (lean — call list-schema-fields for the full key set of a type). index is the row handle for set-post-schema-type / edit-post-schema. disabled_all is the read-only per-post switch that disables the automatic/template schemas. rendering_enabled=false means the site-wide rich-snippets feature is OFF, so stored rows will NOT render as JSON-LD until it is enabled. Does NOT touch the automatic-template override.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: mixed, id?: mixed} $args
 * @return array{
 *   post_id: int,
 *   schemas: list<array{index: int, type: string, fields: array<string, mixed>}>,
 *   disabled_all: bool,
 *   rendering_enabled: bool
 * }|WP_Error
 */
function seopress_get_post_schema(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }
    if (!sp_detect_setup()['pro']) {
        return sp_pro_required('Structured data');
    }

    $post = sp_resolve_post($args['post_id'] ?? $args['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    return sp_schema_view($post->ID);
}
