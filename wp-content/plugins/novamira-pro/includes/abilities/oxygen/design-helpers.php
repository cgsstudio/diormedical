<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Helpers for the Oxygen design tokens — variables, global classes and
 * global settings. Each token blob lives in a `wp_options` entry the
 * Soflyy engine writes through `Breakdance\Data\set_global_option`, which
 * auto-prefixes the option name with `oxygen_` and json-encodes the value
 * once on write / json-decodes once on read.
 *
 *   variables_json_string             → OxygenVariable[]   (stored as ARRAY)
 *   variables_collections_json_string → string[]           (stored as ARRAY)
 *   oxy_selectors_json_string         → OxygenSelector[]    (stored as ARRAY)
 *   oxy_selectors_collections_json_string → string[]        (stored as ARRAY)
 *   global_settings_json_string       → nested settings    (stored as STRING)
 *
 * ENCODING CONTRACT (verified against the native save paths — this bit
 * bites hard). `set_global_option` json-encodes ONCE internally, so the
 * shape you hand it decides the final storage:
 *  - variables + collections: the native `plugin/variables/save.php` hands
 *    an ARRAY, so we pass arrays as-is (single-encoded). The reader accepts
 *    both array and string.
 *  - selectors + their collections: native `saveSelectors()` decodes the
 *    transaction JSON and hands ARRAYS to `set_global_option`.
 *  - global settings: native `plugin/data/save.php` hands a JSON STRING, so
 *    we `wp_json_encode` first (double-encoded). Passing an array instead makes
 *    the renderer cast it to string and breaks the cache.
 *
 * Reads go through `ox_decode_design_json_option`, which handles both the
 * array shape (variables) and the string shape (classes/settings) by
 * decoding once more when the decoded outer value is still a string.
 */

/**
 * Normalize a value returned by `Breakdance\Data\get_global_option`.
 * A correctly-stored option arrives as an array; a legacy double-encoded
 * value arrives as a JSON string that we decode once more so old data
 * still reads (and self-heals on the next write).
 *
 * @param mixed $value
 * @return array<int|string, mixed>
 */
function ox_decode_design_json_option(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode(json: $value, associative: true);
    if (!is_array($decoded)) {
        return [];
    }
    return $decoded;
}

/**
 * @return list<array<string, mixed>>
 */
function ox_read_variables(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'variables_json_string');
    $decoded = ox_decode_design_json_option($raw);
    $out = [];
    /** @var mixed $item */
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        /** @var array<string, mixed> $item */
        $out[] = $item;
    }
    return $out;
}

/**
 * The variable types Oxygen 6's builder decoder + CSS macro actually
 * understand (`plugin/variables/template/macro.twig`). Anything else — e.g.
 * `typography`, `shadow`, `size` — is never decoded; the renderer falls
 * through to a raw `--name: <value>` and emits "Array to string conversion"
 * (rendering `--name: Array`) when the value is an object.
 *
 * @return list<string>
 */
function ox_variable_types(): array
{
    return ['text', 'number', 'unit', 'color', 'font_family', 'image_url'];
}

/**
 * Whether a variable value can render for its type. Mirrors the native macro's
 * unwrapping (`value.style`, then for `color` `.value`, for `image_url`
 * `.url`); the final resolved value must be a scalar or null. An array value
 * would otherwise be cast to string (`--name: Array`) or, for `font_family`,
 * reach the native `process_font()` which FATALS on a nested-array descriptor
 * (`Cannot access offset of type array on array`) AND poisons the variable
 * store, because `saveVariables()` persists the option before regenerating CSS.
 * So every type — font_family included — requires a scalar (a font stack is a
 * plain string like "Inter, sans-serif") or null.
 */
function ox_variable_value_renderable(string $type, mixed $value): bool
{
    /** @var mixed $resolved */
    $resolved = is_array($value) && array_key_exists('style', $value) ? $value['style'] : $value;
    if ($type === 'color' && is_array($resolved) && array_key_exists('value', $resolved)) {
        /** @var mixed $resolved */
        $resolved = $resolved['value'];
    }
    if ($type === 'image_url' && is_array($resolved) && array_key_exists('url', $resolved)) {
        /** @var mixed $resolved */
        $resolved = $resolved['url'];
    }
    return $resolved === null || is_scalar($resolved);
}

/**
 * Validate a variable's `type` + `value` pair against Oxygen's real contract.
 * Returns a WP_Error to bail with, or null when the pair is renderable.
 *
 * @param mixed $value
 */
function ox_validate_variable_type_value(string $type, mixed $value): ?\WP_Error
{
    if (!in_array($type, ox_variable_types(), strict: true)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: comma-separated list of valid variable types */
            __(
                '`type` must be one of: %s. Oxygen does not decode other kinds, so they render broken.',
                domain: 'novamira-pro',
            ),
            implode(', ', ox_variable_types()),
        ));
    }
    if (!ox_variable_value_renderable($type, $value)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: variable type slug */
            __(
                '`value` is not compatible with type `%s`; it must resolve to a scalar, otherwise Oxygen renders `--<name>: Array`.',
                domain: 'novamira-pro',
            ),
            $type,
        ));
    }
    return null;
}

/**
 * Persist the variables through Oxygen's native `saveVariables()` entry
 * point, which — unlike a raw `set_global_option` — also records a global
 * revision, regenerates the CSS cache, AND persists the collection registry
 * (`variables_collections_json_string`). The builder derives variable groups
 * from that collection option, so a variable created in a brand-new
 * collection would otherwise be invisible. We assemble the collection list
 * here (existing ∪ any referenced by the variables) because native
 * saveVariables stores whatever list it is handed.
 *
 * TRANSACTION INTEGRITY. Native `saveVariables` writes the COLLECTIONS option
 * FIRST (unconditionally) and the VARIABLES option SECOND (gated) with no
 * rollback, so a hook that blocks only the variables write leaves the new
 * collection registered with no variable behind it — an orphan the integration
 * has no ability to clean up. We therefore snapshot both options, and after the
 * native save confirm every intended variable actually landed (id + fields); if
 * any did not, we restore BOTH options to their snapshot and regenerate the CSS,
 * so a blocked/altered write leaves no orphan collection and reports
 * `oxygen_write_not_applied` instead of a partial success.
 *
 * Returns true on success, or a WP_Error when the API is unavailable, the
 * payload cannot be encoded, or the write did not persist as intended.
 *
 * @param list<array<string, mixed>> $variables
 */
