<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: install a Google Font family into the WordPress Font Library so
 * it becomes available as a Spectra One typography preset. Wrapper on top
 * of the theme-native `spectra-one/install-font` — the theme already owns
 * the Google Fonts CSS2 fetch + WOFF2 magic-byte verification + host
 * allowlist + Font Library registration, so this ability validates the
 * input and delegates.
 */

wp_register_ability('novamira/spectra-one-add-font-family', [
    'label' => __('Add Spectra One Font Family', domain: 'novamira-pro'),
    'description' => __(
        'Downloads a Google Font family and registers it with the WordPress Font Library so blocks and the Spectra One typography settings can reference it. Provide the font family `name` (exactly as it appears on fonts.google.com — case sensitive, e.g. "Playfair Display", "DM Sans", "Space Grotesk") and the numeric `weights` to install (defaults to [400, 700] when omitted). Idempotent: a subsequent call for the same font family reports the already-installed slug without redownloading. Delegates to the theme-native `spectra-one/install-font` ability so upstream SSRF / WOFF2 verification / host allowlist logic stays in one place.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 60,
                'description' => 'Google Fonts family name (case sensitive).',
            ],
            'weights' => [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 9,
                'items' => [
                    'type' => 'integer',
                    'enum' => [100, 200, 300, 400, 500, 600, 700, 800, 900],
                ],
                'description' => 'Numeric weights to install (100..900 in 100 increments). Defaults to [400, 700] on the theme side when omitted.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'Font family slug the Font Library registered — pass it back to novamira/spectra-one-set-typography via `body.font_family` or `headings.font_family`.',
            ],
            'name' => ['type' => 'string'],
            'weights' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'already_installed' => [
                'type' => 'boolean',
                'description' => 'True when the ability skipped the download because the family + weights combination was already registered.',
            ],
        ],
        'required' => ['slug', 'name', 'weights', 'already_installed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_add_font_family',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Installs the font first, then use novamira/spectra-one-set-typography to actually apply it to body / headings. If the font is already installed the call returns the existing slug with already_installed: true — safe to call from a workflow that idempotently prepares typography before writing.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{name: string, weights?: list<int>} $input
 * @return array{slug: string, name: string, weights: list<int>, already_installed: bool}|WP_Error
 */
function spectra_one_add_font_family(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $name = trim($input['name']);
    if ($name === '' || preg_match('/^[A-Za-z0-9 \\-_\'\\.]+$/', $name) !== 1) {
        return so_invalid_input(sprintf(
            /* translators: %s: rejected font-family name */
            __('Invalid Google Fonts family name: %s', domain: 'novamira-pro'),
            $name,
        ));
    }

    $native = wp_get_ability('spectra-one/install-font');
    if ($native === null) {
        return new WP_Error(
            'spectra_one_native_install_font_unavailable',
            __(
                'The theme-native spectra-one/install-font ability is not available on this install. Ensure Spectra One 1.2.x is active and WordPress 6.9+ is running.',
                domain: 'novamira-pro',
            ),
            ['status' => 501],
        );
    }

    /** @var mixed $result */
    $result = $native->execute([
        'name' => $name,
        'weights' => $input['weights'] ?? [400, 700],
    ]);
    if ($result instanceof WP_Error) {
        return $result;
    }
    if (!is_array($result)) {
        return new WP_Error(
            'spectra_one_native_install_font_unexpected',
            __('The theme-native install-font ability returned an unexpected shape.', domain: 'novamira-pro'),
            ['status' => 500],
        );
    }

    /** @var mixed $payload */
    $payload = $result['data'] ?? $result;
    if (!is_array($payload)) {
        $payload = [];
    }

    return [
        'slug' => so_string_at($payload, ['slug'], fallback: sanitize_title($name)),
        'name' => so_string_at($payload, ['name'], fallback: $name),
        'weights' => so_extract_int_list($payload, ['weights'], fallback: $input['weights'] ?? [400, 700]),
        'already_installed' => so_bool_at($payload, ['already_installed']),
    ];
}
