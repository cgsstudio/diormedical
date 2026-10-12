<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/** Maximum placement locations echoed per item instance in outputs. */
const BLY_BUILDER_MAX_LOCATIONS = 20;

/** Maximum settings keys accepted in one edit-builder-item call. */
const BLY_BUILDER_MAX_EDIT_KEYS = 50;

/** Maximum option rows echoed in the per-item schema summary. */
const BLY_BUILDER_MAX_ITEM_OPTIONS = 120;

/** Maximum JSON bytes for a per-option default echoed in the item summary. */
const BLY_BUILDER_MAX_OPTION_DEFAULT_BYTES = 1_024;

/** Maximum choice keys echoed per option in the item summary. */
const BLY_BUILDER_MAX_ITEM_CHOICES = 20;

/** Maximum sections expanded by the get abilities (marker via *_omitted). */
const BLY_BUILDER_MAX_SECTIONS = 20;

/** Maximum items_with_settings ids echoed per section (marker element beyond). */
const BLY_BUILDER_MAX_SETTINGS_IDS = 60;

/** Maximum section settings keys echoed per section (marker element beyond). */
const BLY_BUILDER_MAX_SECTION_KEYS = 40;

/** Maximum registered item-type rows echoed by list-builder-items. */
const BLY_BUILDER_MAX_ITEM_TYPES = 100;

/** Maximum placed instances echoed per item type (marker entry beyond). */
const BLY_BUILDER_MAX_PLACED_INSTANCES = 20;

/**
 * Shared helpers for the Blocksy header/footer builder abilities (Sprint 3).
 *
 * Storage model, verified against Blocksy 2.1.52 source:
 *
 *  - The WHOLE header builder state is ONE theme_mod, `header_placements`
 *    (inc/components/builder/header-logic.php:249): `{current_section,
 *    sections: [...]}`. Each section is `{id, mode: 'placements', desktop:
 *    [bar…], mobile: [bar…], items: [{id, values}…], settings: {…}}` where a
 *    bar is `{id: top-row|middle-row|bottom-row|offcanvas, placements:
 *    [{id: start|middle|end|start-middle|end-middle, items: [instanceId…]}]}`
 *    (offcanvas bars carry only `start` — header-logic.php:195,
 *    has_secondary=false). Placed items are referenced by INSTANCE id: the
 *    plain item type id (`logo`), or `type~<32hex>` for extra instances of
 *    clone-enabled items (builder-renderer.php:54 splits on `~`).
 *  - The footer is `footer_placements` (footer-logic.php:227) with the same
 *    envelope but a columns model: sections are `{id, mode: 'columns',
 *    rows: [{id: top-row|middle-row|bottom-row, columns: [[instanceId…]…]},
 *    …], items, settings}` — no per-device trees, no offcanvas.
 *  - Per-item settings live in the SAME mod, in each section's `items` list
 *    as `{id: instanceId, values: {optionId: value…}}` — Blocksy stores an
 *    entry only once the item is customized; absence means "all defaults"
 *    (builder-renderer.php:175 get_item_data_for falls back to []). Rows
 *    (the primary items) store their settings the same way under their row
 *    id. There are NO separate per-item option rows.
 *  - Defaults are NEVER persisted: an absent mod means the builder default
 *    from Blocksy_Header_Builder::get_default_value() (filtered through
 *    `blocksy:header:default_value`) / the footer equivalent applies.
 *  - The section that actually renders is chosen by the
 *    `blocksy:<panel>:current_section_id` filter (header-logic.php:372,
 *    default 'type-1'; Companion Pro's header-conditions feature hooks it) —
 *    the stored `current_section` key only tracks what the customizer UI
 *    last edited.
 *  - Available item types are the directories under
 *    `inc/panel-builder/<panel>/` plus every path added via the
 *    `blocksy:<panel>:items-paths` filter (customizer-builder.php:262 —
 *    Companion free adds `account`, Companion Pro premium adds `contacts`,
 *    `content-block`, `divider`, `language-switcher`, `search-input`, …).
 *    Per-item `config.php` declares placement rules: `devices` (default
 *    both), `allowed_in` (flat row-id list, or a device-keyed map of them),
 *    `excluded_from`, `clone`, `enabled` — adjusted by the
 *    `blocksy:<panel>:items-config` filter. The customizer enforces them
 *    client-side (static/bundle/customizer-controls.js): an item may sit in
 *    a row iff its (device-resolved) allowed_in is empty or contains the
 *    row id, AND its excluded_from does not contain the row id.
 *  - On save the customizer strips the preview-only keys
 *    `__forced_static_header__`, `__should_refresh_item__`,
 *    `__should_refresh__` and numeric keys from the value
 *    (inc/manager.php:96) — writes here mirror that.
 *  - Gate 2 for the whole value is Blocksy's own `ct-header-builder` /
 *    `ct-footer-builder` sanitizer branch (inc/customizer/validator.php:92 →
 *    blocksy_sanitize_builder_value, validator.php:45), which HTML-sanitizes
 *    the `text` item's `header_text` and the `copyright` item's
 *    `copyright_text` through blocksy_sanitize_user_html().
 */

// ---------------------------------------------------------------------------
// Builder identity + theme accessors
// ---------------------------------------------------------------------------

/** The theme_mod id a builder's whole state lives in. */
function bly_builder_mod_id(string $builder): string
{
    return $builder === 'header' ? 'header_placements' : 'footer_placements';
}

/**
 * The primary (row) item ids of a builder — never placeable or deletable,
 * always editable (their settings live in section.items like any item's).
 *
 * @return list<string>
 */
function bly_builder_primary_ids(string $builder): array
{
    return (
        $builder === 'header'
            ? ['top-row', 'middle-row', 'bottom-row', 'offcanvas']
            : ['top-row', 'middle-row', 'bottom-row']
    );
}

/**
 * Validated `builder` input parameter ('header' or 'footer'), or WP_Error.
 */
function bly_builder_input(mixed $builder): string|WP_Error
{
    if ($builder === 'header' || $builder === 'footer') {
        return $builder;
    }
    return bly_error('bly_invalid_input', message: '`builder` is required and must be "header" or "footer".');
}

/**
 * The item TYPE id behind an instance id: `button~a1b2…` → `button`,
 * plain ids pass through (mirror of Blocksy_Builder_Render::get_original_id,
 * builder-renderer.php:54).
 */
function bly_original_item_id(string $instance): string
{
    return explode('~', $instance)[0];
}

