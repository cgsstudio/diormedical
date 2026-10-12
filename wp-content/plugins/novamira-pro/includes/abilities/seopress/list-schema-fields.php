<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-list-schema-fields', [
    'label' => __('List Schema Fields (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Lists the manual structured-data (rich snippets) field keys for ONE SeoPress Pro schema type (requires SeoPress Pro). This is the FIELD-DISCOVERY VALVE for edit-post-schema: SeoPress stores per-post manual schemas as rows keyed by full `_seopress_pro_rich_snippets_*` meta keys, and the keys differ from the type value (a plural/singular quirk — e.g. type "articles" uses `_seopress_pro_rich_snippets_article_*` keys, type "products" uses `_product_*`). Call this with one type to learn exactly which keys edit-post-schema accepts, instead of carrying all 14 types\' keys in every schema. Returns { type, fields: [{ key, format }] } where key is the FULL meta key and format is a coarse hint: string | url | date | time | number | repeater(array) | json-ld-script. type "custom" returns a single json-ld-script field (`_seopress_pro_rich_snippets_custom` — raw JSON-LD <script>); "faq" and the howto steps key are repeater(array). The 14 types: articles, localbusiness, recipes, jobs, videos, events, products, softwareapp, services, review, faq, howto, courses, custom.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'enum' => [
                    'articles',
                    'localbusiness',
                    'recipes',
                    'jobs',
                    'videos',
                    'events',
                    'products',
                    'softwareapp',
                    'services',
                    'review',
                    'faq',
                    'howto',
                    'courses',
                    'custom',
                ],
                'description' => 'The schema type whose field keys to list.',
            ],
        ],
        'required' => ['type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string'],
            'fields' => ['type' => 'array'],
        ],
        'required' => ['type', 'fields'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_list_schema_fields',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The field-discovery valve for the manual rich-snippets editor. Pass one of the 14 schema types; get back its FULL `_seopress_pro_rich_snippets_*` field keys plus a coarse format hint. Use it before edit-post-schema so you send keys that match the row\'s type — the keys differ from the type value (articles → _article_*, products → _product_*). custom = one raw JSON-LD <script> field.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type?: mixed} $args
 * @return array{type: string, fields: list<array{key: string, format: string}>}|WP_Error
 */
function seopress_list_schema_fields(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }
    if (!sp_detect_setup()['pro']) {
        return sp_pro_required('Structured data');
    }

    $type = sp_str($args['type'] ?? null);
    $fields = sp_schema_fields_for($type);
    if ($fields === null) {
        return sp_invalid('type must be one of: ' . implode(', ', sp_schema_types()));
    }

    return ['type' => $type, 'fields' => $fields];
}
