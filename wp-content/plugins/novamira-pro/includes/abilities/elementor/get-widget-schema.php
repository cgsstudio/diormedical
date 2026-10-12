<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

/**
 * Ability: Unified Elementor schema reader.
 *
 * One MCP tool, two actions:
 *   action=list  → enumerate every registered widget
 *   action=get   → describe one or more widgets in compact form (default)
 *
 * Progressive disclosure is layered on top of the compact default:
 *   include_styles  include style / advanced / visibility tab controls;
 *                   pair with control_names, tab, or section whenever possible
 *   verbose         attach labels / descriptions / widget metadata
 *   control_names   restrict the controls map to those names, always verbose
 *   tab             restrict controls to a single tab (content, style, advanced, ...)
 *   section         restrict controls to a single section id
 *
 * The compact output is the same `{t, opts, def, rv, if, arr, fields}` shape
 * used by `elementor-set-content` / `elementor-add-widget` /
 * `elementor-edit-element` during validation, so a caller that asks for a
 * schema can feed the result straight back into a write call.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/elementor-get-schema', [
    'label' => __('Get Elementor Widget Schema', domain: 'novamira-pro'),
    'description' => __(
        'Discovery and exploration tool for Elementor widgets (v3 and v4) and the container element. Use this to understand what widgets exist, what controls they expose, and what enum values they accept. NOT required before writing — set-content / add-element / edit-element validate server-side on every call and return the compact schema of the affected widget INLINE in any error response. Two actions: "list" (discover) and "get" (describe). LIST without filters returns a minimal overview: {categories (alphabetically sorted list of category names — use these as "category" filter values), sources (map of package → widget count — use these as "source" filter values)}. No widget names are returned in the overview — drill into a subset with filters. LIST with any filter (name_contains, is_atomic, source, category — combined with AND) returns a flat widget list with {name, title, is_atomic, categories, source} per entry. For v4 atomic widgets use is_atomic:true. For keyword search use name_contains. GET takes widget_types (a list of strings, "__container__" allowed) and returns compact schemas {t, opts?, def?, rv?, if?, arr?, fields?} by default. For style checks, prefer include_styles:true with control_names:["padding","margin",...] or tab/section narrowing; broad include_styles:true on common v3 widgets can be very large. Use verbose:true only when labels / widget metadata are needed. The compact output shape is exactly what set-content / add-element / edit-element expect, so feed it back verbatim when you do use it.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'action' => [
                'type' => 'string',
                'description' => 'Action to perform. "list" discovers widgets: without filters it returns a grouped overview (categories, atomics, sources, counts) in a single call; with any filter set it returns a flat filtered widget list instead. "get" describes widgets in compact form.',
                'enum' => ['list', 'get'],
            ],
            'widget_types' => [
                'type' => 'array',
                'description' => 'Widget type names to describe. Required for action "get". Pass "__container__" to include the container element.',
                'items' => ['type' => 'string'],
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Filter "list" results to widgets that declare this Elementor category (e.g. "v4-elements", "basic", "dynamic-content-for-elementor-post"). Combines in AND with other filters.',
            ],
            'name_contains' => [
                'type' => 'string',
                'description' => 'Filter "list" results to widgets whose name contains this case-insensitive substring. It is a plain substring test against the widget name, not a fuzzy or word-stem match, so "heading" reaches heading and e-heading but never animated-headline — search "headline" for that one. Combines in AND with other filters.',
            ],
            'is_atomic' => [
                'type' => 'boolean',
                'description' => 'Filter "list" results to only atomic (v4) widgets when true, or only legacy (v3) widgets when false.',
            ],
            'source' => [
                'type' => 'string',
                'description' => 'Filter "list" results by the widget\'s source package. Three identifiers are fixed: "elementor" (core v3 only), "elementor-pro" (non-atomic Pro widgets), "atomic" (every atomic v4 widget and container, whichever package registers it — exactly the is_atomic:true set). Every other source is derived from the top-level PHP namespace of the widget class, lowercased with punctuation stripped — so it is NOT the plugin\'s slug and must never be spelled out from the plugin name: Dynamic Content for Elementor reports "dynamiccontentforelementor", not "dynamic-content-for-elementor". Widgets whose class carries no namespace at all share a single catch-all bucket, which holds whatever un-namespaced widgets the site has rather than one plugin\'s. Read the accepted strings off the site instead of guessing: a "list" call with no filters returns them as the keys of its "sources" map, and only those match. A source no widget reports matches nothing; the response says so and names the ones that exist.',
            ],
            'include_styles' => [
                'type' => 'boolean',
                'description' => 'For action "get": when true, also include controls from the style / advanced / visibility tabs. Defaults to false (content only). Prefer pairing this with control_names, tab, or section; broad style reads for v3 widgets can be very large.',
            ],
            'verbose' => [
                'type' => 'boolean',
                'description' => 'For action "get": when true, include widget metadata (icon, categories, keywords) and control labels / descriptions. Defaults to false.',
            ],
            'control_names' => [
                'type' => 'array',
                'description' => 'For action "get": restrict the controls map to the listed control IDs. When set, those controls are always returned in verbose form.',
                'items' => ['type' => 'string'],
            ],
            'tab' => [
                'type' => 'string',
                'description' => 'For action "get": restrict controls to a single tab (e.g. "content", "style", "advanced"). Style and advanced tabs need include_styles:true as well, since without it those controls are not in the schema to be narrowed. Only v3 widgets file their controls under tabs; atomic (v4) widgets and the atomic containers do not, so this filter matches nothing on them. Elementor also builds controls without any tab information in some contexts, and re-defaults what it dropped, so the call is refused rather than answered from that default whenever the tabs cannot be trusted — schema_fidelity names the widgets it was refused for. Use "section", which is not dropped, or "control_names".',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'For action "get": restrict controls to a single section id (e.g. "section_title"). Section ids come back on every control when you read a schema with verbose:true or control_names. Two caveats: pair it with include_styles:true to reach style sections, and it matches nothing on atomic (v4) widgets, which have no sections. Unlike "tab" it is never dropped, so it stays exact on widgets that schema_fidelity flags.',
            ],
        ],
        'required' => ['action'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'categories' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'For action "list" without filters: alphabetically sorted list of every Elementor category name declared by a registered widget. Use these strings as the "category" filter value.',
            ],
            'sources' => [
                'type' => 'object',
                'description' => 'For action "list" without filters: map of source package identifier (elementor / elementor-pro / atomic / third-party namespace) to the count of widgets from that source. Use these exact strings as the "source" filter value.',
            ],
            'widgets' => [
                'type' => ['array', 'object'],
                'description' => 'For action "list" WITH at least one filter: the filtered flat widget list, each entry containing {name, title, is_atomic, categories, source}. For action "get": a map of widget_type => schema.',
            ],
            'count' => [
                'type' => 'integer',
                'description' => 'For action "list" with filters: the number of widgets that matched.',
            ],
            'filters' => [
                'type' => 'object',
                'description' => 'For action "list" with filters: the normalized filters that were applied, echoed back so you can verify how the server interpreted them.',
            ],
            'missing' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'For action "get": widget types that were requested but could not be resolved.',
            ],
            'schema_fidelity' => [
                'type' => 'object',
                'description' => 'Present only for widget types whose control metadata is not known to be complete, mapping each to "reduced" (Elementor reports that it built the stack stripped, so absent editor metadata was dropped on the way) or "indeterminate" (this build strips controls in some contexts and does not report whether it did, so completeness could not be checked and an absence establishes nothing either way). Types, defaults and section ids are unaffected in both cases and can be used, as can the "section" and "control_names" filters. The "tab" filter is refused for any widget listed here, because a tab that was dropped is re-defaulted rather than left empty and the resulting partition is indistinguishable from a real one. A widget absent from this map — or an absent map — has metadata Elementor reports as complete.',
            ],
            'hint' => [
                'type' => 'string',
                'description' => 'Present when the response needs qualifying: a filter matched nothing and the valid values are worth naming, a read was broad enough to be worth narrowing, or some control metadata is not known to be complete. See schema_fidelity for which widgets that last case covers and on what grounds.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\elementor_get_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Execute the unified get-schema ability.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function elementor_get_schema(array $input): array
{
    if (!class_exists(\Elementor\Plugin::class)) {
        return [
            'success' => false,
            'error' => 'Elementor is not active or not fully loaded.',
        ];
    }

    $action = (string) ($input['action'] ?? '');

    if ($action === 'list') {
        return schema_handle_list($input);
    }

    if ($action === 'get') {
        return schema_get_widgets($input);
    }

    return [
        'success' => false,
        'error' => sprintf('Unknown action "%s". Use "list" or "get".', $action),
    ];
}

/**
 * Internal widget names that should never appear in list output — they are
 * abstract base classes used by Elementor for inheritance rather than real
 * widgets the caller can instantiate.
 */
