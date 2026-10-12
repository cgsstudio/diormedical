<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

/**
 * Ability: List registered Bricks elements.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-elements', [
    'label' => __('List Bricks Elements', domain: 'novamira-pro'),
    'description' => __(
        'Lists registered Bricks elements with their `nestable` flag (can hold children) and `control_family` (which shared layout controls the element accepts: "layout" = section/container/block/div with _direction/_rowGap/_columnGap/_gridGap; "flex_item" = most others, with _flexDirection/_gap; "none" = neither, taking no shared layout keys — read the field rather than assuming a family). Use element="<name>" to get the element-specific control schema for one element, or include_controls=true to get controls for all. Controls are returned in a compact shape (type, label, group, description, value_format, options) by default — pass verbose=true for every field. Pass include_shared_controls=true to also get the shared style controls (_padding, _margin, _typography, _background, _border, sizing, flex/grid, …) read from the element\'s fully loaded control set: with element="<name>" you get exactly the shared controls that element accepts; without it you get the two families side by side. Call this before writing element settings — the returned keys are the only ones Bricks renders.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'include_controls' => [
                'type' => 'boolean',
                'description' => 'Whether to include element-specific control definitions for every element. Can produce large output — prefer element="<name>" to fetch one.',
                'default' => false,
            ],
            'element' => [
                'type' => 'string',
                'description' => 'Filter to a specific element name to get its full details including element-specific controls.',
            ],
            'include_shared_controls' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'If true, also return the shared style controls (_padding, _margin, _border, _typography, _cssCustom, sizing, flex/grid keys, …) read from the loaded element. With element="<name>" the response carries the shared controls of that element only; without it, both families ("layout" for section/container/block/div, "flex_item" for everything else) are returned so you can fetch them once per session.',
            ],
            'verbose' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Return every field of every control (css selectors, placeholders, tooltips, defaults, etc.). Only useful for debugging — defaults to a compact shape.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'elements' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'icon' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'keywords' => ['type' => 'array'],
                        'scripts' => ['type' => 'array'],
                        'nestable' => [
                            'type' => 'boolean',
                            'description' => 'True when the element can hold child elements. Children under a non-nestable element are ignored by Bricks.',
                        ],
                        'control_family' => [
                            'type' => 'string',
                            'description' => '"layout" (accepts _direction/_rowGap/_columnGap/_gridGap: section, container, block, div), "flex_item" (accepts _flexDirection/_gap: every other element, including those that define their own element-specific _direction or _gap control), or "none". Derived from the loaded controls, never from a fixed list.',
                        ],
                        'notes' => ['type' => 'string'],
                        'controls' => ['type' => ['object', 'array']],
                        'control_groups' => ['type' => ['object', 'array']],
                        'shared_controls_hint' => ['type' => 'string'],
                    ],
                ],
            ],
            'total' => ['type' => 'integer'],
            'shared_style_controls' => [
                'type' => 'object',
                'description' => 'Present with include_shared_controls=true. With element="<name>": the `_`-prefixed controls of that element. Without: {families: {layout: {elements, controls}, flex_item: {elements, controls}}}.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_elements',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Progressive disclosure: the default per-element response is compact (~1-2k tokens). Pass element="heading" for one element, include_controls=true to get controls for every element (large), verbose=true to include editor metadata (css selectors, placeholders, tooltips). Element-specific controls (text, tag, link, image, …) and shared style controls (_padding, _typography, flex/grid, …) are returned separately: the shared ones come only with include_shared_controls=true and depend on the element\'s control_family — layout elements (section/container/block/div) take _direction/_rowGap/_columnGap/_gridGap, a flex_item element takes _flexDirection/_gap, and a control_family of "none" takes neither — read the family from this ability instead of assuming it. Check `nestable` before giving an element children.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_list_elements(array $input): array
{
    $include_controls = ($input['include_controls'] ?? false) === true;
    $include_shared = ($input['include_shared_controls'] ?? false) === true;
    $verbose = ($input['verbose'] ?? false) === true;
    /** @var string|null $element_filter */
    $element_filter = $input['element'] ?? null;

    /** @var array<string, class-string<\Bricks\Element>|array{class: class-string<\Bricks\Element>, name: string, label: string}> $registered */
    $registered = \Bricks\Elements::$elements;

    $elements = [];

    foreach ($registered as $name => $entry) {
        if ($element_filter !== null && $name !== $element_filter) {
            continue;
        }

        $element_class = is_array($entry) ? $entry['class'] : $entry;

        $elements[] = match (true) {
            ($include_controls || $element_filter !== null) && $verbose
                => bricks_describe_element_with_controls_verbose($name, $element_class),
            $include_controls || $element_filter !== null => bricks_describe_element_with_controls_compact(
                $name,
                $element_class,
            ),
            default => bricks_describe_element($name, $element_class),
        };
    }

    $result = [
        'elements' => $elements,
        'total' => count($elements),
    ];

    if ($include_shared) {
        $result['shared_style_controls'] = bricks_shared_style_controls($element_filter);
    }

    return $result;
}

