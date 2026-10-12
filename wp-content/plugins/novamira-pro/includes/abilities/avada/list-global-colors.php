<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-list-global-colors', [
    'label' => __('List Avada Global Colors', domain: 'novamira-pro'),
    'description' => __(
        'Enumerate the AWB Global Colors palette. Each row: slug (`color1`..`colorN`), label, and current color value (typically an `rgba(...)` string). Read-only. Palette storage lives in `fusion_options.color_palette`; the ability reads through `AWB_Global_Colors::get_instance()->get_palette()` so any theme filter overlaying the base palette is honoured.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'colors' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'color' => ['type' => 'string'],
                    ],
                    'required' => ['slug', 'label', 'color'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'colors'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_list_global_colors',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read AWB Global Colors palette. Use `edit-global-color` to modify a single preset.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>|WP_Error
 */
function avada_list_global_colors(): array|WP_Error
{
    if (!avada_theme_available()) {
        return av_theme_not_active();
    }
    if (!class_exists('AWB_Global_Colors')) {
        return av_theme_not_active();
    }

    /** @var array<string, array<string, mixed>> $palette */
    $palette = \AWB_Global_Colors::get_instance()->get_palette();

    /** @var list<array{slug: string, label: string, color: string}> $colors */
    $colors = [];
    foreach ($palette as $slug => $entry) {
        $colors[] = awb_color_map($slug, $entry);
    }

    return [
        'total' => count($colors),
        'colors' => $colors,
    ];
}
