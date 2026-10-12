<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-get-structure-schema', [
    'label' => __('Get Beaver Builder Structure Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the settings schema for a Beaver Builder structural node — a `row` or a `column` — so an agent can set the exact keys (background, padding/margin, column `size`, borders, visibility) instead of guessing them. Rows and columns are NOT modules, so get-module does not cover them; this is their counterpart. Given `type` (row|column) it reports the typed fields walked from Beaver\'s settings form (key, type, label, tab, section, options, default). Progressive disclosure: narrow with `search` (key/label substring, case-insensitive), `tab` ("style"/"advanced"), or `section` instead of reading the whole form — a row form is ~80 fields, so a background lookup is `type=row search="bg"`, spacing is `search="padding"` or `search="margin"`, and column width is `type=column search="size"`; the filters AND-combine and `fields_total`/`fields_matched` report the match count. Background keys: set `bg_type` (color|gradient|photo|video|parallax) PLUS the matching key — `bg_color`, or `bg_gradient` {type,angle,position,colors,stops}, or `bg_image`/`bg_image_url`; `bg_type` defaults to "none", so a `bg_color` with no `bg_type` renders nothing. `include_defaults` (the flat get_row_defaults/get_col_defaults map) defaults to false because each field row already carries its own default.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'enum' => ['row', 'column'],
                'description' => 'Which structural node to describe: a row or a column.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Keep only fields whose key OR label contains this case-insensitive substring. Use it to narrow the large settings form, e.g. search:"bg" for background keys, search:"padding"/"margin" for spacing, search:"size" for the column width. Combines (AND) with tab/section.',
            ],
            'tab' => [
                'type' => 'string',
                'description' => 'Keep only fields in this form tab (e.g. "style", "advanced"). Combines (AND) with search/section.',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'Keep only fields in this form section id (e.g. "bg_color", "border", "margins"). Combines (AND) with search/tab.',
            ],
            'include_defaults' => [
                'type' => 'boolean',
                'description' => 'Include the flat default-settings map (FLBuilderModel::get_row_defaults / get_col_defaults). Defaults to false because each `fields` row already carries its own `default`.',
            ],
            'include_fields' => [
                'type' => 'boolean',
                'description' => 'Include the typed field list walked from the settings form. Defaults to true.',
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
        'required' => ['type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string'],
            'name' => ['type' => 'string'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_get_structure_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Progressive disclosure for the structural nodes get-module cannot describe: rows and columns. The `fields` rows carry the exact setting keys (key/type/label/options + each row\'s own default) you write into a row/column node settings object via set-layout/add-node/edit-node. NARROW instead of dumping the whole form: search="bg" / search="padding" / search="margin" matches a field key OR label (case-insensitive), tab="style"/"advanced" and section=<id> scope to part of the form, and they combine with AND — a row is ~80 fields, a column somewhat fewer. Backgrounds: set `bg_type` (color|gradient|photo|video|parallax) PLUS the matching key (bg_color / bg_gradient / bg_image|bg_image_url); the `bg_type` default is "none", so bg_color alone renders nothing. Column width is the `size` field (a percent number); set it on every column or the column renders collapsed. Style with the row/column settings and Beaver Global Colors/Fonts — never inject custom CSS via bb_css_code.',
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
function beaver_builder_get_structure_schema(array $input): array|WP_Error
{
    $type = bb_string_arg($input['type'] ?? '');
    if (!in_array($type, ['row', 'column'], strict: true)) {
        return bb_error(
            code: 'beaver_builder_invalid_structure_type',
            message: 'Pass `type` as "row" or "column".',
            data: ['status' => 400],
        );
    }

    if (!class_exists('FLBuilderModel')) {
        return bb_error(
            code: 'beaver_builder_runtime_unavailable',
            message: 'Beaver Builder is not available on this site.',
            data: ['status' => 503],
        );
    }

    $caps = [
        'max_fields' => bb_int_arg(input: $input, key: 'max_fields', default: 300, min: 1, max: 1000),
        'max_options' => bb_int_arg(input: $input, key: 'max_options', default: 50, min: 0, max: 500),
        'max_field_bytes' => bb_int_arg(input: $input, key: 'max_field_bytes', default: 2048, min: 0, max: 1_000_000),
    ];

    $result = [
        'type' => $type,
        'name' => $type === 'row' ? __('Row', domain: 'novamira-pro') : __('Column', domain: 'novamira-pro'),
        'accepts_children' => true,
    ];

    if (bb_bool_arg($input['include_fields'] ?? true)) {
        $result += bb_structure_fields_section(
            form_id: $type === 'row' ? 'row' : 'col',
            caps: $caps,
            filters: bb_module_field_filters($input),
        );
    }
    if (bb_bool_arg($input['include_defaults'] ?? false)) {
        $result += bb_structure_defaults_section(type: $type, caps: $caps);
    }

    return $result;
}

/**
 * @param array{max_fields: int, max_options: int, max_field_bytes: int} $caps
 * @param array{search?: string, tab?: string, section?: string} $filters
 * @return array<string, mixed>
 */
function bb_structure_fields_section(string $form_id, array $caps, array $filters): array
{
    $walked = bb_settings_form_fields(
        form_id: $form_id,
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
function bb_structure_defaults_section(string $type, array $caps): array
{
    $bounded = bb_bounded_defaults(
        defaults: bb_structure_default_settings($type),
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
 * The flat default-settings map for a row or column, from FLBuilderModel.
 *
 * @return array<string, mixed>
 */
function bb_structure_default_settings(string $type): array
{
    $method = $type === 'row' ? 'get_row_defaults' : 'get_col_defaults';
    if (!class_exists('FLBuilderModel') || !method_exists('FLBuilderModel', $method)) {
        return [];
    }

    /** @var mixed $defaults */
    $defaults = \FLBuilderModel::{$method}();
    if (is_object($defaults)) {
        $defaults = get_object_vars($defaults);
    }
    if (!is_array($defaults)) {
        return [];
    }

    $normalized = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($defaults as $key => $value) {
        $normalized[(string) $key] = $value;
    }
    return $normalized;
}