function ox_write_variables(array $variables): bool|\WP_Error
{
    if (!function_exists('Breakdance\\Variables\\saveVariables')) {
        return ox_error('oxygen_invalid_input', __('The Oxygen variables API is unavailable.', domain: 'novamira-pro'));
    }
    // Snapshot both options BEFORE the write so an incomplete transaction can be
    // rolled back wholesale (no orphan collection left behind an error).
    $before_variables = ox_read_variables();
    $before_collections = ox_read_variable_collections();

    $collections = $before_collections;
    foreach ($variables as $variable) {
        /** @var mixed $collection */
        $collection = $variable['collection'] ?? null;
        if (is_string($collection) && $collection !== '' && !in_array($collection, $collections, strict: true)) {
            $collections[] = $collection;
        }
    }
    $payload = wp_json_encode(['variables' => $variables, 'collections' => $collections]);
    if ($payload === false) {
        return ox_error('oxygen_invalid_input', __('Failed to encode the variables.', domain: 'novamira-pro'));
    }
    \Breakdance\Variables\saveVariables($payload);

    // Observed-state on the WHOLE native transaction. Exact comparison catches
    // both asymmetric failures: collections committed while variables were
    // blocked, and variables committed while the collection registry was
    // blocked. On either mismatch restore and RE-VERIFY both snapshots.
    $observed_variables = ox_read_variables();
    $observed_collections = ox_read_variable_collections();
    if (!ox_state_matches($variables, $observed_variables) || !ox_state_matches($collections, $observed_collections)) {
        \Breakdance\Data\set_global_option(field_name: 'variables_json_string', value: $before_variables);
        \Breakdance\Data\set_global_option(field_name: 'variables_collections_json_string', value: $before_collections);
        ox_regenerate_global_css();
        $retained_variables = ox_read_variables();
        $retained_collections = ox_read_variable_collections();
        $restored =
            ox_state_matches($before_variables, $retained_variables)
            && ox_state_matches($before_collections, $retained_collections);
        $message = $restored
            ? 'Both the variables and collection registry were restored and re-verified.'
            : sprintf(
                'Rollback was partial: %d variable(s) and %d collection(s) remain (state fingerprint %s).',
                count($retained_variables),
                count($retained_collections),
                ox_state_fingerprint([$retained_variables, $retained_collections]),
            );
        return ox_error(
            'oxygen_write_not_applied',
            sprintf(
                __('The Oxygen variables transaction did not persist as requested. %s', domain: 'novamira-pro'),
                $message,
            ),
            [
                'rollback' => [
                    'restored' => $restored,
                    'variables' => count($retained_variables),
                    'collections' => count($retained_collections),
                ],
            ],
        );
    }
    return true;
}

/**
 * @return list<string>
 */
function ox_read_variable_collections(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'variables_collections_json_string');
    $decoded = ox_decode_design_json_option($raw);
    $out = [];
    /** @var mixed $item */
    foreach ($decoded as $item) {
        if (!is_string($item)) {
            continue;
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function ox_read_global_classes(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'oxy_selectors_json_string');
    $decoded = ox_decode_design_json_option($raw);
    $out = [];
    /** @var mixed $item */
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        /** @var array<string, mixed> $item */
        $out[] = $item;
    }
    return $out;
}

