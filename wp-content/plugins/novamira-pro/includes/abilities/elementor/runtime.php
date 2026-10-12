<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * PHP 8.0-compatible equivalent of array_is_list().
 *
 * @param array<array-key, mixed> $value
 */
function el_array_is_list(array $value): bool
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
 * Shared runtime guards for Elementor abilities and skills.
 *
 * These checks deliberately use runtime feature detection instead of relying
 * on the version number alone. The atomic surface requires Elementor 4.x
 * proper plus the concrete runtime classes; some feature modules remain
 * optional on top of that.
 */

/**
 * Whether Elementor's experiments manager knows a given flag at all.
 *
 * "Not registered" and "registered but switched off" are different answers and
 * must not be collapsed: when Elementor graduates an experiment it drops the
 * flag and the feature it guarded becomes always-on, so a lookup for the
 * retired name reports "inactive" for something that is in fact permanently
 * enabled.
 *
 * False for a manager that cannot enumerate its flags: absence can only be
 * asserted from a manager that can be asked what it holds.
 */
function el_feature_registered(string $feature): bool
{
    if (!class_exists('Elementor\\Plugin')) {
        return false;
    }

    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $experiments */
    $experiments = $plugin->experiments ?? null;
    if (!is_object($experiments) || !method_exists($experiments, 'get_features')) {
        return false;
    }

    /** @var mixed $registered */
    $registered = $experiments->get_features($feature);
    return is_array($registered) && $registered !== [];
}

/**
 * Whether a given Elementor experiment leaves the feature it guards enabled.
 *
 * Only a flag that is registered *and* switched off gates. An unknown or
 * unavailable experiment manager, and a flag Elementor no longer registers at
 * all, are both treated as "not gating" so releases that retired the flag do
 * not produce false negatives.
 *
 * This answers "did the user turn it off?", not "is the feature here?". Pair
 * it with a positive capability probe — the class or repository implementing
 * the feature — which is what actually decides availability.
 */
function el_feature_active(string $feature): bool
{
    if (!class_exists('Elementor\\Plugin')) {
        return false;
    }

    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $experiments */
    $experiments = $plugin->experiments ?? null;
    if (!is_object($experiments) || !method_exists($experiments, 'is_feature_active')) {
        return true;
    }

    // Only a manager that can enumerate its flags can prove one is absent.
    // Where it can and the flag is not there, the feature graduated and must
    // not gate; where it cannot, the bare lookup is all we have.
    if (method_exists($experiments, 'get_features') && !el_feature_registered($feature)) {
        return true;
    }

    /** @var mixed $active */
    $active = $experiments->is_feature_active($feature);
    return $active === true;
}

/**
 * Whether the atomic widgets / containers runtime is available.
 *
 * Beyond Elementor's minimum version and the Atomic_Element_Base class, this
 * also requires the `e_atomic_elements` experiment to be active: the runtime
 * classes ship inside Elementor itself but the experiment gates the actual
 * registration of the `e-*` widgets (e-heading, e-flexbox, e-paragraph, …).
 * Without it the widgets are never registered, so reporting "atomic
 * available" while the experiment is off is actively misleading — callers
 * would try the v4 path, get "type not available" back, and have no clean
 * fallback.
 */
function el_atomic_runtime_available(): bool
{
    if (!el_min_runtime_available()) {
        return false;
    }

    if (version_compare(el_elementor_version(), version2: '4.0.0', operator: '<')) {
        return false;
    }

    if (!class_exists(NOVAMIRA_PRO_ELEMENTOR_ATOMIC_BASE_CLASS)) {
        return false;
    }

    return el_feature_active('e_atomic_elements');
}

/**
 * Whether the atomic-widgets runtime classes are loaded but the
 * `e_atomic_elements` experiment is OFF — i.e. the runtime is "almost
 * there" but `e-*` widgets are not registered. Used by check-setup to emit
 * a targeted issue distinct from "Elementor too old" or "module missing".
 */
