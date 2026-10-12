<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

require_once __DIR__ . '/helpers-schema.php';
require_once __DIR__ . '/helpers-lint.php';
require_once __DIR__ . '/helpers-write.php';
require_once __DIR__ . '/helpers-css.php';
require_once __DIR__ . '/helpers-settings.php';

/**
 * Fields retained by `bricks_compact_control()`. Everything else —
 * css selector arrays, placeholder, tooltip, default, required, inline, mode,
 * small, dd, multiple, hasDynamicData, exclude, breakpoints, content, reload,
 * desc — is editor metadata that callers discovering keys don't need.
 *
 * Shared between `bricks-list-settings` and `bricks-list-elements` so both
 * abilities produce identically-shaped compact control descriptions.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_CONTROL_COMPACT_FIELDS = [
    'type',
    'label',
    'group',
    'description',
    'value_format',
    'options',
    'deprecated',
];

/**
 * PHP 8.0-compatible equivalent of array_is_list().
 *
 * @param array<array-key, mixed> $value
 */
function bricks_array_is_list(array $value): bool
{
    $expected = 0;
    foreach ($value as $key => $_) {
        if ($key !== $expected) {
            return false;
        }
        $expected++;
    }
    return true;
}

/**
 * Recursively merges $patch into $base. Associative arrays are merged key
 * by key; scalars and list arrays in $patch replace the corresponding value
 * in $base.
 *
 * Explicit null values replace the corresponding key with null.
 *
 * @param array<string, mixed> $base
 * @param array<string, mixed> $patch
 * @return array<string, mixed>
 */
function bricks_deep_merge(array $base, array $patch): array
{
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        if (
            is_array($value)
            && array_key_exists($key, $base)
            && is_array($base[$key])
            && !bricks_array_is_list($value)
            && !bricks_array_is_list($base[$key])
        ) {
            /** @var array<string, mixed> $base_sub */
            $base_sub = $base[$key];
            /** @var array<string, mixed> $patch_sub */
            $patch_sub = $value;
            $base[$key] = bricks_deep_merge($base_sub, $patch_sub);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

/**
 * Strip a Bricks control definition to the fields relevant to an agent
 * discovering keys and validating values.
 *
 * @param array<string, mixed> $control
 * @return array<string, mixed>
 */
function bricks_compact_control(array $control): array
{
    $compact = [];
    foreach (NOVAMIRA_BRICKS_CONTROL_COMPACT_FIELDS as $field) {
        if (!array_key_exists($field, $control)) {
            continue;
        }
        $compact[$field] = $control[$field];
    }
    return $compact;
}

/**
 * Validates element structural integrity.
 *
 * Returns an array with two keys:
 *  - 'warnings': informational issues (data can still be saved).
 *  - 'errors':   fatal issues that must block saving (duplicate ids, circular
 *                parent chains, shapes that crash the renderer).
 *
 * `$focus_ids` narrows the nesting advisories (non-nestable element with
 * children, structural element without children) to the elements a caller
 * actually wrote — insert-content passes its payload ids, patch-elements the
 * patched ids — so a full-page validation after a targeted edit does not
 * re-report every pre-existing empty container. Null means "all elements".
 *
 * @param list<array<string, mixed>> $elements  Flat Bricks element array.
 * @param list<string>|null          $focus_ids Ids whose nesting is checked; null = every element.
 * @return array{warnings: list<string>, errors: list<string>}
 */
function bricks_validate_elements(array $elements, ?array $focus_ids = null): array
{
    [$id_set, $id_warnings, $id_errors] = bricks_validate_element_ids($elements);

    return [
        'warnings' => array_merge(
            $id_warnings,
            bricks_validate_element_fields($elements, $id_set),
            bricks_validate_element_settings($elements),
            bricks_validate_settings_shapes($elements),
            bricks_validate_children_reciprocity($elements, $id_set),
            bricks_validate_nesting($elements, $focus_ids),
        ),
        'errors' => array_merge(
            $id_errors,
            bricks_detect_circular_parents($elements),
            bricks_validate_fatal_shapes($elements),
        ),
    ];
}

/**
 * Nesting advisories derived from Bricks' own `nestable` flag on the element
 * classes: children under an element that cannot hold them are silently
 * ignored by the builder and the renderer, and a section / container / block
 * with no children renders as an empty band. Advisory only — an empty
 * structural wrapper can be an intentional intermediate step.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<string>|null          $focus_ids
 * @return list<string>
 */
function bricks_validate_nesting(array $elements, ?array $focus_ids = null): array
{
    $focus = $focus_ids === null ? null : array_fill_keys($focus_ids, value: true);
    $child_counts = bricks_child_counts($elements);

    $warnings = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        $name = (string) ($element['name'] ?? '');
        if ($id === '' || $name === '' || $focus !== null && !array_key_exists($id, $focus)) {
            continue;
        }

        $warning = bricks_nesting_warning($element, $id, $name, $child_counts[$id] ?? 0);
        if ($warning !== null) {
            $warnings[] = $warning;
        }
    }

    return $warnings;
}

/**
 * Number of direct children per element id, derived from the parent pointers.
 *
 * @param list<array<string, mixed>> $elements
 * @return array<string, int>
 */
function bricks_child_counts(array $elements): array
{
    /** @var array<string, int> $child_counts */
    $child_counts = [];
    foreach ($elements as $element) {
        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;
        if ($parent === 0 || $parent === '') {
            continue;
        }
        $child_counts[(string) $parent] = ($child_counts[(string) $parent] ?? 0) + 1;
    }

    return $child_counts;
}

/**
 * The nesting advisory for one element, or null when its child count is
 * consistent with Bricks' nestable flag.
 *
 * @param array<string, mixed> $element
 */
function bricks_nesting_warning(array $element, string $id, string $name, int $children): ?string
{
    if ($children > 0 && bricks_element_is_nestable($name) === false) {
        return sprintf(
            'Element "%s" (%s) is not nestable, but %d element(s) declare it as their parent — Bricks ignores children under a non-nestable element. Move them under a container, block, or div (nestable elements are flagged `nestable: true` by novamira/bricks-list-elements).',
            $id,
            $name,
            $children,
        );
    }

    // A component instance is childless on purpose: the builder expands the
    // component body under it at render time, so it is judged by that body
    // rather than by its own children — and on whatever tag it sits, because
    // an instance that expands to nothing is as invisible on a div as on a
    // block. Checked before the structural-name gate for that reason.
    $component_id = (string) ($element['cid'] ?? '');
    if ($component_id !== '') {
        return bricks_component_instance_warning($id, $component_id);
    }

    if ($children === 0 && in_array($name, ['section', 'container', 'block'], strict: true)) {
        return sprintf(
            'Element "%s" (%s) has no children — it renders as an empty band. Add the content elements (heading, text-basic, button, image, …) as its children, or remove it.',
            $id,
            $name,
        );
    }

    return null;
}

/**
 * The elements that are pure layout wrappers: on their own they render a band
 * with nothing in it. `div` belongs here as well as the three the empty-band
 * advisory names, because a component body can be built on one.
 */
const NOVAMIRA_BRICKS_STRUCTURAL_ELEMENTS = ['section', 'container', 'block', 'div'];

/**
 * The advisory for one component instance, or null when the instance is fine.
 *
 * Four states are worth telling a reader about, and they are not the same
 * problem — see bricks_component_instance_verdict() for what each means and
 * what was observed for it. Each gets its own message, the call that shows
 * the stored body, and the call that repairs it.
 */
function bricks_component_instance_warning(string $id, string $component_id): ?string
{
    return match (bricks_component_instance_verdict($component_id)) {
        'renders' => null,
        'missing_component' => sprintf(
            'Element "%s" is an instance of component "%s", and no component with that id is stored — it renders nothing at all. List what this site has with novamira/bricks-list-components and point the element at one of those ids, or remove it.',
            $id,
            $component_id,
        ),
        'no_root' => sprintf(
            'Element "%s" is an instance of component "%s", whose stored body has no element carrying the component id. The builder renders a component from that root, so this instance renders nothing at all. Read the body with novamira/bricks-list-components verbose=true, then rewrite it with novamira/bricks-edit-component patch.elements — the first element you send becomes the root — or remove the instance.',
            $id,
            $component_id,
        ),
        'ambiguous_root' => sprintf(
            'Element "%s" is an instance of component "%s", whose stored body has more than one element carrying the component id. That body is MALFORMED and what it puts on the page is not reliable: which of the duplicate rows the builder resolves varies with their order and content, so the instance may render all of the body, part of it, or nothing — arrangements of the same two ids were seen doing each. Repair it rather than relying on it: read the body with novamira/bricks-list-components verbose=true, then rewrite it with novamira/bricks-edit-component patch.elements so exactly one element carries the component id.',
            $id,
            $component_id,
        ),
        default => sprintf(
            'Element "%s" is an instance of component "%s", whose stored body has no content reachable from its root — under the root there are layout wrappers only, and anything beside the root or orphaned from it is stored but never rendered. It renders as an empty band. Read the body with novamira/bricks-list-components verbose=true, then give the root content with novamira/bricks-edit-component patch.elements, or remove the instance.',
            $id,
            $component_id,
        ),
    };
}

/**
 * The verdict for an instance: the body's verdict, plus the case where no
 * component with that id is stored at all.
 *
 * @return 'renders'|'missing_component'|'no_root'|'ambiguous_root'|'nothing_reachable'
 */
function bricks_component_instance_verdict(string $component_id): string
{
    $body = bricks_component_body($component_id);

    return $body === null ? 'missing_component' : bricks_component_body_verdict($body, $component_id);
}

/**
 * Whether an instance with this verdict puts an empty band on the page.
 *
 * "ambiguous_root" deliberately does NOT count. An instance of a malformed
 * body was observed rendering the body's content in three of seven
 * arrangements, and empty_structural_ids is a list a caller acts on by
 * removing the element — following it there would delete content that is on
 * the page. The malformed body is still reported; it is reported as malformed.
 */
function bricks_component_verdict_is_empty_band(string $verdict): bool
{
    return in_array($verdict, ['missing_component', 'no_root', 'nothing_reachable'], strict: true);
}

/**
 * What a stored component body is, from the point of view of an instance:
 *  - "renders"           — content is reachable from the single root;
 *  - "no_root"           — no element carries the component id. The builder
 *                          renders a component from that root, and pages
 *                          carrying such an instance rendered none of the
 *                          body on either supported build;
 *  - "ambiguous_root"    — more than one element carries it. This one is NOT
 *                          a prediction: the body is malformed and what it
 *                          renders varies. Seven arrangements of two
 *                          duplicate rows were fetched on both builds; three
 *                          rendered the body's text and four rendered nothing,
 *                          following which row the builder resolved and what
 *                          the instance element itself was named. So it is
 *                          reported as malformed, not as empty;
 *  - "nothing_reachable" — a single root, but only layout wrappers hang under
 *                          it (content beside the root or orphaned from it is
 *                          stored and not rendered).
 *
 * The last one used to absorb the other two: a body with no root was judged
 * from its first element instead, and a body with two roots from whichever
 * row happened to win the id map — both said "renders" while the page showed
 * nothing.
 *
 * @param list<array<string, mixed>> $elements
 * @return 'renders'|'no_root'|'ambiguous_root'|'nothing_reachable'
 */
function bricks_component_body_verdict(array $elements, string $component_id): string
{
    $carrying = 0;
    foreach ($elements as $element) {
        if ((string) ($element['id'] ?? '') !== $component_id) {
            continue;
        }
        $carrying++;
    }
    if ($carrying === 0) {
        return 'no_root';
    }
    if ($carrying > 1) {
        return 'ambiguous_root';
    }

    return bricks_elements_render_content($elements, $component_id) ? 'renders' : 'nothing_reachable';
}

/**
 * The stored element tree of a component, or null when no component with this
 * id is stored.
 *
 * Reads the same single option the component abilities write, so a check run
 * during a write sees the components that exist at that moment.
 *
 * @return list<array<string, mixed>>|null
 */
function bricks_component_body(string $component_id): ?array
{
    foreach (comp_load_all() as $component) {
        if ((string) ($component['id'] ?? '') !== $component_id) {
            continue;
        }
        /** @var list<array<string, mixed>> */
        return is_array($component['elements'] ?? null) ? array_values($component['elements']) : [];
    }

    return null;
}

/**
 * Whether a component body renders anything: somewhere under its ROOT, at any
 * depth, an element that is not a structural wrapper.
 *
 * The walk starts at the root and follows parent pointers down, because that
 * is what the builder renders. Three things follow from that, each of which
 * was a way to be silent about a component that shows nothing:
 *  - a wrapper that has a child is not evidence of content: `block > empty
 *    container` is still nothing but empty bands, so the answer has to come
 *    from what the subtree contains, not from a child count;
 *  - a second element parented to 0 beside the root is NOT rendered — the
 *    body has exactly one root — so content sitting there does not count;
 *  - an element whose parent id is not in the body is not rendered either, so
 *    an orphan does not count.
 * A `seen` set stops a cycle in the body from looping.
 *
 * There is no fallback root: a body whose root is missing or ambiguous is not
 * something to guess at, and bricks_component_body_verdict() answers those
 * two before asking this. Falling back to the first element reported content
 * the page does not render.
 *
 * @param list<array<string, mixed>> $elements
 * @param string                     $root_id the component id, which the body
 *                                            root carries
 */
function bricks_elements_render_content(array $elements, string $root_id): bool
{
    $by_id = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if ($id !== '') {
            $by_id[$id] = $element;
        }
    }
    if (!array_key_exists($root_id, $by_id)) {
        return false;
    }

    $children_of = bricks_children_by_parent($elements);
    $stack = [$root_id];
    $seen = [];
    while ($stack !== []) {
        $id = array_pop($stack);
        if (array_key_exists($id, $seen)) {
            continue;
        }
        $seen[$id] = true;
        $name = (string) ($by_id[$id]['name'] ?? '');
        if ($name !== '' && !in_array($name, NOVAMIRA_BRICKS_STRUCTURAL_ELEMENTS, strict: true)) {
            return true;
        }
        foreach ($children_of[$id] ?? [] as $child) {
            $stack[] = $child;
        }
    }

    return false;
}