/**
 * Bricks control types that only structure the editor panel (separators,
 * info boxes, "apply" buttons) and never hold a stored value.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_UI_ONLY_CONTROL_TYPES = ['separator', 'info', 'apply'];

/**
 * Annotates a Bricks control definition with a human-readable `value_format`
 * hint describing what shape its settings value should take. The Bricks
 * control schema itself only describes the editor UI; it does not document
 * the accepted value shape, which is what agents actually need. UI-only
 * controls (separators, info boxes, apply buttons) are marked explicitly so
 * their lack of a value shape does not look accidental.
 *
 * @param array<string, mixed> $control
 * @return array<string, mixed>
 */
function bricks_annotate_control(array $control): array
{
    $type = is_string($control['type'] ?? null) ? $control['type'] : null;
    if ($type === null) {
        return $control;
    }

    if (($control['multiple'] ?? false) === true) {
        $control['value_format'] =
            'list of strings, each matching one of options keys (multi-value control — Bricks stores and iterates a list here, so a bare string is rejected; e.g. ["'
            . bricks_first_option_key($control)
            . '"])';

        return $control;
    }

    $has_units = ($control['units'] ?? false) !== false;

    $control['value_format'] = match (true) {
        in_array($type, NOVAMIRA_BRICKS_UI_ONLY_CONTROL_TYPES, strict: true)
            => 'ui-only control — no stored value, never write this key',
        $type === 'number' && $has_units => 'string with unit, e.g. "1.5rem" or "var(--space-m)"',
        $type === 'number' => 'scalar number or numeric string',
        $type === 'color' => '{raw: "var(--token)"} or {raw: "#hex"} or {id: "<palette color id>"} palette reference',
        $type === 'spacing',
        $type === 'dimensions',
            => '{top, right, bottom, left} with unit strings (e.g. "var(--space-m)")',
        $type === 'border' => '{width:{top,right,bottom,left}, style, color:{raw}, radius:{top,right,bottom,left}}',
        $type === 'typography'
            => '{font-family: "<bare family name, no var(), no comma stack>", font-size, line-height, letter-spacing, font-weight, color:{raw}, text-align, text-transform} — all sub-values are scalar strings except color',
        $type === 'background' => '{color:{raw}, image:{url,id}, ...}',
        $type === 'gradient'
            => '{applyTo: "background"|"overlay"|"text", angle: "<deg>", colors: [{color:{raw}, stop:"<0-100>"}, …]}',
        $type === 'box-shadow' => '{values:{offsetX, offsetY, blur, spread}, color:{raw}, inset?: true}',
        $type === 'text-shadow' => '{values:{offsetX, offsetY, blur}, color:{raw}}',
        $type === 'transform'
            => '{translateX, translateY, scaleX, scaleY, rotateX, rotateY, rotateZ, skewX, skewY} — strings with units, only the ones you set',
        $type === 'checkbox' => 'boolean true to enable; omit the key (do not write false) to leave it off',
        $type === 'select',
        $type === 'direction',
        $type === 'align-items',
        $type === 'justify-content',
        $type === 'text-align',
            => 'string matching one of options keys',
        $type === 'text', $type === 'textarea' => 'string',
        $type === 'editor'
            => 'HTML string limited to inline/prose markup (<p>, <strong>, <a>, <ul>) — never layout markup (<section>, <div>) or <style>; use native elements for structure',
        $type === 'repeater'
            => 'list of objects; each item uses the keys of this control\'s `fields` (see verbose=true) plus an auto-assigned `id`',
        $type === 'query'
            => '{objectType: "post"|"term"|"user", post_type: ["<slug>"], posts_per_page: <n>, orderby?, order?, tax_query?, meta_query?} — set together with hasLoop: true on the direct child block of the grid',
        $type === 'query-list' => 'list of query ids',
        $type === 'filters'
            => 'CSS filter values as {blur, brightness, contrast, hue, invert, opacity, saturation, sepia} with unit strings',
        $type === 'datepicker' => 'date string as Bricks stores it from the picker (e.g. "2026-12-31 10:00")',
        $type === 'code' => 'raw code string — for _cssCustom must be a full CSS rule block using %root% as selector',
        default => bricks_annotate_asset_control($type),
    };

    return $control;
}