function el_atomic_runtime_classes_loaded_but_experiment_off(): bool
{
    if (!el_min_runtime_available()) {
        return false;
    }

    if (version_compare(el_elementor_version(), version2: '4.0.0', operator: '<')) {
        return false;
    }

    if (!class_exists(NOVAMIRA_PRO_ELEMENTOR_ATOMIC_BASE_CLASS)) {
        return false;
    }

    // el_feature_active() already reports a retired flag as "not gating", so a
    // feature that graduated never reaches this as "switched off".
    return !el_feature_active('e_atomic_elements');
}

/**
 * The element types an ability may offer a caller on this site. The v4 atomic
 * containers are listed only where the atomic runtime is registered: where the
 * `e-*` types do not exist they cannot be written at all, so offering them
 * would only steer the caller into a dead end. Shared by every ability that
 * advertises the set, so the descriptions cannot drift apart.
 *
 * @return list<string>
 */
function el_element_type_enum(): array
{
    if (!el_atomic_runtime_available()) {
        return ['widget', 'container'];
    }

    return ['widget', 'container', ...NOVAMIRA_ATOMIC_CONTAINER_TYPES];
}

/**
 * el_element_type_enum() rendered as a quoted, comma-separated list, for the
 * abilities that name the set in prose rather than in a JSON Schema enum.
 */
function el_element_type_enum_text(): string
{
    return implode(', ', array_map(static fn(string $type): string => '"' . $type . '"', el_element_type_enum()));
}

/**
 * Example widget type names for a `widget_type` description. The atomic ones
 * are shown only where they are registered, for the same reason.
 */
function el_widget_type_examples(): string
{
    if (!el_atomic_runtime_available()) {
        return '"heading", "text-editor", "image"';
    }

    return '"heading", "text-editor", "image", "e-heading", "e-button"';
}

/**
 * Whether the v4 Atomic Widgets style schema is available.
 */
function el_style_schema_available(): bool
{
    return el_atomic_runtime_available() && class_exists('Elementor\\Modules\\AtomicWidgets\\Styles\\Style_Schema');
}

/**
 * Whether Elementor Global Classes are available.
 */
function el_global_classes_available(): bool
{
    return el_atomic_runtime_available()
    && class_exists('Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository');
}

/**
 * Whether Elementor Variables are available.
 *
 * Mirrors the Variables module's own constructor gate, which is a conjunction
 * and must stay one. Core requires the atomic-widgets experiment before it
 * registers anything for Variables — the CSS renderer, the REST routes, the
 * prop types — and on releases that still ship a separate Variables flag it
 * requires that one too. So do we: el_atomic_runtime_available() is NOT
 * optional here, and dropping it because the Variables flag was retired would
 * advertise five abilities on a site where core registered nothing for them.
 *
 * The repository class is the capability probe: where it exists the storage
 * these abilities read and write exists too. The Variables flag is consulted
 * only so a user who explicitly switched that feature off still gets it
 * gated; where the flag was retired it stops gating and the atomic experiment
 * is the whole answer, exactly as it is for core.
 */
function el_variables_available(): bool
{
    return (
        el_atomic_runtime_available()
        && class_exists('Elementor\\Modules\\Variables\\Storage\\Variables_Repository')
        && el_feature_active('e_variables')
    );
}

/**
 * Whether Elementor Interactions on atomic widgets are available.
 */
function el_interactions_available(): bool
{
    return el_atomic_runtime_available();
}

/**
 * Whether the bundled v3 -> v4 conversion skill can be used honestly on this
 * site without advertising missing abilities in its playbook.
 */
function el_v4_conversion_skill_available(): bool
{
    return el_atomic_runtime_available() && el_style_schema_available() && el_global_classes_available();
}