/**
 * The child ids of every element, keyed by parent id, taken from the parent
 * pointers (the side the write paths rebuild `children` from).
 *
 * Rows without an id are DROPPED, and so is a parent of "0": a caller walking
 * this map follows ids, and neither can be followed. Used by the
 * component-body content walk, which asks what a root reaches.
 *
 * A caller that has to NOTICE an unfollowable row wants
 * bricks_child_rows_by_parent() instead — every walk that predates this
 * helper uses that one, because folding the two together dropped the row an
 * insert relied on to refuse a corrupt tree.
 *
 * @param  list<array<string, mixed>> $elements
 * @return array<string, list<string>>
 */
function bricks_children_by_parent(array $elements): array
{
    /** @var array<string, list<string>> $children_of */
    $children_of = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        $parent = (string) ($element['parent'] ?? '0');
        if ($id === '' || $parent === '' || $parent === '0') {
            continue;
        }
        $children_of[$parent][] = $id;
    }

    return $children_of;
}

/**
 * Every child ROW keyed by parent id, including a row that carries no id —
 * kept as an empty string.
 *
 * The insert-position resolver walks the stored subtree to find where a
 * target ends. A stored row with no id is corruption it must not write over:
 * the walk cannot follow the row, so it stops and the insert is refused
 * before anything is stored. Dropping such rows makes the walk succeed and
 * turns a refusal into a committed write over a corrupt tree, so this map
 * keeps them and its caller treats the id it cannot resolve as the signal.
 *
 * @param  list<array<string, mixed>> $elements
 * @return array<string, list<string>>
 */
function bricks_child_rows_by_parent(array $elements): array
{
    /** @var array<string, list<string>> $rows */
    $rows = [];
    foreach ($elements as $element) {
        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;
        if ($parent === 0 || $parent === '') {
            continue;
        }
        $rows[(string) $parent][] = (string) ($element['id'] ?? '');
    }

    return $rows;
}

/**
 * Warning-level reciprocity check between `children` arrays and `parent`
 * pointers: every id listed in a `children` array must exist and its element's
 * `parent` must point back. Every write path rebuilds the `children` arrays
 * from the parent pointers at save time (bricks_ensure_children), so trees WE
 * write can never trip this — it surfaces corruption already stored by other
 * sources before this save reconciles it. Deliberately a warning, not an
 * error: blocking the save would make an already-corrupted page unfixable
 * through the abilities themselves, while this very write repairs it.
 *
 * @param list<array<string, mixed>> $elements
 * @param array<string, true> $id_set
 * @return list<string>
 */
function bricks_validate_children_reciprocity(array $elements, array $id_set): array
{
    $parent_by_id = [];
    foreach ($elements as $element) {
        $parent_by_id[(string) ($element['id'] ?? '')] = (string) ($element['parent'] ?? '0');
    }

    $warnings = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if (!isset($element['children']) || !is_array($element['children'])) {
            continue;
        }
        /** @var mixed $child */
        foreach ($element['children'] as $child) {
            if (!is_string($child) || $child === '') {
                continue;
            }
            if (!array_key_exists($child, $id_set)) {
                $warnings[] = sprintf(
                    'Element "%s" lists unknown child "%s" in its children array; children arrays are rebuilt from the parent pointers on this save.',
                    $id,
                    $child,
                );
                continue;
            }
            if (($parent_by_id[$child] ?? '0') !== $id) {
                $warnings[] = sprintf(
                    'Element "%s" lists child "%s" whose parent points to "%s" instead; children arrays are rebuilt from the parent pointers on this save.',
                    $id,
                    $child,
                    $parent_by_id[$child] ?? '0',
                );
            }
        }
    }

    return $warnings;
}

/**
 * Checks for misshapes that would cause a fatal PHP error inside Bricks'
 * renderer. Unlike the warning-level shape-checkers, callers MUST block saving
 * when any of these are present — saving a tree with one of these misshapes
 * leaves the site in a broken state until the element is re-patched.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<string>
 */
function bricks_validate_fatal_shapes(array $elements): array
{
    $errors = [];

    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        $name = (string) ($element['name'] ?? '');
        /** @var array<string, mixed> $settings */
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];

        if ($settings === []) {
            continue;
        }

        foreach (bricks_check_css_global_classes_shape($id, $settings) as $e) {
            $errors[] = $e;
        }
        foreach (bricks_check_list_control_shapes($id, $name, $settings) as $e) {
            $errors[] = $e;
        }
    }

    return $errors;
}

/**
 * A control Bricks stores as a list (multi-value select, repeater) must be
 * written as a list: the renderer iterates the value, so a bare string
 * (`"actions": "custom"` on a form) passes every key check, saves, and then
 * throws a TypeError on the front end. Blocking error naming the key and
 * the expected shape.
 *
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function bricks_check_list_control_shapes(string $id, string $name, array $settings): array
{
    $list_controls = $name === '' ? null : bricks_element_list_controls($name);
    if ($list_controls === null || $list_controls === []) {
        return [];
    }

    $errors = [];
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        [$base_key] = bricks_split_setting_key($key);
        if (!isset($list_controls[$base_key]) || $value === null) {
            continue;
        }
        $problem = bricks_list_value_problem($value, $list_controls[$base_key]['kind']);
        if ($problem === null) {
            continue;
        }
        $errors[] = sprintf(
            'Element "%s" (%s): "%s" must be %s, got %s — Bricks stores a list here and iterates it at render time, so this value breaks the page instead of being ignored. Write it as "%s": [%s].',
            $id,
            $name,
            $key,
            $list_controls[$base_key]['kind'] === 'strings'
                ? 'a list of strings (each one of the control\'s option keys)'
                : 'a list of objects (one per item, using the control\'s fields)',
            $problem,
            $key,
            $list_controls[$base_key]['kind'] === 'strings'
                ? '"' . $list_controls[$base_key]['example'] . '"'
                : $list_controls[$base_key]['example'],
        );
    }

    return $errors;
}

/**
 * Why a value is not the list a control expects, or null when it is.
 */