/**
 * First option key of a control (for value examples), or a placeholder.
 *
 * @param array<string, mixed> $control
 */
function bricks_first_option_key(array $control): string
{
    $options = is_array($control['options'] ?? null) ? array_keys($control['options']) : [];

    return $options !== [] ? (string) $options[0] : 'value';
}

/**
 * `value_format` hints for the asset-bearing control types (links, images,
 * media, icons); every other unknown type gets a generic pointer to
 * verbose=true.
 */
function bricks_annotate_asset_control(string $type): string
{
    return match ($type) {
        'link'
            => '{type: "external"|"internal"|"taxonomy"|"meta"|"lightboxImage"|"lightboxVideo"|"media", url?: "<absolute url>" (external), postId?: <id> (internal), useDynamicData?: "{post_url}" (meta — the link reads useDynamicData only when type is meta), newTab?: true, rel?: "nofollow", ariaLabel?: "..."} — on a section/container/block/div also set tag: "a", otherwise the element renders as a plain div and the link is ignored',
        'image'
            => '{id: <attachment id>, url: "<full url>", size?: "full"|"large"|"medium"|"thumbnail"} — or {useDynamicData: "{featured_image}"} for a dynamic image',
        'image-gallery' => '{images: [{id, url, size?}, …], size?: "large"}',
        'video',
        'audio',
            => '{url: "<file url>"} or {id: <attachment id>} for library media; other sources use the element-specific keys (videoType, youTubeId, …)',
        'svg' => '{id: <attachment id>, url: "<svg url>"}',
        'icon'
            => '{library: "themify"|"ionicons"|"fontawesomeSolid"|"fontawesomeRegular"|"fontawesomeBrands", icon: "<class name, e.g. ti-arrow-right>"} — or {library: "svg", svg: {id, url}}',
        default => sprintf(
            'control type "%s" has no documented value shape; use verbose=true and mirror what the Bricks editor stores',
            $type,
        ),
    };
}

/**
 * Shared style controls read from Bricks' fully loaded elements. With an
 * element name, the `_`-prefixed controls of that element; without one, the
 * two families side by side — the layout family from `container` and the
 * flex-item family from `heading` — with the element names each family
 * applies to, derived from the live registry.
 *
 * @return array<string, mixed>
 */
function bricks_shared_style_controls(?string $element_name): array
{
    if ($element_name !== null) {
        $controls = bricks_element_shared_controls($element_name);
        if ($controls === null) {
            return ['error' => sprintf('Element "%s" is not registered or cannot be introspected.', $element_name)];
        }
        return [
            'element' => $element_name,
            'control_family' => bricks_element_control_family($element_name),
            'controls' => $controls,
        ];
    }

    /** @var array<string, list<string>> $by_family */
    $by_family = ['layout' => [], 'flex_item' => [], 'none' => []];
    foreach (array_keys(\Bricks\Elements::$elements) as $name) {
        $family = bricks_element_control_family($name) ?? 'none';
        $by_family[$family][] = $name;
    }

    return [
        'note' => 'Shared controls are injected by Bricks per element. Section, container, block and div register the layout family (_direction, _rowGap, _columnGap, _gridGap and the grid template controls); most other elements register the flex-item family (_flexDirection, _gap). Some register neither — `slot` is one — and those report control_family "none" and take no shared layout keys at all, so read the family from this ability rather than assuming it. A few flex items define an element-specific control under a layout-looking key (post-navigation _direction, back-to-top _gap) — that key is valid on that element only. Writing a key from the other family is ignored by Bricks. Responsive / pseudo values use <key>[:<breakpoint>][:<pseudo>] (desktop unsuffixed).',
        'breakpoints' => 'Responsive values use the breakpoint key as a suffix on any control key (e.g. `_padding:tablet_portrait`); the base breakpoint takes the plain key. Read the keys and widths in effect with novamira/bricks-list-breakpoints — custom sets can rename them.',
        'families' => [
            'layout' => [
                'elements' => $by_family['layout'],
                'controls' => bricks_element_shared_controls('container') ?? [],
            ],
            'flex_item' => [
                'elements' => $by_family['flex_item'],
                'controls' => bricks_element_shared_controls('heading') ?? [],
            ],
        ],
    ];
}

