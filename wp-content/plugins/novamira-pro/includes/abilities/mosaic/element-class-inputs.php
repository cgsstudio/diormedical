<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The four class inputs shared by mosaic-add-element, mosaic-edit-element and
 * mosaic-edit-component-instance-override. Each maps onto exactly one Mosaic
 * wire shape (see the ELEMENT WIRE SHAPES section of class-model.php):
 *
 *  - `css_classes`    → `data.cssClasses` `[{className, uuid}]`, REPLACED. Foreign
 *                       (non-Mosaic) class strings only; a Mosaic-owned name is
 *                       refused because it would render with no CSS.
 *  - `classes`        → `data.style.universalClasses` `{rootID: attachedID}`,
 *                       MERGED by root key.
 *  - `detach_classes` → removes root keys from that same map.
 *  - `variant`        → `data.style.variant` `"<variantID>[/<subClassID>]"`,
 *                       REPLACED, validated against the element type.
 *
 * They replace the former `class_names` shortcut, which wrote Mosaic Classes
 * into `data.cssClasses` — the mechanism that renders without CSS on the
 * current Mosaic class model.
 */

/**
 * @return array<string, mixed>
 */
function mo_css_classes_input_property_schema(): array
{
    return [
        'type' => 'array',
        'items' => ['type' => 'string'],
        'description' => 'Class names that come from OUTSIDE Mosaic — for example a theme or plugin stylesheet — written as-is to the element\'s class attribute. Never use this for Classes created in Mosaic\'s Class system or for Variants: Mosaic only emits a Class\'s CSS when it is attached through `classes`, so a Mosaic class name here would render with no styles, and it is refused with `mo_mosaic_class_in_css_classes`. Each name must be a plain CSS class (letters, digits, `-`, `_`; not starting with a digit). Replaces the element\'s whole list: `[]` clears it; omit to keep it.',
    ];
}

/**
 * @return array<string, mixed>
 */
function mo_classes_input_property_schema(): array
{
    return [
        'type' => 'array',
        'items' => ['type' => 'string'],
        'description' => 'Mosaic Classes or sub classes to ATTACH, each given by UUID or by the class name it emits (e.g. `card`, or `card--large` for a sub class) — find them with mosaic-list-classes / mosaic-get-class. Mosaic keeps one entry per Class: attaching a sub class of a Class that is already attached switches that entry to the new sub class, while every other attached Class is kept. Mosaic emits the CSS of attached Classes and prints their class names on the element. Remove one with `detach_classes`.',
    ];
}

/**
 * @return array<string, mixed>
 */
function mo_detach_classes_input_property_schema(): array
{
    return [
        'type' => 'array',
        'items' => ['type' => 'string'],
        'description' => 'Mosaic Classes to DETACH, each given by the UUID or emitted name of the Class or of any of its sub classes; the Class\'s whole entry is removed from the element. Runs before `classes`, so detaching and attaching in one call swaps them. Detaching a Class the element does not have is a no-op.',
    ];
}

/**
 * @return array<string, mixed>
 */
function mo_variant_input_property_schema(): array
{
    return [
        'type' => ['string', 'null'],
        'description' => 'The element\'s Variant — Mosaic\'s built-in style base for its element type (a button can be a Button, a Link or a Badge) — optionally narrowed to one of that Variant\'s sub classes. Pass a Variant UUID or class name (e.g. `m-link`), or a variant sub class UUID or emitted name (e.g. `m-button--primary`); mosaic-list-variants(element_type) lists what an element type accepts. Replaces the element\'s current Variant; `null` or `""` resets it to the element type\'s default. A Variant the element type does not support is refused with `mo_unsupported_variant`.',
    ];
}

