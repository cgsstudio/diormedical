<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-list-sliders', [
    'label' => __('List Avada Fusion Sliders', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates every Fusion Slider registered on this site. In Avada, a "slider" is a term of the `slide-page` taxonomy — this ability returns the slug + display name + numeric term id + slide count for each. Use before `avada-get-slider` (to know the slug) and before writing to `avada-edit-slider-settings`. Read-only; requires Fusion Core plugin active at min version.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'include_empty' => [
                'type' => 'boolean',
                'description' => 'Whether to include sliders with zero assigned slides. Default false.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'sliders' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'slide_count' => ['type' => 'integer'],
                    ],
                    'required' => ['id', 'slug', 'name', 'slide_count'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'sliders'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_list_sliders',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run first on any Fusion Slider workflow to discover slider slugs. Slug is the identity for all subsequent slider abilities.',
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
function avada_list_sliders(array $input = []): array|WP_Error
{
    if (!fusion_core_available()) {
        return av_fusion_core_not_active();
    }

    $include_empty = isset($input['include_empty']) && $input['include_empty'] === true;

    /** @var array<int, WP_Term>|WP_Error $terms */
    $terms = get_terms([
        'taxonomy' => AVADA_TAX_SLIDER,
        'hide_empty' => !$include_empty,
    ]);
    if ($terms instanceof WP_Error) {
        return [
            'total' => 0,
            'sliders' => [],
        ];
    }

    $sliders = [];
    foreach ($terms as $term) {
        $sliders[] = [
            'id' => (int) $term->term_id,
            'slug' => $term->slug,
            'name' => $term->name,
            // `count` on a WP_Term is maintained by core term-count callbacks
            // and matches what `hide_empty` gates on. For Fusion Slider terms
            // it counts published `slide` posts assigned to the term.
            'slide_count' => (int) $term->count,
        ];
    }

    return [
        'total' => count($sliders),
        'sliders' => $sliders,
    ];
}
