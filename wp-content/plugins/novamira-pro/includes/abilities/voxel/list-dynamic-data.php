<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate Voxel's dynamic data surface (groups, property trees,
 * modifiers) through Voxel's own Dynamic_Data\Exporter, so the answer always
 * reflects the installed Voxel version, the site's configured post fields, and
 * any filter-registered extensions — never a hand-maintained list.
 */

wp_register_ability('novamira/voxel-list-dynamic-data', [
    'label' => __('List Voxel Dynamic Data', domain: 'novamira-pro'),
    'description' => __(
        'Discover Voxel\'s dynamic data surface — the groups and properties usable as dynamic tags like @post(title), @user(email), @site(url), @term(label). Progressive disclosure: with no input it returns the group summary (property counts, group methods, the Voxel post type keys available for @post) plus usage notes; pass group to get that group\'s full property tree as flat dot-paths, and for group "post" pass post_type to include that post type\'s configured fields. Pass include_modifiers to list the modifier registry (.fallback(), .date_format(), .is_equal_to(x).then(y).else(z), ...). Render an expression with novamira/voxel-render-dynamic-data.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'group' => [
                'type' => 'string',
                'enum' => ['post', 'user', 'site', 'term', 'simple-post', 'value'],
                'description' => 'Return the property tree of one group. "user" also covers @author (the same group bound to the post author). "simple-post" is an export-only type: it backs subgroup references but is not directly addressable as a tag (group keys cannot contain hyphens, so @simple-post(...) never parses). Omit for the group summary.',
            ],
            'post_type' => [
                'type' => 'string',
                'description' => 'Only with group "post": include the configured fields of this post type (default "post"). Each post type exposes a different @post(...) tree.',
            ],
            'include_hidden' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Also list hidden/soft-deprecated properties.',
            ],
            'include_modifiers' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Also list the modifier registry (value modifiers and control structures).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'mode' => ['type' => 'string', 'enum' => ['summary', 'group']],
            'groups' => [
                'type' => 'array',
                'description' => 'Summary mode: one row per dynamic data group.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'group' => ['type' => 'string'],
                        'export_key' => ['type' => 'string'],
                        'properties' => ['type' => 'integer'],
                        'methods' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['group', 'export_key', 'properties', 'methods'],
                    'additionalProperties' => false,
                ],
            ],
            'post_types' => [
                'type' => 'array',
                'description' => 'Summary mode: Voxel-managed post type keys usable as post_type for the @post tree.',
                'items' => ['type' => 'string'],
            ],
            'group' => ['type' => 'string'],
            'export_key' => ['type' => 'string'],
            'properties' => [
                'type' => 'array',
                'description' => 'Group mode: flattened property rows. path is the dot-path used inside the tag, e.g. @post(post_type.key).',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'hidden' => ['type' => 'boolean'],
                        'subgroup' => [
                            'type' => 'string',
                            'description' => 'This property nests another group\'s whole tree; list that group (or @post with the referenced post type) to expand it.',
                        ],
                    ],
                    'required' => ['path', 'type', 'label'],
                    'additionalProperties' => false,
                ],
            ],
            'aliases' => [
                'type' => 'array',
                'description' => 'Group mode: legacy property aliases (e.g. :title -> title) still accepted by the engine.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'alias' => ['type' => 'string'],
                        'property' => ['type' => 'string'],
                    ],
                    'required' => ['alias', 'property'],
                    'additionalProperties' => false,
                ],
            ],
            'methods' => [
                'type' => 'array',
                'description' => 'Group mode: group methods, called with modifier syntax on an empty property, e.g. @post().meta(key).',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'arguments' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['key', 'label', 'arguments'],
                    'additionalProperties' => false,
                ],
            ],
            'related_groups' => [
                'type' => 'array',
                'description' => 'Group mode: export keys of groups referenced as subgroups by this tree.',
                'items' => ['type' => 'string'],
            ],
            'modifiers' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'type' => [
                            'type' => 'string',
                            'description' => '"modifier" transforms the value; "control-structure" gates it (.then()/.else()).',
                        ],
                        'label' => ['type' => 'string'],
                        'expects' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'arguments' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['key', 'type', 'label', 'expects', 'arguments'],
                    'additionalProperties' => false,
                ],
            ],
            'notes' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['mode', 'notes'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_dynamic_data',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Start with no input for the group summary, then request one group\'s tree. The @post tree differs per post type — pass post_type. Rows with a subgroup key nest another group\'s whole tree: list that group (for "post_type:<key>" call again with group "post" and that post_type) instead of expecting it inline.',
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
function voxel_list_dynamic_data(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    /** @var mixed $group */
    $group = $input['group'] ?? null;
    if ($group !== null && !in_array($group, ['post', 'user', 'site', 'term', 'simple-post', 'value'], strict: true)) {
        return vx_invalid('group must be one of: post, user, site, term, simple-post, value.');
    }

    /** @var mixed $post_type_key */
    $post_type_key = $input['post_type'] ?? null;
    if ($post_type_key !== null && !is_string($post_type_key)) {
        return vx_invalid('post_type must be a string.');
    }
    if ($post_type_key !== null && $group !== 'post') {
        return vx_invalid('post_type is only valid together with group "post".');
    }

    $result = is_string($group)
        ? dd_group_detail($group, $post_type_key ?? 'post', ($input['include_hidden'] ?? false) === true)
        : dd_group_summary();
    if ($result instanceof WP_Error) {
        return $result;
    }

    // The mode builders always carry the modifier registry from their own
    // exporter run; expose it only when asked, to keep the default compact.
    if (($input['include_modifiers'] ?? false) !== true) {
        unset($result['modifiers']);
    }

    return $result;
}

/**
 * Summary mode: one compact row per group, plus the post type keys available
 * for the @post tree and short usage notes.
 *
 * @return array<string, mixed>
 */
function dd_group_summary(): array
{
    $export = dd_export(['user', 'site', 'simple-post', 'term', 'value'], post_type_key: 'post');
    $exports = $export['groups'];

    $rows = [];
    foreach ([
        'post' => 'post_type:post',
        'user' => 'user',
        'site' => 'site',
        'term' => 'term',
        'simple-post' => 'simple-post',
        'value' => 'value',
    ] as $group_key => $export_key) {
        $group_export = $exports[$export_key] ?? null;
        if ($group_export === null) {
            continue;
        }
        $rows[] = [
            'group' => $group_key,
            'export_key' => $export_key,
            'properties' => count(dd_flatten_properties($group_export['exports'], include_hidden: false)),
            'methods' => array_map(static fn(array $method): string => $method['key'], dd_method_rows($group_export)),
        ];
    }

    $post_type_keys = [];
    foreach (\Voxel\Post_Type::get_voxel_types() as $post_type) {
        $post_type_keys[] = $post_type->get_key();
    }

    return [
        'mode' => 'summary',
        'groups' => $rows,
        'post_types' => $post_type_keys,
        'modifiers' => dd_modifier_rows($export['modifiers']),
        'notes' => [
            'Tag-addressable render contexts: @post, @user, @author, @site, @term — @author is the user group bound to the post author. The counts above are for the "post" post type; every post type exposes its own @post tree.',
            'simple-post and value are export-only engine types, not parseable tags: simple-post backs subgroup references (group keys cannot contain hyphens), and @value() is only meaningful inside modifier arguments.',
            'Call again with group=<key> for a full property tree (group "post" accepts post_type), and include_modifiers=true for the modifier registry.',
        ],
    ];
}

/**
 * Group mode: the flattened property tree of one group.
 *
 * @return array<string, mixed>|WP_Error
 */
// @mago-expect lint:no-boolean-flag-parameter -- include_hidden is the ability input passed through
function dd_group_detail(string $group, string $post_type_key, bool $include_hidden): array|WP_Error
{
    if ($group === 'post' && \Voxel\Post_Type::get($post_type_key) === null) {
        return vx_not_found('post_type', $post_type_key);
    }

    $is_post = $group === 'post';
    $export = dd_export($is_post ? [] : [$group], post_type_key: $is_post ? $post_type_key : null);
    $exports = $export['groups'];
    $export_key = $is_post ? 'post_type:' . $post_type_key : $group;

    $group_export = $exports[$export_key] ?? null;
    if ($group_export === null) {
        return vx_operation_failed(sprintf('Voxel did not export dynamic data group "%s".', $export_key));
    }

    $aliases = [];
    /** @var mixed $property */
    foreach ($group_export['aliases'] as $alias => $property) {
        if (!is_string($property)) {
            continue;
        }
        $aliases[] = ['alias' => (string) $alias, 'property' => $property];
    }

    $related = array_values(array_filter(array_keys($exports), static fn(string $key): bool => $key !== $export_key));

    $note = $group === 'simple-post'
        ? 'simple-post is an export-only engine type, NOT a parseable tag (group keys cannot contain hyphens, so @simple-post(...) never parses). These paths apply inside a parent property that references this subgroup.'
        : 'Use a path inside the tag: @'
        . $group
        . '(<path>). Rows with a subgroup nest that group\'s whole tree — list it separately (for "post_type:<key>" call group "post" with that post_type).';

    return [
        'mode' => 'group',
        'group' => $group,
        'export_key' => $export_key,
        'properties' => dd_flatten_properties($group_export['exports'], $include_hidden),
        'aliases' => $aliases,
        'methods' => dd_method_rows($group_export),
        'related_groups' => $related,
        'modifiers' => dd_modifier_rows($export['modifiers']),
        'notes' => [$note],
    ];
}

/**
 * Run Voxel's exporter over a set of registry group keys, resetting the mutable
 * singleton before and after so no state leaks between requests (or in from the
 * theme's own admin screens). Both the group trees and the modifier registry
 * come from this single Exporter run — the runtime source of truth.
 *
 * @param list<string> $registry_keys keys from Voxel's data-group registry (no mock args)
 * @param string|null $post_type_key when set, also export the post group mocked for this post type
 * @return array{groups: array<string, array{aliases: array<array-key, mixed>, exports: array<array-key, mixed>, methods: array<array-key, mixed>}>, modifiers: array<array-key, mixed>}
 */
function dd_export(array $registry_keys, ?string $post_type_key): array
{
    $exporter = \Voxel\Dynamic_Data\Exporter::get();
    $exporter->reset();
    try {
        foreach ($registry_keys as $registry_key) {
            $exporter->add_group_by_key($registry_key);
        }
        if ($post_type_key !== null) {
            $exporter->add_group_by_key('post', $post_type_key);
        }
        $export = $exporter->export();
    } finally {
        $exporter->reset();
    }

    $groups = [];
    /** @var mixed $group_export */
    foreach ($export['groups'] as $export_key => $group_export) {
        if (!is_array($group_export)) {
            continue;
        }
        $groups[$export_key] = [
            'aliases' => dd_to_array($group_export['aliases'] ?? null),
            'exports' => dd_to_array($group_export['exports'] ?? null),
            'methods' => dd_to_array($group_export['methods'] ?? null),
        ];
    }

    return ['groups' => $groups, 'modifiers' => $export['modifiers']];
}

/**
 * Normalize the exporter's stdClass-or-array values to arrays. The exporter
 * object-casts aliases/exports/methods maps for JSON output.
 *
 * @return array<array-key, mixed>
 */
function dd_to_array(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if ($value instanceof \stdClass) {
        return get_object_vars($value);
    }
    return [];
}

/**
 * Flatten a property-export tree into compact rows.
 *
 * @param array<array-key, mixed> $exports
 * @return list<array{path: string, type: string, label: string, hidden?: bool, subgroup?: string}>
 */
// @mago-expect lint:no-boolean-flag-parameter -- include_hidden is the ability input passed through
function dd_flatten_properties(array $exports, bool $include_hidden, bool $parent_hidden = false): array
{
    $rows = [];
    /** @var mixed $export */
    foreach ($exports as $export) {
        if (!is_array($export)) {
            continue;
        }
        foreach (dd_flatten_node($export, $include_hidden, $parent_hidden) as $row) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * Rows for one property export. Plain object containers are skipped (their
 * leaves carry the paths); object-list containers keep a row so list semantics
 * stay visible; subgroup references are emitted as pointers instead of
 * inlining a whole other group.
 *
 * @param array<array-key, mixed> $export
 * @return list<array{path: string, type: string, label: string, hidden?: bool, subgroup?: string}>
 */
// @mago-expect lint:no-boolean-flag-parameter -- include_hidden is the ability input passed through
function dd_flatten_node(array $export, bool $include_hidden, bool $parent_hidden): array
{
    $hidden = $parent_hidden || ($export['hidden'] ?? false) === true;
    if ($hidden && !$include_hidden) {
        return [];
    }

    $row = dd_property_row($export, $hidden);

    /** @var mixed $subgroup */
    $subgroup = $export['subgroup'] ?? null;
    if (is_array($subgroup) && isset($subgroup['key']) && is_string($subgroup['key'])) {
        $row['subgroup'] = $subgroup['key'];
        return [$row];
    }

    /** @var mixed $children */
    $children = $export['exports'] ?? null;
    if (!is_array($children) || $children === []) {
        return [$row];
    }

    $rows = $row['type'] === 'object-list' ? [$row] : [];
    foreach (dd_flatten_properties($children, $include_hidden, $hidden) as $child_row) {
        $rows[] = $child_row;
    }

    return $rows;
}

/**
 * The compact row for one property export (without subgroup/children logic).
 *
 * @param array<array-key, mixed> $export
 * @return array{path: string, type: string, label: string, hidden?: bool, subgroup?: string}
 */
// @mago-expect lint:no-boolean-flag-parameter -- hidden state resolved by the caller (parent containers inherit)
function dd_property_row(array $export, bool $hidden): array
{
    $segments = [];
    /** @var mixed $path */
    $path = $export['path'] ?? null;
    if (is_array($path)) {
        /** @var mixed $segment */
        foreach ($path as $segment) {
            $segments[] = is_scalar($segment) ? (string) $segment : '';
        }
    }

    $row = [
        'path' => implode('.', $segments),
        'type' => isset($export['type']) && is_string($export['type']) ? $export['type'] : '',
        'label' => isset($export['label']) && is_string($export['label']) ? $export['label'] : '',
    ];
    if ($hidden) {
        $row['hidden'] = true;
    }

    return $row;
}

/**
 * Compact rows for a group's methods (modifier-syntax calls like .meta(key)).
 *
 * @param array{aliases: array<array-key, mixed>, exports: array<array-key, mixed>, methods: array<array-key, mixed>} $group_export
 * @return list<array{key: string, label: string, arguments: list<string>}>
 */
function dd_method_rows(array $group_export): array
{
    $rows = [];
    /** @var mixed $config */
    foreach ($group_export['methods'] as $method_key => $config) {
        $config_arr = dd_to_array($config);
        $rows[] = [
            'key' => (string) $method_key,
            'label' => isset($config_arr['label']) && is_string($config_arr['label']) ? $config_arr['label'] : '',
            'arguments' => dd_argument_labels($config_arr),
        ];
    }

    return $rows;
}

/**
 * Compact rows for the modifier registry, straight from the Exporter's own
 * export (each entry is a modifier's editor config keyed by modifier key).
 *
 * @param array<array-key, mixed> $modifier_exports
 * @return list<array{key: string, type: string, label: string, expects: list<string>, arguments: list<string>}>
 */
function dd_modifier_rows(array $modifier_exports): array
{
    $rows = [];
    /** @var mixed $config */
    foreach ($modifier_exports as $modifier_key => $config) {
        $config_arr = dd_to_array($config);
        $rows[] = [
            'key' => (string) $modifier_key,
            'type' => isset($config_arr['type']) && is_string($config_arr['type']) ? $config_arr['type'] : 'modifier',
            'label' => isset($config_arr['label']) && is_string($config_arr['label']) ? $config_arr['label'] : '',
            'expects' => dd_string_list($config_arr['expects'] ?? null),
            'arguments' => dd_argument_labels($config_arr),
        ];
    }

    return $rows;
}

/**
 * Argument labels from a modifier/method editor config.
 *
 * @param array<array-key, mixed> $config
 * @return list<string>
 */
function dd_argument_labels(array $config): array
{
    $labels = [];
    /** @var mixed $argument */
    foreach (dd_to_array($config['arguments'] ?? null) as $argument) {
        if (!is_array($argument)) {
            continue;
        }
        $labels[] = isset($argument['label']) && is_string($argument['label']) ? $argument['label'] : '';
    }

    return $labels;
}

/**
 * The string members of a mixed list value.
 *
 * @return list<string>
 */
function dd_string_list(mixed $value): array
{
    $out = [];
    /** @var mixed $item */
    foreach (dd_to_array($value) as $item) {
        if (!is_string($item)) {
            continue;
        }
        $out[] = $item;
    }

    return $out;
}