/**
 * Novamira-curated note for specific Bricks element names. Supplements
 * Bricks's own metadata with guidance (hierarchy conventions, semantic-tag
 * defaults, …) that the agent should see at element lookup time.
 *
 * @return string|null
 */
function bricks_element_note(string $name): ?string
{
    return match ($name) {
        'section'
            => 'Top-level layout band. The typical direct child is a `container`; default to that shape for content sections. Legitimate cases for skipping the container (block-level child directly inside the section): full-bleed hero, edge-to-edge media, or a section already constrained by a theme style. A `link` on a section/container/block/div renders only when `tag` is "a" — without it the element stays a plain div and the link is silently ignored.',
        'container'
            => 'Centered/constrained wrapper, the typical direct child of `section` and parent of block-level elements (heading, text-basic, button, image, …). Skipping the container is fine for full-bleed sections, but for ordinary content sections the section > container > block-level shape picks up theme-style spacing predictably. When the section represents repeated CMS content (blog grid, portfolio list, related posts strip, anything the customer will edit over time), make this container the grid (`_display: "grid"`, `_gridTemplateColumns`, `_gridGap`) and put `hasLoop: true` plus the `query` block on its DIRECT CHILD `block` — the block is the card that repeats; the grid container itself never loops. See the `bricks-build-page` skill. A `link` on a section/container/block/div renders only when `tag` is "a" — without it the element stays a plain div and the link is silently ignored.',
        'block'
            => 'Block-level grouping wrapper for clustering related elements inside a `container`. For an entire layout band prefer `container`; reach for `block` when a nested grouping inside a container is specifically needed — including the repeating card of a query loop (`hasLoop: true` + `query` go on this block, as the direct child of the grid container). A `link` on a section/container/block/div renders only when `tag` is "a" — without it the element stays a plain div and the link is silently ignored.',
        'div'
            => 'Unstyled grouping wrapper (no default layout controls beyond the shared ones). A `link` renders only when `tag` is "a" — without it the element stays a plain div and the link is silently ignored.',
        'form'
            => '`actions` is a multi-value select stored as a list of option keys (e.g. ["custom"], ["email", "redirect"]) — Bricks iterates it at render time, so a bare string breaks the page; the validator rejects it. Fields go in `fields` (list of objects) and the submit label in `submitButtonText`.',
        'text-basic'
            => 'Renders inside the tag set in `settings.tag`, which falls back to `<div>` when unset (Bricks treats the tag control\'s `placeholder` as the effective default). For prose paragraphs, set `settings.tag = "p"` explicitly to produce semantic markup that assistive tech and SEO crawlers expect. The `<div>` fallback is fine for non-prose wrappers (badges, labels, layout text that does not represent a paragraph). One paragraph per element — never a whole section\'s HTML in `text`.',
        default => null,
    };
}

/**
 * Basic metadata shared by every catalog entry shape: label, icon,
 * category, keywords, scripts, the `nestable` flag and the shared-control
 * family, plus the curated note when there is one.
 *
 * @param class-string<\Bricks\Element> $element_class
 * @return array<string, mixed>
 */