/**
 * A fresh instance id for an extra copy of a clone-enabled item, in the
 * exact `type~<32hex>` shape the customizer generates (blocksy_rand_md5).
 * Collisions are not re-checked against the stored tree: 128 random bits
 * from md5(uniqid(wp_rand(), true)) colliding with the handful of existing
 * clone ids is beyond astronomically unlikely — the same bet the theme's
 * own blocksy_rand_md5() makes for every generated id.
 */
function bly_new_instance_id(string $item): string
{
    return $item . '~' . md5(uniqid((string) wp_rand(), more_entropy: true));
}

/**
 * JS-parity check for a builder item's `enabled` config flag: the
 * customizer gates on plain truthiness, so ANY falsey value — false, 0,
 * 0.0, '' or null — disables the item, not only literal false. A missing
 * key was already defaulted to true by the config merge.
 *
 * @param array<array-key, mixed> $config
 */
function bly_builder_item_enabled(array $config): bool
{
    /** @var mixed $enabled */
    $enabled = $config['enabled'] ?? true;
    return !in_array($enabled, [false, 0, 0.0, '', null], strict: true);
}

/**
 * The maximum TOTAL number of instances of one item type, mirroring the
 * customizer's own formula `(parseInt(config.clone) || 1) + 1` behind a
 * truthiness gate (static/bundle: the clone button renders only while
 * `count < (parseInt(clone)||1)+1`): no/falsey clone flag → 1 instance;
 * clone: true → 2 (parseInt(true) is NaN, || 1); clone: 9 (the header
 * text item) → 10.
 *
 * @param array<array-key, mixed> $config
 */
function bly_builder_clone_limit(array $config): int
{
    /** @var mixed $clone */
    $clone = $config['clone'] ?? false;
    if (in_array($clone, [false, 0, 0.0, '', null], strict: true)) {
        return 1;
    }
    $numeric = is_scalar($clone) ? (int) $clone : 0;
    if ($numeric < 1) {
        $numeric = 1;
    }
    return $numeric + 1;
}

/**
 * Every existing instance id of one item type in a section — placed in
 * the trees or holding a settings entry (the customizer's CLONE_ITEM
 * creates the settings entry first, so an unplaced clone still exists and
 * still counts toward the clone limit). Placed instances first, then
 * settings-only ones; duplicates collapsed.
 *
 * @param array<array-key, mixed> $section
 * @return list<string>
 */
function bly_builder_type_instances(string $builder, array $section, string $type): array
{
    $out = [];
    foreach (bly_builder_placed_ids($builder, $section) as $placed) {
        if (bly_original_item_id($placed) !== $type) {
            continue;
        }
        $out[] = $placed;
    }
    /** @var mixed $entry */
    foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $entry) {
        if (!is_array($entry) || !is_string($entry['id'] ?? null)) {
            continue;
        }
        $id = $entry['id'];
        if (bly_original_item_id($id) === $type && !in_array($id, $out, strict: true)) {
            $out[] = $id;
        }
    }
    return $out;
}

/**
 * Copy the BASE (plain type id) instance's settings entry onto a freshly
 * created clone id — the exact CLONE_ITEM reducer behavior (the
 * customizer strips the clicked id to its original and copies THAT
 * entry's values, or {} when the base was never customized), so a new
 * clone starts as a settings copy of the base instance.
 *
 * @param array<array-key, mixed> $section
 * @param-out array<array-key, mixed> $section
 */
function bly_builder_copy_base_values(array &$section, string $type, string $clone_instance): void
{
    $base_values = bly_builder_item_values($section, $type);
    bly_builder_set_item_values($section, $clone_instance, $base_values ?? []);
}

/**
 * Capability-aware HTML sanitization for a builder item's HTML-bearing
 * (wp-editor) settings — the EXACT function Blocksy's builder sanitizer
 * applies to its hardcoded item ids (blocksy_sanitize_user_html,
 * inc/helpers.php:362: strips <script> blocks and on* attributes for
 * users without unfiltered_html, stores admin HTML verbatim). Blocksy's
 * own gate matches literal ids only ('text', and a 'copyright_text' key
 * that never matches the real 'copyright' instance id), so clone
 * instances (text~<hash>) and the footer copyright would ship UNSANITIZED
 * without this normalization — the value renders through
 * blocksy_output_html_safely(), which is only do_shortcode(). Fails
 * closed: if the theme helper cannot run, the write is refused.
 */
function bly_builder_sanitize_html_leaf(string $option_id, string $value): string|WP_Error
{
    if (!function_exists('blocksy_sanitize_user_html')) {
        return bly_sanitizer_error(
            $option_id,
            detail: 'blocksy_sanitize_user_html() is not defined — the theme helpers are not loaded',
        );
    }
    try {
        /** @var mixed $sanitized */
        $sanitized = blocksy_sanitize_user_html($value);
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: blocksy_sanitize_user_html() threw for "%s": %s',
            $option_id,
            $e->getMessage(),
        ));
        return bly_sanitizer_error(
            $option_id,
            detail: 'blocksy_sanitize_user_html() threw — details in the server error log',
        );
    }
    return is_string($sanitized) ? $sanitized : '';
}

/**
 * Cap a list of strings for agent-facing output, signaling truncation
 * with an explicit trailing marker element (S1 discipline — silent
 * truncation reads as "this is everything").
 *
 * @param list<string> $list
 * @return list<string>
 */
function bly_builder_cap_string_list(array $list, int $cap, string $what): array
{
    if (count($list) <= $cap) {
        return $list;
    }
    $out = array_slice($list, offset: 0, length: $cap);
    $out[] = sprintf('[+%d more %s omitted beyond the %d-entry cap]', count($list) - $cap, $what, $cap);
    return $out;
}

/**
 * The theme's DEFAULT builder value (never persisted by Blocksy), through
 * the same code path the front end uses — get_default_value() on the
 * builder-logic object, including its `blocksy:header:default_value`
 * filter. Fails explicitly when the theme objects are unavailable or a
 * default-value filter throws; never fabricates a structure.
 *
 * @return array<array-key, mixed>|WP_Error
 */
