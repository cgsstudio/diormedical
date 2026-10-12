<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Element-schema helpers shared by bricks-list-elements and the validator.
 *
 * Bricks registers every element twice: a bare registration (class + name +
 * label) at boot, and a full "load" that runs the element's whole control
 * lifecycle (base controls before, element controls, base controls after,
 * third-party filters). Only the loaded registry entry carries the real
 * control set — including the base-injected shared style controls, which
 * differ between the layout elements (section/container/block/div: `_direction`,
 * `_rowGap`, `_columnGap`, `_gridGap`, …) and every other element
 * (`_flexDirection`, `_gap`, …). Everything here reads the loaded entry, so the
 * catalog and the validator describe what Bricks actually accepts.
 */

/**
 * Layout-family keys registered only by the layout elements (section,
 * container, block, div) and by elements that opt in themselves.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_LAYOUT_FAMILY_KEYS = ['_direction', '_rowGap', '_columnGap', '_gridGap'];

/**
 * Flex-item family keys the base class injects on every non-layout element.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_ITEM_FAMILY_KEYS = ['_flexDirection', '_gap'];

/**
 * `_`-prefixed keys Bricks stores on elements that are not controls: global
 * class references, interactions, element conditions and the builder's
 * "hidden" flag. They must never be reported as unknown.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_NON_CONTROL_UNDERSCORE_KEYS = ['_cssGlobalClasses', '_interactions', '_conditions', '_hidden'];

/**
 * Returns Bricks' fully loaded registry entry for an element name (class,
 * label, controls, controlGroups, …), running the element's load lifecycle
 * on first access exactly the way Bricks does before rendering. Null when
 * Bricks is not active or the element is not registered.
 *
 * @return array<string, mixed>|null
 */
function bricks_loaded_element_entry(string $name): ?array
{
    /** @var array<string, array<string, mixed>|null> $cache */
    static $cache = [];

    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

    return $cache[$name] = bricks_compute_loaded_element_entry($name);
}

/**
 * Uncached core of {@see bricks_loaded_element_entry()}.
 *
 * @return array<string, mixed>|null
 */
function bricks_compute_loaded_element_entry(string $name): ?array
{
    if ($name === '' || bricks_resolve_element_class($name) === null) {
        return null;
    }

    if (!method_exists(\Bricks\Elements::class, 'get_element')) {
        return null;
    }

    try {
        /** @var mixed $entry */
        $entry = \Bricks\Elements::get_element(['name' => $name]);
    } catch (\Throwable) {
        return null;
    }

    if (!is_array($entry) || !is_array($entry['controls'] ?? null)) {
        return null;
    }

    /** @var array<string, mixed> $entry */
    return $entry;
}

/**
 * The loaded control definitions of an element, keyed by control key.
 *
 * @return array<string, array<string, mixed>>|null
 */
function bricks_loaded_element_controls(string $name): ?array
{
    $entry = bricks_loaded_element_entry($name);
    if ($entry === null) {
        return null;
    }

    /** @var mixed $raw */
    $raw = $entry['controls'];
    if (!is_array($raw)) {
        return null;
    }

    /** @var array<string, array<string, mixed>> $controls */
    $controls = [];
    /** @var mixed $control */
    foreach ($raw as $key => $control) {
        if (!is_array($control)) {
            continue;
        }
        /** @var array<string, mixed> $definition */
        $definition = [];
        /** @var mixed $value */
        foreach ($control as $field => $value) {
            $definition[(string) $field] = $value;
        }
        $controls[(string) $key] = $definition;
    }

    return $controls;
}

/**
 * Whether an element accepts child elements (Bricks' `nestable` flag on the
 * element class). Null when the element cannot be introspected.
 */
function bricks_element_is_nestable(string $name): ?bool
{
    /** @var array<string, bool|null> $cache */
    static $cache = [];

    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

    $element_class = bricks_resolve_element_class($name);
    if ($element_class === null) {
        return $cache[$name] = null;
    }

    try {
        $instance = new $element_class();
        $nestable = $instance->nestable === true;
    } catch (\Throwable) {
        return $cache[$name] = null;
    }

    return $cache[$name] = $nestable;
}

/**
 * Which shared layout-control family the element exposes, derived from its
 * loaded controls (never from a hard-coded element list):
 *  - "layout": the container layout controls `_rowGap` / `_columnGap` /
 *    `_gridGap` (+ `_direction`), which Bricks gives only to its layout
 *    elements — section, container, block, div on a stock install, plus any
 *    element a third party adds through Bricks' layout-element filter;
 *  - "flex_item": the base `_flexDirection` / `_gap` controls every other
 *    element receives. An element that defines its own control under a
 *    layout-looking key (post-navigation's `_direction`, back-to-top's
 *    `_gap`) is still a flex item — that key is element-specific, not the
 *    layout family, and it validates because it IS in the loaded controls;
 *  - "none": no layout controls at all (slot).
 * Null when the element cannot be introspected.
 */
