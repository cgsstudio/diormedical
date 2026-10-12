<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read one post's per-post Blocksy options with default resolution.
 */

wp_register_ability('novamira/blocksy-get-page-options', [
    'label' => __('Get Blocksy Page Options', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Blocksy per-post options of ONE post/page/CPT entry (the "Blocksy Settings" panel of the editor): page-title/hero mode and its custom hero settings (has_hero_section, hero_section, hero_elements, alignment, height, background, typography, colors, padding), page structure (page_structure_type: inherit / narrow / normal / left or right sidebar), content area style + design (content_style_source, content_style, background, content_background, boxed spacing/radius/border/shadow), vertical spacing (vertical_spacing_source, content_area_spacing), and the element switches (disable_featured_image, disable_share_box, disable_header, disable_footer; posts add disable_post_tags, disable_author_box, disable_posts_navigation, disable_related_posts). The key set is resolved per post from the theme\'s own per-post option files, exactly as the block editor loads them: posts and pages have their own schema, the posts page and the WooCommerce shop page only accept disable_header/disable_footer (schema=blog), Blocksy-supported public CPTs get the default schema; products, attachments and builder/plugin post types have none. Blocksy never stores defaults — every row resolves the effective value and says whether it is stored or the option-file default. Stored keys the schema does not model (the theme\'s styles_descriptor CSS cache, Companion keys) are listed by name only.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'Post id (any status except auto-draft; not a revision, not a Content Block).',
            ],
            'ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional: restrict the response to these option ids (unknown ids are reported under `unknown_ids`, never an error).',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'post_type' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'schema' => [
                'type' => 'string',
                'enum' => ['post', 'page', 'blog', 'default', 'integration'],
                'description' => 'Which per-post option file applies: post, page, blog (posts page / shop page — only disable_header/disable_footer are honored there), default (supported custom post types) or integration (a post type a Blocksy integration registers for the editor panel and supplies the schema for through its filters, e.g. tribe_events).',
            ],
            'special' => [
                'type' => ['string', 'null'],
                'enum' => ['posts_page', 'shop_page', null],
                'description' => 'Set when the post is the posts page or the WooCommerce shop page (hence schema=blog).',
            ],
            'has_meta_row' => [
                'type' => 'boolean',
                'description' => 'Whether a blocksy_post_meta_options meta row exists for the post at all (raw row check). An editor save with no overrides leaves a row holding an empty array — then has_meta_row=true and has_stored_options=false.',
            ],
            'has_stored_options' => [
                'type' => 'boolean',
                'description' => 'True when the stored options array holds at least one key (schema-modelled or not). False when the row is absent AND when it exists but is empty — see has_meta_row for the distinction.',
            ],
            'options' => [
                'type' => 'object',
                'description' => 'Option id → {type, label, section, value, source: stored|default, default? (stored rows only), choices? (finite vocabularies)}. value is the effective value: the stored one when source=stored, else the option-file default. Oversized values (>16 KB JSON) are replaced with a marker string.',
            ],
            'extra_stored_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Stored keys the schema does not model — names only (styles_descriptor is the theme\'s per-post CSS cache, managed automatically by blocksy-edit-page-options).',
            ],
            'unknown_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Requested `ids` that are not in this post\'s schema.',
            ],
        ],
        'required' => [
            'post_id',
            'post_type',
            'title',
            'schema',
            'special',
            'has_meta_row',
            'has_stored_options',
            'options',
            'extra_stored_keys',
            'unknown_ids',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_get_page_options',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to inspect or plan per-page overrides (hide the page title on one landing page, go full-width, drop the header/footer, kill the content spacing) without touching the global customizer options. source=default means the post inherits — the value shown is what renders. Pass `ids` to keep the response small once you know the keys; `choices` lists the exact values blocksy-edit-page-options accepts.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: mixed, ids?: mixed} $input
 * @return array{
 *     post_id: int,
 *     post_type: string,
 *     title: string,
 *     schema: string,
 *     special: string|null,
 *     has_meta_row: bool,
 *     has_stored_options: bool,
 *     options: array<array-key, array<string, mixed>>|\stdClass,
 *     extra_stored_keys: list<string>,
 *     unknown_ids: list<string>,
 * }|WP_Error
 */
function blocksy_get_page_options(array $input): array|WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }

    $post = bly_po_get_post($input['post_id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $wanted = null;
    if (array_key_exists('ids', $input)) {
        $wanted = bly_po_parse_ids($input['ids']);
        if ($wanted instanceof WP_Error) {
            return $wanted;
        }
    }

    $schema = bly_po_schema($post);
    if ($schema instanceof WP_Error) {
        return $schema;
    }

    $stored = bly_po_read_stored($post->ID);
    $unknown = [];
    $rows = gpo_select_rows($schema['index'], $stored, $wanted, $unknown);

    return [
        'post_id' => $post->ID,
        'post_type' => $post->post_type,
        'title' => bly_ext_clean_text($post->post_title, BLY_EXT_MAX_NAME_BYTES),
        'schema' => $schema['kind'],
        'special' => $schema['special'],
        'has_meta_row' => bly_po_meta_row_exists($post->ID),
        'has_stored_options' => $stored !== [],
        'options' => bly_object_or_map($rows),
        'extra_stored_keys' => bly_po_extra_keys($stored, $schema['index']),
        'unknown_ids' => $unknown,
    ];
}

/**
 * Parse the `ids` input: a non-empty list of non-empty strings, deduped,
 * at most BLY_PO_MAX_IDS.
 *
 * @return list<string>|WP_Error
 */
function bly_po_parse_ids(mixed $raw): array|WP_Error
{
    if (!is_array($raw) || $raw === [] || !bly_array_is_list($raw)) {
        return bly_error('bly_invalid_input', message: '`ids` must be a non-empty list of option id strings.');
    }
    if (count($raw) > BLY_PO_MAX_IDS) {
        return bly_error('bly_invalid_input', sprintf(
            '`ids` holds %d entries, above the %d cap.',
            count($raw),
            BLY_PO_MAX_IDS,
        ));
    }
    $out = [];
    /** @var mixed $id */
    foreach ($raw as $id) {
        if (!is_string($id) || trim($id) === '') {
            return bly_error('bly_invalid_input', message: '`ids` entries must be non-empty strings.');
        }
        $id = trim($id);
        if (!in_array($id, $out, strict: true)) {
            $out[] = $id;
        }
    }
    return $out;
}

/**
 * The resolved rows to emit: every schema row, or only the requested ids
 * (ids missing from the schema are collected into `$unknown`).
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @param array<array-key, mixed> $stored
 * @param list<string>|null $wanted
 * @param list<string> $unknown
 * @param-out list<string> $unknown
 * @return array<string, array<string, mixed>>
 */
function gpo_select_rows(array $index, array $stored, ?array $wanted, array &$unknown): array
{
    $rows = [];
    if ($wanted === null) {
        foreach ($index as $id => $row) {
            $rows[$id] = bly_po_resolve_row($row, $stored);
        }
        return $rows;
    }
    foreach ($wanted as $id) {
        if (!isset($index[$id])) {
            $unknown[] = bly_ext_clean_text($id, BLY_MAX_EXTRA_KEY_BYTES);
            continue;
        }
        $rows[$id] = bly_po_resolve_row($index[$id], $stored);
    }
    return $rows;
}
