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
 * Ability: edit the Spectra One primary navigation menu.
 *
 * Wraps the theme-native `spectra-one/update-navigation` — the theme already
 * owns the wp_navigation post creation, wp:navigation-link block
 * serialisation, submenu handling, and the tricky header/footer template-
 * part rewiring that the FSE navigation model requires. This wrapper
 * does a partial / merge update with stable WP_Error codes and delegates
 * the heavy lifting.
 */

wp_register_ability('novamira/spectra-one-edit-navigation', [
    'label' => __('Edit Spectra One Navigation', domain: 'novamira-pro'),
    'description' => __(
        'Sets or appends items to the Spectra One primary navigation menu. Accepts `items` — a list of `{ label, url, id?, children? }` entries; top-level items with a non-empty `children` array render as hierarchical dropdowns (wp:navigation-submenu). `append: true` merges with the existing menu, deduplicating by id / url and unioning children by url; `append: false` (default) replaces the menu wholesale. Wires the header + footer template parts to reference the resulting wp_navigation post — so a single call sets the menu everywhere it renders. Delegates to the theme-native `spectra-one/update-navigation`.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'minItems' => 1,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
                        'url' => ['type' => 'string', 'maxLength' => 2048],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'children' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
                                    'url' => ['type' => 'string', 'maxLength' => 2048],
                                ],
                                'required' => ['label', 'url'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['label'],
                    'additionalProperties' => false,
                ],
            ],
            'append' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When true, merge with the existing menu (dedup by id / url). Default false — wholesale replace.',
            ],
        ],
        'required' => ['items'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'navigation_id' => ['type' => 'integer', 'minimum' => 1],
            'item_count' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Number of top-level items the menu now carries.',
            ],
            'header_wired' => ['type' => 'boolean'],
            'footer_wired' => ['type' => 'boolean'],
        ],
        'required' => ['navigation_id', 'item_count', 'header_wired', 'footer_wired'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_edit_navigation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'For a full replace pass append: false (default). For incremental additions pass append: true — the merge respects existing dropdowns (children are unioned by url).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{items: list<array<string, mixed>>, append?: bool} $input
 * @return array{
 *     navigation_id: int,
 *     item_count: int,
 *     header_wired: bool,
 *     footer_wired: bool,
 * }|WP_Error
 */
function spectra_one_edit_navigation(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $native = wp_get_ability('spectra-one/update-navigation');
    if ($native === null) {
        return new WP_Error(
            'spectra_one_native_update_navigation_unavailable',
            __(
                'The theme-native spectra-one/update-navigation ability is not available on this install. Ensure Spectra One 1.2.x is active and WordPress 6.9+ is running.',
                domain: 'novamira-pro',
            ),
            ['status' => 501],
        );
    }

    // The theme-native update-navigation ability does NOT lock on its own;
    // two concurrent invocations can interleave writes on the wp_navigation
    // post + the header / footer template parts (audit round 4, Concurrency
    // lens). Wrap the delegation in a shared lock slot so parallel writers
    // serialise.
    return so_with_lock('wp_navigation', static fn(): array|WP_Error => so_dispatch_edit_navigation($native, $input));
}

/**
 * Delegate to the theme-native `spectra-one/update-navigation` under a
 * lock and normalise the return shape to the Novamira output contract.
 *
 * @param \WP_Ability $native
 * @param array{items: list<array<string, mixed>>, append?: bool} $input
 * @return array{
 *     navigation_id: int,
 *     item_count: int,
 *     header_wired: bool,
 *     footer_wired: bool,
 * }|WP_Error
 */
function so_dispatch_edit_navigation(\WP_Ability $native, array $input): array|WP_Error
{
    /** @var mixed $result */
    $result = $native->execute([
        'items' => $input['items'],
        'append' => $input['append'] ?? false,
    ]);
    if ($result instanceof WP_Error) {
        return $result;
    }
    if (!is_array($result)) {
        return new WP_Error(
            'spectra_one_native_update_navigation_unexpected',
            __('The theme-native update-navigation ability returned an unexpected shape.', domain: 'novamira-pro'),
            ['status' => 500],
        );
    }

    /** @var mixed $payload */
    $payload = $result['data'] ?? $result;
    if (!is_array($payload)) {
        $payload = [];
    }

    return [
        'navigation_id' => so_int_at($payload, ['navigation_id']),
        'item_count' => so_int_at($payload, ['item_count']),
        'header_wired' => so_bool_at($payload, ['header_wired']),
        'footer_wired' => so_bool_at($payload, ['footer_wired']),
    ];
}
