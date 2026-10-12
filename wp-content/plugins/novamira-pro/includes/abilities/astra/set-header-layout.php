<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: replace the Astra desktop and/or mobile header builder grid (PUT).
 */

wp_register_ability('novamira/astra-set-header-layout', [
    'label' => __('Set Astra Header Layout', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the Astra header builder desktop grid (`header-desktop-items`) and/or mobile grid (`header-mobile-items`) under a MySQL advisory lock. At least one of `desktop` or `mobile` must be provided. Unknown cell keys and element slugs are dropped and reported — use `strict=true` to fail-fast instead. Call `astra-get-header-layout` first to read the current layout and obtain the valid cell keys and element slugs.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'desktop' => [
                'type' => 'object',
                'description' => 'Full replacement for header-desktop-items. Structure: row keys (above, primary, below, popup) → cell keys → ordered list of element slugs. Obtain valid cell keys and slugs from astra-get-header-layout.',
                'additionalProperties' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
            'mobile' => [
                'type' => 'object',
                'description' => 'Full replacement for header-mobile-items. Same structure as desktop but with 3 columns per row instead of 5.',
                'additionalProperties' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
            'strict' => [
                'type' => 'boolean',
                'description' => 'When true, any unknown cell key or element slug returns a 400 error instead of being silently dropped. Defaults to false.',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'desktop_written' => ['type' => 'boolean'],
            'mobile_written' => ['type' => 'boolean'],
            'unknown_cells' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Cell keys in the payload not found in the allowlist — dropped (or 400 when strict=true).',
            ],
            'unknown_elements' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Element slugs not found in the allowlist — dropped (or 400 when strict=true).',
            ],
            'lock_supported' => [
                'type' => 'boolean',
                'description' => 'True when the database supports MySQL GET_LOCK (advisory) locks; the write was serialised under one on this call. False on databases that do not (e.g. some hosted MySQL variants) — the write still completed but without cross-request serialisation.',
            ],
        ],
        'required' => ['desktop_written', 'mobile_written', 'unknown_cells', 'unknown_elements', 'lock_supported'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_set_header_layout',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PUT-replaces the header builder grid under a MySQL advisory lock. Read the current layout with astra-get-header-layout first, modify the structure, then POST the full replacement. For moving a single element between cells, prefer the cheaper astra-reorder-header-row ability instead. Unknown cell keys and element slugs are silently dropped unless strict=true.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Coerce a raw input value to array and validate it as an HFB grid.
 * Returns a WP_Error when the value is not an object/map.
 *
 * @param mixed  $raw     Raw ability input field value.
 * @param string $variant Grid variant passed to astra_validate_hfb_grid.
 * @return array{clean: array<string, array<string, list<string>>>, unknown_cells: list<string>, unknown_elements: list<string>}|WP_Error
 */
function ast_hl_validate_grid(mixed $raw, string $variant): array|WP_Error
{
    $coerced = astra_coerce_array($raw);
    if ($coerced === null) {
        return astra_invalid_input_error(sprintf(
            'The %s grid must be an object mapping row keys to cell objects.',
            $variant,
        ));
    }
    return astra_validate_hfb_grid($coerced, $variant);
}

/**
 * Validate desktop and/or mobile header grid inputs together.
 * Pass null for a variant that was not provided by the caller.
 *
 * @param mixed $desktop_raw Raw desktop grid value, or null when not provided.
 * @param mixed $mobile_raw  Raw mobile grid value, or null when not provided.
 * @return array{clean_desktop: array<string, array<string, list<string>>>|null, clean_mobile: array<string, array<string, list<string>>>|null, unknown_cells: list<string>, unknown_elements: list<string>}|WP_Error
 */
function ast_hl_validate_grids(mixed $desktop_raw, mixed $mobile_raw): array|WP_Error
{
    $clean_desktop = null;
    $du_cells = [];
    $du_elems = [];

    if ($desktop_raw !== null) {
        $v = ast_hl_validate_grid($desktop_raw, variant: 'desktop');
        if ($v instanceof WP_Error) {
            return $v;
        }
        ['clean' => $clean_desktop, 'unknown_cells' => $du_cells, 'unknown_elements' => $du_elems] = $v;
    }

    $clean_mobile = null;
    $mu_cells = [];
    $mu_elems = [];

    if ($mobile_raw !== null) {
        $v = ast_hl_validate_grid($mobile_raw, variant: 'mobile');
        if ($v instanceof WP_Error) {
            return $v;
        }
        ['clean' => $clean_mobile, 'unknown_cells' => $mu_cells, 'unknown_elements' => $mu_elems] = $v;
    }

    // Size guard: prevent a multi-MB serialized blob from reaching the options table.
    $total_size = strlen(serialize($clean_desktop ?? [])) + strlen(serialize($clean_mobile ?? []));
    if ($total_size > ASTRA_MAX_HFB_GRID_BYTES) {
        return astra_invalid_input_error(sprintf(
            'HFB grid exceeds %d bytes limit (%d bytes serialized). Reduce the number of elements per cell.',
            ASTRA_MAX_HFB_GRID_BYTES,
            $total_size,
        ));
    }

    return [
        'clean_desktop' => $clean_desktop,
        'clean_mobile' => $clean_mobile,
        'unknown_cells' => array_values(array_unique(array_merge($du_cells, $mu_cells))),
        'unknown_elements' => array_values(array_unique(array_merge($du_elems, $mu_elems))),
    ];
}

/**
 * Write validated desktop and/or mobile header grids into the astra-settings option bag.
 * A null value means the caller did not provide that variant — skip writing it.
 * Must be called inside an astra_with_settings_lock closure.
 *
 * @param array<string, array<string, list<string>>>|null $clean_desktop
 * @param array<string, array<string, list<string>>>|null $clean_mobile
 */
function ast_hl_write_grids(?array $clean_desktop, ?array $clean_mobile): void
{
    /** @var mixed $raw */
    $raw = get_option('astra-settings', []);
    $bag = is_array($raw) ? $raw : [];
    if ($clean_desktop !== null) {
        $bag['header-desktop-items'] = $clean_desktop;
    }
    if ($clean_mobile !== null) {
        $bag['header-mobile-items'] = $clean_mobile;
    }
    update_option('astra-settings', $bag);
}

/**
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_set_header_layout(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    $desktop_given = array_key_exists('desktop', $input);
    $mobile_given = array_key_exists('mobile', $input);

    if (!$desktop_given && !$mobile_given) {
        return astra_invalid_input_error('At least one of desktop or mobile must be provided.');
    }

    $validated = ast_hl_validate_grids(
        $desktop_given ? $input['desktop'] ?? [] : null,
        $mobile_given ? $input['mobile'] ?? [] : null,
    );
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    $strict = astra_coerce_bool($input['strict'] ?? false);
    $all_unknown_cells = $validated['unknown_cells'];
    $all_unknown_elements = $validated['unknown_elements'];

    if ($strict && ($all_unknown_cells !== [] || $all_unknown_elements !== [])) {
        return astra_invalid_input_error(sprintf(
            'strict=true: %d unknown cell key(s), %d unknown element slug(s). Use astra-get-header-layout to retrieve valid keys.',
            count($all_unknown_cells),
            count($all_unknown_elements),
        ));
    }

    $lock_supported = astra_settings_lock_supported();

    astra_with_settings_lock(static fn() => ast_hl_write_grids(
        $validated['clean_desktop'],
        $validated['clean_mobile'],
    ));

    astra_invalidate_dynamic_css_cache();

    return [
        'desktop_written' => $desktop_given,
        'mobile_written' => $mobile_given,
        'unknown_cells' => $all_unknown_cells,
        'unknown_elements' => $all_unknown_elements,
        'lock_supported' => $lock_supported,
    ];
}