function bricks_element_control_family(string $name): ?string
{
    $controls = bricks_loaded_element_controls($name);
    if ($controls === null) {
        return null;
    }

    $has_layout = array_key_exists('_rowGap', $controls) || array_key_exists('_gridGap', $controls);
    $has_item = array_key_exists('_flexDirection', $controls) || array_key_exists('_gap', $controls);

    return match (true) {
        $has_layout && !$has_item => 'layout',
        $has_item => 'flex_item',
        $has_layout => 'layout',
        default => 'none',
    };
}

/**
 * Controls of an element whose stored value is a list, from the loaded
 * control set: a multi-value select (`multiple`) stores a list of option
 * keys, a repeater a list of item objects. Bricks iterates these values when
 * it renders, so a bare scalar breaks the page instead of being ignored.
 *
 * @return array<string, array{kind: 'strings'|'objects', example: string}>|null
 *         control key => list kind + an example entry; null when the element
 *         cannot be introspected
 */
function bricks_element_list_controls(string $name): ?array
{
    /** @var array<string, array<string, array{kind: 'strings'|'objects', example: string}>|null> $cache */
    static $cache = [];

    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

    $controls = bricks_loaded_element_controls($name);
    if ($controls === null) {
        return $cache[$name] = null;
    }

    $list_controls = [];
    foreach ($controls as $key => $control) {
        if (($control['multiple'] ?? false) === true) {
            $options = is_array($control['options'] ?? null) ? array_keys($control['options']) : [];
            $list_controls[$key] = [
                'kind' => 'strings',
                'example' => $options !== [] ? (string) $options[0] : 'value',
            ];
            continue;
        }
        if (($control['type'] ?? null) === 'repeater') {
            $list_controls[$key] = ['kind' => 'objects', 'example' => bricks_repeater_item_example($control)];
        }
    }

    return $cache[$name] = $list_controls;
}

/**
 * Example item for a repeater control, built from its first field keys
 * (`{"type": "…", "label": "…"}`), or a generic object placeholder.
 *
 * @param array<string, mixed> $control
 */
function bricks_repeater_item_example(array $control): string
{
    $fields = is_array($control['fields'] ?? null) ? array_keys($control['fields']) : [];
    $keys = array_slice(array_map('strval', $fields), offset: 0, length: 2);
    if ($keys === []) {
        return '{…}';
    }

    return '{' . implode(', ', array_map(static fn(string $key): string => '"' . $key . '": "…"', $keys)) . '}';
}

/**
 * The `_`-prefixed (shared style) controls of a loaded element, compacted and
 * annotated with value_format hints. This is the real base-injected set, so
 * a layout element lists `_direction`/`_rowGap`/… and a heading lists
 * `_flexDirection`/`_gap`.
 *
 * @return array<string, array<string, mixed>>|null
 */
function bricks_element_shared_controls(string $name): ?array
{
    $controls = bricks_loaded_element_controls($name);
    if ($controls === null) {
        return null;
    }

    $shared = [];
    foreach ($controls as $key => $control) {
        if ($key === '' || $key[0] !== '_') {
            continue;
        }
        $shared[$key] = bricks_compact_control(bricks_annotate_control($control));
    }

    return $shared;
}

/**
 * Advisory replacement for a `_`-prefixed key that is not a control of the
 * given element, when the key belongs to the other layout family or is a
 * common misspelling of a real base control. Null when there is no
 * family-based suggestion.
 *
 * @param array<string, array<string, mixed>> $controls Loaded controls of the element.
 */
function bricks_underscore_key_suggestion(string $key, array $controls): ?string
{
    $aliases = [
        '_maxWidth' => '_widthMax',
        '_minWidth' => '_widthMin',
        '_maxHeight' => '_heightMax',
        '_minHeight' => '_heightMin',
    ];
    if (array_key_exists($key, $aliases)) {
        return $aliases[$key];
    }

    $has_layout_family = array_key_exists('_rowGap', $controls);
    $has_item_family = array_key_exists('_flexDirection', $controls);

    if ($has_layout_family) {
        return match ($key) {
            '_flexDirection' => '_direction',
            '_gap' => '_rowGap / _columnGap (or _gridGap for grid layouts)',
            default => null,
        };
    }

    if ($has_item_family) {
        return match ($key) {
            '_direction' => '_flexDirection',
            '_rowGap', '_columnGap', '_gridGap' => '_gap',
            '_gridTemplateColumns',
            '_gridTemplateRows',
                => 'a layout element (container/block/div) as the grid parent — items do not register grid template controls',
            default => null,
        };
    }

    return null;
}

/**
 * Control types whose OFF state is the ABSENCE of the setting, not a stored
 * false.
 *
 * Bricks' builder writes a ticked checkbox as a truthy value and DELETES the
 * key when it is unticked, and its rendering code asks isset() / ! empty()
 * about those keys. A stored `false` is therefore a state the builder itself
 * can never produce, and one that reads as ON wherever isset() is the test —
 * which is how a slider written with autoplay: false autoplayed anyway.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_PRESENCE_CONTROL_TYPES = ['checkbox'];

/**
 * Settings Bricks stores and tests by presence that are NOT declared as
 * controls, so the control scan cannot find them. Kept deliberately short:
 * each one is a key the builder writes itself and reads with isset().
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_PRESENCE_SETTING_KEYS = ['hasLoop'];

/**
 * Whether writing `false` to this setting would be read as ON.
 *
 * @param array<array-key, mixed> $controls the loaded control set of the target
 */
