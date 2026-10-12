<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-get-element-schema', [
    'label' => __('Get Breakdance Element Schema', domain: 'novamira-pro'),
    'description' => __(
        'Progressive-disclosure schema for a Breakdance element\'s controls — the `content.*`, `design.*` and `settings.*` property PATHS, each control\'s TYPE, and (for enum controls) the allowed values — so you can style an element without guessing. Pass `type` (e.g. `EssentialElements\\Heading`). With no other argument you get a compact GROUP MAP: per tab, each top-level control group with its `path` prefix and leaf-control `count` — cheap, never the whole tree. Drill into a group with `group` (a group slug or dotted path, e.g. `spacing` or `design.typography`) or find controls with `search` to get the leaf rows: `path` (exactly what add-element/edit-element expect under `properties`), `label`, `type` (unit, color, typography, dropdown, …), `value` (a shape hint — e.g. a `unit` is `{"number":24,"unit":"px"}`), and `options` (enum values for dropdown/button_bar). The `settings` tab includes the builder-level per-element settings shared by every element type: CSS classes (`settings.advanced.classes`), display conditions (`settings.conditions.*`), and the draft flag (`settings.advanced.draft`). The exact serialized value of compound controls (typography, …) is best confirmed by setting it once and reading it back with get-element.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Element type slug, e.g. `EssentialElements\\Section`. Casing is normalised against the live registry.',
            ],
            'tab' => [
                'type' => 'string',
                'description' => 'Limit to one tab: `content`, `design`, or `settings`.',
            ],
            'group' => [
                'type' => 'string',
                'description' => 'Drill into a control group by slug or dotted path (e.g. `spacing`, `design.typography`). Returns the leaf controls under it.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match on a control\'s path or label.',
            ],
        ],
        'required' => ['type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string'],
            'groups' => ['type' => 'array'],
            'controls' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['type', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_get_element_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call with just `type` first for the group map, then drill with `group` or `search`. Paths returned here are the exact `properties` paths for add-element / edit-element (e.g. `design.spacing.padding`, `content.content.text`, `settings.advanced.classes`). When editing `settings.*`, remember edit-element replaces top-level `properties` keys whole: read the element first and send back the complete `settings` object. Pair with the SKILL\'s style hierarchy — prefer global settings / variables / global classes over raw per-element values.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_get_element_schema(array $input): array|WP_Error
{
    $type = isset($input['type']) && is_string($input['type']) ? trim($input['type']) : '';
    if ($type === '') {
        return bd_error('bd_invalid_input', __(
            '`type` is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    $resolved = bd_resolve_element_type($type);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }

    $tab_filter = isset($input['tab']) && is_string($input['tab']) ? strtolower(trim($input['tab'])) : null;
    $group = bd_schema_string_arg($input, key: 'group');
    $search = bd_schema_string_arg($input, key: 'search');

    $indexed = bd_schema_index($resolved, $tab_filter);

    if ($group === null && $search === null) {
        return [
            'type' => $resolved,
            'groups' => bd_schema_top_groups($indexed['groups']),
            'total' => count($indexed['groups']),
        ];
    }

    $rows = bd_schema_filter_leaves($indexed['leaves'], $group, $search);
    return ['type' => $resolved, 'controls' => $rows, 'total' => count($rows)];
}

/**
 * Read a trimmed non-empty string input, or null.
 *
 * @param array<string, mixed> $input
 */
function bd_schema_string_arg(array $input, string $key): ?string
{
    if (!isset($input[$key]) || !is_string($input[$key])) {
        return null;
    }
    $value = trim($input[$key]);
    return $value === '' ? null : $value;
}

/**
 * Collect a resolved element's control tabs and flatten them into a leaf map
 * (keyed by path) and a group map (keyed by path).
 *
 * @return array{leaves: array<string, array<string, mixed>>, groups: array<string, array{tab: string, group: string, path: string, depth: int, count: int}>}
 */
function bd_schema_index(string $class_slug, ?string $tab_filter): array
{
    bd_schema_require_presets();
    $class = bd_element_classes()[$class_slug] ?? $class_slug;
    $leaves = [];
    $groups = [];
    foreach ([
        'content' => 'contentControls',
        'design' => 'designControls',
        'settings' => 'settingsControls',
    ] as $tab => $method) {
        if ($tab_filter !== null && $tab !== $tab_filter || !method_exists($class, $method)) {
            continue;
        }
        /** @var callable $cb */
        $cb = [$class, $method];
        /** @var mixed $tree */
        $tree = $cb();
        if (is_array($tree)) {
            bd_schema_walk($tree, $tab, depth: 1, leaves: $leaves, groups: $groups);
        }
    }
    // The builder's Settings tab (CSS classes, display conditions, draft) is
    // UI-injected and identical for every element — `settingsControls()`
    // returns [] across all of Breakdance 2.8 — so those per-node paths never
    // come out of the control trees. Expose them as synthetic rows (paths
    // verified against render/renderer.php and conditions/should-show-node.php).
    if ($tab_filter === null || $tab_filter === 'settings') {
        $builder_rows = bd_schema_builder_settings_rows();
        foreach ($builder_rows as $path => $row) {
            $leaves[$path] = $row;
        }
        foreach (['advanced', 'conditions'] as $slug) {
            $prefix = 'settings.' . $slug;
            $count = count(array_filter(array_keys($builder_rows), static fn(string $p): bool => str_starts_with(
                $p,
                $prefix . '.',
            )));
            $groups[$prefix] = [
                'tab' => 'settings',
                'group' => $slug,
                'path' => $prefix,
                'depth' => 1,
                'count' => $count,
            ];
        }
    }
    return ['leaves' => $leaves, 'groups' => $groups];
}

/**
 * Load Breakdance's preset registry before reading control trees. Elements with
 * preset-backed control groups (PostsLoop `design.list`, Div `design.layout_v2`
 * / `design.background`, …) resolve those groups through
 * `PresetSections\getPresetSection()`, which returns a single placeholder
 * control labelled "Error" when the preset registry has not been populated.
 * The builder populates it by calling `requirePresetsAndGetData()` before
 * reading any controls (elements-for-builder.php); we mirror that order here.
 * Guarded with `function_exists` so the ability degrades gracefully when
 * Breakdance is absent or the entrypoint is renamed, and run once per request
 * (the preset files self-register on require_once).
 */
function bd_schema_require_presets(): void
{
    static $loaded = false;
    // @mago-expect analysis:impossible-condition
    if ($loaded) {
        return;
    }
    $loaded = true;
    if (function_exists('\\Breakdance\\Elements\\PresetSections\\requirePresetsAndGetData')) {
        \Breakdance\Elements\PresetSections\requirePresetsAndGetData();
    }
}

/**
 * Synthetic leaf rows for the builder-injected Settings tab, shared by every
 * element type. These are real per-node `properties` paths the Breakdance
 * renderer reads; they just have no PHP control definition to walk. Writing
 * them goes through add-element / edit-element like any other path — but
 * remember edit-element merges at the TOP level of `properties`, so send the
 * full `settings` object (get-element first) or sibling settings keys are lost.
 *
 * @return array<string, array<string, mixed>>
 */
function bd_schema_builder_settings_rows(): array
{
    return [
        'settings.advanced.classes' => [
            'path' => 'settings.advanced.classes',
            'label' => 'CSS Classes',
            'type' => 'class_list',
            'responsive' => false,
            'value' => '["hero-card","btn-primary"]  (class-name strings, no leading dot; global class names apply that class\'s styles)',
        ],
        'settings.advanced.draft' => [
            'path' => 'settings.advanced.draft',
            'label' => 'Draft (hide on the front end without deleting)',
            'type' => 'toggle',
            'responsive' => false,
            'value' => 'true | false',
        ],
        'settings.conditions.visible' => [
            'path' => 'settings.conditions.visible',
            'label' => 'Visible',
            'type' => 'toggle',
            'responsive' => false,
            'value' => 'true | false  (false hides the element entirely)',
        ],
        'settings.conditions.conditions' => [
            'path' => 'settings.conditions.conditions',
            'label' => 'Display Conditions',
            'type' => 'rule_groups',
            'responsive' => false,
            'value' =>
                'same rule-group shape as breakdance-set-template-conditions '
                    . '(groups OR, rules AND; discover slugs with list-template-conditions)',
        ],
    ];
}

/**
 * The top-level (depth 1) groups, projected to the compact overview row.
 *
 * @param array<string, array{tab: string, group: string, path: string, depth: int, count: int}> $groups
 * @return list<array{tab: string, group: string, path: string, count: int}>
 */
function bd_schema_top_groups(array $groups): array
{
    $top = [];
    foreach ($groups as $g) {
        if ($g['depth'] !== 1) {
            continue;
        }
        $top[] = ['tab' => $g['tab'], 'group' => $g['group'], 'path' => $g['path'], 'count' => $g['count']];
    }
    return $top;
}

/**
 * Leaf controls whose path/label match the optional group and search filters.
 *
 * @param array<string, array<string, mixed>> $leaves
 * @return list<array<string, mixed>>
 */
function bd_schema_filter_leaves(array $leaves, ?string $group, ?string $search): array
{
    $rows = [];
    foreach ($leaves as $leaf) {
        $path = isset($leaf['path']) && is_string($leaf['path']) ? $leaf['path'] : '';
        $label = isset($leaf['label']) && is_string($leaf['label']) ? $leaf['label'] : '';
        if ($group !== null && stripos($path, $group) === false) {
            continue;
        }
        if ($search !== null && stripos($path, $search) === false && stripos($label, $search) === false) {
            continue;
        }
        $rows[] = $leaf;
    }
    return $rows;
}

/**
 * Walk a Breakdance control tree, collecting leaf controls (with their full
 * `properties` path) into $leaves and grouping nodes into $groups. A node is a
 * group when its `options.type` is `section` or it has children and no own
 * control type; otherwise it is a leaf. The path is the tab name plus the chain
 * of control slugs (e.g. `design.spacing.padding`, `content.content.text`).
 *
 * @param array<array-key, mixed> $nodes
 * @param array<string, array<string, mixed>> $leaves
 * @param array<string, array{tab: string, group: string, path: string, depth: int, count: int}> $groups
 */
function bd_schema_walk(array $nodes, string $prefix, int $depth, array &$leaves, array &$groups): void
{
    $tab = explode('.', $prefix)[0];
    foreach (bd_schema_array_nodes($nodes) as $node) {
        $slug = isset($node['slug']) && is_string($node['slug']) ? $node['slug'] : '';
        if ($slug === '') {
            continue;
        }
        $path = $prefix . '.' . $slug;
        if (!bd_schema_is_group($node)) {
            $leaves[$path] = bd_schema_leaf_row($node, $path);
            continue;
        }
        $children = bd_schema_children($node);
        $groups[$path] = [
            'tab' => $tab,
            'group' => $slug,
            'path' => $path,
            'depth' => $depth,
            'count' => bd_schema_count_leaves($children),
        ];
        bd_schema_walk($children, $path, $depth + 1, $leaves, $groups);
    }
}

/**
 * Keep only the array-shaped entries of a (possibly mixed) node list. Centralises
 * the one place that iterates Breakdance's untyped control arrays.
 *
 * @param array<array-key, mixed> $nodes
 * @return list<array<array-key, mixed>>
 */
function bd_schema_array_nodes(array $nodes): array
{
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $out[] = $node;
    }
    return $out;
}

/**
 * Child control nodes of a control, or [] when none.
 *
 * @param array<array-key, mixed> $node
 * @return array<array-key, mixed>
 */
function bd_schema_children(array $node): array
{
    return isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
}

/**
 * Whether a control node is a grouping section rather than a leaf control.
 *
 * @param array<array-key, mixed> $node
 */
function bd_schema_is_group(array $node): bool
{
    $options = isset($node['options']) && is_array($node['options']) ? $node['options'] : [];
    $type = isset($options['type']) && is_string($options['type']) ? $options['type'] : '';
    if ($type === 'section') {
        return true;
    }
    return $type === '' && bd_schema_children($node) !== [];
}

/**
 * Build the compact leaf row for a control node.
 *
 * @param array<array-key, mixed> $node
 * @return array<string, mixed>
 */
function bd_schema_leaf_row(array $node, string $path): array
{
    $options = isset($node['options']) && is_array($node['options']) ? $node['options'] : [];
    $ctype = isset($options['type']) && is_string($options['type']) ? $options['type'] : '';
    $label = isset($node['label']) && is_string($node['label']) ? $node['label'] : '';
    $responsive = isset($node['enableMediaQueries']) && $node['enableMediaQueries'] === true;
    $row = ['path' => $path, 'label' => $label, 'type' => $ctype, 'responsive' => $responsive];
    $hint = bd_schema_value_hint($ctype);
    if ($hint !== null) {
        // A responsive scalar control wraps its value in a {"breakpoint_base": …}
        // envelope — without it the value stores but does not render.
        if ($responsive && in_array($ctype, ['unit', 'slider', 'border_radius', 'color'], strict: true)) {
            $hint = '{"breakpoint_base": ' . $hint . '}';
        }
        $row['value'] = $hint;
    }
    $enum = bd_schema_enum_options($options);
    if ($enum !== []) {
        $row['options'] = $enum;
    }
    return $row;
}

/**
 * Count the leaf (non-group) controls under a set of nodes, recursively.
 *
 * @param array<array-key, mixed> $nodes
 */
function bd_schema_count_leaves(array $nodes): int
{
    $count = 0;
    foreach (bd_schema_array_nodes($nodes) as $node) {
        if (!bd_schema_is_group($node)) {
            $count++;
            continue;
        }
        $count += bd_schema_count_leaves(bd_schema_children($node));
    }
    return $count;
}

/**
 * A short, copy-pasteable value-shape hint for a Breakdance control type, or
 * null when the shape is best discovered by setting it once and reading back
 * with get-element. Shapes verified against the Breakdance source. A
 * `responsive` control (its `responsive` flag is true) wraps the value in a
 * `{"breakpoint_base": <value>}` envelope — without it the value stores but
 * does NOT render.
 */
function bd_schema_value_hint(string $type): ?string
{
    if ($type === 'spacing_complex') {
        // Per-side, responsive; each side carries an explicit `style` string.
        return '{"breakpoint_base":{"top":{"number":24,"unit":"px","style":"24px"},"right":{…},"bottom":{…},"left":{…}}}';
    }
    return bd_schema_base_hint($type);
}

/**
 * The un-wrapped value-shape hint for a control type, before any responsive
 * `breakpoint_base` envelope is applied. Null when best discovered via
 * get-element.
 */
function bd_schema_base_hint(string $type): ?string
{
    return match ($type) {
        'unit',
        'slider',
        'border_radius',
            => '{"number":24,"unit":"px","style":"24px"}  (units: px, em, rem, %, vw, vh, fr, deg, ms)',
        'color' => '"#RRGGBBAA"  (8-digit hex incl. alpha) | "var(--token-id)"',
        'toggle', 'switcher' => 'true | false',
        'text', 'textarea', 'code' => '"a string"',
        'number' => '12  (a bare number)',
        'typography' => 'object — set once and read back with get-element for the exact keys',
        'wpmedia' => '{"id":123,"url":"https://…","size":"full"}',
        default => null,
    };
}

/**
 * Enum values for dropdown / button_bar controls, taken from `options.items`
 * (each item is `{value, text}`). Empty for non-enum controls.
 *
 * @param array<array-key, mixed> $options
 * @return list<string>
 */
function bd_schema_enum_options(array $options): array
{
    if (!isset($options['items']) || !is_array($options['items'])) {
        return [];
    }
    $values = [];
    foreach (bd_schema_array_nodes($options['items']) as $item) {
        if (!isset($item['value']) || !(is_string($item['value']) || is_int($item['value']))) {
            continue;
        }
        $values[] = (string) $item['value'];
    }
    return $values;
}
