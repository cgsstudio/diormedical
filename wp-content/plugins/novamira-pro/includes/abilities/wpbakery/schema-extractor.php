<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Converts WPBakery element definitions from WPBMap into the compact schema
 * format used by Novamira — same approach as the Elementor schema extractor.
 *
 * Compact format per controllo:
 *   t    → type abbreviato
 *   opts → valori enum (per dropdown/select)
 *   def  → valore default
 *   if   → condizioni di dipendenza
 *   fields → sub-schema per repeater (param_group)
 */

/**
 * Param types that carry no agent-useful information and are always skipped.
 *
 * @var list<string>
 */
const WPB_SKIP_PARAM_TYPES = [
    'css_editor', // Design Options visual CSS — agents use inline atts instead
    'animation_style', // Animation picker — cosmetic, not structural
    'custom_markup', // Read-only HTML display in the editor panel
];

/**
 * Param names that only exist to mark a shortcode for WPBakery's own
 * plumbing and mean nothing to an agent.
 *
 * @var list<string>
 */
const WPB_NOISE_PARAM_NAMES = [
    'wpb_woo_shortcode', // WooCommerce marker, always 1
];

/**
 * Param types whose control renders one input per option and stores every
 * ticked one in a single comma-joined att. WPBakery reads them back with
 * `explode(',', $value)` — see `vc_checkbox_form_field()`,
 * `vc_posttypes_form_field()` and `vc_taxonomies_form_field()` in
 * `include/params/default_params.php`.
 *
 * @var list<string>
 */
const WPB_MULTI_VALUE_PARAM_TYPES = [
    'checkbox',
    'posttypes',
    'taxonomies',
];

/**
 * Param names that are always skipped regardless of type.
 * These are reserved WPBakery infrastructure keys.
 *
 * @var list<string>
 */
const WPB_SKIP_PARAM_NAMES = [
    'el_id',
    'el_class',
    'css',
    'css_animation',
];

/**
 * Returns a compact schema for a single WPBakery element tag, or null if the
 * tag is not registered.
 *
 * @return array<string, mixed>|null
 */
function wpb_compact_element(string $tag): ?array
{
    $settings = \WPBMap::getShortCode($tag);
    if ($settings === null) {
        return null;
    }

    /** @var list<array<string, mixed>> $params */
    $params = is_array($settings['params'] ?? null) ? $settings['params'] : [];

    $controls = [];
    foreach ($params as $param) {
        $name = (string) ($param['param_name'] ?? '');
        if ($name === '') {
            continue;
        }

        $compact = wpb_compact_param($param);
        if ($compact === null) {
            continue;
        }

        $controls[$name] = $compact;
    }

    /** @var list<string>|string $raw_categories */
    $raw_categories = $settings['category'] ?? [];
    $categories = is_array($raw_categories) ? $raw_categories : [$raw_categories];

    return [
        'tag' => $tag,
        'label' => (string) ($settings['name'] ?? $tag),
        'is_container' => ($settings['is_container'] ?? false) !== false,
        'categories' => array_values(array_filter($categories, static fn(string $c): bool => $c !== '')),
        'controls' => $controls,
    ];
}

/**
 * Converts a single WPBakery param definition to the compact format.
 * Returns null when the param should be skipped.
 *
 * @param array<string, mixed> $param
 * @return array<string, mixed>|null
 */
function wpb_compact_param(array $param): ?array
{
    $type = (string) ($param['type'] ?? '');
    $name = (string) ($param['param_name'] ?? '');

    if (in_array($name, WPB_SKIP_PARAM_NAMES, strict: true)) {
        return null;
    }
    if (in_array($type, WPB_SKIP_PARAM_TYPES, strict: true)) {
        return null;
    }
    if (in_array($name, WPB_NOISE_PARAM_NAMES, strict: true)) {
        return null;
    }
    // A deprecated param is registered only so old content still parses.
    // The element no longer honours it, so it is not offered as a choice.
    if (($param['deprecated'] ?? false) !== false) {
        return null;
    }

    $compact = ['t' => wpb_map_param_type($type)];

    // `hidden` means "no control in the editor panel", not "no meaning".
    // WPBakery 9.0 moved structural atts behind it — vc_column.width and
    // the WooCommerce sku fields among them — so they are exposed, flagged
    // so the agent knows the editor UI offers no way to set them.
    if ($type === 'hidden') {
        $compact['hidden'] = true;
    }

    wpb_apply_param_default($param, $type, $compact);
    wpb_apply_param_options($param, $type, $compact);
    wpb_apply_param_fields($param, $type, $compact);
    wpb_apply_param_dependency($param, $compact);
    wpb_apply_param_shape_hint($type, $compact);

    return $compact;
}