function bricks_list_value_problem(mixed $value, string $kind): ?string
{
    if (!is_array($value)) {
        return 'a bare ' . gettype($value);
    }
    if (!bricks_array_is_list($value)) {
        return 'an object';
    }
    /** @var mixed $entry */
    foreach ($value as $entry) {
        if ($kind === 'strings' && !is_string($entry)) {
            return 'a non-string entry';
        }
        if ($kind === 'objects' && (!is_array($entry) || $entry === [] || bricks_array_is_list($entry))) {
            return is_array($entry) ? 'a nested list entry where an object is expected' : 'a non-object entry';
        }
    }

    return null;
}

/**
 * `_cssGlobalClasses` must be a plain list of class-ID strings
 * (e.g. `["clshov"]`). Bricks' renderer (`Element::get_element_global_classes`)
 * does `isset($map[$class_id])` on each entry; passing `{id, name}` objects
 * — the natural guess from the `bricks-list-global-classes` shape — fatally blows up
 * on PHP 8+ with `TypeError: Cannot access offset of type array in isset`.
 *
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function bricks_check_css_global_classes_shape(string $id, array $settings): array
{
    /** @var mixed $classes */
    $classes = $settings['_cssGlobalClasses'] ?? null;
    if (!is_array($classes)) {
        return [];
    }

    /** @var mixed $entry */
    foreach ($classes as $entry) {
        if (!is_string($entry)) {
            return [
                sprintf(
                    'Element "%s": "_cssGlobalClasses" must be a list of class-ID strings (e.g. ["clshov"]), not objects. A non-string entry would fatally crash Bricks at render time. Look up class IDs via novamira/bricks-list-global-classes.',
                    $id,
                ),
            ];
        }
    }

    return [];
}

/**
 * Warns about setting values whose *shape* is wrong even though the key is
 * correct. These are the misshapes that Bricks silently drops at render
 * time — distinct from the key-level mistakes caught in
 * `bricks_validate_element_settings`.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<string>
 */
function bricks_validate_settings_shapes(array $elements): array
{
    $warnings = [];

    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        /** @var array<string, mixed> $settings */
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];

        if ($settings === []) {
            continue;
        }

        foreach (bricks_check_scalar_unit_shapes($id, $settings) as $w) {
            $warnings[] = $w;
        }
        foreach (bricks_check_typography_shapes($id, $settings) as $w) {
            $warnings[] = $w;
        }
        foreach (bricks_check_css_custom_shape($id, $settings) as $w) {
            $warnings[] = $w;
        }
        foreach (bricks_check_border_shape($id, $settings) as $w) {
            $warnings[] = $w;
        }
        foreach (bricks_font_family_warnings($settings, sprintf('Element "%s"', $id)) as $w) {
            $warnings[] = $w;
        }
    }

    return $warnings;
}

/**
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function bricks_check_scalar_unit_shapes(string $id, array $settings): array
{
    $scalar_unit_keys = [
        '_rowGap',
        '_columnGap',
        '_gridGap',
        '_gap',
        '_widthMax',
        '_widthMin',
        '_heightMax',
        '_heightMin',
        '_width',
        '_height',
    ];

    $warnings = [];
    foreach ($scalar_unit_keys as $key) {
        if (!array_key_exists($key, $settings) || !is_array($settings[$key])) {
            continue;
        }
        $warnings[] = sprintf(
            'Element "%s": "%s" must be a scalar string like "24px", got object. Value was dropped by Bricks.',
            $id,
            $key,
        );
    }
    return $warnings;
}

/**
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function bricks_check_typography_shapes(string $id, array $settings): array
{
    /** @var mixed $typography */
    $typography = $settings['_typography'] ?? null;
    if (!is_array($typography)) {
        return [];
    }

    $typography_scalar_subs = [
        'font-size',
        'line-height',
        'letter-spacing',
        'font-weight',
        'text-align',
        'text-transform',
        'text-decoration',
        'font-family',
        'font-style',
    ];

    $warnings = [];
    foreach ($typography_scalar_subs as $sub) {
        if (!array_key_exists($sub, $typography) || !is_array($typography[$sub])) {
            continue;
        }
        $warnings[] = sprintf(
            'Element "%s": "_typography.%s" must be a scalar string, got object. Bricks will drop this value.',
            $id,
            $sub,
        );
    }
    return $warnings;
}

/**
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function bricks_check_css_custom_shape(string $id, array $settings): array
{
    /** @var mixed $css */
    $css = $settings['_cssCustom'] ?? null;
    if (!is_string($css) || $css === '') {
        return [];
    }

    if (!str_contains($css, '{')) {
        return [
            sprintf(
                'Element "%s": "_cssCustom" must contain a full CSS rule block (with braces) using %%root%% as selector. Bare declarations are dropped. Example: "%%root%% { color: red; }"',
                $id,
            ),
        ];
    }

    if (str_contains($css, '%root%') || str_contains($css, '@media') || str_contains($css, '@keyframes')) {
        return [];
    }

    return [
        sprintf(
            'Element "%s": "_cssCustom" does not use %%root%% placeholder. Ensure the selector targets this element (e.g. "%%root%% { ... }").',
            $id,
        ),
    ];
}

/**
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function bricks_check_border_shape(string $id, array $settings): array
{
    /** @var mixed $border */
    $border = $settings['_border'] ?? null;
    if (!is_array($border)) {
        return [];
    }

    foreach (['top', 'right', 'bottom', 'left'] as $side) {
        /** @var mixed $sub */
        $sub = $border[$side] ?? null;
        if (!is_array($sub)) {
            continue;
        }
        if (array_key_exists('width', $sub) || array_key_exists('style', $sub) || array_key_exists('color', $sub)) {
            return [
                sprintf(
                    'Element "%s": "_border" has per-side nested width/style/color. Expected flat shape: {width:{top,right,bottom,left}, style, color, radius:{...}}. Value was partially or fully dropped.',
                    $id,
                ),
            ];
        }
    }

    return [];
}

/**
 * Generates the inline CSS Bricks would render for a flat element tree in a
 * specific area. Used by `return_css` on set-content / patch-elements so
 * callers can verify their settings produced the styles they expected.
 *
 * Returns an empty string if Bricks is not active.
 *
 * @param list<array<string, mixed>> $elements
 */
function bricks_render_area_css(array $elements, string $area): string
{
    if (!class_exists(\Bricks\Assets::class)) {
        return '';
    }

    // Reset relevant slots on the Assets singleton so we isolate this area.
    if (array_key_exists($area, \Bricks\Assets::$inline_css)) {
        \Bricks\Assets::$inline_css[$area] = '';
    }
    \Bricks\Assets::$inline_css_breakpoints = [];
    bricks_reset_css_generation_state();

    \Bricks\Assets::generate_css_from_elements($elements, $area);

    return is_string(\Bricks\Assets::$inline_css[$area] ?? null) ? \Bricks\Assets::$inline_css[$area] : '';
}

/**
 * Inspects generated Bricks CSS for orphan declaration blocks — declarations
 * that appear outside any selector, which usually means a `_cssCustom` value
 * was saved without a wrapping rule block.
 *
 * @return list<string>
 */
function bricks_detect_orphan_css(string $css): array
{
    if (trim($css) === '') {
        return [];
    }

    $warnings = [];
    $segments = explode('}', $css);

    foreach ($segments as $segment) {
        $trimmed = trim($segment);
        if ($trimmed === '') {
            continue;
        }

        // Split a segment into the part before the first `{` (the selector)
        // and the part after (the declarations of the next rule, which we
        // ignore here — it will be evaluated on the next iteration via the
        // preceding segment).
        $brace = strpos($trimmed, needle: '{');
        $head = $brace === false ? $trimmed : trim(substr($trimmed, offset: 0, length: $brace));

        if ($head === '') {
            continue;
        }

        // @media/@keyframes/@supports etc. open a nested block — not orphan.
        if ($head[0] === '@') {
            continue;
        }

        // If the "head" contains both `:` and `;`, it is declarations, not a
        // selector — orphan output.
        if (str_contains($head, ':') && str_contains($head, ';')) {
            $snippet = substr($head, offset: 0, length: 80);
            $warnings[] = sprintf(
                'Generated CSS contains orphan declarations near "%s". This usually indicates an _cssCustom value without a wrapping selector block.',
                $snippet,
            );
        }
    }

    return $warnings;
}

/**
 * Warns about common setting-key mistakes that Bricks silently ignores at
 * render time, leaving the model without any feedback about why its output
 * looks wrong:
 *
 *  1. Layout controls used without the `_` prefix (e.g. `direction` instead
 *     of `_direction`) — Bricks has no such control, so the value is ignored.
 *  2. Typography keys placed at the settings root (e.g. `color`, `fontSize`)
 *     instead of nested under `_typography`.
 *  3. Keys that are not a control of this element — including `_`-prefixed
 *     keys from the wrong layout family (`_gap` on a container, `_direction`
 *     on a heading), checked against the element's fully loaded control set.
 *
 * A layout control does NOT need `_display: "flex"` on the same element to
 * compile: Bricks emits the declaration regardless, and section, container
 * and block are flex containers by default (core CSS and theme styles).
 *
 * @param list<array<string, mixed>> $elements
 * @return list<string> Warnings.
 */
function bricks_validate_element_settings(array $elements): array
{
    $warnings = [];

    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        $name = (string) ($element['name'] ?? '');
        /** @var array<string, mixed> $settings */
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $known_controls = $name === '' ? null : bricks_element_control_keys($name);

        if ($settings === []) {
            continue;
        }

        foreach (bricks_check_unprefixed_keys($id, $settings, $known_controls) as $w) {
            $warnings[] = $w;
        }
        foreach (bricks_check_root_typography_keys($id, $settings) as $w) {
            $warnings[] = $w;
        }
        foreach (bricks_check_unknown_element_keys($id, $name, $settings, $known_controls) as $w) {
            $warnings[] = $w;
        }
    }

    return $warnings;
}