// Ensure per-element custom_css in v4 atomic styles is always rendered,
// even when the Elementor Pro license is not activated on this site
// (e.g. staging environments). The filter receives the stripped styles
// as $arg1 and the full styles (with custom_css) as $arg2.
add_filter(
    'elementor/atomic_widgets/editor_data/element_styles',
    static fn(mixed $stripped, mixed $full): mixed => $full,
    priority: 20,
    accepted_args: 2,
);

// DEPRECATED — retained for backward compatibility only; do NOT build on this.
//
// This filter widens the e-heading `tag` enum from h1-h6 to also allow div and
// span. It was originally added so agents could use e-heading for kicker labels,
// decorative numerals, or icon text. That was a mistake: the widened enum exists
// ONLY while Novamira Pro is active, so a page authored with a div/span-tagged
// e-heading is invalid under stock Elementor's atomic schema. Exported as a
// template and imported on a site without Novamira Pro, Document::save runs
// parse_atomic_settings() and throws an uncaught "tag: invalid_value" — a hard
// PHP fatal on import. The content is silently non-portable.
//
// New content must NOT use it: the build skill no longer documents this path and
// steers kicker/decorative/inline text to e-paragraph (p/span), which is valid
// under stock Elementor and renders identically (same base styles, title ↔
// paragraph). Agents therefore never learn this widening exists.
//
// Why it STAYS active despite the above: sites that authored div/span e-heading
// while it was recommended already have that content in _elementor_data. Only
// the SAVE path enforces the enum — Atomic_Widget_Base::get_data_for_save() calls
// parse_atomic_settings(), which throws on an out-of-enum value (verified in
// Elementor 4.1.x, modules/atomic-widgets/elements/base/has-atomic-base.php). The
// RENDER path does not: Render_Props_Resolver::get_validated_value() passes a
// stored non-dynamic value through unchanged (render-props-resolver.php), so such
// headings keep displaying on the front end. That means the content validates on
// Document::save (an editor re-save, a template export) ONLY while this widening
// is present. Removing the filter now would fatal those sites' own editor saves —
// regressing existing users who have Novamira Pro switched on — while the front
// end never needed it. So it remains as a compatibility net, not a feature.
// Candidate for removal in a future major once such legacy content has drained;
// harmless to keep until then.
//
// Identification: only schemas whose `tag` prop has exactly the h1-h6 enum are
// affected — other tag-bearing atomic widgets have different enums.
add_filter('elementor/atomic-widgets/props-schema', __NAMESPACE__ . '\\el_expand_heading_tag_enum');

/**
 * DEPRECATED filter callback: widen the e-heading tag enum to include div/span.
 * Kept only as a back-compat net for content authored before it was retired (see
 * the block above); new content is steered to e-paragraph instead. Do not extend.
 *
 * Targets only schemas where `tag` is a String_Prop_Type with exactly the
 * h1-h6 enum — the fingerprint unique to Atomic_Heading. All other atomic
 * widgets that carry a `tag` prop use a different enum and are left untouched.
 *
 * @deprecated Retained for backward compatibility; do not author against it.
 * @param array<string, mixed> $schema
 * @return array<string, mixed>
 */