const NOVAMIRA_LIST_SKIP_WIDGETS = ['common', 'common-base', 'common-optimized', 'global'];

/**
 * How many values a hint names before it starts counting instead. Enough to
 * recognize the shape of what the site has, short enough that a site with many
 * addons does not turn one wrong filter into a wall of text.
 */
const NOVAMIRA_HINT_SAMPLE_SIZE = 8;

/**
 * How far a widget's control metadata can be trusted. `full` is the only one
 * that satisfies the tab filter; `reduced` and `indeterminate` both refuse it,
 * because an answer built on tabs that were never really there is
 * indistinguishable from a correct one.
 */
const NOVAMIRA_SCHEMA_FULL = 'full';

const NOVAMIRA_SCHEMA_REDUCED = 'reduced';

const NOVAMIRA_SCHEMA_INDETERMINATE = 'indeterminate';

/**
 * Control fields that exist for the editor and are dropped when a stack is
 * built in reduced form. Which of them a given build drops has changed —
 * `description` survives on older ones — so they are reported by observing
 * which are absent from the response rather than by naming them in advance.
 */
const NOVAMIRA_SCHEMA_EDITOR_FIELDS = ['label', 'description', 'options'];

/**
 * Dispatch between the grouped overview (no filters) and the filtered flat
 * list (any filter set). Progressive disclosure: calling `list` once with no
 * filters gives the caller a complete, already-organized picture of every
 * available widget. Filters are a drill-down tool for narrow subsets.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function schema_handle_list(array $input): array
{
    $filters = schema_parse_list_filters($input);

    if ($filters !== []) {
        return schema_list_filtered($filters);
    }

    return schema_list_overview();
}

/**
 * Extract and normalize the list filters from the raw input.
 *
 * @param array<string, mixed> $input
 * @return array{category?: string, name_contains?: string, is_atomic?: bool, source?: string}
 */