/**
 * Adds a `shape` (composite-value description) and/or `opts` (well-known
 * stock values) hint for param types whose decoded form is not obvious
 * from the raw type name. The set is hard-coded because WPBakery does
 * not expose a stable PHP-side API for these — values were captured
 * from WPBakery 8.x and are stable across the 6.5–8.x range we support.
 *
 * - `vc_link`        → shape of the decoded object the agent must pass.
 * - `font_container` → packed pipe-delimited key:value format reference.
 * - `vc_grid_item`   → opts list of stock template slugs the `item` att
 *                      accepts in addition to a custom vc_grid_item post id.
 *
 * @param array<string, mixed> $compact
 */
function wpb_apply_param_shape_hint(string $type, array &$compact): void
{
    if (wpb_is_link_param_type($type)) {
        $compact['shape'] = 'object {url: string, title?: string, target?: "_blank"|"_self", rel?: string}';
        return;
    }
    if ($type === 'href') {
        $compact['shape'] = 'string — a plain URL. Unlike the `link` type this one stores no title, target or rel.';
        return;
    }
    if ($type === 'column_offset') {
        $compact['shape'] = 'space-separated Bootstrap-style classes, one group per breakpoint (xs, sm, md, lg, xl): `vc_col-<bp>-<1..12>` sets the width at that breakpoint, `vc_col-<bp>-offset-<0..11>` shifts it, `vc_hidden-<bp>` hides it. Example: "vc_col-sm-6 vc_col-lg-offset-2".';
        return;
    }
    if ($type === 'font_container') {
        $compact['shape'] = 'pipe-delimited string of key:value pairs. Keys: tag (h1..h6|p), text_align (left|right|center|justify), font_size (e.g. 24px), line_height, color, letter_spacing.';
        return;
    }
    if ($type === 'vc_grid_item') {
        $compact['opts'] = wpb_grid_item_templates();
        $compact['shape'] = 'string (one of opts) OR the integer post id of a custom Grid Builder template';
        return;
    }
}

/**
 * Populates the 'def' key in the compact array from the param definition.
 *
 * @param array<string, mixed> $param
 * @param array<string, mixed> $compact
 */
function wpb_apply_param_default(array $param, string $type, array &$compact): void
{
    /** @var mixed $def */
    $def = $param['std'] ?? null;
    if ($def === null && $type !== 'dropdown') {
        /** @var mixed $def */
        $def = $param['value'] ?? null;
    }
    if ($def !== null && $def !== '') {
        $compact['def'] = $def;
    }
}

/**
 * Populates 'opts' (and 'def' for dropdowns) in the compact array.
 *
 * @param array<string, mixed> $param
 * @param array<string, mixed> $compact
 */
function wpb_apply_param_options(array $param, string $type, array &$compact): void
{
    if ($type === 'dropdown' && is_array($param['value'] ?? null)) {
        $value_map = $param['value'];
        $compact['opts'] = wpb_normalize_dropdown_opts($value_map);
        /** @var mixed $std */
        $std = $param['std'] ?? null;
        $first = $compact['opts'][0] ?? null;
        $compact['def'] = is_scalar($std) ? $std : $first ?? null;
        return;
    }

    if ($type === 'checkbox' && is_array($param['value'] ?? null)) {
        $value_map = $param['value'];
        $compact['opts'] = wpb_normalize_dropdown_opts($value_map);
        return;
    }

    // button_group is a closed choice list like dropdown, but its value map
    // is keyed the other way round: `[value => ['label' => …, 'title' => …]]`.
    // wpb_normalize_dropdown_opts already falls back to the key for a
    // non-list array value, so the same normalizer covers both shapes.
    // WPBakery 9.0 moved several enums (vc_btn.size, vc_btn.align, …) from
    // dropdown to button_group; without this they carried no opts and every
    // value was accepted.
    if ($type === 'button_group' && is_array($param['value'] ?? null)) {
        $value_map = $param['value'];
        $compact['opts'] = wpb_normalize_dropdown_opts($value_map);
    }
}