/**
 * Warns about settings keys that don't correspond to any registered control
 * for the element type. Bricks ignores unknown keys when generating CSS and
 * markup (the value stays in storage but never renders), so mistakes like
 * `title`/`text` on an `icon-box` (which uses a single `content` editor) or
 * `_gap` on a container (whose family is `_rowGap` / `_columnGap`) save
 * without error but have no effect.
 *
 * Keys are compared with the element's fully loaded control set, which
 * includes the base-injected shared style controls, so `_`-prefixed keys are
 * checked too. The `<key>[:<breakpoint>][:<pseudo>]` suffixes
 * (`_padding:mobile_landscape`, `_background:hover`,
 * `_background:mobile_landscape:hover`; desktop is unsuffixed) are stripped
 * before the lookup, and the handful of
 * `_` keys Bricks stores outside its controls (`_cssGlobalClasses`,
 * `_interactions`, `_conditions`, `_hidden`) are always accepted. When a
 * `_` key belongs to the other layout family the warning names the right
 * key for this element.
 *
 * @param array<string, mixed> $settings
 * @param array<string, true>|null $known_controls
 * @return list<string>
 */
function bricks_check_unknown_element_keys(
    string $id,
    string $name,
    array $settings,
    ?array $known_controls = null,
): array {
    if ($name === '') {
        return [];
    }

    $known = $known_controls ?? bricks_element_control_keys($name);
    if ($known === null) {
        // Unknown element type — reported separately by bricks_validate_element_fields().
        return [];
    }

    $warnings = [];
    foreach ($settings as $key => $_value) {
        if ($key === '') {
            continue;
        }
        [$base_key] = bricks_split_setting_key($key);
        if (array_key_exists($base_key, $known)) {
            continue;
        }
        if ($base_key[0] === '_') {
            if (in_array($base_key, NOVAMIRA_BRICKS_NON_CONTROL_UNDERSCORE_KEYS, strict: true)) {
                continue;
            }
            $warnings[] = bricks_unknown_underscore_key_warning($id, $name, $base_key);
            continue;
        }
        $warnings[] = sprintf(
            'Element "%s" (%s): setting "%s" is not a registered control for this element type — Bricks ignores it, so it never renders. Use `bricks-list-elements element=%s` to see the valid control keys.',
            $id,
            $name,
            $key,
            $name,
        );
    }

    return $warnings;
}

/**
 * Advisory for a `_`-prefixed key that is not in the element's loaded control
 * set: names the key of the right layout family when there is one.
 */
function bricks_unknown_underscore_key_warning(string $id, string $name, string $key): string
{
    $suggestion = bricks_underscore_key_suggestion($key, bricks_loaded_element_controls($name) ?? []);
    if ($suggestion !== null) {
        return sprintf(
            'Element "%s" (%s): "%s" is not a control of this element — Bricks ignores it. Use "%s" on a %s. Section, container, block and div expose the layout family (_direction, _rowGap, _columnGap, _gridGap); every other element exposes the flex-item family (_flexDirection, _gap). See `bricks-list-elements element=%s include_shared_controls=true`.',
            $id,
            $name,
            $key,
            $suggestion,
            $name,
            $name,
        );
    }

    return sprintf(
        'Element "%s" (%s): "%s" is not a control of this element — Bricks ignores it, so it never renders. Use `bricks-list-elements element=%s include_shared_controls=true` to see the shared style controls this element actually accepts.',
        $id,
        $name,
        $key,
        $name,
    );
}

/**
 * Returns the set of control keys of the given Bricks element after its full
 * load lifecycle (base controls + element controls + filters), or null if the
 * element type is unknown or cannot be introspected. Cached per request.
 *
 * @return array<string, true>|null
 */
function bricks_element_control_keys(string $name): ?array
{
    /** @var array<string, array<string, true>|null> $cache */
    static $cache = [];

    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

    return $cache[$name] = bricks_compute_element_control_keys($name);
}

/**
 * Uncached core of {@see bricks_element_control_keys()}.
 *
 * @return array<string, true>|null
 */
function bricks_compute_element_control_keys(string $name): ?array
{
    $controls = bricks_loaded_element_controls($name);
    if ($controls === null) {
        return null;
    }

    $keys = [];
    foreach (array_keys($controls) as $k) {
        $keys[$k] = true;
    }

    // Loop-builder controls (`hasLoop` / `query`) are injected by Bricks only
    // inside the builder: Element_Container::set_controls() gates them behind
    // `bricks_is_builder()`, so introspecting the element outside the editor
    // (as we do here) never exposes them — yet Bricks honours them at render
    // on exactly these four layout primitives. Add them so a legitimate query
    // loop on a block/div/section/container is not flagged as an unknown key.
    if (in_array($name, ['section', 'container', 'block', 'div'], strict: true)) {
        $keys['hasLoop'] = true;
        $keys['query'] = true;
    }

    return $keys;
}

/**
 * Resolves a Bricks element name (e.g. `icon-box`) to its registered class,
 * or null if the class cannot be found or is not a Bricks element.
 *
 * @return class-string<\Bricks\Element>|null
 */
function bricks_resolve_element_class(string $name): ?string
{
    if (!class_exists(\Bricks\Elements::class)) {
        return null;
    }

    /** @var array<string, mixed> $registered */
    $registered = \Bricks\Elements::$elements;
    if (!array_key_exists($name, $registered)) {
        return null;
    }

    /** @var mixed $entry */
    $entry = $registered[$name];
    $element_class = is_array($entry) && is_string($entry['class'] ?? null) ? $entry['class'] : null;
    if ($element_class === null && is_string($entry)) {
        $element_class = $entry;
    }

    if ($element_class === null || $element_class === '' || !class_exists($element_class)) {
        return null;
    }

    if (!is_subclass_of($element_class, \Bricks\Element::class)) {
        return null;
    }

    return $element_class;
}

/**
 * @param array<string, mixed>      $settings
 * @param array<string, true>|null $known_controls
 * @return list<string>
 */
function bricks_check_unprefixed_keys(string $id, array $settings, ?array $known_controls = null): array
{
    $suggestions = [
        'direction' => '_direction',
        'justifyContent' => '_justifyContent',
        'alignItems' => '_alignItems',
        'alignContent' => '_alignContent',
        'alignSelf' => '_alignSelf',
        'columnGap' => '_columnGap',
        'rowGap' => '_rowGap',
        'gap' => '_gap (on non-layout elements) or _rowGap / _columnGap (on section/container/block/div)',
        'flexDirection' => '_flexDirection (on non-layout elements) or _direction (on section/container/block/div)',
        'flexWrap' => '_flexWrap',
        'flexGrow' => '_flexGrow',
        'flexShrink' => '_flexShrink',
        'flexBasis' => '_flexBasis',
        'display' => '_display',
        'width' => '_width',
        'height' => '_height',
        'maxWidth' => '_widthMax',
        'minWidth' => '_widthMin',
        'maxHeight' => '_heightMax',
        'minHeight' => '_heightMin',
        'padding' => '_padding',
        'margin' => '_margin',
        'background' => '_background',
        'border' => '_border',
        'textAlign' => '_typography.text-align',
    ];

    $warnings = [];
    foreach ($suggestions as $bad => $good) {
        if (!array_key_exists($bad, $settings)) {
            continue;
        }
        if ($known_controls !== null && array_key_exists($bad, $known_controls)) {
            continue;
        }
        $warnings[] = sprintf(
            'Element "%s": setting "%s" is not a Bricks control — did you mean "%s"? Unprefixed keys are ignored at render time.',
            $id,
            $bad,
            $good,
        );
    }
    return $warnings;
}

/**
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function bricks_check_root_typography_keys(string $id, array $settings): array
{
    $typography_keys = [
        'color',
        'fontSize',
        'font-size',
        'fontWeight',
        'font-weight',
        'fontFamily',
        'font-family',
        'lineHeight',
        'line-height',
        'letterSpacing',
        'letter-spacing',
        'textTransform',
        'text-transform',
        'textAlign',
        'text-align',
        'textDecoration',
        'text-decoration',
    ];

    $warnings = [];
    foreach ($typography_keys as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        $warnings[] = sprintf(
            'Element "%s": typography key "%s" must be nested under "_typography" (e.g. "_typography": {"%s": ...}), not at the settings root.',
            $id,
            $key,
            $key,
        );
    }
    return $warnings;
}

/**
 * Checks element IDs: empty ids and ids with characters that cannot form the
 * `brxe-<id>` DOM id are warnings; duplicates are BLOCKING errors. A
 * duplicated id makes two elements collapse into one logical node — the
 * children map, the cycle detector and the Bricks editor all index by id —
 * so the second occurrence turns into a ghost element and, when its parent
 * differs, into a circular parent chain that no later write can repair.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{array<string, true>, list<string>, list<string>} [id_set, warnings, errors]
 */
function bricks_validate_element_ids(array $elements): array
{
    $warnings = [];
    $errors = [];
    /** @var array<string, int> $id_counts */
    $id_counts = [];
    /** @var array<string, true> $id_set */
    $id_set = [];

    foreach ($elements as $i => $element) {
        $id = (string) ($element['id'] ?? '');

        if ($id === '') {
            $warnings[] = sprintf('Element at index %d has an empty id.', $i);
            continue;
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1) {
            $warnings[] = sprintf(
                'Element id "%s" contains characters outside [A-Za-z0-9_-]; Bricks uses it as the DOM id brxe-<id> and in CSS selectors, so styling and interactions targeting it may fail. Omit the id to have one generated.',
                $id,
            );
        }

        $id_counts[$id] = ($id_counts[$id] ?? 0) + 1;
        $id_set[$id] = true;
    }

    $duplicates = [];
    foreach ($id_counts as $id => $count) {
        if ($count <= 1) {
            continue;
        }
        $duplicates[] = sprintf('"%s" (%d times)', $id, $count);
    }

    if ($duplicates !== []) {
        $errors[] = sprintf(
            'Duplicate element id(s) in the tree: %s. Every element needs a unique id per area — duplicates collapse two elements into one ghost node and produce circular parent chains. Give each element a distinct id, or omit the id and let it be generated (see generated_ids in the response).',
            implode(', ', $duplicates),
        );
    }

    return [$id_set, $warnings, $errors];
}

