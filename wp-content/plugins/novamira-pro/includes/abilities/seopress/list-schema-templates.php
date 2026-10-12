<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-list-schema-templates', [
    'label' => __('List Schema Templates (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Lists SeoPress Pro\'s "automatic" structured-data templates (requires SeoPress Pro). These templates are the seopress_schemas custom post type: each one defines a schema.org type and a set of attach-RULES that decide which content it applies to (by post type, taxonomy or post id), so the schema is emitted automatically across matching pages — distinct from the MANUAL per-post schema you read with seopress-get-post-schema. This ability is a LEAN INDEX: it returns { templates: [ { id, title, type } ], total, limit, offset } where type is the template\'s schema.org type (_seopress_pro_rich_snippets_type, \'\' when unset). It deliberately does NOT return the attach-rules tree (a large nested blob) — use it to learn that the automatic/template system is present and what types are defined, not to edit it. To edit a template\'s type or rules, use novamira/execute-php (update_post_meta on _seopress_pro_rich_snippets_type / _seopress_pro_rich_snippets_rules). Paginate with limit (default 100, max 500 — clamped) and offset; total is the full count so you can page while offset + returned < total.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => ['type' => 'integer', 'description' => 'Max templates to return (1-500, default 100).'],
            'offset' => ['type' => 'integer', 'description' => 'Templates to skip (pagination).'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'templates' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
        ],
        'required' => ['templates', 'total', 'limit', 'offset'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_list_schema_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'List SeoPress Pro automatic schema templates (the seopress_schemas CPT). Lean index — returns {id, title, type} per template, NOT the attach-rules tree. type is the schema.org type. Paginate with limit (default 100, max 500) / offset; total is the full count. For a post\'s MANUAL schema use seopress-get-post-schema; to edit a template\'s rules use execute-php.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{limit?: int, offset?: int} $args
 * @return array{templates: list<array{id: int, title: string, type: string}>, total: int, limit: int, offset: int}|WP_Error
 */
function seopress_list_schema_templates(array $args): array|WP_Error
{
    if (!sp_detect_setup()['pro']) {
        return sp_pro_required('Structured data');
    }

    $limit = sp_int($args['limit'] ?? 0);
    $limit = $limit < 1 ? 100 : min($limit, 500);
    $offset = sp_int($args['offset'] ?? 0);

    $query = new WP_Query([
        'post_type' => 'seopress_schemas',
        'post_status' => 'any',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'date',
        'order' => 'DESC',
        // This CPT can never be sticky; the flag just stops WP_Query prepending
        // the sticky set, which would break the offset/total pagination contract.
        'ignore_sticky_posts' => true,
    ]);

    $templates = [];
    /** @var mixed $post */
    foreach ($query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $templates[] = sp_schema_template_row($post);
    }

    return [
        'templates' => $templates,
        'total' => $query->found_posts,
        'limit' => $limit,
        'offset' => $offset,
    ];
}

/**
 * The LEAN template projection: id, the post_title, and the schema.org type from
 * _seopress_pro_rich_snippets_type ('' when unset). The attach-rules blob
 * (_seopress_pro_rich_snippets_rules) is deliberately NOT included — it is a
 * large nested structure and this ability is only a reporting index.
 *
 * @return array{id: int, title: string, type: string}
 */
function sp_schema_template_row(WP_Post $post): array
{
    return [
        'id' => $post->ID,
        'title' => sp_str($post->post_title),
        'type' => sp_str(sp_post_meta('_seopress_pro_rich_snippets_type', $post->ID)),
    ];
}