function bricks_element_base_entry(string $name, string $element_class): array
{
    $entry = ['name' => $name];

    if (!class_exists($element_class)) {
        return $entry;
    }

    try {
        $instance = new $element_class();

        $entry['label'] = $instance->label ?? $name;
        $entry['icon'] = $instance->icon ?? '';
        $entry['category'] = $instance->category ?? '';
        $entry['keywords'] = $instance->keywords ?? [];
        $entry['scripts'] = $instance->scripts ?? [];
        $entry['nestable'] = $instance->nestable === true;
    } catch (\Throwable $e) {
        $entry['error'] = $e->getMessage();
        return $entry;
    }

    $family = bricks_element_control_family($name);
    if ($family !== null) {
        $entry['control_family'] = $family;
    }

    if (($note = bricks_element_note($name)) !== null) {
        $entry['notes'] = $note;
    }

    return $entry;
}

/**
 * Build a basic description array for a single Bricks element (no controls).
 *
 * @param string                       $name           Element name.
 * @param class-string<\Bricks\Element> $element_class  Element class name.
 * @return array
 */
function bricks_describe_element(string $name, string $element_class): array
{
    return bricks_element_base_entry($name, $element_class);
}

/**
 * Build a full description array for a single Bricks element, including controls.
 *
 * @param string                       $name           Element name.
 * @param class-string<\Bricks\Element> $element_class  Element class name.
 * @return array
 */
function bricks_describe_element_with_controls_verbose(string $name, string $element_class): array
{
    return bricks_describe_element_with_controls_internal(
        $name,
        $element_class,
        static fn(array $control): array => $control,
    );
}

/**
 * Compact variant — controls are reduced via bricks_compact_control().
 *
 * @param string                       $name           Element name.
 * @param class-string<\Bricks\Element> $element_class  Element class name.
 * @return array
 */
function bricks_describe_element_with_controls_compact(string $name, string $element_class): array
{
    return bricks_describe_element_with_controls_internal(
        $name,
        $element_class,
        static fn(array $control): array => bricks_compact_control($control),
    );
}

/**
 * Element-specific controls (every non-`_` key) from the element's fully
 * loaded control set. The `_`-prefixed shared style controls are left out of
 * this shape on purpose — they are the same for every element of a family
 * and are served by include_shared_controls=true — except on the four layout
 * elements, where the builder-only loop controls `hasLoop` / `query` are
 * added because Bricks honours them at render time.
 *
 * @param string                                                $name
 * @param class-string<\Bricks\Element>                          $element_class
 * @param callable(array<string, mixed>): array<string, mixed>  $shape
 * @return array
 */
function bricks_describe_element_with_controls_internal(string $name, string $element_class, callable $shape): array
{
    $entry = bricks_element_base_entry($name, $element_class);
    if (array_key_exists('error', $entry)) {
        return $entry;
    }

    $loaded = bricks_loaded_element_entry($name);
    $loaded_controls = bricks_loaded_element_controls($name);
    if ($loaded === null || $loaded_controls === null) {
        $entry['error'] = 'Element controls could not be loaded.';
        return $entry;
    }

    $controls = [];
    foreach ($loaded_controls as $key => $control) {
        if ($key === '' || $key[0] === '_') {
            continue;
        }
        $controls[$key] = $shape(bricks_annotate_control($control));
    }

    if (in_array($name, ['section', 'container', 'block', 'div'], strict: true)) {
        $controls['hasLoop'] = $shape(bricks_annotate_control([
            'type' => 'checkbox',
            'label' => 'Use query loop',
            'group' => 'query',
            'description' => 'Builder-only control honoured at render time: repeats this element once per query result. Put it on the direct child block of the grid container, together with `query`.',
        ]));
        $controls['query'] = $shape(bricks_annotate_control([
            'type' => 'query',
            'label' => 'Query',
            'group' => 'query',
            'description' => 'Builder-only control honoured at render time: the query loop definition for this element (requires hasLoop: true).',
        ]));
    }

    $entry['controls'] = $controls;
    $entry['control_groups'] = is_array($loaded['controlGroups'] ?? null) ? $loaded['controlGroups'] : [];
    $entry['shared_controls_hint'] = sprintf(
        'Shared style controls (%s) are not listed here — fetch them with include_shared_controls=true (element="%s" returns exactly the ones this element accepts).',
        ($entry['control_family'] ?? '') === 'layout'
            ? '_padding, _margin, _background, _typography, _widthMax, _display, _direction, _rowGap, _columnGap, _gridGap, …'
            : '_padding, _margin, _background, _typography, _widthMax, _display, _flexDirection, _gap, …',
        $name,
    );

    return $entry;
}