function schema_parse_list_filters(array $input): array
{
    $filters = [];

    /** @var mixed $category */
    $category = $input['category'] ?? null;
    if (is_string($category) && $category !== '') {
        $filters['category'] = $category;
    }

    /** @var mixed $name_contains */
    $name_contains = $input['name_contains'] ?? null;
    if (is_string($name_contains) && $name_contains !== '') {
        $filters['name_contains'] = strtolower($name_contains);
    }

    if (array_key_exists('is_atomic', $input) && is_bool($input['is_atomic'])) {
        $filters['is_atomic'] = $input['is_atomic'];
    }

    /** @var mixed $source */
    $source = $input['source'] ?? null;
    if (is_string($source) && $source !== '') {
        $filters['source'] = $source;
    }

    return $filters;
}

/**
 * Build the minimal overview for action=list without filters. Returns only
 * what the caller needs to discover valid values for the `category` and
 * `source` filters — no widget names. For actual widget listings the caller
 * drills with a filter (name_contains / is_atomic / category / source).
 *
 * @return array<string, mixed>
 */
function schema_list_overview(): array
{
    $manager = \Elementor\Plugin::$instance->widgets_manager;
    /** @var array<string, \Elementor\Widget_Base> $types */
    $types = $manager->get_widget_types();

    /** @var array<string, bool> $categories_set */
    $categories_set = [];
    /** @var array<string, int> $sources */
    $sources = [];

    foreach ($types as $name => $widget) {
        if (!schema_widget_is_listable($name, $widget)) {
            continue;
        }

        $source = schema_detect_widget_source($widget);
        $sources[$source] = ($sources[$source] ?? 0) + 1;

        /** @var list<string> $categories */
        $categories = $widget->get_categories();
        if ($categories === []) {
            $categories = ['uncategorized'];
        }

        foreach ($categories as $category) {
            $categories_set[$category] = true;
        }
    }

    // Also include atomic container elements (e-flexbox, e-div-block).
    foreach (NOVAMIRA_ATOMIC_CONTAINER_TYPES as $atomic_type) {
        $schema = build_atomic_element_compact_schema($atomic_type);
        if ($schema === null) {
            continue;
        }
        $sources['atomic'] = ($sources['atomic'] ?? 0) + 1;
        $categories_set['v4-elements'] = true;
    }

    $categories_list = array_keys($categories_set);
    sort($categories_list);
    ksort($sources);

    return [
        'success' => true,
        'categories' => $categories_list,
        'sources' => $sources,
    ];
}

/**
 * Return a flat widget list after applying the filters. Every entry
 * includes the bits a caller usually needs to decide what to use next:
 * name, human title, atomic flag, its Elementor categories, and the
 * detected source package.
 *
 * @param array{category?: string, name_contains?: string, is_atomic?: bool, source?: string} $filters
 * @return array<string, mixed>
 */
function schema_list_filtered(array $filters): array
{
    $manager = \Elementor\Plugin::$instance->widgets_manager;
    /** @var array<string, \Elementor\Widget_Base> $types */
    $types = $manager->get_widget_types();

    $matched = [];
    foreach ($types as $name => $widget) {
        if (!schema_widget_is_listable($name, $widget)) {
            continue;
        }
        if (!schema_widget_matches_filters($name, $widget, $filters)) {
            continue;
        }

        /** @var list<string> $categories */
        $categories = $widget->get_categories();
        $matched[] = [
            'name' => $name,
            'title' => $widget->get_title(),
            'is_atomic' => is_atomic_widget($widget),
            'categories' => $categories,
            'source' => schema_detect_widget_source($widget),
        ];
    }

    // Also include atomic container elements when they match the filters.
    $matched = schema_append_atomic_containers($matched, $filters);

    $response = [
        'success' => true,
        'widgets' => $matched,
        'count' => count($matched),
        'filters' => $filters,
    ];

    if ($matched === []) {
        $hint = schema_empty_match_hint($filters);
        if ($hint !== '') {
            $response['hint'] = $hint;
        }
    }

    return $response;
}

/**
 * Explain an empty filtered listing. A `source` or `category` the site does
 * not use is the common cause and reads exactly like a plugin having no
 * widgets, so name the values that do exist rather than handing back a bare
 * zero. Both are derived rather than guessable — a source comes from a PHP
 * namespace, a category is whatever slug the widget declares — so a caller
 * that spelled one from the plugin name gets no other signal that it missed.
 *
 * @param array{category?: string, name_contains?: string, is_atomic?: bool, source?: string} $filters
 */
function schema_empty_match_hint(array $filters): string
{
    $overview = schema_list_overview();
    /** @var array<string, int> $sources */
    $sources = is_array($overview['sources'] ?? null) ? $overview['sources'] : [];
    /** @var list<string> $categories */
    $categories = is_array($overview['categories'] ?? null) ? $overview['categories'] : [];

    $parts = [];

    $source = $filters['source'] ?? null;
    if (is_string($source) && !array_key_exists($source, $sources)) {
        $parts[] = sprintf(
            'No widget on this site reports the source "%s". The sources that exist are: %s.',
            $source,
            implode(', ', array_keys($sources)),
        );
    }

    $category = $filters['category'] ?? null;
    if (is_string($category) && !in_array($category, $categories, strict: true)) {
        $parts[] = sprintf(
            'No widget on this site declares the category "%s". It has %d categories%s. List with no filters to see them all.',
            $category,
            count($categories),
            schema_sample_suffix($categories),
        );
    }

    if ($parts === []) {
        return '';
    }

    $parts[] = 'Pass one of those values back verbatim; they are read from the registry, not derived from a plugin name or slug.';

    return implode(' ', $parts);
}

