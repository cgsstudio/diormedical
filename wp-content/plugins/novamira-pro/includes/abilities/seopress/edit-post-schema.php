<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-edit-post-schema', [
    'label' => __('Edit Post Schema (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'Partial-merges the FIELDS of one manual structured-data (rich snippets) row on a post or page (requires SeoPress Pro). The row at `index` (default 0) MUST already exist — set its type first with set-post-schema-type. `fields` is an object of full `_seopress_pro_rich_snippets_*` key → value; call list-schema-fields(type) to discover the exact keys for the row\'s type (the keys differ from the type value: articles → _article_*, products → _product_*). Every provided key is VALIDATED against the row\'s CURRENT type and an unknown key is rejected (seopress_invalid_input) — except `_seopress_pro_rich_snippets_custom` (raw JSON-LD <script>) which is allowed only on a `custom` row. CLEARING: a field whose value is "" DELETES that key from the row. Sanitisation mirrors SeoPress exactly: the custom JSON-LD via wp_kses (the <script type="application/ld+json"> tag is PRESERVED); array (repeater) values via map_deep + sanitize_text_field; everything else via sanitize_text_field. CURATED SCOPE: this ability is for the SCALAR fields and the custom JSON-LD. The deeply-nested repeaters (howto steps `_seopress_pro_rich_snippets_how_to`, faq `_seopress_pro_rich_snippets_faq`, course offers/instances, product positive/negative notes) accept an array value and are sanitised, but authoring those nested structures by hand is error-prone — prefer execute-php for them. Identify the post by post_id (alias: id). Returns the re-read schema view (same shape as get-post-schema).',
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
                'description' => 'The 0-based row position to edit. Defaults to 0. The row must already exist.',
            ],
            'fields' => [
                'type' => 'object',
                'description' =>
                    'Full `_seopress_pro_rich_snippets_*` key → value pairs to merge into the row. '
                        . 'An empty-string value deletes that key. Keys must match the row\'s type (see list-schema-fields).',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['fields'],
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
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_edit_post_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial-merge a manual rich-snippets row\'s fields (index default 0; the row must exist — set the type first). fields = full `_seopress_pro_rich_snippets_*` keys for the row\'s type (from list-schema-fields). Unknown keys are rejected; custom JSON-LD is allowed only on a custom row and its <script> survives wp_kses. Value "" deletes a key. For the nested repeaters (howto/faq/courses/product notes) prefer execute-php. Returns the full schema view.',
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
 * @param array{post_id?: mixed, id?: mixed, index?: mixed, fields?: mixed} $args
 * @return array<string, mixed>|WP_Error
 */
function seopress_edit_post_schema(array $args): array|WP_Error
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

    /** @var mixed $fields */
    $fields = $args['fields'] ?? null;
    if (!is_array($fields)) {
        return sp_invalid('fields must be an object of `_seopress_pro_rich_snippets_*` key => value pairs');
    }

    $id = $post->ID;

    // Validate the RAW index before sp_int (which would collapse a negative to 0
    // and silently edit row 0 instead of rejecting).
    /** @var mixed $index */
    $index = $args['index'] ?? 0;
    if (!is_int($index) || $index < 0) {
        return sp_invalid('index must be a non-negative integer');
    }

    // Lock the per-post read-modify-write so a concurrent schema call on the SAME
    // post cannot interleave and drop a row. The row-existence check, the
    // per-type field validation and the write all run under the lock on a fresh read.
    return sp_with_post_lock($id, static function () use ($id, $index, $fields): array|WP_Error {
        $rows = sp_schema_rows($id);
        if ($index >= count($rows)) {
            return sp_invalid("no schema row at index {$index}. Set the type first with set-post-schema-type.");
        }

        $type = sp_schema_row_type($rows[$index]);
        $invalid = sp_schema_validate_fields($type, $fields);
        if ($invalid instanceof WP_Error) {
            return $invalid;
        }

        $rows[$index] = sp_schema_merge_fields($rows[$index], $fields);
        sp_schema_write_rows($id, $rows);
        return sp_schema_view($id);
    });
}