/** @return list<string> */
function ox_read_global_class_collections(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'oxy_selectors_collections_json_string');
    $decoded = ox_decode_design_json_option($raw);
    $out = [];
    /** @var mixed $item */
    foreach ($decoded as $item) {
        if (!is_string($item)) {
            continue;
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * Persist Oxygen's native selectors transaction. Oxygen 6 does NOT consume the
 * Breakdance `breakdance_classes_json_string` store: it stores selector rows at
 * `oxy_selectors_json_string`, their collection registry separately, and binds
 * selector ids from element `properties.meta.classes`.
 *
 * The native saver writes collections before selectors, records selector
 * revisions and regenerates CSS. We pre-render only changed rows, write once,
 * read back both options, render the observed rows, and compensate both options
 * on any storage/render mismatch. All this remains inside one ability call, so
 * the agent receives a compact result rather than full before/after blobs.
 *
 * @param list<array<string, mixed>> $classes
 */
function ox_write_global_classes(array $classes): bool|\WP_Error
{
    if (!function_exists('Breakdance\\BreakdanceOxygen\\Selectors\\saveSelectors')) {
        return ox_error('oxygen_invalid_input', __(
            'The native Oxygen selectors API is unavailable.',
            domain: 'novamira-pro',
        ));
    }
    $before_classes = ox_read_global_classes();
    $before_collections = ox_read_global_class_collections();
    $prepared = ox_prepare_global_class_transaction($before_classes, $before_collections, $classes);
    if ($prepared instanceof \WP_Error) {
        return $prepared;
    }
    $collections = $prepared['collections'];
    $expected_css = $prepared['expected_css'];
    \Breakdance\BreakdanceOxygen\Selectors\saveSelectors($prepared['payload']);
    $observed_classes = ox_read_global_classes();
    $observed_collections = ox_read_global_class_collections();
    $observed_changed = ox_changed_global_class_rows($before_classes, $observed_classes);
    $observed_css = ox_render_global_class_rows($observed_changed);
    $css_matches =
        is_string($observed_css)
        && hash_equals(ox_state_fingerprint($expected_css), ox_state_fingerprint($observed_css));
    if (
        !ox_state_matches($classes, $observed_classes)
        || !ox_state_matches($collections, $observed_collections)
        || !$css_matches
    ) {
        \Breakdance\Data\set_global_option(field_name: 'oxy_selectors_json_string', value: $before_classes);
        \Breakdance\Data\set_global_option(
            field_name: 'oxy_selectors_collections_json_string',
            value: $before_collections,
        );
        ox_regenerate_global_css();
        $retained_classes = ox_read_global_classes();
        $retained_collections = ox_read_global_class_collections();
        $restored =
            ox_state_matches($before_classes, $retained_classes)
            && ox_state_matches($before_collections, $retained_collections);
        $message = $restored
            ? 'The previous selectors and collections were restored and re-verified.'
            : sprintf(
                'Rollback was partial: %d selector(s), %d collection(s), state fingerprint %s.',
                count($retained_classes),
                count($retained_collections),
                ox_state_fingerprint([$retained_classes, $retained_collections]),
            );
        return ox_error(
            'oxygen_write_not_applied',
            sprintf(
                __(
                    'The native Oxygen selector transaction did not produce the requested persisted/rendered state. %s',
                    domain: 'novamira-pro',
                ),
                $message,
            ),
            [
                'rollback' => [
                    'restored' => $restored,
                    'selectors' => count($retained_classes),
                    'collections' => count($retained_collections),
                ],
            ],
        );
    }
    return true;
}

/**
 * Validate and encode a selector transaction before any mutation.
 *
 * @param list<array<string, mixed>> $before_classes
 * @param list<string>               $before_collections
 * @param list<array<string, mixed>> $classes
 * @return array{collections: list<string>, expected_css: string, payload: string}|\WP_Error
 */
function ox_prepare_global_class_transaction(
    array $before_classes,
    array $before_collections,
    array $classes,
): array|\WP_Error {
    $collections = $before_collections;
    $ids = [];
    foreach ($classes as $class) {
        $validation = ox_validate_global_class_row($class);
        if ($validation instanceof \WP_Error) {
            return $validation;
        }
        $id = (string) $class['id'];
        if (isset($ids[$id])) {
            return ox_error('oxygen_invalid_input', sprintf(
                __('Duplicate Oxygen selector id `%s`.', domain: 'novamira-pro'),
                $id,
            ));
        }
        $ids[$id] = true;
        /** @var mixed $collection */
        $collection = $class['collection'] ?? '';
        if (is_string($collection) && $collection !== '' && !in_array($collection, $collections, strict: true)) {
            $collections[] = $collection;
        }
    }
    $effect_error = ox_validate_global_class_render_effects($before_classes, $classes);
    if ($effect_error instanceof \WP_Error) {
        return $effect_error;
    }
    $expected_css = ox_render_global_class_rows(ox_changed_global_class_rows($before_classes, $classes));
    if ($expected_css instanceof \WP_Error) {
        return $expected_css;
    }
    $payload = wp_json_encode(['selectors' => $classes, 'collections' => $collections]);
    if ($payload === false) {
        return ox_error('oxygen_invalid_input', __(
            'Failed to encode the Oxygen selectors transaction.',
            domain: 'novamira-pro',
        ));
    }
    return ['collections' => $collections, 'expected_css' => $expected_css, 'payload' => $payload];
}

/**
 * Selector rows changed or added between two snapshots. Deleted rows need no
 * positive CSS render assertion; their absence is proven by exact read-back.
 *
 * @param list<array<string, mixed>> $before
 * @param list<array<string, mixed>> $after
 * @return list<array<string, mixed>>
 */
function ox_changed_global_class_rows(array $before, array $after): array
{
    $before_by_id = [];
    foreach ($before as $row) {
        if (!isset($row['id']) || !is_string($row['id'])) {
            continue;
        }
        $before_by_id[$row['id']] = $row;
    }
    $changed = [];
    foreach ($after as $row) {
        $id = isset($row['id']) && is_string($row['id']) ? $row['id'] : '';
        if ($id === '' || !isset($before_by_id[$id]) || $before_by_id[$id] !== $row) {
            $changed[] = $row;
        }
    }
    return $changed;
}

/** @param list<array<string, mixed>> $rows */
function ox_render_global_class_rows(array $rows): string|\WP_Error
{
    if ($rows === []) {
        return '';
    }
    if (
        !function_exists('Breakdance\\BreakdanceOxygen\\Selectors\\getCssAndFontsForSelector')
        || !function_exists('Breakdance\\BreakdanceOxygen\\Selectors\\cssTemplate')
        || !function_exists('Breakdance\\BreakdanceOxygen\\Selectors\\propertyPathsToWhitelistInFlatProps')
        || !function_exists('Breakdance\\BreakdanceOxygen\\Selectors\\getSelectorsCacheStore')
    ) {
        return ox_error('oxygen_invalid_input', __(
            'The Oxygen selector renderer is unavailable.',
            domain: 'novamira-pro',
        ));
    }
    try {
        $template = \Breakdance\BreakdanceOxygen\Selectors\cssTemplate();
        $whitelist = \Breakdance\BreakdanceOxygen\Selectors\propertyPathsToWhitelistInFlatProps();
        $breakpoints = \Breakdance\Config\Breakpoints\get_breakpoints();
        $cache = \Breakdance\BreakdanceOxygen\Selectors\getSelectorsCacheStore();
        $variables = function_exists('Breakdance\\Variables\\getVariables') ? \Breakdance\Variables\getVariables() : [];
        $css = '';
        foreach ($rows as $row) {
            $parsed = \Breakdance\BreakdanceOxygen\Selectors\parseVariablesInSelector($row, $variables);
            $selector = \Breakdance\BreakdanceOxygen\Selectors\replaceAmpersandInSelector($parsed);
            [$rendered] = \Breakdance\BreakdanceOxygen\Selectors\getCssAndFontsForSelector(
                $selector,
                $template,
                $whitelist,
                $breakpoints,
                $cache,
            );
            $css .= $rendered;
        }
        return $css;
    } catch (\Throwable $e) {
        return ox_error('oxygen_invalid_input', sprintf(
            __('Oxygen could not render the selector properties: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }
}

/**
 * Before any option write, prove that every added/changed selector-property
 * leaf changes the renderer's output. This catches valid-looking persisted
 * values with the wrong compound/responsive shape (the engine silently drops
 * them) without returning full CSS to the agent.
 *
 * @param list<array<string, mixed>> $before
 * @param list<array<string, mixed>> $after
 */
function ox_validate_global_class_render_effects(array $before, array $after): ?\WP_Error
{
    $before_by_id = ox_selector_rows_by_id($before);
    foreach ($after as $row) {
        $id = isset($row['id']) && is_string($row['id']) ? $row['id'] : '';
        $error = ox_validate_global_class_row_render_effects($row, $before_by_id[$id] ?? []);
        if ($error instanceof \WP_Error) {
            return $error;
        }
    }
    return null;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, array<string, mixed>>
 */
function ox_selector_rows_by_id(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!isset($row['id']) || !is_string($row['id'])) {
            continue;
        }
        $out[$row['id']] = $row;
    }
    return $out;
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed> $previous_row
 */
function ox_validate_global_class_row_render_effects(array $row, array $previous_row): ?\WP_Error
{
    /** @var array<string, mixed> $properties */
    $properties = isset($row['properties']) && is_array($row['properties']) ? $row['properties'] : [];
    /** @var array<string, mixed> $previous_properties */
    $previous_properties = isset($previous_row['properties']) && is_array($previous_row['properties'])
        ? $previous_row['properties']
        : [];
    $current = ox_flatten_selector_property_leaves($properties);
    $previous = ox_flatten_selector_property_leaves($previous_properties);
    // @mago-expect analysis:mixed-assignment
    foreach ($current as $path => $value) {
        if (array_key_exists($path, $previous) && $previous[$path] === $value) {
            continue;
        }
        $error = ox_global_class_property_render_effect_error($row, $properties, $path);
        if ($error instanceof \WP_Error) {
            return $error;
        }
    }
    return null;
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed> $properties
 */
function ox_global_class_property_render_effect_error(array $row, array $properties, string $path): ?\WP_Error
{
    $full_css = ox_render_global_class_rows([$row]);
    if ($full_css instanceof \WP_Error) {
        return $full_css;
    }
    // Walk the leaf and then its ancestors, stopping BEFORE the property-group
    // root. A leaf of a COMPOUND control can be load-bearing without being
    // individually observable: selectors.twig renders `gap` as
    // `gap.row.style == gap.column.style ? row : row ~ ' ' ~ column`, so
    // dropping `layout.gap.row.style` alone still emits `gap:20px` once
    // whitespace is normalised. Removing the control itself (`layout.gap`) does
    // change the CSS, which is the honest question to ask. The walk stops at
    // depth 2 so an invented leaf can never be rescued by its group: removing
    // `typography` would change the CSS through its legitimate siblings.
    $segments = explode('.', $path);
    while (count($segments) >= 2) {
        $without = $row;
        $without['properties'] = ox_unset_selector_property_path($properties, $segments);
        $without_css = ox_render_global_class_rows([$without]);
        if ($without_css instanceof \WP_Error) {
            return $without_css;
        }
        if ($full_css !== $without_css) {
            return null;
        }
        array_pop($segments);
    }
    return ox_error('oxygen_invalid_input', sprintf(
        __(
            'Selector property `%s` produced no observable CSS. Its responsive/compound value shape is invalid or its required companion control is missing; use oxygen-get-global-class-schema.',
            domain: 'novamira-pro',
        ),
        $path,
    ));
}

/**
 * Flatten a selector's properties to `path => leaf` for the render-effect
 * check. Every scalar leaf is returned: the check that follows asks the ENGINE
 * whether the leaf renders, so this must not pre-filter against a control list
 * (the Breakdance-mode control list is the wrong vocabulary for Oxygen).
 *
 * @param array<array-key, mixed> $properties
 * @return array<string, mixed>
 */
function ox_flatten_selector_property_leaves(array $properties, string $prefix = ''): array
{
    $out = [];
    /** @var mixed $value */
    foreach ($properties as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $path = $prefix === '' ? $key : $prefix . '.' . $key;
        if (is_array($value) && $value !== []) {
            $out = array_merge($out, ox_flatten_selector_property_leaves($value, $path));
            continue;
        }
        $out[$path] = $value;
    }
    return $out;
}

/**
 * @param array<array-key, mixed> $properties
 * @param list<string>            $segments
 * @return array<array-key, mixed>
 */
function ox_unset_selector_property_path(array $properties, array $segments): array
{
    $key = $segments[0] ?? null;
    if (!is_string($key)) {
        return $properties;
    }
    if (count($segments) === 1) {
        unset($properties[$key]);
        return $properties;
    }
    if (isset($properties[$key]) && is_array($properties[$key])) {
        $properties[$key] = ox_unset_selector_property_path($properties[$key], array_slice($segments, offset: 1));
    }
    return $properties;
}

/**
 * Validate one native Oxygen selector row and its property paths against the
 * live class-selector control tree before it reaches the native saver.
 *
 * @param array<string, mixed> $row
 */
function ox_validate_global_class_row(array $row): ?\WP_Error
{
    foreach (['id', 'name', 'type', 'collection'] as $key) {
        if (!isset($row[$key]) || !is_string($row[$key])) {
            return ox_error('oxygen_invalid_input', sprintf(
                __('Selector `%s` must be a string.', domain: 'novamira-pro'),
                $key,
            ));
        }
    }
    $id = (string) $row['id'];
    $name = (string) $row['name'];
    $type = (string) $row['type'];
    if ($id === '' || trim($name) === '') {
        return ox_error('oxygen_invalid_input', __('Selector id and name must be non-empty.', domain: 'novamira-pro'));
    }
    if (!in_array($type, ['class', 'custom'], strict: true)) {
        return ox_error('oxygen_invalid_input', __(
            'Selector type must be `class` or `custom`.',
            domain: 'novamira-pro',
        ));
    }
    if ($type === 'class' && ox_class_name_has_unsafe_chars($name)) {
        return ox_error('oxygen_invalid_input', __(
            'Class names must be a single safe CSS token.',
            domain: 'novamira-pro',
        ));
    }
    if (!isset($row['properties']) || !is_array($row['properties'])) {
        return ox_error('oxygen_invalid_input', __('Selector properties must be an object.', domain: 'novamira-pro'));
    }
    if (!isset($row['children']) || !is_array($row['children'])) {
        return ox_error('oxygen_invalid_input', __('Selector children must be an array.', domain: 'novamira-pro'));
    }
    $property_error = ox_validate_global_class_properties($row['properties']);
    return $property_error instanceof \WP_Error ? $property_error : null;
}

/** @param array<array-key, mixed> $properties */
function ox_validate_global_class_properties(array $properties): ?\WP_Error
{
    if (array_is_list($properties) && $properties !== []) {
        return ox_error('oxygen_invalid_input', __(
            'Selector properties must be an object, not a list.',
            domain: 'novamira-pro',
        ));
    }
    // NO control-list allowlist here, deliberately. In Oxygen mode selectors are
    // rendered by `plugin/breakdance-oxygen/selectors.twig`, whose vocabulary is
    // NOT the one `\Breakdance\ClassesSelectors\controls()` exposes (that is the
    // Breakdance-mode list, and Oxygen ships no PHP equivalent — its selector
    // panel is defined in the builder JS). Validating against it rejected every
    // property that actually works — `spacing.spacing.padding`,
    // `borders.border_radius.all`, `layout.gap.row`, `layout.flex_align`,
    // `background.background_color`, `custom_css.custom_css` — while accepting
    // several that render nothing. The authority is therefore the ENGINE: every
    // leaf goes through the render-diff in
    // ox_validate_global_class_render_effects(), which keeps a property only if
    // removing it changes the emitted CSS. An invented path renders nothing and
    // is still refused, one row later, with a message that says so.
    // @mago-expect analysis:mixed-assignment
    foreach ($properties as $key => $value) {
        if (!is_string($key)) {
            return ox_error('oxygen_invalid_input', __(
                'Selector property keys must be strings.',
                domain: 'novamira-pro',
            ));
        }
        if (is_array($value) && array_is_list($value) && $value !== []) {
            return ox_error('oxygen_invalid_input', sprintf(
                __('Selector property group `%s` must be an object.', domain: 'novamira-pro'),
                $key,
            ));
        }
    }
    return null;
}

/**
 * @return array<string, mixed>
 */
function ox_read_global_settings(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'global_settings_json_string');
    $decoded = ox_decode_design_json_option($raw);
    if ($decoded === []) {
        return [];
    }
    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Top-level section slugs from Oxygen's live Global Settings control registry.
 * Base Oxygen deliberately disables these controls; an empty list therefore
 * means this surface is not writable unless an extension re-enables it.
 *
 * @return list<string>
 */
function ox_global_settings_allowed_sections(): array
{
    if (!function_exists('Breakdance\\GlobalSettings\\globalSettingsControlSections')) {
        return [];
    }
    /** @var mixed $sections */
    $sections = \Breakdance\GlobalSettings\globalSettingsControlSections();
    if (!is_array($sections)) {
        return [];
    }
    $out = [];
    /** @var mixed $section */
    foreach ($sections as $section) {
        if (!is_array($section) || !isset($section['slug']) || !is_string($section['slug'])) {
            continue;
        }
        $out[] = $section['slug'];
    }
    return array_values(array_unique($out));
}

/**
 * Persisted through Oxygen's native `save_global_settings()`, which compares
 * the typography presets and clears+regenerates all element CSS caches when
 * they change (per-post element CSS depends on those presets), records a
 * global-settings revision, and regenerates the global cache — none of which
 * a raw `set_global_option` does. Same double-encode contract (inner JSON
 * string). After saving we read the stored inner string back and require an
 * exact match. Returns true on success, or a WP_Error.
 *
 * @param array<string, mixed> $settings
 */
function ox_write_global_settings(array $settings): bool|\WP_Error
{
    if (!function_exists('Breakdance\\Data\\save_global_settings')) {
        return ox_error('oxygen_invalid_input', __(
            'The Oxygen global-settings API is unavailable.',
            domain: 'novamira-pro',
        ));
    }
    $json = wp_json_encode($settings);
    if ($json === false) {
        return ox_error('oxygen_invalid_input', __('Failed to encode the global settings.', domain: 'novamira-pro'));
    }
    /** @var mixed $before */
    $before = \Breakdance\Data\get_global_option(field_name: 'global_settings_json_string');
    \Breakdance\Data\save_global_settings($json);
    /** @var mixed $stored */
    $stored = \Breakdance\Data\get_global_option(field_name: 'global_settings_json_string');
    if ($stored !== $json) {
        \Breakdance\Data\set_global_option(field_name: 'global_settings_json_string', value: $before);
        ox_regenerate_global_css();
        /** @var mixed $retained */
        $retained = \Breakdance\Data\get_global_option(field_name: 'global_settings_json_string');
        $restored = $retained === $before;
        return ox_error(
            'oxygen_write_not_applied',
            sprintf(
                __('The Oxygen global-settings write did not persist as requested. %s', domain: 'novamira-pro'),
                $restored
                    ? 'The previous settings were restored and re-verified.'
                    : sprintf('Rollback was partial; retained state fingerprint: %s.', ox_state_fingerprint($retained)),
            ),
            ['rollback' => ['restored' => $restored]],
        );
    }
    return true;
}

/**
 * Force the engine to regenerate the cached global CSS file after a
 * design-token write. Without this, front-end pages keep serving the
 * pre-edit CSS until the user opens the builder, hits a native save,
 * or the per-post cache rebuilds lazily on next render. The cache
 * regen entrypoint is internal but stable across Oxygen 6.x — guarded
 * with `function_exists` so a future rename does not fatal the
 * integration.
 */
function ox_regenerate_global_css(): void
{
    if (function_exists('\\Breakdance\\Render\\generateCacheForGlobalSettings')) {
        \Breakdance\Render\generateCacheForGlobalSettings();
    }
}

/**
 * Locate a variable by its id. Returns null when missing.
 *
 * @param list<array<string, mixed>> $variables
 * @return array{0: int, 1: array<string, mixed>}|null
 */
function ox_find_variable(array $variables, string $id): ?array
{
    foreach ($variables as $i => $variable) {
        if (isset($variable['id']) && (string) $variable['id'] === $id) {
            return [$i, $variable];
        }
    }
    return null;
}

/**
 * Whether any of a variable's string fields contains the literal two-character
 * substring `{}`. Oxygen's native `saveVariables` runs
 * `str_replace('{}', 'null', $json)` on the WHOLE payload string before decoding
 * (to coerce empty JS objects to null), so a `{}` inside a string value / label /
 * collection / id is silently rewritten to `null` — the value cannot round-trip
 * and the observed-state check would (correctly) see a mismatch. We reject it up
 * front with a clear cause instead of letting it surface as a misleading
 * "a storage hook altered it" write_not_applied. Checks only the fields a caller
 * sets as strings; object/array values (color `{value:…}`) never encode the bare
 * `{}` substring.
 *
 * @param array<string, mixed> $variable
 */
function ox_variable_has_literal_braces(array $variable): bool
{
    foreach (['id', 'label', 'collection', 'value'] as $key) {
        /** @var mixed $field */
        $field = $variable[$key] ?? null;
        if (is_string($field) && str_contains($field, '{}')) {
            return true;
        }
    }
    return false;
}

/**
 * Build the `oxygen_invalid_input` error for a variable field carrying `{}`.
 */
function ox_variable_braces_error(): \WP_Error
{
    return ox_error('oxygen_invalid_input', __(
        'A variable field contains the literal `{}`, which Oxygen cannot store: its native saver replaces every `{}` with `null` before saving, so the value would not round-trip. Remove the `{}` (or express it differently).',
        domain: 'novamira-pro',
    ));
}

/**
 * Whether a selector row contains the literal two-character substring `{}`.
 * Oxygen's native `saveSelectors` applies the same whole-JSON replacement as
 * `saveVariables`: every `{}` becomes `null` before decoding. This includes
 * custom CSS strings such as `:hover {}` as well as names, collections, object
 * keys, and nested property values. Such a row cannot round-trip unchanged.
 *
 * @param array<string, mixed> $selector
 */
function ox_selector_has_literal_braces(array $selector): bool
{
    return ox_selector_value_has_literal_braces($selector);
}

/** Recursively inspect both JSON object keys and values for `{}`. */
function ox_selector_value_has_literal_braces(mixed $value): bool
{
    if (is_string($value)) {
        return str_contains($value, '{}');
    }
    if (!is_array($value)) {
        return false;
    }
    /** @var mixed $child */
    foreach ($value as $key => $child) {
        if (is_string($key) && str_contains($key, '{}')) {
            return true;
        }
        if (ox_selector_value_has_literal_braces($child)) {
            return true;
        }
    }
    return false;
}

/** Build the up-front error for a selector payload that cannot round-trip. */
function ox_selector_braces_error(): \WP_Error
{
    return ox_error('oxygen_invalid_input', __(
        'A selector field contains the literal `{}`, which Oxygen cannot store: its native saver replaces every `{}` with `null` before saving. Remove the empty CSS block (or express it differently).',
        domain: 'novamira-pro',
    ));
}

/**
 * Compare an intended variable row against the observed (persisted) row over
 * the canonical keys. Returns the name of the first field whose persisted value
 * differs from what was requested, or null when every field matches. Used by
 * create/edit-variable to prove the requested write actually LANDED: a storage
 * hook can keep the row's id (so an existence check passes) while reverting or
 * altering its fields (the reviewer's #222222 request surviving as #111111).
 * `ox_write_variables` stores the array verbatim through the native saver, so a
 * successful write yields byte-identical fields and strict comparison is exact.
 *
 * @param array<string, mixed> $intended
 * @param array<string, mixed> $observed
 */
function ox_variable_row_mismatch(array $intended, array $observed): ?string
{
    foreach (['id', 'cssVariableName', 'label', 'value', 'type', 'collection', 'dynamicData'] as $key) {
        if (($intended[$key] ?? null) !== ($observed[$key] ?? null)) {
            return $key;
        }
    }
    return null;
}

/**
 * Locate a variable by its `cssVariableName`. CSS variables are
 * referenced at render time by name (var(--<cssVariableName>)), so a
 * collision means two records map to the same CSS variable and the
 * later-defined one silently shadows the earlier one in the browser.
 *
 * @param list<array<string, mixed>> $variables
 * @return array{0: int, 1: array<string, mixed>}|null
 */
function ox_find_variable_by_css_name(array $variables, string $css_name): ?array
{
    foreach ($variables as $i => $variable) {
        if (isset($variable['cssVariableName']) && (string) $variable['cssVariableName'] === $css_name) {
            return [$i, $variable];
        }
    }
    return null;
}

/**
 * Locate a global class by its name (class names act as ids).
 *
 * @param list<array<string, mixed>> $classes
 * @return array{0: int, 1: array<string, mixed>}|null
 */
function ox_find_global_class(array $classes, string $name): ?array
{
    foreach ($classes as $i => $class) {
        if (isset($class['name']) && (string) $class['name'] === $name) {
            return [$i, $class];
        }
    }
    return null;
}

/**
 * Locate a native Oxygen selector by its stable id.
 *
 * @param list<array<string, mixed>> $classes
 * @return array{0: int, 1: array<string, mixed>}|null
 */
function ox_find_global_class_by_id(array $classes, string $id): ?array
{
    foreach ($classes as $i => $class) {
        if (isset($class['id']) && (string) $class['id'] === $id) {
            return [$i, $class];
        }
    }
    return null;
}

/** Create a collision-resistant selector id for Oxygen's id-based bindings. */
function ox_make_global_class_id(): string
{
    return 'novamira-selector-' . bin2hex(random_bytes(length: 8));
}

/**
 * Slugify a string into a stable id (used when callers do not supply an
 * explicit id on create). Lowercase, dashes, no whitespace or special
 * chars. A non-slugifiable hint falls back to a cryptographic random
 * suffix so ids stay collision-free on burst load (a clock-based
 * derivative collided ~95% at k=10 000 in Breakdance testing).
 */
function ox_make_id(string $hint): string
{
    $lower = strtolower($hint);
    $slug = preg_replace(pattern: '~[^a-z0-9]+~', replacement: '-', subject: $lower);
    if (!is_string($slug)) {
        $slug = '';
    }
    $slug = trim($slug, characters: '-');
    return $slug !== '' ? $slug : 'var-' . bin2hex(random_bytes(length: 4));
}

/**
 * Whether a caller-supplied `cssVariableName` is a usable CSS custom-property
 * identifier. Oxygen renders it as `--<name>`, so a name with whitespace or
 * other declaration-breaking characters produces an invalid rule (`--bad name:
 * …`) that the browser drops, and a name already starting with `--` would
 * double the prefix. Accepts only ASCII letters / digits / `_` / `-` and no
 * leading `--`. Not applied to the slugified default (which is always safe).
 */
function ox_valid_css_variable_name(string $name): bool
{
    if ($name === '' || str_starts_with($name, '--')) {
        return false;
    }
    // The `D` (dollar-end-only) modifier stops `$` from matching before a
    // trailing newline, so a name like "brand\n" is correctly rejected.
    return preg_match('/^[A-Za-z0-9_-]+$/D', $name) === 1;
}

/**
 * Pull a trimmed non-empty string out of `$input[$key]`, or fall back
 * to the provided default. Cleaner than repeating the isset+is_string+
 * trim chain at every site.
 *
 * @param array<string, mixed> $input
 */
function ox_pick_string(array $input, string $key, string $fallback): string
{
    if (!isset($input[$key]) || !is_string($input[$key])) {
        return $fallback;
    }
    $trimmed = trim($input[$key]);
    return $trimmed !== '' ? $trimmed : $fallback;
}

/**
 * THE OXYGEN-MODE SELECTOR VOCABULARY.
 *
 * Oxygen 6 renders global classes from `plugin/breakdance-oxygen/selectors.twig`,
 * and that template — not `\Breakdance\ClassesSelectors\controls()` — defines
 * what a selector property may be called. The two disagree on most compound
 * controls (`spacing.spacing.padding` vs `spacing.padding`,
 * `borders.border_radius.all` vs `borders.radius`, `layout.gap.row` vs
 * `layout.gap`, `custom_css.custom_css` vs `custom_css.css`), and only the
 * former renders. Oxygen ships no PHP control list for this mode — its design
 * panel lives in the builder JS — so the vocabulary is transcribed here, each
 * row carrying the CSS declaration the template emits for it.
 *
 * `sample` is a COMPLETE, renderable properties fragment, including any
 * companion the template requires (gap and flex alignment only render inside
 * `display:flex`). The smoke suite feeds every sample through the engine and
 * fails if one stops producing CSS, so an Oxygen upgrade that renames a path
 * breaks the tests instead of silently misleading agents.
 *
 * @return list<array{path: string, label: string, css: string, sample: array<string, mixed>}>
 */
function ox_oxygen_selector_controls(): array
{
    return array_merge(
        ox_selector_controls_layout(),
        ox_selector_controls_size(),
        ox_selector_controls_typography(),
        ox_selector_controls_spacing(),
        ox_selector_controls_decoration(),
        ox_selector_controls_effects(),
    );
}

/** A `unit` value as selectors.twig reads it: only `.style` is interpolated. */
function ox_selector_unit(string $value): array
{
    return ['style' => $value];
}

/** @return list<array{path: string, label: string, css: string, sample: array<string, mixed>}> */
function ox_selector_controls_layout(): array
{
    // Gap and flex alignment only render inside the template's
    // `{% if layout.display == 'flex' %}` branch, so the samples carry it.
    $flex = ['display' => 'flex'];
    return [
        [
            'path' => 'properties.layout.display',
            'label' => 'Display',
            'css' => 'display',
            'sample' => ['layout' => ['display' => 'flex']],
        ],
        [
            'path' => 'properties.layout.flex_direction',
            'label' => 'Flex direction',
            'css' => 'flex-flow',
            'sample' => ['layout' => $flex + ['flex_direction' => 'column']],
        ],
        [
            'path' => 'properties.layout.flex_align.primary_axis',
            'label' => 'Justify content',
            'css' => 'justify-content',
            'sample' => ['layout' => $flex + ['flex_align' => ['primary_axis' => 'center']]],
        ],
        [
            'path' => 'properties.layout.flex_align.cross_axis',
            'label' => 'Align items',
            'css' => 'align-items',
            'sample' => ['layout' => $flex + ['flex_align' => ['cross_axis' => 'center']]],
        ],
        [
            'path' => 'properties.layout.gap',
            'label' => 'Gap (row + column)',
            'css' => 'gap',
            'sample' => [
                'layout' => $flex
                    + ['gap' => ['row' => ox_selector_unit('20px'), 'column' => ox_selector_unit('20px')]],
            ],
        ],
    ];
}

/** @return list<array{path: string, label: string, css: string, sample: array<string, mixed>}> */
function ox_selector_controls_size(): array
{
    return [
        [
            'path' => 'properties.size.width',
            'label' => 'Width',
            'css' => 'width',
            'sample' => ['size' => ['width' => ox_selector_unit('100%')]],
        ],
        [
            'path' => 'properties.size.max_width',
            'label' => 'Max width',
            'css' => 'max-width',
            'sample' => ['size' => ['max_width' => ox_selector_unit('1120px')]],
        ],
        [
            'path' => 'properties.size.min_height',
            'label' => 'Min height',
            'css' => 'min-height',
            'sample' => ['size' => ['min_height' => ox_selector_unit('320px')]],
        ],
    ];
}

/** @return list<array{path: string, label: string, css: string, sample: array<string, mixed>}> */
function ox_selector_controls_typography(): array
{
    return [
        [
            'path' => 'properties.typography.color',
            'label' => 'Color',
            'css' => 'color',
            'sample' => ['typography' => ['color' => '#fafafa']],
        ],
        [
            'path' => 'properties.typography.font_family',
            'label' => 'Font family',
            'css' => 'font-family',
            'sample' => ['typography' => ['font_family' => "'Inter',sans-serif"]],
        ],
        [
            'path' => 'properties.typography.font_size',
            'label' => 'Font size',
            'css' => 'font-size',
            'sample' => ['typography' => ['font_size' => ox_selector_unit('1.2rem')]],
        ],
        [
            'path' => 'properties.typography.font_weight',
            'label' => 'Font weight',
            'css' => 'font-weight',
            'sample' => ['typography' => ['font_weight' => '600']],
        ],
        [
            'path' => 'properties.typography.line_height',
            'label' => 'Line height',
            'css' => 'line-height',
            'sample' => ['typography' => ['line_height' => ox_selector_unit('1.6')]],
        ],
        [
            'path' => 'properties.typography.letter_spacing',
            'label' => 'Letter spacing',
            'css' => 'letter-spacing',
            'sample' => ['typography' => ['letter_spacing' => ox_selector_unit('-.02em')]],
        ],
        [
            'path' => 'properties.typography.text_align',
            'label' => 'Text align',
            'css' => 'text-align',
            'sample' => ['typography' => ['text_align' => 'center']],
        ],
        [
            'path' => 'properties.typography.text_transform',
            'label' => 'Text transform',
            'css' => 'text-transform',
            'sample' => ['typography' => ['text_transform' => 'uppercase']],
        ],
    ];
}

/**
 * Spacing is DOUBLE-nested in Oxygen mode: selectors.twig reads
 * `props.spacing.spacing.padding`, not `props.spacing.padding`.
 *
 * @return list<array{path: string, label: string, css: string, sample: array<string, mixed>}>
 */
function ox_selector_controls_spacing(): array
{
    $box = static fn(string $value): array => [
        'top' => ['style' => $value],
        'right' => ['style' => $value],
        'bottom' => ['style' => $value],
        'left' => ['style' => $value],
    ];
    return [
        [
            'path' => 'properties.spacing.spacing.padding',
            'label' => 'Padding',
            'css' => 'padding-*',
            'sample' => ['spacing' => ['spacing' => ['padding' => $box('24px')]]],
        ],
        [
            'path' => 'properties.spacing.spacing.margin',
            'label' => 'Margin',
            'css' => 'margin-*',
            'sample' => ['spacing' => ['spacing' => ['margin' => $box('12px')]]],
        ],
    ];
}

/** @return list<array{path: string, label: string, css: string, sample: array<string, mixed>}> */
function ox_selector_controls_decoration(): array
{
    return [
        [
            'path' => 'properties.background.background_color',
            'label' => 'Background color',
            'css' => 'background-color',
            'sample' => ['background' => ['background_color' => '#0a0a0a']],
        ],
        [
            'path' => 'properties.borders.border_radius',
            'label' => 'Border radius',
            'css' => 'border-radius',
            'sample' => ['borders' => ['border_radius' => ['all' => ox_selector_unit('16px')]]],
        ],
        [
            'path' => 'properties.borders.borders',
            'label' => 'Borders (per side)',
            'css' => 'border-<side>',
            'sample' => [
                'borders' => [
                    'borders' => [
                        'top' => ['width' => ox_selector_unit('1px'), 'style' => 'solid', 'color' => '#27272a'],
                    ],
                ],
            ],
        ],
        // Emitted OUTSIDE the selector block, with `:selector` replaced by the class.
        [
            'path' => 'properties.custom_css.custom_css',
            'label' => 'Custom CSS',
            'css' => 'raw',
            'sample' => ['custom_css' => ['custom_css' => ':selector{letter-spacing:-.01em}']],
        ],
    ];
}

/** @return list<array{path: string, label: string, css: string, sample: array<string, mixed>}> */
function ox_selector_controls_effects(): array
{
    return [
        [
            'path' => 'properties.effects.opacity',
            'label' => 'Opacity',
            'css' => 'opacity',
            'sample' => ['effects' => ['opacity' => '80']],
        ],
        [
            'path' => 'properties.effects.cursor',
            'label' => 'Cursor',
            'css' => 'cursor',
            'sample' => ['effects' => ['cursor' => 'pointer']],
        ],
        [
            'path' => 'properties.effects.box_shadow',
            'label' => 'Box shadow',
            'css' => 'box-shadow',
            'sample' => [
                'effects' => [
                    'box_shadow' => [[
                        'x' => ox_selector_unit('0px'),
                        'y' => ox_selector_unit('1px'),
                        'blur' => ox_selector_unit('3px'),
                        'spread' => ox_selector_unit('0px'),
                        'color' => 'rgba(0,0,0,.4)',
                    ]],
                ],
            ],
        ],
    ];
}