/**
 * Render a short sample of a value list for a hint.
 *
 * Sources are few and worth naming in full; categories are not — a site
 * carrying several addons declares dozens, and pasting all of them into an
 * error message costs the caller more context than the answer is worth.
 *
 * @param list<string> $values
 */
function schema_sample_suffix(array $values): string
{
    if ($values === []) {
        return '';
    }

    $sample = array_slice($values, offset: 0, length: NOVAMIRA_HINT_SAMPLE_SIZE);
    $suffix = sprintf(', starting %s', implode(', ', $sample));

    $remaining = count($values) - count($sample);
    if ($remaining > 0) {
        $suffix .= sprintf(' and %d more', $remaining);
    }

    return $suffix;
}

/**
 * Append atomic container elements (e-flexbox, e-div-block) to a filtered
 * widget list when they match the given filters.
 *
 * @param list<array<string, mixed>> $matched
 * @param array{category?: string, name_contains?: string, is_atomic?: bool, source?: string} $filters
 * @return list<array<string, mixed>>
 */
function schema_append_atomic_containers(array $matched, array $filters): array
{
    foreach (NOVAMIRA_ATOMIC_CONTAINER_TYPES as $atomic_type) {
        if (!schema_atomic_container_matches_filters($atomic_type, $filters)) {
            continue;
        }

        $schema = build_atomic_element_compact_schema($atomic_type);
        if ($schema === null) {
            continue;
        }

        $matched[] = [
            'name' => $atomic_type,
            'title' => $schema['label'],
            'is_atomic' => true,
            'categories' => ['v4-elements'],
            'source' => 'atomic',
        ];
    }

    return $matched;
}

/**
 * @param array{category?: string, name_contains?: string, is_atomic?: bool, source?: string} $filters
 */
function schema_atomic_container_matches_filters(string $name, array $filters): bool
{
    $name_contains = $filters['name_contains'] ?? null;
    if (is_string($name_contains) && !str_contains(strtolower($name), $name_contains)) {
        return false;
    }
    if (array_key_exists('is_atomic', $filters) && $filters['is_atomic'] !== true) {
        return false;
    }
    $category_filter = $filters['category'] ?? null;
    if (is_string($category_filter) && $category_filter !== 'v4-elements') {
        return false;
    }
    $source_filter = $filters['source'] ?? null;
    if (is_string($source_filter) && $source_filter !== 'atomic') {
        return false;
    }

    return true;
}

/**
 * Is a given widget worth exposing in a list response? Drops internal base
 * classes and widgets with empty titles (abstract / development-only).
 */
function schema_widget_is_listable(string $name, \Elementor\Widget_Base $widget): bool
{
    if (in_array($name, NOVAMIRA_LIST_SKIP_WIDGETS, strict: true)) {
        return false;
    }

    if (trim($widget->get_title()) === '') {
        return false;
    }

    return true;
}

/**
 * Apply the AND-combined filter set to a single widget.
 *
 * @param array{category?: string, name_contains?: string, is_atomic?: bool, source?: string} $filters
 */
function schema_widget_matches_filters(string $name, \Elementor\Widget_Base $widget, array $filters): bool
{
    $name_contains = $filters['name_contains'] ?? null;
    if (is_string($name_contains) && !str_contains(strtolower($name), $name_contains)) {
        return false;
    }

    if (array_key_exists('is_atomic', $filters) && is_atomic_widget($widget) !== $filters['is_atomic']) {
        return false;
    }

    $category_filter = $filters['category'] ?? null;
    if (is_string($category_filter)) {
        /** @var list<string> $categories */
        $categories = $widget->get_categories();
        if (!in_array($category_filter, $categories, strict: true)) {
            return false;
        }
    }

    $source_filter = $filters['source'] ?? null;
    if (is_string($source_filter) && schema_detect_widget_source($widget) !== $source_filter) {
        return false;
    }

    return true;
}

/**
 * Detect the source package of a widget from its PHP class namespace.
 * Returns a stable identifier the caller can feed back into a `source`
 * filter — the values are exactly what the grouped overview surfaces in
 * its `sources` field so the caller never has to guess.
 */
function schema_detect_widget_source(\Elementor\Widget_Base $widget): string
{
    $class = get_class($widget);

    // Atomic (v4) widgets ship under the same namespaces as the v3 ones —
    // core atomics under `Elementor\`, Pro atomics under `ElementorPro\` — so
    // the namespace alone cannot tell them apart. `atomic` is deliberately one
    // source across packages, and the same check backs the `is_atomic` filter,
    // so the two always describe the same set.
    if (is_atomic_widget($widget)) {
        return 'atomic';
    }

    if (str_starts_with($class, 'ElementorPro\\')) {
        return 'elementor-pro';
    }

    if (str_starts_with($class, 'Elementor\\')) {
        return 'elementor';
    }

    $parts = explode('\\', $class);
    if (count($parts) > 1) {
        return sanitize_title($parts[0]);
    }

    return 'unknown';
}