function el_expand_heading_tag_enum(array $schema): array
{
    /** @var mixed $tag */
    $tag = $schema['tag'] ?? null;
    if (!$tag instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type) {
        return $schema;
    }
    if ($tag->get_enum() !== ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']) {
        return $schema;
    }
    $tag->enum(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span']);
    return $schema;
}

// el_elementor_version() and el_min_runtime_available() are defined in includes/gates.php
// (loaded before this file) so they are available here without redeclaration.

/**
 * The machine-readable declaration of one element node, shared by every
 * ability parameter that takes node-shaped input.
 *
 * Until now `content.items` and `tree` were declared as bare
 * `{"type": "object"}`: which keys a node takes, and that two of them accept a
 * second spelling, existed only in prose. An agent reading the schema — the
 * one thing an MCP client always sees — had nothing to go on. These properties
 * say it in the schema instead.
 *
 * `additionalProperties` is deliberately left OPEN. A real document node
 * carries Elementor's own extras (`isInner`, `interactions`, `editor_settings`,
 * `version`, and whatever a future version adds), and
 * `novamira/elementor-get-content` hands those straight back — closing the
 * object would break exactly the read-then-write round-trip that works today.
 * The point here is to describe the shape, not to fence it.
 *
 * Nested children are declared one level deep and documented as recursive:
 * WordPress's schema validator has no `$ref`, so a self-referencing tree
 * cannot be expressed, and a fake second level would only look precise.
 *
 * The types stay as wide as the callbacks already are, `null` included, and
 * `elements` deliberately declares no `items` constraint. Every one of these
 * keys has an input boundary in the callback that names the offending path AND
 * says what to do about it (`invalid_tree_shape` reports
 * `content[0].elements[1].settings is string, expected object` and adds "omit
 * the key when there is nothing to set", with the same list in `invalid_values`
 * for a caller correcting the payload programmatically). A narrower `type`
 * here would shadow all of that with a bare "is not of type object" from the
 * REST layer, and would refuse a JSON `null`, which everywhere in these
 * callbacks means "not provided". A declaration whose job is to describe the
 * shape must not quietly become the thing that rejects it.
 *
 * @return array<string, mixed>
 */
function el_node_schema_properties(): array
{
    return [
        'id' => [
            'type' => ['string', 'null'],
            'description' => 'Element id. The ONLY spelling a node takes — `element_id` / `elementId` are refused, not silently regenerated. Omit it to have a 7-char hex id assigned; pass a kebab-case slug for anything with a natural name, because the id becomes the rendered `data-id` and (for v4 atomic elements with synthesized local styles) the CSS class `s-<id>`.',
        ],
        'elType' => [
            'type' => ['string', 'null'],
            'description' =>
                'Element type, Elementor\'s own key: "widget" (with widgetType), "container" for the legacy v3 wrapper, or an atomic element type ('
                    . el_element_type_enum_text()
                    . ' on this site). An atomic WIDGET name ("e-heading", …) is accepted here too and is stored as elType "widget" + widgetType. Required on every node of a subtree passed to novamira/elementor-add-element. Wins over `element_type` when both are given.',
        ],
        'element_type' => [
            'type' => ['string', 'null'],
            'description' => 'Accepted alias of `elType`, the spelling novamira/elementor-add-element uses for its own single-element parameter. Same values; ignored when `elType` is also present.',
        ],
        'widgetType' => [
            'type' => ['string', 'null'],
            'description' =>
                'Widget registry name (e.g. '
                    . el_widget_type_examples()
                    . '), required when the element type is "widget". Wins over `widget_type` when both are given.',
        ],
        'widget_type' => [
            'type' => ['string', 'null'],
            'description' => 'Accepted alias of `widgetType`. Same values; ignored when `widgetType` is also present.',
        ],
        'settings' => [
            'type' => ['object', 'null'],
            'description' => 'Element settings, keyed by control id. The ONLY spelling — `element_settings` / `elementSettings` are refused rather than dropped. Omit the key when there is nothing to set; a scalar is rejected, never coerced to "nothing".',
        ],
        'elements' => [
            'type' => ['array', 'null'],
            'description' => 'Child elements, each with this same node shape, recursively. The ONLY spelling — `children` is refused rather than dropped. Must be a JSON array of element objects, never a stringified list; an entry that is not an object is refused by path.',
        ],
        'styles' => [
            'type' => ['object', 'null'],
            'description' => 'Per-element v4 styles. The ONLY spelling — `style` is refused rather than dropped. Two shapes, the same two every other styling surface takes: the ergonomic flat `{css-prop: value}` map (synthesized into one base-breakpoint variant under `s-<id>`), or the verbose `{"<style_id>": {id, type, label, variants: [{meta: {breakpoint, state}, props: {...}}]}}` map when you need explicit breakpoints or states.',
        ],
    ];
}