function bly_builder_default_value(string $builder): array|WP_Error
{
    if (!function_exists('blocksy_manager')) {
        return bly_theme_inactive_error();
    }
    $logic = $builder === 'header' ? blocksy_manager()->header_builder : blocksy_manager()->footer_builder;
    if ($logic === null) {
        return bly_schema_error(sprintf('the theme %s builder object is not initialized', $builder));
    }

    try {
        /** @var mixed $default */
        $default = $logic->get_default_value();
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: computing the default %s builder value threw: %s',
            $builder,
            $e->getMessage(),
        ));
        return bly_schema_error(sprintf(
            'the default %s builder value could not be computed (a default_value filter threw — details in the server error log)',
            $builder,
        ));
    }

    if (!bly_builder_value_usable($default)) {
        return bly_schema_error(sprintf('the theme returned an unusable default %s builder value', $builder));
    }
    /** @var array<array-key, mixed> $default */
    return $default;
}

/**
 * Whether a candidate builder value has the minimal envelope every consumer
 * in the theme dereferences unguarded: an array with a non-empty `sections`
 * list of arrays.
 */
function bly_builder_value_usable(mixed $value): bool
{
    if (!is_array($value) || !is_array($value['sections'] ?? null) || $value['sections'] === []) {
        return false;
    }
    /** @var mixed $section */
    foreach ($value['sections'] as $section) {
        if (!is_array($section)) {
            return false;
        }
    }
    return true;
}

/**
 * The EFFECTIVE builder value: the stored theme_mod when present and
 * structurally usable, else the theme default. `stored_invalid` reports a
 * present-but-unusable stored value — reads fall back to the default in
 * that case (like the theme would render garbage, the abilities refuse to
 * pretend it is fine and surface the flag), and WRITES must refuse instead
 * of silently replacing whatever the corrupted row held.
 *
 * @param array<array-key, mixed>|null $mods Pre-fetched mods (the locked
 *     write path passes the fresh in-lock read); null reads the current mods.
 * @return array{value: array<array-key, mixed>, stored: bool, stored_invalid: bool}|WP_Error
 */
function bly_builder_effective_value(string $builder, ?array $mods = null): array|WP_Error
{
    $mods ??= bly_stored_mods();
    $mod_id = bly_builder_mod_id($builder);
    $has_stored = array_key_exists($mod_id, $mods);
    /** @var mixed $stored */
    $stored = $has_stored ? $mods[$mod_id] : null;

    if ($has_stored && bly_builder_value_usable($stored)) {
        /** @var array<array-key, mixed> $stored */
        return ['value' => $stored, 'stored' => true, 'stored_invalid' => false];
    }

    $default = bly_builder_default_value($builder);
    if ($default instanceof WP_Error) {
        return $default;
    }
    return ['value' => $default, 'stored' => false, 'stored_invalid' => $has_stored];
}

/**
 * The section id that actually renders, via the same filter the theme
 * resolves it with (`blocksy:<builder>:current_section_id`, default
 * 'type-1'). Companion Pro's header conditions hook this filter; a throwing
 * hook is contained to the theme's own default. Mirroring the theme's own
 * lookup (header-logic.php:63 falls back to `sections[0]` when the filtered
 * id matches nothing), a filtered id that does not exist in the value
 * resolves to the FIRST section's id — never to a phantom section.
 */
function bly_builder_rendered_section_id(string $builder, array $value): string
{
    $theme_default = 'type-1';
    try {
        /** @var mixed $id */
        $id = apply_filters('blocksy:' . $builder . ':current_section_id', $theme_default, $value);
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: a %s current_section_id filter threw: %s',
            $builder,
            $e->getMessage(),
        ));
        $id = 'type-1';
    }
    $id = is_string($id) && $id !== '' ? $id : 'type-1';

    $first = null;
    /** @var mixed $section */
    foreach (is_array($value['sections'] ?? null) ? $value['sections'] : [] as $section) {
        if (!is_array($section) || !is_string($section['id'] ?? null)) {
            continue;
        }
        if ($section['id'] === $id) {
            return $id;
        }
        $first ??= $section['id'];
    }
    return is_string($first) ? $first : $id;
}

/**
 * Locate a section by id inside a builder value. With `$section_id` null
 * the rendered section is targeted, falling back to the first section like
 * the theme's own lookup does (header-logic.php:63). An EXPLICIT id that
 * does not exist is an error listing the available ids.
 *
 * @param array<array-key, mixed> $value
 * @return array{index: int, section: array<array-key, mixed>}|WP_Error
 */
function bly_builder_find_section(string $builder, array $value, ?string $section_id): array|WP_Error
{
    $sections = is_array($value['sections'] ?? null) ? $value['sections'] : [];
    $wanted = $section_id ?? bly_builder_rendered_section_id($builder, $value);

    $available = [];
    $first = null;
    /** @var mixed $section */
    foreach (array_values($sections) as $index => $section) {
        if (!is_array($section)) {
            continue;
        }
        $id = isset($section['id']) && is_string($section['id']) ? $section['id'] : '';
        $available[] = $id;
        $first ??= ['index' => $index, 'section' => $section];
        if ($id === $wanted) {
            return ['index' => $index, 'section' => $section];
        }
    }

    if ($section_id === null && $first !== null) {
        return $first;
    }
    return bly_error('bly_not_found', sprintf(
        'Section "%s" does not exist in the %s builder. Available sections: %s.',
        $wanted,
        $builder,
        $available === [] ? '(none)' : implode(', ', $available),
    ));
}

/**
 * Strip the preview-only bookkeeping the customizer itself removes on save
 * (inc/manager.php:96): the __forced_static/__should_refresh keys and any
 * numeric top-level keys.
 *
 * @param array<array-key, mixed> $value
 * @return array<array-key, mixed>
 */
function bly_builder_strip_preview_keys(array $value): array
{
    unset(
        $value['__forced_static_header__'],
        $value['__forced_static_footer__'],
        $value['__should_refresh_item__'],
        $value['__should_refresh__'],
    );
    foreach (array_keys($value) as $key) {
        if (!is_int($key) && !is_numeric($key)) {
            continue;
        }
        unset($value[$key]);
    }
    return $value;
}

// ---------------------------------------------------------------------------
// Structure walkers (locations, placed instances, compact summaries)
// ---------------------------------------------------------------------------

/**
 * Every location one instance id occupies in a HEADER section's device
 * trees.
 *
 * @param array<array-key, mixed> $section
 * @return list<array{device: string, row: string, placement: string, position: int}>
 */
