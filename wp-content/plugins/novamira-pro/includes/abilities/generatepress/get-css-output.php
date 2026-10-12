<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-get-css-output', [
    'label' => __('Get GeneratePress Dynamic CSS', domain: 'novamira-pro'),
    'description' => __(
        'Returns the CSS strings GeneratePress builds at runtime from `generate_settings` and (when active) the Font Library. Useful for debugging why a style choice is not landing on the front-end. Five streams: `base` (theme container / colour variables — generate_base_css), `typography` (font-family / size / weight rules — generate_typography_default_styles), `spacing` (container / element spacing — generate_spacing_css), `advanced` (top bar / secondary nav / mobile menu — generate_advanced_css), and `font_library` (the dynamic `@font-face` rules from GP Premium\'s Font Library, empty string when the module is off). The combined output mirrors the inline `<style id="generate-style-inline-css">` block on the front-end, minus the small `generate_no_cache_dynamic_css` fragment that GP injects at enqueue time.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'streams' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['base', 'typography', 'spacing', 'advanced', 'font_library'],
                ],
                'description' => 'Which streams to include. Pass a subset to reduce payload size (e.g. ["font_library"] when only debugging font rendering).',
                'default' => ['base', 'typography', 'spacing', 'advanced', 'font_library'],
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'base' => ['type' => 'string'],
            'typography' => ['type' => 'string'],
            'spacing' => ['type' => 'string'],
            'advanced' => ['type' => 'string'],
            'font_library' => ['type' => 'string'],
            'total_length' => ['type' => 'integer'],
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_get_css_output',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Debug tool — only call when investigating a styling issue (a setting that does not seem to render, a font that does not load, an unexpected layout width). For day-to-day work, generatepress-get-settings is enough.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{streams?: list<string>} $input
 * @return array<string, mixed>
 */
function generatepress_get_css_output(array $input): array
{
    $streams = isset($input['streams']) && $input['streams'] !== []
        ? $input['streams']
        : ['base', 'typography', 'spacing', 'advanced', 'font_library'];

    $out = [];
    if (in_array('base', $streams, strict: true)) {
        $out['base'] = function_exists('generate_base_css') ? generate_base_css() : '';
    }
    if (in_array('typography', $streams, strict: true)) {
        $out['typography'] = gp_resolve_typography_css();
    }
    if (in_array('spacing', $streams, strict: true)) {
        $out['spacing'] = function_exists('generate_spacing_css') ? generate_spacing_css() : '';
    }
    if (in_array('advanced', $streams, strict: true)) {
        $out['advanced'] = function_exists('generate_advanced_css') ? generate_advanced_css() : '';
    }
    if (in_array('font_library', $streams, strict: true)) {
        $out['font_library'] = gp_font_library_active() && class_exists('GeneratePress_Pro_Font_Library')
            ? \GeneratePress_Pro_Font_Library::generate_font_css()
            : '';
    }
    $out['total_length'] = array_sum(array_map(static fn(string $v): int => strlen($v), $out));
    return $out;
}