/**
 * Dispatch for action=get. Accepts one or many widget types and returns a
 * compact or verbose schema for each.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function schema_get_widgets(array $input): array
{
    /** @var mixed $raw_types */
    $raw_types = $input['widget_types'] ?? null;
    if (!is_array($raw_types) || $raw_types === []) {
        return [
            'success' => false,
            'error' => 'Parameter "widget_types" must be a non-empty array of widget type names when action is "get".',
        ];
    }

    $opts = schema_parse_get_options($input);

    $widgets = [];
    $missing = [];
    $fidelity = [];
    $absent = [];

    /** @var mixed $raw */
    foreach ($raw_types as $raw) {
        if (!is_string($raw) || $raw === '') {
            continue;
        }

        $meta = schema_control_metadata($raw);
        if ($meta['fidelity'] !== NOVAMIRA_SCHEMA_FULL) {
            $fidelity[$raw] = $meta['fidelity'];
            // Kept per widget. Merging them would let one widget's missing
            // labels and another's missing enums add up to a claim about a
            // control that neither of them has.
            $absent[$raw] = $meta['absent'];
        }

        $schema = schema_build_single($raw, $opts, $meta['locations']);
        if ($schema === null) {
            $missing[] = $raw;
            continue;
        }

        $widgets[$raw] = $schema;
    }

    // Only a stack known to be whole can answer a tab filter. A stripped one
    // carries no tab at all and Elementor re-defaults the missing value, so
    // the filter would not come back empty — it would answer "content" with
    // every control the widget has and "style" with none, both looking exactly
    // like real answers. An undecided one could do either. Refuse both.
    if ($opts['tab'] !== '' && $fidelity !== []) {
        return schema_refuse_tab_filter($fidelity);
    }

    $response = [
        'success' => true,
        'widgets' => $widgets,
    ];

    if ($missing !== []) {
        $response['missing'] = $missing;
    }

    if ($fidelity !== []) {
        $response['schema_fidelity'] = $fidelity;
    }

    $hint = $fidelity === [] ? schema_get_response_hint($opts) : schema_fidelity_hint($fidelity, $absent);
    if ($hint !== '') {
        $response['hint'] = $hint;
    }

    return $response;
}

/**
 * Refuse a tab filter the stacks cannot support, saying which widgets and on
 * what grounds, and naming the two filters that are exact in the same place.
 *
 * @param array<string, string> $fidelity
 * @return array<string, mixed>
 */
function schema_refuse_tab_filter(array $fidelity): array
{
    $named = [];
    foreach ($fidelity as $widget => $state) {
        $named[] = $state === NOVAMIRA_SCHEMA_REDUCED
            ? sprintf('%s (built without tab information)', $widget)
            : sprintf('%s (this build does not report whether tab information was kept)', $widget);
    }

    return [
        'success' => false,
        'error' => sprintf(
            'The "tab" filter cannot be honoured for %s. Elementor builds control stacks in a reduced form in some contexts, dropping the tab of every control, and what it drops is re-defaulted afterwards — so every control reports the same tab and a filter on one would answer with the whole widget or with nothing, either of them looking like a real answer. Use "section", which is not dropped, or "control_names" to name the controls you want; both are exact here.',
            implode(', ', $named),
        ),
        'schema_fidelity' => $fidelity,
    ];
}

/**
 * Qualify a response whose control metadata is not known to be complete.
 *
 * Says what was observed and separates the two grounds for saying it. A
 * `reduced` widget was built stripped and its missing fields are missing
 * because of that. An `indeterminate` one is not a claim about anything: the
 * build reduces without saying whether it did, so completeness could not be
 * checked, and the fields absent from it may be absent because the widget has
 * none. Presenting the second as the first would trade one false certainty for
 * another.
 *
 * Absences are reported per widget for the same reason — a union across
 * widgets asserts of each what was only true of one.
 *
 * It prescribes no transport: which contexts are spared has changed between
 * builds, so the obvious suggestion is wrong exactly where it would be needed,
 * and is in any case the transport that just produced this answer.
 *
 * @param array<string, string> $fidelity
 * @param array<string, list<string>> $absent
 */
function schema_fidelity_hint(array $fidelity, array $absent): string
{
    $clauses = [];
    foreach ($fidelity as $widget => $state) {
        $fields = $absent[$widget] ?? [];
        $observed = $fields === [] ? '' : sprintf(' — no control in it carries %s', schema_join_fields($fields));

        $clauses[] = $state === NOVAMIRA_SCHEMA_REDUCED
            ? sprintf('%s was built with its editor metadata stripped%s', $widget, $observed)
            : sprintf('%s could not be checked for completeness%s', $widget, $observed);
    }

    $hint = 'Schema metadata is not known to be complete here: ' . implode('; ', $clauses) . '.';

    if (in_array(NOVAMIRA_SCHEMA_REDUCED, $fidelity, strict: true)) {
        $hint .=
            ' Where a widget was stripped, read a missing enum as values that were dropped on the way'
            . ' rather than as a control that accepts anything.';
    }

    if (in_array(NOVAMIRA_SCHEMA_INDETERMINATE, $fidelity, strict: true)) {
        $hint .=
            ' Where completeness could not be checked, treat what is absent as unreported rather than as'
            . ' established either way.';
    }

    $hint .=
        ' Types, defaults and section ids are unaffected in both cases, and "section" and "control_names"'
        . ' stay exact. Re-issuing the same call will not return more: the build and the context decide this,'
        . ' not the request.';

    return $hint;
}

/**
 * Join field names into readable prose: "a label", "a label or an options
 * list", "a label, a description or an options list".
 *
 * @param list<string> $fields
 */