/**
 * Normalizes a WPBakery dropdown `value` map into a flat list of valid
 * choice strings. Two shapes show up in the wild:
 *  - `[label => value]` — the standard form. `array_values` is the value.
 *  - `[ [value, label], … ]` — used by loop-style fields (vc_basic_grid
 *    `post_type`, `orderby`, …). The first column is the value, the
 *    second is the human-readable label.
 *
 * The validator does `in_array($att, $opts, strict: true)`, so opts must
 * be a flat list of the actual values. Without this normalization,
 * `post_type=post` was falsely flagged as `invalid_value` because the
 * opts list contained `["post", "Post"]` arrays instead of `"post"`.
 *
 * @param array<int|string, mixed> $value_map
 * @return list<scalar>
 */
function wpb_normalize_dropdown_opts(array $value_map): array
{
    $opts = [];
    /** @var mixed $val */
    foreach ($value_map as $key => $val) {
        if (is_array($val) && $val === array_values($val) && isset($val[0]) && is_scalar($val[0])) {
            $opts[] = $val[0];
            continue;
        }
        // `[['label' => '6', 'value' => 2], …]` — the shape the four grid
        // `element_width` params and WooCommerce's `product_category.category`
        // use. The legal values are the `value` entries, not the array indices.
        if (is_array($val) && array_key_exists('value', $val) && is_scalar($val['value'])) {
            $opts[] = $val['value'];
            continue;
        }
        if (is_scalar($val)) {
            $opts[] = $val;
            continue;
        }
        $opts[] = $key;
    }
    return $opts;
}

/**
 * Populates 'fields' in the compact array for param_group (repeater) params.
 *
 * @param array<string, mixed> $param
 * @param array<string, mixed> $compact
 */
function wpb_apply_param_fields(array $param, string $type, array &$compact): void
{
    if ($type !== 'param_group' || !is_array($param['params'] ?? null)) {
        return;
    }

    $fields = [];
    /** @var list<array<string, mixed>> $sub_params */
    $sub_params = $param['params'];
    foreach ($sub_params as $sub) {
        $sub_name = (string) ($sub['param_name'] ?? '');
        if ($sub_name === '') {
            continue;
        }
        $sub_compact = wpb_compact_param($sub);
        if ($sub_compact !== null) {
            $fields[$sub_name] = $sub_compact;
        }
    }
    $compact['fields'] = $fields;
}

/**
 * Populates 'if' (dependency conditions) in the compact array.
 *
 * @param array<string, mixed> $param
 * @param array<string, mixed> $compact
 */
function wpb_apply_param_dependency(array $param, array &$compact): void
{
    if (!is_array($param['dependency'] ?? null)) {
        return;
    }

    /** @var array<string, mixed> $dep */
    $dep = $param['dependency'];
    $if = [];

    if (array_key_exists('element', $dep)) {
        $if['on'] = (string) $dep['element'];
    }
    if (array_key_exists('value', $dep)) {
        $if['eq'] = $dep['value'];
    }
    if (array_key_exists('value_not_equal_to', $dep)) {
        $if['neq'] = $dep['value_not_equal_to'];
    }
    if (($dep['not_empty'] ?? false) !== false) {
        $if['not_empty'] = true;
    }
    if (($dep['is_empty'] ?? false) !== false) {
        $if['is_empty'] = true;
    }

    if ($if !== []) {
        $compact['if'] = $if;
    }
}

/**
 * Maps a WPBakery param type string to the compact 't' value.
 */
function wpb_map_param_type(string $type): string
{
    return match ($type) {
        'attach_image' => 'image',
        'attach_images' => 'images',
        'param_group' => 'repeater',
        'textarea_html' => 'textarea_html',
        'textarea_raw_html' => 'textarea',
        default => $type,
    };
}

/**
 * The element categories this install actually uses, sorted.
 *
 * Read from the elements themselves rather than WPBMap::getCategories(),
 * which on 9.0.1 registers names no mapped element carries ("deprecated"):
 * every name returned here matches at least one element. WPBakery hands
 * them back already translated into the site locale.
 *
 * @return list<string>
 */
