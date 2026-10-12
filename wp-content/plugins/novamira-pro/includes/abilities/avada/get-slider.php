<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-get-slider', [
    'label' => __('Get Avada Fusion Slider', domain: 'novamira-pro'),
    'description' => __(
        'Read a single Fusion Slider (a `slide-page` term) with its full `_fusion` term_meta settings bag (dimensions, autoplay, animation, navigation, indicator) plus a compact list of the slides assigned to it (id, title, slug, status, modified). Use `slider` as the term slug returned by `avada-list-sliders`, or the numeric term id. Set `include_slides: false` to skip slide enumeration when only the settings are needed. Read-only; requires Fusion Core plugin active at min version.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slider' => [
                'type' => ['string', 'integer'],
                'minLength' => 1,
                'minimum' => 1,
                'description' => 'Slug (from `avada-list-sliders.sliders[].slug`) or numeric term id. All-digit strings are resolved as slugs first, falling back to numeric ids.',
            ],
            'include_slides' => [
                'type' => 'boolean',
                'description' => 'Whether to enumerate the slides assigned to the slider. Default true.',
            ],
        ],
        'required' => ['slider'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'settings' => [
                'type' => 'object',
                'description' => 'The `_fusion` term_meta bag as stored. Keys are Fusion Slider setting names (slider_width, autoplay, animation, ...). Values are the raw stored form (strings, ints).',
                'additionalProperties' => true,
            ],
            'slide_count' => ['type' => 'integer'],
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
                    ],
                    'required' => ['id', 'title', 'slug', 'status', 'modified'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['id', 'slug', 'name', 'settings', 'slide_count', 'slides'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_get_slider',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read slider settings + assigned slides. Use include_slides: false to skip slide enumeration when only settings are needed.',
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
function avada_get_slider(array $input): array|WP_Error
{
    if (!fusion_core_available()) {
        return av_fusion_core_not_active();
    }

    /** @var mixed $ref */
    $ref = $input['slider'] ?? null;
    if (!is_string($ref) && !is_int($ref)) {
        return av_invalid_input('slider must be a string slug or numeric term id');
    }
    $term = av_slider_term_or_error($ref);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $include_slides = !isset($input['include_slides']) || $input['include_slides'] !== false;
    $slides = [];
    if ($include_slides) {
        /** @var array<int, \WP_Post>|WP_Error $posts */
        $posts = get_posts([
            'post_type' => AVADA_CPT_SLIDE,
            'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
            'numberposts' => -1,
            'orderby' => 'menu_order date',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => AVADA_TAX_SLIDER,
                    'field' => 'term_id',
                    'terms' => (int) $term->term_id,
                ],
            ],
            'suppress_filters' => false,
        ]);
        if (is_array($posts)) {
            foreach ($posts as $slide) {
                $slides[] = [
                    'id' => (int) $slide->ID,
                    'title' => $slide->post_title,
                    'slug' => $slide->post_name,
                    'status' => $slide->post_status,
                    'modified' => $slide->post_modified_gmt,
                ];
            }
        }
    }

    return [
        'id' => (int) $term->term_id,
        'slug' => $term->slug,
        'name' => $term->name,
        // Cast to `(object)` so an empty `_fusion` term_meta bag
        // JSON-serialises as `{}` rather than `[]` (output_schema
        // declares `type: object`). Same footgun as the round-1
        // code-review F1 fix for get-theme-options.
        'settings' => (object) av_get_slider_settings((int) $term->term_id),
        'slide_count' => (int) $term->count,
        'slides' => $slides,
    ];
}