/**
 * Checks element names (against Bricks registry) and parent references.
 *
 * @param list<array<string, mixed>> $elements
 * @param array<string, true> $id_set Known element IDs in this batch.
 * @return list<string> Warnings.
 */
function bricks_validate_element_fields(array $elements, array $id_set): array
{
    $warnings = [];
    $can_check_names = class_exists(\Bricks\Elements::class);
    // Bricks writes themeStyles, variant, slotChildren, selectors, attributes,
    // global, cloneable, deletable and insideDropdown on the element record
    // itself (not inside settings); they must round-trip.
    $allowed_element_keys = [
        'id',
        'name',
        'parent',
        'children',
        'settings',
        'label',
        'cid',
        'properties',
        'themeStyles',
        'variant',
        'slotChildren',
        'selectors',
        'attributes',
        'global',
        'cloneable',
        'deletable',
        'insideDropdown',
    ];

    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        $name = (string) ($element['name'] ?? '');

        $unknown = array_diff(array_keys($element), $allowed_element_keys);
        foreach ($unknown as $key) {
            $warnings[] = sprintf(
                'Element "%s" has unexpected top-level key "%s". Did you mean to put this inside "settings"?',
                $id,
                $key,
            );
        }

        if ($name === '') {
            $warnings[] = sprintf('Element "%s" has an empty name.', $id);
        }

        if ($name !== '' && $can_check_names && !array_key_exists($name, \Bricks\Elements::$elements)) {
            $warnings[] = sprintf('Element "%s" has unrecognised type "%s".', $id, $name);
        }

        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;
        if ($parent !== 0 && $parent !== '' && !array_key_exists((string) $parent, $id_set)) {
            $warnings[] = sprintf('Element "%s" references non-existent parent "%s".', $id, (string) $parent);
        }
    }

    return $warnings;
}

/**
 * Detects circular parent chains and reports each cycle ONCE with its full
 * chain (`a -> b -> c -> a`) and the fix. A cycle can only come from parent
 * pointers that loop, which in practice means a moved subtree whose ancestor
 * was re-parented under its own descendant, or a duplicated id that merged
 * two elements into one node.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<string> Errors, one per distinct cycle.
 */
function bricks_detect_circular_parents(array $elements): array
{
    /** @var array<array-key, string> $parent_of Numeric-looking ids become int keys. */
    $parent_of = [];
    foreach ($elements as $el) {
        $eid = (string) ($el['id'] ?? '');
        if ($eid === '') {
            continue;
        }
        /** @var string|int $p */
        $p = $el['parent'] ?? 0;
        $parent_of[$eid] = $p === 0 || $p === '' ? '' : (string) $p;
    }

    $errors = [];
    /** @var array<string, true> $reported */
    $reported = [];
    foreach (array_keys($parent_of) as $id) {
        // Numeric-looking ids come back from array_keys() as ints.
        $cycle = bricks_parent_cycle_from((string) $id, $parent_of);
        if ($cycle === null) {
            continue;
        }
        $key = implode('>', bricks_rotate_cycle($cycle));
        if (array_key_exists($key, $reported)) {
            continue;
        }
        $reported[$key] = true;
        $errors[] = sprintf(
            'Circular parent chain (each arrow points to the parent): %s -> %s. An element cannot be an ancestor of itself. Set the parent of one element in the chain to 0 (root) or to an element outside the chain — when moving a subtree, only the subtree root changes parent. If you did not intend a cycle, check for a duplicated id: two elements sharing an id collapse into one node and produce exactly this error.',
            implode(' -> ', $cycle),
            $cycle[0],
        );
    }

    return $errors;
}

/**
 * Walks the parent chain from `$id`; returns the members of the cycle it
 * runs into (starting at the first repeated element), or null when the chain
 * reaches a root or an unknown parent.
 *
 * @param array<array-key, string> $parent_of
 * @return list<string>|null
 */
function bricks_parent_cycle_from(string $id, array $parent_of): ?array
{
    /** @var list<string> $path */
    $path = [];
    /** @var array<string, int> $seen */
    $seen = [];
    $current = $id;

    while ($current !== '' && array_key_exists($current, $parent_of)) {
        if (array_key_exists($current, $seen)) {
            return array_slice($path, $seen[$current]);
        }
        $seen[$current] = count($path);
        $path[] = $current;
        $current = $parent_of[$current];
    }

    return null;
}

/**
 * Rotates a cycle so its lexicographically smallest id comes first, giving
 * every traversal of the same cycle one canonical key.
 *
 * @param list<string> $cycle
 * @return list<string>
 */
function bricks_rotate_cycle(array $cycle): array
{
    $sorted = $cycle;
    sort($sorted);
    $offset = array_search($sorted[0], $cycle, strict: true);
    if (!is_int($offset) || $offset === 0) {
        return $cycle;
    }

    return array_merge(array_slice($cycle, $offset), array_slice($cycle, offset: 0, length: $offset));
}

/**
 * Ensures every parent element has a `children` array listing its direct
 * child IDs, derived from the `parent` field on each element. Bricks'
 * frontend renderer requires this to render nested content.
 *
 * The `children` array is also Bricks' source of truth for *render order*,
 * which is not necessarily the flat-array order — pages built or edited by
 * other tools (Advanced Themer, imports, …) routinely store the two out of
 * sync. So a parent's order is rebuilt from the flat array ONLY when its set
 * of child ids actually changed (a child was added or removed); when the set
 * is unchanged the parent keeps its existing `children` order verbatim. Without
 * this, a write that touches one container would re-derive — and thus silently
 * reorder — the children of every other container whose stored order differs
 * from the flat-array order.
 *
 * @param list<array<string, mixed>> $elements Flat array of Bricks element arrays.
 * @return list<array<string, mixed>> The same elements with `children` arrays injected.
 */
function bricks_ensure_children(array $elements): array
{
    /** @var array<array-key, non-empty-list<string>> $children_map */
    $children_map = [];

    // `?? ''` on purpose: a stored row with no id is corruption another source
    // left, and reconciling the children of the rows around it must not raise
    // an undefined-key notice on the way. Such a row simply has no children.
    foreach ($elements as $element) {
        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;
        $id = (string) ($element['id'] ?? '');

        if ($parent !== 0 && $parent !== '' && (string) $parent !== $id) {
            $children_map[$parent][] = $id;
        }
    }

    foreach ($elements as &$element) {
        $id = (string) ($element['id'] ?? '');
        $flat_children = $children_map[$id] ?? null;

        if ($flat_children === null) {
            // No children according to the parent pointers (the source of
            // truth): force an empty list instead of skipping. Skipping left
            // a container emptied by remove-content with its STALE children
            // array (a dangling id the Bricks editor then chokes on), and
            // inserted leaves without the `children: []` key every
            // builder-saved element carries.
            $element['children'] = [];
            continue;
        }

        // Preserve the stored render order when the child set is unchanged; only
        // the container that actually gained or lost a child falls back to the
        // flat-array order (which the add / insert / remove path arranged for it).
        $existing = bricks_existing_child_ids($element);
        $element['children'] = $existing !== null && bricks_same_id_set($existing, $flat_children)
            ? $existing
            : $flat_children;
    }

    return $elements;
}

/**
 * An element's existing `children` as a list of string ids, or null when it
 * has no usable `children` array.
 *
 * @param array<string, mixed> $element
 * @return list<string>|null
 */
function bricks_existing_child_ids(array $element): ?array
{
    if (!isset($element['children']) || !is_array($element['children'])) {
        return null;
    }
    $ids = [];
    /** @var mixed $child */
    foreach ($element['children'] as $child) {
        if (is_string($child)) {
            $ids[] = $child;
            continue;
        }
        if (is_int($child)) {
            $ids[] = (string) $child;
        }
    }
    return $ids;
}

/**
 * Whether two id lists contain exactly the same ids, ignoring order.
 *
 * @param list<string> $a
 * @param list<string> $b
 */
function bricks_same_id_set(array $a, array $b): bool
{
    if (count($a) !== count($b)) {
        return false;
    }
    sort($a);
    sort($b);
    return $a === $b;
}

/**
 * Resolves %root% placeholders in _cssCustom settings to the actual
 * Bricks CSS selector. The Bricks editor does this in JavaScript;
 * programmatic content must be pre-processed server-side.
 *
 * @param list<array<string, mixed>> $elements Flat Bricks element array.
 * @return list<array<string, mixed>> Elements with _cssCustom resolved.
 */
function bricks_resolve_css_selectors(array $elements): array
{
    foreach ($elements as &$element) {
        /** @var array<string, mixed> $settings */
        $settings = is_array($element['settings']) ? $element['settings'] : [];
        $css = is_string($settings['_cssCustom'] ?? null) ? $settings['_cssCustom'] : '';

        if ($css === '') {
            continue;
        }

        $id = (string) $element['id'];
        $selector = '#brxe-' . $id;

        $settings['_cssCustom'] = str_replace('%root%', $selector, $css);
        $element['settings'] = $settings;
    }

    return $elements;
}

/**
 * Normalises element settings on the write path to match what the Bricks
 * editor would persist, so a payload that is schema-valid but that Bricks
 * silently drops (or that breaks builder tooling) is corrected before saving.
 *
 * @param list<array<string, mixed>> $elements Flat Bricks element array.
 * @return list<array<string, mixed>>
 */
function bricks_normalize_write_elements(array $elements): array
{
    foreach ($elements as &$element) {
        $element = bricks_settings_object_shape($element);
        $element = bricks_drop_empty_svg_source($element);
    }
    unset($element);

    return $elements;
}