function wpb_element_categories(): array
{
    $seen = [];
    foreach (\WPBMap::getAllShortCodes() as $settings) {
        /** @var list<string>|string $raw */
        $raw = $settings['category'] ?? [];
        foreach (is_array($raw) ? $raw : [$raw] as $cat) {
            if ($cat === '') {
                continue;
            }
            $seen[$cat] = true;
        }
    }

    $names = array_keys($seen);
    sort($names);

    return $names;
}

/**
 * Every param name WPBakery registers for a tag, exposed or not, as a
 * `name => param definition` map. Returns an empty array for an unknown
 * tag, which makes every att unknown — the caller has already resolved
 * the tag by then.
 *
 * @return array<string, array<string, mixed>>
 */
function wpb_registered_param_names(string $tag): array
{
    if ($tag === '') {
        return [];
    }

    $settings = \WPBMap::getShortCode($tag);
    if ($settings === null) {
        return [];
    }

    /** @var list<array<string, mixed>> $params */
    $params = is_array($settings['params'] ?? null) ? $settings['params'] : [];

    $names = [];
    foreach ($params as $param) {
        $name = (string) ($param['param_name'] ?? '');
        if ($name !== '') {
            $names[$name] = $param;
        }
    }

    return $names;
}

/**
 * Validates that an atts array is compatible with a registered element's
 * params schema. Returns arrays of dropped_keys and invalid_values.
 *
 * @param array<string, mixed> $atts
 * @param array<string, mixed> $schema Compact schema from wpb_compact_element().
 * @return array{dropped_keys: list<string>, invalid_values: list<array<string, mixed>>, deprecated_keys: list<string>}
 */
function wpb_validate_atts(array $atts, array $schema): array
{
    /** @var array<string, array<string, mixed>> $controls */
    $controls = is_array($schema['controls'] ?? null) ? $schema['controls'] : [];

    // A key is "unknown" when WPBakery does not register it at all — not
    // when we chose to hide it from the agent. The two sets diverged in
    // WPBakery 9.0, which turned params that carry real meaning into
    // type=hidden: vc_column.width and vc_column_inner.width among them.
    // Validating against $controls alone would reject the writes that build
    // a multi-column layout.
    $registered = wpb_registered_param_names((string) ($schema['tag'] ?? ''));

    $dropped_keys = [];
    $invalid_values = [];
    $deprecated_keys = [];

    foreach (array_keys($atts) as $key) {
        /** @var mixed $att_value */
        $att_value = $atts[$key];

        // Reserved keys always allowed — el_id/el_class/css/css_animation
        // are universal WPBakery atts that don't appear in per-element
        // schemas but are accepted everywhere.
        if (in_array($key, WPB_SKIP_PARAM_NAMES, strict: true)) {
            continue;
        }

        if (!array_key_exists($key, $registered)) {
            $dropped_keys[] = $key;
            continue;
        }

        // WPBakery keeps deprecated params registered so old content still
        // parses, but the element stops honouring them and the enum of valid
        // choices is emptied. Writing one is accepted and then ignored at
        // render time, so it is reported rather than refused.
        if (($registered[$key]['deprecated'] ?? false) !== false) {
            $deprecated_keys[] = $key;
            continue;
        }

        // Registered upstream but deliberately not exposed to the agent:
        // accepted as written, with no enum to check it against.
        if (!array_key_exists($key, $controls)) {
            continue;
        }

        $invalid = wpb_invalid_att_value($key, $att_value, $controls[$key]);
        if ($invalid !== null) {
            $invalid_values[] = $invalid;
        }
    }

    return [
        'dropped_keys' => $dropped_keys,
        'invalid_values' => $invalid_values,
        'deprecated_keys' => $deprecated_keys,
    ];
}

/**
 * Checks one att against its control's enum. Returns the invalid_values
 * entry, or null when the value is acceptable.
 *
 * @param array<string, mixed> $control
 * @return array<string, mixed>|null
 */
function wpb_invalid_att_value(string $key, mixed $att_value, array $control): ?array
{
    $opts = is_array($control['opts'] ?? null) ? $control['opts'] : null;
    if ($opts === null) {
        return null;
    }

    // vc_grid_item params (e.g. vc_basic_grid.item) accept either a
    // stock template slug (in opts) OR a custom vc_grid_item post id.
    // Numeric strings count too — WPBakery serializes the id as a
    // string in shortcode atts.
    if (
        (string) ($control['t'] ?? '') === 'vc_grid_item'
        && (is_int($att_value) || is_string($att_value) && ctype_digit($att_value))
    ) {
        return null;
    }

    if (!is_scalar($att_value)) {
        return null;
    }
    foreach (wpb_att_value_pieces($att_value, (string) ($control['t'] ?? '')) as $needle) {
        if (!wpb_opts_contain($opts, $needle)) {
            return [
                'key' => $key,
                'value' => $att_value,
                'opts' => $opts,
            ];
        }
    }

    return null;
}