// Defensive walk over untyped stored builder trees — every node is
// shape-checked before use.
// @mago-expect lint:cyclomatic-complexity
function bly_header_locations(array $section, string $instance): array
{
    $out = [];
    foreach (['desktop', 'mobile'] as $device) {
        /** @var mixed $bar */
        foreach (is_array($section[$device] ?? null) ? $section[$device] : [] as $bar) {
            if (!is_array($bar)) {
                continue;
            }
            $row = isset($bar['id']) && is_string($bar['id']) ? $bar['id'] : '';
            /** @var mixed $slot */
            foreach (is_array($bar['placements'] ?? null) ? $bar['placements'] : [] as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $placement = isset($slot['id']) && is_string($slot['id']) ? $slot['id'] : '';
                /** @var mixed $item */
                foreach (array_values(is_array($slot['items'] ?? null) ? $slot['items'] : []) as $position => $item) {
                    if ($item !== $instance) {
                        continue;
                    }
                    $out[] = [
                        'device' => $device,
                        'row' => $row,
                        'placement' => $placement,
                        'position' => $position,
                    ];
                }
            }
        }
    }
    return $out;
}

/**
 * Every location one instance id occupies in a FOOTER section's rows.
 * Columns are reported 1-based, matching the ability input.
 *
 * @param array<array-key, mixed> $section
 * @return list<array{row: string, column: int, position: int}>
 */
function bly_footer_locations(array $section, string $instance): array
{
    $out = [];
    /** @var mixed $row_node */
    foreach (is_array($section['rows'] ?? null) ? $section['rows'] : [] as $row_node) {
        if (!is_array($row_node)) {
            continue;
        }
        $row = isset($row_node['id']) && is_string($row_node['id']) ? $row_node['id'] : '';
        /** @var mixed $column */
        foreach (array_values(is_array($row_node['columns'] ?? null) ? $row_node['columns'] : []) as $ci => $column) {
            /** @var mixed $item */
            foreach (array_values(is_array($column) ? $column : []) as $position => $item) {
                if ($item !== $instance) {
                    continue;
                }
                $out[] = ['row' => $row, 'column' => $ci + 1, 'position' => $position];
            }
        }
    }
    return $out;
}

/**
 * Human-readable location strings for one instance in one section, capped.
 *
 * @param array<array-key, mixed> $section
 * @return list<string>
 */
function bly_builder_location_strings(string $builder, array $section, string $instance): array
{
    $out = [];
    if ($builder === 'header') {
        foreach (bly_header_locations($section, $instance) as $loc) {
            $out[] = sprintf('%s:%s:%s', $loc['device'], $loc['row'], $loc['placement']);
        }
        return bly_builder_cap_string_list($out, BLY_BUILDER_MAX_LOCATIONS, what: 'locations');
    }
    foreach (bly_footer_locations($section, $instance) as $loc) {
        $out[] = sprintf('%s:col%d', $loc['row'], $loc['column']);
    }
    return bly_builder_cap_string_list($out, BLY_BUILDER_MAX_LOCATIONS, what: 'locations');
}

/**
 * Every instance id placed anywhere in a section (order of first
 * appearance, duplicates collapsed).
 *
 * @param array<array-key, mixed> $section
 * @return list<string>
 */
// Defensive walk over untyped stored builder trees — every node is
// shape-checked before use.
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:kan-defect
function bly_builder_placed_ids(string $builder, array $section): array
{
    $out = [];
    $push = static function (mixed $item) use (&$out): void {
        /** @var list<string> $out */
        if (is_string($item) && $item !== '' && !in_array($item, $out, strict: true)) {
            $out[] = $item;
        }
    };

    if ($builder === 'header') {
        foreach (['desktop', 'mobile'] as $device) {
            /** @var mixed $bar */
            foreach (is_array($section[$device] ?? null) ? $section[$device] : [] as $bar) {
                /** @var mixed $slot */
                foreach (is_array($bar) && is_array($bar['placements'] ?? null) ? $bar['placements'] : [] as $slot) {
                    /** @var mixed $item */
                    foreach (is_array($slot) && is_array($slot['items'] ?? null) ? $slot['items'] : [] as $item) {
                        $push($item);
                    }
                }
            }
        }
        return $out;
    }

    /** @var mixed $row_node */
    foreach (is_array($section['rows'] ?? null) ? $section['rows'] : [] as $row_node) {
        /** @var mixed $column */
        foreach (is_array($row_node) && is_array($row_node['columns'] ?? null) ? $row_node['columns'] : [] as $column) {
            /** @var mixed $item */
            foreach (is_array($column) ? $column : [] as $item) {
                $push($item);
            }
        }
    }
    return $out;
}

/**
 * Compact structure summary of one HEADER section:
 * {desktop: {row: {placement: [instanceId…]}}, mobile: {…}} in stored order.
 *
 * @param array<array-key, mixed> $section
 * @return array<string, array<string, array<string, list<string>>|\stdClass>>
 */
// Defensive walk over untyped stored builder trees — every node is
// shape-checked before use.
// @mago-expect lint:cyclomatic-complexity
function bly_header_structure_summary(array $section): array
{
    $out = [];
    foreach (['desktop', 'mobile'] as $device) {
        $rows = [];
        /** @var mixed $bar */
        foreach (is_array($section[$device] ?? null) ? $section[$device] : [] as $bar) {
            if (!is_array($bar) || !isset($bar['id']) || !is_string($bar['id'])) {
                continue;
            }
            $placements = [];
            /** @var mixed $slot */
            foreach (is_array($bar['placements'] ?? null) ? $bar['placements'] : [] as $slot) {
                if (!is_array($slot) || !isset($slot['id']) || !is_string($slot['id'])) {
                    continue;
                }
                $items = [];
                /** @var mixed $item */
                foreach (is_array($slot['items'] ?? null) ? $slot['items'] : [] as $item) {
                    if (!is_string($item)) {
                        continue;
                    }
                    $items[] = $item;
                }
                $placements[$slot['id']] = $items;
            }
            $rows[$bar['id']] = bly_object_or_map($placements);
        }
        $out[$device] = bly_object_or_map($rows);
    }
    /** @var array<string, array<string, array<string, list<string>>|\stdClass>> */
    return $out;
}

/**
 * Compact structure summary of one FOOTER section:
 * {row: [[instanceId…] per column]} in stored order.
 *
 * @param array<array-key, mixed> $section
 * @return array<string, list<list<string>>>
 */