function schema_join_fields(array $fields): string
{
    $words = [];
    foreach ($fields as $field) {
        $words[] = match ($field) {
            'label' => 'a label',
            'description' => 'a description',
            'options' => 'an options list',
            default => $field,
        };
    }

    if (count($words) === 1) {
        return $words[0];
    }

    $last = (string) array_pop($words);
    return implode(', ', $words) . ' or ' . $last;
}

/**
 * Pick the hint worth attaching to a "get" response that came back whole.
 * Both of these only suggest a cheaper or richer read; a response that came
 * back short of that is qualified by schema_fidelity_hint() instead, because
 * that one is about the answer's accuracy rather than its shape.
 *
 * @param array{include_styles: bool, verbose: bool, control_names: list<string>, tab: string, section: string} $opts
 */
function schema_get_response_hint(array $opts): string
{
    $narrowed = $opts['control_names'] !== [] || $opts['tab'] !== '' || $opts['section'] !== '';

    if ($opts['include_styles'] && !$narrowed) {
        return 'Broad include_styles:true reads can be large for v3 widgets. Prefer control_names:["padding","margin",...] or tab / section when checking for specific styling controls.';
    }

    if (!$opts['verbose'] && $opts['control_names'] === []) {
        return 'Compact view. Pass control_names:["id",...] to zoom into specific controls, include_styles:true plus control_names / tab / section for style controls, or verbose:true only when labels / widget metadata are needed.';
    }

    return '';
}

/**
 * Normalize the "get" input into an internal options struct used by every
 * downstream helper.
 *
 * @param array<string, mixed> $input
 * @return array{include_styles: bool, verbose: bool, control_names: list<string>, tab: string, section: string}
 */
function schema_parse_get_options(array $input): array
{
    /** @var list<string> $control_names */
    $control_names = [];
    if (array_key_exists('control_names', $input) && is_array($input['control_names'])) {
        /** @var mixed $name */
        foreach ($input['control_names'] as $name) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            $control_names[] = $name;
        }
    }

    return [
        'include_styles' => ($input['include_styles'] ?? false) === true,
        'verbose' => ($input['verbose'] ?? false) === true,
        'control_names' => $control_names,
        'tab' => (string) ($input['tab'] ?? ''),
        'section' => (string) ($input['section'] ?? ''),
    ];
}

/**
 * Build the schema for a single widget, applying progressive-disclosure
 * filters (tab, section, control_names, verbose).
 *
 * @param array{include_styles: bool, verbose: bool, control_names: list<string>, tab: string, section: string} $opts
 * @param array<string, array{tab: string, section: string}> $locations
 * @return array<string, mixed>|null
 */
function schema_build_single(string $widget_type, array $opts, array $locations): ?array
{
    $extract_opts = ['include_styles' => $opts['include_styles']];
    $schema = resolve_compact_schema($widget_type, $extract_opts);
    if ($schema === null) {
        return null;
    }

    $schema = schema_apply_drilldown_filters($schema, $opts, $locations);

    if ($opts['verbose'] || $opts['control_names'] !== []) {
        $schema = schema_enrich_verbose($widget_type, $schema);
    }

    return $schema;
}

/**
 * Where each control of a widget sits, plus whether the stack it was read from
 * is one Elementor built in reduced form.
 *
 * The compact entry deliberately carries neither tab nor section — they
 * describe the editor panel rather than the value, and the compact shape is
 * fed straight back into write calls — so the tab / section filters have to
 * read them from the widget itself. Anything with no tabbed panel of its own
 * gets an empty map: atomic (v4) widgets and the atomic containers organize
 * their props differently and have nothing to match against.
 *
 * @return array{locations: array<string, array{tab: string, section: string}>, fidelity: string, absent: list<string>}
 */
function schema_control_metadata(string $widget_type): array
{
    $empty = ['locations' => [], 'fidelity' => NOVAMIRA_SCHEMA_FULL, 'absent' => []];

    if (in_array($widget_type, NOVAMIRA_ATOMIC_CONTAINER_TYPES, strict: true)) {
        return $empty;
    }

    if ($widget_type === NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY) {
        $container = \Elementor\Plugin::$instance->elements_manager->get_element_types('container');
        if (!$container instanceof \Elementor\Element_Base) {
            return $empty;
        }
        /** @var array<string, mixed> $raw_controls */
        $raw_controls = $container->get_controls();
        return schema_read_control_metadata($raw_controls, schema_reduction_predicate());
    }

    $widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types($widget_type);
    if (!$widget instanceof \Elementor\Widget_Base || is_atomic_widget($widget)) {
        return $empty;
    }

    /** @var array<string, mixed> $raw_controls */
    $raw_controls = $widget->get_controls();
    return schema_read_control_metadata($raw_controls, schema_reduction_predicate());
}

/**
 * Elementor's own answer to "am I building reduced control stacks", or null
 * where this build reduces but publishes no answer.
 *
 * Read rather than reasoned about. The helper caches its verdict in a static on
 * first call and `Controls_Stack::add_control()` consults that same cached value
 * while it builds, so within a request the two cannot drift: it is a faithful
 * record of how the stacks in front of us were made, whatever the terms of the
 * test happen to be on this build. Which is why it is read as evidence and
 * never re-derived — the terms have changed between builds, and older ones do
 * not exempt REST requests at all.
 *
 * Three answers, and the third is not a failure. Builds old enough to predate
 * the whole optimization have nothing to ask, and nothing to fear either: they
 * never strip a control, so `false` is a fact about them rather than a guess.
 * Detected by the absence of both gates, since that is what "this build cannot
 * reduce" looks like. Between the two eras sits a line that reduces from a
 * private method and publishes nothing; there the honest answer is null, and
 * the caller must not claim completeness it cannot check.
 *
 * Nothing here calls into the private gate or touches any state: its presence
 * is tested by name only.
 */
