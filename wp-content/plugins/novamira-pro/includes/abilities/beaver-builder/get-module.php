<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-get-module', [
    'label' => __('Get Beaver Builder Module', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Beaver Builder module settings schema so an agent can set the exact keys instead of guessing them. Given a module `slug` from list-modules, it reports the typed fields walked from the module registered form (key, type, label, tab, section, options, default). Use this before set-layout/add-node/edit-node to learn a heading text key, a button link key, or a post-grid query key. Progressive disclosure: narrow with `search` (key/label substring, case-insensitive), `tab`, or `section` instead of reading the whole module — a post-grid has ~96 fields, so a query lookup is `get-module slug=post-grid search="post"` and a style lookup is `search="color"` or `tab="style"` (the filters combine with AND, and `fields_total`/`fields_matched` then report the match count). `include_defaults` (the flat get_module_defaults map) defaults to false because each field row already carries its own default; turn it on only for the exhaustive/responsive keys. Fields and defaults are bounded and large values are byte-capped.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'Module slug from list-modules (module or alias key), e.g. heading, button, post-grid.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Keep only fields whose key OR label contains this case-insensitive substring. Use it to narrow a large module instead of reading every field, e.g. post-grid + search:"post" for the query keys, or search:"color" for the color fields. Combines (AND) with tab/section.',
            ],
            'tab' => [
                'type' => 'string',
                'description' => 'Keep only fields in this form tab (e.g. "style", "advanced", "layout", "pagination"). Combines (AND) with search/section.',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'Keep only fields in this form section id. Combines (AND) with search/tab.',
            ],
            'include_defaults' => [
                'type' => 'boolean',
                'description' => 'Include the flat default-settings map (FLBuilderModel::get_module_defaults). Defaults to false because each `fields` row already carries its own `default`; turn it on for the exhaustive/responsive keys the form walk does not surface.',
            ],
            'include_fields' => [
                'type' => 'boolean',
                'description' => 'Include the typed field list walked from the module form. Defaults to true.',
            ],
            'max_fields' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 1000,
                'description' => 'Maximum field descriptors to return. Defaults to 300.',
            ],
            'max_options' => [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 500,
                'description' => 'Maximum option keys to list per field. Defaults to 50.',
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Per-value string byte cap for defaults and field defaults. Defaults to 2048; 0 disables.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'module' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'group' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'alias' => ['type' => 'boolean'],
            'accepts_children' => ['type' => 'boolean'],
            'fields' => ['type' => 'array', 'items' => ['type' => 'object']],
            'fields_total' => ['type' => 'integer'],
            'fields_truncated' => ['type' => 'boolean'],
            'fields_matched' => ['type' => 'integer'],
            'filters' => ['type' => 'object'],
            'defaults' => ['type' => 'object'],
            'defaults_total' => ['type' => 'integer'],
            'defaults_truncated' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_get_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Progressive disclosure: list-modules is the catalog (slugs); this returns one module fields. The `fields` rows carry the exact setting keys (key/type/label/options + each row\'s own default) you write into a node settings object. NARROW instead of dumping the whole module: search="post" / search="color" matches a field key OR label (case-insensitive), tab="style" / section=<id> scope to part of the form, and they combine with AND — e.g. a post-grid query is get-module slug=post-grid search="post", a style lookup is search="color" or tab="style". `fields_matched` reports how many fields passed the filters. `include_defaults` (the flat get_module_defaults map) is OFF by default since fields already carry defaults; enable it for the exhaustive/responsive keys. The query keys for the Posts modules (post-grid/post-carousel/post-slider) such as data_source and post_type are added by Beaver loop settings at edit time and may not all appear in defaults; see the build-page skill loop section.',
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
function beaver_builder_get_module(array $input): array|WP_Error
{
    $slug = bb_string_arg($input['slug'] ?? '');
    if ($slug === '') {
        return bb_error(code: 'beaver_builder_invalid_module', message: 'Pass a non-empty module `slug`.', data: [
            'status' => 400,
        ]);
    }

    $module = bb_resolve_module_slug(slug: $slug, scope: 'all');
    if ($module === '') {
        return bb_error(
            code: 'beaver_builder_unknown_module',
            message: sprintf(
                'Module `%s` is not registered on this site. Use list-modules to find a valid slug.',
                $slug,
            ),
            data: ['status' => 404],
        );
    }

    $row = bb_module_catalog_row(module: $module, scope: 'all');
    $caps = [
        'max_fields' => bb_int_arg(input: $input, key: 'max_fields', default: 300, min: 1, max: 1000),
        'max_options' => bb_int_arg(input: $input, key: 'max_options', default: 50, min: 0, max: 500),
        'max_field_bytes' => bb_int_arg(input: $input, key: 'max_field_bytes', default: 2048, min: 0, max: 1_000_000),
    ];

    $result = [
        'slug' => $row['slug'] ?? $module,
        'module' => $module,
        'name' => $row['name'] ?? '',
        'category' => $row['category'] ?? '',
        'group' => $row['group'] ?? '',
        'description' => $row['description'] ?? '',
        'enabled' => $row['enabled'] ?? false,
        'alias' => $row['alias'] ?? false,
        'accepts_children' => bb_module_accepts_children($module),
    ];

    if (bb_bool_arg($input['include_fields'] ?? true)) {
        $result += bb_module_fields_section(module: $module, caps: $caps, filters: bb_module_field_filters($input));
    }
    if (bb_bool_arg($input['include_defaults'] ?? false)) {
        $result += bb_module_defaults_section(module: $module, caps: $caps);
    }

    return $result;
}

/**
 * Extract and normalize the field-narrowing filters (search / tab / section).
 * Only non-empty string filters are kept, so an unset or empty value never
 * narrows the walk.
 *
 * @param array<string, mixed> $input
 * @return array{search?: string, tab?: string, section?: string}
 */
function bb_module_field_filters(array $input): array
{
    $filters = [];
    foreach (['search', 'tab', 'section'] as $key) {
        $value = bb_string_arg($input[$key] ?? '');
        if ($value !== '') {
            $filters[$key] = $value;
        }
    }
    /** @var array{search?: string, tab?: string, section?: string} $filters */
    return $filters;
}

/**
 * @param array{max_fields: int, max_options: int, max_field_bytes: int} $caps
 * @param array{search?: string, tab?: string, section?: string} $filters
 * @return array<string, mixed>
 */
function bb_module_fields_section(string $module, array $caps, array $filters = []): array
{
    $instance = bb_registered_module($module);
    $walked = $instance === null
        ? ['fields' => [], 'total' => 0, 'truncated' => false]
        : bb_module_form_fields(
            module: $instance,
            max_fields: $caps['max_fields'],
            max_options: $caps['max_options'],
            max_field_bytes: $caps['max_field_bytes'],
            filters: $filters,
        );
    $section = [
        'fields' => $walked['fields'],
        'fields_total' => $walked['total'],
        'fields_truncated' => $walked['truncated'],
    ];
    if ($filters !== []) {
        $section['fields_matched'] = $walked['total'];
        $section['filters'] = (object) $filters;
    }
    return $section;
}

/**
 * @param array{max_fields: int, max_options: int, max_field_bytes: int} $caps
 * @return array<string, mixed>
 */
function bb_module_defaults_section(string $module, array $caps): array
{
    $bounded = bb_bounded_defaults(
        defaults: bb_module_default_settings($module),
        max_keys: $caps['max_fields'],
        max_field_bytes: $caps['max_field_bytes'],
    );
    return [
        'defaults' => (object) $bounded['defaults'],
        'defaults_total' => $bounded['total'],
        'defaults_truncated' => $bounded['truncated'],
    ];
}

/**
 * Whether a module node would accept child nodes (container modules such as box).
 */
function bb_module_accepts_children(string $module): bool
{
    $instance = bb_registered_module($module);
    if ($instance === null || !method_exists($instance, 'accepts_children')) {
        return false;
    }
    return $instance->accepts_children() === true;
}