/**
 * Apply the four class inputs to an element data blob.
 *
 * `$raw_data` is the caller's own `data` payload: when it carries a native
 * `cssClasses` list, the Mosaic-owned-name guard runs on it too, so the guard
 * cannot be sidestepped through the Mosaic-native shape. Values already stored
 * on the element are never re-checked — editing an old page must not fail
 * because of classes an earlier write put there.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed> $input
 * @param array<string, mixed> $raw_data
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_element_class_inputs(
    array $data,
    array $input,
    array $raw_data,
    string $theme_id,
    string $element_type,
): array|WP_Error {
    $keys = ['css_classes', 'classes', 'detach_classes', 'variant'];
    $native_css = array_key_exists('cssClasses', $raw_data);
    if (array_intersect($keys, array_keys($input)) === [] && !$native_css) {
        return $data;
    }
    $err = mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $index = mo_class_index($theme_id);
    $owned = mo_mosaic_owned_class_names($index);
    $guard = $native_css ? mo_guard_css_class_records($raw_data['cssClasses'], $owned, field: 'data.cssClasses') : null;
    if ($guard !== null) {
        return $guard;
    }
    $data = array_key_exists('css_classes', $input)
        ? mo_apply_css_classes_input($data, $input['css_classes'], $owned)
        : $data;
    if ($data instanceof WP_Error) {
        return $data;
    }
    return mo_apply_element_style_inputs($data, $input, $index, $element_type);
}

/**
 * Apply `detach_classes` / `classes` / `variant` to `data.style`, leaving every
 * other style key untouched.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed> $input
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_element_style_inputs(array $data, array $input, array $index, string $element_type): array|WP_Error
{
    /** @var array<string, mixed> $style */
    $style = is_array($data['style'] ?? null) ? $data['style'] : [];
    $style = mo_apply_class_attachments($style, $input, $index);
    if ($style instanceof WP_Error) {
        return $style;
    }
    $style = array_key_exists('variant', $input)
        ? mo_apply_variant_input($style, $input['variant'], $index, $element_type)
        : $style;
    if ($style instanceof WP_Error) {
        return $style;
    }
    unset($data['style']);
    if ($style !== []) {
        $data['style'] = $style;
    }
    return $data;
}

/**
 * Normalize `css_classes` into Mosaic's `[{className, uuid}]` records. The
 * record uuid is only a per-record id Mosaic never reads for CSS, so an
 * existing record keeps its uuid when its className is kept, and new ones get
 * a fresh UUID.
 *
 * @param array<string, mixed>              $data
 * @param array<string, 'classes'|'variant'> $owned
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_css_classes_input(array $data, mixed $css_classes, array $owned): array|WP_Error
{
    $names = mo_read_css_class_names($css_classes);
    if ($names instanceof WP_Error) {
        return $names;
    }
    $existing = mo_css_class_record_ids($data['cssClasses'] ?? null);
    $records = [];
    foreach ($names as $name) {
        $err = mo_owned_css_class_error($name, $owned, field: 'css_classes');
        if ($err !== null) {
            return $err;
        }
        $records[] = ['className' => $name, 'uuid' => $existing[$name] ?? mo_uuid()];
    }
    unset($data['cssClasses']);
    if ($records !== []) {
        $data['cssClasses'] = $records;
    }
    return $data;
}

/**
 * Validate `css_classes` against Mosaic's own className grammar
 * (ValidatorClassName: /^[a-zA-Z_-][a-zA-Z0-9_-]*$/) and dedupe it.
 *
 * @return list<string>|WP_Error
 */
function mo_read_css_class_names(mixed $css_classes): array|WP_Error
{
    if (!is_array($css_classes)) {
        return mo_error('mo_invalid_input', __(
            '`css_classes` must be an array of class-name strings.',
            domain: 'novamira-pro',
        ));
    }
    $names = [];
    /** @var mixed $name */
    foreach ($css_classes as $name) {
        $clean = is_string($name) ? trim($name) : '';
        if (preg_match('/^[a-zA-Z_-][a-zA-Z0-9_-]*$/', $clean) !== 1) {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: %s: rejected entry, JSON-encoded. */
                __(
                    '`css_classes` entry %s is not a plain CSS class name (letters, digits, `-`, `_`, not starting with a digit).',
                    domain: 'novamira-pro',
                ),
                (string) wp_json_encode($name),
            ));
        }
        $names[$clean] = true;
    }
    return array_keys($names);
}