/**
 * Keep a structural element's `settings` an object, never an empty array.
 *
 * Bricks (and builder tooling such as Advanced Themer's element-type "Convert")
 * treats an element's `settings` as an object; an empty PHP array serialises to
 * JSON `[]` instead of `{}`, and JS that writes into it (e.g. `settings.tag = …`)
 * then loses the write. For an otherwise-settingless div/block/container/section
 * we store the default HTML tag Bricks would render anyway — render-identical —
 * which makes `settings` a non-empty array that serialises as `{}`. Elements that
 * already carry settings are left untouched. (An empty `settings` is fine for
 * Bricks' own render; this only conforms the shape for the editor/JS layer.)
 *
 * @param array<string, mixed> $element
 * @return array<string, mixed>
 */
function bricks_settings_object_shape(array $element): array
{
    $default_tags = ['div' => 'div', 'block' => 'div', 'container' => 'div', 'section' => 'section'];
    $name = is_string($element['name'] ?? null) ? $element['name'] : '';
    if (!isset($default_tags[$name])) {
        return $element;
    }

    /** @var mixed $settings */
    $settings = $element['settings'] ?? null;
    if ($settings === null || is_array($settings) && $settings === []) {
        $element['settings'] = ['tag' => $default_tags[$name]];
    }

    return $element;
}

/**
 * Drop an SVG element's literal empty `source` so Bricks applies its 'file'
 * default. The "File" source value in the control schema is the empty string,
 * but `Element_Svg::render()` resolves `$settings['source'] ?? 'file'` and
 * matches `=== 'file'`, so a literal `source: ""` never renders while an absent
 * source defaults to 'file' and renders. The editor omits the key; we do too.
 *
 * @param array<string, mixed> $element
 * @return array<string, mixed>
 */
function bricks_drop_empty_svg_source(array $element): array
{
    if (($element['name'] ?? null) !== 'svg' || !is_array($element['settings'] ?? null)) {
        return $element;
    }

    /** @var array<string, mixed> $settings */
    $settings = $element['settings'];
    if (($settings['source'] ?? null) === '') {
        unset($settings['source']);
        $element['settings'] = $settings;
    }

    return $element;
}

/**
 * Signs the `code` of Code / SVG-"Code" elements (and a query loop's PHP
 * `queryEditor`) on the write path, so Bricks renders them instead of
 * silently dropping them.
 *
 * Bricks gates code output behind a per-element signature: at render time it
 * recomputes `wp_hash($code)` and emits the markup only if the stored
 * `signature` matches (Element_Code / Element_Svg). The editor produces that
 * signature on save (`Admin::process_elements_for_signature`); a programmatic
 * write bypasses the editor, so without this step the element saves but
 * renders empty ("No signature").
 *
 * We delegate to Bricks' own signer, which signs only when site code
 * execution is enabled AND the current user holds the Bricks "Execute code"
 * capability — the same gate the editor enforces. When an element needs a
 * signature but does not get one, a warning explains why, so the outcome is
 * never a silent empty render. The hash is taken over the code exactly as it
 * will be stored: the write path slashes the tree before `update_post_meta()`
 * (which unslashes), so the stored bytes are the caller's original bytes and
 * the signer must NOT strip slashes — Bricks' own save strips them only
 * because it signs request-slashed `$_POST` data.
 *
 * Sites can freeze signature generation with the `BRICKS_LOCK_CODE_SIGNATURES`
 * constant (any truthy value counts, matching Bricks' own check). The lock is
 * honored on every supported Bricks version: signing new or changed code on a
 * locked site would defeat the site's explicit opt-in, so the lock is
 * detected up front and surfaced as a hard `bricks_code_signatures_locked`
 * error instead of quietly signing (or persisting code that cannot render).
 *
 * @param list<array<string, mixed>> $elements Flat Bricks element array.
 * @return array{0: list<array<string, mixed>>, 1: list<string>}|WP_Error [elements, warnings]
 */
function bricks_sign_code_elements(array $elements): array|WP_Error
{
    $needing = bricks_collect_elements_needing_signature($elements);
    if ($needing === []) {
        return [$elements, []];
    }

    // Truthy on purpose, matching Bricks' own lock semantics (1 and '1' lock too).
    // @mago-expect analysis:mixed-operand
    if (defined('BRICKS_LOCK_CODE_SIGNATURES') && (bool) constant('BRICKS_LOCK_CODE_SIGNATURES')) {
        return bricks_code_signatures_locked_error($needing);
    }

    $signer_ready =
        class_exists(\Bricks\Helpers::class)
        && class_exists(\Bricks\Admin::class)
        && method_exists(\Bricks\Admin::class, 'process_elements_for_signature');

    if (!$signer_ready || !\Bricks\Helpers::code_execution_enabled()) {
        $warnings = [];
        foreach ($needing as $id => $name) {
            $warnings[] = sprintf(
                'Element "%s" (%s) contains code, but Bricks code execution is disabled, so Bricks will render it empty. Enable it under Bricks > Settings > Custom code > Code execution, grant the "Execute code" capability, then re-save.',
                $id,
                $name,
            );
        }
        return [$elements, $warnings];
    }

    $signed = \Bricks\Admin::process_elements_for_signature(
        $elements,
        only_regenerate_if_missing: false,
        strip_slashes: false,
    );

    $warnings = [];
    foreach ($signed as $element) {
        $id = (string) ($element['id'] ?? '');
        if (!array_key_exists($id, $needing) || bricks_element_is_code_signed($element)) {
            continue;
        }
        $warnings[] = sprintf(
            'Element "%s" (%s) contains code that could not be signed because the current user lacks the Bricks "Execute code" capability, so Bricks will render it empty. Grant that capability to this user or role, or open the page in the Bricks editor as an authorised user and re-save.',
            $id,
            $needing[$id],
        );
    }

    return [$signed, $warnings];
}

/**
 * Collects the elements that need a code signature, keyed by element id with
 * the element name as value.
 *
 * @param  list<array<string, mixed>> $elements
 * @return array<string, string>
 */
function bricks_collect_elements_needing_signature(array $elements): array
{
    /** @var array<string, string> $needing */
    $needing = [];
    foreach ($elements as $element) {
        if (!bricks_element_needs_code_signature($element)) {
            continue;
        }
        $needing[(string) ($element['id'] ?? '')] = is_string($element['name'] ?? null) ? $element['name'] : '';
    }
    return $needing;
}

/**
 * Builds the hard error returned when a write needs code signatures while the
 * BRICKS_LOCK_CODE_SIGNATURES constant freezes signature generation site-wide.
 *
 * @param array<string, string> $needing id => element name
 */
function bricks_code_signatures_locked_error(array $needing): WP_Error
{
    $labels = [];
    foreach ($needing as $id => $name) {
        $labels[] = sprintf('"%s" (%s)', $id, $name);
    }

    return bricks_error('bricks_code_signatures_locked', sprintf(
        'This site has locked code-signature generation (the BRICKS_LOCK_CODE_SIGNATURES constant is set), so this ability will not sign new or changed code, and the write was rejected for code element(s) %s. Remove the constant (usually defined in wp-config.php), or make this edit in an environment where signatures are unlocked.',
        implode(', ', $labels),
    ));
}

/**
 * True when an element carries code that Bricks renders only with a valid
 * signature: a Code or SVG element with non-empty `code`, or any element whose
 * query loop uses the PHP query editor.
 *
 * @param array<string, mixed> $element
 */
function bricks_element_needs_code_signature(array $element): bool
{
    /** @var array<string, mixed> $settings */
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    $name = is_string($element['name'] ?? null) ? $element['name'] : '';

    if (
        in_array($name, ['code', 'svg'], strict: true)
        && is_string($settings['code'] ?? null)
        && $settings['code'] !== ''
    ) {
        return true;
    }

    /** @var array<string, mixed> $query */
    $query = is_array($settings['query'] ?? null) ? $settings['query'] : [];
    return is_string($query['queryEditor'] ?? null) && $query['queryEditor'] !== '';
}

/**
 * True when an element that needs a code signature actually has one. Used
 * after signing to detect elements Bricks declined to sign (e.g. the caller
 * lacks the "Execute code" capability).
 *
 * @param array<string, mixed> $element
 */
function bricks_element_is_code_signed(array $element): bool
{
    return bricks_code_field_signed($element) && bricks_query_editor_signed($element);
}

/**
 * True unless the element is a Code/SVG element whose non-empty `code` lacks a
 * `signature`.
 *
 * @param array<string, mixed> $element
 */
function bricks_code_field_signed(array $element): bool
{
    /** @var array<string, mixed> $settings */
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    $name = is_string($element['name'] ?? null) ? $element['name'] : '';
    $has_code =
        in_array($name, ['code', 'svg'], strict: true)
        && is_string($settings['code'] ?? null)
        && $settings['code'] !== '';

    return !$has_code || is_string($settings['signature'] ?? null) && $settings['signature'] !== '';
}

/**
 * True unless the element's query loop uses the PHP `queryEditor` and lacks a
 * `query.signature`.
 *
 * @param array<string, mixed> $element
 */
function bricks_query_editor_signed(array $element): bool
{
    /** @var array<string, mixed> $settings */
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    /** @var array<string, mixed> $query */
    $query = is_array($settings['query'] ?? null) ? $settings['query'] : [];
    $has_editor = is_string($query['queryEditor'] ?? null) && $query['queryEditor'] !== '';

    return !$has_editor || is_string($query['signature'] ?? null) && $query['signature'] !== '';
}

/**
 * Resolves the %root% placeholder inside a global class's _cssCustom to the
 * actual class selector (`.<className>`). Bricks does the equivalent in the
 * editor (JavaScript) when the user clicks Save on a class panel; programmatic
 * writes via the abilities must do it server-side. Without this substitution
 * the stored CSS contains literal "%root%" text, which the browser drops.
 *
 * @param array<array-key, mixed> $settings    The global class `settings` array.
 * @param string                  $class_name  Slugified class name (e.g. "hover-fade").
 * @return array<array-key, mixed> Settings with `_cssCustom` resolved.
 */