function bly_footer_structure_summary(array $section): array
{
    $out = [];
    /** @var mixed $row_node */
    foreach (is_array($section['rows'] ?? null) ? $section['rows'] : [] as $row_node) {
        if (!is_array($row_node) || !isset($row_node['id']) || !is_string($row_node['id'])) {
            continue;
        }
        $columns = [];
        /** @var mixed $column */
        foreach (is_array($row_node['columns'] ?? null) ? $row_node['columns'] : [] as $column) {
            $items = [];
            /** @var mixed $item */
            foreach (is_array($column) ? $column : [] as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $items[] = $item;
            }
            $columns[] = $items;
        }
        $out[$row_node['id']] = $columns;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Structure mutators (used inside the locked write's compute callback)
// ---------------------------------------------------------------------------

/**
 * Insert an instance id into one header placement target
 * ({device, row, placement}). The target must exist in the STORED
 * structure — bars and placement slots are never fabricated (an offcanvas
 * bar legitimately has only `start`).
 *
 * @param array<array-key, mixed> $section
 * @param array{device: string, row: string, placement: string, column: int} $target
 * @param-out array<array-key, mixed> $section
 */
// @mago-expect lint:cyclomatic-complexity
function bly_header_insert(array &$section, array $target, string $instance, ?int $position): ?WP_Error
{
    ['device' => $device, 'row' => $row, 'placement' => $placement] = $target;
    if (!isset($section[$device]) || !is_array($section[$device])) {
        return bly_error('bly_not_found', sprintf(
            'This header section stores no `%s` device tree. Read blocksy-get-header for the actual structure.',
            $device,
        ));
    }

    $bars = $section[$device];
    $rows_seen = [];
    /** @var mixed $bar */
    foreach ($bars as $bar_index => $bar) {
        if (!is_array($bar)) {
            continue;
        }
        $bar_id = isset($bar['id']) && is_string($bar['id']) ? $bar['id'] : '';
        $rows_seen[] = $bar_id;
        if ($bar_id !== $row) {
            continue;
        }

        $slots_seen = [];
        $placements = is_array($bar['placements'] ?? null) ? $bar['placements'] : [];
        /** @var mixed $slot */
        foreach ($placements as $slot_index => $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $slot_id = isset($slot['id']) && is_string($slot['id']) ? $slot['id'] : '';
            $slots_seen[] = $slot_id;
            if ($slot_id !== $placement) {
                continue;
            }
            $items = array_values(is_array($slot['items'] ?? null) ? $slot['items'] : []);
            $error = bly_builder_splice($items, $instance, $position);
            if ($error !== null) {
                return $error;
            }
            $slot['items'] = $items;
            $placements[$slot_index] = $slot;
            $bar['placements'] = $placements;
            $bars[$bar_index] = $bar;
            $section[$device] = $bars;
            return null;
        }
        return bly_error('bly_not_found', sprintf(
            'Placement "%s" does not exist in the %s %s row. Available placements: %s.',
            $placement,
            $device,
            $row,
            $slots_seen === [] ? '(none)' : implode(', ', $slots_seen),
        ));
    }
    return bly_error('bly_not_found', sprintf(
        'Row "%s" does not exist in this header section\'s %s tree. Available rows: %s.',
        $row,
        $device,
        $rows_seen === [] ? '(none)' : implode(', ', $rows_seen),
    ));
}

/**
 * Insert an instance id into one footer column (1-based). Columns are
 * never fabricated: the column count is builder state the customizer
 * manages through the row's own settings.
 *
 * @param array<array-key, mixed> $section
 * @param-out array<array-key, mixed> $section
 */
function bly_footer_insert(array &$section, string $row, int $column, string $instance, ?int $position): ?WP_Error
{
    $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
    $rows_seen = [];
    /** @var mixed $row_node */
    foreach ($rows as $row_index => $row_node) {
        if (!is_array($row_node)) {
            continue;
        }
        $row_id = isset($row_node['id']) && is_string($row_node['id']) ? $row_node['id'] : '';
        $rows_seen[] = $row_id;
        if ($row_id !== $row) {
            continue;
        }
        $columns = array_values(is_array($row_node['columns'] ?? null) ? $row_node['columns'] : []);
        if ($column < 1 || $column > count($columns)) {
            return bly_error('bly_not_found', sprintf(
                'Column %d does not exist in the footer %s row — it has %d column(s). Columns are 1-based; change the column count through the row\'s own settings first if needed.',
                $column,
                $row,
                count($columns),
            ));
        }
        /** @var mixed $column_items */
        $column_items = $columns[$column - 1];
        $items = array_values(is_array($column_items) ? $column_items : []);
        $error = bly_builder_splice($items, $instance, $position);
        if ($error !== null) {
            return $error;
        }
        $columns[$column - 1] = $items;
        $row_node['columns'] = $columns;
        $rows[$row_index] = $row_node;
        $section['rows'] = $rows;
        return null;
    }
    return bly_error('bly_not_found', sprintf(
        'Row "%s" does not exist in this footer section. Available rows: %s.',
        $row,
        $rows_seen === [] ? '(none)' : implode(', ', $rows_seen),
    ));
}

/**
 * Splice an instance id into an items list at `$position` (null appends);
 * out-of-range positions are rejected, never clamped.
 *
 * @param list<mixed> $items
 * @param-out list<mixed> $items
 */
function bly_builder_splice(array &$items, string $instance, ?int $position): ?WP_Error
{
    $count = count($items);
    $at = $position ?? $count;
    if ($at < 0 || $at > $count) {
        return bly_error('bly_invalid_input', sprintf(
            '`position` %d is out of range — the target holds %d item(s), so valid positions are 0..%d (omit to append).',
            $at,
            $count,
            $count,
        ));
    }
    array_splice($items, $at, length: 0, replacement: [$instance]);
    return null;
}

/**
 * Remove every occurrence of an instance id from a header section's device
 * trees (`$device` null removes from both). Returns the removed count.
 *
 * @param array<array-key, mixed> $section
 * @param-out array<array-key, mixed> $section
 */
function bly_header_remove(array &$section, string $instance, ?string $device): int
{
    $removed = 0;
    foreach (['desktop', 'mobile'] as $tree) {
        if ($device !== null && $tree !== $device) {
            continue;
        }
        if (!is_array($section[$tree] ?? null)) {
            continue;
        }
        $bars = $section[$tree];
        /** @var mixed $bar */
        foreach ($bars as $bar_index => $bar) {
            if (!is_array($bar) || !is_array($bar['placements'] ?? null)) {
                continue;
            }
            $placements = $bar['placements'];
            /** @var mixed $slot */
            foreach ($placements as $slot_index => $slot) {
                if (!is_array($slot) || !is_array($slot['items'] ?? null)) {
                    continue;
                }
                $kept = [];
                /** @var mixed $item */
                foreach ($slot['items'] as $item) {
                    if ($item === $instance) {
                        $removed++;
                        continue;
                    }
                    /** @var mixed */
                    $kept[] = $item;
                }
                $slot['items'] = $kept;
                $placements[$slot_index] = $slot;
            }
            $bar['placements'] = $placements;
            $bars[$bar_index] = $bar;
        }
        $section[$tree] = $bars;
    }
    return $removed;
}

/**
 * Remove every occurrence of an instance id from a footer section's rows.
 * Returns the removed count.
 *
 * @param array<array-key, mixed> $section
 * @param-out array<array-key, mixed> $section
 */
function bly_footer_remove(array &$section, string $instance): int
{
    $removed = 0;
    $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
    /** @var mixed $row_node */
    foreach ($rows as $row_index => $row_node) {
        if (!is_array($row_node) || !is_array($row_node['columns'] ?? null)) {
            continue;
        }
        $columns = [];
        /** @var mixed $column */
        foreach ($row_node['columns'] as $column) {
            $kept = [];
            /** @var mixed $item */
            foreach (is_array($column) ? $column : [] as $item) {
                if ($item === $instance) {
                    $removed++;
                    continue;
                }
                /** @var mixed */
                $kept[] = $item;
            }
            $columns[] = $kept;
        }
        $row_node['columns'] = $columns;
        $rows[$row_index] = $row_node;
    }
    $section['rows'] = $rows;
    return $removed;
}

// ---------------------------------------------------------------------------
// Per-item settings entries (section.items)
// ---------------------------------------------------------------------------

/**
 * One instance's stored settings (`values`) from a section's items list,
 * or null when the item was never customized (Blocksy stores no entry then).
 *
 * @param array<array-key, mixed> $section
 * @return array<array-key, mixed>|null
 */
function bly_builder_item_values(array $section, string $instance): ?array
{
    /** @var mixed $entry */
    foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $entry) {
        if (is_array($entry) && ($entry['id'] ?? null) === $instance) {
            return is_array($entry['values'] ?? null) ? $entry['values'] : [];
        }
    }
    return null;
}

/**
 * Write one instance's settings entry (replacing its `values`, keeping any
 * sibling keys of the entry), creating the entry when absent. The items
 * list stays a JSON list.
 *
 * @param array<array-key, mixed> $section
 * @param array<array-key, mixed> $values
 * @param-out array<array-key, mixed> $section
 */
function bly_builder_set_item_values(array &$section, string $instance, array $values): void
{
    $items = array_values(is_array($section['items'] ?? null) ? $section['items'] : []);
    /** @var mixed $entry */
    foreach ($items as $index => $entry) {
        if (!is_array($entry) || ($entry['id'] ?? null) !== $instance) {
            continue;
        }
        $entry['values'] = $values;
        $items[$index] = $entry;
        $section['items'] = $items;
        return;
    }
    $items[] = ['id' => $instance, 'values' => $values];
    $section['items'] = $items;
}

/**
 * Drop one instance's settings entry. Returns whether an entry existed.
 *
 * @param array<array-key, mixed> $section
 * @param-out array<array-key, mixed> $section
 */
function bly_builder_drop_item_values(array &$section, string $instance): bool
{
    if (!isset($section['items']) || !is_array($section['items'])) {
        return false;
    }
    $kept = [];
    $dropped = false;
    /** @var mixed $entry */
    foreach ($section['items'] as $entry) {
        if (is_array($entry) && ($entry['id'] ?? null) === $instance) {
            $dropped = true;
            continue;
        }
        /** @var mixed */
        $kept[] = $entry;
    }
    $section['items'] = $kept;
    return $dropped;
}

/**
 * Replace one section (by the normalized index bly_builder_find_section
 * reported) inside a builder value — through a locally-typed sections list
 * so the assignment never targets a mixed node.
 *
 * @param array<array-key, mixed> $value
 * @param array<array-key, mixed> $section
 * @return array<array-key, mixed>
 */
function bly_builder_replace_section(array $value, int $index, array $section): array
{
    $sections = is_array($value['sections'] ?? null) ? $value['sections'] : [];
    $sections[$index] = $section;
    $value['sections'] = $sections;
    return $value;
}

// ---------------------------------------------------------------------------
// Registered item types + configs (theme + companion, filter-aware)
// ---------------------------------------------------------------------------

/**
 * Every registered item type of a builder, keyed by item id:
 * {id, path, is_primary, config} — through the theme's own registry
 * (Blocksy_Customizer_Builder::get_registered_items_by, which resolves the
 * `blocksy:<builder>:items-paths` filter), with configs read from each
 * item's config.php. Outside admin/customize requests the registry omits
 * configs entirely (customizer-builder.php:311), so they are read here with
 * the same defaulting and the same `blocksy:<builder>:items-config` filter
 * the theme applies (customizer-builder.php:336 read_config_for).
 *
 * Deliberately NOT memoized: the paths and configs flow through filters
 * that can be added or removed at any point in a request, and a stale
 * snapshot would validate placements against rules that no longer apply.
 * The cost is a handful of tiny config.php requires per call.
 *
 * @return array<string, array{id: string, path: string, is_primary: bool, config: array<array-key, mixed>}>|WP_Error
 */
function bly_builder_registered_items(string $builder): array|WP_Error
{
    if (!function_exists('blocksy_manager')) {
        return bly_theme_inactive_error();
    }
    $registry = blocksy_manager()->builder;
    if ($registry === null) {
        return bly_schema_error('the theme builder registry object is not initialized');
    }

    try {
        $rows = $registry->get_registered_items_by($builder);
    } catch (\Throwable $e) {
        error_log(sprintf('novamira-pro blocksy: enumerating %s builder items threw: %s', $builder, $e->getMessage()));
        return bly_schema_error(sprintf(
            'the %s builder item registry could not be enumerated (an items-paths filter threw — details in the server error log)',
            $builder,
        ));
    }

    $items = [];
    foreach ($rows as $row) {
        if (!isset($row['id'], $row['path']) || !is_string($row['id']) || !is_string($row['path'])) {
            continue;
        }
        $items[$row['id']] = [
            'id' => $row['id'],
            'path' => $row['path'],
            'is_primary' => ($row['is_primary'] ?? false) === true,
            'config' => bly_builder_read_item_config($builder, $row['id'], $row['path']),
        ];
    }

    return $items;
}

/**
 * One item's config, mirroring the theme's private read_config_for
 * (customizer-builder.php:336): the `$config` array from the item's
 * config.php (contained — a throwing config file degrades to defaults),
 * merged over the same defaults, then passed through the
 * `blocksy:<builder>:items-config` filter exactly like the customizer does
 * (Companion Pro adjusts devices/allowed_in through it).
 *
 * @return array<array-key, mixed>
 */
function bly_builder_read_item_config(string $builder, string $id, string $path): array
{
    $config = [];
    $file = $path . '/config.php';
    if (is_readable($file)) {
        try {
            $config = (static function (string $novamira_config_file): array {
                $config = [];
                require $novamira_config_file;
                /** @var mixed $config */
                return is_array($config) ? $config : [];
            })($file);
        } catch (\Throwable $e) {
            error_log(sprintf(
                'novamira-pro blocksy: reading builder item config %s threw: %s',
                $file,
                $e->getMessage(),
            ));
        }
    }

    $config = array_merge([
        'name' => implode(' ', array_map('ucfirst', explode('-', basename($path)))),
        'description' => '',
        'typography_keys' => [],
        'translation_keys' => [],
        'devices' => ['desktop', 'mobile'],
        'selective_refresh' => [],
        'allowed_in' => [],
        'excluded_from' => [],
        'clone' => false,
        'shortcut_style' => 'drop',
        'enabled' => true,
    ], $config);

    try {
        /** @var mixed $filtered */
        $filtered = apply_filters('blocksy:' . $builder . ':items-config', $config, $id);
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: a %s items-config filter threw for "%s": %s',
            $builder,
            $id,
            $e->getMessage(),
        ));
        $filtered = $config;
    }
    return is_array($filtered) ? $filtered : $config;
}

