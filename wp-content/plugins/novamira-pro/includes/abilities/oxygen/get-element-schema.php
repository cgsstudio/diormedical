<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-get-element-schema', [
    'label' => __('Get Oxygen Element Schema', domain: 'novamira-pro'),
    'description' => __(
        'Progressive-disclosure schema for an Oxygen element\'s controls — the `content.*`, `design.*`, `settings.*`, and native binding-metadata paths, with each control\'s type and allowed enum values. With no other argument you get a compact group map; drill with `group` or `search`. Oxygen global selectors are bound by stable ids at `meta.classes` (prefer oxygen-apply-global-class). The exact serialized value of compound controls is best confirmed by setting it once and reading it back.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Element type slug, e.g. `OxygenElements\\Container` or `OxygenElements\\PhpCode`. Casing is normalised against the live registry; discover valid slugs via novamira/oxygen-list-element-types.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_get_element_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call with just `type` first for the group map, then drill with `group` or `search`. Paths returned here are the exact `properties` paths for future add-element / edit-element calls. Pair with the SKILL\'s style hierarchy — prefer global settings / variables / global classes over raw per-element values.',
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
function oxygen_get_element_schema(array $input): array|WP_Error
{
    $type = isset($input['type']) && is_string($input['type']) ? trim($input['type']) : '';
    if ($type === '') {
        return ox_error('oxygen_invalid_input', __(
            '`type` is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    $resolved = ox_resolve_element_type($type);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }

    $tab_filter = isset($input['tab']) && is_string($input['tab']) ? strtolower(trim($input['tab'])) : null;
    $group = ox_schema_string_arg($input, key: 'group');
    $search = ox_schema_string_arg($input, key: 'search');

    $indexed = ox_schema_index($resolved, $tab_filter);

    if ($group === null && $search === null) {
        return [
            'type' => $resolved,
            'groups' => ox_schema_top_groups($indexed['groups']),
            'total' => count($indexed['groups']),
        ];
    }

    $rows = ox_schema_filter_leaves($indexed['leaves'], $group, $search);
    return ['type' => $resolved, 'controls' => $rows, 'total' => count($rows)];
}

/**
 * Read a trimmed non-empty string input, or null.
 *
 * @param array<string, mixed> $input
 */
function ox_schema_string_arg(array $input, string $key): ?string
{
    if (!isset($input[$key]) || !is_string($input[$key])) {
        return null;
    }
    $value = trim($input[$key]);
    return $value === '' ? null : $value;
}

/**
 * Collect a resolved element's control tabs and flatten them into a leaf
 * map (keyed by path) and a group map (keyed by path).
 *
 * @return array{leaves: array<string, array<string, mixed>>, groups: array<string, array{tab: string, group: string, path: string, depth: int, count: int}>}
 */
function ox_schema_index(string $class_slug, ?string $tab_filter): array
{
    $class = ox_element_classes()[$class_slug] ?? $class_slug;
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
            ox_schema_walk($tree, $tab, depth: 1, leaves: $leaves, groups: $groups);
        }
    }
    // The builder's Settings tab (CSS classes, display conditions,
    // draft) is UI-injected and identical for every element, so those
    // per-node paths never come out of the control trees. Expose them
    // as synthetic rows.
    if ($tab_filter === null || $tab_filter === 'settings') {
        $builder_rows = ox_schema_builder_settings_rows();
        foreach ($builder_rows as $path => $row) {
            $leaves[$path] = $row;
        }
        foreach (['settings.advanced', 'settings.conditions', 'meta'] as $prefix) {
            $segments = explode('.', $prefix);
            $slug = $segments[count($segments) - 1];
            $count = count(array_filter(array_keys($builder_rows), static fn(string $p): bool => str_starts_with(
                $p,
                $prefix . '.',
            )));
            $groups[$prefix] = [
                'tab' => $prefix === 'meta' ? 'meta' : 'settings',
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
 * Synthetic leaf rows for the builder-injected Settings tab, shared by
 * every element type. Real per-node `properties` paths the renderer
 * reads; they just have no PHP control definition to walk.
 *
 * @return array<string, array<string, mixed>>
 */
function ox_schema_builder_settings_rows(): array
{
    return [
        'meta.classes' => [
            'path' => 'meta.classes',
            'label' => 'Oxygen Selector IDs',
            'type' => 'selector_id_list',
            'responsive' => false,
            'value' => '["selector-id-1","selector-id-2"]  (stable ids from oxygen-list-global-classes; prefer oxygen-apply-global-class)',
        ],
        'settings.advanced.draft' => [
            'path' => 'settings.advanced.draft',
            'label' => 'Draft (hide on the front end without deleting)',
            'type' => 'toggle',
            'responsive' => false,
            'value' => 'true | false',
        ],
        'settings.advanced.css' => [
            'path' => 'settings.advanced.css',
            'label' => 'Custom CSS (per-element; the engine renders `settings.advanced.css`)',
            'type' => 'code',
            'responsive' => true,
            'value' => '{"breakpoint_base": "%%SELECTOR%% { color: red; }"}  (full rule blocks, %%SELECTOR%% = this element\'s selector; a bare string is also accepted and applies at the base breakpoint. Last resort — prefer variables / global classes. The legacy `settings.advanced.custom_css` alias is normalised to this key on write)',
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
            'value' => 'group / rule shape — see the oxygen-build-page skill for the vocabulary',
        ],
    ];
}

/**
 * The top-level (depth 1) groups, projected to the compact overview row.
 *
 * @param array<string, array{tab: string, group: string, path: string, depth: int, count: int}> $groups
 * @return list<array{tab: string, group: string, path: string, count: int}>
 */
function ox_schema_top_groups(array $groups): array
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
 * Leaf controls whose path/label match the optional group and search
 * filters.
 *
 * @param array<string, array<string, mixed>> $leaves
 * @return list<array<string, mixed>>
 */
function ox_schema_filter_leaves(array $leaves, ?string $group, ?string $search): array
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
 * Walk an Oxygen control tree, collecting leaf controls (with their full
 * `properties` path) into $leaves and grouping nodes into $groups.
 *
 * @param array<array-key, mixed> $nodes
 * @param array<string, array<string, mixed>> $leaves
 * @param array<string, array{tab: string, group: string, path: string, depth: int, count: int}> $groups
 */
function ox_schema_walk(array $nodes, string $prefix, int $depth, array &$leaves, array &$groups): void
{
    $tab = explode('.', $prefix)[0];
    foreach (ox_schema_array_nodes($nodes) as $node) {
        $slug = isset($node['slug']) && is_string($node['slug']) ? $node['slug'] : '';
        if ($slug === '') {
            continue;
        }
        $path = $prefix . '.' . $slug;
        if (!ox_schema_is_group($node)) {
            $leaves[$path] = ox_schema_leaf_row($node, $path);
            continue;
        }
        $children = ox_schema_children($node);
        $groups[$path] = [
            'tab' => $tab,
            'group' => $slug,
            'path' => $path,
            'depth' => $depth,
            'count' => ox_schema_count_leaves($children),
        ];
        ox_schema_walk($children, $path, $depth + 1, $leaves, $groups);
    }
}

/**
 * Keep only the array-shaped entries of a (possibly mixed) node list.
 *
 * @param array<array-key, mixed> $nodes
 * @return list<array<array-key, mixed>>
 */
function ox_schema_array_nodes(array $nodes): array
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
function ox_schema_children(array $node): array
{
    return isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
}

/**
 * Whether a control node is a grouping section rather than a leaf
 * control.
 *
 * @param array<array-key, mixed> $node
 */
function ox_schema_is_group(array $node): bool
{
    $options = isset($node['options']) && is_array($node['options']) ? $node['options'] : [];
    $type = isset($options['type']) && is_string($options['type']) ? $options['type'] : '';
    if ($type === 'section') {
        return true;
    }
    return $type === '' && ox_schema_children($node) !== [];
}

/**
 * Build the compact leaf row for a control node.
 *
 * @param array<array-key, mixed> $node
 * @return array<string, mixed>
 */
function ox_schema_leaf_row(array $node, string $path): array
{
    $options = isset($node['options']) && is_array($node['options']) ? $node['options'] : [];
    $ctype = isset($options['type']) && is_string($options['type']) ? $options['type'] : '';
    $label = isset($node['label']) && is_string($node['label']) ? $node['label'] : '';
    $responsive = isset($node['enableMediaQueries']) && $node['enableMediaQueries'] === true;
    $row = ['path' => $path, 'label' => $label, 'type' => $ctype, 'responsive' => $responsive];
    $hint = ox_schema_value_hint($ctype);
    if ($hint !== null) {
        if ($responsive && in_array($ctype, ['unit', 'slider', 'border_radius', 'color'], strict: true)) {
            $hint = '{"breakpoint_base": ' . $hint . '}';
        }
        $row['value'] = $hint;
    }
    $enum = ox_schema_enum_options($options);
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
function ox_schema_count_leaves(array $nodes): int
{
    $count = 0;
    foreach (ox_schema_array_nodes($nodes) as $node) {
        if (!ox_schema_is_group($node)) {
            $count++;
            continue;
        }
        $count += ox_schema_count_leaves(ox_schema_children($node));
    }
    return $count;
}

/**
 * A short, copy-pasteable value-shape hint for an Oxygen control type,
 * or null when the shape is best discovered by setting it once and
 * reading back with get-element. A `responsive` control wraps the value
 * in a `{"breakpoint_base": <value>}` envelope.
 */
function ox_schema_value_hint(string $type): ?string
{
    if ($type === 'spacing_complex') {
        return '{"breakpoint_base":{"top":{"number":24,"unit":"px","style":"24px"},"right":{…},"bottom":{…},"left":{…}}}';
    }
    return ox_schema_base_hint($type);
}

/**
 * The un-wrapped value-shape hint for a control type, before any
 * responsive `breakpoint_base` envelope is applied.
 */
function ox_schema_base_hint(string $type): ?string
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
        'wp_query'
            => 'query-loop envelope (content.query.query) — do NOT hand-write it: the renderer reads many sibling keys without defaults, so a partial object silently breaks the query. Configure the loop with oxygen-set-loop (post-type source, posts_per_page, order, or a DynamicDataLoop repeater), which writes the full content.query.query + content.repeated_block.global_block for you.',
        default => null,
    };
}

/**
 * Enum values for dropdown / button_bar controls, taken from
 * `options.items` (each item is `{value, text}`). Empty for non-enum
 * controls.
 *
 * @param array<array-key, mixed> $options
 * @return list<string>
 */
function ox_schema_enum_options(array $options): array
{
    if (!isset($options['items']) || !is_array($options['items'])) {
        return [];
    }
    $values = [];
    foreach (ox_schema_array_nodes($options['items']) as $item) {
        if (!isset($item['value']) || !(is_string($item['value']) || is_int($item['value']))) {
            continue;
        }
        $values[] = (string) $item['value'];
    }
    return $values;
}