function bricks_resolve_global_class_root(array $settings, string $class_name): array
{
    if (!is_string($settings['_cssCustom'] ?? null) || $settings['_cssCustom'] === '') {
        return $settings;
    }

    $selector = '.' . $class_name;
    $settings['_cssCustom'] = str_replace('%root%', $selector, $settings['_cssCustom']);

    return $settings;
}

/**
 * Read the Bricks template type meta (`_bricks_template_type`).
 * Falls back to "content" when missing, matching Bricks core behavior.
 */
function bricks_template_type(int $template_id): string
{
    /** @var string|false $raw */
    $raw = get_post_meta($template_id, BRICKS_DB_TEMPLATE_TYPE, single: true);
    return is_string($raw) && $raw !== '' ? $raw : 'content';
}

/**
 * Map a Bricks template type to the meta key its elements live in.
 * Mirrors the canonical mapping used by Bricks core (templates.php import,
 * database.php frontend rendering). Header/footer types route to their
 * matching area; every other type uses the content area.
 */
function bricks_template_area_meta_key(string $template_type): string
{
    return match ($template_type) {
        'header' => BRICKS_DB_PAGE_HEADER,
        'footer' => BRICKS_DB_PAGE_FOOTER,
        default => BRICKS_DB_PAGE_CONTENT,
    };
}

/**
 * Map a Bricks template type to the area name (header/footer/content)
 * its elements should live in.
 */
function bricks_template_expected_area(string $template_type): string
{
    return match ($template_type) {
        'header' => 'header',
        'footer' => 'footer',
        default => 'content',
    };
}

/**
 * Generate a short alphanumeric id matching Bricks UI conventions
 * (e.g. classes use ids like "abcdef" or "clshov"). Uses lowercase
 * letters + digits, length 6 by default.
 */
function bricks_random_id(int $length = 6, string $prefix = ''): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    // An unprefixed id can come out all digits, and PHP stores such a string
    // as an INTEGER array key — see bricks_id_is_type_ambiguous(). Draw again
    // rather than hand out an id that changes type when it is used as a key.
    for ($attempt = 0; $attempt < 32; $attempt++) {
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }
        if (!bricks_id_is_type_ambiguous($prefix . $id)) {
            return $prefix . $id;
        }
    }

    return $prefix . 'a' . substr($id, offset: 1);
}

/**
 * Given an option name holding category objects [{id, name}], either
 * find an existing category (by id or case-insensitive name) or create
 * one and persist it. Returns the resolved category id, or null if the
 * input was null/empty (uncategorized).
 */
/**
 * Which Bricks constant, if any, says that a global-variable-family option
 * is shared from the network's main site.
 *
 * Bricks routes these options per family, each behind its own constant, and
 * the two are INDEPENDENT — a network can share the variables and keep the
 * categories per site, or the reverse. They are resolved separately here for
 * that reason and must never be conflated.
 *
 * Builds that expose the variables accessor route the variables (and their
 * trash) by the variables constant; builds without it read the variables
 * under the categories constant instead, and following the constant that
 * build's own reader uses is what keeps our reads pointed at the rows the
 * builder shows.
 */
function bricks_variable_option_share_constant(string $option): ?string
{
    if ($option === BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES) {
        return 'BRICKS_MULTISITE_USE_MAIN_SITE_VARIABLES_CATEGORIES';
    }
    // Global-CLASS categories are their own family behind their own
    // constant. Both supported builds read them from the main site under it
    // (Database::set_global_data on the older one, get_global_classes_categories
    // on the newer), so the read follows. Only this one class-family option is
    // routed: the other class options have inconsistent vendor read/write
    // paths and are not changed here without a network to observe.
    if ($option === BRICKS_DB_GLOBAL_CLASSES_CATEGORIES) {
        return 'BRICKS_MULTISITE_USE_MAIN_SITE_CLASSES_CATEGORIES';
    }
    if ($option !== BRICKS_DB_GLOBAL_VARIABLES && $option !== bricks_variables_trash_option()) {
        return null;
    }

    return bricks_variables_accessor_available()
        ? 'BRICKS_MULTISITE_USE_MAIN_SITE_VARIABLES'
        : 'BRICKS_MULTISITE_USE_MAIN_SITE_VARIABLES_CATEGORIES';
}

/**
 * Writes a category option where the installed builder's own writer would
 * write it.
 *
 * Global-class categories are the one family whose newer builds declare a
 * routing writer: on a network configured to share them, it targets the main
 * site's row while a plain update_option() targets the subsite's. We would
 * then have written, read back and reported success on a row the builder does
 * not use. When that writer is declared AND the network configuration it keys
 * off is in effect on a subsite, the write is handed to it.
 *
 * Off a network — and on the main site of one — this resolves to exactly the
 * update_option() call it always made, so single-site installs are unchanged
 * down to the autoload flag. NOT OBSERVED END TO END: there is no multisite
 * fixture here, so the routed branch is source-derived from the builds' own
 * writer and reader. What is observed is that single sites keep today's
 * behaviour.
 *
 * @param list<array<string, mixed>>|array<array-key, mixed> $categories
 */
function bricks_write_category_option(string $option, array $categories): void
{
    if ($option === BRICKS_DB_GLOBAL_CLASSES_CATEGORIES && bricks_class_categories_write_is_routed()) {
        \Bricks\Database::save_global_classes_categories($categories);
        return;
    }

    update_option($option, $categories);
}

/**
 * Whether a class-category write on THIS install goes somewhere other than
 * the current site's option row — the only case in which delegating changes
 * anything.
 *
 * All three have to hold: a network, a subsite of it, the builder declaring
 * the routing writer, and its configuration constant switched on.
 */
function bricks_class_categories_write_is_routed(): bool
{
    if (!is_multisite() || get_main_site_id() === get_current_blog_id()) {
        return false;
    }
    if (!method_exists(\Bricks\Database::class, 'save_global_classes_categories')) {
        return false;
    }

    // @mago-expect analysis:mixed-operand -- a site owner's constant; any
    // truthy value switches the sharing on, matching the builder's own test.
    return (
        defined('BRICKS_MULTISITE_USE_MAIN_SITE_CLASSES_CATEGORIES')
        && (bool) constant('BRICKS_MULTISITE_USE_MAIN_SITE_CLASSES_CATEGORIES')
    );
}

/** Whether this build exposes Bricks' own main-site-aware variables reader. */
function bricks_variables_accessor_available(): bool
{
    return method_exists(\Bricks\Helpers::class, 'get_global_variables_option');
}

/**
 * The site that holds a global-variable-family option, or null for the
 * current one (always null off a network).
 */
function bricks_variable_option_site(string $option): ?int
{
    if (!is_multisite()) {
        return null;
    }
    $constant = bricks_variable_option_share_constant($option);
    if ($constant === null || !defined($constant) || !constant($constant)) {
        return null;
    }
    $main = get_main_site_id();

    return $main === get_current_blog_id() ? null : $main;
}

/**
 * Reads a Bricks option from the site that holds it.
 *
 * Two families are routed today: the global variables (with their trash and
 * their categories) and the global-CLASS categories. Every other option is
 * read from the current site exactly as before, so this is safe to use as
 * the single option reader.
 *
 * Every read of a routed option must come through here. Reading one member of
 * a family from a different site than the others is what makes a variable the
 * builder shows look missing — and makes the lint call a perfectly good var()
 * reference unresolved. The class categories are the same story: the write
 * path routes them, so a read that did not would have create and list
 * disagreeing about the same store.
 */
function bricks_routed_option_read(string $option, mixed $default_value = []): mixed
{
    if (
        bricks_variables_accessor_available()
        && ($option === BRICKS_DB_GLOBAL_VARIABLES || $option === bricks_variables_trash_option())
    ) {
        return \Bricks\Helpers::get_global_variables_option($option, $default_value);
    }
    $site = bricks_variable_option_site($option);

    return $site === null ? get_option($option, $default_value) : get_blog_option($site, $option, $default_value);
}

/**
 * Rows of a routed option, as a list.
 *
 * @return list<mixed>
 */
function bricks_routed_option_rows(string $option): array
{
    /** @var mixed $raw */
    $raw = bricks_routed_option_read($option, []);

    return is_array($raw) ? array_values($raw) : [];
}

function bricks_resolve_category(?string $input, string $option_name): ?string
{
    if ($input === null || trim($input) === '') {
        return null;
    }

    /** @var mixed $raw_categories */
    $raw_categories = bricks_routed_option_read($option_name, []);
    $categories = is_array($raw_categories) ? $raw_categories : [];

    /** @var array<string, mixed> $cat */
    foreach ($categories as $cat) {
        /** @var mixed $cat_id */
        $cat_id = $cat['id'] ?? null;
        /** @var mixed $cat_name */
        $cat_name = $cat['name'] ?? null;
        if (is_string($cat_id) && $cat_id === $input) {
            return $cat_id;
        }
        if (is_string($cat_name) && strcasecmp($cat_name, $input) === 0 && is_string($cat_id)) {
            return $cat_id;
        }
    }

    $new_id = bricks_random_id(prefix: 'cat-');
    $categories[] = ['id' => $new_id, 'name' => $input];
    bricks_write_category_option($option_name, $categories);

    return $new_id;
}

/**
 * Ids of any Bricks "code" elements in a flat write payload. The code element
 * injects raw HTML/CSS/JS and is a last resort, so the write abilities gate it
 * behind explicit confirmation (see bricks_code_element_block()).
 *
 * @param array<array-key, mixed> $elements
 * @return list<string>
 */
function bricks_collect_code_element_ids(array $elements): array
{
    $ids = [];
    /** @var mixed $el */
    foreach ($elements as $el) {
        if (!is_array($el) || ($el['name'] ?? null) !== 'code') {
            continue;
        }
        $ids[] = (string) ($el['id'] ?? '?');
    }
    return $ids;
}

/**
 * The confirmation gate returned when a write would create Bricks "code"
 * elements without the explicit allow_code_elements opt-in. The agent must
 * confirm with the user and re-call with allow_code_elements:true.
 *
 * @param list<string> $code_ids
 */
