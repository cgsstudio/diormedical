<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-get-slide', [
    'label' => __('Get Avada Fusion Slide', domain: 'novamira-pro'),
    'description' => __(
        'Read a single Fusion Slider slide (a `slide` post) with its full `_fusion` postmeta bag — heading + caption, media routing (image/youtube/vimeo/mp4), color overrides, per-slide link. Also returns the featured-image URL (background of the slide when `type=image`) and the slider(s) the slide is assigned to. Fields whose serialised value exceeds `max_field_bytes` are truncated and listed in `truncated_fields[]` (progressive disclosure). Set `max_field_bytes: 0` to opt out of truncation. Read-only; requires Fusion Core plugin active at min version.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Byte cap per individual `_fusion` bag field. Default 20000. Set to 0 to opt out.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'modified' => ['type' => 'string'],
            'featured_image' => [
                'oneOf' => [
                    [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'url' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'url'],
                        'additionalProperties' => false,
                    ],
                    ['type' => 'null'],
                ],
            ],
            'sliders' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'slug', 'name'],
                    'additionalProperties' => false,
                ],
            ],
            'meta' => [
                'type' => 'object',
                'description' => 'The `_fusion` postmeta bag as stored. Keys are Fusion Slider slide field names (heading, caption, type, video ids, colors, ...). Values are the raw stored form.',
                'additionalProperties' => true,
            ],
            'truncated_fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Names of fields whose value exceeded `max_field_bytes` and was truncated in the response.',
            ],
        ],
        'required' => [
            'id',
            'title',
            'slug',
            'status',
            'modified',
            'featured_image',
            'sliders',
            'meta',
            'truncated_fields',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_get_slide',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read one slide with its `_fusion` bag + featured image + slider assignments. Progressive disclosure via `max_field_bytes`.',
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
function avada_get_slide(array $input): array|WP_Error
{
    if (!fusion_core_available()) {
        return av_fusion_core_not_active();
    }

    $id = avada_coerce_int($input['id'] ?? 0, minimum: 0);
    if ($id <= 0) {
        return av_invalid_input('id must be a positive integer');
    }
    $post = av_post_or_error($id, [AVADA_CPT_SLIDE]);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $max_bytes = avada_coerce_int($input['max_field_bytes'] ?? AVADA_DEFAULT_MAX_FIELD_BYTES, minimum: 0);

    // Featured image resolves to the slide background when `type=image`
    // (Fusion Slider convention). Kept in a dedicated field rather than
    // stuffing the URL into `meta` because the source of truth is the
    // WordPress attachment layer, not the `_fusion` bag.
    $thumb_id = (int) get_post_thumbnail_id($post->ID);
    $featured_image = null;
    if ($thumb_id > 0) {
        $url = wp_get_attachment_url($thumb_id);
        if (is_string($url)) {
            $featured_image = ['id' => $thumb_id, 'url' => $url];
        }
    }

    // Slider assignments (a slide may belong to multiple sliders in theory,
    // one in practice).
    /** @var array<int, \WP_Term>|WP_Error $terms */
    $terms = get_the_terms($post->ID, AVADA_TAX_SLIDER);
    $sliders = [];
    if (is_array($terms)) {
        foreach ($terms as $t) {
            $sliders[] = [
                'id' => (int) $t->term_id,
                'slug' => $t->slug,
                'name' => $t->name,
            ];
        }
    }

    // The `_fusion` bag. Byte-cap each value independently so a single
    // very-long field (e.g. an inlined data:URI preview_image) doesn't
    // force truncation of siblings.
    $raw_meta = av_get_slide_meta($post->ID);
    $meta = [];
    $truncated = [];
    /** @var mixed $value */
    foreach ($raw_meta as $key => $value) {
        if (is_scalar($value)) {
            [$body, $was_truncated] = avada_truncate_body((string) $value, $max_bytes);
            $meta[$key] = $body;
            if ($was_truncated) {
                $truncated[] = $key;
            }
            continue;
        }
        // Non-scalar values pass through unchanged (arrays / objects) —
        // the JSON serialisation on the wire handles size on its own; a
        // structured field is out of scope for the byte-cap trick.
        $meta[$key] = $value;
    }

    return [
        'id' => (int) $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'status' => $post->post_status,
        'modified' => $post->post_modified_gmt,
        'featured_image' => $featured_image,
        'sliders' => $sliders,
        // Cast to `(object)` so an empty `_fusion` postmeta bag
        // JSON-serialises as `{}` rather than `[]` (output_schema
        // declares `type: object`). Same footgun as the round-1
        // code-review F1 fix for get-theme-options.
        'meta' => (object) $meta,
        'truncated_fields' => $truncated,
    ];
}
