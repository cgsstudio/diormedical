<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-list-global-typography', [
    'label' => __('List Avada Global Typography', domain: 'novamira-pro'),
    'description' => __(
        'Enumerate the AWB Global Typography presets. Each row: slug (`typography1`..`typographyN`), label, family, size, weight, lineHeight, letterSpacing, textTransform. Read-only. Preset storage lives in `fusion_options.typography_sets`; the ability reads through `AWB_Global_Typography::get_instance()->get_typography()` so any theme filter overlaying the defaults is honoured.',
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
            'presets' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'family' => ['type' => 'string'],
                        'size' => ['type' => 'string'],
                        'weight' => ['type' => 'string'],
                        'lineHeight' => ['type' => 'string'],
                        'letterSpacing' => ['type' => 'string'],
                        'textTransform' => ['type' => 'string'],
                    ],
                    'required' => [
                        'slug',
                        'label',
                        'family',
                        'size',
                        'weight',
                        'lineHeight',
                        'letterSpacing',
                        'textTransform',
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'presets'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_list_global_typography',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read AWB Global Typography presets. Use `edit-global-typography` to modify a single preset.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>|WP_Error
 */
function avada_list_global_typography(): array|WP_Error
{
    if (!avada_theme_available()) {
        return av_theme_not_active();
    }
    if (!class_exists('AWB_Global_Typography')) {
        return av_theme_not_active();
    }

    /** @var array<string, array<string, mixed>> $typography */
    $typography = \AWB_Global_Typography::get_instance()->get_typography();

    /** @var list<array<string, string>> $presets */
    $presets = [];
    foreach ($typography as $slug => $entry) {
        $presets[] = awb_typography_map($slug, $entry);
    }

    return [
        'total' => count($presets),
        'presets' => $presets,
    ];
}
