<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-list-fonts', [
    'label' => __('List GeneratePress Fonts', domain: 'novamira-pro'),
    'description' => __(
        'Lists the fonts the GP Premium Font Library has bundled (Google Fonts downloaded locally + custom uploads). Each row carries `{id, name, slug, fontFamily, alias, variants, source, fallback, fontDisplay, preview, cssVariable, disabled}`. Notable: `cssVariable` is the CSS custom-property name (e.g. `--gp-font--inter`) you reference in `generate_settings.font_body` / `font_headings`; `disabled=true` means the post is not in `publish` status; `source` may be `""` when GP Premium did not record a provenance (most Google downloads in 2.5.x leave it blank — fall back to per-variant `source` inside `variants` to know if a row came from Google CDN vs custom upload).',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Optional slug filter — return only the font whose `post_name` equals this value. Empty / missing returns the entire library.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'fonts' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['fonts', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_list_fonts',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this whenever the user mentions fonts / typography on a GeneratePress site. The `cssVariable` of each row is what you reference inside `generate_settings.font_body` / `font_headings` (via generatepress-set-settings) — never write a raw family string when a library font exists.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{name?: string} $input
 * @return array<string, mixed>
 */
function generatepress_list_fonts(array $input): array
{
    $name = isset($input['name']) ? trim($input['name']) : '';
    $fonts = class_exists('GeneratePress_Pro_Font_Library')
        ? \GeneratePress_Pro_Font_Library::get_fonts($name === '' ? null : $name)
        : [];
    return [
        'fonts' => $fonts,
        'total' => count($fonts),
    ];
}
