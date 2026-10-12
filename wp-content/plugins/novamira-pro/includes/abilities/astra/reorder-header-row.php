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
 * Ability: move a single element between cells within the same header row.
 */

wp_register_ability('novamira/astra-reorder-header-row', [
    'label' => __('Reorder Astra Header Row', domain: 'novamira-pro'),
    'description' => __(
        'Moves a single element slug from one cell to another within the same header row (above, primary, or below). Cheaper than a full `astra-set-header-layout` call when only one element needs to move. The move is atomic under a MySQL advisory lock. If the element is not found in `from_cell` (already moved or never there), `moved=false` is returned and no write occurs — making the operation idempotent. Use `to_index` to control insertion position (0 = first, -1 or omit = append).',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'variant' => [
                'type' => 'string',
                'enum' => ['desktop', 'mobile'],
                'description' => 'Target grid: desktop (header-desktop-items) or mobile (header-mobile-items). Defaults to desktop.',
                'default' => 'desktop',
            ],
            'row' => [
                'type' => 'string',
                'enum' => ['above', 'primary', 'below'],
                'description' => 'The header row that contains both from_cell and to_cell.',
            ],
            'element' => [
                'type' => 'string',
                'description' => 'Element slug to move (must be in astra-get-header-layout available_elements).',
            ],
            'from_cell' => [
                'type' => 'string',
                'description' => 'Cell key where the element currently lives. Must be a valid cell for the given row and variant.',
            ],
            'to_cell' => [
                'type' => 'string',
                'description' => 'Destination cell key. Must be a valid cell for the given row and variant. May equal from_cell to change position within the same cell.',
            ],
            'to_index' => [
                'type' => 'integer',
                'description' => 'Insertion position in to_cell (0-based). -1 or any negative value appends to the end. Defaults to -1.',
                'default' => -1,
            ],
        ],
        'required' => ['row', 'element', 'from_cell', 'to_cell'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'moved' => [
                'type' => 'boolean',
                'description' => 'True when the element was found in from_cell and successfully moved to to_cell.',
            ],
            'was_at_index' => [
                'type' => ['integer', 'null'],
                'description' => 'The position the element occupied in from_cell before the move. Null when moved=false.',
            ],
            'now_at_index' => [
                'type' => ['integer', 'null'],
                'description' => 'The position the element now occupies in to_cell. Null when moved=false.',
            ],
            'row_after' => [
                'type' => 'object',
                'description' => 'The complete row structure (all cells in the row) after the move.',
                'additionalProperties' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'lock_supported' => [
                'type' => 'boolean',
                'description' => 'True when the database supports MySQL GET_LOCK (advisory) locks; the write was serialised under one on this call. False on databases that do not (e.g. some hosted MySQL variants) — the write still completed but without cross-request serialisation.',
            ],
            'reason' => [
                'type' => 'string',
                'description' => 'Human-readable explanation when moved=false due to the element already being at the target position.',
            ],
        ],
        'required' => ['moved', 'was_at_index', 'now_at_index', 'row_after', 'lock_supported'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_reorder_header_row',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Moves one element slug between cells in a header row under a MySQL advisory lock. Idempotent: calling again after a successful move returns moved=false because the element is no longer in from_cell. Check row_after to verify the final cell arrangement. For bulk layout changes, use astra-set-header-layout instead.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Extract and coerce raw input fields for the reorder operation.
 *
 * @param array<array-key, mixed> $input
 * @return array{variant: string, row: string, element: string, from_cell: string, to_cell: string, to_index: int}
 */
function ast_rhr_extract_fields(array $input): array
{
    /** @var mixed $variant_raw */
    $variant_raw = $input['variant'] ?? null;
    /** @var mixed $row_raw */
    $row_raw = $input['row'] ?? null;
    /** @var mixed $element_raw */
    $element_raw = $input['element'] ?? null;
    /** @var mixed $from_raw */
    $from_raw = $input['from_cell'] ?? null;
    /** @var mixed $to_raw */
    $to_raw = $input['to_cell'] ?? null;

    return [
        'variant' => is_string($variant_raw) ? $variant_raw : 'desktop',
        'row' => is_string($row_raw) ? $row_raw : '',
        'element' => is_string($element_raw) ? $element_raw : '',
        'from_cell' => is_string($from_raw) ? $from_raw : '',
        'to_cell' => is_string($to_raw) ? $to_raw : '',
        'to_index' => isset($input['to_index']) ? astra_coerce_int($input['to_index']) : -1,
    ];
}

/**
 * Validate that the variant value is a supported grid variant.
 */
function ast_rhr_check_variant(string $variant): ?WP_Error
{
    if ($variant !== 'desktop' && $variant !== 'mobile') {
        return astra_invalid_input_error('variant must be "desktop" or "mobile".');
    }
    return null;
}

/**
 * Validate that the element slug is a known header builder element.
 */
function ast_rhr_check_element(string $element): ?WP_Error
{
    if ($element === '' || !in_array($element, astra_hfb_element_slugs(), strict: true)) {
        return astra_invalid_input_error(sprintf(
            'element "%s" is not a valid header builder element slug. Use astra-get-header-layout to list valid slugs.',
            $element,
        ));
    }
    return null;
}

/**
 * Validate that both from_cell and to_cell belong to the given row and variant.
 */
function ast_rhr_validate_cells(string $row, string $variant, string $from_cell, string $to_cell): ?WP_Error
{
    $row_cells = astra_hfb_header_row_cells($row, $variant);
    if ($row_cells === []) {
        return astra_invalid_input_error(sprintf(
            'No valid cells found for row "%s" and variant "%s".',
            $row,
            $variant,
        ));
    }
    if (!in_array($from_cell, $row_cells, strict: true)) {
        return astra_invalid_input_error(sprintf(
            'from_cell "%s" is not valid for row "%s" / variant "%s". Valid: %s.',
            $from_cell,
            $row,
            $variant,
            implode(', ', $row_cells),
        ));
    }
    if (!in_array($to_cell, $row_cells, strict: true)) {
        return astra_invalid_input_error(sprintf(
            'to_cell "%s" is not valid for row "%s" / variant "%s". Valid: %s.',
            $to_cell,
            $row,
            $variant,
            implode(', ', $row_cells),
        ));
    }
    return null;
}

/**
 * Validate and normalise all scalar inputs for the reorder operation.
 *
 * Returns an array with the validated and normalised values on success, or a
 * WP_Error when any validation rule fails.
 *
 * @param array<array-key, mixed> $input
 * @return array{variant: string, row: string, element: string, from_cell: string, to_cell: string, to_index: int}|WP_Error
 */
function ast_rhr_validate_inputs(array $input): array|WP_Error
{
    $fields = ast_rhr_extract_fields($input);

    $variant_error = ast_rhr_check_variant($fields['variant']);
    if ($variant_error !== null) {
        return $variant_error;
    }

    if (!in_array($fields['row'], ['above', 'primary', 'below'], strict: true)) {
        return astra_invalid_input_error('row must be one of: above, primary, below.');
    }

    $element_error = ast_rhr_check_element($fields['element']);
    if ($element_error !== null) {
        return $element_error;
    }

    $cell_error = ast_rhr_validate_cells($fields['row'], $fields['variant'], $fields['from_cell'], $fields['to_cell']);
    if ($cell_error !== null) {
        return $cell_error;
    }

    return $fields;
}

/**
 * Remove an element from a source cell. Returns false when the element is not found.
 *
 * @param array<string, mixed> $grid
 * @return array{updated_grid: array<string, mixed>, was_at: int}|false
 */
function ast_rhr_remove_from_source(array $grid, string $row, string $cell, string $element): array|false
{
    $raw = isset($grid[$row][$cell]) && is_array($grid[$row][$cell]) ? $grid[$row][$cell] : [];

    /** @var list<string> $contents */
    $contents = array_values($raw);
    $pos = array_search($element, $contents, strict: true);
    if ($pos === false) {
        return false;
    }
    array_splice($contents, offset: (int) $pos, length: 1);
    if (!is_array($grid[$row])) {
        $grid[$row] = [];
    }
    $grid[$row][$cell] = $contents;
    return ['updated_grid' => $grid, 'was_at' => (int) $pos];
}

/**
 * Insert an element into a target cell at the requested index.
 *
 * @param array<string, mixed> $grid
 * @return array{updated_grid: array<string, mixed>, actual_index: int}
 */
function ast_rhr_insert_into_target(array $grid, string $row, string $cell, string $element, int $index): array
{
    $raw = isset($grid[$row][$cell]) && is_array($grid[$row][$cell]) ? $grid[$row][$cell] : [];

    /** @var list<string> $contents */
    $contents = array_values($raw);
    $len = count($contents);
    $actual = $index < 0 || $index > $len ? $len : $index;
    array_splice($contents, offset: $actual, length: 0, replacement: [$element]);
    if (!is_array($grid[$row])) {
        $grid[$row] = [];
    }
    $grid[$row][$cell] = $contents;
    return ['updated_grid' => $grid, 'actual_index' => $actual];
}

/**
 * Guard: return a no-op result when the element is already at the target position
 * (same cell, same index). Returns null when the guard does not apply so that
 * the caller can proceed with the normal move path.
 *
 * Extracted to keep `ast_rhr_apply_move` below the Halstead-effort threshold.
 *
 * @param array<string, mixed> $grid
 * @param array{variant: string, row: string, element: string, from_cell: string, to_cell: string, to_index: int} $params
 * @return array{moved: bool, was_at_index: int, now_at_index: int, row_after: array<string, list<string>>, reason: string}|null
 */
function ast_rhr_guard_same_position(array $grid, array $params): ?array
{
    if ($params['from_cell'] !== $params['to_cell']) {
        return null;
    }
    $cell_raw = isset($grid[$params['row']][$params['from_cell']])
    && is_array($grid[$params['row']][$params['from_cell']])
        ? $grid[$params['row']][$params['from_cell']]
        : [];
    $current_idx = array_search($params['element'], array_values($cell_raw), strict: true);
    if ($current_idx === false || (int) $current_idx !== $params['to_index']) {
        return null;
    }
    /** @var array<string, list<string>> $current_row */
    $current_row = isset($grid[$params['row']]) && is_array($grid[$params['row']]) ? $grid[$params['row']] : [];
    return [
        'moved' => false,
        'was_at_index' => (int) $current_idx,
        'now_at_index' => (int) $current_idx,
        'row_after' => $current_row,
        'reason' => 'element already at target position',
    ];
}

/**
 * Apply the element move inside the astra-settings option bag. Must be called
 * within an `astra_with_settings_lock` closure.
 *
 * @param array{variant: string, row: string, element: string, from_cell: string, to_cell: string, to_index: int} $params
 * @return array{moved: bool, was_at_index: int|null, now_at_index: int|null, row_after: array<string, list<string>>, reason?: string}
 */
function ast_rhr_apply_move(string $option_key, array $params): array
{
    /** @var mixed $raw */
    $raw = get_option('astra-settings', []);
    $bag = is_array($raw) ? $raw : [];

    /** @var mixed $grid_raw */
    $grid_raw = $bag[$option_key] ?? [];
    /** @var array<string, mixed> $grid */
    $grid = is_array($grid_raw) ? $grid_raw : [];

    $guard = ast_rhr_guard_same_position($grid, $params);
    if ($guard !== null) {
        return $guard;
    }

    $removed = ast_rhr_remove_from_source($grid, $params['row'], $params['from_cell'], $params['element']);
    if ($removed === false) {
        /** @var array<string, list<string>> $current_row */
        $current_row = isset($grid[$params['row']]) && is_array($grid[$params['row']]) ? $grid[$params['row']] : [];
        return ['moved' => false, 'was_at_index' => null, 'now_at_index' => null, 'row_after' => $current_row];
    }

    $inserted = ast_rhr_insert_into_target(
        $removed['updated_grid'],
        $params['row'],
        $params['to_cell'],
        $params['element'],
        $params['to_index'],
    );
    $bag[$option_key] = $inserted['updated_grid'];
    update_option('astra-settings', $bag);

    /** @var array<string, list<string>> $row_after */
    $row_after = $inserted['updated_grid'][$params['row']];

    return [
        'moved' => true,
        'was_at_index' => $removed['was_at'],
        'now_at_index' => $inserted['actual_index'],
        'row_after' => $row_after,
    ];
}

/**
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_reorder_header_row(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    $validated = ast_rhr_validate_inputs($input);
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    $option_key = 'header-' . $validated['variant'] . '-items';
    $lock_supported = astra_settings_lock_supported();

    $result = astra_with_settings_lock(static fn() => ast_rhr_apply_move($option_key, $validated));

    if ($result['moved']) {
        astra_invalidate_dynamic_css_cache();
    }

    $out = [
        'moved' => $result['moved'],
        'was_at_index' => $result['was_at_index'],
        'now_at_index' => $result['now_at_index'],
        'row_after' => $result['row_after'],
        'lock_supported' => $lock_supported,
    ];
    if (isset($result['reason'])) {
        $out['reason'] = $result['reason'];
    }
    return $out;
}