function schema_reduction_predicate(): ?bool
{
    if (
        class_exists(\Elementor\Core\Frontend\Performance::class)
        && method_exists(\Elementor\Core\Frontend\Performance::class, 'should_optimize_controls')
    ) {
        return \Elementor\Core\Frontend\Performance::should_optimize_controls() === true;
    }

    // No public helper. Either this build keeps the same decision in a private
    // method — reduction happens and cannot be read — or it has no such
    // decision at all, and no control is ever stripped.
    if (method_exists(\Elementor\Controls_Stack::class, 'should_optimize_controls')) {
        return null;
    }

    return false;
}

/**
 * Read the tab / section of every control, and judge how much of the control
 * metadata survived into the stack we are looking at.
 *
 * Three answers, because two are not enough. A stack is not simply whole or
 * stripped: `Controls_Stack::add_control()` is where the stripping happens,
 * but `update_control()` reaches the stack through
 * `Controls_Manager::update_control_in_stack()`, which merges the caller's
 * fields onto what is already stored and hands the result to
 * `add_control_to_stack()` directly — never passing the stripping block again.
 * So a plugin that relabels one control on a stripped stack puts a label back
 * without putting the tabs back, and the widget then looks whole by any test
 * that counts labels while its tabs are still the default the manager filled
 * in. That is not rare: on an ordinary site Elementor Pro's own query widgets
 * arrive this way, one or two labels among several hundred controls.
 *
 * So `full` and `reduced` are claims, and `indeterminate` is the honest answer
 * whenever the evidence does not settle it. The tab filter is served only by
 * `full`; the other two refuse, because a wrong tab answer looks exactly like
 * a right one.
 *
 * `$optimizing` is Elementor's own verdict where it publishes one, and it is
 * the only thing that ever yields `full`: it is what governed the build, and
 * no arrangement of the controls can stand in for it. The shape is consulted
 * in one direction only — it can withdraw a `full` the verdict claimed, when
 * what came back flatly contradicts it, and it can never grant one. Where
 * there is no verdict to read the answer is `indeterminate` whatever the
 * controls look like, because a stack that was stripped and written back over
 * is indistinguishable from one that was never stripped at all.
 *
 * @param array<string, mixed> $raw_controls
 * @return array{locations: array<string, array{tab: string, section: string}>, fidelity: string, absent: list<string>}
 */
function schema_read_control_metadata(array $raw_controls, ?bool $optimizing): array
{
    $locations = [];
    $labelled = 0;
    $tabs = [];
    $present = [];

    /** @var mixed $raw */
    foreach ($raw_controls as $cid => $raw) {
        if (!is_array($raw)) {
            continue;
        }

        $tab = (string) ($raw['tab'] ?? '');
        $locations[$cid] = ['tab' => $tab, 'section' => (string) ($raw['section'] ?? '')];
        $tabs[$tab] = true;

        if (($raw['label'] ?? '') !== '') {
            $labelled++;
        }

        foreach (NOVAMIRA_SCHEMA_EDITOR_FIELDS as $field) {
            /** @var mixed $value */
            $value = $raw[$field] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $present[$field] = true;
        }
    }

    $absent = [];
    foreach (NOVAMIRA_SCHEMA_EDITOR_FIELDS as $field) {
        if (array_key_exists($field, $present)) {
            continue;
        }
        $absent[] = $field;
    }

    return [
        'locations' => $locations,
        'fidelity' => schema_judge_fidelity(count($locations), $labelled, count($tabs), $optimizing),
        'absent' => $absent,
    ];
}

/**
 * Decide how far the control metadata can be trusted, from the cause alone.
 *
 * `full` is claimed only when Elementor states it was not optimizing — never
 * from the shape of what came back. Three attempts at a shape that proves
 * completeness have now each been falsified by the same mechanism, and the
 * fourth would be too: `Controls_Manager::update_control_in_stack()` merges a
 * caller's fields onto what is stored and calls `add_control_to_stack()`
 * directly, without passing the stripping block again, so any field can be
 * written back into a stripped stack after the fact. Labels were the second
 * attempt. Varied tabs were the third, and `Controls_Stack::update_control()`
 * defeats them outright: updating a section control propagates its own
 * `tab` — `get_section_args()` copies exactly `tab` and `condition` — to every
 * control in that section, so one restored section is enough to make a
 * stripped stack show two tabs.
 *
 * There is no shape left to trust, because a reconstructed stack and a whole
 * one are the same thing seen from here. So the shape is used only to
 * contradict, never to confirm: it can withdraw a `full` the cause claimed,
 * and it can never grant one.
 *
 * The cost is that `tab` is refused on the builds that reduce without saying
 * so. That is the intended trade — a refused filter is recoverable, a false
 * partition returned as success is not — and it is narrower than it sounds,
 * because builds that predate the optimization report `false` from absence of
 * the machinery rather than from any inference about their controls.
 */