function bricks_setting_is_presence_toggle(array $controls, string $key): bool
{
    [$base] = bricks_split_setting_key($key);
    if (in_array($base, NOVAMIRA_BRICKS_PRESENCE_SETTING_KEYS, strict: true)) {
        return true;
    }
    if (!is_array($controls[$base] ?? null)) {
        return false;
    }
    /** @var array<string, mixed> $control */
    $control = $controls[$base];

    return in_array((string) ($control['type'] ?? ''), NOVAMIRA_BRICKS_PRESENCE_CONTROL_TYPES, strict: true);
}

/**
 * Turns an explicit `false` on a presence-tested setting into the absence
 * Bricks reads as OFF, and says so.
 *
 * Removing rather than refusing is the same decision the global settings take
 * for a toggle whose off state is the missing key: it is what the agent asked
 * for, and it is the only OFF the builder can express. Refusing would leave
 * the setting ON with no way to turn it off through this ability.
 *
 * @param array<array-key, mixed> $settings
 * @param array<string, mixed>    $controls
 * @param list<string>            $warnings
 * @return array<array-key, mixed>
 */
function bricks_apply_presence_toggle_offs(array $settings, array $controls, string $target, array &$warnings): array
{
    $removed = [];
    foreach (array_keys($settings) as $key) {
        $key = (string) $key;
        if (($settings[$key] ?? null) === false && bricks_setting_is_presence_toggle($controls, $key)) {
            unset($settings[$key]);
            $removed[] = $key;
            continue;
        }
        if (!is_array($settings[$key] ?? null)) {
            continue;
        }
        $settings[$key] = bricks_strip_nested_presence_offs($settings[$key], $controls, $key, $removed);
    }
    if ($removed !== []) {
        $warnings[] = sprintf(
            'Turned %s off on %s by removing the key(s), not by storing false: Bricks asks whether these settings are present, so a stored false reads as ON and the feature stays enabled. The stored value now matches what the builder writes when the box is unticked. Read it back with novamira/bricks-get-element (or novamira/bricks-get-settings for page settings) — the key is absent, which is off.',
            implode(', ', $removed),
            $target,
        );
    }

    return $settings;
}

/**
 * Applies the same rule one level down, where the definitions allow it.
 *
 * A REPEATER declares its rows through the control's own `fields`, so each
 * row is checked against those definitions — the same evidence the top level
 * uses. Anything else nested is passed through untouched: without a control
 * declaring the polarity there is no evidence, and this rule acts only on
 * evidence.
 *
 * @param array<array-key, mixed> $value
 * @param array<string, mixed>    $controls
 * @param list<string>            $removed
 * @return array<array-key, mixed>
 */
function bricks_strip_nested_presence_offs(array $value, array $controls, string $key, array &$removed): array
{
    [$base] = bricks_split_setting_key($key);

    if (!is_array($controls[$base] ?? null) || !is_array($controls[$base]['fields'] ?? null)) {
        return $value;
    }
    $fields = $controls[$base]['fields'];
    foreach (array_keys($value) as $row_key) {
        if (!is_array($value[$row_key] ?? null)) {
            continue;
        }
        $value[$row_key] = bricks_strip_row_presence_offs(
            $value[$row_key],
            $fields,
            sprintf('%s[%s]', $base, (string) $row_key),
            $removed,
        );
    }

    return $value;
}

/**
 * One repeater row against that repeater's own field definitions.
 *
 * @param array<array-key, mixed> $row
 * @param array<array-key, mixed> $fields
 * @param list<string>            $removed
 * @return array<array-key, mixed>
 */
function bricks_strip_row_presence_offs(array $row, array $fields, string $path, array &$removed): array
{
    foreach (array_keys($row) as $field) {
        $field = (string) $field;
        if (($row[$field] ?? null) !== false || !bricks_setting_is_presence_toggle($fields, $field)) {
            continue;
        }
        unset($row[$field]);
        $removed[] = $path . '.' . $field;
    }

    return $row;
}

/**
 * Splits a Bricks settings key into its base control key and the optional
 * suffix. Bricks' key grammar is `<key>[:<breakpoint>][:<pseudo>]` — the
 * desktop value is the unsuffixed key, a breakpoint value adds `:<breakpoint>`
 * (`_padding:mobile_landscape`), a pseudo-state value adds `:<pseudo>`
 * (`_background:hover`), and both combine as `_background:mobile_landscape:hover`.
 *
 * @return array{0: string, 1: string}
 */
function bricks_split_setting_key(string $key): array
{
    $colon = strpos($key, needle: ':');
    if ($colon === false) {
        return [$key, ''];
    }

    return [substr($key, offset: 0, length: $colon), substr($key, offset: $colon + 1)];
}
