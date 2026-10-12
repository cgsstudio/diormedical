<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: edit one Blocksy customizer option (theme_mod) by id.
 */

wp_register_ability('novamira/blocksy-edit-option', [
    'label' => __('Edit Blocksy Option', domain: 'novamira-pro'),
    'description' => __(
        'Sets a single Blocksy customizer option by id, in the storage the option declares (reported as `storage`): almost every option is a theme_mod — the value replaces that option\'s value only, every sibling theme_mod in the shared theme_mods row is preserved (merge-write under a cross-request lock); the few options Blocksy declares with setting.type=option (on 2.1.52 the WooCommerce settings re-exposed in its customizer panels — checkout field toggles, privacy-policy/terms page pickers, demo-store notice, catalog columns/rows, thumbnail cropping, catalog ordering, shop/category display, single image width — plus wp_page_for_privacy_policy) are written to their OWN wp_options row, exactly where the customizer saves them and WooCommerce/core read them — through a hook-for-hook replica of the customizer\'s own update_option($id, $value, true) pipeline (sanitize_option incl. sanitize_option_<id>, pre_update_option filters, core\'s same-value no-op, the pre-SQL update_option/add_option action, autoload set to what a customizer save stores, then update_option_<id>/updated_option or add_option_<id>/added_option after a verified persist); for those, `value`/`previous_value` come back in stored form (scalars as strings, the same form blocksy-get-option returns). The id must exist in the theme\'s option schema (discover ids with blocksy-list-options; unknown ids are rejected). Values are validated against the option schema where determinable: ct-switch accepts yes/no or booleans (coerced to the vocabulary the option declares), finite choice sets are enforced, numeric slider/number values must sit inside the option\'s declared min/max, unit-bearing slider strings must be a number plus one of the option\'s declared units (e.g. "20px"), and the value shape must match the schema default (structured vs scalar; responsive {desktop, tablet, mobile} maps allowed on responsive options). ct-typography values (rootTypography, h1Typography…h6Typography, buttons, …) are objects {family, variation, size, line-height, letter-spacing, text-transform, text-decoration}: a partial object is completed from the option\'s current effective value (keys you pass win), so {"family": "Lora"} changes only the family — the theme needs every key present to render the font. After a successful write, Blocksy\'s theme-mod cache and both dynamic-CSS caches (transient + CSS file) are refreshed automatically, exactly like a customizer save. Use blocksy-edit-color-palette for the colorPalette option — it merges per slot.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Option id as listed by blocksy-list-options (the theme_mod name). Must exist in the option schema.',
            ],
            'value' => [
                'type' => ['string', 'number', 'integer', 'boolean', 'object', 'array'],
                'description' => 'New value for the option. Must match the option\'s value shape — read blocksy-get-option first when unsure (its `value`/`default` show the expected shape, `choices` the valid values).',
            ],
        ],
        'required' => ['id', 'value'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'value' => [
                'type' => ['string', 'number', 'integer', 'boolean', 'object', 'array', 'null'],
                'description' => 'The value that was written (after coercion, e.g. boolean → yes/no on ct-switch). For storage=option ids: the persisted value re-read from its wp_options row (scalars in stored string form).',
            ],
            'previous_value' => [
                'type' => ['string', 'number', 'integer', 'boolean', 'object', 'array', 'null'],
                'description' => 'Effective value before the write: the stored value (theme_mod, or the wp_options row for storage=option) when previously_stored=true, else the schema default. Oversized values (>16 KB JSON) are replaced with a marker string.',
            ],
            'previously_stored' => [
                'type' => 'boolean',
                'description' => 'False when the option had no stored value before this write (no theme_mod key / no wp_options row — the site ran on the schema default).',
            ],
            'storage' => [
                'type' => 'string',
                'enum' => ['theme_mod', 'option'],
                'description' => 'Where the value was written: `theme_mod` (the theme_mods row, the default) or `option` (the id\'s own wp_options row — declared setting.type=option, the storage the customizer and the consumers use).',
            ],
        ],
        'required' => ['id', 'value', 'previous_value', 'previously_stored', 'storage'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_edit_option',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the option with blocksy-get-option first to learn its value shape and choices. The write is validated and atomic: on any error nothing is persisted. The dynamic-CSS refresh runs automatically — no extra cache step is needed for the change to reach the front end.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id?: string, value?: mixed} $input
 * @return array{
 *     id: string,
 *     value: mixed,
 *     previous_value: mixed,
 *     previously_stored: bool,
 *     storage: string,
 * }|WP_Error
 */
function blocksy_edit_option(array $input): array|WP_Error
{
    // Pin the storage identity as the VERY FIRST statements — even the
    // availability gate does a filtered theme lookup (get_template()), and
    // the option-tree sync preload and Blocksy's own sanitizer load theme
    // code later; none of them may precede or redirect the pin.
    $stylesheet = get_stylesheet();
    $template = get_template();
    $mods_key = 'theme_mods_' . $stylesheet;

    if (!blocksy_theme_available($template)) {
        return bly_theme_inactive_error();
    }

    $id = isset($input['id']) ? trim($input['id']) : '';
    if ($id === '') {
        return bly_error('bly_invalid_input', message: '`id` is required and must be a non-empty string.');
    }
    if (!array_key_exists('value', $input)) {
        return bly_error('bly_invalid_input', message: '`value` is required.');
    }

    $index = bly_option_index_or_error();
    if ($index instanceof WP_Error) {
        return $index;
    }

    $row = eo_target_row($id, $index);
    if ($row instanceof WP_Error) {
        return $row;
    }
    /** @var mixed $default */
    $default = $row['option']['value'] ?? null;
    $storage = bly_option_storage($row);
    $prepared = eo_prevalidate($row, $input['value']);
    if ($prepared instanceof WP_Error) {
        return $prepared;
    }
    /** @var mixed $value */
    $value = $prepared['value'];
    $complete_in_lock = $prepared['complete_in_lock'];

    // Dynamic choice vocabularies (nav menus, pages, terms) are
    // re-resolved and re-checked against the FINAL value — after the
    // pre_set_theme_mod / pre_update_option filter window, immediately
    // before the persist tail; static ones return immediately. See
    // bly_recheck_dynamic_choice.
    $recheck = bly_option_recheck($row);

    // Honor the declared storage (bly_option_storage): a setting.type=option
    // id lives in its OWN wp_options row — writing it into theme_mods would
    // report success while the row WooCommerce/core read stays unchanged.
    if ($storage === BLY_STORAGE_OPTION) {
        // A typography row stored in its own wp_options row completes
        // INSIDE that row's lock too (bly_locked_option_write's compute),
        // from the fresh raw row — same discipline as the mods path.
        $compute = $complete_in_lock
            ? static fn(mixed $current): mixed => eo_complete_in_lock($row, $value, $current)
            : null;
        return eo_write_option_storage($id, $value, $default, $stylesheet, [
            'recheck' => $recheck,
            'compute' => $compute,
        ]);
    }

    $previously_stored = false;
    /** @var mixed $previous_value */
    $previous_value = null;

    /** @var mixed $written */
    $written = bly_locked_mod_write(
        $id,
        static function (array $mods) use (
            $id,
            $row,
            $value,
            $default,
            $complete_in_lock,
            &$previously_stored,
            &$previous_value,
        ): mixed {
            $previously_stored = array_key_exists($id, $mods);
            /** @var mixed $previous_value */
            $previous_value = $previously_stored ? $mods[$id] : $default;
            return $complete_in_lock ? eo_complete_in_lock($row, $value, $previous_value) : $value;
        },
        $stylesheet,
        $mods_key,
        $recheck,
    );
    if ($written instanceof WP_Error) {
        return $written;
    }
    return [
        'id' => $id,
        'value' => $written,
        'previous_value' => bly_cap_value($previous_value),
        'previously_stored' => $previously_stored,
        'storage' => BLY_STORAGE_THEME_MOD,
    ];
}

/**
 * The in-lock step of a ct-typography write: completion from the FRESH
 * row's current value (bly_complete_typography_value), then the full
 * typed gates (1 + 2) on the completed object — the value persisted.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function eo_complete_in_lock(array $row, mixed $candidate, mixed $current): mixed
{
    /** @var mixed $completed */
    $completed = bly_complete_typography_value($row, $candidate, $current);
    if ($completed instanceof WP_Error) {
        return $completed;
    }
    return bly_validate_option_value($row, $completed);
}

/**
 * The schema row for `$id`: unknown ids are refused (never write an
 * unknown theme_mod), colorPalette is redirected to its own ability.
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @return array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}|WP_Error
 */
function eo_target_row(string $id, array $index): array|WP_Error
{
    if (!isset($index[$id])) {
        return bly_error('bly_not_found', sprintf(
            'Option "%s" is not defined in the Blocksy option schema; refusing to write an unknown theme_mod. Use blocksy-list-options to discover valid ids.',
            $id,
        ));
    }
    if ($id === 'colorPalette') {
        return bly_error(
            'bly_invalid_input',
            message: 'Use blocksy-edit-color-palette for the color palette — it validates colors and merges per slot instead of replacing the whole structure.',
        );
    }
    return $index[$id];
}

/**
 * Pre-lock validation of the candidate value. Every option but
 * ct-typography is fully validated here (gates 1 + 2 — current-
 * independent). A ct-typography candidate is a partial object completed
 * from the CURRENT stored value — the theme renders a typography object
 * only when every key (and every device of a responsive key) is present
 * — and that completion plus its typed gates run INSIDE the write lock,
 * from the fresh in-lock row, on BOTH storages (the compute closure of
 * blocksy_edit_option for theme_mods; bly_locked_option_write's
 * `$compute` for a row declared setting.type=option — none ship on
 * 2.1.52, a filter-supplied one is covered by the smoke suite):
 * completing from a pre-lock read would let the locked write overwrite a
 * concurrent writer's fresh keys with the stale expansion. Pre-lock only
 * the current-independent shape is checked (bly_typography_candidate_shape),
 * so invalid requests never take the lock.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return array{value: mixed, complete_in_lock: bool}|WP_Error
 */
function eo_prevalidate(array $row, mixed $candidate): array|WP_Error
{
    if ($row['type'] !== 'ct-typography') {
        /** @var mixed $value */
        $value = bly_validate_option_value($row, $candidate);
        return $value instanceof WP_Error ? $value : ['value' => $value, 'complete_in_lock' => false];
    }
    $shape = bly_typography_candidate_shape($row, $candidate);
    return $shape instanceof WP_Error ? $shape : ['value' => $shape, 'complete_in_lock' => true];
}

/**
 * The option-storage branch of blocksy-edit-option: the locked, verified
 * wp_options write (bly_locked_option_write) shaped into the ability's
 * output. `$in_lock['compute']` (ct-typography rows) completes the
 * candidate from the fresh raw row inside the lock.
 *
 * @param array{recheck: callable(mixed): (WP_Error|null), compute: null|callable(mixed): mixed} $in_lock
 * @return array{
 *     id: string,
 *     value: mixed,
 *     previous_value: mixed,
 *     previously_stored: bool,
 *     storage: string,
 * }|WP_Error
 */
function eo_write_option_storage(
    string $id,
    mixed $value,
    mixed $default,
    string $stylesheet,
    array $in_lock,
): array|WP_Error {
    $written = bly_locked_option_write($id, $value, $default, $stylesheet, $in_lock);
    if ($written instanceof WP_Error) {
        return $written;
    }
    return [
        'id' => $id,
        'value' => bly_cap_value($written['value']),
        'previous_value' => bly_cap_value($written['previous_value']),
        'previously_stored' => $written['previously_stored'],
        'storage' => BLY_STORAGE_OPTION,
    ];
}