/**
 * className → record uuid of the element's current `cssClasses` records.
 *
 * @return array<string, string>
 */
function mo_css_class_record_ids(mixed $records): array
{
    $ids = [];
    /** @var mixed $record */
    foreach (is_array($records) ? $records : [] as $record) {
        if (!is_array($record) || !is_string($record['className'] ?? null) || !is_string($record['uuid'] ?? null)) {
            continue;
        }
        $ids[$record['className']] = $record['uuid'];
    }
    return $ids;
}

/**
 * Guard a Mosaic-native `cssClasses` list the caller sent in `data`.
 * Dynamic-code className objects are left alone — they are not class names
 * until render time.
 *
 * @param array<string, 'classes'|'variant'> $owned
 */
function mo_guard_css_class_records(mixed $records, array $owned, string $field): ?WP_Error
{
    /** @var mixed $record */
    foreach (is_array($records) ? $records : [] as $record) {
        $name = is_array($record) && is_string($record['className'] ?? null) ? trim($record['className']) : '';
        $err = $name === '' ? null : mo_owned_css_class_error($name, $owned, $field);
        if ($err !== null) {
            return $err;
        }
    }
    return null;
}

/**
 * @param array<string, 'classes'|'variant'> $owned
 */
function mo_owned_css_class_error(string $name, array $owned, string $field): ?WP_Error
{
    $target = $owned[$name] ?? null;
    if ($target === null) {
        return null;
    }
    $message = $target === 'classes'
        ? __(
            '`%1$s` is a Mosaic Class, not a foreign CSS class. Mosaic only emits a Class\'s CSS when it is attached, so in `%2$s` it would render without styles. Attach it with `classes` instead (and remove it from `%2$s`).',
            domain: 'novamira-pro',
        )
        : __(
            '`%1$s` is a Mosaic Variant class, not a foreign CSS class. Select it with `variant` instead of listing it in `%2$s`.',
            domain: 'novamira-pro',
        );
    return mo_error('mo_mosaic_class_in_css_classes', sprintf($message, $name, $field));
}

/**
 * Apply `detach_classes` then `classes` to `style.universalClasses`, keyed by
 * root Class UUID (see class-model.php).
 *
 * @param array<string, mixed> $style
 * @param array<string, mixed> $input
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_class_attachments(array $style, array $input, array $index): array|WP_Error
{
    if (!array_key_exists('classes', $input) && !array_key_exists('detach_classes', $input)) {
        return $style;
    }
    $map = mo_detach_class_refs(
        mo_attached_class_map($style['universalClasses'] ?? null),
        $input['detach_classes'] ?? [],
        $index,
    );
    if ($map instanceof WP_Error) {
        return $map;
    }
    $map = mo_attach_class_refs($map, $input['classes'] ?? [], $index);
    if ($map instanceof WP_Error) {
        return $map;
    }
    unset($style['universalClasses']);
    if ($map !== []) {
        $style['universalClasses'] = $map;
    }
    return $style;
}

/**
 * The element's current `{rootID: attachedID}` map, lowercased.
 *
 * @return array<string, string>
 */
function mo_attached_class_map(mixed $stored): array
{
    $map = [];
    /** @var mixed $attached */
    foreach (is_array($stored) ? $stored : [] as $root => $attached) {
        if (!is_string($attached)) {
            continue;
        }
        $map[strtolower((string) $root)] = strtolower($attached);
    }
    return $map;
}

/**
 * @return list<string>|WP_Error
 */
function mo_read_class_refs(mixed $refs, string $field): array|WP_Error
{
    $out = [];
    /** @var mixed $ref */
    foreach (is_array($refs) ? $refs : [null] as $ref) {
        if (!is_string($ref) || trim($ref) === '') {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: %s: input field name. */
                __('`%s` must be an array of non-empty strings.', domain: 'novamira-pro'),
                $field,
            ));
        }
        $out[] = trim($ref);
    }
    return $out;
}