function schema_judge_fidelity(int $controls, int $labelled, int $distinct_tabs, ?bool $optimizing): string
{
    if ($optimizing === true) {
        // Conclusive on its own, and not cross-checked: Elementor says these
        // stacks were built stripped, and anything that looks whole in one is
        // something written back afterwards rather than something that
        // survived.
        return NOVAMIRA_SCHEMA_REDUCED;
    }

    if ($optimizing === null) {
        // This build reduces and will not say whether it did. Nothing in the
        // controls can settle it — see the note above — so the answer is that
        // it is unsettled, whatever the shape suggests.
        return NOVAMIRA_SCHEMA_INDETERMINATE;
    }

    if ($controls === 0) {
        return NOVAMIRA_SCHEMA_FULL;
    }

    // The cause says nothing was stripped. Believe it, but not past a shape
    // that flatly contradicts it — a stack with no labels at all and one tab
    // for every control is what stripping leaves, and a predicate that
    // disagrees with that has been overtaken by something. Report the
    // disagreement instead of picking the side that reads better.
    $shape_contradicts = $distinct_tabs <= 1 && $labelled === 0;

    return $shape_contradicts ? NOVAMIRA_SCHEMA_INDETERMINATE : NOVAMIRA_SCHEMA_FULL;
}

/**
 * Apply the tab / section / control_names filters to a compact schema's
 * controls map. Operates on the compact shape produced by the extractor,
 * reading each control's tab and section from `$locations` because the
 * compact entry does not carry them.
 *
 * @param array<string, mixed> $schema
 * @param array{include_styles: bool, verbose: bool, control_names: list<string>, tab: string, section: string} $opts
 * @param array<string, array{tab: string, section: string}> $locations
 * @return array<string, mixed>
 */
function schema_apply_drilldown_filters(array $schema, array $opts, array $locations): array
{
    if ($opts['control_names'] === [] && $opts['tab'] === '' && $opts['section'] === '') {
        return $schema;
    }

    /** @var array<string, array<string, mixed>> $controls */
    $controls = is_array($schema['controls'] ?? null) ? $schema['controls'] : [];

    $filtered = [];
    $name_filter = $opts['control_names'] === [] ? null : array_flip($opts['control_names']);

    foreach ($controls as $cid => $entry) {
        if ($name_filter !== null && !array_key_exists($cid, $name_filter)) {
            continue;
        }
        if ($opts['tab'] !== '' && ($locations[$cid]['tab'] ?? '') !== $opts['tab']) {
            continue;
        }
        if ($opts['section'] !== '' && ($locations[$cid]['section'] ?? '') !== $opts['section']) {
            continue;
        }
        $filtered[$cid] = $entry;
    }

    $schema['controls'] = $filtered;
    return $schema;
}

/**
 * Enrich a compact schema with verbose metadata: widget-level icon /
 * categories / keywords, and per-control label / description. The original
 * compact fields (t/opts/def/...) are preserved so downstream validators can
 * still consume the response.
 *
 * @param array<string, mixed> $schema
 * @return array<string, mixed>
 */
function schema_enrich_verbose(string $widget_type, array $schema): array
{
    if ($widget_type === NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY) {
        $schema['is_atomic'] = false;
        return $schema;
    }

    // Atomic container elements — already enriched by build_atomic_element_compact_schema.
    if (in_array($widget_type, NOVAMIRA_ATOMIC_CONTAINER_TYPES, strict: true)) {
        $schema['categories'] = ['v4-elements'];
        return $schema;
    }

    $manager = \Elementor\Plugin::$instance->widgets_manager;
    $widget = $manager->get_widget_types($widget_type);
    if (!$widget instanceof \Elementor\Widget_Base) {
        return $schema;
    }

    $schema['icon'] = $widget->get_icon();
    /** @var list<string> $categories */
    $categories = $widget->get_categories();
    $schema['categories'] = $categories;
    /** @var list<string> $keywords */
    $keywords = $widget->get_keywords();
    $schema['keywords'] = $keywords;
    $schema['is_atomic'] = is_atomic_widget($widget);

    if (is_atomic_widget($widget)) {
        return $schema;
    }

    /** @var array<string, array<string, mixed>> $raw_controls */
    $raw_controls = $widget->get_controls();
    /** @var array<string, array<string, mixed>> $compact_controls */
    $compact_controls = is_array($schema['controls'] ?? null) ? $schema['controls'] : [];

    foreach ($compact_controls as $cid => $entry) {
        $raw = $raw_controls[$cid] ?? null;
        if (!is_array($raw)) {
            continue;
        }
        $compact_controls[$cid] = schema_attach_verbose_fields($entry, $raw);
    }

    $schema['controls'] = $compact_controls;
    return $schema;
}

/**
 * Merge the interesting verbose fields (label / description / placeholder /
 * tab / section) from a raw control definition into a compact entry.
 *
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function schema_attach_verbose_fields(array $entry, array $raw): array
{
    foreach (['label', 'description', 'placeholder', 'tab', 'section'] as $field) {
        if (!array_key_exists($field, $raw)) {
            continue;
        }
        /** @var mixed $value */
        $value = $raw[$field];
        if ($value === '' || $value === null) {
            continue;
        }
        $entry[$field] = $value;
    }
    return $entry;
}

/**
 * Whether a widget instance is an atomic (v4) widget.
 */
function is_atomic_widget(\Elementor\Widget_Base $widget): bool
{
    if (!class_exists(\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base::class)) {
        return false;
    }

    return $widget instanceof \Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
}
