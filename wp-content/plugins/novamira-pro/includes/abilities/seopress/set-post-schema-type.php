<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-set-post-schema-type', [
    'label' => __('Set Post Schema Type (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Sets (PUT-style) the TYPE of one manual structured-data (rich snippets) row on a post or page (requires SeoPress Pro). SeoPress Pro stores manual schemas as a list of rows; this ability sets the row at `index` (default 0) to the given `type`. If no row exists at that index, it CREATES the row with just the type — then use edit-post-schema to fill in its `_seopress_pro_rich_snippets_*` fields (call list-schema-fields(type) to learn the keys). If a row already exists at that index with a DIFFERENT type, switching the type starts a CLEAN row of the new type: every foreign field key from the old type is DROPPED (only the type is kept) so the row never carries stale, wrong-type fields. Re-setting the SAME type is idempotent (no field loss). type must be one of: articles, localbusiness, recipes, jobs, videos, events, products, softwareapp, services, review, faq, howto, courses, custom. Note the type→key-stem quirk (articles → _article_* keys, products → _product_*) — list-schema-fields resolves it. Identify the post by post_id (alias: id). Returns the re-read schema view (same shape as get-post-schema).',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'id' => ['type' => 'integer', 'description' => 'Alias for post_id.'],
            'index' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'The 0-based row position to set. Defaults to 0. A gap past the end appends.',
            ],
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
                'description' => 'The schema type for the row.',
            ],
        ],
        'required' => ['type'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_set_post_schema_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PUT the type of a manual rich-snippets row (index default 0). Missing row → created with just the type. Existing row, SAME type → idempotent no-op. Existing row, DIFFERENT type → foreign fields DROPPED (clean row of the new type). Then fill fields with edit-post-schema (keys from list-schema-fields). Returns the full schema view.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Returns the get-post-schema view shape (post_id, schemas, disabled_all,
 * rendering_enabled) — declared loosely because it is produced inside the
 * sp_with_post_lock closure; the precise shape is the get-post-schema contract.
 *
 * @param array{post_id?: mixed, id?: mixed, index?: mixed, type?: mixed} $args
 * @return array<string, mixed>|WP_Error
 */
function seopress_set_post_schema_type(array $args): array|WP_Error
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

    $type = sp_str($args['type'] ?? null);
    if (!in_array($type, sp_schema_types(), strict: true)) {
        return sp_invalid('type must be one of: ' . implode(', ', sp_schema_types()));
    }

    // Validate the RAW index before sp_int (which would collapse a negative to 0
    // and silently mutate row 0 instead of rejecting).
    /** @var mixed $index */
    $index = $args['index'] ?? 0;
    if (!is_int($index) || $index < 0) {
        return sp_invalid('index must be a non-negative integer');
    }

    $id = $post->ID;

    // Lock the per-post read-modify-write so a concurrent schema call on the SAME
    // post cannot interleave and drop a row.
    return sp_with_post_lock($id, static function () use ($id, $index, $type): array {
        $rows = sp_schema_rows($id);
        $rows = sp_schema_apply_type($rows, $index, $type);
        sp_schema_write_rows($id, $rows);
        return sp_schema_view($id);
    });
}

/**
 * Set the row at $index to $type. An absent row (index >= count, or a gap) is
 * created with just the type. An existing row of the SAME type is left untouched
 * (idempotent — its fields survive). An existing row of a DIFFERENT type is
 * reset to a clean row carrying ONLY the new type (foreign fields dropped).
 * Rows are kept dense (a gap appends at the end rather than leaving holes).
 *
 * @param list<array<array-key, mixed>> $rows
 * @return list<array<array-key, mixed>>
 */
function sp_schema_apply_type(array $rows, int $index, string $type): array
{
    $type_only = [SP_SCHEMA_TYPE_KEY => $type];

    if ($index < 0 || $index >= count($rows)) {
        $rows[] = $type_only;
        return array_values($rows);
    }

    if (sp_schema_row_type($rows[$index]) === $type) {
        return array_values($rows);
    }

    $rows[$index] = $type_only;
    return array_values($rows);
}