/**
 * The guidance that goes with a refused write, matched to the reason it was
 * refused. A dropped key and a value outside the list are fixed in different
 * places, so the guidance points to `invalid_values` only when that list has
 * entries.
 *
 * @param array{dropped_keys: list<string>, invalid_values: list<array<string, mixed>>, deprecated_keys: list<string>} $validation
 */
function wpb_validation_hint(string $tag, array $validation): string
{
    $parts = [];
    if ($validation['dropped_keys'] !== []) {
        $parts[] = sprintf(
            'dropped_keys[] names settings %s does not have, which can mean the setting belongs to another element or was renamed in this WPBakery version.',
            $tag,
        );
    }
    if ($validation['invalid_values'] !== []) {
        $parts[] = 'invalid_values[].opts already lists the valid choices for each of those settings — pick one and retry.';
    }
    $parts[] = sprintf('Call wpbakery-get-element-schema with tag="%s" for the full control set.', $tag);

    return implode(' ', $parts);
}

/**
 * The Grid Builder template slugs this install actually ships, read from
 * WPBakery rather than remembered.
 *
 * Template slugs differ between WPBakery versions (9.0.1 ships 39, under
 * names different from 8.x), so the list is read at runtime.
 *
 * `Vc_Grid_Item` is not always loaded when an ability runs, so the same
 * file it reads is included directly, through the same filter, when the
 * class is not there.
 *
 * @return list<string>
 */
function wpb_grid_item_templates(): array
{
    if (class_exists('Vc_Grid_Item')) {
        return wpb_template_slugs(\Vc_Grid_Item::predefinedTemplates());
    }

    if (!function_exists('vc_path_dir')) {
        return [];
    }
    $file = vc_path_dir('PARAMS_DIR', file: 'vc_grid_item/templates.php');
    if (!is_readable($file)) {
        return [];
    }

    /** @var mixed $templates */
    $templates = apply_filters('vc_grid_item_predefined_templates', include $file);

    return is_array($templates) ? wpb_template_slugs($templates) : [];
}

/**
 * The keys of a template list, as strings.
 *
 * @param array<array-key, mixed> $templates
 * @return list<string>
 */
function wpb_template_slugs(array $templates): array
{
    $slugs = [];
    foreach (array_keys($templates) as $slug) {
        $slugs[] = (string) $slug;
    }

    return $slugs;
}

/**
 * The individual values packed into one att.
 *
 * A `checkbox`, `posttypes` or `taxonomies` control renders one input per
 * option and stores every ticked one in a single comma-joined att —
 * `options="count,hierarchical"`. WPBakery reads them back with
 * `explode(',', $value)` (include/params/default_params.php), so each piece
 * has to be judged on its own; comparing the whole string against a single
 * option would flag a value written by WPBakery's own editor.
 *
 * An empty att on one of those controls is how "nothing ticked" is stored,
 * and it is never one of the options, so it expands to no pieces at all.
 *
 * @return list<string>
 */
function wpb_att_value_pieces(mixed $att_value, string $type): array
{
    if (!in_array($type, WPB_MULTI_VALUE_PARAM_TYPES, strict: true)) {
        return [(string) $att_value];
    }
    if ((string) $att_value === '') {
        return [];
    }

    return explode(',', (string) $att_value);
}

/**
 * Whether one of the declared options matches, compared as strings on
 * purpose. Every att that has been through post_content is a string —
 * `[vc_tabs interval="5"]` — while WPBakery declares some of these lists
 * with integer values (vc_tabs.interval, vc_gallery.interval,
 * vc_zigzag.el_border_width, …), so the comparison is loose: a strict one
 * would flag a stored value that is read and written back unchanged.
 *
 * @param array<array-key, mixed> $opts
 */
function wpb_opts_contain(array $opts, string $needle): bool
{
    /** @var mixed $opt */
    foreach ($opts as $opt) {
        if (is_scalar($opt) && (string) $opt === $needle) {
            return true;
        }
    }

    return false;
}
