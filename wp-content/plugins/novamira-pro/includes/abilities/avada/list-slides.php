<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-list-slides', [
    'label' => __('List Avada Fusion Slides', domain: 'novamira-pro'),
    'description' => __(
        'Paginated enumeration of Fusion Slider slides (posts of type `slide`). Returns compact rows: id, title, slug, status, modified, and (for classification convenience) the parent slider slug/name if the slide belongs to exactly one slider. Filter by `slider` (slug or term id) to scope the listing to one slider; omit to list every slide on the site. `limit` is clamped to a hard maximum of 100 to keep responses agent-friendly (progressive disclosure — page through with `offset`). Read-only; requires Fusion Core plugin active at min version.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'slider' => [
                'type' => ['string', 'integer'],
                'minLength' => 1,
                'minimum' => 1,
                'description' => 'Optional. Slider slug or numeric term id to scope the listing.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['any', 'publish', 'draft', 'private', 'pending', 'future'],
                'description' => 'Post status filter. Default `any` (all visible statuses).',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'description' => 'Max rows per page. Hard-clamped to 100.',
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of rows to skip (pagination).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'limit' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'slides' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'modified' => ['type' => 'string'],
                        'slider' => [
                            'oneOf' => [
                                [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'slug' => ['type' => 'string'],
                                        'name' => ['type' => 'string'],
                                    ],
                                    'required' => ['id', 'slug', 'name'],
                                    'additionalProperties' => false,
                                ],
                                ['type' => 'null'],
                            ],
                            'description' => 'The single slider this slide belongs to. `null` when the slide is unassigned or assigned to multiple sliders.',
                        ],
                    ],
                    'required' => ['id', 'title', 'slug', 'status', 'modified', 'slider'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'returned', 'offset', 'limit', 'truncated', 'slides'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_list_slides',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Enumerate slides. Use `slider` filter to scope to one slider. Page through with `offset` + `limit` (max 100).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function avada_list_slides(array $input = []): array|WP_Error
{
    if (!fusion_core_available()) {
        return av_fusion_core_not_active();
    }

    $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : 'any';
    // Runtime enum guard (defense in depth against schema-bypassing PHP
    // callers) — same pattern the theme-side list-* abilities use.
    if (!in_array($status, av_accepted_statuses(), strict: true)) {
        $status = 'any';
    }

    $limit = avada_coerce_int($input['limit'] ?? 20, minimum: 1, maximum: 100);
    $offset = avada_coerce_int($input['offset'] ?? 0, minimum: 0);

    /** @var array<int, array{taxonomy: string, field: string, terms: int}> $tax_query */
    $tax_query = [];
    if (isset($input['slider'])) {
        /** @var mixed $ref */
        $ref = $input['slider'];
        if (!is_string($ref) && !is_int($ref)) {
            return av_invalid_input('slider must be a string slug or numeric term id');
        }
        $term = av_slider_term_or_error($ref);
        if ($term instanceof WP_Error) {
            return $term;
        }
        $tax_query[] = [
            'taxonomy' => AVADA_TAX_SLIDER,
            'field' => 'term_id',
            'terms' => (int) $term->term_id,
        ];
    }

    /** @var array<string, mixed> $args */
    $args = [
        'post_type' => AVADA_CPT_SLIDE,
        'post_status' => $status,
        'posts_per_page' => $limit,
        'offset' => $offset,
        'no_found_rows' => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => true,
        'suppress_filters' => false,
        'ignore_sticky_posts' => true,
        'orderby' => 'menu_order date',
        'order' => 'ASC',
    ];
    if ($tax_query !== []) {
        $args['tax_query'] = $tax_query;
    }

    $query = new \WP_Query($args);
    $total = (int) $query->found_posts;

    $slides = [];
    /** @var array<int, WP_Post> $posts */
    $posts = $query->posts;
    foreach ($posts as $slide) {
        $slides[] = avada_shape_slide_row($slide);
    }

    return [
        'total' => $total,
        'returned' => count($slides),
        'offset' => $offset,
        'limit' => $limit,
        'truncated' => ($offset + count($slides)) < $total,
        'slides' => $slides,
    ];
}

/**
 * Shape a single `slide` WP_Post into the list-slides row schema.
 * Extracted from the outer callback so the halstead effort stays
 * under threshold. The `slider` sub-object is populated only when
 * the slide belongs to EXACTLY ONE slide-page term (the common case);
 * multi-slider assignments fall back to `null` because a single-slot
 * field can't represent them faithfully.
 *
 * @return array<string, mixed>
 */
function avada_shape_slide_row(WP_Post $slide): array
{
    /** @var array<int, \WP_Term>|false $terms */
    $terms = get_the_terms($slide->ID, AVADA_TAX_SLIDER);
    $slider = null;
    if (is_array($terms) && count($terms) === 1) {
        $t = $terms[0];
        $slider = [
            'id' => (int) $t->term_id,
            'slug' => $t->slug,
            'name' => $t->name,
        ];
    }
    return [
        'id' => (int) $slide->ID,
        'title' => $slide->post_title,
        'slug' => $slide->post_name,
        'status' => $slide->post_status,
        'modified' => $slide->post_modified_gmt,
        'slider' => $slider,
    ];
}