/**
 * @param array<string, string> $map
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array<string, string>|WP_Error
 */
function mo_detach_class_refs(array $map, mixed $refs, array $index): array|WP_Error
{
    $list = $refs === [] ? [] : mo_read_class_refs($refs, field: 'detach_classes');
    if ($list instanceof WP_Error) {
        return $list;
    }
    foreach ($list as $ref) {
        // A root key already on the element can always be detached, even when
        // its Class no longer resolves (deleted, or from another theme).
        $resolved = isset($map[strtolower($ref)])
            ? ['root' => strtolower($ref), 'attached' => '']
            : mo_resolve_class_ref($index, $ref);
        if ($resolved instanceof WP_Error) {
            return $resolved;
        }
        unset($map[$resolved['root']]);
    }
    return $map;
}

/**
 * @param array<string, string> $map
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array<string, string>|WP_Error
 */
function mo_attach_class_refs(array $map, mixed $refs, array $index): array|WP_Error
{
    $list = $refs === [] ? [] : mo_read_class_refs($refs, field: 'classes');
    if ($list instanceof WP_Error) {
        return $list;
    }
    $roots_in_call = [];
    foreach ($list as $ref) {
        $resolved = mo_resolve_class_ref($index, $ref);
        if ($resolved instanceof WP_Error) {
            return $resolved;
        }
        if (isset($roots_in_call[$resolved['root']])) {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: 1: first reference, 2: second reference. */
                __(
                    '`classes` names two entries of the same Class (`%1$s` and `%2$s`). An element holds one entry per Class — pass only the Class or the one sub class you want.',
                    domain: 'novamira-pro',
                ),
                $roots_in_call[$resolved['root']],
                $ref,
            ));
        }
        $roots_in_call[$resolved['root']] = $ref;
        $map[$resolved['root']] = $resolved['attached'];
    }
    return $map;
}

/**
 * Apply `variant` to `style.variant`: resolve, validate against the element
 * type's supported set, and write the single `<variantID>[/<subClassID>]`
 * string. An empty value removes the key, which Mosaic renders as the element
 * type's default Variant.
 *
 * @param array<string, mixed> $style
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_variant_input(array $style, mixed $variant, array $index, string $element_type): array|WP_Error
{
    unset($style['variant']);
    if ($variant === null || $variant === '') {
        return $style;
    }
    if (!is_string($variant)) {
        return mo_error('mo_invalid_input', __('`variant` must be a string or null.', domain: 'novamira-pro'));
    }
    $resolved = mo_resolve_variant_ref($index, $variant);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    $supported = mo_element_type_variants($element_type);
    if ($supported instanceof WP_Error) {
        return $supported;
    }
    if (!in_array($resolved['variant'], $supported['variants'], strict: true)) {
        return mo_unsupported_variant_error($variant, $element_type, $supported['variants']);
    }
    $style['variant'] = $resolved['sub_class'] === ''
        ? $resolved['variant']
        : $resolved['variant'] . '/' . $resolved['sub_class'];
    return $style;
}

/**
 * @param list<string> $allowed_ids
 */
function mo_unsupported_variant_error(string $variant, string $element_type, array $allowed_ids): WP_Error
{
    $catalog = mo_variant_catalog();
    $allowed = [];
    foreach ($allowed_ids as $id) {
        $allowed[] = ($catalog[$id]['name'] ?? $id) . ' (`' . ($catalog[$id]['class_name'] ?? $id) . '`)';
    }
    return mo_error('mo_unsupported_variant', sprintf(
        /* translators: 1: requested variant, 2: element type, 3: comma-separated list of allowed variants. */
        __(
            'Variant `%1$s` is not available on a `%2$s` element — Mosaic would ignore it and render the default Variant. Allowed: %3$s. See mosaic-list-variants.',
            domain: 'novamira-pro',
        ),
        $variant,
        $element_type,
        implode(', ', $allowed),
    ));
}
