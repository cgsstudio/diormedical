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
 * Ability: switch the active header / footer design by pointing the FSE
 * template part at a different bundled pattern. Delegates to the theme-
 * native `spectra-one/update-template-part` which owns the pattern-
 * validation + template-part-CPT persistence + site-title fallback logic.
 *
 * Partial / merge update: the template part row exists already and we
 * change which pattern it renders, not the row itself.
 */

wp_register_ability('novamira/spectra-one-edit-template-part', [
    'label' => __('Edit Spectra One Template Part', domain: 'novamira-pro'),
    'description' => __(
        'Switches the active header or footer design by activating a different Spectra One pattern on the corresponding FSE template part. Discover the available pattern slugs with `novamira/spectra-one-list-patterns` filtered by category `spectra-one-headers` or `spectra-one-footers`. Optional `use_site_title` swaps the logo image for a wp:site-title block (useful when the install has no logo asset). Delegates to the theme-native `spectra-one/update-template-part`.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'area' => [
                'type' => 'string',
                'enum' => ['header', 'footer'],
                'description' => 'FSE template-part area to update. `sidebar` is currently NOT accepted by the theme-native ability.',
            ],
            'pattern' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 120,
                'description' => 'Pattern slug. Must be prefixed `spectra-one/header` for area=header or `spectra-one/footer` for area=footer.',
            ],
            'use_site_title' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Replace the logo image block with a wp:site-title block.',
            ],
        ],
        'required' => ['area', 'pattern'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'area' => ['type' => 'string', 'enum' => ['header', 'footer']],
            'pattern' => ['type' => 'string', 'maxLength' => 120],
            'template_part_id' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'The wp_template_part post id that now hosts the pattern. 0 when the theme-native ability persisted through a route that does not surface an id.',
            ],
        ],
        'required' => ['area', 'pattern', 'template_part_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_edit_template_part',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Preview the available patterns with novamira/spectra-one-list-patterns before calling — the theme-native ability rejects any slug outside the spectra-one/header* or spectra-one/footer* families.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{area: string, pattern: string, use_site_title?: bool} $input
 * @return array{area: string, pattern: string, template_part_id: int}|WP_Error
 */
function spectra_one_edit_template_part(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }

    $native = wp_get_ability('spectra-one/update-template-part');
    if ($native === null) {
        return new WP_Error(
            'spectra_one_native_update_template_part_unavailable',
            __(
                'The theme-native spectra-one/update-template-part ability is not available on this install. Ensure Spectra One 1.2.x is active and WordPress 6.9+ is running.',
                domain: 'novamira-pro',
            ),
            ['status' => 501],
        );
    }

    // The theme-native update-template-part ability does NOT lock on its
    // own; two concurrent writers on the same area can corrupt the
    // wp_template_part CPT row (audit round 4, Concurrency lens). Wrap the
    // delegation in a per-area lock slot so parallel writers serialise.
    return so_with_lock('template_part_'
    . $input['area'], static fn(): array|WP_Error => so_dispatch_edit_template_part($native, $input));
}

/**
 * Delegate to the theme-native `spectra-one/update-template-part` under a
 * lock and normalise the return shape to the Novamira output contract.
 *
 * @param \WP_Ability $native
 * @param array{area: string, pattern: string, use_site_title?: bool} $input
 * @return array{area: string, pattern: string, template_part_id: int}|WP_Error
 */
function so_dispatch_edit_template_part(\WP_Ability $native, array $input): array|WP_Error
{
    /** @var mixed $result */
    $result = $native->execute([
        'area' => $input['area'],
        'pattern' => $input['pattern'],
        'use_site_title' => $input['use_site_title'] ?? false,
    ]);
    if ($result instanceof WP_Error) {
        return $result;
    }
    if (!is_array($result)) {
        return new WP_Error(
            'spectra_one_native_update_template_part_unexpected',
            __('The theme-native update-template-part ability returned an unexpected shape.', domain: 'novamira-pro'),
            ['status' => 500],
        );
    }

    /** @var mixed $payload */
    $payload = $result['data'] ?? $result;
    if (!is_array($payload)) {
        $payload = [];
    }

    return [
        'area' => $input['area'],
        'pattern' => $input['pattern'],
        'template_part_id' => so_int_at($payload, ['template_part_id']),
    ];
}