/**
 * Where an item type comes from: the theme, Blocksy Companion (free
 * surface), or the Companion Pro premium bundle. Path containment against
 * the template directory and the companion plugin directory — premium
 * items all live under `framework/premium/`.
 */
function bly_builder_item_source(string $path): string
{
    $norm = wp_normalize_path($path);
    if (str_starts_with($norm, wp_normalize_path(get_template_directory()))) {
        return 'theme';
    }
    if (defined('BLOCKSY__FILE__')) {
        /** @var mixed $companion_file */
        $companion_file = constant('BLOCKSY__FILE__');
        if (is_string($companion_file) && $companion_file !== '') {
            $companion = wp_normalize_path(dirname($companion_file));
            if (str_starts_with($norm, $companion)) {
                return str_contains($norm, '/premium/') ? 'companion-pro' : 'companion';
            }
        }
    }
    return 'other';
}

/**
 * Whether an item may sit in a row, per the exact rule the customizer's
 * JS enforces (static/bundle/customizer-controls.js): resolve `allowed_in`
 * for the device (a flat list applies everywhere; a device-keyed map
 * contributes only its matching entry, no entry = no restriction), then
 * the row must be allowed (empty list = anywhere) and not excluded.
 *
 * @param array<array-key, mixed> $config
 */
