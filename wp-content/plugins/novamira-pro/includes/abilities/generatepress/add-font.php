<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-add-font', [
    'label' => __('Add Google Font to GeneratePress Library', domain: 'novamira-pro'),
    'description' => __(
        'Downloads a Google Font into the local GP Premium Font Library and creates the matching `gp_font` post. Side effects: woff2/woff files written under `wp-content/uploads/generatepress/fonts/<slug>/`, post created with the variants meta filled in, fonts.css regenerated. Idempotent on `family` — calling twice with the same family + variants tops up missing variants but does not duplicate the post. Use generatepress-list-fonts after to confirm the cssVariable assigned. Each variant string follows the Google Fonts notation (`400`, `400italic`, `700`, `700italic`).',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'family' => [
                'type' => 'string',
                'description' => 'Google Font family name (e.g. "Inter", "Roboto Mono"). Case-sensitive — must match the Google Fonts catalogue exactly.',
            ],
            'variants' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Variants to download. Each entry combines weight + optional `italic` suffix (e.g. ["400", "400italic", "700"]).',
                'default' => ['400'],
            ],
        ],
        'required' => ['family'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'font' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_add_font',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use sparingly — every add-font hits Google Fonts CDN and writes binary files to uploads. Before calling, check generatepress-list-fonts to confirm the family is not already installed. Pair with generatepress-set-settings to point `font_body` / `font_headings` at the new cssVariable.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{family: string, variants?: list<string>} $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_add_font(array $input): array|WP_Error
{
    $family = trim($input['family']);
    if ($family === '') {
        return gp_error('gp_invalid_input', __('family is required.', domain: 'novamira-pro'));
    }
    // Schema declares `variants` as an array, but the abilities runtime
    // does NOT enforce per-property types before dispatch, so a string /
    // bool would crash the variant parser. Guard explicitly.
    /** @var mixed $raw_variants */
    $raw_variants = $input['variants'] ?? null;
    if ($raw_variants !== null && !is_array($raw_variants)) {
        return gp_error('gp_invalid_input', __('variants must be an array of strings.', domain: 'novamira-pro'));
    }
    $variants = is_array($raw_variants) && $raw_variants !== [] ? $raw_variants : ['400'];
    /** @var list<string> $variant_strings */
    $variant_strings = array_values(array_filter($variants, static fn($v): bool => is_string($v)));
    if ($variant_strings === []) {
        $variant_strings = ['400'];
    }

    $slug = sanitize_title($family);
    $base_variants = gp_format_google_font_variants($family, $variant_strings);
    $font_payload = ['name' => $family, 'fontFamily' => $family, 'slug' => $slug];

    // Step 1 — ask GP Premium to resolve the Google CDN URLs for each
    // variant (this is what its admin UI does before calling upload-fonts).
    // The optimize endpoint expects `font.name` (not fontFamily) — see
    // GeneratePress_Pro_Font_Library_Optimize::get_variants.
    $optimize = gp_dispatch_font_step('optimize-google-fonts', [
        'font' => $font_payload,
        'variants' => $base_variants,
    ]);
    if ($optimize['error'] !== null) {
        return ['success' => false, 'error' => 'optimize-google-fonts: ' . $optimize['error']];
    }
    $resolved = is_array($optimize['data'])
    && isset($optimize['data']['response'])
    && is_array($optimize['data']['response'])
        ? $optimize['data']['response']
        : $base_variants;

    // Step 2 — actually download + create the gp_font post.
    $upload = gp_dispatch_font_step('upload-fonts', [
        'font' => $font_payload,
        'variants' => $resolved,
        'source' => 'google',
        'slug' => $slug,
    ]);
    if ($upload['error'] !== null) {
        return ['success' => false, 'error' => 'upload-fonts: ' . $upload['error']];
    }

    $fonts = \GeneratePress_Pro_Font_Library::get_fonts($slug);
    if ($fonts === []) {
        // The upstream upload-fonts endpoint reports success even when
        // Google Fonts has no record for the family — only the per-variant
        // error list is populated. Clean up the empty directory it creates
        // and surface the real cause instead of "Font download succeeded".
        gp_cleanup_failed_font_dir($slug);
        return [
            'success' => false,
            'error' => sprintf(
                'Google Fonts has no record for "%s" (no variants downloaded). Check the spelling and casing against fonts.google.com.',
                $family,
            ),
        ];
    }
    return ['success' => true, 'font' => $fonts[0]];
}