function bricks_code_element_block(array $code_ids): \WP_Error
{
    return bricks_error('bricks_code_element_needs_confirmation', sprintf(
        'This write would create %d Bricks "code" element(s) (ids: %s). The code element injects raw HTML/CSS/JS and is a LAST RESORT: it is unsigned and may render empty, and it sidesteps the theme and the Bricks design system. Build content and layout with native Bricks elements (heading, text-basic, button, image, container, …) and style via global classes + Bricks fields instead. Only if the user has EXPLICITLY confirmed they want a raw code/HTML element, re-call with "allow_code_elements": true — do not set that flag on your own.',
        count($code_ids),
        implode(', ', $code_ids),
    ));
}

/**
 * Canonical HTTP status for every error code the Bricks abilities raise.
 *
 * Without a status a WP_Error reaches the caller as HTTP 500 — "the server
 * broke, retry" — for failures that are definitive: an unknown post id, a
 * malformed payload, a lock another user holds. An agent that retries those
 * unchanged can only fail the same way.
 *
 * The default for an UNLISTED code is 500, so a new call site cannot quietly
 * label an unclassified failure as caller-correctable. The drift guard in
 * tests/bricks-smoke/error-statuses.php fails when a raised code is neither
 * listed here nor given an explicit status at its call site.
 *
 * Two codes are deliberately absent because they never reach a caller:
 * `revision_not_created` and `revision_meta_not_copied` are consumed by
 * bricks_revision_snapshot_note() and reported as a note on a successful
 * write, never returned as the ability's result.
 *
 * SCOPE — a code is mapped only when every cause that raises it wants the
 * SAME answer from the caller. A status belongs to the CAUSE, not to the
 * name of the code, so a code raised for two causes with different remedies
 * is left unclassified here and decided at its call site, or not at all.
 *
 * Five codes are deliberately absent for that reason, each shown by an
 * executed counterexample rather than by inspection:
 *
 *  - `not_found` and `component_not_found` answer both a genuine miss (the
 *    resource is not there — 404) and an input that named nothing at all: a
 *    blank id, or an integer id of 0, on which 14 abilities answer the same
 *    404 while the addressed post exists.
 *  - `invalid_elements` covers payload validation (400) and, in
 *    bricks-remove-content, element ids that were looked up and not found —
 *    a 404 whose own message says "were not found".
 *  - `invalid_position` covers a malformed position (400) and a
 *    `position.after` id that was looked up and missed (404).
 *  - `bricks_global_settings_rejected` covers a stored conflict the caller
 *    can clear (409), a shape this request introduced (400), and a rollback
 *    that did not land (500).
 *
 * Unclassified here means the WP_Error carries NO status key — not a key set
 * to 500. The REST layer serves a statusless error as HTTP 500 either way, so
 * the caller sees what it saw before; the difference is that nothing in this
 * surface claims 500 is the right answer for a cause that wants 400, 404 or
 * 409. Deciding them needs per-call-site work, specified with its executed
 * counterexamples in issue #177.
 *
 * @return array<string, int>
 */
function bricks_error_map(): array
{
    return [
        // 400 — the caller's input is wrong. Deterministic: the same call
        // cannot start working, so it must not be labelled retryable.
        'ambiguous_source' => 400,
        'ambiguous_toggle' => 400,
        'dangling_connection' => 400,
        'invalid_condition' => 400,
        'invalid_input' => 400,
        'invalid_post_type_slug' => 400,
        'invalid_post_types_shape' => 400,
        'invalid_property' => 400,
        'invalid_setting_shape' => 400,
        'invalid_type' => 400,
        'invalid_value' => 400,
        'missing_element_ids' => 400,
        'missing_elements' => 400,
        'missing_source' => 400,
        // The post exists; it just has no public URL to render-check.
        'no_url' => 400,
        'not_template' => 400,
        'unknown_global_setting' => 400,
        'unknown_post_type' => 400,
        'unsupported_interaction' => 400,

        // 404 — a lookup for an addressed resource failed. No code is
        // mapped here: the two that raise 404 (`not_found`,
        // `component_not_found`) also fire when nothing was addressed at
        // all, so they are unclassified. See the list below.

        // 409 — a state conflict the caller can resolve and then retry.
        // `corrupt_tree` and `invalid_component` are stored damage rather
        // than a competing actor, but they belong here for the same reason:
        // the caller has a documented repair (bricks-set-content,
        // bricks-remove-content, bricks-edit-component with patch.elements)
        // and 500 would tell an agent to retry a call that never succeeds.
        'corrupt_tree' => 409,
        'duplicate_name' => 409,
        'empty_area' => 409,
        'instances_exist' => 409,
        'invalid_component' => 409,
        'locked_by_other_user' => 409,

        // 412 — a site prerequisite is missing, so the operation cannot be
        // attempted at all. Nothing about the caller's input is wrong.
        'bricks_code_signatures_locked' => 412,
        'not_available' => 412,
        'unavailable' => 412,

        // 422 — the request is well formed but needs an explicit opt-in.
        'bricks_code_element_needs_confirmation' => 422,

        // 500 — a genuine server-side fault the caller cannot act on.
        // `trash_not_stored` is a write that was attempted, read back and
        // found not to have landed: the same class as a failed rollback.
        'trash_not_stored' => 500,
    ];
}

/**
 * Build a WP_Error carrying the HTTP status its code maps to, or NO status
 * key at all when the code has none.
 *
 * The absence is the point. Defaulting an unmapped code to 500 does not leave
 * it unclassified — it classifies it, as the worst available answer, for
 * causes that want 400, 404 or 409. The REST layer still serves a statusless
 * WP_Error as HTTP 500, so what the caller sees is unchanged; what changes is
 * that this surface no longer asserts that 500 is the right answer.
 *
 * `$status` overrides the map for the call sites where one code covers two
 * different causes and the cause — not the name — decides the status. An
 * explicit status always wins, mapped or not.
 *
 * @param array<string, mixed> $data
 */
function bricks_error(string $code, string $message, array $data = [], ?int $status = null): \WP_Error
{
    $status ??= bricks_error_map()[$code] ?? null;
    if ($status === null) {
        return new \WP_Error($code, $message, $data);
    }

    return new \WP_Error($code, $message, ['status' => $status] + $data);
}

/**
 * Read a stored area for editing, telling an EMPTY area apart from one whose
 * stored value is not an element tree at all.
 *
 * get_post_meta() answers the same '' for "never written" and "written
 * empty", and any other scalar arrives as a string. Collapsing all of them
 * into "the area is empty" makes the reply advise adding content on top of a
 * value that is still there, which is how stored data gets overwritten.
 *
 * Absent, written-empty and [] are genuinely empty — bricks-set-content
 * writes [] for `elements: []`, so it is a normal state, not damage.
 * metadata_exists() is what separates "never written" from "written and
 * unreadable". Anything else is reported as stored damage.
 *
 * The damage message deliberately does NOT send the caller to
 * bricks-get-content: that reader renders a corrupt area as an empty list, so
 * following it would confirm exactly the wrong conclusion.
 *
 * @return list<array<string, mixed>>|WP_Error
 */
function bricks_area_elements_or_error(int $post_id, string $area, string $action): array|WP_Error
{
    $meta_key = bricks_area_meta_keys()[$area] ?? null;
    if ($meta_key === null) {
        return bricks_error('invalid_input', message: 'area must be one of: header, content, footer.');
    }

    /** @var array<array-key, mixed>|string|false $stored */
    $stored = get_post_meta($post_id, $meta_key, single: true);

    if (!is_array($stored)) {
        $unreadable =
            $stored !== '' && $stored !== false || $stored === false && metadata_exists('post', $post_id, $meta_key);
        if ($unreadable) {
            return bricks_error('corrupt_tree', sprintf(
                'The "%s" area on post %d holds a stored value that is not a Bricks element tree (%s), so %s. Something IS stored there: novamira/bricks-get-content reports this area as an empty list and cannot show it, so do not treat it as empty. Replacing the area with novamira/bricks-set-content clears it and discards whatever that value was — confirm with the site owner before doing so.',
                $area,
                $post_id,
                get_debug_type($stored),
                $action,
            ));
        }
    }

    if (!is_array($stored)) {
        return [];
    }

    /** @var list<array<string, mixed>> */
    return array_values($stored);
}

/**
 * Classify a validation failure raised AFTER a targeted edit was applied to a
 * stored area.
 *
 * The tree handed to bricks_validate_elements() on these paths is mostly
 * stored data: the caller supplied a settings patch, a removal list, a class
 * name or one component instance — never a whole tree. The validator's ERRORS
 * are computed over the whole tree regardless (duplicate ids and circular
 * parent chains are cross-element by construction, and $focus_ids narrows
 * only the warnings), so the failure can be caused entirely by damage the
 * caller never touched. Reporting that as `invalid_elements` — a 400, "your
 * input is wrong" — is false, and 500 would tell an agent to retry a call
 * that cannot start working.
 *
 * The question is answered structurally, not by comparing error lists: the
 * write has not committed yet at every call site, so the stored area is still
 * the pre-edit state and can simply be validated on its own. This is the same
 * shape as the up-front stored-cycle guard bricks_insert_content() already
 * applies before resolving an insert position.
 *
 * When the stored area was ALREADY invalid the stored errors are reported,
 * because those are what the caller has to repair first; a payload problem in
 * the same call surfaces on the retry.
 *
 * @param list<string> $errors errors from validating the post-edit tree
 */
function bricks_write_validation_error(int $post_id, string $area, array $errors): \WP_Error
{
    $stored_errors = bricks_validate_elements(bricks_get_area_elements($post_id, $area))['errors'];
    if ($stored_errors === []) {
        return bricks_error('invalid_elements', implode(' ', $errors));
    }

    return bricks_error('corrupt_tree', sprintf(
        'The stored "%s" area of post %d is already invalid, independently of this call: %s Nothing was written. Repair the area first — read it with novamira/bricks-get-content (summary: true), then rewrite it with novamira/bricks-set-content or drop the offending element with novamira/bricks-remove-content — and retry.',
        $area,
        $post_id,
        implode(' ', $stored_errors),
    ));
}