function bly_builder_item_allowed_at(array $config, string $row, ?string $device): bool
{
    /** @var mixed $allowed_raw */
    $allowed_raw = $config['allowed_in'] ?? [];
    $allowed = [];
    if (is_array($allowed_raw) && bly_array_is_list($allowed_raw)) {
        $allowed = $allowed_raw;
    }
    if (
        is_array($allowed_raw)
        && !bly_array_is_list($allowed_raw)
        && $device !== null
        && isset($allowed_raw[$device])
        && is_array($allowed_raw[$device])
    ) {
        $allowed = $allowed_raw[$device];
    }
    /** @var mixed $excluded_raw */
    $excluded_raw = $config['excluded_from'] ?? [];
    $excluded = is_array($excluded_raw) ? $excluded_raw : [];

    $allowed_ok = $allowed === [] || in_array($row, $allowed, strict: true);
    $excluded_ok = $excluded === [] || !in_array($row, $excluded, strict: true);
    return $allowed_ok && $excluded_ok;
}

/**
 * The device trees an item's config admits it into ('desktop'/'mobile'),
 * matching the customizer's exact `devices.includes(view)` check: only a
 * MISSING (or non-array) `devices` key defaults to both trees — an
 * explicitly-empty declared list means NO devices, exactly as the JS
 * would place the item nowhere, and is returned empty here.
 *
 * @param array<array-key, mixed> $config
 * @return list<string>
 */
function bly_builder_item_devices(array $config): array
{
    /** @var mixed $devices_raw */
    $devices_raw = $config['devices'] ?? null;
    if (!is_array($devices_raw)) {
        return ['desktop', 'mobile'];
    }
    $out = [];
    /** @var mixed $device */
    foreach ($devices_raw as $device) {
        if ($device !== 'desktop' && $device !== 'mobile') {
            continue;
        }
        $out[] = $device;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Per-item option schemas (gate 1 for item settings)
// ---------------------------------------------------------------------------

/**
 * Flat id-indexed option schema for one registered item, loaded through
 * the theme's own loader (Blocksy_Customizer_Builder::get_options_for →
 * blocksy_get_options on the item's options.php) and flattened with the
 * same walker the customizer-wide index uses. Items without an options.php
 * yield []. Not memoized, for the same filter-sensitivity reason as
 * bly_builder_registered_items (option files see `blocksy:options:retrieve`
 * and friends).
 *
 * Callers resolve bly_option_index_or_error() FIRST (it preloads the
 * customizer sync helpers some option files assume) and propagate its
 * WP_Error — that also guarantees gate 2 can resolve the builder option row.
 *
 * @return array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>|WP_Error
 */
function bly_builder_item_option_index(string $builder, string $item_id, string $path): array|WP_Error
{
    if (!is_readable($path . '/options.php')) {
        return [];
    }
    if (!function_exists('blocksy_manager')) {
        return bly_theme_inactive_error();
    }
    $registry = blocksy_manager()->builder;
    if ($registry === null) {
        return bly_schema_error('the theme builder registry object is not initialized');
    }

    try {
        $options = $registry->get_options_for($builder, ['id' => $item_id, 'path' => $path]);
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: loading options for builder item "%s" threw: %s',
            $item_id,
            $e->getMessage(),
        ));
        return bly_schema_error(sprintf(
            'the option schema for builder item "%s" could not be loaded (details in the server error log)',
            $item_id,
        ));
    }

    $out = [];
    bly_walk_options($out, $options, []);
    return $out;
}

/**
 * Compact agent-facing summary of one item's option schema: id, control
 * type, label, a size-capped default, and (when declared) a capped choice
 * list — enough to compose a valid edit-builder-item call. Capped at
 * BLY_BUILDER_MAX_ITEM_OPTIONS rows; truncation is signaled via the
 * `options_truncated` flag the caller emits.
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @return list<array<string, mixed>>
 */
function bly_builder_item_option_summary(array $index): array
{
    $out = [];
    foreach ($index as $row) {
        if (count($out) >= BLY_BUILDER_MAX_ITEM_OPTIONS) {
            break;
        }
        /** @var mixed $default */
        $default = $row['option']['value'] ?? null;
        $encoded = wp_json_encode($default);
        if (is_string($encoded) && strlen($encoded) > BLY_BUILDER_MAX_OPTION_DEFAULT_BYTES) {
            $default = sprintf('[default omitted: %d bytes JSON-encoded]', strlen($encoded));
        }

        $summary = [
            'id' => $row['id'],
            'type' => $row['type'],
            'label' => $row['label'],
            'default' => $default,
        ];

        /** @var mixed $choices */
        $choices = $row['option']['choices'] ?? null;
        $typed = bly_choice_vocabulary($row);
        if (is_array($choices) && $typed !== null) {
            $values = array_map('strval', $typed);
            $capped = array_slice($values, offset: 0, length: BLY_BUILDER_MAX_ITEM_CHOICES);
            $labels = array_intersect_key(bly_choice_labels($choices), array_flip($capped));
            if (count($values) > BLY_BUILDER_MAX_ITEM_CHOICES) {
                $capped[] = sprintf('[+%d more choices omitted]', count($values) - BLY_BUILDER_MAX_ITEM_CHOICES);
            }
            $summary['choices'] = $capped;
            if ($labels !== []) {
                // Object cast: numeric-string keys ("16") coerce to int
                // array keys, which would JSON-encode a sequential map as
                // a bare array instead of the value=>label object.
                $summary['choice_labels'] = (object) $labels;
            }
        }

        $out[] = $summary;
    }
    return $out;
}

/**
 * In-lock TOCTOU re-check of every written item setting whose choice
 * vocabulary is DYNAMIC (bly_choice_source — the nav-menu picker, the
 * content-block picker), run as the write path's `$recheck` step on the
 * FINAL builder value: after the pre_set_theme_mod filter window (a
 * filter may have emptied the menu / deleted the block as a side effect
 * while returning the value unchanged, or rewritten a setting), before
 * the ownership/persist tail. The item's options.php is re-loaded fresh
 * ONCE per write (the same loader discovery and gate 1 use) and each
 * dynamic setting's value AS IT SITS IN THE FINAL VALUE (`$final_values`,
 * extracted by bly_builder_final_item_values and intersected with the
 * keys this write set) is re-verified against the fresh vocabulary
 * through bly_recheck_dynamic_choice. Static settings cost nothing — no
 * reload happens at all when none of the written keys is dynamic. Only
 * the keys this write set are checked: settings persisted earlier were
 * validated at their own write time and must not fail an unrelated
 * write. Returns the first `bly_choice_stale` (or schema) error, or null
 * when every value still holds.
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $option_index
 *     The request-start item schema the values were validated against.
 * @param array<string, mixed> $final_values Setting key => the value
 *     about to be persisted, restricted to the keys this write set.
 */
function bly_builder_recheck_dynamic_settings(
    string $builder,
    string $type,
    string $item_path,
    array $option_index,
    array $final_values,
): ?WP_Error {
    /** @var array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>|WP_Error|null $fresh_index */
    $fresh_index = null;
    $reload = static function () use ($builder, $type, $item_path, &$fresh_index): array|WP_Error {
        $fresh_index ??= bly_builder_item_option_index($builder, $type, $item_path);
        return $fresh_index;
    };
    /** @var mixed $final */
    foreach ($final_values as $key => $final) {
        if (!isset($option_index[$key])) {
            continue;
        }
        $stale = bly_recheck_dynamic_choice($option_index[$key], $final, $reload);
        if ($stale !== null) {
            return $stale;
        }
    }
    return null;
}

/**
 * The settings of `$instance` inside section `$section_id` of a complete
 * (post-filter) builder value, restricted to the keys of `$written` —
 * the map bly_builder_recheck_dynamic_settings re-verifies. A written
 * key the filter dropped from the final value has nothing to re-check
 * (the persist verification rejects a rewritten value anyway); [] when
 * the value, section or entry is not there at all.
 *
 * @param array<string, mixed> $written Setting key => value this write set.
 * @return array<string, mixed>
 */
function bly_builder_final_item_values(mixed $final, string $section_id, string $instance, array $written): array
{
    if (!is_array($final)) {
        return [];
    }
    /** @var mixed $section */
    foreach (is_array($final['sections'] ?? null) ? $final['sections'] : [] as $section) {
        if (!is_array($section) || ($section['id'] ?? null) !== $section_id) {
            continue;
        }
        /** @var array<string, mixed> */
        return array_intersect_key(bly_builder_item_values($section, $instance) ?? [], $written);
    }
    return [];
}

// ---------------------------------------------------------------------------
// Gate 2 for the whole builder value
// ---------------------------------------------------------------------------

/**
 * Run a complete builder value through Blocksy's own ct-header-builder /
 * ct-footer-builder sanitizer (blocksy_sanitize_builder_value via
 * blocksy_validate_for) — the exact gate a customizer save applies, which
 * HTML-sanitizes the text/copyright item HTML fields. Fails closed like
 * every gate-2 use: if the sanitizer cannot run, the write is refused.
 *
 * @param array<array-key, mixed> $value
 * @return array<array-key, mixed>|WP_Error
 */
function bly_builder_gate2(string $builder, array $value): array|WP_Error
{
    $index = bly_option_index_or_error();
    if ($index instanceof WP_Error) {
        return $index;
    }
    $mod_id = bly_builder_mod_id($builder);
    if (!isset($index[$mod_id])) {
        return bly_schema_error(sprintf('the %s option is missing from the resolved option schema', $mod_id));
    }
    /** @var mixed $sanitized */
    $sanitized = bly_apply_blocksy_sanitizer($index[$mod_id], $value);
    if ($sanitized instanceof WP_Error) {
        return $sanitized;
    }
    if (!is_array($sanitized)) {
        return bly_error('bly_write_failed', sprintf(
            'Blocksy\'s own builder sanitizer returned a non-structural value for %s — refusing to persist it. Nothing was written.',
            $mod_id,
        ));
    }
    return $sanitized;
}
